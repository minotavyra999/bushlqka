<?php
/**
 * Plugin Name: Bushlyaka Booking
 * Description: Система за резервации със сектори, blackout периоди и плащания.
 * Version: 1.1.0
 * Author: Bushlyaka Dev Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bushlyak_Booking_Plugin {

    public static function init() {
        register_activation_hook( __FILE__, [ __CLASS__, 'on_activate' ] );
        register_deactivation_hook( __FILE__, [ __CLASS__, 'on_deactivate' ] );

        add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_menu' ] );

        require_once __DIR__ . '/includes/class-bushlyak-booking-db.php';
        require_once __DIR__ . '/includes/class-bushlyak-booking-rest.php';
        Bushlyak_Booking_REST::init();
    }

    public static function on_activate() {
        require_once __DIR__ . '/includes/class-bushlyak-booking-db.php';
        Bushlyak_Booking_DB::create_tables();
        add_option( 'bush_notify_emails', get_option( 'admin_email' ) );
        add_option( 'bush_redirect_url', site_url( '/thanks' ) );
    }

    public static function on_deactivate() {
        // clean up if needed
    }

    /** Assets */
    public static function enqueue_assets() {
        $ver = '1.1.0';
        $css_file = plugin_dir_url( __FILE__ ) . 'assets/css/styles.css';
        $js_file  = plugin_dir_url( __FILE__ ) . 'assets/js/app.js';

        if ( file_exists( plugin_dir_path( __FILE__ ) . 'assets/css/styles.min.css' ) ) {
            $css_file = plugin_dir_url( __FILE__ ) . 'assets/css/styles.min.css';
        }
        if ( file_exists( plugin_dir_path( __FILE__ ) . 'assets/js/app.min.js' ) ) {
            $js_file = plugin_dir_url( __FILE__ ) . 'assets/js/app.min.js';
        }

        wp_enqueue_style( 'bushlyak-booking', $css_file, [], $ver );
        wp_enqueue_script( 'bushlyak-booking', $js_file, [ 'jquery' ], $ver, true );

        wp_localize_script( 'bushlyak-booking', 'bushlyaka', [
            'restUrl'     => esc_url_raw( rest_url( 'bush/v1/' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'redirectUrl' => get_option( 'bush_redirect_url', site_url( '/thanks' ) ),
            'messages'    => [
                'loading'   => __( 'Изпращане...', 'bushlyaka' ),
                'success'   => __( 'Резервацията е изпратена успешно!', 'bushlyaka' ),
                'error'     => __( 'Възникна грешка. Опитайте отново.', 'bushlyaka' ),
            ],
        ] );
    }

    /** Shortcodes */
    public static function register_shortcodes() {
        add_shortcode( 'bushlyaka_booking', [ __CLASS__, 'render_booking_form' ] );
    }

    /** Booking form */
    public static function render_booking_form() {
        ob_start(); ?>
        <div class="bushlyaka-booking-form">
            <h2><?php _e( 'Резервация', 'bushlyaka' ); ?></h2>
            <div class="bush-error-global" style="display:none;color:red;"></div>
            
            <form>
                <label><?php _e( 'Изберете дати:', 'bushlyaka' ); ?></label>
                <input type="text" class="bush-date-range" readonly />

                <div class="bush-sectors">
                    <?php foreach ( range(1, 6) as $sector ) : ?>
                        <div class="bush-sector" data-sector="<?php echo esc_attr( $sector ); ?>">
                            <?php echo sprintf( __( 'Сектор %d', 'bushlyaka' ), $sector ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <label><?php _e( 'Брой рибари:', 'bushlyaka' ); ?></label>
                <select name="anglers">
                    <option value="1">1</option>
                    <option value="2">2</option>
                </select>
                <label>
                    <input type="checkbox" name="secondHasCard" />
                    <?php _e( 'Втори рибар с карта', 'bushlyaka' ); ?>
                </label>

                <p><?php _e( 'Ориентировъчна цена:', 'bushlyaka' ); ?> <span class="bush-price-estimate">0 лв.</span></p>

                <label><?php _e( 'Име:', 'bushlyaka' ); ?></label>
                <input type="text" name="firstName" required />
                <label><?php _e( 'Фамилия:', 'bushlyaka' ); ?></label>
                <input type="text" name="lastName" required />
                <label><?php _e( 'Имейл:', 'bushlyaka' ); ?></label>
                <input type="email" name="email" required />
                <label><?php _e( 'Телефон:', 'bushlyaka' ); ?></label>
                <input type="text" name="phone" required />

                <label><?php _e( 'Бележки:', 'bushlyaka' ); ?></label>
                <textarea name="notes"></textarea>

                <label><?php _e( 'Метод на плащане:', 'bushlyaka' ); ?></label>
                <select name="payMethod"></select>

                <button type="submit"><?php _e( 'Изпрати резервация', 'bushlyaka' ); ?></button>
            </form>
        </div>
        <?php return ob_get_clean();
    }

    /** Admin menu */
    public static function register_admin_menu() {
        add_menu_page(
            __( 'Bushlyaka Booking', 'bushlyaka' ),
            __( 'Bushlyaka Booking', 'bushlyaka' ),
            'manage_options',
            'bushlyaka-booking',
            [ __CLASS__, 'render_bookings_page' ],
            'dashicons-calendar-alt'
        );

        add_submenu_page(
            'bushlyaka-booking',
            __( 'Резервации', 'bushlyaka' ),
            __( 'Резервации', 'bushlyaka' ),
            'manage_options',
            'bushlyaka-booking',
            [ __CLASS__, 'render_bookings_page' ]
        );

        add_submenu_page(
            'bushlyaka-booking',
            __( 'Цени', 'bushlyaka' ),
            __( 'Цени', 'bushlyaka' ),
            'manage_options',
            'bushlyaka-booking-prices',
            [ __CLASS__, 'render_prices_page' ]
        );

        add_submenu_page(
            'bushlyaka-booking',
            __( 'Blackout периоди', 'bushlyaka' ),
            __( 'Blackout периоди', 'bushlyaka' ),
            'manage_options',
            'bushlyaka-booking-blackouts',
            [ __CLASS__, 'render_blackouts_page' ]
        );

        add_submenu_page(
            'bushlyaka-booking',
            __( 'Методи за плащане', 'bushlyaka' ),
            __( 'Методи за плащане', 'bushlyaka' ),
            'manage_options',
            'bushlyaka-booking-paymethods',
            [ __CLASS__, 'render_paymethods_page' ]
        );

        add_submenu_page(
            'bushlyaka-booking',
            __( 'Настройки', 'bushlyaka' ),
            __( 'Настройки', 'bushlyaka' ),
            'manage_options',
            'bushlyaka-booking-settings',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    /** CRUD: Резервации */
    public static function render_bookings_page() {
        include __DIR__ . '/admin/admin-bookings.php';
    }

    /** CRUD: Цени */
    public static function render_prices_page() {
        include __DIR__ . '/admin/admin-prices.php';
    }

    /** CRUD: Blackouts */
    public static function render_blackouts_page() {
        include __DIR__ . '/admin/admin-blackouts.php';
    }

    /** CRUD: Методи за плащане */
    public static function render_paymethods_page() {
        include __DIR__ . '/admin/admin-paymethods.php';
    }

    /** Настройки */
    public static function render_settings_page() {
        include __DIR__ . '/admin/admin-settings.php';
    }
}

Bushlyak_Booking_Plugin::init();
