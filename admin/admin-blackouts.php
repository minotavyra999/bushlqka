<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

if ( isset($_POST['action']) && $_POST['action'] === 'add_blackout' ) {
    check_admin_referer('bush_blackout_action');

    $dates = explode(" to ", sanitize_text_field($_POST['daterange']));
    $start = !empty($dates[0]) ? $dates[0] : null;
    $end   = !empty($dates[1]) ? $dates[1] : null;

    if ($start && $end) {
        $wpdb->insert(
            "{$wpdb->prefix}bush_blackouts",
            [
                'start'  => $start,
                'end'    => $end,
                'reason' => sanitize_text_field($_POST['reason'])
            ],
            [ '%s','%s','%s' ]
        );
    }
}

if ( isset($_GET['delete']) ) {
    $id = intval($_GET['delete']);
    $wpdb->delete( "{$wpdb->prefix}bush_blackouts", [ 'id' => $id ], [ '%d' ] );
}
?>
<div class="wrap">
    <h1><?php _e('Blackout периоди', 'bushlyaka'); ?></h1>

    <form method="post">
        <?php wp_nonce_field('bush_blackout_action'); ?>
        <input type="hidden" name="action" value="add_blackout">
        <table class="form-table">
            <tr>
                <th><label for="daterange"><?php _e('Период', 'bushlyaka'); ?></label></th>
                <td><input type="text" name="daterange" class="blackout-range" required></td>
            </tr>
            <tr>
                <th><label for="reason"><?php _e('Причина', 'bushlyaka'); ?></label></th>
                <td><input type="text" name="reason" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button(__('Добави Blackout', 'bushlyaka')); ?>
    </form>

    <h2><?php _e('Списък с blackout периоди', 'bushlyaka'); ?></h2>
    <table class="widefat">
        <thead>
            <tr>
                <th><?php _e('Начало', 'bushlyaka'); ?></th>
                <th><?php _e('Край', 'bushlyaka'); ?></th>
                <th><?php _e('Причина', 'bushlyaka'); ?></th>
                <th><?php _e('Действия', 'bushlyaka'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bush_blackouts ORDER BY start DESC");
        if ($rows) :
            foreach ($rows as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row->start); ?></td>
                    <td><?php echo esc_html($row->end); ?></td>
                    <td><?php echo esc_html($row->reason); ?></td>
                    <td><a href="?page=bushlyak-blackouts&delete=<?php echo $row->id; ?>" onclick="return confirm('<?php _e('Сигурни ли сте?', 'bushlyaka'); ?>')"><?php _e('Изтрий', 'bushlyaka'); ?></a></td>
                </tr>
            <?php endforeach;
        else : ?>
            <tr><td colspan="4"><?php _e('Няма записи.', 'bushlyaka'); ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(document).ready(function($){
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".blackout-range", {
            mode: "range",
            dateFormat: "Y-m-d H:i:S",
            enableTime: true,
            appendTo: document.body // ✅ за да не се реже календара в админ
        });
    }
});
</script>
