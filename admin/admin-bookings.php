<?php
if ( ! defined( 'ABSPATH' ) ) exit;

echo '<div class="wrap"><h1>' . __( 'Резервации', 'bushlyaka' ) . '</h1>';

/** Обработка на действия */
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

/** Зареждане на резервации */
$bookings = Bushlyak_Booking_DB::list_bookings(50);

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
