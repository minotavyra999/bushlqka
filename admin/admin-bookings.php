<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$bookings = Bushlyak_Booking_DB::list_bookings(100);
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
                <th>Телефон</th>
                <th>Имейл</th>
                <th>Рибари</th>
                <th>Бележки</th>
                <th>Метод на плащане</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $bookings ) : ?>
                <?php foreach ( $bookings as $b ) : ?>
                    <?php
                    // Форматираме периода като 12:00 -> 12:00
                    $start = date_i18n( 'd.m.Y', strtotime($b->start) ) . ' 12:00';
                    $end   = date_i18n( 'd.m.Y', strtotime($b->end) )   . ' 12:00';
                    ?>
                    <tr>
                        <td><?php echo intval($b->id); ?></td>
                        <td><?php echo esc_html($start . ' – ' . $end); ?></td>
                        <td><?php echo 'Сектор ' . esc_html($b->sector); ?></td>
                        <td><?php echo esc_html($b->client_first . ' ' . $b->client_last); ?></td>
                        <td><?php echo esc_html($b->client_phone); ?></td>
                        <td><?php echo esc_html($b->client_email); ?></td>
                        <td><?php echo intval($b->anglers); ?></td>
                        <td><?php echo esc_html($b->notes); ?></td>
                        <td><?php echo esc_html($b->pay_method); ?></td>
                        <td><?php echo esc_html($b->status); ?></td>
                        <td>
                            <?php if ( $b->status === 'pending' ) : ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bushlyak_approve_booking&id='.$b->id), 'bush_booking_action'); ?>" class="button button-primary">Одобри</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bushlyak_reject_booking&id='.$b->id), 'bush_booking_action'); ?>" class="button">Откажи</a>
                            <?php endif; ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=bushlyak_delete_booking&id='.$b->id), 'bush_booking_action'); ?>" class="button button-danger" onclick="return confirm('Сигурни ли сте, че искате да изтриете тази резервация?');">Изтрий</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="11">Няма намерени резервации.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
