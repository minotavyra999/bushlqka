<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Bushlyak_Booking_REST' ) ) {

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

        public static function verify_nonce( $request ) {
            $nonce = $request->get_header( 'X-WP-Nonce' );
            if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
                return new WP_Error( 'forbidden', 'Невалиден или липсващ nonce.', [ 'status' => 403 ] );
            }
            return true;
        }

        /** API: цени */
        public static function get_pricing() {
            return Bushlyak_Booking_DB::get_prices();
        }

        /** API: заетост */
        public static function post_availability( $request ) {
            $params = $request->get_json_params();
            $start = sanitize_text_field( $params['start'] ?? '' );
            $end   = sanitize_text_field( $params['end'] ?? '' );

            if ( empty( $start ) || empty( $end ) ) {
                return new WP_Error( 'invalid_request', 'Липсва начален или краен час.', [ 'status' => 400 ] );
            }

            return [
                'unavailable' => Bushlyak_Booking_DB::find_unavailable_sectors( $start, $end )
            ];
        }

        /** API: резервация */
        public static function post_booking( $request ) {
            $params = $request->get_json_params();

            if ( empty( $params['start'] ) || empty( $params['end'] ) || empty( $params['sector'] ) ) {
                return new WP_Error( 'missing_field', 'Липсват задължителни полета.', [ 'status' => 400 ] );
            }

            $booking_id = Bushlyak_Booking_DB::create_booking( $params );

            if ( ! $booking_id ) {
                return new WP_Error( 'db_error', 'Неуспешно създаване на резервация.', [ 'status' => 500 ] );
            }

            return [ 'ok' => true, 'bookingId' => $booking_id ];
        }

        /** API: blackouts */
        public static function get_blackouts() {
            return Bushlyak_Booking_DB::list_blackouts();
        }

        /** API: методи за плащане */
        public static function get_paymethods() {
            return Bushlyak_Booking_DB::list_paymethods();
        }
    }
}
