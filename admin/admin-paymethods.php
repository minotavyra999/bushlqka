<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap"><h1>' . __( 'Методи за плащане', 'bushlyaka' ) . '</h1>';

/** Добавяне на метод */
if ( isset( $_POST['bush_add_paymethod'] ) && check_admin_referer( 'bush_add_paymethod_action', 'bush_add_paymethod_nonce' ) ) {
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}bush_paymethods",
        [
            'name'        => sanitize_text_field( $_POST['name'] ),
            'instructions'=> sanitize_textarea_field( $_POST['instructions'] ),
        ],
        [ '%s','%s' ]
    );
    echo '<div class="updated"><p>Методът за плащане е добавен успешно.</p></div>';
}

/** Изтриване */
if ( isset( $_GET['delete_paymethod'], $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'bush_delete_paymethod_' . $_GET['delete_paymethod'] ) ) {
    global $wpdb;
    $wpdb->delete( "{$wpdb->prefix}bush_paymethods", [ 'id' => intval( $_GET['delete_paymethod'] ) ], [ '%d' ] );
    echo '<div class="updated"><p>Методът за плащане е изтрит.</p></div>';
}

/** Редакция */
if ( isset( $_POST['bush_edit_paymethod'] ) && check_admin_referer( 'bush_edit_paymethod_action', 'bush_edit_paymethod_nonce' ) ) {
    global $wpdb;
    $wpdb->update(
        "{$wpdb->prefix}bush_paymethods",
        [
            'name'        => sanitize_text_field( $_POST['name'] ),
            'instructions'=> sanitize_textarea_field( $_POST['instructions'] ),
        ],
        [ 'id' => intval( $_POST['id'] ) ],
        [ '%s','%s' ],
        [ '%d' ]
    );
    echo '<div class="updated"><p>Методът за плащане е обновен.</p></div>';
}

/** Лист с методи */
global $wpdb;
$methods = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bush_paymethods ORDER BY id ASC" );

if ( $methods ) {
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>Име</th><th>Инструкции</th><th>Действия</th>';
    echo '</tr></thead><tbody>';

    foreach ( $methods as $m ) {
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=bushlyaka-booking-paymethods&delete_paymethod=' . $m->id ),
            'bush_delete_paymethod_' . $m->id
        );

        echo '<tr>';
        echo '<td>' . intval( $m->id ) . '</td>';
        echo '<td>' . esc_html( $m->name ) . '</td>';
        echo '<td>' . esc_html( $m->instructions ) . '</td>';
        echo '<td>
            <a href="' . esc_url( admin_url( 'admin.php?page=bushlyaka-booking-paymethods&edit=' . $m->id ) ) . '" class="button">Редактирай</a> 
            <a href="' . esc_url( $delete_url ) . '" class="button button-danger" onclick="return confirm(\'Сигурни ли сте?\')">Изтрий</a>
        </td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
} else {
    echo '<p>Няма добавени методи за плащане.</p>';
}

/** Форма за редакция */
if ( isset( $_GET['edit'] ) ) {
    $id = intval( $_GET['edit'] );
    $method = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bush_paymethods WHERE id = %d", $id ) );
    if ( $method ) {
        ?>
        <h2>Редактирай метод</h2>
        <form method="post">
            <?php wp_nonce_field( 'bush_edit_paymethod_action', 'bush_edit_paymethod_nonce' ); ?>
            <input type="hidden" name="id" value="<?php echo intval( $method->id ); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="name">Име</label></th>
                    <td><input type="text" name="name" value="<?php echo esc_attr( $method->name ); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="instructions">Инструкции</label></th>
                    <td><textarea name="instructions" rows="4"><?php echo esc_textarea( $method->instructions ); ?></textarea></td>
                </tr>
            </table>
            <p><input type="submit" name="bush_edit_paymethod" class="button-primary" value="Обнови"></p>
        </form>
        <?php
    }
}

/** Форма за нов метод */
?>
<h2><?php _e( 'Добави нов метод', 'bushlyaka' ); ?></h2>
<form method="post">
    <?php wp_nonce_field( 'bush_add_paymethod_action', 'bush_add_paymethod_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th><label for="name">Име</label></th>
            <td><input type="text" name="name" required></td>
        </tr>
        <tr>
            <th><label for="instructions">Инструкции</label></th>
            <td><textarea name="instructions" rows="4"></textarea></td>
        </tr>
    </table>
    <p><input type="submit" name="bush_add_paymethod" class="button-primary" value="Добави"></p>
</form>
</div>
