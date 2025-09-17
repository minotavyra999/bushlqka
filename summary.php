<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$booking_id = isset($_GET['booking']) ? intval($_GET['booking']) : 0;
$booking    = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id=%d", $booking_id) );

if ( ! $booking ) {
    echo '<p>Няма такава резервация.</p>';
    return;
}

// Форматиране на периода (12:00 -> 12:00)
$start_date = date_i18n( 'd.m.Y', strtotime($booking->start) ) . ' 12:00';
$end_date   = date_i18n( 'd.m.Y', strtotime($booking->end) )   . ' 12:00';

$sector_name = "Сектор " . esc_html( $booking->sector );
$price = Bushlyak_Booking_REST::calculate_price(
    $booking->anglers,
    isset($booking->secondHasCard) ? (bool)$booking->secondHasCard : false,
    $booking->start,
    $booking->end
);
?>
<div class="bush-summary">
    <h2>Вашата резервация е получена!</h2>
    <p>Благодарим ви, <strong><?php echo esc_html( $booking->client_first . ' ' . $booking->client_last ); ?></strong>. Ето детайлите на вашата резервация:</p>

    <table class="wp-list-table widefat fixed striped">
        <tr>
            <th>Резервация №</th>
            <td><?php echo intval($booking->id); ?></td>
        </tr>
        <tr>
            <th>Период</th>
            <td><?php echo esc_html( $start_date . " – " . $end_date ); ?></td>
        </tr>
        <tr>
            <th>Сектор</th>
            <td><?php echo esc_html( $sector_name ); ?></td>
        </tr>
        <tr>
            <th>Брой рибари</th>
            <td><?php echo intval($booking->anglers); ?></td>
        </tr>
        <tr>
            <th>Втори с карта</th>
            <td><?php echo !empty($booking->secondHasCard) ? 'Да' : 'Не'; ?></td>
        </tr>
        <tr>
            <th>Метод на плащане</th>
            <td><?php echo esc_html( $booking->pay_method ); ?></td>
        </tr>
        <tr>
            <th>Бележки</th>
            <td><?php echo esc_html( $booking->notes ); ?></td>
        </tr>
        <tr>
            <th>Цена</th>
            <td><?php echo number_format_i18n($price, 2) . ' лв.'; ?></td>
        </tr>
        <tr>
            <th>Статус</th>
            <td><?php echo esc_html( $booking->status ); ?></td>
        </tr>
    </table>

    <p>Ще получите допълнителен имейл, когато резервацията бъде одобрена от администратор.</p>
</div>
