<?php
/**
 * Plugin Name: Bushlyak Booking
 * Description: Система за резервации на сектори (риболов).
 * Version: 1.0.0
 * Author: minotavyra
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Bushlyak_Booking_Plugin' ) ) {

    class Bushlyak_Booking_Plugin {

        public static function init() {
            // Includes
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-bushlyak-booking-db.php';
            require_once plugin_dir_path( __FILE__ ) . 'includes/class-bushlyak-booking-rest.php';

            // Hooks
            register_activation_hook( __FILE__, [ __CLASS__, 'activate' ] );
            add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );
            add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
            add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );

            // REST
            Bushlyak_Booking_REST::init();
        }

        /** Активация – създава таблиците */
        public static function activate() {
            Bushlyak_Booking_DB::create_tables();
        }

        /** Admin меню */
        public static function admin_menu() {
            add_menu_page(
                __( 'Bushlyak Booking', 'bushlyaka' ),
                __( 'Bushlyak Booking', 'bushlyaka' ),
                'manage_options',
                'bushlyak-booking',
                [ __CLASS__, 'render_admin_dashboard' ],
                'dashicons-calendar-alt'
            );

            add_submenu_page(
                'bushlyak-booking',
                __( 'Резервации', 'bushlyaka' ),
                __( 'Резервации', 'bushlyaka' ),
                'manage_options',
                'bushlyak-bookings',
                [ __CLASS__, 'render_admin_bookings' ]
            );

            add_submenu_page(
                'bushlyak-booking',
                __( 'Цени', 'bushlyaka' ),
                __( 'Цени', 'bushlyaka' ),
                'manage_options',
                'bushlyak-prices',
                [ __CLASS__, 'render_admin_prices' ]
            );

            add_submenu_page(
                'bushlyak-booking',
                __( 'Методи за плащане', 'bushlyaka' ),
                __( 'Методи за плащане', 'bushlyaka' ),
                'manage_options',
                'bushlyak-payments',
                [ __CLASS__, 'render_admin_payments' ]
            );

            add_submenu_page(
                'bushlyak-booking',
                __( 'Blackout периоди', 'bushlyaka' ),
                __( 'Blackout периоди', 'bushlyaka' ),
                'manage_options',
                'bushlyak-blackouts',
                [ __CLASS__, 'render_admin_blackouts' ]
            );
        }

        /** Enqueue CSS/JS */
        public static function enqueue_assets() {
            $url = plugin_dir_url( __FILE__ );

            // Flatpickr (календар)
            wp_enqueue_style( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13' );
            wp_enqueue_script( 'flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js', [], '4.6.13', true );

            // Основни стилове и скриптове
            wp_enqueue_style( 'bushlyak-booking', $url . 'assets/css/styles.css', [], '1.0' );
            wp_enqueue_script( 'bushlyak-booking', $url . 'assets/js/app.js', [ 'jquery' ], '1.0', true );

            wp_localize_script( 'bushlyak-booking', 'bushlyaka', [
                'restUrl'     => esc_url_raw( rest_url( 'bush/v1/' ) ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'messages'    => [
                    'loading' => __( 'Моля, изчакайте...', 'bushlyaka' ),
                    'success' => __( 'Резервацията е създадена успешно!', 'bushlyaka' ),
                    'error'   => __( 'Възникна грешка. Опитайте отново.', 'bushlyaka' ),
                ],
                'redirectUrl' => home_url('/thank-you'),
            ]);
        }

        /** Shortcodes */
        public static function register_shortcodes() {
            add_shortcode( 'bushlyaka_booking', [ __CLASS__, 'render_booking_form' ] );
        }

        /** Форма за резервации (frontend) */
        public static function render_booking_form() {
            ob_start(); ?>
            <div class="bushlyaka-booking-form">
                <form>
                    <div class="bush-field">
                        <label><?php _e( 'Изберете период:', 'bushlyaka' ); ?></label>
                        <input type="text" class="bush-date-range" name="daterange" readonly>
                    </div>

                    <div class="bush-field">
                        <label><?php _e( 'Сектори:', 'bushlyaka' ); ?></label>
                        <div class="bush-sectors">
                            <?php foreach ( range(1, 19) as $sector ) : ?>
                                <div class="bush-sector" data-sector="<?php echo esc_attr( $sector ); ?>">
                                    <?php echo sprintf( __( 'Сектор %d', 'bushlyaka' ), $sector ); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="bush-field">
                        <label><?php _e( 'Брой рибари:', 'bushlyaka' ); ?></label>
                        <select name="anglers">
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                        <label>
                            <input type="checkbox" name="secondHasCard"> <?php _e( 'Втори с карта', 'bushlyaka' ); ?>
                        </label>
                    </div>

                    <div class="bush-field">
                        <label><?php _e( 'Име:', 'bushlyaka' ); ?></label>
                        <input type="text" name="firstName" required>
                    </div>
                    <div class="bush-field">
                        <label><?php _e( 'Фамилия:', 'bushlyaka' ); ?></label>
                        <input type="text" name="lastName" required>
                    </div>
                    <div class="bush-field">
                        <label><?php _e( 'Имейл:', 'bushlyaka' ); ?></label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="bush-field">
                        <label><?php _e( 'Телефон:', 'bushlyaka' ); ?></label>
                        <input type="text" name="phone" required>
                    </div>
                    <div class="bush-field">
                        <label><?php _e( 'Бележки:', 'bushlyaka' ); ?></label>
                        <textarea name="notes"></textarea>
                    </div>

                    <div class="bush-field">
                        <label><?php _e( 'Метод на плащане:', 'bushlyaka' ); ?></label>
                        <select name="payMethod"></select>
                    </div>

                    <div class="bush-price">
                        <?php _e( 'Цена:', 'bushlyaka' ); ?> <span class="bush-price-estimate">0.00 лв.</span>
                    </div>

                    <div class="bush-error-global" style="display:none;color:red;"></div>

                    <button type="submit"><?php _e( 'Изпрати резервация', 'bushlyaka' ); ?></button>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }

        /** Admin страници */
        public static function render_admin_dashboard() {
            echo '<div class="wrap"><h1>Bushlyak Booking</h1><p>Тук ще бъдат настройките и резервациите.</p></div>';
        }

        public static function render_admin_bookings() {
            include plugin_dir_path( __FILE__ ) . 'admin/admin-bookings.php';
        }

        public static function render_admin_prices() {
            include plugin_dir_path( __FILE__ ) . 'admin/admin-prices.php';
        }

        public static function render_admin_payments() {
            include plugin_dir_path( __FILE__ ) . 'admin/admin-payments.php';
        }

        public static function render_admin_blackouts() {
            include plugin_dir_path( __FILE__ ) . 'admin/admin-blackouts.php';
        }
    }

    Bushlyak_Booking_Plugin::init();
}
