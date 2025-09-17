<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Проверка на права
if ( ! current_user_can('manage_options') ) {
    wp_die( __( 'Нямате права да достъпите тази страница.' ) );
}

// Запис на настройки
if ( isset($_POST['save_settings']) ) {
    check_admin_referer('bushlyaka_settings_action');

    update_option('bushlyaka_booking_email', sanitize_email($_POST['booking_email']));
    update_option('bushlyaka_booking_redirect', sanitize_text_field($_POST['booking_redirect']));

    echo '<div class="updated notice"><p>Настройките са запазени.</p></div>';
}

// Вземаме текущи стойности
$email    = get_option('bushlyaka_booking_email', get_option('admin_email'));
$redirect = get_option('bushlyaka_booking_redirect', '/booking-summary');
?>

<div class="wrap">
    <h1>Настройки</h1>

    <form method="post">
        <?php wp_nonce_field('bushlyaka_settings_action'); ?>
        <table class="form-table">
            <tr>
                <th><label for="booking_email">Имейл за уведомления</label></th>
                <td>
                    <input type="email" name="booking_email" value="<?php echo esc_attr($email); ?>" required>
                    <p class="description">На този имейл ще получавате известия за нови резервации.</p>
                </td>
            </tr>
            <tr>
                <th><label for="booking_redirect">Страница за резюме</label></th>
                <td>
                    <input type="text" name="booking_redirect" value="<?php echo esc_attr($redirect); ?>" required>
                    <p class="description">URL или slug на страницата, където е сложен шорткода <code>[bushlyaka_booking_summary]</code>.</p>
                </td>
            </tr>
        </table>
        <p><input type="submit" name="save_settings" class="button button-primary" value="Запази"></p>
    </form>
</div>
