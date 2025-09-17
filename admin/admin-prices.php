<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap"><h1>' . __( 'Цени', 'bushlyaka' ) . '</h1>';

/** Добавяне на цена */
if ( isset( $_POST['bush_add_price'] ) && check_admin_referer( 'bush_add_price_action', 'bush_add_price_nonce' ) ) {
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}bush_prices",
        [
            'valid_from'       => sanitize_text_field( $_POST['valid_from'] ),
            'base'             => floatval( $_POST['base'] ),
            'second'           => floatval( $_POST['second'] ),
            'second_with_card' => floatval( $_POST['second_with_card'] ),
        ],
        [ '%s','%f','%f','%f' ]
    );
    echo '<div class="updated"><p>Цената е добавена успешно.</p></div>';
}

/** Изтриване на цена */
if ( isset( $_GET['delete_price'], $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'bush_delete_price_' . $_GET['delete_price'] ) ) {
    global $wpdb;
    $wpdb->delete( "{$wpdb->prefix}bush_prices", [ 'id' => intval( $_GET['delete_price'] ) ], [ '%d' ] );
    echo '<div class="updated"><p>Цената е изтрита.</p></div>';
}

/** Лист с цени */
global $wpdb;
$prices = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bush_prices ORDER BY valid_from DESC" );

if ( $prices ) {
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>Валидна от</th><th>Един рибар</th><th>Двама</th><th>Втори с карта</th><th>Действия</th>';
    echo '</tr></thead><tbody>';

    foreach ( $prices as $p ) {
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=bushlyaka-booking-prices&delete_price=' . $p->id ),
            'bush_delete_price_' . $p->id
        );

        echo '<tr>';
        echo '<td>' . intval( $p->id ) . '</td>';
        echo '<td>' . esc_html( $p->valid_from ) . '</td>';
        echo '<td>' . esc_html( $p->base ) . ' лв.</td>';
        echo '<td>' . esc_html( $p->second ) . ' лв.</td>';
        echo '<td>' . esc_html( $p->second_with_card ) . ' лв.</td>';
        echo '<td><a href="' . esc_url( $delete_url ) . '" class="button button-danger" onclick="return confirm(\'Сигурни ли сте?\')">Изтрий</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
} else {
    echo '<p>Няма добавени цени.</p>';
}

/** Форма за нова цена */
?>
<h2><?php _e( 'Добави нова цена', 'bushlyaka' ); ?></h2>
<form method="post">
    <?php wp_nonce_field( 'bush_add_price_action', 'bush_add_price_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th><label for="valid_from">Валидна от</label></th>
            <td><input type="date" name="valid_from" required></td>
        </tr>
        <tr>
            <th><label for="base">Един рибар</label></th>
            <td><input type="number" step="0.01" name="base" required> лв.</td>
        </tr>
        <tr>
            <th><label for="second">Двама</label></th>
            <td><input type="number" step="0.01" name="second" required> лв.</td>
        </tr>
        <tr>
            <th><label for="second_with_card">Втори с карта</label></th>
            <td><input type="number" step="0.01" name="second_with_card" required> лв.</td>
        </tr>
    </table>
    <p><input type="submit" name="bush_add_price" class="button-primary" value="Добави"></p>
</form>
</div>
