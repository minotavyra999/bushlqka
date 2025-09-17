<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$booking = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id=%d",
    $booking_id
) );

if ( ! $booking ) {
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

// Съобщение
$subject = "Вашата резервация (#{$booking->id}) е получена";
$message = "
<p>Здравейте, <strong>{$booking->client_first} {$booking->client_last}</strong>,</p>
<p>Вашата резервация беше получена успешно. Ето детайлите:</p>
<table border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse;'>
    <tr><th align='left'>Резервация №</th><td>{$booking->id}</td></tr>
    <tr><th align='left'>Период</th><td>{$start_date} – {$end_date}</td></tr>
    <tr><th align='left'>Сектор</th><td>{$sector_name}</td></tr>
    <tr><th align='left'>Брой рибари</th><td>{$booking->anglers}</td></tr>
    <tr><th align='left'>Втори с карта</th><td>" . (!empty($booking->secondHasCard) ? 'Да' : 'Не') . "</td></tr>
    <tr><th align='left'>Метод на плащане</th><td>{$booking->pay_method}</td></tr>
    <tr><th align='left'>Бележки</th><td>{$booking->notes}</td></tr>
    <tr><th align='left'>Цена</th><td>" . number_format_i18n($price, 2) . " лв.</td></tr>
    <tr><th align='left'>Статус</th><td>{$booking->status}</td></tr>
</table>
<p>Ще получите допълнителен имейл, когато резервацията бъде разгледана от администратор.</p>
";

$headers = ['Content-Type: text/html; charset=UTF-8'];
wp_mail( $booking->client_email, $subject, $message, $headers );
