<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;

// Добавяне на цена
if ( isset($_POST['bush_add_price']) && check_admin_referer('bush_price_action') ) {
    $valid_from       = sanitize_text_field($_POST['valid_from']) . ' 12:00:00';
    $base             = floatval($_POST['base']);
    $second           = floatval($_POST['second']);
    $second_with_card = floatval($_POST['second_with_card']);

    $wpdb->insert("{$wpdb->prefix}bush_prices", [
        'valid_from'       => $valid_from,
        'base'             => $base,
        'second'           => $second,
        'second_with_card' => $second_with_card,
    ]);
    echo '<div class="updated"><p>Цената е добавена.</p></div>';
}

// Изтриване
if ( isset($_GET['delete']) && check_admin_referer('bush_price_delete') ) {
    $wpdb->delete("{$wpdb->prefix}bush_prices", [ 'id' => intval($_GET['delete']) ]);
    echo '<div class="updated"><p>Цената е изтрита.</p></div>';
}

$prices = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bush_prices ORDER BY valid_from DESC");
?>
<div class="wrap">
    <h1>Цени</h1>

    <form method="post">
        <?php wp_nonce_field('bush_price_action'); ?>
        <table class="form-table">
            <tr>
                <th><label for="valid_from">Валидна от</label></th>
                <td><input type="date" name="valid_from" required></td>
            </tr>
            <tr>
                <th><label for="base">Базова цена</label></th>
                <td><input type="text" name="base" required></td>
            </tr>
            <tr>
                <th><label for="second">Втори рибар</label></th>
                <td><input type="text" name="second" required></td>
            </tr>
            <tr>
                <th><label for="second_with_card">Втори с карта</label></th>
                <td><input type="text" name="second_with_card" required></td>
            </tr>
        </table>
        <p><input type="submit" name="bush_add_price" class="button-primary" value="Добави"></p>
    </form>

    <h2>Списък с цени</h2>
    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Валидна от</th>
                <th>Базова цена</th>
                <th>Втори рибар</th>
                <th>Втори с карта</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $prices ) : ?>
                <?php foreach ( $prices as $p ) : ?>
                    <?php
                    $valid_from = date_i18n('d.m.Y', strtotime($p->valid_from)) . ' 12:00';
                    ?>
                    <tr>
                        <td><?php echo intval($p->id); ?></td>
                        <td><?php echo esc_html($valid_from); ?></td>
                        <td><?php echo number_format_i18n($p->base, 2) . ' лв.'; ?></td>
                        <td><?php echo number_format_i18n($p->second, 2) . ' лв.'; ?></td>
                        <td><?php echo number_format_i18n($p->second_with_card, 2) . ' лв.'; ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=bushlyak-prices&delete='.$p->id), 'bush_price_delete'); ?>" class="button" onclick="return confirm('Сигурни ли сте, че искате да изтриете тази цена?');">Изтрий</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr><td colspan="6">Няма въведени цени.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
