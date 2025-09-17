<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bushlyak_Booking_REST {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    public static function register_routes() {
        $namespace = 'bush/v1';

        register_rest_route( $namespace, '/pricing', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'get_pricing' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( $namespace, '/availability', [
            'methods'  => 'POST',
            'callback' => [ __CLASS__, 'post_availability' ],
            'permission_callback' => [ __CLASS__, 'verify_nonce' ],
        ]);

        register_rest_route( $namespace, '/bookings', [
            'methods'  => 'POST',
            'callback' => [ __CLASS__, 'post_booking' ],
            'permission_callback' => [ __CLASS__, 'verify_nonce' ],
        ]);

        register_rest_route( $namespace, '/blackouts', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'get_blackouts' ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route( $namespace, '/payments/methods', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'get_paymethods' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /** Verify nonce for security */
    public static function verify_nonce( $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'forbidden', 'Невалиден или липсващ nonce.', [ 'status' => 403 ] );
        }
        return true;
    }

    /** Get current pricing */
    public static function get_pricing( $request ) {
        return Bushlyak_Booking_DB::get_prices();
    }

    /** Check availability */
    public static function post_availability( $request ) {
        $params = $request->get_json_params();
        $start = sanitize_text_field( $params['start'] ?? '' );
        $end   = sanitize_text_field( $params['end'] ?? '' );

        if ( empty( $start ) || empty( $end ) ) {
            return new WP_Error( 'invalid_request', 'Липсва начален или краен час.', [ 'status' => 400 ] );
        }

        $unavailable = Bushlyak_Booking_DB::find_unavailable_sectors( $start, $end );
        return [ 'unavailable' => $unavailable ];
    }

    /** Create new booking */
    public static function post_booking( $request ) {
        $params = $request->get_json_params();

        // Validate required fields
        $required = [ 'start', 'end', 'sector', 'anglers', 'client' ];
        foreach ( $required as $field ) {
            if ( empty( $params[ $field ] ) ) {
                return new WP_Error( 'missing_field', "Полето {$field} е задължително.", [ 'status' => 400 ] );
            }
        }

        $start = sanitize_text_field( $params['start'] );
        $end   = sanitize_text_field( $params['end'] );
        $sector = sanitize_text_field( $params['sector'] );
        $anglers = intval( $params['anglers'] );

        $client = [
            'firstName' => sanitize_text_field( $params['client']['firstName'] ?? '' ),
            'lastName'  => sanitize_text_field( $params['client']['lastName'] ?? '' ),
            'email'     => sanitize_email( $params['client']['email'] ?? '' ),
            'phone'     => sanitize_text_field( $params['client']['phone'] ?? '' ),
        ];

        if ( empty( $client['firstName'] ) || empty( $client['lastName'] ) ) {
            return new WP_Error( 'invalid_client', 'Моля въведете име и фамилия.', [ 'status' => 400 ] );
        }
        if ( ! is_email( $client['email'] ) ) {
            return new WP_Error( 'invalid_email', 'Невалиден имейл адрес.', [ 'status' => 400 ] );
        }
        if ( ! preg_match( '/^[0-9+\-\s]{6,20}$/', $client['phone'] ) ) {
            return new WP_Error( 'invalid_phone', 'Невалиден телефонен номер.', [ 'status' => 400 ] );
        }

        // Check if sector is available
        $conflicts = Bushlyak_Booking_DB::find_unavailable_sectors( $start, $end );
        if ( in_array( $sector, $conflicts, true ) ) {
            return new WP_Error( 'conflict', 'Избраният сектор е вече зает.', [ 'status' => 409 ] );
        }

        // Create booking
        $booking_id = Bushlyak_Booking_DB::create_booking([
            'start'  => $start,
            'end'    => $end,
            'sector' => $sector,
            'anglers'=> $anglers,
            'client' => $client,
            'notes'  => sanitize_textarea_field( $params['notes'] ?? '' ),
            'pay_method'      => sanitize_text_field( $params['payMethodId'] ?? '' ),
            'pay_instructions'=> sanitize_textarea_field( $params['payInstructions'] ?? '' ),
        ]);

        if ( ! $booking_id ) {
            return new WP_Error( 'db_error', 'Неуспешно създаване на резервация.', [ 'status' => 500 ] );
        }

        // Send email notification
        $to = get_option( 'bush_notify_emails', get_option( 'admin_email' ) );
        $subject = "Нова резервация #{$booking_id}";
        $message = "Име: {$client['firstName']} {$client['lastName']}\n"
                 . "Имейл: {$client['email']}\nТелефон: {$client['phone']}\n"
                 . "Сектор: {$sector}\nОт: {$start}\nДо: {$end}\n"
                 . "Рибари: {$anglers}\n\nБележки: " . ( $params['notes'] ?? '' );

        wp_mail( $to, $subject, $message );

        return [ 'ok' => true, 'bookingId' => $booking_id ];
    }

    /** Get blackout ranges */
    public static function get_blackouts( $request ) {
        return Bushlyak_Booking_DB::list_blackout_ranges();
    }

    /** Get pay methods */
    public static function get_paymethods( $request ) {
        return Bushlyak_Booking_DB::list_paymethods();
    }
}
