<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$booking = $wpdb->get_row( $wpdb->prepare(
    "SELECT b.*, p.instructions AS pay_instructions
     FROM {$wpdb->prefix}bush_bookings b
     LEFT JOIN {$wpdb->prefix}bush_paymethods p ON b.pay_method = p.id
     WHERE b.id = %d", $id
) );

if ( ! $booking ) {
    echo '<p>Невалидна резервация.</p>';
    return;
}

// Форматираме периода
$period = '';
if ( $booking->start && $booking->end && $booking->start !== '0000-00-00 00:00:00' ) {
    $period = date_i18n( 'd.m.Y H:i', strtotime($booking->start) ) . ' – ' . date_i18n( 'd.m.Y H:i', strtotime($booking->end) );
}
?>
<div class="bush-summary">
    <h2><?php _e('Вашата резервация е получена!', 'bushlyaka'); ?></h2>
    <p><?php printf(__('Благодарим ви, %s %s. Ето детайлите на вашата резервация:', 'bushlyaka'),
        esc_html($booking->client_first), esc_html($booking->client_last)); ?></p>

    <table class="widefat fixed striped">
        <tr>
            <th><?php _e('Резервация №', 'bushlyaka'); ?></th>
            <td><?php echo esc_html($booking->id); ?></td>
        </tr>
        <tr>
            <th><?php _e('Период', 'bushlyaka'); ?></th>
            <td><?php echo $period ? esc_html($period) : __('—', 'bushlyaka'); ?></td>
        </tr>
        <tr>
            <th><?php _e('Сектор', 'bushlyaka'); ?></th>
            <td><?php echo sprintf(__('Сектор %d', 'bushlyaka'), esc_html($booking->sector)); ?></td>
        </tr>
        <tr>
            <th><?php _e('Брой рибари', 'bushlyaka'); ?></th>
            <td><?php echo esc_html($booking->anglers); ?></td>
        </tr>
        <tr>
            <th><?php _e('Втори с карта', 'bushlyaka'); ?></th>
            <td><?php echo ! empty($booking->secondHasCard) ? __('Да', 'bushlyaka') : __('Не', 'bushlyaka'); ?></td>
        </tr>
        <tr>
            <th><?php _e('Метод на плащане', 'bushlyaka'); ?></th>
            <td><?php echo esc_html($booking->pay_instructions ?: $booking->pay_method); ?></td>
        </tr>
        <tr>
            <th><?php _e('Бележки', 'bushlyaka'); ?></th>
            <td><?php echo esc_html($booking->notes); ?></td>
        </tr>
        <tr>
            <th><?php _e('Цена', 'bushlyaka'); ?></th>
            <td>
                <?php
                $total = Bushlyak_Booking_REST::calculate_price(
                    $booking->anglers,
                    ! empty($booking->secondHasCard),
                    $booking->start,
                    $booking->end
                );
                echo number_format_i18n($total, 2) . ' лв.';
                ?>
            </td>
        </tr>
        <tr>
            <th><?php _e('Статус', 'bushlyaka'); ?></th>
            <td><?php echo esc_html($booking->status); ?></td>
        </tr>
    </table>

    <p><?php _e('Ще получите допълнителен имейл, когато резервацията бъде одобрена от администратор.', 'bushlyaka'); ?></p>
</div>
