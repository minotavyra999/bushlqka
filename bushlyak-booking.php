<?php
/**
 * Plugin Name: Bushlyak Booking
 * Description: Система за резервации на Bushlyak (шорткод: [bushlyaka_booking]).
 * Version: 1.0.9
 * Author: Ivaylo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-bushlyak-booking-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-bushlyak-booking-rest.php';

/**
 * Главен плъгин клас
 */
class Bushlyak_Booking_Plugin {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_menu',        [__CLASS__, 'admin_menu']);
        add_action('rest_api_init',     [__CLASS__, 'register_rest']); // в твоя код REST е под bush/v1

        self::register_shortcodes();

        // Админ действия (одобряване/отхвърляне/изтриване)
        add_action('admin_post_bush_approve', [__CLASS__, 'handle_approve_booking']);
        add_action('admin_post_bush_reject',  [__CLASS__, 'handle_reject_booking']);
        add_action('admin_post_bush_delete',  [__CLASS__, 'handle_delete_booking']);
    }

    /**
     * CSS/JS – включваме flatpickr CSS (липсващият стил често е причината „календарът да не излиза“)
     */
    public static function enqueue_assets() {
        // Flatpickr styles + script
        wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', true);

        // Нашите стилове/скриптове
        wp_enqueue_style( 'bushlyaka-styles', plugin_dir_url(__FILE__) . 'assets/css/styles.css', [], '1.0.9' );
        wp_enqueue_script('bushlyaka-app',    plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery','flatpickr'], '1.0.9', true );

        // Локализация за JS – ВАЖНО: тук е bush/v1 (както е в твоите файлове)
        wp_localize_script('bushlyaka-app', 'bushlyaka', [
            'restUrl'     => esc_url_raw( rest_url( 'bush/v1/' ) ),
            'nonce'       => wp_create_nonce('wp_rest'),
            'messages'    => [
                'loading' => __( 'Моля, изчакайте...', 'bushlyaka' ),
                'success' => __( 'Резервацията е създадена успешно!', 'bushlyaka' ),
                'error'   => __( 'Възникна грешка. Опитайте отново.', 'bushlyaka' ),
            ],
            'redirectUrl' => home_url('/booking-summary'),
        ]);
    }

    /** РЕГИСТРАЦИЯ НА ШОРТКОДИ */
    public static function register_shortcodes() {
        add_shortcode( 'bushlyaka_booking',          [ __CLASS__, 'render_booking_form' ] );
        add_shortcode( 'bushlyaka_booking_summary',  [ __CLASS__, 'render_booking_summary' ] );
    }

    /**
     * Форма за резервации – ID-тата съвпадат с твоето app.js:
     * #daterange, #sector, #anglers, #secondHasCard, #payMethod, #notes, #firstName, #lastName, #email, #phone, #price
     */
    public static function render_booking_form() {
        // Методи за плащане от базата (ако таблицата/функцията липсва – падаме към празен масив)
        $methods = [];
        if ( class_exists('Bushlyak_Booking_DB') && method_exists('Bushlyak_Booking_DB','list_paymethods') ) {
            $methods = (array) Bushlyak_Booking_DB::list_paymethods();
        }

        // Секторите – 1..19 (както искаше)
        $sectors = range(1,19);

        ob_start(); ?>
        <div class="bushlyaka-booking-form">
            <form id="bushlyakaBookingForm">

                <!-- Период -->
                <div class="form-group">
                    <label for="daterange"><?php _e('Изберете период', 'bushlyaka'); ?></label>
                    <input type="text" id="daterange" name="daterange" required>
                </div>

                <!-- Сектор -->
                <div class="form-group">
                    <label for="sector"><?php _e('Сектор', 'bushlyaka'); ?></label>
                    <select id="sector" name="sector" required>
                        <option value=""><?php _e('-- Изберете сектор --', 'bushlyaka'); ?></option>
                        <?php foreach ($sectors as $n): ?>
                            <option value="<?php echo esc_attr($n); ?>">Сектор <?php echo esc_html($n); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Рибари -->
                <div class="form-group">
                    <label for="anglers"><?php _e('Брой рибари', 'bushlyaka'); ?></label>
                    <select id="anglers" name="anglers" required>
                        <option value="1">1 рибар</option>
                        <option value="2">2 рибари</option>
                    </select>
                </div>

                <!-- Втори с карта -->
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" id="secondHasCard" name="secondHasCard">
                        <?php _e('Втори рибар с карта', 'bushlyaka'); ?>
                    </label>
                </div>

                <!-- Цена (динамична) -->
                <div class="form-group price-display">
                    <strong><?php _e('Цена:', 'bushlyaka'); ?> <span id="price">—</span></strong>
                </div>

                <!-- Клиентски данни -->
                <div class="form-group">
                    <label for="firstName"><?php _e('Име', 'bushlyaka'); ?></label>
                    <input type="text" id="firstName" name="firstName" required>
                </div>

                <div class="form-group">
                    <label for="lastName"><?php _e('Фамилия', 'bushlyaka'); ?></label>
                    <input type="text" id="lastName" name="lastName" required>
                </div>

                <div class="form-group">
                    <label for="email"><?php _e('Имейл', 'bushlyaka'); ?></label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone"><?php _e('Телефон', 'bushlyaka'); ?></label>
                    <input type="text" id="phone" name="phone" required>
                </div>

                <!-- Бележки -->
                <div class="form-group">
                    <label for="notes"><?php _e('Бележки', 'bushlyaka'); ?></label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>

                <!-- Метод на плащане -->
                <div class="form-group">
                    <label for="payMethod"><?php _e('Метод на плащане', 'bushlyaka'); ?></label>
                    <select id="payMethod" name="payMethod" required>
                        <option value=""><?php _e('-- Изберете метод --', 'bushlyaka'); ?></option>
                        <?php if (!empty($methods)) : foreach ($methods as $m) : ?>
                            <option value="<?php echo esc_attr($m->id); ?>">
                                <?php echo esc_html($m->name); ?> — <?php echo esc_html($m->instructions); ?>
                            </option>
                        <?php endforeach; else: ?>
                            <option value="0"><?php _e('На място', 'bushlyaka'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Бутон -->
                <div class="form-group">
                    <button type="submit" class="button button-primary"><?php _e('Резервирай', 'bushlyaka'); ?></button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Резюме (страница /booking-summary със шорткод [bushlyaka_booking_summary])
     * Файлът summary.php при теб показва детайлите и цена (използва calculate_price).
     */
    public static function render_booking_summary() {
        ob_start();
        include plugin_dir_path(__FILE__) . 'summary.php';
        return ob_get_clean();
    }

    /** Админ менюта */
    public static function admin_menu() {
        add_menu_page(
            'Bushlyak Booking',
            'Bushlyak Booking',
            'edit_pages',
            'bushlyak-booking',
            function() {
                echo '<div class="wrap"><h1>Bushlyak Booking</h1><p>Тук ще бъдат настройките и резервациите.</p></div>';
            },
            'dashicons-calendar-alt'
        );

        add_submenu_page(
            'bushlyak-booking',
            'Резервации',
            'Резервации',
            'edit_pages',
            'bushlyak-bookings',
            function() { include plugin_dir_path(__FILE__) . 'admin/admin-bookings.php'; }
        );

        add_submenu_page(
            'bushlyak-booking',
            'Цени',
            'Цени',
            'edit_pages',
            'bushlyak-prices',
            function() { include plugin_dir_path(__FILE__) . 'admin/admin-prices.php'; }
        );

        add_submenu_page(
            'bushlyak-booking',
            'Методи на плащане',
            'Методи на плащане',
            'edit_pages',
            'bushlyak-payments',
            function() { include plugin_dir_path(__FILE__) . 'admin/admin-payments.php'; }
        );

        add_submenu_page(
            'bushlyak-booking',
            'Неактивни периоди',
            'Неактивни периоди',
            'edit_pages',
            'bushlyak-blackouts',
            function() { include plugin_dir_path(__FILE__) . 'admin/admin-blackouts.php'; }
        );

        add_submenu_page(
            'bushlyak-booking',
            'Настройки',
            'Настройки',
            'manage_options',
            'bushlyak-settings',
            function() { include plugin_dir_path(__FILE__) . 'admin/admin-settings.php'; }
        );
    }

    /** Регистрация на REST маршрути – при теб са под 'bush/v1' и класът използва __CLASS__ в callback-и */
    public static function register_rest() {
        if ( class_exists('Bushlyak_Booking_REST') && method_exists('Bushlyak_Booking_REST','register_routes') ) {
            Bushlyak_Booking_REST::register_routes();
        }
    }

    /** Имейл – извиква се от REST класа след успешен insert */
    public static function send_booking_email( $booking_id ) {
        $email_file = plugin_dir_path(__FILE__) . 'email.php';
        if ( file_exists($email_file) ) {
            include_once $email_file;
            if ( function_exists('bushlyaka_send_booking_email') ) {
                bushlyaka_send_booking_email( $booking_id );
            }
        }
    }

    /** Админ: одобряване/отхвърляне/изтриване */
    public static function handle_approve_booking() {
        if ( ! current_user_can('manage_options') ) wp_die('Not allowed');
        check_admin_referer('bush_booking_action');
        $id = intval($_GET['id']);
        if ($id && class_exists('Bushlyak_Booking_DB')) Bushlyak_Booking_DB::update_booking_status($id, 'approved');
        wp_redirect( admin_url('admin.php?page=bushlyak-bookings') ); exit;
    }

    public static function handle_reject_booking() {
        if ( ! current_user_can('manage_options') ) wp_die('Not allowed');
        check_admin_referer('bush_booking_action');
        $id = intval($_GET['id']);
        if ($id && class_exists('Bushlyak_Booking_DB')) Bushlyak_Booking_DB::update_booking_status($id, 'rejected');
        wp_redirect( admin_url('admin.php?page=bushlyak-bookings') ); exit;
    }

    public static function handle_delete_booking() {
        if ( ! current_user_can('manage_options') ) wp_die('Not allowed');
        check_admin_referer('bush_booking_action');
        $id = intval($_GET['id']);
        if ($id && class_exists('Bushlyak_Booking_DB')) Bushlyak_Booking_DB::delete_booking($id);
        wp_redirect( admin_url('admin.php?page=bushlyak-bookings') ); exit;
    }
}

Bushlyak_Booking_Plugin::init();
