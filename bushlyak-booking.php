<?php
/**
 * Plugin Name: Bushlyaka Booking
 * Description: Система за резервации със сектори, blackout периоди и плащания.
 * Version: 1.0.0
 * Author: Bushlyaka Dev Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bushlyak_Booking_Plugin {

    public static function init() {
        // Активиране / деактивиране
        register_activation_hook( __FILE__, [ __CLASS__, 'on_activate' ] );
        register_deactivation_hook( __FILE__, [ __CLASS__, 'on_deactivate' ] );

        // Hooks
        add_action( 'init', [ __CLASS__, 'register_shortcodes' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_menu', [ __CLASS__, 'register_admin_menu' ] );

        // Зареждаме REST API класове
        require_once __DIR__ . '/includes/class-bushlyak-booking-db.php';
        require_once __DIR__ . '/includes/class-bushlyak-booking-rest.php';
        Bushlyak_Booking_REST::init();
    }

    /** При активиране */
    public static function on_activate() {
        require_once __DIR__ . '/includes/class-bushlyak-booking-db.php';
        Bushlyak_Booking_DB::create_tables();
    }

    /** При деактивиране */
    public static function on_deactivate() {
        // Тук може да чистим cron задачи, кешове и др.
    }

    /** Зареждане на ресурси */
    public static function enqueue_assets() {
        $ver = '1.0.0';
        $css_file = plugin_dir_url( __FILE__ ) . 'assets/css/styles.css';
        $js_file  = plugin_dir_url( __FILE__ ) . 'assets/js/app.js';

        // Ако имаме минифицирани файлове – използвай тях
        if ( file_exists( plugin_dir_path( __FILE__ ) . 'assets/css/styles.min.css' ) ) {
            $css_file = plugin_dir_url( __FILE__ ) . 'assets/css/styles.min.css';
        }
        if ( file_exists( plugin_dir_path( __FILE__ ) . 'assets/js/app.min.js' ) ) {
            $js_file = plugin_dir_url( __FILE__ ) . 'assets/js/app.min.js';
        }

        wp_enqueue_style( 'bushlyak-booking', $css_file, [], $ver );
        wp_enqueue_script( 'bushlyak-booking', $js_file, [ 'jquery' ], $ver, true );

        // Локализирани данни за JS
        wp_localize_script( 'bushlyak-booking', 'bushlyaka', [
            'restUrl'     => esc_url_raw( rest_url( 'bush/v1/' ) ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'redirectUrl' => site_url( '/thanks' ), // може да стане настройка
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

    /** Форма за резервация */
    public static function render_booking_form() {
        ob_start();
        ?>
        <div class="bushlyaka-booking-form">
            <h2><?php _e( 'Резервация', 'bushlyaka' ); ?></h2>
            <div class="bush-error-global" style="display:none;color:red;"></div>
            
            <form>
                <!-- Календар -->
                <label><?php _e( 'Изберете дати:', 'bushlyaka' ); ?></label>
                <input type="text" class="bush-date-range" readonly />

                <!-- Сектори -->
                <div class="bush-sectors">
                    <?php foreach ( range(1, 6) as $sector ) : ?>
                        <div class="bush-sector" data-sector="<?php echo esc_attr( $sector ); ?>">
                            <?php echo sprintf( __( 'Сектор %d', 'bushlyaka' ), $sector ); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Англери -->
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

                <!-- Клиентски данни -->
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

                <!-- Методи за плащане -->
                <label><?php _e( 'Метод на плащане:', 'bushlyaka' ); ?></label>
                <select name="payMethod"></select>

                <button type="submit"><?php _e( 'Изпрати резервация', 'bushlyaka' ); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Админ меню */
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

    /** Страница: Резервации */
    public static function render_bookings_page() {
        global $wpdb;

        // Действия (одобри, откажи, изтрий)
        if ( isset( $_GET['action'], $_GET['booking_id'], $_GET['_wpnonce'] ) ) {
            $booking_id = intval( $_GET['booking_id'] );
            if ( wp_verify_nonce( $_GET['_wpnonce'], 'bush_booking_action_' . $booking_id ) ) {
                switch ( $_GET['action'] ) {
                    case 'approve':
                        Bushlyak_Booking_DB::update_booking_status( $booking_id, 'approved' );
                        add_settings_error( 'bushlyaka_booking', 'booking_updated', 'Резервацията е одобрена.', 'updated' );
                        break;
                    case 'reject':
                        Bushlyak_Booking_DB::update_booking_status( $booking_id, 'rejected' );
                        add_settings_error( 'bushlyaka_booking', 'booking_updated', 'Резервацията е отказана.', 'error' );
                        break;
                    case 'delete':
                        Bushlyak_Booking_DB::delete_booking( $booking_id );
                        add_settings_error( 'bushlyaka_booking', 'booking_deleted', 'Резервацията е изтрита.', 'error' );
                        break;
                }
            }
        }

        settings_errors( 'bushlyaka_booking' );

        $bookings = Bushlyak_Booking_DB::list_bookings(50);

        echo '<div class="wrap"><h1>' . __( 'Резервации', 'bushlyaka' ) . '</h1>';

        if ( ! empty( $bookings ) ) {
            echo '<table class="widefat fixed striped"><thead><tr>';
            echo '<th>ID</th><th>Сектор</th><th>От</th><th>До</th><th>Клиент</th><th>Телефон</th><th>Имейл</th><th>Статус</th><th>Действия</th>';
            echo '</tr></thead><tbody>';
            foreach ( $bookings as $b ) {
                $approve_url = wp_nonce_url(
                    admin_url( 'admin.php?page=bushlyaka-booking&action=approve&booking_id=' . $b->id ),
                    'bush_booking_action_' . $b->id
                );
                $reject_url = wp_nonce_url(
                    admin_url( 'admin.php?page=bushlyaka-booking&action=reject&booking_id=' . $b->id ),
                    'bush_booking_action_' . $b->id
                );
                $delete_url = wp_nonce_url(
                    admin_url( 'admin.php?page=bushlyaka-booking&action=delete&booking_id=' . $b->id ),
                    'bush_booking_action_' . $b->id
                );

                echo '<tr>';
                echo '<td>' . intval( $b->id ) . '</td>';
                echo '<td>' . esc_html( $b->sector ) . '</td>';
                echo '<td>' . esc_html( $b->start ) . '</td>';
                echo '<td>' . esc_html( $b->end ) . '</td>';
                echo '<td>' . esc_html( $b->client_first . ' ' . $b->client_last ) . '</td>';
                echo '<td>' . esc_html( $b->client_phone ) . '</td>';
                echo '<td>' . esc_html( $b->client_email ) . '</td>';
                echo '<td>' . ucfirst( esc_html( $b->status ) ) . '</td>';
                echo '<td>
                    <a href="' . esc_url( $approve_url ) . '" class="button button-primary">Одобри</a> 
                    <a href="' . esc_url( $reject_url ) . '" class="button">Откажи</a> 
                    <a href="' . esc_url( $delete_url ) . '" class="button button-danger" onclick="return confirm(\'Сигурни ли сте?\')">Изтрий</a>
                </td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __( 'Няма резервации.', 'bushlyaka' ) . '</p>';
        }

        echo '</div>';
    }

    /** Страница: Цени */
    public static function render_prices_page() {
        echo '<div class="wrap"><h1>' . __( 'Цени', 'bushlyaka' ) . '</h1>';
        $prices = Bushlyak_Booking_DB::get_prices();
        echo '<pre>'; print_r($prices); echo '</pre>';
        echo '</div>';
    }

    /** Страница: Blackouts */
    public static function render_blackouts_page() {
        echo '<div class="wrap"><h1>' . __( 'Blackout периоди', 'bushlyaka' ) . '</h1>';
        $blackouts = Bushlyak_Booking_DB::list_blackouts();
        echo '<pre>'; print_r($blackouts); echo '</pre>';
        echo '</div>';
    }

    /** Страница: Методи за плащане */
    public static function render_paymethods_page() {
        echo '<div class="wrap"><h1>' . __( 'Методи за плащане', 'bushlyaka' ) . '</h1>';
        $methods = Bushlyak_Booking_DB::list_paymethods();
        echo '<pre>'; print_r($methods); echo '</pre>';
        echo '</div>';
    }

    /** Страница: Настройки */
    public static function render_settings_page() {
        echo '<div class="wrap"><h1>' . __( 'Настройки', 'bushlyaka' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'bushlyaka_booking' );
        do_settings_sections( 'bushlyaka_booking' );
        submit_button();
        echo '</form></div>';
    }
}

Bushlyak_Booking_Plugin::init();
