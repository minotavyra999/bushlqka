<?php
/**
 * Plugin Name: Bushlyak Booking
 * Description: Система за резервации на Bushlyak.
 * Version: 1.1.0
 * Author: Ivaylo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once plugin_dir_path(__FILE__) . 'includes/class-bushlyak-booking-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-bushlyak-booking-rest.php';

// Зареждаме CSS и JS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('bushlyaka-styles', plugin_dir_url(__FILE__) . 'assets/css/styles.css', [], '1.1.0');
    wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], null, true);
    wp_enqueue_script('bushlyaka-app', plugin_dir_url(__FILE__) . 'assets/js/app.js', ['jquery','flatpickr'], '1.1.0', true);
    wp_localize_script('bushlyaka-app', 'bushlyaka', [
        'restUrl'     => esc_url_raw( rest_url('bushlyaka/v1/') ),
        'redirectUrl' => site_url('/booking-summary')
    ]);
});

// Шорткод за форма
add_shortcode('bushlyaka_booking', function() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'summary.php';
    return ob_get_clean();
});

// Админ меню
add_action('admin_menu', function() {
    add_menu_page(
        'Bushlyak Booking',
        'Bushlyak Booking',
        'edit_pages',
        'bushlyaka-booking',
        function() {
            echo '<div class="wrap"><h1>Bushlyak Booking</h1><p>Тук ще бъдат настройките и резервациите.</p></div>';
        },
        'dashicons-calendar-alt'
    );

    add_submenu_page(
        'bushlyaka-booking',
        'Резервации',
        'Резервации',
        'edit_pages',
        'bushlyaka-booking-bookings',
        function() {
            include plugin_dir_path(__FILE__) . 'admin/admin-bookings.php';
        }
    );

    add_submenu_page(
        'bushlyaka-booking',
        'Цени',
        'Цени',
        'edit_pages',
        'bushlyaka-booking-prices',
        function() {
            include plugin_dir_path(__FILE__) . 'admin/admin-prices.php';
        }
    );

    add_submenu_page(
        'bushlyaka-booking',
        'Методи на плащане',
        'Методи на плащане',
        'edit_pages',
        'bushlyaka-booking-payments',
        function() {
            include plugin_dir_path(__FILE__) . 'admin/admin-payments.php';
        }
    );

    add_submenu_page(
        'bushlyaka-booking',
        'Неактивни периоди',
        'Неактивни периоди',
        'edit_pages',
        'bushlyaka-booking-blackouts',
        function() {
            include plugin_dir_path(__FILE__) . 'admin/admin-blackouts.php';
        }
    );

    add_submenu_page(
        'bushlyaka-booking',
        'Настройки',
        'Настройки',
        'manage_options',
        'bushlyaka-booking-settings',
        function() {
            include plugin_dir_path(__FILE__) . 'admin/admin-settings.php';
        }
    );
});

// REST API
add_action('rest_api_init', function() {
    $rest = new Bushlyak_Booking_REST();
    $rest->register_routes();
});
