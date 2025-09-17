<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ( ! $booking_id ) {
    echo '<p style="color:red;">Няма намерена резервация.</p>';
    return;
}

$b = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id = {$booking_id}");
if ( ! $b ) {
    echo '<p style="color:red;">Няма намерена резервация.</p>';
    return;
}

// взимаме метод на плащане
$pay = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_paymethods WHERE id=".intval($b->pay_method));
$pay_display = $pay ? $pay->name . ' – ' . $pay->instructions : '—';

// изчисляваме цената
$price = Bushlyak_Booking_REST::calculate_price(
    intval($b->anglers),
    !empty($b->secondHasCard),
    $b->start,
    $b->end
);
?>

<div class="bushlyak-summary">
    <h2 style="color:#0073aa;">Вашата резервация е получена!</h2>
    <p>Благодарим ви, <strong><?php echo esc_html($b->client_first . ' ' . $b->client_last); ?></strong>. Ето детайлите на вашата резервация:</p>

    <table style="border-collapse:collapse;width:100%;margin:20px 0;">
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Резервация №</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo $b->id; ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Период</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo esc_html($b->start).' – '.esc_html($b->end); ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Сектор</th>
            <td style="border:1px solid #ccc;padding:8px;">Сектор <?php echo esc_html($b->sector); ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Брой рибари</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo intval($b->anglers); ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Втори с карта</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo !empty($b->secondHasCard) ? 'Да' : 'Не'; ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Метод на плащане</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo esc_html($pay_display); ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Бележки</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo nl2br(esc_html($b->notes)); ?></td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Цена</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo number_format($price, 2, '.', ' '); ?> лв.</td>
        </tr>
        <tr>
            <th style="text-align:left;border:1px solid #ccc;padding:8px;">Статус</th>
            <td style="border:1px solid #ccc;padding:8px;"><?php echo esc_html($b->status); ?></td>
        </tr>
    </table>

    <p>Ще получите допълнителен имейл, когато резервацията бъде одобрена от администратор.</p>
</div>
