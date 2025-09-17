<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Bushlyak_Booking_DB' ) ) {

    class Bushlyak_Booking_DB {

        /** Създаване на таблици */
        public static function create_tables() {
            global $wpdb;
            $charset_collate = $wpdb->get_charset_collate();

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $tables = [];

            // Резервации
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

            // Цени
            $tables[] = "CREATE TABLE {$wpdb->prefix}bush_prices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                valid_from DATE NOT NULL,
                base DECIMAL(10,2) NOT NULL,
                second DECIMAL(10,2) NOT NULL,
                second_with_card DECIMAL(10,2) NOT NULL,
                PRIMARY KEY (id)
            ) $charset_collate;";

            // Плащания
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

            // Методи за плащане
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

        /** CRUD: резервации */
        public static function create_booking( $data ) {
            global $wpdb;
            return $wpdb->insert(
                "{$wpdb->prefix}bush_bookings",
                [
                    'start'        => sanitize_text_field( $data['start'] ),
                    'end'          => sanitize_text_field( $data['end'] ),
                    'sector'       => sanitize_text_field( $data['sector'] ),
                    'anglers'      => intval( $data['anglers'] ),
                    'client_first' => sanitize_text_field( $data['client']['firstName'] ),
                    'client_last'  => sanitize_text_field( $data['client']['lastName'] ),
                    'client_email' => sanitize_email( $data['client']['email'] ),
                    'client_phone' => sanitize_text_field( $data['client']['phone'] ),
                    'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
                    'status'       => 'pending',
                    'pay_method'   => sanitize_text_field( $data['pay_method'] ?? '' ),
                ],
                [ '%s','%s','%s','%d','%s','%s','%s','%s','%s','%s' ]
            ) ? $wpdb->insert_id : false;
        }

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

        public static function delete_booking( $id ) {
            global $wpdb;
            $id = intval( $id );
            $wpdb->delete( "{$wpdb->prefix}bush_payments", [ 'booking_id' => $id ], [ '%d' ] );
            return $wpdb->delete( "{$wpdb->prefix}bush_bookings", [ 'id' => $id ], [ '%d' ] );
        }

        public static function list_bookings( $limit = 50 ) {
            global $wpdb;
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bush_bookings ORDER BY created_at DESC LIMIT %d",
                    $limit
                )
            );
        }

        /** Цени */
        public static function get_prices() {
            global $wpdb;
            return $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}bush_prices ORDER BY valid_from DESC LIMIT 1" );
        }

        /** Blackouts */
        public static function list_blackouts() {
            global $wpdb;
            return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bush_blackouts ORDER BY start DESC" );
        }

        /** Методи за плащане */
        public static function list_paymethods() {
            global $wpdb;
            return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bush_paymethods ORDER BY id ASC" );
        }

        /** ✅ Нови методи */

        // Взимане на резервация по ID
        public static function get_booking( $id ) {
            global $wpdb;
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id = %d",
                    intval($id)
                )
            );
        }

        // Проверка за конфликт със съществуваща одобрена резервация
        public static function has_conflict( $start, $end, $sector, $exclude_id = 0 ) {
            global $wpdb;
            $sql = "SELECT 1
                      FROM {$wpdb->prefix}bush_bookings
                     WHERE status = 'approved'
                       AND sector = %s
                       AND start < %s
                       AND end   > %s";
            $params = [ $sector, $end, $start ];
            if ( $exclude_id ) {
                $sql .= " AND id <> %d";
                $params[] = intval($exclude_id);
            }
            return (bool) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
        }

        // Отхвърля всички pending резервации, които се припокриват с одобрена
        public static function reject_pending_overlaps( $start, $end, $sector, $approved_id ) {
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}bush_bookings
                        SET status = 'rejected'
                      WHERE status = 'pending'
                        AND sector = %s
                        AND start < %s
                        AND end   > %s
                        AND id <> %d",
                    $sector, $end, $start, intval($approved_id)
                )
            );
        }
    }
}
