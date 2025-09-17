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

        /** Калкулация на цената (на ден × дни) */
        public static function calculate_price( $anglers = 1, $secondHasCard = false, $start = '', $end = '' ) {
            $prices = Bushlyak_Booking_DB::get_prices();
            if ( ! $prices ) return 0;

            $days = 1;
            if ($start && $end) {
                try {
                    $d1 = new DateTime($start);
                    $d2 = new DateTime($end);
                    $days = max(1, $d1->diff($d2)->days);
                } catch (Exception $e) {
                    $days = 1;
                }
            }

            if ( $anglers == 1 ) {
                return floatval($prices->base) * $days;
            }
            if ( $anglers == 2 && $secondHasCard ) {
                return ( floatval($prices->base) + floatval($prices->second_with_card) ) * $days;
            }
            if ( $anglers == 2 ) {
                return ( floatval($prices->base) + floatval($prices->second) ) * $days;
            }

            return 0;
        }

        /** API: Връща цените */
        public static function get_pricing() {
            $prices = Bushlyak_Booking_DB::get_prices();
            if ( ! $prices ) {
                return new WP_Error( 'no_prices', 'Няма въведени цени.', [ 'status' => 404 ] );
            }

            return [
                'base'             => floatval( $prices->base ),
                'second'           => floatval( $prices->second ),
                'second_with_card' => floatval( $prices->second_with_card )
            ];
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

            $price = self::calculate_price(
                intval($params['anglers'] ?? 1),
                !empty($params['secondHasCard']),
                $params['start'] ?? '',
                $params['end'] ?? ''
            );

            // Изпращаме имейл
            Bushlyak_Booking_Plugin::send_booking_email($booking_id);

            return [
                'ok'         => true,
                'bookingId'  => $booking_id,
                'price'      => $price,
                'redirect'   => home_url('/booking-summary?booking=' . $booking_id),
            ];
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
