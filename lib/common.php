<?php
require_once('lib_users.php');

//sql wrappers
function sql_fetch_array($q) {
    return $q->fetch();
}
function sql_fetch_assoc($q) {
    return $q->fetch(PDO::FETCH_ASSOC);
}
function sql_num_rows($q) {
    return $q->rowCount();
}
function sql_quote($string) {
    global $pdo_db;
    return $pdo_db->quote($string);
}
function sql_begin() {
    global $transaction_counter;
    global $nested_transaction_counter;
    global $pdo_db;
    if (!$transaction_counter) {
        $pdo_db->beginTransaction();
        ++$transaction_counter;
    } else {
        ++$nested_transaction_counter;
    }
}
function sql_commit() {
    global $transaction_counter;
    global $nested_transaction_counter;
    global $pdo_db;
    if ($nested_transaction_counter) {
        --$nested_transaction_counter;
    } else {
        $pdo_db->commit();
        --$transaction_counter;
    }
}
function sql_query($q, $debug=1, $override_readonly=0) {
    global $config;
    global $pdo_db;
    global $total_time;
    global $total_queries;
    if (file_exists($config['project']['readonly_flag']) && stripos(trim($q), 'select') > 1 && !$override_readonly)
        throw new Exception("Database in readonly mode");
    $debug = isset($_SESSION['debug_mode']) && $debug;
    $time_start = microtime(true);
    if ($debug) {
        $q = preg_replace('/select /i', 'SELECT SQL_NO_CACHE ', $q, 1);
    }

    try {
        if ($debug)
            printf("<table class='debug' width='100%%'><tr><td valign='top' width='20'>%d<td>SQL: %s</td>", $total_queries, htmlspecialchars($q));
        $res = $pdo_db->query($q);
        $time = microtime(true)-$time_start;
        $total_time += $time;
        $total_queries++;
        if ($debug) {
            printf("<td width='100'>%.4f сек.</td><td width='100'>%.4f сек.</td></tr></table>\n", $time, $total_time);
        }
    }
    catch (PDOException $e) {
        if ($debug) {
            printf("<td width='100'>%.4f сек.</td><td width='100'>%.4f сек.</td></tr></table>\n", $time, $total_time);
            print "<table class='debug_error' width='100%'><tr><td colspan='3'>".htmlspecialchars($e->getMessage())."</td></tr></table>\n";
        }
        throw new Exception("DB Error");
    }
    return $res;
}
function sql_fetchall($res) {
    return $res->fetchAll();
}
function sql_insert_id() {
    // MUST BE CALLED BEFORE TRANSACTION COMMIT!
    // otherwise returns 0
    global $pdo_db;
    return $pdo_db->lastInsertId();
}
function sql_prepare($q, $override_readonly=0) {
    global $config;
    global $pdo_db;
    global $total_time;
    $debug = isset($_SESSION['debug_mode']);
    if (file_exists($config['project']['readonly_flag']) && stripos(trim($q), 'select') > 1 && !$override_readonly)
        throw new Exception("Database in readonly mode");
    $time_start = microtime(true);
    if ($debug)
        printf("<table class='debug' width='100%%'><tr><td valign='top' width='20'>*</td><td colspan='3'>PREPARE: %s</td></tr></table>\n", htmlspecialchars($q));
    try {
        $q = $pdo_db->prepare($q);
        if (!$q) {
            $einfo = $pdo_db->errorInfo();
            throw new Exception($einfo[1] . ": " . $einfo[2]);
        }
        $time = microtime(true)-$time_start;
        $total_time += $time;
        return $q;
    }
    catch (PDOException $e) {
        if ($debug)
            print "<table class='debug_error' width='100%'><tr><td colspan='3'>".htmlspecialchars($e->getMessage())."</td></tr></table>\n";
        throw new Exception("DB Error");
    }
}
function sql_execute($res, $params) {
    global $pdo_db;
    global $total_time;
    global $total_queries;
    $debug = isset($_SESSION['debug_mode']);
    $time_start = microtime(true);

    try {
        $res->execute($params);
        $time = microtime(true)-$time_start;
        $total_time += $time;
        $total_queries++;
        if ($debug) {
            printf("<table class='debug' width='100%%'><tr><td valign='top' width='20'>%d<td>SQL: %s</td><td width='100'>%.4f сек.</td><td width='100'>%.4f сек.</td></tr></table>\n", $total_queries, "(prepared statement)", $time, $total_time);
        }
    }
    catch (PDOException $e) {
        if ($debug)
            print "<table class='debug_error' width='100%'><tr><td colspan='3'>".htmlspecialchars($e->getMessage())."</td></tr></table>\n";
        throw new Exception("DB Error");
    }
}
function sql_pe($query, $params) {
    // prepares and executes query, closes cursor
    // returns all the rows
    $res = sql_prepare($query);
    sql_execute($res, $params);
    try {
        return sql_fetchall($res);
    }
    catch (PDOException $e) {
        return array();
    }
}
//sql checks
function sql_get_schema() {
    $res = sql_query("SHOW TABLES");
    $out = array();
    while ($r = sql_fetch_array($res)) {
        $out[$r[0]] = array();
        $res1 = sql_query("EXPLAIN `$r[0]`");
        while ($r1 = sql_fetch_array($res1)) {
            $out[$r[0]][] = strtolower($r1[0]);
        }
    }
    return $out;
}
//other
function is_cached($template_name, $cache_id = false) {
    if (isset($_SESSION['debug_mode'])) return false;
    global $smarty;
    return $cache_id ? $smarty->isCached($template_name, $cache_id)
                     : $smarty->isCached($template_name);
}
function show_error($text = "Произошла ошибка.") {
    global $smarty;
    $smarty->assign('error_text', $text);
    $smarty->display('error.tpl');
}

class PermissionError extends Exception {}
class NotLoggedError extends Exception {}

function oc_exception_handler($exception) {
    if ($exception instanceof PermissionError)
        show_error("У вас недостаточно прав для просмотра этой страницы.");
    elseif ($exception instanceof NotLoggedError)
        show_error("Для выполнения этого действия нужно <a href='/login.php'>войти</a> в свою учётную запись.");
    else
        show_error("Произошла ошибка.<br/><br/>" . $exception->getMessage());
}
function create_revset($comment = '') {
    if (!isset($_SESSION['user_id']) || !$_SESSION['user_id'])
        throw new Exception();

    $now = time();
    global $config;
    // check if there is a recent set by the same user with the same comment
    $timeout = $now - $config['misc']['changeset_timeout'];
    $res = sql_pe("
        SELECT set_id
        FROM rev_sets
        WHERE user_id = ?
        AND timestamp > ?
        AND comment = ?
        ORDER BY set_id DESC
        LIMIT 1
    ", array($_SESSION['user_id'], $timeout, $comment));
    if (sizeof($res) > 0) {
        sql_query("UPDATE rev_sets SET timestamp=$now WHERE set_id=".$res[0]['set_id']." LIMIT 1");
        return $res[0]['set_id'];
    }

    $q = "INSERT INTO `rev_sets` VALUES(NULL, ?, ?, ?)";
    sql_pe($q, array($now, $_SESSION['user_id'], $comment));
    return sql_insert_id();
}
function typo_spaces($str, $with_tags = 0) {
    if (!$with_tags) {
        $patterns = array(' ,', ' .', '( ', ' )', ' :', ' ;');
        $replacements = array(',', '.', '(', ')', ':', ';');
        return str_replace($patterns, $replacements, $str);
    }
    $patterns = array('/\s(<[^>]+>(?:\.|\,|\)|:|;)<\/[^>]+>)/', '/(<[^>]+>(?:\()<\/[^>]+>)\s/');
    $replacements = array('\1', '\1');
    return preg_replace($patterns, $replacements, $str);
}
function get_wiki_page($title) {
    return htmlspecialchars_decode(fetch_wiki_page($title));
}
function fetch_wiki_page($title) {
    $title = urlencode($title);
    $url = "http://localhost/w/api.php?action=query&prop=revisions&titles=$title&rvprop=content&rvparse=1&format=xml";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $contents = curl_exec($ch);
    curl_close($ch);
    return $contents;
}
function get_downloads_info() {
    global $config;
    $dict = array();
    $annot = array();
    $ngram = array();
    $ngram_path = 'files/export/ngrams/';

    $dict['xml'] = get_file_info('files/export/dict/dict.opcorpora.xml');
    $dict['txt'] = get_file_info('files/export/dict/dict.opcorpora.txt');
    $annot['xml'] = get_file_info('files/export/annot/annot.opcorpora.xml');
    $annot['disamb_xml'] = get_file_info('files/export/annot/annot.opcorpora.no_ambig.xml');
    $annot['disamb_xml_strict'] = get_file_info('files/export/annot/annot.opcorpora.no_ambig_strict.xml');

    $types1 = array('exact', 'exact_lc', 'exact_cyr', 'exact_cyr_lc');
    $types2 = array('exact', 'exact_lc', 'exact_cyrA', 'exact_cyrB', 'exact_cyrA_lc', 'exact_cyrB_lc');
    $i2word = array(1 => 'unigrams', 2 => 'bigrams', 3 => 'trigrams');

    for ($i = 1; $i <= 3; ++$i) {
        $arr = ($i == 1) ? $types1 : $types2;
        foreach ($arr as $type) {
            $ngram[$i][$type] = get_file_info($ngram_path.$i2word[$i].$config['ngram_suffixes'][$type]);
        }
    }

    $colloc['mi'] = get_file_info($ngram_path.'/colloc.MI');

    return array('dict'=>$dict, 'annot'=>$annot, 'ngram'=>$ngram, 'colloc'=>$colloc);
}
function get_file_info($path) {
    //get size and time info about a group of archives
    $mb = 1024 * 1024;
    $out = array();
    $stat = stat($path.'.bz2');
    $out['bz2']['size'] = sprintf("%.2f", $stat[7] / $mb);
    $out['updated'] = date('d.m.Y H:i \M\S\K', $stat[9]);
    $stat = stat($path.'.zip');
    $out['zip']['size'] = sprintf("%.2f", $stat[7] / $mb);
    return $out;
}
function get_top100_info($what, $type) {
    global $config;
    $stats = array();

    $filename = '';
    if ($what == 'colloc') {
        $filename = 'colloc.MI';
    } else {
        list($N, $ltype) = explode('_', $type, 2);
        switch ($N) {
            case 1:
                $filename = 'unigrams';
                break;
            case 2:
                $filename = 'bigrams';
                break;
            case 3:
                $filename = 'trigrams';
                break;
            default:
                return $stats;
        }

        if (isset($config['ngram_suffixes'][$ltype]))
            $filename .= $config['ngram_suffixes'][$ltype];
        else
            return $stats;
    }

    $f = file($config['project']['root']."/files/export/ngrams/$filename.top100");

    if ($what == 'colloc') {
        foreach ($f as $s) {
            list($lterm, $rterm, $lfreq, $rfreq, $cfreq, $coeff) = explode("\t", $s);
            $stats[] = array('lterm' => $lterm, 'rterm' => $rterm, 'lfreq' => $lfreq, 'rfreq' => $rfreq, 'cfreq' => $cfreq, 'coeff' => $coeff);
        }
    } else {
        foreach ($f as $s) {
            list($token, $abs, $ipm) = explode("\t", $s);
            $stats[] = array('token' => $token, 'abs' => $abs, 'ipm' => $ipm);
        }
    }

    return $stats;
}
function set_readonly_on() {
    check_permission(PERM_ADMIN);
    global $config;
    touch_file($config['project']['readonly_flag']);
}
function set_readonly_off() {
    check_permission(PERM_ADMIN);
    global $config;
    unlink($config['project']['readonly_flag']);
}
function touch_file($path) {
    exec("touch ".escapeshellarg($path));
}

function safe_read($file, $length) {
    $fp = fopen($file, 'r');
    if (!is_resource($fp)) {
        return FALSE;
    }
    flock($fp, LOCK_SH);
    $data = fread($fp, $length);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $data;
}

function safe_write($file, $mode, $data) {
    $fp = fopen($file, $mode);
    if (!is_resource($fp)) {
        return FALSE;
    }
    flock($fp, LOCK_EX);
    fwrite($fp, $data);
    flock($fp, LOCK_UN);
    fclose($fp);

    return TRUE;
}
/**
* saves alert message to session
* @param string $type alert type [success,error,info]
* @param string $message alert text
*/
function alert_set($type,$message) {
    switch ($type) {
        case 'success':
        case 'error':
        case 'info':
            if (!isset($_SESSION['alert'])) {
                $_SESSION['alert'] = array();
            }
            $_SESSION['alert'][$type] = $message;
            break;
    }
}
/**
* returns alert message & destroys it
* @param string $type alert type [success,error,info]
*/
function alert_get($type = '') {
    if (!isset($_SESSION['alert'])) {
        return false;
    }
    switch ($type) {
        case 'success':
        case 'error':
        case 'info':
            if (isset($_SESSION['alert'][$type])) {
                $message = $_SESSION['alert'][$type];
                unset($_SESSION['alert'][$type]);
                return $message;
            }
            break;
    }
}
/**
* returns all alert messages & destroys them
*/
function alert_getall() {
    if (!isset($_SESSION['alert'])) {
        return array();
    }
    $alert = $_SESSION['alert'];
    unset($_SESSION['alert']);
    return $alert;
}
