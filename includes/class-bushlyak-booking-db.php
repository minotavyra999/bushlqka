
<?php
if (!defined('ABSPATH')) exit;

class Bushlyak_Booking_DB {
    public static function table($name){ global $wpdb; return $wpdb->prefix . 'bush_' . $name; }
    public static function table_exists($table){ global $wpdb; return ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table); }
    public static function col_exists($table, $col){ global $wpdb; $row = $wpdb->get_row("SHOW COLUMNS FROM `$table` LIKE '$col'"); return ! is_null($row); }

    public static function create_tables(){
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $bookings   = self::table('bookings');
        $prices     = self::table('prices');
        $payments   = self::table('payments');
        $blackouts  = self::table('blackouts');
        $paymethods = self::table('paymethods');

        $sql1 = "CREATE TABLE IF NOT EXISTS $bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            start DATETIME NOT NULL,
            end DATETIME NOT NULL,
            sector TINYINT UNSIGNED NOT NULL,
            anglers TINYINT UNSIGNED NOT NULL,
            second_has_card TINYINT(1) NOT NULL DEFAULT 0,
            client_first VARCHAR(100) NOT NULL,
            client_last VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            notes TEXT NULL,
            price_estimate DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            pay_method VARCHAR(100) NULL,
            pay_instructions TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_period (start, end),
            INDEX idx_sector (sector)
        ) $charset;";

        $sql2 = "CREATE TABLE IF NOT EXISTS $prices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            base_per_24h DECIMAL(10,2) NOT NULL DEFAULT 40,
            second_angler_price DECIMAL(10,2) NOT NULL DEFAULT 80,
            second_angler_with_card_price DECIMAL(10,2) NOT NULL DEFAULT 40,
            valid_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        $sql3 = "CREATE TABLE IF NOT EXISTS $payments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method VARCHAR(20) NOT NULL,
            reference VARCHAR(190) NULL,
            received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_booking (booking_id)
        ) $charset;";

        $sql4 = "CREATE TABLE IF NOT EXISTS $blackouts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            reason VARCHAR(190) NULL,
            PRIMARY KEY (id),
            INDEX idx_range (start_date, end_date)
        ) $charset;";

        $sql5 = "CREATE TABLE IF NOT EXISTS $paymethods (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            instructions TEXT NOT NULL,
            PRIMARY KEY (id)
        ) $charset;";

        dbDelta($sql1); dbDelta($sql2); dbDelta($sql3); dbDelta($sql4); dbDelta($sql5);
    }

    public static function migrate_add_columns(){
        global $wpdb; $t = self::table('bookings');
        if (! self::table_exists($t)) { self::create_tables(); }
        if (! self::col_exists($t, 'pay_method')) { $wpdb->query("ALTER TABLE `$t` ADD `pay_method` VARCHAR(100) NULL AFTER `status`"); }
        if (! self::col_exists($t, 'pay_instructions')) { $wpdb->query("ALTER TABLE `$t` ADD `pay_instructions` TEXT NULL AFTER `pay_method`"); }
    }

    public static function seed_default_prices(){
        global $wpdb; $t = self::table('prices');
        $row = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
        if ($row === 0){
            $wpdb->insert($t, [
                'base_per_24h' => 40,
                'second_angler_price' => 80,
                'second_angler_with_card_price' => 40,
            ]);
        }
    }

    public static function get_prices(){
        global $wpdb; $t = self::table('prices');
        $row = $wpdb->get_row("SELECT * FROM $t ORDER BY id DESC LIMIT 1", ARRAY_A);
        return $row ?: ['base_per_24h'=>40,'second_angler_price'=>80,'second_angler_with_card_price'=>40];
    }

    public static function update_prices($base,$second,$second_card){
        global $wpdb; $t = self::table('prices');
        $wpdb->insert($t, [
            'base_per_24h' => $base,
            'second_angler_price' => $second,
            'second_angler_with_card_price' => $second_card,
        ]);
    }

    public static function list_bookings($limit=100){
        global $wpdb; $t = self::table('bookings');
        $limit = intval($limit);
        return $wpdb->get_results("SELECT * FROM $t ORDER BY created_at DESC, id DESC LIMIT $limit");
    }

    public static function get_booking($id){
        global $wpdb; $t = self::table('bookings');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id));
    }

    public static function create_booking($data){
        global $wpdb; $t = self::table('bookings');
        if (! self::table_exists($t)) { self::create_tables(); }
        self::migrate_add_columns();
        $formats = ['%s','%s','%d','%d','%d','%s','%s','%s','%s','%s','%f','%s','%s','%s'];
        $ok = $wpdb->insert($t, $data, $formats);
        if ($ok === false){
            if (defined('WP_DEBUG') && WP_DEBUG){ error_log('[Bushlyak Booking] Insert failed: ' . $wpdb->last_error); }
            return false;
        }
        return (int)$wpdb->insert_id;
    }

    public static function update_booking_status($id, $status){
        global $wpdb; $t = self::table('bookings');
        $wpdb->update($t, ['status'=>$status], ['id'=>$id]);
    }

    public static function delete_booking($id){
        global $wpdb; $t = self::table('bookings'); $tp = self::table('payments');
        $wpdb->delete($tp, ['booking_id'=>$id]);
        $wpdb->delete($t, ['id'=>$id]);
    }

    public static function add_payment($p){
        global $wpdb; $t = self::table('payments');
        $wpdb->insert($t, [
            'booking_id' => $p['booking_id'],
            'amount'     => $p['amount'],
            'method'     => $p['method'],
            'reference'  => $p['reference'],
        ]);
    }

    public static function list_payments($booking_id){
        global $wpdb; $t = self::table('payments');
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE booking_id=%d ORDER BY id DESC", $booking_id));
    }

    public static function find_unavailable_sectors($start, $end){
        global $wpdb; $t = self::table('bookings');
        $sql = $wpdb->prepare("SELECT DISTINCT sector FROM $t WHERE start < %s AND end > %s AND status IN ('pending','approved')", $end, $start);
        $rows = $wpdb->get_col($sql);
        return array_map('intval', $rows);
    }

    public static function list_blackout_ranges(){
        global $wpdb; $t = self::table('blackouts');
        return $wpdb->get_results("SELECT start_date, end_date FROM $t");
    }

    public static function add_blackout($start, $end, $reason=''){
        global $wpdb; $t = self::table('blackouts');
        $wpdb->insert($t, [ 'start_date'=>$start, 'end_date'=>$end, 'reason'=>$reason ]);
    }
    public static function delete_blackout($id){
        global $wpdb; $t = self::table('blackouts');
        $wpdb->delete($t, ['id'=>$id]);
    }
    public static function list_blackouts(){
        global $wpdb; $t = self::table('blackouts');
        return $wpdb->get_results("SELECT * FROM $t ORDER BY start_date DESC");
    }

    public static function add_paymethod($name, $instructions){
        global $wpdb; $t = self::table('paymethods');
        $wpdb->insert($t, [ 'name'=>$name, 'instructions'=>$instructions ]);
    }
    public static function delete_paymethod($id){
        global $wpdb; $t = self::table('paymethods');
        $wpdb->delete($t, ['id'=>$id]);
    }
    public static function list_paymethods(){
        global $wpdb; $t = self::table('paymethods');
        return $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC");
    }

    public static function seed_examples(){
        // примерни blackout дати и метод на плащане, ако са празни
        global $wpdb;
        $tb = self::table('blackouts');
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $tb") === 0){
            $wpdb->insert($tb, [ 'start_date'=>'2025-12-24', 'end_date'=>'2025-12-25', 'reason'=>'Коледа (пример)' ]);
            $wpdb->insert($tb, [ 'start_date'=>'2026-01-01', 'end_date'=>'2026-01-01', 'reason'=>'Нова година (пример)' ]);
        }
        $tm = self::table('paymethods');
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $tm") === 0){
            $wpdb->insert($tm, [ 'name'=>'Банков превод', 'instructions'=>'Изпратете сумата по сметка IBAN: BG00XXXX1234, Титуляр: Яз. Бушляк' ]);
        }
        if (! get_option('bush_notify_emails')) { update_option('bush_notify_emails', get_option('admin_email')); }
    }
}
