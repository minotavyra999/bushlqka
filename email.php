<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

$booking_id = isset($booking_id) ? intval($booking_id) : 0;
if ( ! $booking_id ) return;

$b = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id = {$booking_id}");
if ( ! $b ) return;

// Взимаме метод на плащане
$pay = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_paymethods WHERE id=".intval($b->pay_method));
$pay_display = $pay ? $pay->name . ' – ' . $pay->instructions : '—';

// Изчисляваме цената
$price = Bushlyak_Booking_REST::calculate_price(
    intval($b->anglers),
    !empty($b->secondHasCard),
    $b->start,
    $b->end
);

$subject = sprintf( __( 'Вашата резервация №%d', 'bushlyaka' ), $b->id );

// HTML съдържание
$message = '
<h2 style="color:#0073aa;">Потвърждение за резервация</h2>
<p>Здравейте, <strong>'.esc_html($b->client_first).' '.esc_html($b->client_last).'</strong>,</p>
<p>Вашата резервация беше успешно получена. Ето детайлите:</p>

<table style="border-collapse:collapse;width:100%;margin:20px 0;">
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Резервация №</th>
    <td style="border:1px solid #ccc;padding:8px;">'.$b->id.'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Период</th>
    <td style="border:1px solid #ccc;padding:8px;">'.esc_html($b->start).' – '.esc_html($b->end).'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Сектор</th>
    <td style="border:1px solid #ccc;padding:8px;">Сектор '.esc_html($b->sector).'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Брой рибари</th>
    <td style="border:1px solid #ccc;padding:8px;">'.intval($b->anglers).'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Втори с карта</th>
    <td style="border:1px solid #ccc;padding:8px;">'.(!empty($b->secondHasCard) ? 'Да' : 'Не').'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Метод на плащане</th>
    <td style="border:1px solid #ccc;padding:8px;">'.$pay_display.'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Бележки</th>
    <td style="border:1px solid #ccc;padding:8px;">'.nl2br(esc_html($b->notes)).'</td>
  </tr>
  <tr>
    <th style="text-align:left;border:1px solid #ccc;padding:8px;">Цена</th>
    <td style="border:1px solid #ccc;padding:8px;">'.number_format($price, 2, '.', ' ').' лв.</td>
  </tr>
</table>

<p>Ще получите допълнително известие, когато резервацията бъде одобрена от администратор.</p>
<p>Благодарим ви, че избрахте <strong>Bushlyak Booking</strong>!</p>
';

$headers = array('Content-Type: text/html; charset=UTF-8');

// Изпращаме имейл до клиента
wp_mail( $b->client_email, $subject, $message, $headers );

// Изпращаме и до администратора
wp_mail( get_option('admin_email'), 'Нова резервация', $message, $headers );
