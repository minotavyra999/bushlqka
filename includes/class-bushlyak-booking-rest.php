<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bushlyak_Booking_REST {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {

        // GET цени
        register_rest_route( 'bush/v1', '/pricing', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'get_pricing' ],
            'permission_callback' => '__return_true',
        ]);

        // POST нова резервация
        register_rest_route( 'bush/v1', '/bookings', [
            'methods'  => 'POST',
            'callback' => [ __CLASS__, 'create_booking' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Връща цените от таблицата bush_prices
     */
    public static function get_pricing( $request ) {
        global $wpdb;
        $prices = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_prices LIMIT 1", ARRAY_A);

        if ( ! $prices ) {
            $prices = [
                'base'              => 50,
                'second'            => 30,
                'second_with_card'  => 20,
            ];
        }
        return $prices;
    }

    /**
     * Създава резервация в таблицата bush_bookings
     */
    public static function create_booking( $request ) {
        global $wpdb;

        // ✅ Разделяме daterange на start и end
        $range = sanitize_text_field( $request['daterange'] ?? '' );
        $dates = explode(" to ", $range);
        $start = !empty($dates[0]) ? $dates[0] : null;
        $end   = !empty($dates[1]) ? $dates[1] : null;

        if ( ! $start || ! $end ) {
            return new WP_Error(
                'invalid_dates',
                __( 'Невалиден период. Моля, изберете начална и крайна дата.', 'bushlyaka' ),
                [ 'status' => 400 ]
            );
        }

        $sector = intval( $request['sector'] );

        // ✅ Проверка за конфликт със съществуваща одобрена резервация
        if ( Bushlyak_Booking_DB::has_conflict( $start, $end, $sector ) ) {
            return new WP_Error(
                'conflict',
                __( 'Секторът вече е зает за избрания период.', 'bushlyaka' ),
                [ 'status' => 409 ]
            );
        }

        $data = [
            'start'         => $start,
            'end'           => $end,
            'sector'        => $sector,
            'anglers'       => intval( $request['anglers'] ),
            'secondHasCard' => ! empty($request['secondHasCard']) ? 1 : 0,
            'pay_method'    => intval( $request['payMethod'] ),
            'notes'         => sanitize_textarea_field( $request['notes'] ),
            'client_first'  => sanitize_text_field( $request['firstName'] ),
            'client_last'   => sanitize_text_field( $request['lastName'] ),
            'client_email'  => sanitize_email( $request['email'] ),
            'client_phone'  => sanitize_text_field( $request['phone'] ),
            'status'        => 'pending',
            'created_at'    => current_time('mysql'),
        ];

        $inserted = $wpdb->insert( "{$wpdb->prefix}bush_bookings", $data );

        if ( ! $inserted ) {
            // DEBUG LOG
            error_log("Bushlyak Booking DB insert error: " . $wpdb->last_error);
            error_log("Bushlyak Booking DB insert data: " . print_r($data, true));
            return new WP_Error( 'db_error', 'Грешка при запис в базата', [ 'status' => 500 ] );
        }

        $booking_id = $wpdb->insert_id;

        // Изпращаме имейл с детайли
        Bushlyak_Booking_Plugin::send_booking_email( $booking_id );

        return [
            'id'      => $booking_id,
            'message' => 'Резервацията е успешно създадена.',
        ];
    }

    /**
     * Калкулация на цената
     */
    public static function calculate_price( $anglers, $secondHasCard, $start, $end ) {
        $prices = self::get_pricing(null);

        $s = strtotime($start);
        $e = strtotime($end);
        $days = max(1, ceil(($e - $s) / DAY_IN_SECONDS));

        $total = 0;
        if ($anglers == 1) {
            $total = $prices['base'] * $days;
        } elseif ($anglers == 2 && $secondHasCard) {
            $total = ($prices['base'] + $prices['second_with_card']) * $days;
        } else {
            $total = ($prices['base'] + $prices['second']) * $days;
        }

        return $total;
    }
}
