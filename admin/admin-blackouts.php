<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Проверка на права
if ( ! current_user_can('manage_options') ) {
    wp_die( __( 'Нямате права да достъпите тази страница.' ) );
}

global $wpdb;
$table = $wpdb->prefix . 'bush_blackouts';

// Изтриване на период
if ( isset($_GET['delete_blackout']) ) {
    $id = intval($_GET['delete_blackout']);
    if ( check_admin_referer('delete_blackout_' . $id) ) {
        $wpdb->delete( $table, [ 'id' => $id ] );
        echo '<div class="updated notice"><p>Периодът е изтрит успешно.</p></div>';
    } else {
        echo '<div class="error notice"><p>Невалиден опит за изтриване (nonce грешка).</p></div>';
    }
}

// Добавяне на период
if ( isset($_POST['new_blackout']) ) {
    check_admin_referer('add_blackout_action');
    $wpdb->insert( $table, [
        'start' => sanitize_text_field($_POST['start']),
        'end'   => sanitize_text_field($_POST['end']),
        'note'  => sanitize_textarea_field($_POST['note']),
    ] );
    echo '<div class="updated notice"><p>Новият период е добавен.</p></div>';
}

// Всички blackout-и
$blackouts = $wpdb->get_results("SELECT * FROM $table ORDER BY start ASC");
?>

<div class="wrap">
    <h1>Неактивни периоди</h1>

    <h2>Добави нов период</h2>
    <form method="post">
        <?php wp_nonce_field('add_blackout_action'); ?>
        <table class="form-table">
            <tr>
                <th><label for="start">Начало</label></th>
                <td><input type="date" name="start" required></td>
            </tr>
            <tr>
                <th><label for="end">Край</label></th>
                <td><input type="date" name="end" required></td>
            </tr>
            <tr>
                <th><label for="note">Бележка</label></th>
                <td><textarea name="note" rows="3"></textarea></td>
            </tr>
        </table>
        <p><input type="submit" name="new_blackout" class="button button-primary" value="Добави"></p>
    </form>

    <h2>Списък</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Начало</th>
                <th>Край</th>
                <th>Бележка</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $blackouts ) : ?>
                <?php foreach ( $blackouts as $b ) : ?>
                    <tr>
                        <td><?php echo esc_html($b->id); ?></td>
                        <td><?php echo esc_html($b->start); ?></td>
                        <td><?php echo esc_html($b->end); ?></td>
                        <td><?php echo esc_html($b->note); ?></td>
                        <td>
                            <?php 
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=bushlyaka-booking-blackouts&delete_blackout=' . $b->id),
                                'delete_blackout_' . $b->id
                            );
                            ?>
                            <a href="<?php echo esc_url($delete_url); ?>" 
                               class="button button-danger"
                               onclick="return confirm('Сигурни ли сте, че искате да изтриете този период?');">
                               Изтрий
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="5">Няма зададени неактивни периоди.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
