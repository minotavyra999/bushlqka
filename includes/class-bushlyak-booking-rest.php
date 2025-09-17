<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bushlyak_Booking_REST {

    public static function register_routes() {
        register_rest_route('bush/v1', '/bookings', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_booking'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('bush/v1', '/available-sectors', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_available_sectors'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('bush/v1', '/pricing', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_pricing'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function create_booking($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bush_bookings';

        $data = [
            'start'        => sanitize_text_field($request['start']),
            'end'          => sanitize_text_field($request['end']),
            'sector'       => intval($request['sector']),
            'anglers'      => intval($request['anglers']),
            'secondHasCard'=> intval($request['secondHasCard']),
            'pay_method'   => intval($request['payMethod']),
            'notes'        => sanitize_textarea_field($request['notes']),
            'client_first' => sanitize_text_field($request['firstName']),
            'client_last'  => sanitize_text_field($request['lastName']),
            'client_email' => sanitize_email($request['email']),
            'client_phone' => sanitize_text_field($request['phone']),
            'status'       => 'pending',
            'created_at'   => current_time('mysql'),
        ];

        $wpdb->insert($table, $data);
        if ($wpdb->last_error) {
            return new WP_Error('db_error', $wpdb->last_error, ['status'=>500]);
        }

        return ['success'=>true,'id'=>$wpdb->insert_id];
    }

    public static function get_available_sectors($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bush_bookings';

        $start = sanitize_text_field($request['start']);
        $end   = sanitize_text_field($request['end']);

        // Заети сектори
        $booked = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT sector FROM $table 
             WHERE (start <= %s AND end >= %s) 
             AND status IN ('pending','approved')",
            $end, $start
        ));

        $all = range(1,19);
        $available = array_values(array_diff($all, $booked ?: []));

        return ['available'=>$available];
    }

    public static function get_pricing($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'bush_prices';
        return $wpdb->get_results("SELECT * FROM $table");
    }
}
