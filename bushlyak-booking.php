<?php
/**
 * Plugin Name: Bushlyak Booking
 * Description: Резервации за язовир Бушляк — календар, 19 сектора, blackout дни, плащания, пълен админ панел, имейл известия.
 * Version: 1.5.2
 * Author: Ivaylo Gochev
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-bushlyak-booking-db.php';
require_once __DIR__ . '/includes/class-bushlyak-booking-rest.php';

class Bushlyak_Booking_Plugin {
    const VERSION = '1.5.2';
    const SLUG    = 'bushlyak-booking';
    private $rest = null;

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'on_activate']);
        add_shortcode('bushlyak_booking', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'admin_menu']);
        $this->rest = new Bushlyak_Booking_REST();
        add_action('plugins_loaded', [$this, 'maybe_upgrade']);
    }

    public function maybe_upgrade(){
        Bushlyak_Booking_DB::create_tables();
        Bushlyak_Booking_DB::migrate_add_columns();
        Bushlyak_Booking_DB::seed_default_prices();
        Bushlyak_Booking_DB::seed_examples();
        update_option('bush_version', self::VERSION);
    }

    public function on_activate() { $this->maybe_upgrade(); }

    public function enqueue_assets() {
        // Flatpickr за фронтенда
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', [], '4.6.13');
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', [], '4.6.13', true);

        // Нашите стилове/скрипт
        wp_enqueue_style(self::SLUG, plugins_url('assets/css/styles.css', __FILE__), [], self::VERSION);
        $ccss = get_option('bush_custom_css', '');
        if ($ccss) { wp_add_inline_style(self::SLUG, $ccss); }
        wp_enqueue_script(self::SLUG, plugins_url('assets/js/app.js', __FILE__), ['flatpickr'], self::VERSION, true);

        wp_localize_script(self::SLUG, 'BushCfg', [
            'rest' => [ 'base' => esc_url_raw( rest_url('bush/v1/') ), 'nonce' => wp_create_nonce('wp_rest') ],
            'sectors' => range(1,19),
        ]);
    }

    public function shortcode($atts = []) {
        $atts = shortcode_atts(['class' => ''], $atts);
        $extra = $atts['class'] ? ' ' . sanitize_html_class($atts['class']) : '';
        ob_start(); ?>
        <div id="bush-booking" class="bush-wrap<?php echo esc_attr($extra); ?>">
          <h2 class="bush-title">Резервации — язовир Бушляк</h2>

          <div class="bush-step" data-step="1">
            <h3>Стъпка 1: Период (12:00 → 12:00)</h3>
            <p class="bush-hint">Ако началната дата е петък, минималният престой е 48 часа (до неделя 12:00).</p>
            <label>Изберете период:</label>
            <input id="bush-date-range" class="bush-input" type="text" placeholder="Изберете дати" />
            <div id="bush-duration" class="bush-meta"></div>
            <button class="bush-btn bush-next" data-next="2" disabled>Напред</button>
          </div>

          <div class="bush-step" data-step="2" hidden>
            <h3>Стъпка 2: Избор на сектор</h3>
            <div id="bush-unavailable" class="bush-hint"></div>
            <div id="bush-sectors" class="bush-sectors"></div>
            <div class="bush-nav">
              <button class="bush-btn ghost bush-prev" data-prev="1">Назад</button>
              <button class="bush-btn bush-next" data-next="3" disabled>Напред</button>
            </div>
          </div>

          <div class="bush-step" data-step="3" hidden>
            <h3>Стъпка 3: Рибари и цена</h3>
            <div class="bush-grid">
              <label>Брой рибари
                <select id="bush-anglers" class="bush-input">
                  <option value="1">1</option>
                  <option value="2">2</option>
                </select>
              </label>
              <label class="bush-inline">
                <input type="checkbox" id="bush-second-card" disabled /> Втори рибар има карта
              </label>
            </div>
            <div id="bush-price" class="bush-price"></div>
            <div class="bush-meta" id="bush-price-meta"></div>
            <div class="bush-nav">
              <button class="bush-btn ghost bush-prev" data-prev="2">Назад</button>
              <button class="bush-btn bush-next" data-next="4">Напред</button>
            </div>
          </div>

          <div class="bush-step" data-step="4" hidden>
            <h3>Стъпка 4: Данни за контакт</h3>
            <div class="bush-grid two">
              <label>Име <input id="bush-first" class="bush-input" /></label>
              <label>Фамилия <input id="bush-last" class="bush-input" /></label>
              <label>Имейл <input id="bush-email" type="email" class="bush-input" /></label>
              <label>Телефон <input id="bush-phone" type="tel" class="bush-input" /></label>
              <label class="full">Бележка <input id="bush-notes" class="bush-input" /></label>
            </div>
            <div class="bush-nav">
              <button class="bush-btn ghost bush-prev" data-prev="3">Назад</button>
              <button class="bush-btn bush-next" data-next="5" disabled>Напред</button>
            </div>
          </div>

          <div class="bush-step" data-step="5" hidden>
            <h3>Стъпка 5: Плащане (информативно)</h3>
            <label>Метод
              <select id="bush-pay-method" class="bush-input"></select>
            </label>
            <div class="bush-hint" id="bush-pay-instr"></div>
            <label class="bush-inline">
              <input type="checkbox" id="bush-consent" checked /> Съгласен съм с общите условия.
            </label>
            <div class="bush-nav">
              <button class="bush-btn ghost bush-prev" data-prev="4">Назад</button>
              <button class="bush-btn bush-next" data-next="6">Напред</button>
            </div>
          </div>

          <div class="bush-step" data-step="6" hidden>
            <h3>Стъпка 6: Преглед и потвърждение</h3>
            <div id="bush-summary" class="bush-summary"></div>
            <div class="bush-nav">
              <button class="bush-btn ghost bush-prev" data-prev="5">Назад</button>
              <button id="bush-submit" class="bush-btn">Потвърди заявката</button>
            </div>
            <div id="bush-result" class="bush-result"></div>
          </div>
        </div>
        <?php return ob_get_clean();
    }

    /* ---------- ADMIN ---------- */

    private function admin_handle_actions(){
        if (!current_user_can('manage_options')) return;
        if (!isset($_GET['page']) || $_GET['page'] !== 'bushlyak-booking') return;

        // Промяна на статус
        if (isset($_GET['approve']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'bush_approve')) {
            Bushlyak_Booking_DB::update_booking_status(intval($_GET['approve']), 'approved');
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Резервацията е одобрена.</p></div>'; });
        }
        if (isset($_GET['reject']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'bush_reject')) {
            Bushlyak_Booking_DB::update_booking_status(intval($_GET['reject']), 'rejected');
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Резервацията е отказана.</p></div>'; });
        }
        // Изтриване
        if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'bush_del')) {
            Bushlyak_Booking_DB::delete_booking(intval($_GET['delete']));
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Резервацията е изтрита.</p></div>'; });
        }
        // Добавяне на плащане
        if (isset($_POST['bush_add_payment']) && check_admin_referer('bush_add_payment')) {
            Bushlyak_Booking_DB::add_payment([
                'booking_id' => intval($_POST['booking_id']),
                'amount'     => floatval($_POST['amount']),
                'method'     => sanitize_text_field($_POST['method']),
                'reference'  => sanitize_text_field($_POST['reference']),
            ]);
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Плащането е добавено.</p></div>'; });
        }
        // Цени
        if (isset($_POST['bush_save_prices']) && check_admin_referer('bush_prices')) {
            Bushlyak_Booking_DB::update_prices(
                floatval($_POST['base']),
                floatval($_POST['second']),
                floatval($_POST['second_card'])
            );
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Цените са обновени.</p></div>'; });
        }
        // Blackout
        if (isset($_POST['bush_blackout_add']) && check_admin_referer('bush_blackout_add')) {
            Bushlyak_Booking_DB::add_blackout(
                sanitize_text_field($_POST['bo_start']),
                sanitize_text_field($_POST['bo_end']),
                sanitize_text_field($_POST['bo_reason'])
            );
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Блокиран период е добавен.</p></div>'; });
        }
        if (isset($_GET['bo_del']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'bush_bo_del')) {
            Bushlyak_Booking_DB::delete_blackout(intval($_GET['bo_del']));
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Блокираният период е изтрит.</p></div>'; });
        }
        // Методи за плащане
        if (isset($_POST['bush_paymethod_add']) && check_admin_referer('bush_paymethod_add')) {
            Bushlyak_Booking_DB::add_paymethod(
                sanitize_text_field($_POST['pm_name']),
                wp_kses_post($_POST['pm_instr'])
            );
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Методът е добавен.</p></div>'; });
        }
        if (isset($_GET['pm_del']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'],'bush_pm_del')) {
            Bushlyak_Booking_DB::delete_paymethod(intval($_GET['pm_del']));
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Методът е изтрит.</p></div>'; });
        }
        // Custom CSS
        //if (isset($_POST['bush_custom_css_save']) && check_admin_referer('bush_custom_css')) {
           // update_option('bush_custom_css', wp_unslash($_POST['bush_custom_css']));
           // add_action('admin_notices', function(){ echo '<div class="updated"><p>Custom CSS е записано.</p></div>'; });
        //}
        // Имейли за известия
        if (isset($_POST['bush_notify_save']) && check_admin_referer('bush_notify')) {
            update_option('bush_notify_emails', sanitize_text_field($_POST['notify_emails']));
            add_action('admin_notices', function(){ echo '<div class="updated"><p>Имейлите за уведомления са записани.</p></div>'; });
        }
    }

    public function admin_page(){
        $this->admin_handle_actions();
        $prices = Bushlyak_Booking_DB::get_prices();
        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;

        echo '<div class="wrap"><h1>Bushlyak Booking — Настройки и Резервации</h1>';

        /* ЦЕНИ */
        echo '<h2 class="title">Цени (за 24ч)</h2>';
        echo '<form method="post">';
        wp_nonce_field('bush_prices');
        echo '<table class="form-table">';
        echo '<tr><th>База (1 рибар)</th><td><input name="base" type="number" step="0.01" value="'.esc_attr($prices['base_per_24h']).'" /></td></tr>';
        echo '<tr><th>2-ри рибар без карта</th><td><input name="second" type="number" step="0.01" value="'.esc_attr($prices['second_angler_price']).'" /></td></tr>';
        echo '<tr><th>2-ри рибар с карта</th><td><input name="second_card" type="number" step="0.01" value="'.esc_attr($prices['second_angler_with_card_price']).'" /></td></tr>';
        echo '</table><p><button class="button button-primary" name="bush_save_prices" value="1">Запази</button></p></form>';

        /* CUSTOM CSS */
        /*$ccss = get_option('bush_custom_css', '');
        echo '<h2 class="title">Custom CSS</h2>';
        echo '<form method="post">'; wp_nonce_field('bush_custom_css');
        echo '<textarea name="bush_custom_css" rows="8" style="width:100%;font-family:monospace;">'.esc_textarea($ccss).'</textarea>';
        echo '<p><button class="button" name="bush_custom_css_save" value="1">Запази CSS</button></p>';
        echo '</form>';*/

        /* ИМЕЙЛИ */
        echo '<h2 class="title">Имейли за уведомления</h2>';
        $notify = get_option('bush_notify_emails', get_option('admin_email'));
        echo '<form method="post">'; wp_nonce_field('bush_notify');
        echo '<input type="text" name="notify_emails" value="'.esc_attr($notify).'" style="width:100%" placeholder="email1@example.com, email2@example.com" />';
        echo '<p><button class="button" name="bush_notify_save" value="1">Запази</button></p>';
        echo '</form>';

        /* BLACKOUT ДАТИ */
        echo '<hr/><h2>Затворени дати / събития</h2>';
        echo '<form method="post" style="margin-bottom:12px">';
        wp_nonce_field('bush_blackout_add');
        echo '<input type="date" name="bo_start" required /> → <input type="date" name="bo_end" required /> ';
        echo '<input type="text" name="bo_reason" placeholder="Причина (по желание)" size="40" /> ';
        echo '<button class="button" name="bush_blackout_add" value="1">Добави</button>';
        echo '</form>';
        $blackouts = Bushlyak_Booking_DB::list_blackouts();
        echo '<table class="widefat"><thead><tr><th>ID</th><th>От</th><th>До</th><th>Причина</th><th></th></tr></thead><tbody>';
        if ($blackouts){
            foreach($blackouts as $bo){
                $del = wp_nonce_url(admin_url('admin.php?page=bushlyak-booking&bo_del='.$bo->id), 'bush_bo_del');
                echo '<tr><td>'.intval($bo->id).'</td><td>'.esc_html($bo->start_date).'</td><td>'.esc_html($bo->end_date).'</td><td>'.esc_html($bo->reason).'</td><td><a class="button" href="'.$del.'">Изтрий</a></td></tr>';
            }
        } else {
            echo '<tr><td colspan="5">Няма блокирани периоди.</td></tr>';
        }
        echo '</tbody></table>';

        /* МЕТОДИ ЗА ПЛАЩАНЕ */
        echo '<hr/><h2>Методи на плащане + инструкции</h2>';
        echo '<form method="post" style="margin-bottom:12px">';
        wp_nonce_field('bush_paymethod_add');
        echo '<p><label>Име на метод: <input type="text" name="pm_name" required /></label></p>';
        echo '<p><label>Инструкции:<br/><textarea name="pm_instr" rows="4" cols="70" required></textarea></label></p>';
        echo '<p><button class="button" name="bush_paymethod_add" value="1">Добави метод</button></p>';
        echo '</form>';
        $methods = Bushlyak_Booking_DB::list_paymethods();
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Метод</th><th>Инструкции</th><th></th></tr></thead><tbody>';
        if ($methods){
            foreach($methods as $m){
                $del = wp_nonce_url(admin_url('admin.php?page=bushlyak-booking&pm_del='.$m->id), 'bush_pm_del');
                echo '<tr><td>'.intval($m->id).'</td><td>'.esc_html($m->name).'</td><td>'.wp_kses_post(nl2br($m->instructions)).'</td><td><a class="button" href="'.$del.'">Изтрий</a></td></tr>';
            }
        } else {
            echo '<tr><td colspan="4">Няма добавени методи.</td></tr>';
        }
        echo '</tbody></table>';

        /* РЕЗЕРВАЦИИ */
        $view = isset($_GET['view']) ? intval($_GET['view']) : 0;
        if ($view) {
            $b = Bushlyak_Booking_DB::get_booking($view);
            if ($b) {
                echo '<hr/><h2>Резервация #'.intval($b->id).'</h2>';
                echo '<p><strong>Период:</strong> '.esc_html($b->start).' → '.esc_html($b->end).' (Сектор '.$b->sector.')</p>';
                echo '<p><strong>Рибари:</strong> '.$b->anglers.($b->second_has_card?' (2-ри с карта)':'').'</p>';
                echo '<p><strong>Клиент:</strong> '.esc_html($b->client_first.' '.$b->client_last).' · '.esc_html($b->phone).' · '.esc_html($b->email).'</p>';
                echo '<p><strong>Сума:</strong> '.number_format((float)$b->price_estimate,2,'.',' ').' лв</p>';
                echo '<p><strong>Статус:</strong> '.esc_html($b->status).'</p>';
                if (isset($b->pay_method) && $b->pay_method) echo '<p><strong>Метод за плащане:</strong> '.esc_html($b->pay_method).'</p>';
                if (isset($b->pay_instructions) && $b->pay_instructions) echo '<div style="white-space:pre-wrap;background:#fff;border:1px solid #e5e7eb;padding:8px;border-radius:8px;"><strong>Инструкции:</strong> '.wp_kses_post($b->pay_instructions).'</div>';

                $approve_url = wp_nonce_url( admin_url('admin.php?page=bushlyak-booking&approve='.$b->id), 'bush_approve' );
                $reject_url  = wp_nonce_url( admin_url('admin.php?page=bushlyak-booking&reject='.$b->id),  'bush_reject' );
                $del_url     = wp_nonce_url( admin_url('admin.php?page=bushlyak-booking&delete='.$b->id),  'bush_del' );
                $confirm  = esc_js('Сигурни ли сте, че искате да изтриете тази резервация?');

                echo '<p><a class="button button-primary" href="'.$approve_url.'">Одобри</a> <a class="button" href="'.$reject_url.'">Откажи</a> <a class="button" style="color:#b91c1c" href="'.$del_url.'" onclick="return confirm(\''.$confirm.'\');">Изтрий</a></p>';

                // Плащания
                $payments = Bushlyak_Booking_DB::list_payments($b->id);
                echo '<h3>Плащания</h3>';
                if ($payments){
                    echo '<table class="widefat"><thead><tr><th>ID</th><th>Сума</th><th>Метод</th><th>Референция</th><th>Дата</th></tr></thead><tbody>';
                    foreach ($payments as $p) {
                        echo '<tr><td>'.intval($p->id).'</td><td>'.number_format((float)$p->amount,2,'.',' ').'</td><td>'.esc_html($p->method).'</td><td>'.esc_html($p->reference).'</td><td>'.esc_html($p->received_at).'</td></tr>';
                    }
                    echo '</tbody></table>';
                } else { echo '<p>Няма въведени плащания.</p>'; }

                echo '<h4>Добави плащане</h4>';
                echo '<form method="post">';
                wp_nonce_field('bush_add_payment');
                echo '<input type="hidden" name="booking_id" value="'.intval($b->id).'" />';
                echo '<p><label>Сума <input name="amount" type="number" step="0.01" /></label></p>';
                echo '<p><label>Метод <select name="method"><option value="cash">В брой</option><option value="bank">Банков превод</option><option value="card">Карта</option></select></label></p>';
                echo '<p><label>Референция <input name="reference" type="text" /></label></p>';
                echo '<p><button class="button button-primary" name="bush_add_payment" value="1">Добави</button></p>';
                echo '</form>';
            } else {
                echo '<p>Резервацията не е намерена.</p>';
            }
        } else {
            echo '<h2 class="title">Резервации (последни 100)</h2>';
            $items = Bushlyak_Booking_DB::list_bookings(100);
            echo '<table class="widefat"><thead><tr><th>ID</th><th>Период</th><th>Сектор</th><th>Рибари</th><th>Клиент</th><th>Статус</th><th>Създадено</th><th></th></tr></thead><tbody>';
            if ($items){
                foreach ($items as $b){
                    $view_url = admin_url('admin.php?page=bushlyak-booking&view='.$b->id);
                    $del_url  = wp_nonce_url( admin_url('admin.php?page=bushlyak-booking&delete='.$b->id),  'bush_del' );
                    $confirm  = esc_js('Да изтрия тази резервация?');
                    echo '<tr>';
                    echo '<td>'.intval($b->id).'</td>';
                    echo '<td>'.esc_html($b->start.' → '.$b->end).'</td>';
                    echo '<td>'.intval($b->sector).'</td>';
                    echo '<td>'.intval($b->anglers).'</td>';
                    echo '<td>'.esc_html($b->client_first.' '.$b->client_last.' · '.$b->phone.' · '.$b->email).'</td>';
                    echo '<td>'.esc_html($b->status).'</td>';
                    echo '<td>'.esc_html($b->created_at).'</td>';
                    echo '<td><a class="button" href="'.$view_url.'">Преглед</a> <a class="button" style="color:#b91c1c" href="'.$del_url.'" onclick="return confirm(\''.$confirm.'\');">Изтрий</a></td>';
                    echo '</tr>';
                }
            } else { echo '<tr><td colspan="8">Няма записи.</td></tr>'; }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public function admin_menu(){
        add_menu_page('Bushlyak Booking', 'Bushlyak Booking', 'manage_options', 'bushlyak-booking', [$this, 'admin_page'], 'dashicons-calendar-alt');
    }
}
new Bushlyak_Booking_Plugin();
