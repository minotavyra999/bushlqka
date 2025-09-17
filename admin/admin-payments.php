<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Проверка на права
if ( ! current_user_can('manage_options') ) {
    wp_die( __( 'Нямате права да достъпите тази страница.' ) );
}

global $wpdb;
$table = $wpdb->prefix . 'bush_paymethods';

// Изтриване
if ( isset($_GET['delete_method']) ) {
    $id = intval($_GET['delete_method']);
    if ( check_admin_referer('delete_method_' . $id) ) {
        $wpdb->delete( $table, [ 'id' => $id ] );
        echo '<div class="updated notice"><p>Методът е изтрит.</p></div>';
    } else {
        echo '<div class="error notice"><p>Невалидна операция (nonce грешка).</p></div>';
    }
}

// Добавяне / редакция
if ( isset($_POST['save_method']) ) {
    check_admin_referer('save_method_action');

    $data = [
        'name'         => sanitize_text_field($_POST['name']),
        'instructions' => sanitize_textarea_field($_POST['instructions']),
        'active'       => isset($_POST['active']) ? 1 : 0,
    ];

    if ( ! empty($_POST['id']) ) {
        $wpdb->update($table, $data, [ 'id' => intval($_POST['id']) ]);
        echo '<div class="updated notice"><p>Методът е обновен.</p></div>';
    } else {
        $wpdb->insert($table, $data);
        echo '<div class="updated notice"><p>Методът е добавен.</p></div>';
    }
}

// Всички методи
$methods = $wpdb->get_results("SELECT * FROM $table ORDER BY id ASC");
?>

<div class="wrap">
    <h1>Методи за плащане</h1>

    <h2>Добави / Редактирай</h2>
    <form method="post">
        <?php wp_nonce_field('save_method_action'); ?>
        <input type="hidden" name="id" value="">
        <table class="form-table">
            <tr>
                <th><label for="name">Име</label></th>
                <td><input type="text" name="name" required></td>
            </tr>
            <tr>
                <th><label for="instructions">Инструкции</label></th>
                <td><textarea name="instructions" rows="4"></textarea></td>
            </tr>
            <tr>
                <th><label for="active">Активен</label></th>
                <td><input type="checkbox" name="active" value="1" checked></td>
            </tr>
        </table>
        <p><input type="submit" name="save_method" class="button button-primary" value="Запази"></p>
    </form>

    <h2>Списък</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Име</th>
                <th>Инструкции</th>
                <th>Активен</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $methods ) : ?>
                <?php foreach ( $methods as $m ) : ?>
                    <tr>
                        <td><?php echo esc_html($m->id); ?></td>
                        <td><?php echo esc_html($m->name); ?></td>
                        <td><?php echo nl2br(esc_html($m->instructions)); ?></td>
                        <td><?php echo $m->active ? 'Да' : 'Не'; ?></td>
                        <td>
                            <?php 
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=bushlyaka-booking-payments&delete_method=' . $m->id),
                                'delete_method_' . $m->id
                            );
                            ?>
                            <a href="<?php echo esc_url($delete_url); ?>" class="button button-danger" onclick="return confirm('Сигурни ли сте, че искате да изтриете този метод?');">Изтрий</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="5">Няма добавени методи.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
