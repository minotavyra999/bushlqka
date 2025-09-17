<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Проверка на права
if ( ! current_user_can('manage_options') ) {
    wp_die( __( 'Нямате права да достъпите тази страница.' ) );
}

global $wpdb;
$table = $wpdb->prefix . 'bush_bookings';

// Промяна на статус
if ( isset($_GET['booking_action'], $_GET['id']) ) {
    $id = intval($_GET['id']);
    $action = sanitize_text_field($_GET['booking_action']);

    if ( check_admin_referer('booking_action_' . $id) ) {
        if ( in_array($action, ['approve','reject','delete']) ) {
            if ($action === 'delete') {
                $wpdb->delete($table, [ 'id' => $id ]);
                echo '<div class="updated notice"><p>Резервацията е изтрита.</p></div>';
            } else {
                $status = ($action === 'approve') ? 'approved' : 'rejected';
                $wpdb->update($table, [ 'status' => $status ], [ 'id' => $id ]);
                echo '<div class="updated notice"><p>Статусът е обновен на: ' . esc_html($status) . '</p></div>';
            }
        }
    } else {
        echo '<div class="error notice"><p>Невалидна операция (nonce грешка).</p></div>';
    }
}

// Взимаме всички резервации
$bookings = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
?>

<div class="wrap">
    <h1>Резервации</h1>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Период</th>
                <th>Сектор</th>
                <th>Рибари</th>
                <th>Клиент</th>
                <th>Имейл</th>
                <th>Телефон</th>
                <th>Плащане</th>
                <th>Статус</th>
                <th>Създадена</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $bookings ) : ?>
            <?php foreach ( $bookings as $b ) : ?>
                <tr>
                    <td><?php echo esc_html($b->id); ?></td>
                    <td><?php echo esc_html($b->start); ?> → <?php echo esc_html($b->end); ?></td>
                    <td><?php echo esc_html($b->sector); ?></td>
                    <td>
                        <?php echo esc_html($b->anglers); ?>
                        <?php if ( $b->secondHasCard ) echo '(+ карта)'; ?>
                    </td>
                    <td><?php echo esc_html($b->client_first . ' ' . $b->client_last); ?></td>
                    <td><?php echo esc_html($b->client_email); ?></td>
                    <td><?php echo esc_html($b->client_phone); ?></td>
                    <td><?php echo esc_html($b->pay_method); ?></td>
                    <td><?php echo esc_html($b->status); ?></td>
                    <td><?php echo esc_html($b->created_at); ?></td>
                    <td>
                        <?php 
                        $approve_url = wp_nonce_url(admin_url('admin.php?page=bushlyaka-booking-bookings&booking_action=approve&id=' . $b->id), 'booking_action_' . $b->id);
                        $reject_url  = wp_nonce_url(admin_url('admin.php?page=bushlyaka-booking-bookings&booking_action=reject&id=' . $b->id), 'booking_action_' . $b->id);
                        $delete_url  = wp_nonce_url(admin_url('admin.php?page=bushlyaka-booking-bookings&booking_action=delete&id=' . $b->id), 'booking_action_' . $b->id);
                        ?>
                        <a href="<?php echo esc_url($approve_url); ?>" class="button">Одобри</a>
                        <a href="<?php echo esc_url($reject_url); ?>" class="button">Отхвърли</a>
                        <a href="<?php echo esc_url($delete_url); ?>" class="button button-danger" onclick="return confirm('Сигурни ли сте, че искате да изтриете тази резервация?');">Изтрий</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="11">Няма направени резервации.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
