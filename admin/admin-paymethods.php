<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Bushlyak_Admin_Paymethods {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
        add_action( 'admin_post_bushlyak_save_paymethod', [ __CLASS__, 'save_paymethod' ] );
        add_action( 'admin_post_bushlyak_delete_paymethod', [ __CLASS__, 'delete_paymethod' ] );

        // при активация да създадем таблицата
        register_activation_hook( __FILE__, [ __CLASS__, 'create_table' ] );
    }

    public static function menu() {
        add_submenu_page(
            'bushlyak-booking',
            'Методи на плащане',
            'Методи на плащане',
            'manage_options',
            'bushlyak-paymethods',
            [ __CLASS__, 'page' ]
        );
    }

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'bush_paymethods';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            method_name varchar(200) NOT NULL,
            method_note text NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function page() {
        global $wpdb;
        $table = $wpdb->prefix . 'bush_paymethods';

        // взимаме всички методи
        $methods = $wpdb->get_results("SELECT * FROM $table ORDER BY id DESC");
        ?>
        <div class="wrap">
            <h1>Методи на плащане</h1>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="bushlyak_save_paymethod">
                <?php wp_nonce_field( 'bushlyak_save_paymethod' ); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="method_name">Метод</label></th>
                        <td><input name="method_name" type="text" id="method_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="method_note">Коментар</label></th>
                        <td><textarea name="method_note" id="method_note" class="regular-text" rows="3"></textarea></td>
                    </tr>
                </table>

                <?php submit_button('Добави метод'); ?>
            </form>

            <h2>Списък с методи</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Метод</th>
                        <th>Коментар</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($methods): ?>
                        <?php foreach ($methods as $m): ?>
                            <tr>
                                <td><?php echo esc_html($m->id); ?></td>
                                <td><?php echo esc_html($m->method_name); ?></td>
                                <td><?php echo esc_html($m->method_note); ?></td>
                                <td>
                                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline-block;">
                                        <input type="hidden" name="action" value="bushlyak_delete_paymethod">
                                        <input type="hidden" name="id" value="<?php echo esc_attr($m->id); ?>">
                                        <?php wp_nonce_field( 'bushlyak_delete_paymethod' ); ?>
                                        <button type="submit" class="button button-danger" onclick="return confirm('Сигурни ли сте, че искате да изтриете този метод?')">Изтрий</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4">Няма въведени методи.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function save_paymethod() {
        if ( ! current_user_can('manage_options') || ! check_admin_referer( 'bushlyak_save_paymethod' ) ) {
            wp_die('Not allowed');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bush_paymethods';

        $wpdb->insert($table, [
            'method_name' => sanitize_text_field($_POST['method_name']),
            'method_note' => sanitize_textarea_field($_POST['method_note']),
        ]);

        wp_redirect(admin_url('admin.php?page=bushlyak-paymethods'));
        exit;
    }

    public static function delete_paymethod() {
        if ( ! current_user_can('manage_options') || ! check_admin_referer( 'bushlyak_delete_paymethod' ) ) {
            wp_die('Not allowed');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'bush_paymethods';
        $id = intval($_POST['id']);

        $wpdb->delete($table, [ 'id' => $id ]);

        wp_redirect(admin_url('admin.php?page=bushlyak-paymethods'));
        exit;
    }
}

Bushlyak_Admin_Paymethods::init();
