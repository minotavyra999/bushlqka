<?php
if ( ! defined('ABSPATH') ) exit;

global $wpdb;

// ➕ Добавяне на нов метод
if ( isset($_POST['bush_add_paymethod']) && check_admin_referer('bush_add_paymethod_action','bush_add_paymethod_nonce') ) {
    $name = sanitize_text_field($_POST['name']);
    $instructions = sanitize_textarea_field($_POST['instructions']);
    if ($name) {
        $wpdb->insert("{$wpdb->prefix}bush_paymethods", [
            'name' => $name,
            'instructions' => $instructions
        ]);
        echo '<div class="updated"><p>Методът е добавен.</p></div>';
    }
}

// ❌ Изтриване
if ( isset($_GET['delete']) ) {
    $wpdb->delete("{$wpdb->prefix}bush_paymethods", [ 'id' => intval($_GET['delete']) ]);
    echo '<div class="updated"><p>Методът е изтрит.</p></div>';
}

$methods = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bush_paymethods ORDER BY id ASC");
?>

<div class="wrap">
    <h1>Методи за плащане</h1>

    <h2>Добавяне на метод</h2>
    <form method="post">
        <?php wp_nonce_field('bush_add_paymethod_action','bush_add_paymethod_nonce'); ?>
        <p>
            <label>Име на метода:</label><br>
            <input type="text" name="name" required style="width:300px;">
        </p>
        <p>
            <label>Инструкции (ще се показват на клиента):</label><br>
            <textarea name="instructions" style="width:400px;height:100px;"></textarea>
        </p>
        <p><input type="submit" name="bush_add_paymethod" class="button button-primary" value="Добави"></p>
    </form>

    <h2>Списък с методи</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Име</th>
                <th>Инструкции</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( $methods ) : ?>
            <?php foreach ( $methods as $m ) : ?>
                <tr>
                    <td><?php echo $m->id; ?></td>
                    <td><?php echo esc_html($m->name); ?></td>
                    <td><?php echo esc_html($m->instructions); ?></td>
                    <td><a href="<?php echo admin_url('admin.php?page=bushlyak-payments&delete='.$m->id); ?>" onclick="return confirm('Сигурни ли сте?')">❌ Изтрий</a></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="4">Няма добавени методи.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
