<?php
require('lib/header.php');
require('lib/lib_dict.php');

$action = GET('act', '');

$smarty->assign('active_page', 'dict');

switch ($action) {
    case 'add_gram':
        add_grammem(POST('g_name'), POST('parent_gram'), POST('outer_id'), POST('descr'));
        header("Location:dict.php?act=gram");
        break;
    case 'del_gram':
        del_grammem(GET('id'));
        header("Location:dict.php?act=gram");
        break;
    case 'edit_gram':
        $id = POST('id');
        $inner_id = POST('inner_id');
        $outer_id = POST('outer_id');
        $descr = POST('descr');
        edit_grammem($id, $inner_id, $outer_id, $descr);
        header('Location:dict.php?act=gram');
        break;
    case 'clear_errata':
        clear_dict_errata(isset($_GET['old']));
        header("Location:dict.php?act=errata");
        break;
    case 'not_error':
        mark_dict_error_ok(GET('error_id'), POST('comm'));
        header("Location:dict.php?act=errata");
        break;
    case 'add_restr':
        add_dict_restriction($_POST);
        header("Location:dict.php?act=gram_restr");
        break;
    case 'del_restr':
        del_dict_restriction(GET('id'));
        header("Location:dict.php?act=gram_restr");
        break;
    case 'update_restr':
        calculate_gram_restrictions();
        header("Location:dict.php?act=gram_restr");
        break;
    case 'save':
        // update after selectpicker (lemma_edit.tpl)
        // now we have to implode the arrays
        if (!empty($_POST['form_gram']))
            foreach ($_POST['form_gram'] as &$grams) {
                $grams = implode(', ', $grams);
            }
        if (!empty($_POST['lemma_gram']))
            $_POST['lemma_gram'] = implode(', ', $_POST['lemma_gram']);

        $lemma_id = dict_save($_POST);
        header("Location:dict.php?act=edit&saved&id=$lemma_id");
        break;
    case 'add_link':
        add_link(POST('from_id'), POST('lemma_id'), POST('link_type'));
        header("Location:dict.php?act=edit&id=".POST('from_id'));
        break;
    case 'del_link':
        del_link(GET('id'));
        header("Location:dict.php?act=edit&id=".GET('lemma_id'));
        break;
    case 'change_link_dir':
        change_link_direction(GET('id'));
        header("Location:dict.php?act=edit&id=".GET('lemma_id'));
        break;
    case 'del_lemma':
        del_lemma(GET('lemma_id'));
        header("Location:dict.php");
        break;
    case 'lemmata':
        $smarty->assign('search', get_dict_search_results($_GET));
        $smarty->display('dict/lemmata.tpl');
        break;
    case 'gram':
        $order = GET('order', '');
        $smarty->assign('grammems', get_grammem_editor($order));
        $smarty->assign('order', $order);
        $smarty->assign('select', dict_get_select_gram());
        $smarty->display('dict/gram.tpl');
        break;
    case 'gram_restr':
        $smarty->assign('restrictions', get_gram_restrictions(isset($_GET['hide_auto'])));
        $smarty->display('dict/restrictions.tpl');
        break;
    case 'edit':
        $smarty->assign('editor', get_lemma_editor(GET('id')));
        $smarty->assign('link_types', get_link_types());
        $smarty->assign('possible_grammems', dict_get_select_gram());
        $smarty->display('dict/lemma_edit.tpl');
        break;
    case 'errata':
        $smarty->assign('errata', get_dict_errata(isset($_GET['all']), isset($_GET['rand'])));
        $smarty->display('dict/errata.tpl');
        break;
    case 'pending':
        $skip = GET('skip', 0);
        $smarty->assign('data', get_pending_updates($skip));
        $smarty->display('dict/pending.tpl');
        break;
    case 'reannot':
        update_pending_tokens(POST('rev_id'), POST('smart_mode', 0) == 'on');
        header("Location:dict.php?act=pending");
        break;
    case 'absent':
        $smarty->setCaching(Smarty::CACHING_LIFETIME_SAVED);
        $smarty->setCacheLifetime(3600);
        if (!is_cached('dict/absent.tpl'))
            $smarty->assign('words', get_top_absent_words());
        $smarty->display('dict/absent.tpl');
        break;
    default:

        $smarty->setCaching(Smarty::CACHING_LIFETIME_SAVED);
        $smarty->setCacheLifetime(600);
        if (!is_cached('dict/main.tpl', (int)user_has_permission(PERM_DICT))) {
            $smarty->assign('stats', get_dict_stats());
            $smarty->assign('dl', get_downloads_info());
        }
        $smarty->display('dict/main.tpl', (int)user_has_permission(PERM_DICT));
}
log_timing();
?>
