<?php
if (!defined('ABSPATH')) exit;

class Bushlyak_Booking_REST {
    public function __construct(){
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(){
        register_rest_route('bush/v1', '/pricing', [
            'methods' => 'GET',
            'callback' => [$this, 'get_pricing'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('bush/v1', '/availability', [
            'methods' => 'POST',
            'callback' => [$this, 'post_availability'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('bush/v1', '/bookings', [
            'methods' => 'POST',
            'callback' => [$this, 'post_booking'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('bush/v1', '/blackouts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_blackouts'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('bush/v1', '/payments/methods', [
            'methods' => 'GET',
            'callback' => [$this, 'get_paymethods'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_pricing($req){
        return Bushlyak_Booking_DB::get_prices();
    }

    public function post_availability($req){
        $p = $req->get_json_params();
        $start = sanitize_text_field($p['start'] ?? '');
        $end   = sanitize_text_field($p['end'] ?? '');
        if (!$start || !$end) return new WP_Error('bad_request','Missing dates', ['status' => 400]);
        $unav = Bushlyak_Booking_DB::find_unavailable_sectors($start, $end);
        return [ 'unavailableSectors' => $unav ];
    }

    public function get_blackouts($req){
        $rows = Bushlyak_Booking_DB::list_blackout_ranges();
        $out = [];
        foreach ($rows as $r) { $out[] = [ 'start' => $r->start_date, 'end' => $r->end_date ]; }
        return $out;
    }

    public function get_paymethods($req){
        $rows = Bushlyak_Booking_DB::list_paymethods();
        $out = [];
        foreach ($rows as $r) { $out[] = [ 'id' => (int)$r->id, 'name' => $r->name, 'instructions' => $r->instructions ]; }
        return $out;
    }

    public function post_booking($req){
        $p = $req->get_json_params();
        $required = ['start','end','sector','anglers','client'];
        foreach ($required as $k) if (!isset($p[$k])) return new WP_Error('bad_request', 'Missing '.$k, ['status'=>400]);

        $start = sanitize_text_field($p['start']);
        $end   = sanitize_text_field($p['end']);
        $sector = intval($p['sector']);
        $anglers = intval($p['anglers']);
        $second_has_card = !empty($p['secondHasCard']) ? 1 : 0;
        $client = $p['client'];

        // Нормализиране на 12:00 → 12:00 и правило „петък ⇒ минимум 48ч“
        try {
            $s = new DateTime($start); $e = new DateTime($end);
            $s->setTime(12,0); $e->setTime(12,0);
            if ((int)$s->format('N') === 5) { // Friday
                $minEnd = clone $s; $minEnd->modify('+2 days');
                if ($e < $minEnd) $e = $minEnd;
            } else if ($e <= $s) {
                $e = (clone $s)->modify('+1 day');
            }
            $start = $s->format('Y-m-d H:i:s'); $end = $e->format('Y-m-d H:i:s');
        } catch (\Exception $ex) {}

        $payMethodId = isset($p['payMethodId']) ? intval($p['payMethodId']) : 0;
        $pm = null;
        if ($payMethodId) {
            foreach (Bushlyak_Booking_DB::list_paymethods() as $m) { if ((int)$m->id === $payMethodId) { $pm = $m; break; } }
        }

        $data = [
            'start' => $start,
            'end' => $end,
            'sector' => $sector,
            'anglers' => $anglers,
            'second_has_card' => $second_has_card,
            'client_first' => sanitize_text_field($client['firstName'] ?? ''),
            'client_last'  => sanitize_text_field($client['lastName'] ?? ''),
            'email' => sanitize_email($client['email'] ?? ''),
            'phone' => sanitize_text_field($client['phone'] ?? ''),
            'notes' => sanitize_text_field($client['notes'] ?? ''),
            'price_estimate' => floatval($p['priceEstimate'] ?? 0),
            'status' => 'pending',
            'pay_method' => $pm ? $pm->name : null,
            'pay_instructions' => $pm ? $pm->instructions : null,
        ];

        $id = Bushlyak_Booking_DB::create_booking($data);
        if (!$id){ return new WP_Error('db_insert_failed', 'Неуспешно записване в базата данни.', ['status'=>500]); }

        // Имейл известие към админ(и)
        $emails_raw = get_option('bush_notify_emails', get_option('admin_email'));
        $emails = array_map('trim', explode(',', $emails_raw));
        $subject = 'Нова резервация – Яз. Бушляк';
        $priceFmt = number_format((float)$data['price_estimate'], 2, '.', ' ') . ' лв';

        $body  = '<h2 style="margin:0 0 12px 0;font-family:Arial">Нова резервация – Яз. Бушляк</h2>';
        $body .= '<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-family:Arial;border:1px solid #e5e7eb">';
        $row = function($a,$b){ return '<tr><td style="border:1px solid #e5e7eb;background:#f9fafb"><b>'.$a.'</b></td><td style="border:1px solid #e5e7eb">'.esc_html($b).'</td></tr>'; };
        $body .= $row('Период', $data['start'].' → '.$data['end']);
        $body .= $row('Сектор', $data['sector']);
        $body .= $row('Рибари', $data['anglers'].($data['anglers']==2?($data['second_has_card']?' (2-ри с карта)':' (2-ри без карта)') : ''));
        $body .= $row('Цена', $priceFmt);
        $body .= $row('Име', $data['client_first'].' '.$data['client_last']);
        $body .= $row('Имейл', $data['email']);
        $body .= $row('Телефон', $data['phone']);
        if (!empty($data['notes'])) $body .= $row('Бележка', $data['notes']);
        if (!empty($data['pay_method'])) $body .= $row('Метод на плащане', $data['pay_method']);
        if (!empty($data['pay_instructions'])) $body .= '<tr><td style="border:1px solid #e5e7eb;background:#f9fafb"><b>Инструкции</b></td><td style="border:1px solid #e5e7eb">'.wp_kses_post($data['pay_instructions']).'</td></tr>';
        $body .= '</table>';

        $link = add_query_arg(['page'=>'bushlyak-booking','view'=>$id], admin_url('admin.php'));
        $body .= '<p><a href="'.esc_url($link).'" style="display:inline-block;background:#2563eb;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;font-family:Arial">Прегледай резервацията</a></p>';

        add_filter('wp_mail_content_type', function(){ return 'text/html; charset=UTF-8'; });
        foreach ($emails as $to){ if ($to) wp_mail($to, $subject, $body); }
        remove_filter('wp_mail_content_type', '__return_false');

        return [ 'ok' => true, 'bookingId' => (string)$id ];
    }
}
