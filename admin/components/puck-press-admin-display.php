<?php
$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
?>
<!-- Our admin page content should all be inside .wrap -->
<div class="wrap">
    <!-- Print the page title -->
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <!-- Here are our tabs -->
    <nav class="nav-tab-wrapper">
        <a href="?page=puck-press" class="nav-tab <?php if ($tab === null) : ?>nav-tab-active<?php endif; ?>">Schedule</a>
        <a href="?page=puck-press&tab=roster" class="nav-tab <?php if ($tab === 'roster') : ?>nav-tab-active<?php endif; ?>">Roster</a>
        <a href="?page=puck-press&tab=cron" class="nav-tab <?php if ($tab === 'cron') : ?>nav-tab-active<?php endif; ?>">Cron</a>
        <a href="?page=puck-press&tab=game-summary" class="nav-tab <?php if ($tab === 'game-summary') : ?>nav-tab-active<?php endif; ?>">Post Game Summary</a>
        <a href="?page=puck-press&tab=insta-post" class="nav-tab <?php if ($tab === 'insta-post') : ?>nav-tab-active<?php endif; ?>">Insta Post</a>
    </nav>

    <div class="tab-content">
        <?php switch ($tab):
            case 'roster':
                include plugin_dir_path(dirname(__FILE__)) . 'components/roster/roster-admin-display.php';
                $roster_admin_display = new Puck_Press_Roster_Admin_Display();
                echo $roster_admin_display->render();
                break;
            case 'cron':
                include plugin_dir_path(dirname(__FILE__)) . 'components/cron/cron-admin-display.php';
                $cron_admin_display = new Puck_Press_Cron_Admin_Display();
                echo $cron_admin_display->render();
                break;
            case 'game-summary':
                include_once plugin_dir_path(dirname(__FILE__)) . 'components/game-summary-post/game-summary-display-post.php';
                $game_summary_display = new Puck_Press_Admin_Game_Summary_Post_Display();
                echo $game_summary_display->render();
                break;
            case 'insta-post':
                include_once plugin_dir_path(dirname(__FILE__)) . 'components/insta-post-importer/instagram-post-admin-display.php';
                $insta_post_display = new Puck_Press_Admin_Instagram_Post_Importer_Display();
                echo $insta_post_display->render();
                break;
            default:
                include plugin_dir_path(dirname(__FILE__)) . 'components/schedule/schedule-admin-display.php';
                $schedule_admin_display = new Puck_Press_Schedule_Admin_Display();
                echo $schedule_admin_display->render();
                break;
        endswitch; ?>
    </div>
</div>