<?php
/**
 * Plugin Name: Bushlyak Booking
 * Description: Система за резервации на Bushlyak (шорткод: [bushlyaka_booking]).
 * Version: 1.1.0
 * Author: Ivaylo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-bushlyak-booking-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-bushlyak-booking-rest.php';

class Bushlyak_Booking_Plugin {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_menu',        [__CLASS__, 'admin_menu']);
        add_action('rest_api_init',     [__CLASS__, 'register_rest']);

        self::register_shortcodes();
    }

    public static function enqueue_assets() {
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', true);

        wp_enqueue_style('bushlyaka-styles', plugin_dir_url(__FILE__) . 'assets/css/styles.css', [], '1.1.0');
        wp_enqueue_script('bushlyaka-app', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery','flatpickr'], '1.1.0', true);

        wp_localize_script('bushlyaka-app', 'bushlyaka', [
            'restUrl'     => esc_url_raw( rest_url( 'bush/v1/' ) ),
            'nonce'       => wp_create_nonce('wp_rest'),
            'redirectUrl' => home_url('/booking-summary'),
        ]);
    }

    public static function register_shortcodes() {
        add_shortcode('bushlyaka_booking', [ __CLASS__, 'render_booking_form' ]);
        add_shortcode('bushlyaka_booking_summary', [ __CLASS__, 'render_booking_summary' ]);
    }

    public static function render_booking_form() {
        ob_start(); ?>
        <div class="bushlyaka-booking-form">
            <form id="bushlyakaBookingForm">
                <div class="form-group">
                    <label for="daterange">Изберете период</label>
                    <input type="text" id="daterange" name="daterange" required>
                </div>

                <div class="form-group">
                    <label for="sector">Сектор</label>
                    <select id="sector" name="sector" required>
                        <option value="">-- Изберете сектор --</option>
                        <?php for ($i=1; $i<=19; $i++): ?>
                            <option value="<?php echo $i; ?>">Сектор <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="anglers">Брой рибари</label>
                    <select id="anglers" name="anglers" required>
                        <option value="1">1 рибар</option>
                        <option value="2">2 рибари</option>
                    </select>
                </div>

                <div class="form-group checkbox">
                    <label><input type="checkbox" id="secondHasCard" name="secondHasCard"> Втори рибар с карта</label>
                </div>

                <div class="form-group price-display">
                    <strong>Цена: <span id="price">—</span></strong>
                </div>

                <div class="form-group">
                    <label for="firstName">Име</label>
                    <input type="text" id="firstName" name="firstName" required>
                </div>

                <div class="form-group">
                    <label for="lastName">Фамилия</label>
                    <input type="text" id="lastName" name="lastName" required>
                </div>

                <div class="form-group">
                    <label for="email">Имейл</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="phone">Телефон</label>
                    <input type="text" id="phone" name="phone" required>
                </div>

                <div class="form-group">
                    <label for="notes">Бележки</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>

                <div class="form-group">
                    <label for="payMethod">Метод на плащане</label>
                    <select id="payMethod" name="payMethod" required>
                        <option value="0">На място</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="button button-primary">Резервирай</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function render_booking_summary() {
        ob_start();
        include plugin_dir_path(__FILE__) . 'summary.php';
        return ob_get_clean();
    }

    public static function admin_menu() {
        add_menu_page('Bushlyak Booking','Bushlyak Booking','edit_pages','bushlyak-booking',
            function(){ echo '<div class="wrap"><h1>Bushlyak Booking</h1></div>'; },'dashicons-calendar-alt');
        add_submenu_page('bushlyak-booking','Резервации','Резервации','edit_pages','bushlyak-bookings',
            function(){ include plugin_dir_path(__FILE__).'admin/admin-bookings.php'; });
        add_submenu_page('bushlyak-booking','Цени','Цени','edit_pages','bushlyak-prices',
            function(){ include plugin_dir_path(__FILE__).'admin/admin-prices.php'; });
        add_submenu_page('bushlyak-booking','Методи на плащане','Методи на плащане','edit_pages','bushlyak-payments',
            function(){ include plugin_dir_path(__FILE__).'admin/admin-payments.php'; });
        add_submenu_page('bushlyak-booking','Неактивни периоди','Неактивни периоди','edit_pages','bushlyak-blackouts',
            function(){ include plugin_dir_path(__FILE__).'admin/admin-blackouts.php'; });
        add_submenu_page('bushlyak-booking','Настройки','Настройки','manage_options','bushlyak-settings',
            function(){ include plugin_dir_path(__FILE__).'admin/admin-settings.php'; });
    }

    public static function register_rest() {
        Bushlyak_Booking_REST::register_routes();
    }
}

Bushlyak_Booking_Plugin::init();
