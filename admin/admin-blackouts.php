<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap"><h1>' . __( 'Blackout периоди', 'bushlyaka' ) . '</h1>';

/** Добавяне на blackout */
if ( isset( $_POST['bush_add_blackout'] ) && check_admin_referer( 'bush_add_blackout_action', 'bush_add_blackout_nonce' ) ) {
    global $wpdb;
    $wpdb->insert(
        "{$wpdb->prefix}bush_blackouts",
        [
            'start'  => sanitize_text_field( $_POST['start'] ),
            'end'    => sanitize_text_field( $_POST['end'] ),
            'reason' => sanitize_text_field( $_POST['reason'] ),
        ],
        [ '%s','%s','%s' ]
    );
    echo '<div class="updated"><p>Blackout периодът е добавен успешно.</p></div>';
}

/** Изтриване на blackout */
if ( isset( $_GET['delete_blackout'], $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'bush_delete_blackout_' . $_GET['delete_blackout'] ) ) {
    global $wpdb;
    $wpdb->delete( "{$wpdb->prefix}bush_blackouts", [ 'id' => intval( $_GET['delete_blackout'] ) ], [ '%d' ] );
    echo '<div class="updated"><p>Blackout периодът е изтрит.</p></div>';
}

/** Лист с blackout-и */
global $wpdb;
$blackouts = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}bush_blackouts ORDER BY start DESC" );

if ( $blackouts ) {
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>ID</th><th>Начало</th><th>Край</th><th>Причина</th><th>Действия</th>';
    echo '</tr></thead><tbody>';

    foreach ( $blackouts as $b ) {
        $delete_url = wp_nonce_url(
            admin_url( 'admin.php?page=bushlyaka-booking-blackouts&delete_blackout=' . $b->id ),
            'bush_delete_blackout_' . $b->id
        );

        echo '<tr>';
        echo '<td>' . intval( $b->id ) . '</td>';
        echo '<td>' . esc_html( $b->start ) . '</td>';
        echo '<td>' . esc_html( $b->end ) . '</td>';
        echo '<td>' . esc_html( $b->reason ) . '</td>';
        echo '<td><a href="' . esc_url( $delete_url ) . '" class="button button-danger" onclick="return confirm(\'Сигурни ли сте?\')">Изтрий</a></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
} else {
    echo '<p>Няма blackout периоди.</p>';
}

/** Форма за нов blackout */
?>
<h2><?php _e( 'Добави нов blackout', 'bushlyaka' ); ?></h2>
<form method="post">
    <?php wp_nonce_field( 'bush_add_blackout_action', 'bush_add_blackout_nonce' ); ?>
    <table class="form-table">
        <tr>
            <th><label for="start">Начална дата</label></th>
            <td><input type="date" name="start" required></td>
        </tr>
        <tr>
            <th><label for="end">Крайна дата</label></th>
            <td><input type="date" name="end" required></td>
        </tr>
        <tr>
            <th><label for="reason">Причина</label></th>
            <td><input type="text" name="reason" placeholder="Пример: Поддръжка"></td>
        </tr>
    </table>
    <p><input type="submit" name="bush_add_blackout" class="button-primary" value="Добави"></p>
</form>
</div>
