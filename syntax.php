<?php
require_once('lib/header.php');
require_once('lib/lib_anaphora_syntax.php');


$action = GET('act', '');

switch ($action) {
    case 'finish_moder':
        finish_syntax_moderation($_GET['book_id']);
        header("Location:syntax.php");
        break;
    case 'set_status':
        set_syntax_annot_status($_GET['book_id'], $_GET['status']);
        header("Location:syntax.php");
        break;
    case 'set_moderated':
        become_syntax_moderator($_GET['book_id']);
        header("Location:syntax.php");
        break;

    default:
        check_permission(PERM_SYNTAX);
        $smarty->assign('page', get_books_with_syntax());
        $smarty->display('syntax/main.tpl');
}
log_timing();
?>
