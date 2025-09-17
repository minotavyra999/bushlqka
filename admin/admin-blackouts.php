<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Добавяне на blackout
if ( isset($_POST['bush_add_blackout']) && check_admin_referer('bush_blackout_action') ) {
    $start  = sanitize_text_field($_POST['start']) . ' 12:00:00';
    $end    = sanitize_text_field($_POST['end'])   . ' 12:00:00';
    $reason = sanitize_text_field($_POST['reason']);

    $wpdb->insert("{$wpdb->prefix}bush_blackouts", [
        'start'  => $start,
        'end'    => $end,
        'reason' => $reason,
    ]);
    echo '<div class="updated"><p>Blackout периодът е добавен.</p></div>';
}

// Изтриване
if ( isset($_GET['delete']) && check_admin_referer('bush_blackout_delete') ) {
    $wpdb->delete("{$wpdb->prefix}bush_blackouts", [ 'id' => intval($_GET['delete']) ]);
    echo '<div class="updated"><p>Blackout периодът е изтрит.</p></div>';
}

$blackouts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bush_blackouts ORDER BY start DESC");
?>
<div class="wrap">
    <h1>Blackout периоди</h1>

    <form method="post">
        <?php wp_nonce_field('bush_blackout_action'); ?>
        <table class="form-table">
            <tr>
                <th><label for="start">Начална дата</label></th>
                <td><input type="date" name="start" required></td>
            </tr>
            <tr>
                <th><label for="end">Крайна дата</label></th>
                <td><input type="date" name="end" required></td>
            </tr>
            <tr>
                <th><label for="reason">Причина</label></th>
                <td><input type="text" name="reason"></td>
            </tr>
        </table>
        <p><input type="submit" name="bush_add_blackout" class="button-primary" value="Добави"></p>
    </form>

    <h2>Списък с blackout периоди</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Период</th>
                <th>Причина</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $blackouts ) : ?>
                <?php foreach ( $blackouts as $b ) : ?>
                    <?php
                    $start = date_i18n('d.m.Y', strtotime($b->start)) . ' 12:00';
                    $end   = date_i18n('d.m.Y', strtotime($b->end))   . ' 12:00';
                    ?>
                    <tr>
                        <td><?php echo intval($b->id); ?></td>
                        <td><?php echo esc_html($start . ' – ' . $end); ?></td>
                        <td><?php echo esc_html($b->reason); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=bushlyak-blackouts&delete='.$b->id), 'bush_blackout_delete'); ?>" class="button" onclick="return confirm('Сигурни ли сте, че искате да изтриете този blackout период?');">Изтрий</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="4">Няма въведени blackout периоди.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
