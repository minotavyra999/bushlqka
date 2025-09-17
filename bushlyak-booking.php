<?php
/**
 * Plugin Name: Bushlyak Booking
 * Description: Система за резервации на сектори (риболов).
 * Version: 1.3.0
 * Author: minotavyra
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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

            // Admin post actions
            add_action( 'admin_post_bushlyak_approve_booking', [ __CLASS__, 'handle_approve_booking' ] );
            add_action( 'admin_post_bushlyak_reject_booking', [ __CLASS__, 'handle_reject_booking' ] );
            add_action( 'admin_post_bushlyak_delete_booking', [ __CLASS__, 'handle_delete_booking' ] );

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
            wp_enqueue_style( 'bushlyak-booking', $url . 'assets/css/styles.css', [], '1.3' );
            wp_enqueue_script( 'bushlyak-booking', $url . 'assets/js/app.js', [ 'jquery' ], '1.3', true );

            wp_localize_script( 'bushlyak-booking', 'bushlyaka', [
                'restUrl'     => esc_url_raw( rest_url( 'bush/v1/' ) ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'messages'    => [
                    'loading' => __( 'Моля, изчакайте...', 'bushlyaka' ),
                    'success' => __( 'Резервацията е създадена успешно!', 'bushlyaka' ),
                    'error'   => __( 'Възникна грешка. Опитайте отново.', 'bushlyaka' ),
                ],
                'redirectUrl' => home_url('/booking-summary'), // страница със shortcode за резюме
            ]);
        }

        /** Shortcodes */
        public static function register_shortcodes() {
            add_shortcode( 'bushlyaka_booking', [ __CLASS__, 'render_booking_form' ] );
            add_shortcode( 'bushlyaka_booking_summary', [ __CLASS__, 'render_booking_summary' ] );
        }

        /** Форма за резервации (frontend) */
        public static function render_booking_form() {
            $methods = Bushlyak_Booking_DB::list_paymethods();

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

                    <div class="bush-field"><label>Име:</label><input type="text" name="firstName" required></div>
                    <div class="bush-field"><label>Фамилия:</label><input type="text" name="lastName" required></div>
                    <div class="bush-field"><label>Имейл:</label><input type="email" name="email" required></div>
                    <div class="bush-field"><label>Телефон:</label><input type="text" name="phone" required></div>
                    <div class="bush-field"><label>Бележки:</label><textarea name="notes"></textarea></div>

                    <div class="bush-field">
                        <label><?php _e( 'Метод на плащане:', 'bushlyaka' ); ?></label>
                        <select name="payMethod" required>
                            <option value=""><?php _e('— изберете —','bushlyaka'); ?></option>
                            <?php foreach ($methods as $m): ?>
                                <option value="<?php echo esc_attr($m->id); ?>">
                                    <?php echo esc_html($m->name . ' – ' . $m->instructions); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

        /** Страница с резюме за клиента */
        public static function render_booking_summary() {
            if ( empty($_GET['booking']) ) {
                return '<p>Няма намерена резервация.</p>';
            }

            global $wpdb;
            $id = intval($_GET['booking']);
            $b = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id=$id");
            if (!$b) return '<p>Няма намерена резервация.</p>';

            // изчисляваме цена
            $price = Bushlyak_Booking_REST::calculate_price(
                intval($b->anglers),
                !empty($b->secondHasCard),
                $b->start,
                $b->end
            );

            ob_start(); ?>
            <div class="bush-booking-summary">
                <h2>Резервация №<?php echo $b->id; ?></h2>
                <p><strong>Период:</strong> <?php echo $b->start . ' – ' . $b->end; ?></p>
                <p><strong>Сектор:</strong> <?php echo $b->sector; ?></p>
                <p><strong>Клиент:</strong> <?php echo $b->client_first . ' ' . $b->client_last; ?></p>
                <p><strong>Имейл:</strong> <?php echo $b->client_email; ?></p>
                <p><strong>Телефон:</strong> <?php echo $b->client_phone; ?></p>
                <p><strong>Бележки:</strong> <?php echo $b->notes; ?></p>
                <p><strong>Метод на плащане:</strong> <?php echo $b->pay_method; ?></p>
                <p><strong>Статус:</strong> <?php echo $b->status; ?></p>
                <p><strong>Обща цена:</strong> <?php echo number_format($price, 2, '.', ' '); ?> лв.</p>
            </div>
            <?php
            return ob_get_clean();
        }

        /** Имейл до клиента + админа */
        public static function send_booking_email($booking_id) {
            global $wpdb;
            $b = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bush_bookings WHERE id=$booking_id");
            if (!$b) return;

            $price = Bushlyak_Booking_REST::calculate_price(
                intval($b->anglers),
                !empty($b->secondHasCard),
                $b->start,
                $b->end
            );

            $subject = "Вашата резервация №{$b->id}";

            $message = '
            <html>
            <head>
              <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .wrapper { max-width: 600px; margin: auto; padding: 20px; border:1px solid #ddd; border-radius:8px; }
                h2 { color: #006400; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                td, th { border: 1px solid #ccc; padding: 8px; text-align: left; }
                th { background: #f5f5f5; }
              </style>
            </head>
            <body>
              <div class="wrapper">
                <h2>Благодарим за вашата резервация!</h2>
                <p>Вашата резервация е създадена успешно. Ето детайлите:</p>

                <table>
                  <tr><th>Номер на резервация</th><td>'.$b->id.'</td></tr>
                  <tr><th>Период</th><td>'.$b->start.' – '.$b->end.'</td></tr>
                  <tr><th>Сектор</th><td>'.$b->sector.'</td></tr>
                  <tr><th>Рибари</th><td>'.$b->anglers.'</td></tr>
                  <tr><th>Име</th><td>'.$b->client_first.' '.$b->client_last.'</td></tr>
                  <tr><th>Имейл</th><td>'.$b->client_email.'</td></tr>
                  <tr><th>Телефон</th><td>'.$b->client_phone.'</td></tr>
                  <tr><th>Метод на плащане</th><td>'.$b->pay_method.'</td></tr>
                  <tr><th>Бележки</th><td>'.$b->notes.'</td></tr>
                  <tr><th>Статус</th><td>'.$b->status.'</td></tr>
                  <tr><th>Обща цена</th><td>'.number_format($price, 2, '.', ' ').' лв.</td></tr>
                </table>

                <p style="margin-top:20px;">Ще се свържем с вас при необходимост. Благодарим, че избрахте Bushlyak!</p>
              </div>
            </body>
            </html>
            ';

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Bushlyak Booking <no-reply@'.$_SERVER['SERVER_NAME'].'>'
            ];

            // до клиента
            wp_mail($b->client_email, $subject, $message, $headers);

            // копие до администратора
            wp_mail(get_option('admin_email'), "Нова резервация №{$b->id}", $message, $headers);
        }

        /** Admin страници */
        public static function render_admin_dashboard() {
            echo '<div class="wrap"><h1>Bushlyak Booking</h1><p>Използвайте менюто за управление на резервации, цени и плащания.</p></div>';
        }
        public static function render_admin_bookings() { include plugin_dir_path( __FILE__ ) . 'admin/admin-bookings.php'; }
        public static function render_admin_prices() { include plugin_dir_path( __FILE__ ) . 'admin/admin-prices.php'; }
        public static function render_admin_payments() { include plugin_dir_path( __FILE__ ) . 'admin/admin-payments.php'; }
        public static function render_admin_blackouts() { include plugin_dir_path( __FILE__ ) . 'admin/admin-blackouts.php'; }

        /** Admin actions */
        public static function handle_approve_booking() {
            if ( ! current_user_can('manage_options') ) wp_die('Not allowed');
            check_admin_referer('bush_booking_action');
            $id = intval($_GET['id']);
            if ($id) Bushlyak_Booking_DB::update_booking_status($id, 'approved');
            wp_redirect( admin_url('admin.php?page=bushlyak-bookings') );
            exit;
        }
        public static function handle_reject_booking() {
            if ( ! current_user_can('manage_options') ) wp_die('Not allowed');
            check_admin_referer('bush_booking_action');
            $id = intval($_GET['id']);
            if ($id) Bushlyak_Booking_DB::update_booking_status($id, 'rejected');
            wp_redirect( admin_url('admin.php?page=bushlyak-bookings') );
            exit;
        }
        public static function handle_delete_booking() {
            if ( ! current_user_can('manage_options') ) wp_die('Not allowed');
            check_admin_referer('bush_booking_action');
            $id = intval($_GET['id']);
            if ($id) Bushlyak_Booking_DB::delete_booking($id);
            wp_redirect( admin_url('admin.php?page=bushlyak-bookings') );
            exit;
        }
    }

    Bushlyak_Booking_Plugin::init();
}
