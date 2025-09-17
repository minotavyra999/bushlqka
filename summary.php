<?php
if ( ! defined( 'ABSPATH' ) ) exit;

global $wpdb;
$sectors_table = $wpdb->prefix . 'bush_sectors';
$paymethods_table = $wpdb->prefix . 'bush_paymethods';

$sectors = $wpdb->get_results("SELECT * FROM $sectors_table ORDER BY id ASC");
$paymethods = $wpdb->get_results("SELECT * FROM $paymethods_table WHERE active = 1 ORDER BY id ASC");
?>

<div class="bushlyaka-booking-form">
    <form id="bushlyakaBookingForm">

        <!-- Дата -->
        <div class="form-group">
            <label for="daterange">Изберете период</label>
            <input type="text" id="daterange" name="daterange" required>
        </div>

        <!-- Сектор -->
        <div class="form-group">
            <label for="sector">Сектор</label>
            <select id="sector" name="sector" required>
                <option value="">-- Изберете сектор --</option>
                <?php foreach ( $sectors as $s ) : ?>
                    <option value="<?php echo esc_attr($s->id); ?>">
                        <?php echo esc_html($s->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Рибари -->
        <div class="form-group">
            <label for="anglers">Брой рибари</label>
            <select id="anglers" name="anglers" required>
                <option value="1">1 рибар</option>
                <option value="2">2 рибари</option>
            </select>
        </div>

        <!-- Втори с карта -->
        <div class="form-group checkbox">
            <label>
                <input type="checkbox" id="secondHasCard" name="secondHasCard">
                Втори рибар с карта
            </label>
        </div>

        <!-- Цена -->
        <div class="form-group price-display">
            <strong>Цена: <span id="price">—</span></strong>
        </div>

        <!-- Име -->
        <div class="form-group">
            <label for="firstName">Име</label>
            <input type="text" id="firstName" name="firstName" required>
        </div>

        <!-- Фамилия -->
        <div class="form-group">
            <label for="lastName">Фамилия</label>
            <input type="text" id="lastName" name="lastName" required>
        </div>

        <!-- Имейл -->
        <div class="form-group">
            <label for="email">Имейл</label>
            <input type="email" id="email" name="email" required>
        </div>

        <!-- Телефон -->
        <div class="form-group">
            <label for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" required>
        </div>

        <!-- Бележки -->
        <div class="form-group">
            <label for="notes">Бележки</label>
            <textarea id="notes" name="notes" rows="3"></textarea>
        </div>

        <!-- Метод на плащане -->
        <div class="form-group">
            <label for="payMethod">Метод на плащане</label>
            <select id="payMethod" name="payMethod" required>
                <option value="">-- Изберете метод --</option>
                <?php foreach ( $paymethods as $m ) : ?>
                    <option value="<?php echo esc_attr($m->id); ?>">
                        <?php echo esc_html($m->name); ?> — <?php echo esc_html($m->instructions); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Бутон -->
        <div class="form-group">
            <button type="submit" class="button button-primary">Резервирай</button>
        </div>
    </form>
</div>
