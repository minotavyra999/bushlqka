<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Шаблон за имейл потвърждение на резервация
 * Очаква да получи $booking (масив с данни за резервацията)
 */
?>

<h2>Потвърждение на резервация</h2>

<p><strong>Име:</strong> <?php echo esc_html($booking['client_first']); ?> <?php echo esc_html($booking['client_last']); ?></p>
<p><strong>Имейл:</strong> <?php echo esc_html($booking['client_email']); ?></p>
<p><strong>Телефон:</strong> <?php echo esc_html($booking['client_phone']); ?></p>

<p><strong>Период:</strong> <?php echo esc_html($booking['start']); ?> до <?php echo esc_html($booking['end']); ?></p>
<p><strong>Сектор:</strong> <?php echo intval($booking['sector']); ?></p>
<p><strong>Брой рибари:</strong> <?php echo intval($booking['anglers']); ?></p>

<?php if (!empty($booking['secondHasCard'])): ?>
    <p><strong>Втори рибар:</strong> С карта</p>
<?php else: ?>
    <p><strong>Втори рибар:</strong> Без карта</p>
<?php endif; ?>

<p><strong>Метод на плащане:</strong> <?php echo !empty($booking['payName']) ? esc_html($booking['payName']) : '—'; ?></p>

<?php if (!empty($booking['payInstructions'])): ?>
    <p><strong>Инструкции за плащане:</strong><br>
    <?php echo nl2br(esc_html($booking['payInstructions'])); ?></p>
<?php endif; ?>

<?php if (!empty($booking['notes'])): ?>
    <p><strong>Бележки:</strong><br><?php echo nl2br(esc_html($booking['notes'])); ?></p>
<?php endif; ?>

<hr>
<p><em>Успешен риболов ! </em></p>
