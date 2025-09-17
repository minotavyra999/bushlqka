<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bushlyak_Booking_DB {

    /** Create database tables */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tables = [];

        // Bookings
        $tables[] = "CREATE TABLE {$wpdb->prefix}bush_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            start DATETIME NOT NULL,
            end DATETIME NOT NULL,
            sector VARCHAR(50) NOT NULL,
            anglers INT NOT NULL DEFAULT 1,
            client_first VARCHAR(100) NOT NULL,
            client_last VARCHAR(100) NOT NULL,
            client_email VARCHAR(100) NOT NULL,
            client_phone VARCHAR(50) NOT NULL,
            notes TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            pay_method VARCHAR(50),
            pay_instructions TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_period (start, end),
            KEY idx_sector (sector)
        ) $charset_collate;";

        // Prices
        $tables[] = "CREATE TABLE {$wpdb->prefix}bush_prices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            valid_from DATE NOT NULL,
            base DECIMAL(10,2) NOT NULL,
            second DECIMAL(10,2) NOT NULL,
            second_with_card DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Payments
        $tables[] = "CREATE TABLE {$wpdb->prefix}bush_payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(50) NOT NULL,
            reference VARCHAR(100),
            received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_booking (booking_id)
        ) $charset_collate;";

        // Blackouts
        $tables[] = "CREATE TABLE {$wpdb->prefix}bush_blackouts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            start DATE NOT NULL,
            end DATE NOT NULL,
            reason VARCHAR(200),
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Paymethods
        $tables[] = "CREATE TABLE {$wpdb->prefix}bush_paymethods (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            instructions TEXT,
            PRIMARY KEY (id)
        ) $charset_collate;";

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }
    }

    /** Insert booking */
    public static function create_booking( $data ) {
        global $wpdb;

        $start  = sanitize_text_field( $data['start'] ?? '' );
        $end    = sanitize_text_field( $data['end'] ?? '' );
        $sector = sanitize_text_field( $data['sector'] ?? '' );

        // Validate dates
        if ( empty( $start ) || empty( $end ) ) {
            return false;
        }

        // Check availability again (safety net)
        $conflicts = self::find_unavailable_sectors( $start, $end );
        if ( in_array( $sector, $conflicts, true ) ) {
            return false; // Sector already taken
        }

        $inserted = $wpdb->insert(
            "{$wpdb->prefix}bush_bookings",
            [
                'start'          => $start,
                'end'            => $end,
                'sector'         => $sector,
                'anglers'        => intval( $data['anglers'] ?? 1 ),
                'client_first'   => sanitize_text_field( $data['client']['firstName'] ?? '' ),
                'client_last'    => sanitize_text_field( $data['client']['lastName'] ?? '' ),
                'client_email'   => sanitize_email( $data['client']['email'] ?? '' ),
                'client_phone'   => sanitize_text_field( $data['client']['phone'] ?? '' ),
                'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
                'status'         => 'pending',
                'pay_method'     => sanitize_text_field( $data['pay_method'] ?? '' ),
                'pay_instructions' => sanitize_textarea_field( $data['pay_instructions'] ?? '' ),
            ],
            [ '%s','%s','%s','%d','%s','%s','%s','%s','%s','%s','%s' ]
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /** Update booking status */
    public static function update_booking_status( $id, $status ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}bush_bookings",
            [ 'status' => sanitize_text_field( $status ) ],
            [ 'id' => intval( $id ) ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /** Delete booking */
    public static function delete_booking( $id ) {
        global $wpdb;
        $id = intval( $id );
        $wpdb->delete( "{$wpdb->prefix}bush_payments", [ 'booking_id' => $id ], [ '%d' ] );
        return $wpdb->delete( "{$wpdb->prefix}bush_bookings", [ 'id' => $id ], [ '%d' ] );
    }

    /** Add payment */
    public static function add_payment( $booking_id, $amount, $method, $reference = '' ) {
        global $wpdb;
        $inserted = $wpdb->insert(
            "{$wpdb->prefix}bush_payments",
            [
                'booking_id' => intval( $booking_id ),
                'amount'     => floatval( $amount ),
                'method'     => sanitize_text_field( $method ),
                'reference'  => sanitize_text_field( $reference ),
            ],
            [ '%d','%f','%s','%s' ]
        );

        if ( $inserted ) {
            // Автоматично одобрение при плащане
            self::update_booking_status( $booking_id, 'paid' );
            return $wpdb->insert_id;
        }
        return false;
    }

    /** Find unavailable sectors */
    public static function find_unavailable_sectors( $start, $end ) {
        global $wpdb;
        $sql = $wpdb->prepare(
            "SELECT DISTINCT sector
             FROM {$wpdb->prefix}bush_bookings
             WHERE status IN ('pending','approved','paid')
             AND start < %s
             AND end > %s",
            $end, $start
        );
        return $wpdb->get_col( $sql );
    }

    // --- Останалите методи (get_prices, list_bookings, list_blackouts и т.н.)
    // може да останат почти същите, но добави sanitize и проверки както горните.
}
