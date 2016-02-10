<?php
require('lib/header.php');
require('lib/lib_annot.php');

$search = trim(mb_strtolower(GET('q')));
$smarty->assign('search', get_search_results($search, isset($_GET['exact_form'])));
$smarty->display('search.tpl');
log_timing();
