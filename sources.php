<?php
require('lib/header.php');
require_once('lib/lib_books.php');

$action = GET('act', '');

switch ($action) {
    case 'add':
        source_add($_POST['url'], $_POST['title'], $_POST['parent']);
        header("Location:sources.php");
        break;
    default:
        $what = GET('what', '');
        $skip = GET('skip', 0);
        $src  = GET('src', 0);
        $smarty->assign('sources', get_sources_page($skip, $what, $src));
        $smarty->assign('what', $what);
        $smarty->assign('skip', $skip);
        $smarty->display('templates/sources.tpl');
}
log_timing();
?>
