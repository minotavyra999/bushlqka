<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$bookings = Bushlyak_Booking_DB::list_bookings(100);
?>

<div class="wrap">
    <h1><?php _e('Резервации', 'bushlyaka'); ?></h1>

    <?php if ( empty($bookings) ): ?>
        <p><?php _e('Няма резервации до момента.', 'bushlyaka'); ?></p>
    <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'bushlyaka'); ?></th>
                    <th><?php _e('Начало', 'bushlyaka'); ?></th>
                    <th><?php _e('Край', 'bushlyaka'); ?></th>
                    <th><?php _e('Сектор', 'bushlyaka'); ?></th>
                    <th><?php _e('Клиент', 'bushlyaka'); ?></th>
                    <th><?php _e('Имейл', 'bushlyaka'); ?></th>
                    <th><?php _e('Телефон', 'bushlyaka'); ?></th>
                    <th><?php _e('Статус', 'bushlyaka'); ?></th>
                    <th><?php _e('Създадена', 'bushlyaka'); ?></th>
                    <th><?php _e('Действия', 'bushlyaka'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $bookings as $b ): ?>
                <tr>
                    <td><?php echo esc_html($b->id); ?></td>
                    <td><?php echo esc_html($b->start); ?></td>
                    <td><?php echo esc_html($b->end); ?></td>
                    <td><?php echo esc_html($b->sector); ?></td>
                    <td><?php echo esc_html($b->client_first . ' ' . $b->client_last); ?></td>
                    <td><?php echo esc_html($b->client_email); ?></td>
                    <td><?php echo esc_html($b->client_phone); ?></td>
                    <td>
                        <?php
                            if ($b->status === 'approved') {
                                echo '<span style="color:green;font-weight:bold;">Одобрена</span>';
                            } elseif ($b->status === 'rejected') {
                                echo '<span style="color:red;font-weight:bold;">Отказана</span>';
                            } else {
                                echo '<span style="color:orange;font-weight:bold;">Изчаква</span>';
                            }
                        ?>
                    </td>
                    <td><?php echo esc_html($b->created_at); ?></td>
                    <td>
                        <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=bushlyak_approve_booking&id='.$b->id), 'bush_booking_action' ); ?>">✅ Одобри</a> |
                        <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=bushlyak_reject_booking&id='.$b->id), 'bush_booking_action' ); ?>">❌ Откажи</a> |
                        <a href="<?php echo wp_nonce_url( admin_url('admin-post.php?action=bushlyak_delete_booking&id='.$b->id), 'bush_booking_action' ); ?>" onclick="return confirm('Сигурни ли сте, че искате да изтриете тази резервация?')">🗑 Изтрий</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
