<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bush_bookings ORDER BY id DESC");
?>
<div class="wrap">
    <h1>Резервации</h1>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Период</th>
                <th>Сектор</th>
                <th>Клиент</th>
                <th>Имейл</th>
                <th>Телефон</th>
                <th>Метод на плащане</th>
                <th>Бележки</th>
                <th>Цена</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $bookings ) : ?>
                <?php foreach ( $bookings as $b ) : 
                    // метод на плащане
                    $pay = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_paymethods WHERE id=".intval($b->pay_method));
                    $pay_display = $pay ? $pay->name . ' – ' . $pay->instructions : '—';

                    // цена
                    $price = Bushlyak_Booking_REST::calculate_price(
                        intval($b->anglers),
                        !empty($b->secondHasCard),
                        $b->start,
                        $b->end
                    );
                ?>
                <tr>
                    <td><?php echo $b->id; ?></td>
                    <td><?php echo esc_html($b->start . ' – ' . $b->end); ?></td>
                    <td><?php echo esc_html($b->sector); ?></td>
                    <td><?php echo esc_html($b->client_first . ' ' . $b->client_last); ?></td>
                    <td><?php echo esc_html($b->client_email); ?></td>
                    <td><?php echo esc_html($b->client_phone); ?></td>
                    <td><?php echo esc_html($pay_display); ?></td>
                    <td><?php echo nl2br(esc_html($b->notes)); ?></td>
                    <td><?php echo number_format($price, 2, '.', ' '); ?> лв.</td>
                    <td><?php echo esc_html($b->status); ?></td>
                    <td>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bushlyak_approve_booking&id='.$b->id), 'bush_booking_action'); ?>" class="button">Одобри</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bushlyak_reject_booking&id='.$b->id), 'bush_booking_action'); ?>" class="button">Откажи</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bushlyak_delete_booking&id='.$b->id), 'bush_booking_action'); ?>" class="button" onclick="return confirm('Сигурни ли сте, че искате да изтриете тази резервация?')">Изтрий</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="11">Няма резервации.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
