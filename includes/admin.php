<?php
/**
 * Anex Tour — Admin pages: Settings + Bookings.
 */

defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────
   Admin menu
───────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_menu_page(
        'Anex Tour',
        'Anex Tour',
        'manage_options',
        'anex-tour',
        'anex_admin_settings_page',
        'dashicons-palmtree',
        30
    );
    add_submenu_page( 'anex-tour', 'Налаштування', 'Налаштування', 'manage_options', 'anex-tour',            'anex_admin_settings_page' );
    add_submenu_page( 'anex-tour', 'Заявки',       'Заявки',       'manage_options', 'anex-tour-bookings',   'anex_admin_bookings_page' );
} );

/* ─────────────────────────────────────────────
   Settings save
───────────────────────────────────────────── */
/* ─── AJAX: Test API token ─── */
add_action( 'wp_ajax_anex_test_token', function () {
    /* JS надсилає поле `nonce`; без другого аргументу WP очікує `_ajax_nonce` і перевірка не проходить */
    check_ajax_referer( 'anex_test_token', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Недостатньо прав (потрібен адміністратор).' ] );
    }

    $token = get_option( ITTOUR_LAB_TOKEN_OPTION, '' );
    if ( $token === '' ) {
        wp_send_json_error( [ 'message' => 'Токен не задано. Введіть токен і збережіть налаштування.' ] );
    }

    try {
        /* Як у каталозі: список країн у module/params → data.countries (не dictionary/country!) */
        $result = ittour_lab_api_fetch( 'module/params', [], 'uk' );
    } catch ( Throwable $e ) {
        wp_send_json_error( [ 'message' => 'PHP: ' . $e->getMessage() ] );
    }

    if ( is_wp_error( $result ) ) {
        wp_send_json_error(
            [
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ]
        );
    }

    $payload   = $result['data'] ?? [];
    $api_error = is_array( $payload ) && ! empty( $payload['error'] )
        ? (string) ( $payload['error_desc'] ?? $payload['error'] ?? '' )
        : '';
    if ( $api_error !== '' ) {
        wp_send_json_error( [ 'message' => 'IT-Tour API: ' . $api_error ] );
    }

    $countries = $payload['countries'] ?? null;
    $count     = is_array( $countries ) ? count( $countries ) : 0;

    wp_send_json_success(
        [
            'message' => sprintf(
                '✅ Токен працює! Завантажено %d країн (module/params).',
                $count
            ),
        ]
    );
} );

add_action( 'admin_post_anex_save_settings', function () {
    check_admin_referer( 'anex_settings_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

    $fields = [
        ITTOUR_LAB_TOKEN_OPTION => 'ittour_api_token',
        'anex_agency_name'      => 'anex_agency_name',
        'anex_agency_phone'     => 'anex_agency_phone',
        'anex_agency_address'   => 'anex_agency_address',
        'anex_agency_viber'     => 'anex_agency_viber',
        'anex_agency_telegram'  => 'anex_agency_telegram',
        'anex_consult_avatar_url' => 'anex_consult_avatar_url',
        'anex_notify_email'     => 'anex_notify_email',
        'anex_slug_hotel_catalog'=> 'anex_slug_hotel_catalog',
        'anex_slug_hotel_detail' => 'anex_slug_hotel_detail',
        'anex_slug_excursion_detail' => 'anex_slug_excursion_detail',
        'anex_slug_tour_lab'    => 'anex_slug_tour_lab',
    ];
    foreach ( $fields as $post_key => $option_key ) {
        if ( ! isset( $_POST[ $post_key ] ) ) {
            continue;
        }
        $raw = wp_unslash( (string) $_POST[ $post_key ] );
        if ( 'anex_consult_avatar_url' === $option_key ) {
            update_option( $option_key, esc_url_raw( $raw ) );
            continue;
        }
        update_option( $option_key, sanitize_text_field( $raw ) );
    }
    // Modules
    $modules = [ 'hotel_catalog', 'tour_lab' ];
    foreach ( $modules as $m ) {
        update_option( 'anex_module_' . $m, isset( $_POST[ 'anex_module_' . $m ] ) ? '1' : '0' );
    }

    wp_redirect( admin_url( 'admin.php?page=anex-tour&saved=1' ) );
    exit;
} );

/* ─────────────────────────────────────────────
   Settings page HTML
───────────────────────────────────────────── */
function anex_admin_settings_page(): void {
    $token    = get_option( ITTOUR_LAB_TOKEN_OPTION, '' );
    $saved    = isset( $_GET['saved'] );
    ?>
    <div class="wrap">
        <h1>⚙️ Anex Tour — Налаштування</h1>

        <?php if ( $saved ): ?><div class="notice notice-success is-dismissible"><p>Налаштування збережено.</p></div><?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'anex_settings_nonce' ); ?>
            <input type="hidden" name="action" value="anex_save_settings">

            <h2>🔑 IT-Tour API</h2>
            <table class="form-table">
                <tr>
                    <th><label for="ittour_api_token">API Token</label></th>
                    <td>
                        <input type="password" id="ittour_api_token" name="ittour_api_token"
                               value="<?php echo esc_attr( $token ); ?>" class="regular-text">
                        <p class="description">Токен з особистого кабінету IT-Tour.</p>
                        <?php if ( $token === '' ): ?>
                            <p style="color:red;font-weight:700">⚠ Токен не задано — всі виджети будуть порожні!</p>
                        <?php else: ?>
                            <p style="color:green">✅ Токен задано (<?php echo strlen($token); ?> символів).</p>
                        <?php endif; ?>
                        <button type="button" id="anex-test-token" class="button button-secondary"
                                style="margin-top:8px"
                                <?php if ( $token === '' ) echo 'disabled'; ?>>
                            🔌 Перевірити підключення до API
                        </button>
                        <span id="anex-test-result" style="margin-left:12px;font-weight:700"></span>
                        <script>
                        (function(){
                            var ajaxEndpoint = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                            function parseAjaxPayload(d){
                                if ( d && d.success ) {
                                    return { ok:true, text:(d.data && d.data.message) ? d.data.message : (typeof d.data === 'string' ? d.data : JSON.stringify(d.data||'')) };
                                }
                                var msg = '';
                                if ( d == null || typeof d !== 'object' ) {
                                    msg = 'Невідома відповідь: ' + String(d);
                                } else if ( d.data == null && d.success === false ) {
                                    /* типовий нестандартний JSON — показати повністю */
                                    try { msg = 'Відповідь без даних: ' + JSON.stringify(d); } catch(e){ msg = 'Порожня відповідь сервера'; }
                                } else if ( typeof d.data === 'string' ) { msg = d.data; }
                                else if ( typeof d.data === 'object' && d.data && d.data.message ) { msg = d.data.message; }
                                else { try { msg = JSON.stringify(d.data); } catch(e){ msg = String(d.data); } }
                                return { ok:false, text:msg };
                            }
                            document.getElementById('anex-test-token')?.addEventListener('click', function(){
                                var el = document.getElementById('anex-test-result');
                                el.textContent = '⏳ Перевіряємо…';
                                el.style.color = '#888';
                                fetch(ajaxEndpoint, {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                                    body: new URLSearchParams({
                                        action: 'anex_test_token',
                                        nonce: '<?php echo wp_create_nonce("anex_test_token"); ?>',
                                        _ajax_nonce: '<?php echo wp_create_nonce("anex_test_token"); ?>'
                                    })
                                }).then(function(r){
                                    return r.text().then(function(t){
                                        try { return JSON.parse(t); } catch(err){
                                            throw new Error('Сервер повернув не JSON (HTTP '+r.status+'): '+t.slice(0,160));
                                        }
                                    });
                                }).then(function(d){
                                    var out = parseAjaxPayload(d);
                                    if(out.ok){ el.textContent = out.text; el.style.color = 'green'; }
                                    else { el.textContent = '❌ Помилка: '+out.text; el.style.color = 'red'; }
                                }).catch(function(e){ el.textContent='❌ '+e.message; el.style.color='red'; });
                            });
                        })();
                        </script>
                    </td>
                </tr>
            </table>

            <h2>🏢 Інформація про агентство</h2>
            <table class="form-table">
                <?php
                $agency_fields = [
                    'anex_agency_name'     => [ 'Назва агентства',  'Anex Tour Львів' ],
                    'anex_agency_phone'    => [ 'Телефон',          '+380979451781' ],
                    'anex_agency_address'  => [ 'Адреса',           'Львів, вул. Героїв УПА, 6' ],
                    'anex_agency_viber'    => [ 'Viber посилання',  'viber://chat?number=%2B380979451781' ],
                    'anex_agency_telegram' => [ 'Telegram посилання','https://t.me/your_manager' ],
                    'anex_consult_avatar_url' => [ 'Фото консультанта (URL, https)', 'https://…' ],
                    'anex_notify_email'    => [ 'Email для заявок', get_option( 'admin_email' ) ],
                ];
                foreach ( $agency_fields as $key => [ $label, $placeholder ] ):
                ?>
                <tr>
                    <th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <input type="text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
                               value="<?php echo esc_attr( get_option( $key, '' ) ); ?>"
                               placeholder="<?php echo esc_attr( $placeholder ); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <h2>📦 Модулі</h2>
            <table class="form-table">
                <tr>
                    <th>Каталог готелів</th>
                    <td>
                        <label>
                            <input type="checkbox" name="anex_module_hotel_catalog" value="1"
                                <?php checked( get_option( 'anex_module_hotel_catalog', '1' ), '1' ); ?>>
                            Увімкнено
                        </label>
                        <p class="description">
                            Legacy-сторінка: <code>[anex_hotel_catalog]</code><br>
                            Slug сторінки з повним каталогом (результати пошуку):
                            <input type="text" name="anex_slug_hotel_catalog"
                                   value="<?php echo esc_attr( get_option( 'anex_slug_hotel_catalog', 'populyarni-goteli' ) ); ?>"
                                   style="width:200px">
                            <span class="description"><br>
                                Якщо результати на <code>/katalog/</code>, вкажіть тут <strong>katalog</strong> або покладіться на авто: існуюча сторінка <code>katalog</code> має пріоритет при редіректі з головної.<br>
                                Примусово: <code>[anex_search target="<?php echo esc_attr( home_url( '/katalog/' ) ); ?>"]</code>
                            </span>
                        </p>
                        <p class="description" style="margin-top:12px">
                            Сторінка картки готелю (Elementor + шорткоди <code>[anex_hotel_detail_bootstrap]</code> тощо), куди відкривається турист при кліку з каталогу.
                            Slug (лише латиниця):
                            <input type="text" name="anex_slug_hotel_detail"
                                   value="<?php echo esc_attr( get_option( 'anex_slug_hotel_detail', '' ) ); ?>"
                                   style="width:200px" placeholder="hotel">
                            <br><span class="description">Порожньо = спроба знайти сторінку з <code>[anex_hotel_detail_bootstrap]</code> автоматично; якщо не вдалося — картка на URL каталогу. Якщо вказано slug (напр. <code>hotel</code>) або повний URL сторінки — посилання <code>/hotel/?tour_key=…&amp;hotel_id=…</code>.</span>
                        </p>
                        <p class="description" style="margin-top:12px">
                            Сторінка картки екскурсійного туру (Elementor + шорткоди <code>[anex_excursion_detail_*]</code>).
                            Slug (лише латиниця):
                            <input type="text" name="anex_slug_excursion_detail"
                                   value="<?php echo esc_attr( get_option( 'anex_slug_excursion_detail', '' ) ); ?>"
                                   style="width:200px" placeholder="excursion-tour">
                            <br><span class="description">Порожньо = спроба знайти сторінку з <code>[anex_excursion_detail_bootstrap]</code> автоматично; якщо не вдалося — відкриття на сторінці екскурсійного каталогу.</span>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Пошук турів (лабораторія)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="anex_module_tour_lab" value="1"
                                <?php checked( get_option( 'anex_module_tour_lab', '1' ), '1' ); ?>>
                            Увімкнено
                        </label>
                        <p class="description">
                            Legacy-сторінка: <code>[anex_tour_lab]</code><br>
                            Slug сторінки:
                            <input type="text" name="anex_slug_tour_lab"
                                   value="<?php echo esc_attr( get_option( 'anex_slug_tour_lab', 'ittour-lab' ) ); ?>"
                                   style="width:200px">
                        </p>
                    </td>
                </tr>
            </table>

            <h2>📖 Як використовувати в Elementor</h2>
            <div style="background:#f0f6ff;border-left:4px solid #1a5dc8;padding:16px;border-radius:4px;max-width:700px">
                <ol style="margin:0;padding-left:20px;line-height:2">
                    <li>Задайте API Token вище та збережіть.</li>
                    <li>Заповніть інформацію про агентство (телефон, адреса тощо).</li>
                    <li>Створіть сторінку → Elementor → оберіть шаблон <strong>Canvas</strong>.</li>
                    <li>Додайте віджет <strong>Shortcode</strong> та вставте потрібний шорткод.</li>
                </ol>
            </div>

            <h2>🧩 Всі шорткоди</h2>
            <table class="widefat striped" style="max-width:750px">
                <thead><tr><th>Шорткод</th><th>Опис</th><th>Де використовувати</th></tr></thead>
                <tbody>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_search]</code></td>
                        <td>🔍 Форма пошуку туру як у каталозі. Без блоку результатів веде на /katalog/</td>
                        <td>Окремий Elementor Shortcode-блок або верх сторінки каталогу</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_excursion_search]</code></td>
                        <td>🚌 Форма пошуку екскурсій (режим excursion примусово)</td>
                        <td>Сторінка «Екскурсійні тури», верх сторінки</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_excurs_search]</code></td>
                        <td>Аліас для <code>[anex_excursion_search]</code></td>
                        <td>Можна використовувати замість основного шорткоду</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_tour_results]</code></td>
                        <td>Фільтри + права частина результатів пошуку (тури та екскурсії)</td>
                        <td>Окремий Elementor Shortcode-блок під формою пошуку</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_tour_filters]</code></td>
                        <td>Тільки ліва панель фільтрів</td>
                        <td>Коли фільтри потрібно поставити окремо</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_tour_results_main]</code></td>
                        <td>Тільки права частина результатів</td>
                        <td>Коли список результатів потрібно поставити окремо</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_hot_tours]</code></td>
                        <td>🔥 Гарячі тури — каруселі готелів по країнах</td>
                        <td>Будь-яка сторінка, домашня, лендінг</td>
                    </tr>
                    <tr style="background:#f0f6ff">
                        <td><code>[anex_directions]</code></td>
                        <td>🗺 Популярні напрямки — сітка країн з цінами</td>
                        <td>Будь-яка сторінка, домашня, лендінг</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="font-weight:700">Картка готелю (збірка сторінки в Elementor по блоках)</td>
                    </tr>
                    <tr>
                        <td><code>[anex_hotel_detail_bootstrap]</code></td>
                        <td>Обовʼязковий технічний bootstrap (додати 1 раз на сторінку)</td>
                        <td>Окремий shortcode-блок, можна внизу сторінки</td>
                    </tr>
                    <tr><td><code>[anex_hotel_detail_head]</code></td><td>Хедер готелю: title + gallery + best offer</td><td>Верх шаблону сторінки готелю</td></tr>
                    <tr><td><code>[anex_hotel_detail_info]</code></td><td>Інформація про готель</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_prices]</code></td><td>Таблиця цін і форма пошуку по туру</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_calendar]</code></td><td>Календар низьких цін</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_facilities]</code></td><td>Послуги та зручності</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_reviews]</code></td><td>Відгуки</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_similar_price]</code></td><td>Схожі готелі за ціною</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_similar_beach]</code></td><td>Схожі готелі за пляжем</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_hotel_detail_compact]</code></td><td>Компактна збірка всієї картки готелю одним шорткодом</td><td>Коли треба мінімум блоків в Elementor</td></tr>
                    <tr>
                        <td colspan="3" style="font-weight:700">Картка екскурсійного туру (збірка сторінки в Elementor по блоках)</td>
                    </tr>
                    <tr><td><code>[anex_excursion_detail_bootstrap]</code></td><td>Технічний bootstrap: сітка двох колонок (контент + бічна панель), слоти переносяться скриптом</td><td>Додати 1 раз на сторінку</td></tr>
                    <tr><td><code>[anex_excursion_detail_head]</code></td><td>Хедер: хлібні крихти, назва, код туру, маршрут, теги</td><td>Верх шаблону</td></tr>
                    <tr><td><code>[anex_excursion_detail_info]</code></td><td>Ключова інформація по туру</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_excursion_detail_program]</code></td><td>Маршрут + програма: <code>day_detail</code> (акордеон) та/або HTML <code>description</code> з API</td><td>Основна колонка</td></tr>
                    <tr><td><code>[anex_excursion_detail_gallery]</code></td><td>Фотогалерея туру з API</td><td>Після хедера або інфо</td></tr>
                    <tr><td><code>[anex_excursion_detail_hikes]</code></td><td>Екскурсії та активності в турі</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_excursion_detail_included]</code></td><td>Включено / не включено у вартість</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_excursion_detail_dates]</code></td><td>Таблиця «Дати виїздів» з API</td><td>Окремий блок (рекомендовано після «включено»)</td></tr>
                    <tr><td><code>[anex_excursion_detail_documents]</code></td><td>Документи та памʼятка туристу</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_excursion_detail_popular]</code></td><td>Добірка турів: спочатку <code>module-excursion/search</code>, якщо порожньо — <code>showcase/hot-offers/search</code> (гарячі в тій самій країні)</td><td>Рекомендовано внизу основної колонки</td></tr>
                    <tr><td><code>[anex_excursion_detail_price]</code></td><td>Ціна, дата, кнопка заявки</td><td>Окремий блок</td></tr>
                    <tr><td><code>[anex_excursion_detail_compact]</code></td><td>Компактна збірка картки екскурсії одним шорткодом</td><td>Коли треба мінімум блоків в Elementor</td></tr>
                </tbody>
            </table>

            <?php submit_button( 'Зберегти налаштування' ); ?>
        </form>
    </div>
    <?php
}

/* ─────────────────────────────────────────────
   Bookings page HTML
───────────────────────────────────────────── */
function anex_admin_bookings_page(): void {
    $bookings = get_option( ITTOUR_LAB_BOOKINGS_OPTION, [] );
    if ( ! is_array( $bookings ) ) $bookings = [];

    // Export CSV
    if ( isset( $_GET['export'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'anex_export' );
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="bookings-' . date( 'Y-m-d' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'ID', 'Дата', 'Імʼя', 'Телефон', 'Email', 'Тур', 'Ціна', 'Коментар' ] );
        foreach ( $bookings as $b ) {
            fputcsv( $out, [ $b['id'] ?? '', $b['created_at'] ?? '', $b['name'] ?? '', $b['phone'] ?? '', $b['email'] ?? '', $b['tour_title'] ?? '', $b['tour_price'] ?? '', $b['message'] ?? '' ] );
        }
        fclose( $out );
        exit;
    }
    ?>
    <div class="wrap">
        <h1>📋 Заявки на тури <span style="font-size:14px;color:#888">(<?php echo count( $bookings ); ?>)</span></h1>
        <p>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=anex-tour-bookings&export=1' ), 'anex_export' ) ); ?>"
               class="button">⬇ Завантажити CSV</a>
        </p>
        <?php if ( empty( $bookings ) ): ?>
            <p>Заявок ще немає.</p>
        <?php else: ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Дата</th><th>Імʼя</th><th>Телефон</th><th>Email</th>
                    <th>Тур</th><th>Дата виїзду</th><th>Ціна</th><th>Коментар</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $bookings as $b ): ?>
                <tr>
                    <td><?php echo esc_html( $b['created_at'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $b['name'] ?? '' ); ?></td>
                    <td><a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $b['phone'] ?? '' ) ); ?>"><?php echo esc_html( $b['phone'] ?? '' ); ?></a></td>
                    <td><?php echo esc_html( $b['email'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $b['tour_title'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $b['tour_date'] ?? '' ); ?></td>
                    <td style="font-weight:700;color:#1a5dc8"><?php echo esc_html( $b['tour_price'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $b['message'] ?? '' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}
