<?php
require('lib/header.php');
require_once('lib/lib_books.php');
require_once('lib/lib_anaphora_syntax.php');
require_once('lib/lib_ne.php');
require_once('lib/lib_users.php');

$action = GET('act', '');
if (!$action) {
    $book_id = GET('book_id', 0);
    if ($book_id) {
        $smarty->assign('book', get_book_page($book_id, isset($_GET['full'])));
        $smarty->display('book.tpl');
    } else {
        $smarty->assign('books', get_books_list());
        $smarty->display('books.tpl');
    }
}
elseif ($action == 'anaphora') {
    check_permission(PERM_SYNTAX);
    $book_id = GET('book_id');
    $book = get_book_page($book_id, TRUE);
    $groups = array();
    $smarty->assign('group_types', get_syntax_group_types());

    foreach ($book['paragraphs'] as &$paragraph) {

        foreach ($paragraph['sentences'] as &$sentence) {
            $sentence['props'] = get_pronouns_by_sentence($sentence['id']);
            foreach ($sentence['tokens'] as &$token) {
                $groups[$token['id']] = $token['groups']
                    = get_moderated_groups_by_token($token['id']);
            }
        }
    }

    $smarty->assign('anaphora', get_anaphora_by_book($book_id));
    $smarty->assign('book', $book);
    $smarty->assign('token_groups', $groups);
    $smarty->display('syntax/book.tpl');
}

elseif ($action == 'ner') {
    //check_permission(PERM_SYNTAX);
    check_logged();
    $book_id = GET('book_id');

    $tagset_id = get_current_tagset();
    $is_book_moderator = is_user_book_moderator($book_id, $tagset_id);

    $book = get_book_page($book_id, TRUE, TRUE);
    // список из статусов => список id параграфов
    $paragraphs_status = get_ne_paragraph_status($book_id, $_SESSION['user_id'], $tagset_id);

    foreach ($book['paragraphs'] as &$paragraph) {
        // и для модератора, и для простого юзера забираем "свою" разметку
        $ne = get_ne_by_paragraph($paragraph['id'], $_SESSION['user_id'], $tagset_id);
        $mentions = get_ne_by_paragraph($paragraph['id'], $_SESSION['user_id'], $tagset_id, TRUE);

        $paragraph['named_entities'] = isset($ne['entities']) ? $ne['entities'] : array();
        $paragraph['mentions'] = isset($mentions['entities']) ? $mentions['entities'] : array();

        $paragraph['annotation_id'] = isset($ne['annot_id']) ? $ne['annot_id'] : 0;
        $paragraph['ne_by_token'] = get_ne_tokens_by_paragraph($paragraph['id'], $_SESSION['user_id'], $tagset_id);
        $paragraph['comments'] = get_comments_by_paragraph($paragraph['id'], $_SESSION['user_id'], $tagset_id);

        $paragraph['mine'] = false;
        if (in_array($paragraph['id'], $paragraphs_status['unavailable']) or
            in_array($paragraph['id'], $paragraphs_status['done_by_user'])) {
            $paragraph['disabled'] = true;
        }
        elseif (in_array($paragraph['id'], $paragraphs_status['started_by_user'])) {
            $paragraph['mine'] = true;
        }

        if (in_array($paragraph['id'], $paragraphs_status['done_by_user'])) {
            $paragraph['done_by_me'] = true;
        }

        // если текущий пользователь - модератор, забираем разметку других пользователей
        if (!$is_book_moderator) continue;

        $annotators = get_paragraph_annotators($paragraph['id'], $tagset_id);
        $paragraph['all_annotations'] = array();

        foreach ($annotators as $user_id) {
            $paragraph['all_annotations'][$user_id] = array();
            $PAR = &$paragraph['all_annotations'][$user_id];

            $ne = get_ne_by_paragraph($paragraph['id'], $user_id, $tagset_id);
            $mentions = get_ne_by_paragraph($paragraph['id'], $user_id, $tagset_id, TRUE);

            $PAR['named_entities'] = isset($ne['entities']) ? $ne['entities'] : array();
            $PAR['mentions'] = isset($mentions['entities']) ? $mentions['entities'] : array();
            $PAR['user_shown_name'] = get_user_shown_name($user_id);

            $PAR['annotation_id'] = isset($ne['annot_id']) ? $ne['annot_id'] : 0;
            $PAR['ne_by_token'] = get_ne_tokens_by_paragraph($paragraph['id'], $_SESSION['user_id'], $tagset_id);
            $PAR['comments'] = get_comments_by_paragraph($paragraph['id'], $_SESSION['user_id'], $tagset_id);
        }
    }

    $smarty->assign('book', $book);
    $smarty->assign('use_fast_mode', OPTION(OPT_NE_QUICK));
    $smarty->assign('possible_guidelines',
        array(1 => "Default (2014)", 2 => "Dialogue Eval (2016)"));
    $smarty->assign('current_guideline', OPTION(OPT_NE_TAGSET));

    $smarty->assign('entity_types', get_ne_types($tagset_id));
    $smarty->assign('mention_types', get_object_types($tagset_id));
    $smarty->assign('is_moderator', $is_book_moderator);
    $smarty->display('ner/book.tpl');
}

else {
    switch ($action) {
        case 'add':
            $book_name = trim($_POST['book_name']);
            $book_parent = $_POST['book_parent'];
            $book_id = books_add($book_name, $book_parent);
            if (isset($_POST['goto']))
                header("Location:books.php?book_id=$book_id");
            else
                header("Location:books.php?book_id=$book_parent");
            break;
        case 'rename':
            $name = trim($_POST['new_name']);
            $book_id = $_POST['book_id'];
            books_rename($book_id, $name);
            header("Location:books.php?book_id=$book_id");
            break;
        case 'add_tag':
            $book_id = $_POST['book_id'];
            $tag_name = $_POST['tag_name'];
            books_add_tag($book_id, $tag_name);
            header("Location:books.php?book_id=$book_id");
            break;
        case 'del_tag':
            $book_id = $_GET['book_id'];
            $tag_name = $_GET['tag_name'];
            books_del_tag($book_id, $tag_name);
            header("Location:books.php?book_id=$book_id");
            break;
        case 'merge_sentences':
            $sent1 = $_POST['id1'];
            $sent2 = $_POST['id2'];
            merge_sentences($sent1, $sent2);
            header("Location:sentence.php?id=$sent1");
            break;
        case 'split_token':
            $val = split_token($_POST['tid'], $_POST['nc']);
            header("Location:books.php?book_id=".$val[0]."&full#sen".$val[1]);
            break;
        case 'split_sentence':
            $a = split_sentence($_POST['tid']);
            header("Location:books.php?book_id=".$a[0]."&full#sen".$a[1]);
            break;
        case 'split_paragraph':
            $sent_id = $_GET['sid'];
            $book_id = split_paragraph($sent_id);
            header("Location:books.php?book_id=$book_id&full#sen$sent_id");
            break;
        case 'merge_paragraph':
            list($book_id, $sent_id) = merge_paragraphs($_GET['pid']);
            header("Location:books.php?book_id=$book_id&full#sen$sent_id");
            break;
        case 'del_sentence':
            delete_sentence($_GET['sid']);
            header("Location:books.php?book_id=".$_GET['book_id'].'&full');
            break;
        case 'del_paragraph':
            delete_paragraph($_GET['pid']);
            header("Location:books.php?book_id=".$_GET['book_id'].'&full');
            break;
        case 'move':
            books_move($_POST['book_id'], $_POST['book_to']);
            header("Location:books.php?book_id=$book_to");
            break;
    }
}
log_timing();
?>
