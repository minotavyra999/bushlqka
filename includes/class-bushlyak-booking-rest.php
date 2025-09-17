<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bushlyak_Booking_REST {

    public function register_routes() {
        register_rest_route('bushlyaka/v1', '/bookings', [
            'methods'  => 'POST',
            'callback' => [ $this, 'create_booking' ],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('bushlyaka/v1', '/pricing', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_pricing' ],
            'permission_callback' => '__return_true'
        ]);

        // Нов endpoint за свободни сектори
        register_rest_route('bushlyaka/v1', '/available-sectors', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_available_sectors' ],
            'args'     => [
                'start' => [ 'required' => true ],
                'end'   => [ 'required' => true ],
            ],
            'permission_callback' => '__return_true'
        ]);
    }

    public function create_booking( $request ) {
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
            'created_at'   => current_time('mysql')
        ];

        $inserted = $wpdb->insert($table, $data);

        if ( ! $inserted ) {
            return new WP_Error('db_insert_error', 'Грешка при запис в базата', [ 'status' => 500 ]);
        }

        $booking_id = $wpdb->insert_id;

        return [
            'id'     => $booking_id,
            'status' => 'success'
        ];
    }

    public function get_pricing() {
        global $wpdb;
        $table = $wpdb->prefix . 'bush_prices';
        $price = $wpdb->get_row("SELECT * FROM $table ORDER BY id DESC LIMIT 1");
        if ( ! $price ) {
            return [
                'base'             => 0,
                'second'           => 0,
                'second_with_card' => 0
            ];
        }
        return $price;
    }

    public function get_available_sectors( $request ) {
        global $wpdb;

        $table_bookings = $wpdb->prefix . 'bush_bookings';
        $table_sectors  = $wpdb->prefix . 'bush_sectors';

        $start = sanitize_text_field( $request['start'] );
        $end   = sanitize_text_field( $request['end'] );

        // Всички сектори
        $sectors = $wpdb->get_results("SELECT * FROM $table_sectors");

        // Заети сектори (само approved резервации)
        $booked = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT sector FROM $table_bookings 
                 WHERE status = 'approved'
                 AND (
                     (start <= %s AND end >= %s)
                     OR (start < %s AND end >= %s)
                     OR (start >= %s AND end <= %s)
                 )",
                $end, $start, $end, $start, $start, $end
            )
        );

        // Филтрираме свободните
        $available = array_filter($sectors, function($s) use ($booked) {
            return ! in_array($s->id, $booked);
        });

        return rest_ensure_response(array_values($available));
    }
}
