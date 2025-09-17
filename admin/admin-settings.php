<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap"><h1>' . __( 'Настройки', 'bushlyaka' ) . '</h1>';

/** Запазване на настройки */
if ( isset( $_POST['bush_save_settings'] ) && check_admin_referer( 'bush_settings_action', 'bush_settings_nonce' ) ) {
    update_option( 'bush_notify_emails', sanitize_text_field( $_POST['notify_emails'] ) );
    update_option( 'bush_redirect_url', esc_url_raw( $_POST['redirect_url'] ) );
    echo '<div class="updated"><p>Настройките са запазени успешно.</p></div>';
}

/** Текущи стойности */
$notify_emails = get_option( 'bush_notify_emails', get_option( 'admin_email' ) );
$redirect_url  = get_option( 'bush_redirect_url', site_url( '/thanks' ) );
?>

<form method="post">
    <?php wp_nonce_field( 'bush_settings_action', 'bush_settings_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th><label for="notify_emails">Имейли за известия</label></th>
            <td>
                <input type="text" name="notify_emails" value="<?php echo esc_attr( $notify_emails ); ?>" class="regular-text">
                <p class="description">Въведете един или няколко имейла, разделени със запетая, на които да се изпращат известия за нови резервации.</p>
            </td>
        </tr>
        <tr>
            <th><label for="redirect_url">Redirect URL</label></th>
            <td>
                <input type="text" name="redirect_url" value="<?php echo esc_attr( $redirect_url ); ?>" class="regular-text">
                <p class="description">Адресът, към който клиентите ще бъдат пренасочени след успешна резервация (например страница „Благодарим“).</p>
            </td>
        </tr>
    </table>
    <p><input type="submit" name="bush_save_settings" class="button-primary" value="Запази"></p>
</form>
</div>
