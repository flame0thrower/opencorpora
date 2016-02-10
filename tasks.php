<?php
require('lib/header.php');
require_once('lib/constants.php');
require_once('lib/lib_xml.php');
require_once('lib/lib_morph_pools.php');
require_once('lib/lib_multiwords.php');

$smarty->assign('active_page','tasks');

if (!is_logged()) {
    $smarty->assign('content', get_wiki_page("Инструкция по интерфейсу для снятия омонимии"));
    $smarty->display('qa/tasks_guest.tpl');
    return;
}

$action = GET('act', '');
$smarty->assign('user_rating', get_user_rating($_SESSION['user_id']));

switch ($action) {
    case 'annot':
        $pool_size = 5;
        $opt = OPTION(OPT_SAMPLES_PER_PAGE);
        switch ($opt) {
            case 2:
                $pool_size = 10;
                break;
            case 3:
                $pool_size = 20;
                break;
            case 4:
                $pool_size = 50;
        }

        $pool_id = (int)GET('pool_id');
        if ($t = get_annotation_packet($pool_id, $pool_size)) {
            $smarty->assign('packet', $t);
            $smarty->display('qa/morph_annot.tpl');
        } else {
            $smarty->assign('next_pool_id', get_next_pool($_SESSION['user_id'], $pool_id));
            $smarty->assign('final', true);
            if (game_is_on()) {
                $am2 = new AchievementsManager($_SESSION['user_id']);
                $smarty->assign('achievement', $am2->get_closest());
            }
            $smarty->display('qa/morph_annot_thanks.tpl');
        }
        break;
    case 'my':
        if ($t = get_my_answers((int)GET('pool_id'), 0)) {
            $smarty->assign('packet', $t);
            $smarty->display('qa/morph_annot.tpl');
        } else {
            show_error("Не нашлось примеров.");
        }
        break;
    case 'pause':
        $smarty->assign('next_pool_id', get_next_pool($_SESSION['user_id'], (int)GET('pool_id')));
        if (game_is_on()) {
            $am2 = new AchievementsManager($_SESSION['user_id']);
            $smarty->assign('achievement', $am2->get_closest());
        }
        $smarty->display('qa/morph_annot_thanks.tpl');
        break;
    case 'mwords':
        check_permission(PERM_MULTITOKENS);
        $pool_size = 5;
        $opt = OPTION(OPT_SAMPLES_PER_PAGE);
        switch ($opt) {
            case 2:
                $pool_size = 10;
                break;
            case 3:
                $pool_size = 20;
                break;
            case 4:
                $pool_size = 50;
        }

        $smarty->assign('mwords', MultiWordTask::get_tasks($_SESSION['user_id'], $pool_size));
        $smarty->assign('answers', array("ANSWER_YES" => MultiWordTask::ANSWER_YES, "ANSWER_NO" => MultiWordTask::ANSWER_NO, "ANSWER_SKIP" => MultiWordTask::ANSWER_SKIP));
        $smarty->display('mwords_annot.tpl');
        break;
    default:
        $smarty->assign('available', get_available_tasks($_SESSION['user_id']));
        $smarty->assign('complexity',array(
            0 => 'Сложность неизвестна',
            1 => 'Очень простые задания',
            2 => 'Простые задания',
            3 => 'Сложные задания',
            4 => 'Очень сложные задания'));
        $smarty->display('qa/tasks.tpl');
}
log_timing();
?>
