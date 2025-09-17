<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="bushlyaka-booking-form">
    <form id="bushlyakaBookingForm">

        <div class="form-group">
            <label for="daterange">Изберете период</label>
            <input type="text" id="daterange" name="daterange" required>
        </div>

        <div class="form-group">
            <label for="sector">Сектор</label>
            <select id="sector" name="sector" required>
                <option value="">-- Изберете сектор --</option>
                <?php for ($i = 1; $i <= 19; $i++): ?>
                    <option value="<?php echo $i; ?>">Сектор <?php echo $i; ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="anglers">Брой рибари</label>
            <select id="anglers" name="anglers" required>
                <option value="1">1 рибар</option>
                <option value="2">2 рибари</option>
            </select>
        </div>

        <div class="form-group checkbox">
            <label>
                <input type="checkbox" id="secondHasCard" name="secondHasCard">
                Втори рибар с карта
            </label>
        </div>

        <div class="form-group price-display">
            <strong>Цена: <span id="price">—</span></strong>
        </div>

        <div class="form-group">
            <label for="firstName">Име</label>
            <input type="text" id="firstName" name="firstName" required>
        </div>

        <div class="form-group">
            <label for="lastName">Фамилия</label>
            <input type="text" id="lastName" name="lastName" required>
        </div>

        <div class="form-group">
            <label for="email">Имейл</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div class="form-group">
            <label for="phone">Телефон</label>
            <input type="text" id="phone" name="phone" required>
        </div>

        <div class="form-group">
            <label for="notes">Бележки</label>
            <textarea id="notes" name="notes"></textarea>
        </div>

        <div class="form-group">
            <label for="payMethod">Метод на плащане</label>
            <select id="payMethod" name="payMethod" required>
                <option value="">-- Изберете метод --</option>
            </select>
            <div id="payDescription" class="pay-desc"></div>
        </div>

        <div class="form-group">
            <button type="submit" class="button button-primary">Резервирай</button>
        </div>
    </form>
</div>
