<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Проверка на права
if ( ! current_user_can('manage_options') ) {
    wp_die( __( 'Нямате права да достъпите тази страница.' ) );
}

global $wpdb;
$table = $wpdb->prefix . 'bush_prices';

// Изтриване на цена
if ( isset($_GET['delete_price']) ) {
    $price_id = intval($_GET['delete_price']);
    if ( check_admin_referer('delete_price_' . $price_id) ) {
        $wpdb->delete( $table, [ 'id' => $price_id ] );
        echo '<div class="updated notice"><p>Цената е изтрита успешно.</p></div>';
    } else {
        echo '<div class="error notice"><p>Невалиден опит за изтриване (nonce грешка).</p></div>';
    }
}

// Добавяне на нова цена
if ( isset($_POST['new_price']) ) {
    check_admin_referer('add_price_action');
    $wpdb->insert( $table, [
        'base'             => floatval($_POST['base']),
        'second'           => floatval($_POST['second']),
        'second_with_card' => floatval($_POST['second_with_card']),
    ] );
    echo '<div class="updated notice"><p>Новата цена е добавена успешно.</p></div>';
}

// Вземаме всички цени
$prices = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
?>

<div class="wrap">
    <h1>Цени</h1>

    <h2>Добави нова цена</h2>
    <form method="post">
        <?php wp_nonce_field('add_price_action'); ?>
        <table class="form-table">
            <tr>
                <th><label for="base">Първи рибар</label></th>
                <td><input type="number" step="0.01" name="base" required></td>
            </tr>
            <tr>
                <th><label for="second">Втори рибар</label></th>
                <td><input type="number" step="0.01" name="second" required></td>
            </tr>
            <tr>
                <th><label for="second_with_card">Втори рибар с карта</label></th>
                <td><input type="number" step="0.01" name="second_with_card" required></td>
            </tr>
        </table>
        <p><input type="submit" name="new_price" class="button button-primary" value="Добави"></p>
    </form>

    <h2>Списък с цени</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Първи рибар</th>
                <th>Втори рибар</th>
                <th>Втори рибар с карта</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $prices ) : ?>
                <?php foreach ( $prices as $price ) : ?>
                    <tr>
                        <td><?php echo esc_html($price->id); ?></td>
                        <td><?php echo esc_html($price->base); ?> лв.</td>
                        <td><?php echo esc_html($price->second); ?> лв.</td>
                        <td><?php echo esc_html($price->second_with_card); ?> лв.</td>
                        <td>
                            <?php 
                            $delete_url = wp_nonce_url(
                                admin_url('admin.php?page=bushlyaka-booking-prices&delete_price=' . $price->id),
                                'delete_price_' . $price->id
                            );
                            ?>
                            <a href="<?php echo esc_url($delete_url); ?>" 
                               class="button button-small button-danger"
                               onclick="return confirm('Сигурни ли сте, че искате да изтриете тази цена?');">
                                Изтрий
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="5">Няма въведени цени.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
