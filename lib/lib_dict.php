<?php
require_once('lib_xml.php');
require_once('lib_books.php');

// GENERAL
function get_dict_stats() {
    $out = array();
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_g FROM `gram`"));
    $out['cnt_g'] = $r['cnt_g'];
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_l FROM `dict_lemmata`"));
    $out['cnt_l'] = $r['cnt_l'];
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_f FROM `form2lemma`"));
    $out['cnt_f'] = $r['cnt_f'];
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_r FROM `dict_revisions` WHERE f2l_check=0"));
    $out['cnt_r'] = $r['cnt_r'];
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_v FROM `dict_revisions` WHERE dict_check=0"));
    $out['cnt_v'] = $r['cnt_v'];
    return $out;
}
function get_dict_search_results($post) {
    $out = array();
    if (isset($post['search_lemma'])) {
        $q = mysql_real_escape_string($post['search_lemma']);
        $res = sql_query("SELECT lemma_id FROM `dict_lemmata` WHERE `lemma_text`='$q'");
        $count = sql_num_rows($res);
        $out['lemma']['count'] = $count;
        if ($count == 0)
            return $out;
        while ($r = sql_fetch_array($res)) {
            $r1 = sql_fetch_array(sql_query("SELECT SUBSTR(grammems, 7, 4) AS gr FROM form2lemma WHERE lemma_id=".$r['lemma_id']." LIMIT 1"));
            $out['lemma']['found'][] = array('id' => $r['lemma_id'], 'text' => $q, 'pos' => $r1['gr']);
        }
    }
    elseif (isset($post['search_form'])) {
        $q = mysql_real_escape_string($post['search_form']);
        $res = sql_query("SELECT DISTINCT dl.lemma_id, dl.lemma_text FROM `form2lemma` fl LEFT JOIN `dict_lemmata` dl ON (fl.lemma_id=dl.lemma_id) WHERE fl.`form_text`='$q'");
        $count = sql_num_rows($res);
        $out['form']['count'] = $count;
        if ($count == 0)
            return $out;
        while ($r = sql_fetch_array($res)) {
            $r1 = sql_fetch_array(sql_query("SELECT SUBSTR(grammems, 7, 4) AS gr FROM form2lemma WHERE lemma_id=".$r['lemma_id']." LIMIT 1"));
            $out['form']['found'][] = array('id' => $r['lemma_id'], 'text' => $r['lemma_text'], 'pos' => $r1['gr']);
        }
    }
    return $out;
}
function generate_tf_rev($token) {
    $out = '<tfr t="'.htmlspecialchars($token).'">';
    if (preg_match('/^[А-Яа-яЁё][А-Яа-яЁё\-\']*$/u', $token)) {
        $res = sql_query("SELECT lemma_id, lemma_text, grammems FROM form2lemma WHERE form_text='$token'");
        if (sql_num_rows($res) > 0) {
            while($r = sql_fetch_array($res)) {
                $out .= '<v><l id="'.$r['lemma_id'].'" t="'.$r['lemma_text'].'">'.$r['grammems'].'</l></v>';
            }
        } else {
            $out .= '<v><l id="0" t="'.htmlspecialchars(mb_strtolower($token, 'UTF-8')).'"><g v="UNKN"/></l></v>';
        }
    } elseif (preg_match('/^[\,\.\:\;\-\(\)\'\"\[\]\?\!\/]+$/', $token)) {
        $out .= '<v><l id="0" t="'.htmlspecialchars($token).'"><g v="PNCT"/></l></v>';
    } else {
        $out .= '<v><l id="0" t="'.htmlspecialchars($token).'"><g v="UNKN"/></l></v>';
    }
    $out .= '</tfr>';
    return $out;
}
function dict_get_select_gram() {
    $res = sql_query("SELECT `gram_id`, `inner_id` FROM `gram` ORDER by `inner_id`");
    $out = array();
    while($r = sql_fetch_array($res)) {
        $out[$r['gram_id']] = $r['inner_id'];
    }
    return $out;
}
function get_link_types() {
    $res = sql_query("SELECT * FROM dict_links_types ORDER BY link_name");
    $out = array();
    while ($r = sql_fetch_array($res)) {
        $out[$r['link_id']] = $r['link_name'];
    }
    return $out;
}
function parse_dict_rev($text) {
    // output has the following structure:
    // lemma => array (text => lemma_text, grm => array (grm1, grm2, ...)),
    // forms => array (
    //     [0] => array (text => form_text, grm => array (grm1, grm2, ...)),
    //     [1] => ...
    // )
    $arr = xml2ary($text);
    $arr = $arr['dr']['_c'];
    $parsed = array();
    $parsed['lemma']['text'] = $arr['l']['_a']['t'];
    //the rest of the function should be refactored
    $t = array();
    foreach ($arr['l']['_c']['g'] as $garr) {
        if (isset($garr['v'])) {
            //if there is only one grammem
            $t[] = $garr['v'];
            break;
        }
        $t[] = $garr['_a']['v'];
    }
    $parsed['lemma']['grm'] = $t;
    if (isset($arr['f']['_a'])) {
        //if there is only one form
        $parsed['forms'][0]['text'] = $arr['f']['_a']['t'];
        $t = array();
        if (isset($arr['f']['_c'])) {
            //if there are grammems at all
            foreach ($arr['f']['_c']['g'] as $garr) {
                if (isset($garr['v'])) {
                    //if there is only one grammem
                    $t[] = $garr['v'];
                    break;
                }
                $t[] = $garr['_a']['v'];
            }
        }
        $parsed['forms'][0]['grm'] = $t;
    } else {
        foreach($arr['f'] as $k=>$farr) {
            $parsed['forms'][$k]['text'] = $farr['_a']['t'];
            $t = array();
            foreach ($farr['_c']['g'] as $garr) {
                if (isset($garr['v'])) {
                    //if there is only one grammem
                    $t[] = $garr['v'];
                    break;
                }
                $t[] = $garr['_a']['v'];
            }
            $parsed['forms'][$k]['grm'] = $t;
        }
    }
    return $parsed;
}
function form_exists($f) {
    $f = mb_strtolower($f, 'UTF-8');
    if (!preg_match('/^[А-Яа-яЁё]/u', $f)) {
        return -1;
    }
    return sql_num_rows(sql_query("SELECT lemma_id FROM form2lemma WHERE form_text='".mysql_real_escape_string($f)."' LIMIT 1"));
}

// DICTIONARY EDITOR
function get_lemma_editor($id) {
    $out = array('lemma' => array('id' => $id));
    if ($id == -1) return $out;
    $r = sql_fetch_array(sql_query("SELECT l.`lemma_text`, d.`rev_id`, d.`rev_text` FROM `dict_lemmata` l LEFT JOIN `dict_revisions` d ON (l.lemma_id = d.lemma_id) WHERE l.`lemma_id`=$id ORDER BY d.rev_id DESC LIMIT 1"));
    $arr = parse_dict_rev($r['rev_text']);
    $out['lemma']['text'] = $arr['lemma']['text'];
    $out['lemma']['grms'] = implode(', ', $arr['lemma']['grm']);
    foreach($arr['forms'] as $farr) {
        $out['forms'][] = array('text' => $farr['text'], 'grms' => implode(', ', $farr['grm']));
    }
    //links
    $res = sql_query("
    (SELECT lemma1_id lemma_id, lemma_text, link_name, l.link_id
        FROM dict_links l
        LEFT JOIN dict_links_types t ON (l.link_type=t.link_id)
        LEFT JOIN dict_lemmata lm ON (l.lemma1_id=lm.lemma_id)
        WHERE lemma2_id=$id)
    UNION
    (SELECT lemma2_id lemma_id, lemma_text, link_name, l.link_id
        FROM dict_links l
        LEFT JOIN dict_links_types t ON (l.link_type=t.link_id)
        LEFT JOIN dict_lemmata lm ON (l.lemma2_id=lm.lemma_id)
        WHERE lemma1_id=$id)
    ", 1, 1);
    while($r = sql_fetch_array($res)) {
        $out['links'][] = array('lemma_id' => $r['lemma_id'], 'lemma_text' => $r['lemma_text'], 'name' => $r['link_name'], 'id' => $r['link_id']);
    }
    //errata
    $res = sql_query("SELECT e.*, x.item_id, x.timestamp exc_time, x.comment exc_comment, u.user_name
        FROM dict_errata e
        LEFT JOIN dict_errata_exceptions x ON (e.error_type=x.error_type AND e.error_descr=x.error_descr)
        LEFT JOIN users u ON (x.author_id = u.user_id)
        WHERE e.rev_id = 
        (SELECT rev_id FROM dict_revisions WHERE lemma_id=$id ORDER BY rev_id DESC LIMIT 1)
    ");
    while($r = sql_fetch_array($res)) {
        $out['errata'][] = array(
            'id' => $r['error_id'],
            'type' => $r['error_type'],
            'descr' => $r['error_descr'],
            'is_ok' => ($r['item_id'] > 0 ? 1 : 0),
            'author_name' => $r['user_name'],
            'exc_time' => $r['exc_time'],
            'comment' => $r['exc_comment']
        );
    }
    return $out;
}
function dict_add_lemma($array) {
    $ltext = $array['form_text'];
    $lgram = $array['form_gram'];
    $lemma_gram_new = $array['lemma_gram'];
    $lemma_text = $array['lemma_text'];
    $new_paradigm = array();
    foreach($ltext as $i=>$text) {
        $text = trim($text);
        if ($text == '') {
            //the form is to be deleted, so we do nothing
        } elseif (strpos($text, ' ') !== false) {
            show_error("Error: a form cannot contain whitespace ($text)");
            return;
        } else {
            //TODO: perhaps some data validity check?
            array_push($new_paradigm, array($text, $lgram[$i]));
        }
    }
    $upd_forms = array();
    foreach($new_paradigm as $form) {
        $upd_forms[] = $form[0];
    }
    $upd_forms = array_unique($upd_forms);
    foreach($upd_forms as $upd_form) {
        if (!sql_query("INSERT INTO `updated_forms` VALUES('".mysql_real_escape_string($upd_form)."')")) {
            show_error("Error at updated_forms :(");
            exit;
        }
    }
    //new lemma in dict_lemmata
    if (!sql_query("INSERT INTO dict_lemmata VALUES(NULL, '".mysql_real_escape_string($lemma_text)."')")) {
        show_error();
        exit;
    }
    $lemma_id = sql_insert_id();
    //array -> xml
    $new_xml = make_dict_xml($lemma_text, $lemma_gram_new, $new_paradigm);
    $res = new_dict_rev($lemma_id, $new_xml, $array['comment']);
    if ($res) {
        header("Location:dict.php?act=edit&saved&id=$lemma_id");
        return;
    } else show_error("Error on saving");
}
function dict_save($array) {
    //it may be a totally new lemma
    if ($array['lemma_id'] == -1) {
        dict_add_lemma($array);
        return;
    }
    $ltext = $array['form_text'];
    $lgram = $array['form_gram'];
    $lemma_gram_new = $array['lemma_gram'];
    //let's construct the old paradigm
    $r = sql_fetch_array(sql_query("SELECT rev_text FROM dict_revisions WHERE lemma_id=".$array['lemma_id']." ORDER BY `rev_id` DESC LIMIT 1"));
    $pdr = parse_dict_rev($old_xml = $r['rev_text']);
    $lemma_text = $pdr['lemma']['text'];
    $lemma_gram_old = implode(', ', $pdr['lemma']['grm']);
    $old_paradigm = array();
    foreach($pdr['forms'] as $form_arr) {
        array_push($old_paradigm, array($form_arr['text'], implode(', ', $form_arr['grm'])));
    }
    $new_paradigm = array();
    foreach($ltext as $i=>$text) {
        $text = trim($text);
        if ($text == '') {
            //the form is to be deleted, so we do nothing
        } elseif (strpos($text, ' ') !== false) {
            die ("Error: a form cannot contain whitespace ($text)");
        } else {
            //TODO: perhaps some data validity check?
            array_push($new_paradigm, array($text, $lgram[$i]));
        }
    }
    //calculate which forms are actually updated
    $upd_forms = array();
    //if lemma's grammems have changed then all forms have changed
    if ($lemma_gram_new != $lemma_gram_old) {
        foreach($old_paradigm as $farr) {
            array_push($upd_forms, $farr[0]);
        }
        foreach($new_paradigm as $farr) {
            array_push($upd_forms, $farr[0]);
        }
    } else {
        $int = paradigm_diff($old_paradigm, $new_paradigm);
        //..and insert them into `updated_forms`
        foreach($int as $int_form) {
            array_push($upd_forms, $int_form[0]);
        }
    }
    $upd_forms = array_unique($upd_forms);
    foreach($upd_forms as $upd_form) {
        if (!sql_query("INSERT INTO `updated_forms` VALUES('".mysql_real_escape_string($upd_form)."')")) {
            show_error("Error at updated_forms :(");
            exit;
        }
    }
    //array -> xml
    $new_xml = make_dict_xml($lemma_text, $lemma_gram_new, $new_paradigm);
    if ($new_xml != $old_xml) {
        //something's really changed
        $res = new_dict_rev($array['lemma_id'], $new_xml, $array['comment']);
        if ($res) {
            header("Location:dict.php?act=edit&saved&id=".$array['lemma_id']);
            return;
        } else show_error("Error on saving");
    } else {
        header("Location:dict.php?act=edit&id=".$array['lemma_id']);
        return;
    }
}
function make_dict_xml($lemma_text, $lemma_gram, $paradigm) {
    $new_xml = '<dr><l t="'.htmlspecialchars($lemma_text).'">';
    //lemma's grammems
    $lg = explode(',', $lemma_gram);
    foreach($lg as $gr) {
        $new_xml .= '<g v="'.htmlspecialchars(trim($gr)).'"/>';
    }
    $new_xml .= '</l>';
    //paradigm
    foreach($paradigm as $new_form) {
        list($txt, $gram) = $new_form;
        $new_xml .= '<f t="'.htmlspecialchars($txt).'">';
        $gram = explode(',', $gram);
        foreach($gram as $gr) {
            $new_xml .= '<g v="'.htmlspecialchars(trim($gr)).'"/>';
        }
        $new_xml .= '</f>';
    }
    $new_xml .= '</dr>';
    return $new_xml;
}
function new_dict_rev($lemma_id, $new_xml, $comment = '') {
    if (!$lemma_id || !$new_xml) return 0;
    $revset_id = create_revset($comment);
    if (!$revset_id) return 0;
    if (sql_query("INSERT INTO `dict_revisions` VALUES(NULL, '$revset_id', '$lemma_id', '".mysql_real_escape_string($new_xml)."', '0', '0')")) {
        return 1;
    }
    return 0;
}
function paradigm_diff($array1, $array2) {
    $diff = array();
    foreach($array1 as $form_array) {
        if(!in_array($form_array, $array2))
            array_push($diff, $form_array);
    }
    foreach($array2 as $form_array) {
        if(!in_array($form_array, $array1))
            array_push($diff, $form_array);
    }
    return $diff;
}
function del_lemma($id) {
    error_reporting(0);
    //delete links (but preserve history)
    $res = sql_query("SELECT link_id FROM dict_links WHERE lemma1_id=$id OR lemma2_id=$id");
    $revset_id = create_revset();
    while($r = sql_fetch_array($res)) {
        if (!del_link($r['link_id'], $revset_id)) {
            show_error();
            return;
        }
    }
    //update `updated_forms`
    $r = sql_fetch_array(sql_query("SELECT rev_text FROM dict_revisions WHERE lemma_id=$id ORDER BY `rev_id` DESC LIMIT 1"));
    $pdr = parse_dict_rev($r['rev_text']);
    foreach($pdr['forms'] as $form) {
        if (!sql_query("INSERT INTO `updated_forms` VALUES('".$form['text']."')")) {
            show_error();
            return;
        }
    }
    //delete forms from form2lemma
    sql_query("DELETE FROM `form2lemma` WHERE lemma_id=$id");
    //delete lemma
    if (sql_query("INSERT INTO dict_lemmata_deleted (SELECT * FROM dict_lemmata WHERE lemma_id=$id LIMIT 1)") && sql_query("DELETE FROM dict_lemmata WHERE lemma_id=$id LIMIT 1")) {
        header("Location:dict.php");
        return;
    } else
        show_error();
}
function del_link($link_id, $revset_id=0) {
    $r = sql_fetch_array(sql_query("SELECT * FROM dict_links WHERE link_id=$link_id LIMIT 1"));
    if (!$revset_id) $revset_id = create_revset();
    if (sql_query("INSERT INTO dict_links_revisions VALUES(NULL, '$revset_id', '".$r['lemma1_id']."', '".$r['lemma2_id']."', '".$r['link_type']."', '0')") &&
        sql_query("DELETE FROM dict_links WHERE link_id=$link_id LIMIT 1")) {
        return 1;
    }
    return 0;
}
function add_link($from_id, $to_id, $link_type, $revset_id=0) {
    if (!$revset_id) $revset_id = create_revset();
    if (!$from_id || !$to_id || !$link_type) return 0;
    if (sql_query("INSERT INTO dict_links VALUES(NULL, '$from_id', '$to_id', '$link_type')") &&
        sql_query("INSERT INTO dict_links_revisions VALUES(NULL, '$revset_id', '$from_id', '$to_id', '$link_type', '1')")) {
        return 1;
    }
    return 0;
}

// GRAMMEM EDITOR
function get_grammem_editor($order) {
    $out = array();
    $orderby = $order == 'id' ? 'inner_id' :
        ($order == 'outer' ? 'outer_id' : 'orderby');
    $res = sql_query("SELECT g1.`gram_id`, g1.`parent_id`, g1.`inner_id`, g1.`outer_id`, g1.`gram_descr`, g1.`orderby`, g2.`inner_id` AS `parent_name` FROM `gram` g1 LEFT JOIN `gram` g2 ON (g1.parent_id=g2.gram_id) ORDER BY g1.`$orderby`");
    while($r = sql_fetch_array($res)) {
        $class = strlen($r['inner_id']) != 4 ? 'gramed_bad' :
            (preg_match('/^[A-Z0-9-]+$/', $r['inner_id']) ? 'gramed_pos' :
            (preg_match('/[A-Z0-9][A-Z0-9][a-z0-9-][a-z0-9-]/', $r['inner_id']) ? 'gramed_group' :
            (preg_match('/[A-Z][a-z0-9-][a-z0-9-][a-z0-9-]/', $r['inner_id']) ? 'gramed_label' : '')));
        $out[] = array(
            'order' => $r['orderby'],
            'id' => $r['gram_id'],
            'name' => $r['inner_id'],
            'outer_id' => $r['outer_id'],
            'description' => htmlspecialchars($r['gram_descr']),
            'parent_name' => $r['parent_name'],
            'css_class' => $class
        );
    }
    return $out;
}
function add_grammem($inner_id, $group, $outer_id, $descr) {
    $r = sql_fetch_array(sql_query("SELECT MAX(`orderby`) AS `m` FROM `gram`"));
    if (sql_query("INSERT INTO `gram` VALUES(NULL, '$group', '$inner_id', '$outer_id', '$descr', '".($r['m']+1)."')")) {
        header("Location:dict.php?act=gram");
        return;
    } else {
        show_error();
    }
}
function del_grammem($grm_id) {
    if (sql_query("DELETE FROM `gram` WHERE `gram_id`=$grm_id LIMIT 1")) {
        header("Location:dict.php?act=gram");
        return;
    } else
        show_error();
}
function move_grammem($grm_id, $dir) {
    $r = sql_fetch_array(sql_query("SELECT `orderby` as `ord` FROM `gram` WHERE gram_id=$grm_id"));
    $ord = $r['ord'];
    if ($dir == 'up') {
        $q = sql_query("SELECT MAX(`orderby`) as `ord` FROM `gram` WHERE `orderby`<$ord");
        if ($q) {
            $r = sql_fetch_array($q);
            $ord2 = $r['ord'];
        }
    } else {
        $q = sql_query("SELECT MIN(`orderby`) as `ord` FROM `gram` WHERE `orderby`>$ord");
        if ($q) {
            $r = sql_fetch_array($q);
            $ord2 = $r['ord'];
        }
    }
    if (!isset($ord2)) {
        header('Location:dict.php?act=gram');
        return;
    }
    if (sql_query("UPDATE `gram` SET `orderby`='$ord' WHERE `orderby`=$ord2 LIMIT 1") &&
        sql_query("UPDATE `gram` SET `orderby`='$ord2' WHERE `gram_id`=$grm_id LIMIT 1")) {
        header('Location:dict.php?act=gram#g'.$grm_id);
        return;
    } else {
        show_error();
    }
}
function edit_grammem($id, $inner_id, $outer_id, $descr) {
    if (sql_query("UPDATE `gram` SET `inner_id`='$inner_id', `outer_id`='$outer_id', `gram_descr`='$descr' WHERE `gram_id`=$id LIMIT 1")) {
        header('Location:dict.php?act=gram');
        return;
    } else {
        show_error();
    }
}

//ERRATA
function get_dict_errata($all, $rand) {
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_v FROM `dict_revisions` WHERE dict_check=0"));
    $out = array('lag' => $r['cnt_v']);
    $r = sql_fetch_array(sql_query("SELECT COUNT(*) AS cnt_t FROM `dict_errata`"));
    $out['total'] = $r['cnt_t'];
    $res = sql_query("SELECT e.*, r.lemma_id, r.set_id, x.item_id, x.timestamp exc_time, x.comment exc_comment, u.user_name
        FROM dict_errata e
        LEFT JOIN dict_errata_exceptions x ON (e.error_type=x.error_type AND e.error_descr=x.error_descr)
        LEFT JOIN users u ON (x.author_id = u.user_id)
        LEFT JOIN dict_revisions r ON (e.rev_id=r.rev_id)
        ORDER BY ".($rand?'RAND()':'error_id').($all?'':' LIMIT 200'));
    while($r = sql_fetch_array($res)) {
        $out['errors'][] = array(
            'id' => $r['error_id'],
            'timestamp' => $r['timestamp'],
            'revision' => $r['rev_id'],
            'type' => $r['error_type'],
            'description' => preg_replace('/<([^>]+)>/', '<a href="?act=edit&amp;id='.$r['lemma_id'].'">$1</a>', $r['error_descr']),
            'lemma_id' => $r['lemma_id'],
            'set_id' => $r['set_id'],
            'is_ok' => ($r['item_id'] > 0 ? 1 : 0),
            'author_name' => $r['user_name'],
            'exc_time' => $r['exc_time'],
            'comment' => $r['exc_comment']
        );
    }
    return $out;
}
function clear_dict_errata($old) {
    if ($old) {
        if (sql_query("UPDATE dict_revisions SET dict_check='0'")) {
            header("Location:dict.php?act=errata");
            return;
        } else {
            show_error();
        }
    }
    else {
        $res = sql_query("SELECT MAX(rev_id) AS m FROM dict_revisions GROUP BY lemma_id");
        while($r = sql_fetch_array($res)) {
            if (!sql_query("UPDATE dict_revisions SET dict_check='0' WHERE rev_id=".$r['m']." LIMIT 1")) {
                show_error();
                return;
            }
        }
        header("Location:dict.php?act=errata");
        return;
    }
}
function mark_dict_error_ok($id, $comment) {
    if (!$id) {
        header("Location:dict.php?act=errata");
        return;
    }
    if (sql_query("INSERT INTO dict_errata_exceptions VALUES(
            NULL,
            (SELECT error_type FROM dict_errata WHERE error_id=$id LIMIT 1),
            (SELECT error_descr FROM dict_errata WHERE error_id=$id LIMIT 1),
            '".$_SESSION['user_id']."',
            '".time()."',
            '".mysql_real_escape_string($comment)."'
        )")) {
        header("Location:dict.php?act=errata");
        return;
    } else
        show_error();
}
function get_gram_restrictions($hide_auto) {
    $res = sql_query("SELECT r.restr_id, r.obj_type, r.restr_type, r.auto, g1.inner_id `if`, g2.inner_id `then`
        FROM gram_restrictions r
            LEFT JOIN gram g1 ON (r.if_id=g1.gram_id)
            LEFT JOIN gram g2 ON (r.then_id=g2.gram_id)".
            ($hide_auto ? " WHERE `auto`=0" : "")
        ." ORDER BY r.restr_id");
    $out = array('gram_options' => '');
    while ($r = sql_fetch_array($res)) {
        $out['list'][] = array(
            'id' => $r['restr_id'],
            'if_id' => $r['if'],
            'then_id' => $r['then'],
            'type' => $r['restr_type'],
            'obj_type' => $r['obj_type'],
            'auto' => $r['auto']
        );
    }
    $res = sql_query("SELECT gram_id, inner_id FROM gram order by inner_id");
    while ($r = sql_fetch_array($res)) {
        $out['gram_options'][$r['gram_id']] = $r['inner_id'];
    }
    return $out;
}
function add_dict_restriction($post) {
    if (sql_query("INSERT INTO gram_restrictions VALUES(NULL, '".(int)$post['if']."', '".(int)$post['then']."', '".(int)$post['rtype']."', '".((int)$post['if_type'] + (int)$post['then_type'])."', '0')")) {
        calculate_gram_restrictions();
        return;
    } else
        show_error();
}
function del_dict_restriction($id) {
    if (sql_query("DELETE FROM gram_restrictions WHERE restr_id=$id LIMIT 1")) {
        calculate_gram_restrictions();
        return;
    } else
        show_error();
}
function calculate_gram_restrictions() {
    sql_query("DELETE FROM gram_restrictions WHERE `auto`=1");
    $restr = array();
    $res = sql_query("SELECT r.if_id, r.then_id, r.obj_type, r.restr_type, g1.gram_id gram1, g2.gram_id gram2
        FROM gram_restrictions r
        LEFT JOIN gram g1 ON (r.then_id = g1.parent_id)
        LEFT JOIN gram g2 ON (g1.gram_id = g2.parent_id)
        WHERE r.restr_type>0");
    while ($r = sql_fetch_array($res)) {
        $restr[] = $r['if_id'].'#'.$r['then_id'].'#'.$r['obj_type'].'#'.$r['restr_type'];
        if ($r['gram1'])
            $restr[] = $r['if_id'].'#'.$r['gram1'].'#'.$r['obj_type'].'#'.$r['restr_type'];
        if ($r['gram2'])
            $restr[] = $r['if_id'].'#'.$r['gram2'].'#'.$r['obj_type'].'#'.$r['restr_type'];
    }
    $restr = array_unique($restr);
    foreach ($restr as $quad) {
        list($if, $then, $type, $w0) = explode('#', $quad);
        $w = ($w0 == 1 ? 0 : 2);
        if (sql_num_rows(sql_query("SELECT restr_id FROM gram_restrictions WHERE if_id=$if AND then_id=$then AND obj_type=$type AND restr_type=$w")) == 0) {
            if (!sql_query("INSERT INTO gram_restrictions VALUES(NULL, '$if', '$then', '$w', '$type', '1')")) {
                show_error();
            }
        }
    }
    header("Location:dict.php?act=gram_restr");
}

// ADDING TEXTS
function split2paragraphs($txt) {
    return preg_split('/\r?\n\r?\n\r?/', $txt);
}
function split2sentences($txt) {
    return preg_split('/[\r\n]+/', $txt);
}
function tokenize_ml($txt) {
    $coeff = array();
    $out = array();
    $token = '';

    $res = sql_query("SELECT * FROM tokenizer_coeff");
    while($r = sql_fetch_array($res)) {
        $coeff[$r[0]] = $r[1];
    }

    //let's first remove diacritics
    $clear_txt = '';
    for ($i = 0; $i < mb_strlen($txt, 'UTF-8'); ++$i) {
        $char = mb_substr($txt, $i, 1, 'UTF-8');
        if (uniord($char) == 769) continue;
        $clear_txt .= $char;
    }
    $txt = $clear_txt.'  ';

    for($i = 0; $i < mb_strlen($txt, 'UTF-8'); ++$i) {
        $prevchar  = ($i > 0 ? mb_substr($txt, $i-1, 1, 'UTF-8') : '');
        $char      =           mb_substr($txt, $i+0, 1, 'UTF-8');
        $nextchar  =           mb_substr($txt, $i+1, 1, 'UTF-8');
        $nnextchar =           mb_substr($txt, $i+2, 1, 'UTF-8');

        //$chain is the current word which we will perhaps need to check in the dictionary

        $chain = '';
        if (is_hyphen($nextchar) || is_hyphen($char)) {
            for ($j = $i; $j > 0; --$j) {
                $t = mb_substr($txt, $j, 1, 'UTF-8');
                if (is_cyr($t) || is_hyphen($t) || $t === "'") {
                    $chain = $t.$chain;
                } else {
                    break;
                }
            }
            for ($j = $i+1; $j < strlen($txt, 'UTF-8'); ++$j) {
                $t = mb_substr($txt, $j, 1, 'UTF-8');
                if (is_cyr($t) || is_hyphen($t) || $t === "'") {
                    $chain .= $t;
                } else {
                    break;
                }
            }
        }

        $vector = array(
            is_space($char),
            is_space($nextchar),
            is_pmark($char),
            is_pmark($nextchar),
            is_latin($char),
            is_latin($nextchar),
            is_cyr($char),
            is_cyr($nextchar),
            is_hyphen($char),
            is_hyphen($nextchar),
            is_number($prevchar),
            is_number($char),
            is_number($nextchar),
            is_number($nnextchar),
            is_dict_chain($chain),
            is_dot($char),
            is_dot($nextchar)
        );
        $vector = implode('', $vector);

        if (isset($coeff[bindec($vector)])) {
            $sum = $coeff[bindec($vector)];
        } else {
            $sum = 0.5;
        }

        $token .= $char;

        if ($sum > 0) {
            $token = trim($token);
            if ($token !== '') $out[] = array($token, $sum, bindec($vector).'='.$vector);
            $token = '';
        }
    }
    return $out;

}
function uniord($u) {
    $c = unpack("N", mb_convert_encoding($u, 'UCS-4BE', 'UTF-8'));
    return $c[1];
}
function is_space($char) {
    return preg_match('/^\s$/u', $char);
}
function is_hyphen($char) {
    return (int)($char == '-');
}
function is_dot($char) {
    return (int)($char == '.');
}
function is_cyr($char) {
    $re_cyr = '/[А-Яа-яЁё]/u';
    return preg_match($re_cyr, $char);
}
function is_latin($char) {
    $re_lat = '/[A-Za-z]/u';
    return preg_match($re_lat, $char);
}
function is_number($char) {
    return (int)is_numeric($char);
}
function is_pmark($char) {
    $re_punctuation = '/[,!\?;:\(\)\[\]\/"\xAB\xBB]/u';
    return preg_match($re_punctuation, $char);
}
function is_dict_chain($chain) {
    if (!$chain) return 0;
    return (form_exists(mb_strtolower($chain, 'UTF-8')) > 0);
}
function addtext_check($array) {
    $out = array('full' => $array['txt'], 'select0' => get_books_for_select(0));
    $pars = split2paragraphs($array['txt']);
    foreach ($pars as $par) {
        if (!preg_match('/\S/', $par)) continue;
        $par_array = array();
        $sents = split2sentences($par);
        foreach ($sents as $sent) {
            if (!preg_match('/\S/', $sent)) continue;
            $sent_array = array('src' => $sent);
            $tokens = tokenize_ml($sent);
            foreach ($tokens as $token) {
                $sent_array['tokens'][] = array('text' => $token[0], 'class' => form_exists($token[0]), 'border' => $token[1], 'vector' => $token[2]);
            }
            $par_array['sentences'][] = $sent_array;
        }
        $out['paragraphs'][] = $par_array;
    }
    //book
    if (isset($array['book_id'])) {
        $book_id = (int)$array['book_id'];
        $r = sql_fetch_array(sql_query("SELECT parent_id FROM books WHERE book_id=$book_id LIMIT 1"));
        if ($r['parent_id'] > 0) {
            $out['selected0'] = $r['parent_id'];
            $out['select1'] = get_books_for_select($r['parent_id']);
            $out['selected1'] = $book_id;
        } else {
            $out['selected0'] = $book_id;
        }
    }
    return $out;
}
function addtext_add($text, $sentences, $book_id, $par_num) {
    if (!$text || !$book_id || !$par_num) return 0;
    //removing unicode diacritics
    $clear_text = '';
    for($i = 0; $i < mb_strlen($text, 'UTF-8'); ++$i) {
        $char = mb_substr($text, $i, 1, 'UTF-8');
        if (uniord($char) != 769) $clear_text .= $char;
    }
    $revset_id = create_revset();
    if (!$revset_id) return 0;
    $sent_count = 0;
    $pars = split2paragraphs($clear_text);
    foreach($pars as $par) {
        if (!preg_match('/\S/', $par)) continue;
        //adding a paragraph
        if (!sql_query("INSERT INTO `paragraphs` VALUES(NULL, '$book_id', '".($par_num++)."')")) return 0;
        $par_id = sql_insert_id();
        $sent_num = 1;
        $sents = split2sentences($par);
        foreach($sents as $sent) {
            //adding a sentence
            if (!sql_query("INSERT INTO `sentences` VALUES(NULL, '$par_id', '".($sent_num++)."', '".mysql_real_escape_string(trim($sent))."', '0')")) return 0;
            $sent_id = sql_insert_id();
            $token_num = 1;
            //strip excess whitespace
            $tokens = explode('^^', $sentences[$sent_count++]);
            foreach ($tokens as $token) {
                if ($token == '' || $token == ' ') continue;
                //adding a textform
                if (!sql_query("INSERT INTO `text_forms` VALUES(NULL, '$sent_id', '".($token_num++)."', '".mysql_real_escape_string($token)."', '0')")) return 0;
                $tf_id = sql_insert_id();
                //adding a revision
                if (!sql_query("INSERT INTO `tf_revisions` VALUES(NULL, '$revset_id', '$tf_id', '".mysql_real_escape_string(generate_tf_rev($token))."')")) return 0;
            }
        }
    }
    return 1;
}
?>
