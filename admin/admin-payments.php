<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Добавяне на плащане
if ( isset($_POST['bush_add_payment']) && check_admin_referer('bush_payment_action') ) {
    $booking_id = intval($_POST['booking_id']);
    $amount     = floatval($_POST['amount']);
    $method     = sanitize_text_field($_POST['method']);
    $reference  = sanitize_text_field($_POST['reference']);

    $wpdb->insert("{$wpdb->prefix}bush_payments", [
        'booking_id' => $booking_id,
        'amount'     => $amount,
        'method'     => $method,
        'reference'  => $reference,
        'received_at'=> current_time('mysql'),
    ]);
    echo '<div class="updated"><p>Плащането е добавено.</p></div>';
}

// Изтриване
if ( isset($_GET['delete']) && check_admin_referer('bush_payment_delete') ) {
    $wpdb->delete("{$wpdb->prefix}bush_payments", [ 'id' => intval($_GET['delete']) ]);
    echo '<div class="updated"><p>Плащането е изтрито.</p></div>';
}

$payments = $wpdb->get_results("
    SELECT p.*, b.client_first, b.client_last 
    FROM {$wpdb->prefix}bush_payments p
    LEFT JOIN {$wpdb->prefix}bush_bookings b ON p.booking_id = b.id
    ORDER BY p.received_at DESC
");
?>
<div class="wrap">
    <h1>Плащания</h1>

    <form method="post">
        <?php wp_nonce_field('bush_payment_action'); ?>
        <table class="form-table">
            <tr>
                <th><label for="booking_id">Резервация №</label></th>
                <td><input type="number" name="booking_id" required></td>
            </tr>
            <tr>
                <th><label for="amount">Сума</label></th>
                <td><input type="text" name="amount" required></td>
            </tr>
            <tr>
                <th><label for="method">Метод</label></th>
                <td><input type="text" name="method" required></td>
            </tr>
            <tr>
                <th><label for="reference">Референция</label></th>
                <td><input type="text" name="reference"></td>
            </tr>
        </table>
        <p><input type="submit" name="bush_add_payment" class="button-primary" value="Добави"></p>
    </form>

    <h2>Списък с плащания</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Резервация №</th>
                <th>Клиент</th>
                <th>Сума</th>
                <th>Метод</th>
                <th>Референция</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $payments ) : ?>
                <?php foreach ( $payments as $p ) : ?>
                    <?php
                    $received = date_i18n( 'd.m.Y', strtotime($p->received_at) ) . ' 12:00';
                    ?>
                    <tr>
                        <td><?php echo intval($p->id); ?></td>
                        <td><?php echo intval($p->booking_id); ?></td>
                        <td><?php echo esc_html($p->client_first . ' ' . $p->client_last); ?></td>
                        <td><?php echo number_format_i18n($p->amount, 2) . ' лв.'; ?></td>
                        <td><?php echo esc_html($p->method); ?></td>
                        <td><?php echo esc_html($p->reference); ?></td>
                        <td><?php echo esc_html($received); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=bushlyak-payments&delete='.$p->id), 'bush_payment_delete'); ?>" class="button" onclick="return confirm('Сигурни ли сте, че искате да изтриете това плащане?');">Изтрий</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="8">Няма въведени плащания.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
