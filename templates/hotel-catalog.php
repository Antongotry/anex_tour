<?php
/**
 * Окрема сторінка з віджетом популярних готелів на дозволених методах IT-Tour API.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Embed mode: called via [anex_hotel_catalog] shortcode
$_anex_embed = defined('ANEX_EMBED_MODE') && ANEX_EMBED_MODE;
if (!$_anex_embed) {
    status_header(200);
    nocache_headers();
}

$is_preview_user = true; // сторінка відкрита для всіх

// Заглушка вимкнена
if (false) {
    ?><!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>В розробці | <?php bloginfo('name'); ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap');

            :root { --accent: #1a5dc8; --bg: #f5f7fb; --text: #1a2233; --muted:#6d7690; --line:#dce4f2; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                padding: 28px 14px;
                background:
                    radial-gradient(circle at top left, rgba(26, 93, 200, 0.12), transparent 28rem),
                    radial-gradient(circle at top right, rgba(80, 148, 255, 0.14), transparent 24rem),
                    linear-gradient(180deg, #fbfcff 0%, var(--bg) 100%);
                color: var(--text);
                font: 15px/1.55 "Montserrat", system-ui, -apple-system, "Segoe UI", sans-serif;
            }
            .card {
                width: min(560px, 100%);
                padding: 22px 20px;
                border: 1px solid rgba(220, 228, 242, 0.9);
                border-radius: 20px;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: 0 22px 48px rgba(20, 41, 84, 0.08);
                text-align: center;
            }
            .pill {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 8px 12px;
                border-radius: 999px;
                border: 1px solid rgba(26, 93, 200, 0.14);
                background: rgba(26, 93, 200, 0.06);
                color: var(--accent);
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            h1 { margin: 16px 0 10px; font-size: 28px; letter-spacing: 0; }
            p { margin: 0; color: var(--muted); font-size: 15px; }
            a {
                display: inline-flex;
                margin-top: 16px;
                padding: 12px 16px;
                border-radius: 14px;
                border: 1px solid rgba(26, 93, 200, 0.14);
                background: #fff;
                color: var(--accent);
                font-weight: 800;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <main class="card" role="main">
            <span class="pill">В розробці</span>
            <h1>Скоро буде</h1>
            <p>Ми оновлюємо сторінку та готуємо новий функціонал підбору турів. Заходьте трохи пізніше.</p>
            <a href="<?php echo esc_url(home_url('/' . ITTOUR_HOTELS_WIDGET_PAGE_SLUG . '/')); ?>">На головну</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$ajax_url = admin_url('admin-ajax.php');
$nonce    = wp_create_nonce('ittour_lab_public');
$search_v2_endpoint = rest_url( 'anex/v1/search' );
$search_v2_rest_nonce = wp_create_nonce( 'wp_rest' );
$search_v2_shadow_enabled = current_user_can( 'manage_options' );
$detail_tour_key = isset($_GET['tour_key']) ? sanitize_text_field(wp_unslash((string) $_GET['tour_key'])) : '';
$detail_hotel_id = isset($_GET['hotel_id']) ? sanitize_text_field(wp_unslash((string) $_GET['hotel_id'])) : '';
$detail_back_url = remove_query_arg(['tour_key', 'hotel_id'], get_permalink());
$catalog_base_url       = function_exists('anex_get_catalog_page_permalink') ? anex_get_catalog_page_permalink([]) : $detail_back_url;
$hotel_detail_nav_base  = function_exists('anex_get_hotel_detail_nav_base_url') ? anex_get_hotel_detail_nav_base_url() : $detail_back_url;
$excursion_detail_nav_base = function_exists('anex_get_excursion_detail_nav_base_url') ? anex_get_excursion_detail_nav_base_url() : $catalog_base_url;
$site_home_url          = home_url('/');
$anex_agency_telegram   = (string) ( get_option( 'anex_agency_telegram' ) ?: 'https://t.me/' );
$anex_agency_viber      = (string) ( get_option( 'anex_agency_viber' ) ?: 'viber://chat?number=%2B380979451781' );
// Preset search params — used to pre-select country before page renders.
// All other params (from/d1/d2/n1/n2) are read directly from URL by JS readPopularSearchFromUrl().
$preset_country = '';
if ( isset( $_GET['country_id'] ) || isset( $_GET['country'] ) ) {
    $preset_country = sanitize_text_field( wp_unslash( (string) ( $_GET['country_id'] ?? $_GET['country'] ?? '' ) ) );
}
$anex_catalog_lite = function_exists( 'anex_is_katalog_landing_page' ) && anex_is_katalog_landing_page();
// Resolve asset URLs: plugin bundle takes priority over legacy mu-plugin path
$about_image_url = defined('ANEX_PLUGIN_URL')
    ? ANEX_PLUGIN_URL . 'assets/about-travel-service.jpg'
    : content_url('mu-plugins/ittour-lab/about-travel-service.jpg');
$hero_video_url = defined('ANEX_PLUGIN_URL')
    ? ANEX_PLUGIN_URL . 'assets/hero-ocean.mp4'
    : content_url('1502239_Airplane_Air_Vehicle_1920x1080.mp4');
$featured_country_fallbacks = [
    '318' => 'Туреччина',
    '338' => 'Єгипет',
    '39'  => 'Болгарія',
    '372' => 'Греція',
    '16'  => 'ОАЕ',
    '434' => 'Чорногорія',
];
$all_countries = [];
$featured_countries = [];

if (function_exists('ittour_lab_api_fetch')) {
    $params_response = ittour_lab_api_fetch('module/params', [], 'uk');
    $images_response = ittour_lab_api_fetch('dictionary/country-images', [], 'uk');

    $country_names = [];
    $country_images = [];

    if (
        !is_wp_error($params_response)
        && ($params_response['code'] ?? 500) < 300
        && is_array($params_response['data'] ?? null)
        && empty($params_response['data']['error'])
        && !empty($params_response['data']['countries'])
        && is_array($params_response['data']['countries'])
    ) {
        foreach ($params_response['data']['countries'] as $country) {
            $country_id = (string) ($country['id'] ?? '');
            if ($country_id === '') {
                continue;
            }
            $country_names[$country_id] = (string) ($country['name'] ?? $country_id);
            $all_countries[] = [
                'id'    => $country_id,
                'name'  => (string) ($country['name'] ?? $country_id),
                'image' => '',
            ];
        }
    }

    if (
        !is_wp_error($images_response)
        && ($images_response['code'] ?? 500) < 300
        && is_array($images_response['data'] ?? null)
        && empty($images_response['data']['error'])
    ) {
        foreach ($images_response['data'] as $country_image) {
            $country_id = (string) ($country_image['id'] ?? '');
            $images = is_array($country_image['images'] ?? null) ? $country_image['images'] : [];
            if ($country_id === '' || !$images) {
                continue;
            }
            $country_images[$country_id] = (string) (($images[0]['full'] ?? $images[0]['thumb'] ?? ''));
        }
    }

    foreach ($featured_country_fallbacks as $country_id => $country_name) {
        $featured_countries[] = [
            'id'    => $country_id,
            'name'  => $country_names[$country_id] ?? $country_name,
            'image' => $country_images[$country_id] ?? '',
        ];
    }

    if ($all_countries) {
        foreach ($all_countries as &$country_item) {
            $country_item['image'] = $country_images[$country_item['id']] ?? '';
        }
        unset($country_item);
    }
}

if (!$all_countries) {
    foreach ($featured_country_fallbacks as $country_id => $country_name) {
        $all_countries[] = [
            'id'    => $country_id,
            'name'  => $country_name,
            'image' => '',
        ];
    }
}

if (!$featured_countries) {
    foreach ($featured_country_fallbacks as $country_id => $country_name) {
        $featured_countries[] = [
            'id'    => $country_id,
            'name'  => $country_name,
            'image' => '',
        ];
    }
}

$hero_video_poster = '';
if (!empty($featured_countries[0]['image'])) {
    $hero_video_poster = (string) $featured_countries[0]['image'];
}
if ($hero_video_poster === '') {
    $hero_video_poster = $about_image_url;
}
?>
<?php if (!$_anex_embed): ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Популярні готелі | <?php bloginfo('name'); ?></title>
<?php endif; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap');

        :root {
            --accent: #1a5dc8;
            --accent-strong: #1348a8;
            --accent-soft: rgba(26, 93, 200, 0.08);
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #1a2233;
            --muted: #6d7690;
            --line: #dce4f2;
            --star: #f6b73c;
            --shadow: 0 22px 48px rgba(20, 41, 84, 0.08);
            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 16px;
            --radius-sm: 12px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
            background: #f4f6fb;
            color: var(--text);
            font: 15px/1.55 "Montserrat", system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .page-shell.anex-embed-root {
            font-family: "Montserrat", system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        a {
            color: inherit;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .page-shell {
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 0 14px 72px;
        }

        body.menu-open {
            overflow: hidden;
        }

        .hero-stage {
            position: relative;
            overflow: hidden;
            min-height: 760px;
            margin-bottom: 34px;
            margin-inline: calc(50% - 50vw);
            border: 1px solid rgba(220, 228, 242, 0.34);
            border-inline: 0;
            border-radius: 0 0 32px 32px;
            background: #0a1c40;
            box-shadow: 0 22px 48px rgba(18, 37, 73, 0.16);
        }

        .hero-stage::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(7, 18, 42, 0.82) 0%, rgba(7, 18, 42, 0.54) 42%, rgba(7, 18, 42, 0.22) 100%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.1), transparent 34%);
            z-index: 1;
            pointer-events: none;
        }

        .hero-video {
            position: absolute;
            inset: 0;
        }

        .hero-video video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 9000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 20px;
            transition: background-color 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease, color 0.22s ease;
        }

        body.header-scrolled .site-header {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 1px solid rgba(220, 228, 242, 0.92);
            box-shadow: 0 12px 26px rgba(17, 34, 69, 0.08);
            backdrop-filter: blur(16px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            color: #ffffff;
            transition: color 0.22s ease;
        }

        .brand-logo {
            display: grid;
            gap: 1px;
            min-width: 166px;
            line-height: 1;
        }

        .brand-logo-main {
            color: #f31624;
            font-size: 42px;
            font-weight: 800;
            line-height: 0.82;
            letter-spacing: 0;
            text-transform: lowercase;
        }

        .brand-logo-sub {
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            transition: color 0.22s ease;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.24), rgba(255, 255, 255, 0.08));
            border: 1px solid rgba(255, 255, 255, 0.16);
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.04em;
            backdrop-filter: blur(10px);
        }

        .brand-text {
            display: grid;
        }

        .brand-text strong {
            font-size: 17px;
            letter-spacing: 0;
        }

        .brand-text span {
            color: rgba(236, 242, 255, 0.72);
            font-size: 12px;
            font-weight: 600;
            transition: color 0.22s ease;
        }

        .header-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin-left: auto;
            margin-right: 18px;
        }

        .header-nav a {
            color: rgba(240, 244, 255, 0.84);
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.18s ease, opacity 0.18s ease;
        }

        .header-nav a:hover {
            color: #ffffff;
        }

        .header-cta,
        .menu-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 12px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            backdrop-filter: blur(12px);
            transition: background-color 0.22s ease, color 0.22s ease, border-color 0.22s ease;
        }

        .menu-toggle {
            display: none;
            cursor: pointer;
        }

        body.header-scrolled .brand,
        body.header-scrolled .header-nav a,
        body.header-scrolled .header-cta,
        body.header-scrolled .menu-toggle {
            color: var(--text);
        }

        body.header-scrolled .brand-logo-sub {
            color: #293b8f;
        }

        body.header-scrolled .brand-mark,
        body.header-scrolled .header-cta,
        body.header-scrolled .menu-toggle {
            border-color: rgba(26, 93, 200, 0.12);
            background: rgba(26, 93, 200, 0.05);
        }

        body.header-scrolled .brand-text span {
            color: #61708d;
        }

        .hero-layout {
            position: relative;
            z-index: 2;
            display: grid;
            align-content: space-between;
            min-height: calc(760px - 70px);
            gap: 26px;
            padding: 118px 28px 26px;
        }

        .hero-copy {
            display: grid;
            gap: 16px;
            max-width: 680px;
        }

        .hero-copy .eyebrow {
            border-color: rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
        }

        .hero-copy h1 {
            margin: 0;
            max-width: 680px;
            color: #ffffff;
            font-size: clamp(38px, 5.2vw, 64px);
            line-height: 0.96;
            letter-spacing: 0;
            text-wrap: balance;
        }

        .hero-copy p {
            max-width: 560px;
            margin: 0;
            color: rgba(235, 241, 255, 0.84);
            font-size: 17px;
            line-height: 1.55;
        }

        .hero-search-card {
            width: min(720px, 100%);
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.76);
            backdrop-filter: blur(22px) saturate(1.12);
        }

        .hero-search-card--catalog {
            width: min(920px, 100%);
        }

        .hero-search-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .hero-search-title {
            display: grid;
            gap: 4px;
        }

        .hero-search-title strong {
            font-size: 18px;
            letter-spacing: 0;
        }

        .hero-search-title span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .hero-search-card p {
            margin: 0;
            color: #53617d;
            font-size: 14px;
        }

        .hero-search-card .country-switcher {
            margin-bottom: 0;
        }

        .hero-search-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(220px, 0.85fr);
            gap: 12px;
        }

        .search-field {
            display: grid;
            gap: 6px;
        }

        .search-field label {
            color: #5d6b87;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .search-field select {
            width: 100%;
            min-height: 50px;
            padding: 12px 14px;
            border: 1px solid rgba(26, 93, 200, 0.12);
            border-radius: 14px;
            background: #ffffff;
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
            appearance: none;
        }

        .hero-search-caption {
            color: #61708d;
            font-size: 12px;
            font-weight: 700;
        }

        .hero-search-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .hero-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 18px;
            border: 0;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent) 0%, #1347ad 100%);
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.18s ease, opacity 0.18s ease;
        }

        .hero-primary:hover {
            transform: translateY(-1px);
        }

        /* Переваги внизу херо (десктоп / планшет) */
        .hero-benefits--in-hero {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: auto;
        }

        .hero-benefits--in-hero .hero-benefit {
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(16px);
            color: #ffffff;
        }

        .hero-benefits--in-hero .hero-benefit span {
            display: block;
            margin-bottom: 6px;
            color: rgba(235, 242, 255, 0.7);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero-benefits--in-hero .hero-benefit strong {
            display: block;
            margin-bottom: 8px;
            font-size: 28px;
            line-height: 0.96;
            letter-spacing: 0;
        }

        .hero-benefits--in-hero .hero-benefit p {
            margin: 0;
            color: rgba(240, 244, 255, 0.82);
            font-size: 14px;
        }

        /* Та сама сітка під херо лише на телефоні */
        .hero-benefits-section {
            display: none;
            margin: 0 0 20px;
            padding: 16px 12px 20px;
            border-radius: 18px;
            background: #eef2f8;
            border: 1px solid rgba(220, 228, 242, 0.95);
        }

        .hero-benefits--below {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            max-width: 1180px;
            margin: 0 auto;
        }

        .hero-benefits--below .hero-benefit {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid rgba(26, 93, 200, 0.1);
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(17, 34, 69, 0.06);
        }

        .hero-benefits--below .hero-benefit span {
            display: block;
            margin-bottom: 4px;
            color: #61708d;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .hero-benefits--below .hero-benefit strong {
            display: block;
            margin-bottom: 6px;
            font-size: 24px;
            letter-spacing: 0;
            color: var(--text);
        }

        .hero-benefits--below .hero-benefit p {
            margin: 0;
            color: #53617d;
            font-size: 14px;
            line-height: 1.45;
        }

        .mobile-menu {
            position: fixed;
            inset: 0;
            z-index: 10000;
            display: flex;
            justify-content: flex-end;
            padding-left: 56px;
            background: rgba(9, 18, 38, 0.46);
            backdrop-filter: blur(8px);
        }

        .mobile-menu[hidden] {
            display: none !important;
        }

        .mobile-menu-panel {
            width: min(92vw, 380px);
            height: 100%;
            display: grid;
            align-content: start;
            gap: 22px;
            padding: 22px 20px 26px;
            background: #ffffff;
            box-shadow: -22px 0 44px rgba(15, 31, 63, 0.16);
        }

        .mobile-menu-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .menu-close {
            width: 42px;
            height: 42px;
            border: 1px solid rgba(26, 93, 200, 0.12);
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.06);
            color: var(--accent);
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
        }

        .mobile-nav {
            display: grid;
            gap: 10px;
        }

        .mobile-nav a {
            display: block;
            padding: 14px 16px;
            border-radius: 16px;
            background: rgba(26, 93, 200, 0.04);
            color: var(--text);
            font-size: 15px;
            font-weight: 800;
            text-decoration: none;
        }

        .mobile-card {
            display: grid;
            gap: 10px;
            padding: 16px;
            border: 1px solid rgba(220, 228, 242, 0.84);
            border-radius: 18px;
            background: #f9fbff;
        }

        .mobile-card strong {
            font-size: 15px;
            letter-spacing: 0;
        }

        .mobile-card a,
        .mobile-card span {
            color: #4c5873;
            font-size: 14px;
            text-decoration: none;
        }

        .mobile-socials {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
            padding: 0 14px;
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.06);
            color: var(--accent);
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .mobile-note {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .site-footer {
            position: relative;
            display: grid;
            grid-template-columns: minmax(280px, 1.25fr) minmax(0, 1.55fr);
            gap: 34px;
            margin: 44px -14px -72px;
            padding: 42px max(34px, calc((100% - 1180px) / 2 + 34px)) 32px;
            border: 0;
            border-radius: 0;
            background: #071a3d;
            color: #ffffff;
            box-shadow: none;
        }

        .site-footer > * {
            position: relative;
        }

        .footer-brand {
            display: grid;
            align-content: start;
            gap: 18px;
        }

        .site-footer .brand {
            color: #ffffff;
        }

        .site-footer .brand-logo-sub {
            color: #ffffff;
        }

        .site-footer .brand-mark {
            border-color: rgba(26, 93, 200, 0.12);
            background: rgba(26, 93, 200, 0.05);
        }

        .site-footer .brand-text span {
            color: var(--muted);
        }

        .footer-brand p,
        .footer-col p {
            margin: 0;
            color: rgba(238, 243, 255, 0.74);
            font-size: 14px;
            line-height: 1.65;
        }

        .footer-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }

        .footer-cta a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
        }

        .footer-cta-primary {
            background: #f31624;
            color: #ffffff;
        }

        .footer-cta-secondary {
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .footer-links {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
        }

        .footer-col {
            display: grid;
            align-content: start;
            gap: 11px;
            padding-top: 4px;
        }

        .footer-col strong {
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        .footer-col a {
            color: rgba(238, 243, 255, 0.8);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.18s ease, transform 0.18s ease;
        }

        .footer-col a:hover {
            color: #ffffff;
            transform: translateX(2px);
        }

        .footer-bottom {
            grid-column: 1 / -1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            color: rgba(238, 243, 255, 0.68);
            font-size: 13px;
        }

        .footer-socials {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .footer-socials a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0 12px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.07);
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
        }

        .social-link svg,
        .footer-socials svg {
            width: 19px;
            height: 19px;
            display: block;
            fill: currentColor;
        }

        .hero {
            display: grid;
            gap: 18px;
            margin-bottom: 28px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: fit-content;
            padding: 8px 14px;
            border-radius: 999px;
            border: 1px solid rgba(26, 93, 200, 0.15);
            background: rgba(255, 255, 255, 0.75);
            color: var(--accent);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            backdrop-filter: blur(12px);
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(32px, 6vw, 58px);
            line-height: 0.94;
            letter-spacing: 0;
        }

        .hero p {
            max-width: 820px;
            margin: 0;
            color: var(--muted);
            font-size: 17px;
        }

        .about-section {
            display: grid;
            gap: 22px;
            width: 100%;
            max-width: none;
            margin: -10px 0 28px;
            position: relative;
            z-index: 2;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .stat-card {
            padding: 22px 18px;
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.82);
            text-align: center;
            backdrop-filter: blur(10px);
        }

        .stat-card strong {
            display: block;
            margin-bottom: 8px;
            color: var(--accent);
            font-size: clamp(34px, 5vw, 56px);
            line-height: 0.92;
            letter-spacing: 0;
        }

        .stat-card span {
            color: #dd2048;
            font-size: 16px;
            font-weight: 700;
        }

        .about-card {
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(240px, 34%);
            grid-template-rows: auto 1fr;
            column-gap: clamp(22px, 3.2vw, 40px);
            row-gap: 22px;
            align-items: start;
            padding: clamp(24px, 3.2vw, 36px);
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: var(--radius-xl);
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 251, 255, 0.94) 55%, rgba(255, 255, 255, 0.96) 100%);
            box-shadow: var(--shadow);
        }

        .about-copy {
            grid-column: 1;
            grid-row: 1;
            display: grid;
            gap: 12px;
        }

        .about-copy h2 {
            margin: 0;
            color: var(--text);
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.06;
            letter-spacing: -0.02em;
        }

        .about-copy p {
            margin: 0;
            color: var(--muted);
            font-size: clamp(16px, 1.65vw, 18px);
            line-height: 1.55;
            max-width: 40rem;
        }

        .about-points {
            grid-column: 1;
            grid-row: 2;
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            min-height: 0;
        }

        .about-point {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
            gap: clamp(16px, 2.2vw, 22px);
            margin: 0;
            padding: clamp(18px, 2.2vw, 24px) clamp(18px, 2.5vw, 26px);
            border: 1px solid rgba(220, 228, 242, 0.95);
            border-radius: var(--radius-lg);
            background: var(--card);
            color: var(--text);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset;
            opacity: 0.68;
            transform: translateY(12px);
            filter: saturate(0.92);
            transition:
                opacity 0.5s cubic-bezier(0.22, 1, 0.36, 1),
                transform 0.5s cubic-bezier(0.22, 1, 0.36, 1),
                border-color 0.35s ease,
                box-shadow 0.35s ease,
                filter 0.35s ease;
        }

        .about-point.is-active {
            opacity: 1;
            transform: translateY(0);
            filter: saturate(1);
            border-color: rgba(26, 93, 200, 0.2);
            box-shadow:
                inset 3px 0 0 0 var(--accent),
                0 16px 36px rgba(17, 38, 77, 0.09);
        }

        .about-point:hover {
            border-color: rgba(26, 93, 200, 0.14);
        }

        .about-point-icon {
            flex-shrink: 0;
            width: clamp(56px, 7vw, 72px);
            height: clamp(56px, 7vw, 72px);
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: linear-gradient(145deg, var(--accent) 0%, #1347ad 100%);
            color: #ffffff;
            box-shadow: 0 8px 22px rgba(26, 93, 200, 0.3);
        }

        .about-point-icon svg {
            width: clamp(28px, 3.5vw, 36px);
            height: clamp(28px, 3.5vw, 36px);
        }

        .about-point-text {
            margin: 0;
            color: #2f3c52;
            font-size: clamp(16px, 1.85vw, 19px);
            font-weight: 600;
            line-height: 1.48;
        }

        @media (prefers-reduced-motion: reduce) {
            .about-point {
                opacity: 1;
                transform: none;
                filter: none;
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }
        }

        .about-visual {
            grid-column: 2;
            grid-row: 1 / span 2;
            position: relative;
            overflow: hidden;
            width: 100%;
            min-height: 0;
            margin: 0;
            align-self: stretch;
            display: block;
            border-radius: var(--radius-lg);
            background: linear-gradient(160deg, rgba(26, 93, 200, 0.1), rgba(10, 28, 64, 0.06));
            box-shadow:
                inset 0 0 0 1px rgba(26, 93, 200, 0.08),
                0 18px 40px rgba(12, 29, 62, 0.1);
        }

        .about-visual img {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 0;
            object-fit: cover;
            object-position: center 30%;
        }

        .about-badge {
            position: absolute;
            left: 14px;
            right: 14px;
            bottom: 14px;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 14px 16px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 10px 28px rgba(12, 29, 62, 0.14);
            backdrop-filter: blur(10px);
        }

        .about-badge strong {
            color: var(--accent);
            font-size: 15px;
            line-height: 1.2;
            letter-spacing: 0;
        }

        .about-badge span {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            line-height: 1.35;
        }

        .widget-frame {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .widget-frame::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(135deg, rgba(26, 93, 200, 0.045), transparent 22%),
                linear-gradient(180deg, rgba(255, 255, 255, 0.28), transparent 45%);
            pointer-events: none;
        }

        .widget-inner {
            position: relative;
            padding: 34px 34px 36px;
        }

        .toolbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 26px;
        }

        .toolbar-copy {
            display: grid;
            gap: 8px;
        }

        .toolbar-copy h2 {
            margin: 0;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1;
            letter-spacing: 0;
            color: #003087;
            font-weight: 800;
        }

        .toolbar-copy p {
            margin: 0;
            color: var(--muted);
        }

        .meta-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(26, 93, 200, 0.14);
            background: var(--card);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .meta-pill strong {
            color: var(--accent);
        }

        .country-switcher {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .country-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 46px;
            padding: 11px 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            background: rgba(26, 93, 200, 0.055);
            color: var(--accent);
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.18s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
        }

        .country-pill:hover {
            transform: translateY(-1px);
            background: rgba(26, 93, 200, 0.09);
        }

        .country-pill.active {
            border-color: rgba(26, 93, 200, 0.18);
            background: var(--accent);
            color: #ffffff;
            box-shadow: 0 10px 24px rgba(26, 93, 200, 0.18);
        }

        .carousel-shell {
            display: grid;
            gap: 14px;
        }

        .carousel-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Country showcase (offers-section) */
        /* Country showcase з табами */
        .country-showcase {
            display: flex;
            flex-direction: column;
        }

        .showcase-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
        }

        .showcase-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            cursor: pointer;
            transition: background 0.18s, color 0.18s, border-color 0.18s, box-shadow 0.18s;
        }

        .showcase-tab:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .showcase-tab.is-active {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 8px 20px rgba(26, 93, 200, 0.2);
        }

        .showcase-tab .tab-price {
            font-size: 12px;
            font-weight: 700;
            opacity: 0.75;
        }

        .showcase-tab.is-active .tab-price {
            opacity: 0.88;
            color: rgba(255,255,255,0.9);
        }

        .showcase-panel { display: none; }
        .showcase-panel.is-active { display: block; }

        .showcase-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        @media (max-width: 1180px) { .showcase-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 860px)  { .showcase-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px)  { .showcase-cards { grid-template-columns: 1fr; } }

        .showcase-skeleton {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        @media (max-width: 860px)  { .showcase-skeleton { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        .swipe-note {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.06);
            color: var(--accent);
            font-size: 13px;
            font-weight: 700;
        }

        .swipe-note::after {
            content: "→";
            font-size: 16px;
            animation: swipe-hint 1.3s ease-in-out infinite;
        }

        .carousel-track {
            display: flex;
            gap: 18px;
            overflow-x: auto;
            padding: 4px 2px 8px;
            scroll-snap-type: x proximity;
            scrollbar-width: none;
        }

        .carousel-track::-webkit-scrollbar {
            display: none;
        }

        .hotel-card {
            flex: 0 0 calc((100% - 54px) / 4);
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--card);
            scroll-snap-align: start;
            transition: transform 0.28s ease, border-color 0.28s ease;
        }

        @media (max-width: 1180px) {
            .hotel-card { flex: 0 0 calc((100% - 36px) / 3); }
        }
        @media (max-width: 860px) {
            .hotel-card { flex: 0 0 calc((100% - 18px) / 2); }
        }
        @media (max-width: 560px) {
            .hotel-card { flex: 0 0 84%; }
        }

        .hotel-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
        }

        .hotel-media {
            position: relative;
            aspect-ratio: 253 / 160;
            background: linear-gradient(135deg, rgba(26, 93, 200, 0.12), rgba(26, 93, 200, 0.03));
            overflow: hidden;
        }

        .hotel-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s ease, filter 0.35s ease;
        }

        .hotel-card:hover .hotel-media img {
            transform: scale(1.045);
            filter: saturate(1.04);
        }

        .hotel-media.no-image::after {
            content: "Немає фото";
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-weight: 700;
        }

        .review-chip {
            position: absolute;
            top: 14px;
            right: 14px;
            display: inline-flex;
            align-items: stretch;
            gap: 10px;
            max-width: calc(100% - 28px);
            padding: 8px 8px 8px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 12px 24px rgba(12, 29, 62, 0.14);
            backdrop-filter: blur(10px);
        }

        .review-copy {
            min-width: 0;
        }

        .review-copy strong {
            display: block;
            font-size: 12px;
            line-height: 1.1;
        }

        .review-copy span {
            display: block;
            margin-top: 2px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.1;
        }

        .review-score {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 62px;
            padding: 0 10px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2c6bff 0%, var(--accent) 100%);
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
        }

        .hotel-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            padding: 16px 18px 18px;
        }

        .stars {
            margin-bottom: 8px;
            color: var(--star);
            font-size: 18px;
            letter-spacing: 0.12em;
        }

        .hotel-title {
            margin: 0 0 8px;
            font-size: 17px;
            line-height: 1.18;
            letter-spacing: 0;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            min-height: 2.4em;
        }

        .hotel-location {
            margin: 0 0 12px;
            color: var(--muted);
            font-size: 14px;
        }

        .hotel-meta {
            display: grid;
            gap: 6px;
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 13px;
        }

        .hotel-price {
            margin-bottom: 16px;
            font-size: 14px;
            color: var(--muted);
        }

        .hotel-price strong {
            display: block;
            margin-top: 3px;
            color: var(--text);
            font-size: 22px;
            letter-spacing: 0;
        }

        .card-action {
            margin-top: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 54px;
            padding: 14px 18px;
            border: 0;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent) 0%, #1347ad 100%);
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
            text-decoration: none;
            box-shadow: none;
            transition: transform 0.22s ease, background 0.22s ease, opacity 0.22s ease;
        }

        .card-action:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, var(--accent-strong) 0%, #1a5dc8 100%);
        }

        .card-action:active {
            transform: translateY(0);
        }

        .nav-btn {
            position: static;
            z-index: 4;
            width: 48px;
            height: 48px;
            border: 1px solid rgba(26, 93, 200, 0.12);
            border-radius: 12px;
            background: rgba(26, 93, 200, 0.05);
            color: var(--accent);
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            box-shadow: none;
            transform: none;
            transition: opacity 0.18s ease, background-color 0.18s ease, color 0.18s ease;
        }

        .nav-btn:hover:not(:disabled) {
            background: rgba(26, 93, 200, 0.11);
        }

        .nav-btn:disabled {
            opacity: 0.35;
            cursor: default;
        }

        .skeleton-row {
            display: flex;
            gap: 18px;
            width: 100%;
        }

        .skeleton-card {
            flex: 1 1 0;
            min-height: 416px;
            border-radius: 22px;
            background: linear-gradient(90deg, #eef2f9 25%, #f8fbff 37%, #eef2f9 63%);
            background-size: 400% 100%;
            animation: shimmer 1.4s ease infinite;
        }

        .empty-state,
        .error-state {
            width: 100%;
            padding: 28px;
            border-radius: 24px;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--muted);
        }

        .error-state {
            color: #9b1d2d;
            border-color: rgba(155, 29, 45, 0.16);
            background: #fff7f8;
        }

        .search-empty-wrap {
            display: grid;
            gap: 16px;
        }

        .search-empty-title {
            margin: 0;
            color: var(--text);
            font-size: 20px;
            line-height: 1.2;
        }

        .search-empty-copy {
            margin: 0;
            color: var(--muted);
        }

        .search-empty-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-empty-chip {
            min-height: 42px;
            padding: 10px 16px;
            border: 1px solid rgba(26, 93, 200, 0.18);
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.06);
            color: #003087;
            font-weight: 700;
            cursor: pointer;
        }

        .search-empty-chip:hover {
            background: rgba(26, 93, 200, 0.12);
        }

        .directions-section {
            display: grid;
            gap: 22px;
            margin-top: 28px;
            padding: 30px;
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.88);
            box-shadow: var(--shadow);
        }

        .directions-head {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: end;
        }

        .directions-head h2 {
            margin: 0 0 8px;
            font-size: clamp(28px, 3vw, 40px);
            line-height: 0.98;
            letter-spacing: 0;
        }

        .directions-head p {
            max-width: 720px;
            margin: 0;
            color: var(--muted);
            font-size: 16px;
        }

        .directions-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .direction-card {
            display: grid;
            overflow: hidden;
            border: 1px solid rgba(220, 228, 242, 0.8);
            border-radius: 18px;
            background: var(--card);
            transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
        }

        .direction-card:hover {
            transform: translateY(-3px);
            border-color: rgba(26, 93, 200, 0.18);
            box-shadow: 0 22px 38px rgba(17, 38, 77, 0.08);
        }

        .direction-media {
            position: relative;
            overflow: hidden;
            aspect-ratio: 16 / 10;
            background: linear-gradient(135deg, rgba(26, 93, 200, 0.14), rgba(26, 93, 200, 0.03));
        }

        .direction-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.45s ease;
        }

        .direction-card:hover .direction-media img {
            transform: scale(1.04);
        }

        .direction-badge {
            position: absolute;
            left: 14px;
            top: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 34px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.94);
            color: var(--accent);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.02em;
        }

        .direction-body {
            display: grid;
            gap: 14px;
            padding: 18px 18px 20px;
        }

        .direction-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .direction-top h3 {
            margin: 0;
            font-size: 28px;
            line-height: 1;
            letter-spacing: 0;
        }

        .direction-price {
            flex: 0 0 auto;
            padding: 8px 12px;
            border-radius: 12px;
            background: rgba(26, 93, 200, 0.06);
            color: var(--accent);
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .direction-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .direction-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .direction-tag {
            display: inline-flex;
            align-items: center;
            min-height: 34px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.06);
            color: var(--accent);
            font-size: 13px;
            font-weight: 700;
        }

        .direction-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 4px;
        }

        .direction-note {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .direction-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 16px;
            border: 1px solid rgba(26, 93, 200, 0.12);
            border-radius: 12px;
            background: rgba(26, 93, 200, 0.04);
            color: var(--accent);
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: background-color 0.22s ease, color 0.22s ease, transform 0.22s ease;
        }

        .direction-action:hover {
            transform: translateY(-1px);
            background: rgba(26, 93, 200, 0.1);
        }

        .direction-skeleton {
            min-height: 390px;
            border-radius: 18px;
            background: linear-gradient(90deg, #eef2f9 25%, #f8fbff 37%, #eef2f9 63%);
            background-size: 400% 100%;
            animation: shimmer 1.4s ease infinite;
        }

        .hits-section {
            display: grid;
            gap: 22px;
            margin-top: 28px;
            padding: 30px;
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: 24px;
            background: #ffffff;
            box-shadow: var(--shadow);
        }

        .hits-head {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: end;
        }

        .hits-head h2 {
            margin: 0 0 8px;
            font-size: clamp(28px, 3vw, 40px);
            line-height: 1.02;
            letter-spacing: 0;
        }

        .hits-head p {
            max-width: 720px;
            margin: 0;
            color: var(--muted);
            font-size: 16px;
        }

        .hits-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .hit-card {
            display: grid;
            align-content: space-between;
            min-height: 190px;
            padding: 18px;
            border: 1px solid rgba(26, 93, 200, 0.1);
            border-radius: 18px;
            background: #f8fbff;
            text-align: left;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
        }

        .hit-card:hover {
            transform: translateY(-2px);
            border-color: rgba(26, 93, 200, 0.2);
            background: #ffffff;
        }

        .hit-card span {
            width: fit-content;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(243, 22, 36, 0.08);
            color: #c9111d;
            font-size: 12px;
            font-weight: 800;
        }

        .hit-card strong {
            display: block;
            margin-top: 22px;
            color: var(--text);
            font-size: 22px;
            line-height: 1.08;
        }

        .hit-card p {
            margin: 10px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .tour-detail-page {
            display: none;
            width: 100%;
            max-width: none;
            margin: 0;
            padding: 18px 0 36px;
        }

        body.tour-detail-view .hero-stage,
        body.tour-detail-view .hero-benefits-section,
        body.tour-detail-view .about-section,
        body.tour-detail-view .widget-frame,
        body.tour-detail-view .directions-section,
        body.tour-detail-view .hits-section {
            display: none;
        }

        body.tour-detail-view .tour-detail-page {
            display: grid;
            gap: 20px;
        }

        body.tour-detail-view {
            --detail-heading-weight: 600;
            --detail-subheading-weight: 600;
            --detail-body-weight: 400;
            --detail-body-color: #3a455c;
            font-family: "Montserrat", system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        body.tour-detail-view .hotel-detail-shell .detail-section > h2,
        body.tour-detail-view .hotel-detail-shell .detail-section > h3,
        body.tour-detail-view .hotel-detail-title h1,
        body.tour-detail-view .best-offer-card h2,
        body.tour-detail-view .facility-group-card h3,
        body.tour-detail-view .similar-card h3,
        body.tour-detail-view .review-slide-title,
        body.tour-detail-view .advisor-cta h2,
        body.tour-detail-view .booking-benefit-card h3 {
            font-weight: var(--detail-heading-weight);
            letter-spacing: -0.02em;
        }

        body.tour-detail-view .hotel-info-copy,
        body.tour-detail-view .hotel-info-copy p,
        body.tour-detail-view .detail-note,
        body.tour-detail-view .best-offer-fact span,
        body.tour-detail-view .best-offer-included,
        body.tour-detail-view .best-offer-price span,
        body.tour-detail-view .facility-list li,
        body.tour-detail-view .review-slide p,
        body.tour-detail-view .review-slide .review-meta,
        body.tour-detail-view .similar-card p,
        body.tour-detail-view .booking-benefit-card p {
            font-weight: var(--detail-body-weight);
            color: var(--detail-body-color);
        }

        body.tour-detail-view .detail-section > h3,
        body.tour-detail-view .facility-section-subtitle {
            font-weight: var(--detail-subheading-weight);
            color: var(--muted);
        }

        /* Підпис у advisor на темному градієнті — !important щоб тема WP не перебила колір */
        body.tour-detail-view .advisor-cta > div > p {
            color: #f2f6ff !important;
            font-weight: 600;
        }

        body.tour-detail-view .hotel-detail-shell,
        body.tour-detail-view .hotel-detail-shell * {
            font-family: "Montserrat", system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        body.tour-detail-view .hotel-detail-shell .detail-section > h2,
        body.tour-detail-view .hotel-detail-shell .detail-section > h3,
        body.tour-detail-view .hotel-detail-title h1 {
            font-size: 32px !important;
            font-weight: 600 !important;
            line-height: 1.2;
        }

        body.tour-detail-view .hotel-location-line,
        body.tour-detail-view .best-offer-card h2,
        body.tour-detail-view .best-offer-fact span,
        body.tour-detail-view .best-offer-fact strong,
        body.tour-detail-view .price-filter-cell span,
        body.tour-detail-view .price-filter-cell strong,
        body.tour-detail-view .tour-price-table th,
        body.tour-detail-view .low-price-calendar th,
        body.tour-detail-view .detail-buy-button,
        body.tour-detail-view .detail-secondary-button,
        body.tour-detail-view .table-buy-button {
            font-size: 16px !important;
            font-weight: 600 !important;
        }

        body.tour-detail-view .hotel-location-line .hotel-map-cta {
            font-size: 15px !important;
            font-weight: 700 !important;
        }

        body.tour-detail-view .hotel-location-line__text {
            font-weight: 500 !important;
            color: var(--muted) !important;
        }

        body.tour-detail-view .hotel-info-copy,
        body.tour-detail-view .hotel-info-copy p,
        body.tour-detail-view .best-offer-included,
        body.tour-detail-view .tour-price-table td,
        body.tour-detail-view .low-price-calendar td,
        body.tour-detail-view .detail-note,
        body.tour-detail-view .facility-list li,
        body.tour-detail-view .review-slide p {
            font-size: 16px !important;
            font-weight: 400 !important;
        }

        body.tour-detail-view .reviews-carousel .review-slide-title {
            font-size: 14px !important;
            line-height: 1.25 !important;
        }

        body.tour-detail-view .reviews-carousel .review-slide p {
            font-size: 13px !important;
            line-height: 1.45 !important;
        }

        body.tour-detail-view .reviews-carousel .review-slide .review-meta {
            font-size: 10px !important;
            letter-spacing: 0.06em !important;
        }

        .tour-detail-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 0 14px;
        }

        .detail-site-header {
            position: sticky;
            top: 0;
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-inline: calc(50% - 50vw);
            padding: 14px max(14px, calc(50vw - 50% + 14px));
            border-bottom: 1px solid rgba(220, 228, 242, 0.9);
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(14px);
        }

        .detail-site-header .brand-logo-sub {
            color: #293b8f;
        }

        .detail-header-nav {
            display: flex;
            gap: 22px;
            margin-left: auto;
        }

        .detail-header-nav a {
            color: var(--text);
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        .tour-breadcrumbs,
        .tour-tabs {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .tour-breadcrumbs {
            color: var(--muted);
            font-size: 16px;
            font-weight: 600;
        }

        .tour-breadcrumbs a {
            color: var(--muted);
            text-decoration: none;
        }

        .tour-breadcrumbs span {
            color: #a3adc2;
        }

        .tour-tabs {
            position: sticky;
            top: 78px;
            z-index: 20;
            gap: 0;
            padding: 0;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
        }

        .tour-tabs a {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-height: 54px;
            padding: 15px 22px;
            border: 0;
            border-radius: 0;
            background: transparent;
            color: #5e6a83;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
            transition: color 0.18s ease, background-color 0.18s ease;
        }

        .tour-tabs a:hover,
        .tour-tabs a:first-child {
            color: var(--accent);
        }

        .tour-tabs a:first-child::after {
            content: "";
            position: absolute;
            left: 18px;
            right: 18px;
            bottom: -1px;
            height: 3px;
            border-radius: 999px 999px 0 0;
            background: var(--accent);
        }

        .tour-back-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 18px;
            border: 1px solid rgba(26, 93, 200, 0.12);
            border-radius: 999px;
            background: #ffffff;
            color: var(--accent);
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
        }

        .tour-detail-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.65fr);
            gap: 24px;
            align-items: stretch;
        }

        .tour-detail-media {
            overflow: hidden;
            min-height: 440px;
            border-radius: 18px;
            background: #eaf0fa;
        }

        .tour-detail-media img {
            width: 100%;
            height: 100%;
            min-height: 440px;
            object-fit: cover;
            display: block;
        }

        .tour-detail-summary {
            display: grid;
            align-content: start;
            gap: 16px;
            padding: 24px;
            border: 1px solid rgba(220, 228, 242, 0.88);
            border-radius: 18px;
            background: #ffffff;
            box-shadow: var(--shadow);
        }

        .tour-detail-summary h1 {
            margin: 0;
            color: var(--text);
            font-size: clamp(30px, 3.4vw, 46px);
            line-height: 1.02;
            letter-spacing: 0;
        }

        .tour-detail-summary p {
            margin: 0;
            color: var(--muted);
        }

        .tour-detail-price {
            display: grid;
            gap: 4px;
            padding: 18px;
            border-radius: 16px;
            background: #f2f5fb;
        }

        .tour-detail-price span {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .tour-detail-price strong {
            color: var(--text);
            font-size: 30px;
            line-height: 1;
        }

        .tour-detail-cta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .tour-detail-cta a,
        .tour-detail-cta button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            padding: 13px 18px;
            border-radius: 999px;
            font-weight: 800;
            text-decoration: none;
            line-height: 1.15;
        }

        .tour-detail-cta .primary {
            border: 0;
            background: #f31624;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
        }

        .tour-detail-cta .secondary {
            border: 1px solid rgba(26, 93, 200, 0.14);
            background: #ffffff;
            color: var(--accent);
        }

        .tour-detail-sections {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, 0.36fr);
            gap: 22px;
            align-items: start;
        }

        .tour-detail-main,
        .tour-detail-side {
            display: grid;
            gap: 18px;
        }

        .tour-facts-inline {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            padding: 16px 0 4px;
        }

        .tour-fact {
            display: grid;
            gap: 5px;
            min-height: 76px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: #ffffff;
        }

        .tour-fact small {
            color: var(--muted);
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .tour-fact strong {
            color: var(--text);
            font-size: 17px;
            line-height: 1.25;
        }

        .tour-detail-view .content-card {
            border-radius: 16px;
            box-shadow: none;
        }

        .tour-detail-view .content-card h4 {
            font-size: 22px;
            line-height: 1.15;
        }

        .hotel-detail-shell {
            display: grid;
            gap: 42px;
        }

        .hotel-detail-head {
            display: grid;
            gap: 22px;
        }

        .hotel-detail-title {
            display: grid;
            gap: 8px;
        }

        .hotel-detail-title h1 {
            margin: 0;
            color: var(--text);
            font-size: 32px;
            font-weight: 600;
            line-height: 1.04;
            letter-spacing: 0;
        }

        .hotel-location-line {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            font-size: 16px;
            font-weight: 600;
        }

        .hotel-location-line__text {
            color: var(--muted);
            font-weight: 500;
        }

        .hotel-map-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px 9px 14px;
            border-radius: 999px;
            border: 1px solid rgba(26, 93, 200, 0.45);
            background: linear-gradient(180deg, rgba(26, 93, 200, 0.14) 0%, rgba(26, 93, 200, 0.08) 100%);
            color: var(--accent-strong);
            font-size: 15px;
            font-weight: 700;
            line-height: 1.2;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(26, 93, 200, 0.18);
            transition: background .18s ease, border-color .18s ease, color .18s ease, box-shadow .18s ease, transform .12s ease;
        }

        .hotel-map-cta:hover,
        .hotel-map-cta:focus-visible {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 8px 22px rgba(26, 93, 200, 0.35);
            outline: none;
            transform: translateY(-1px);
        }

        .hotel-map-cta__icon {
            flex-shrink: 0;
            display: block;
        }

        .hotel-map-cta__label {
            white-space: nowrap;
        }

        .hotel-photo-offer {
            display: grid;
            grid-template-columns: minmax(0, 31fr) minmax(0, 18fr);
            gap: 18px;
            align-items: start;
        }

        .hotel-gallery-mosaic {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            grid-template-rows: minmax(118px, 142px) minmax(118px, 142px) minmax(78px, 96px);
            grid-template-areas:
                "left-top main main main"
                "left-bottom main main main"
                "bottom-1 bottom-2 bottom-3 bottom-4";
            gap: 8px;
            min-height: 0;
            height: auto;
            max-height: min(42vh, 400px);
        }

        .hotel-gallery-item {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            background: #e8eef7;
        }

        .hotel-gallery-item img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hotel-gallery-main { grid-area: main; }
        .hotel-gallery-left-top { grid-area: left-top; }
        .hotel-gallery-left-bottom { grid-area: left-bottom; }
        .hotel-gallery-bottom-1 { grid-area: bottom-1; }
        .hotel-gallery-bottom-2 { grid-area: bottom-2; }
        .hotel-gallery-bottom-3 { grid-area: bottom-3; }
        .hotel-gallery-bottom-4 { grid-area: bottom-4; }

        .hotel-gallery-more {
            position: absolute;
            right: 12px;
            bottom: 12px;
            color: #ffffff;
            font-size: 22px;
            font-weight: 900;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.38);
        }

        .best-offer-card {
            position: static;
            display: grid;
            gap: 16px;
            padding: 20px 20px 18px;
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: var(--radius-lg);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 16px 38px rgba(20, 41, 84, 0.07);
            height: auto;
            min-width: 0;
        }

        .best-offer-card h2 {
            margin: 0;
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.12;
        }

        .best-offer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 26px 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .best-offer-fact {
            display: grid;
            gap: 8px;
            min-width: 0;
        }

        .best-offer-fact span {
            color: var(--muted);
            font-weight: 600;
            font-size: 16px;
            line-height: 1.3;
        }

        .best-offer-fact-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .best-offer-fact-icon {
            width: 18px;
            height: 18px;
            color: var(--accent);
        }

        .best-offer-fact strong {
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.2;
        }

        .best-offer-included {
            margin: 0;
            color: #36435f;
            font-size: 16px;
            font-weight: 400;
            line-height: 1.35;
        }

        .best-offer-price {
            display: grid;
            gap: 4px;
        }

        .best-offer-price strong {
            color: var(--text);
            font-size: 28px;
            line-height: 1;
        }

        .best-offer-card > .detail-buy-button.detail-scroll-to-prices {
            margin-top: 2px;
        }

        /* Кнопка «Дивитись всі ціни» у картці — синя (не червона CTA) */
        #best-offer > .detail-buy-button.detail-scroll-to-prices.best-offer-prices-btn,
        #best-offer > .detail-buy-button.detail-scroll-to-prices.best-offer-prices-btn:hover,
        #best-offer > .detail-buy-button.detail-scroll-to-prices.best-offer-prices-btn:focus,
        #best-offer > .detail-buy-button.detail-scroll-to-prices.best-offer-prices-btn:active {
            background: #1a5dc8 !important;
            color: #ffffff !important;
            filter: none;
        }

        #best-offer > .detail-buy-button.detail-scroll-to-prices.best-offer-prices-btn:hover {
            filter: brightness(1.06);
        }

        .best-offer-lead-open {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: var(--radius-md);
            border: 2px solid #1a5dc8;
            background: transparent;
            color: #1a5dc8;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, border-color .15s ease;
        }

        .best-offer-lead-open:hover,
        .best-offer-lead-open:focus-visible {
            background: rgba(26, 93, 200, 0.08);
            outline: none;
        }

        .best-offer-lead-backdrop {
            position: fixed;
            inset: 0;
            z-index: 10002;
            display: grid;
            place-items: center;
            padding: 18px;
            background: rgba(9, 18, 38, 0.5);
        }

        .best-offer-lead-backdrop[hidden] {
            display: none !important;
        }

        .best-offer-lead-dialog {
            position: relative;
            width: min(400px, 100%);
            max-height: min(90dvh, 520px);
            overflow-y: auto;
            padding: 22px 22px 20px;
            border-radius: 16px;
            border: 1px solid rgba(205, 216, 233, 0.95);
            background: #ffffff;
            box-shadow: 0 22px 56px rgba(9, 18, 38, 0.22);
        }

        .best-offer-lead-close {
            position: absolute;
            top: 10px;
            right: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            margin: 0;
            padding: 0;
            border: 1px solid rgba(193, 207, 229, 0.95);
            border-radius: 999px;
            background: #e9eef8;
            color: #1f2b46;
            font-size: 26px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }

        .best-offer-lead-close:hover,
        .best-offer-lead-close:focus-visible {
            background: #1a5dc8;
            border-color: #1a5dc8;
            color: #ffffff;
            outline: none;
        }

        .best-offer-lead-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .best-offer-lead-form--modal {
            margin-top: 6px;
        }

        .best-offer-consult {
            display: grid;
            gap: 12px;
            margin-top: 4px;
        }

        .best-offer-lead-form {
            display: grid;
            gap: 8px;
        }

        .best-offer-lead-form input[type="text"],
        .best-offer-lead-form input[type="tel"] {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(220, 228, 242, 0.95);
            background: #fbfcff;
            font: inherit;
            font-size: 14px;
        }

        .best-offer-lead-form input::placeholder {
            color: #8b94ab;
        }

        .best-offer-lead-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 16px;
            border: 0;
            border-radius: 10px;
            background: #1a5dc8;
            color: #ffffff;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .best-offer-lead-submit:hover,
        .best-offer-lead-submit:focus-visible {
            filter: brightness(1.05);
            outline: none;
        }

        .best-offer-lead-form .advisor-lead-status {
            margin: 0;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            min-height: 1.2em;
        }

        .best-offer-messenger-caption {
            margin: 4px 0 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .best-offer-messengers {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .best-offer-msg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            border: 1px solid rgba(220, 228, 242, 0.95);
            background: #ffffff;
            color: var(--accent);
            box-shadow: 0 4px 14px rgba(17, 38, 77, 0.06);
            text-decoration: none;
            transition: border-color .15s ease, transform .12s ease, box-shadow .15s ease;
        }

        .best-offer-msg:hover,
        .best-offer-msg:focus-visible {
            border-color: rgba(26, 93, 200, 0.28);
            transform: translateY(-1px);
            outline: none;
        }

        .best-offer-msg svg {
            display: block;
            width: 26px;
            height: 26px;
            flex-shrink: 0;
            fill: currentColor;
        }

        .best-offer-msg--tg {
            color: #229ed9;
        }

        .best-offer-msg--vb {
            color: #7360f2;
        }

        .best-offer-price span {
            color: var(--muted);
            font-size: 16px;
            font-weight: 600;
        }

        .detail-buy-button,
        .detail-secondary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 58px;
            padding: 14px 22px;
            border: 0;
            border-radius: var(--radius-md);
            background: #f31624;
            color: #ffffff;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .detail-buy-button,
        .detail-buy-button:hover,
        .detail-buy-button:focus,
        .detail-buy-button:active,
        .table-buy-button,
        .table-buy-button:hover,
        .table-buy-button:focus,
        .table-buy-button:active {
            background: #e90f25 !important;
            color: #ffffff !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }

        .detail-buy-button:hover,
        .table-buy-button:hover {
            filter: brightness(1.04);
        }

        .detail-buy-button:focus-visible,
        .table-buy-button:focus-visible {
            outline: 3px solid rgba(10, 25, 58, 0.28);
            outline-offset: 2px;
        }

        .detail-secondary-button {
            border: 1px solid #1a5dc8;
            background: #1a5dc8;
            color: #ffffff;
        }

        .detail-secondary-button:hover,
        .detail-secondary-button:focus-visible {
            background: #1348a8;
            border-color: #1348a8;
            color: #ffffff;
        }

        #tour-prices .detail-secondary-button,
        #tour-prices .detail-secondary-button:hover,
        #tour-prices .detail-secondary-button:focus,
        #tour-prices .detail-secondary-button:focus-visible,
        #tour-prices .detail-secondary-button:active {
            color: #ffffff !important;
        }

        .detail-section {
            display: grid;
            gap: 22px;
        }

        .detail-section h2 {
            margin: 0;
            color: var(--text);
            font-size: 32px;
            font-weight: 600;
            line-height: 1.12;
        }

        /* Compact info block like reference: not full-width on desktop */
        #tour-info {
            width: min(50%, 1140px);
            max-width: min(50%, 1140px);
        }

        #tour-info .detail-secondary-button {
            display: inline-flex !important;
            align-self: start;
            width: auto !important;
            min-width: 200px;
            min-height: 52px;
            padding: 12px 28px;
            border-radius: 12px;
            background: #1a5dc8 !important;
            border: 1px solid #1a5dc8 !important;
            color: #ffffff !important;
            font-size: 16px;
            font-weight: 600;
            line-height: 1;
            text-decoration: none;
        }

        #tour-info .detail-secondary-button:hover,
        #tour-info .detail-secondary-button:focus-visible {
            background: #1348a8 !important;
            border-color: #1348a8 !important;
            color: #ffffff !important;
        }

        .hotel-info-copy {
            max-width: none;
            color: #36435f;
            font-size: 16px;
            font-weight: 400;
            line-height: 1.48;
        }

        .hotel-info-copy p {
            margin: 0 0 12px;
        }

        .detail-bullets {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px 16px;
        }

        .detail-bullets li {
            position: relative;
            padding-left: 18px;
            color: #36435f;
            font-size: 16px;
            line-height: 1.4;
        }

        .detail-bullets li::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0.52em;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #2f6ed8;
            transform: translateY(-50%);
        }

        /* ── Сторінка картки екскурсійного туру (референс: 2 колонки, наші кольори) ── */
        body.anex-excursion-detail-view .anex-excursion-detail-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 28px 32px;
            align-items: start;
            width: 100%;
            max-width: min(1180px, 100%);
            margin: 0 auto;
            padding: 8px 16px 48px;
            box-sizing: border-box;
        }
        body.anex-excursion-detail-view .anex-excursion-detail-main {
            min-width: 0;
            display: grid;
            gap: 20px;
        }
        body.anex-excursion-detail-view .anex-excursion-detail-sidebar-col {
            min-width: 0;
        }
        body.anex-excursion-detail-view .anex-exc-sidebar-stack {
            display: grid;
            gap: 14px;
            position: sticky;
            top: 16px;
        }
        body.anex-excursion-detail-view .anex-excursion-detail-main .anex-excursion-detail-slot:empty,
        body.anex-excursion-detail-view .anex-excursion-detail-sidebar-col .anex-excursion-detail-slot:empty {
            display: none !important;
        }
        body.anex-excursion-detail-view .anex-excursion-detail-slot > * {
            margin: 0 !important;
            max-width: 100% !important;
        }
        body.anex-excursion-detail-view .anex-excursion-detail-compact {
            display: grid;
            gap: 24px;
            width: 100%;
            max-width: min(1180px, 100%);
            margin: 0 auto;
            padding: 8px 16px 48px;
            box-sizing: border-box;
        }

        .exc-head { display: grid; gap: 10px; }
        .exc-head .tour-breadcrumbs { font-size: 14px; color: var(--muted); }
        .exc-head .tour-breadcrumbs a { color: var(--accent); text-decoration: none; }
        .exc-head .tour-breadcrumbs a:hover { text-decoration: underline; }
        .exc-head-title { margin: 0; font-size: clamp(1.5rem, 2.8vw, 2.15rem); font-weight: 800; line-height: 1.12; color: var(--text); }
        .exc-tour-code { margin: 0; font-size: 13px; color: var(--muted); }
        .exc-tour-code span { font-weight: 600; color: #4a5878; }
        .exc-head-route { margin: 0; font-size: 16px; color: #4a5878; line-height: 1.45; }
        .exc-tag-row { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
        .exc-tag { padding: 6px 12px; border-radius: 999px; background: var(--accent-soft); color: #1a3d72; font-size: 13px; font-weight: 600; }

        .exc-gallery-sec { margin: 0; }
        .exc-gal-mosaic {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 0.65fr);
            gap: 10px;
            min-height: 220px;
        }
        .exc-gal-main, .exc-gal-cell { border-radius: 16px; overflow: hidden; border: 1px solid var(--line); background: #e8eef7; position: relative; }
        .exc-gal-cell { display: block; height: 100%; }
        .exc-gal-cell img { width: 100%; height: 100%; object-fit: cover; display: block; min-height: 100px; }
        .exc-gal-main { min-height: 280px; }
        .exc-gal-stack { display: grid; grid-template-rows: 1fr 1fr; gap: 10px; min-height: 0; }
        .exc-gal-stack-row { position: relative; border-radius: 14px; overflow: hidden; border: 1px solid var(--line); min-height: 0; }
        .exc-gal-stack-row--last .exc-gal-cell { height: 100%; }
        .exc-gal-more {
            position: absolute; right: 10px; bottom: 10px;
            padding: 8px 12px; border-radius: 10px;
            background: rgba(26, 34, 51, 0.72); color: #fff; font-size: 14px; font-weight: 800;
        }

        .exc-sec-h { margin: 0 0 12px; font-size: 22px; font-weight: 700; color: var(--text); }
        .exc-sec-h2 { margin: 0 0 8px; font-size: 17px; font-weight: 700; color: var(--text); }

        .exc-route-block { margin-bottom: 8px; }
        .exc-route-line { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 4px; font-size: 15px; color: #36435f; }
        .exc-route-step { display: inline-flex; align-items: center; gap: 8px; }
        .exc-route-badge {
            width: 28px; height: 28px; border-radius: 999px;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(26, 93, 200, 0.12); color: var(--accent); font-weight: 800; font-size: 13px;
        }
        .exc-route-city { font-weight: 600; color: var(--text); }
        .exc-route-gap { color: #9aa6bf; padding: 0 4px; }

        .exc-main-program { display: grid; gap: 16px; }
        .exc-acc-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .exc-acc-collapse {
            border: 0; background: transparent; color: var(--accent); font-weight: 700; font-size: 15px;
            cursor: pointer; text-decoration: underline; padding: 4px 0;
        }
        .exc-program-wrap { display: grid; gap: 10px; }
        .exc-program-fallback-wrap { font-size: 15px; line-height: 1.55; color: #36435f; }
        .exc-program-fallback-wrap .hotel-info-copy { margin-top: 0; }
        .exc-program-html,
        .exc-day-body--html {
            font-size: 15px;
            line-height: 1.55;
            color: #36435f;
            overflow-x: auto;
        }
        .exc-program-html table,
        .exc-day-body--html table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 14px; }
        .exc-program-html td,
        .exc-day-body--html td { border: 1px solid var(--line); padding: 8px 10px; vertical-align: top; }
        .exc-program-html img,
        .exc-day-body--html img { max-width: 100%; height: auto; border-radius: 8px; }
        .exc-program-html p,
        .exc-day-body--html p { margin: 0 0 10px; }
        .exc-day-acc {
            border: 1px solid rgba(26, 93, 200, 0.35); border-radius: 14px; background: #fff; overflow: hidden;
        }
        .exc-day-acc > summary {
            list-style: none; cursor: pointer; padding: 14px 16px; font-weight: 700; font-size: 16px;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
        }
        .exc-day-acc > summary::-webkit-details-marker { display: none; }
        .exc-day-acc > summary::after { content: "▾"; font-size: 12px; color: var(--accent); }
        .exc-day-acc[open] > summary::after { content: "▴"; }
        .exc-day-inner { padding: 0 16px 16px; border-top: 1px solid var(--line); }
        .exc-day-loc { margin: 10px 0 0; font-size: 13px; color: var(--muted); font-weight: 600; }
        .exc-day-body { margin: 8px 0 0; font-size: 15px; line-height: 1.55; color: #36435f; white-space: pre-wrap; }
        .exc-day-media { margin-top: 12px; border-radius: 12px; overflow: hidden; max-height: 240px; }
        .exc-day-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .exc-fallback { font-size: 15px; color: #4a5878; margin: 0; }
        .exc-mini-list { margin: 8px 0 0; padding-left: 18px; color: #36435f; }
        .exc-pro-extra { margin-top: 8px; }

        .exc-hikes-sec { margin: 0; }
        .exc-hike-grid { display: grid; gap: 14px; }
        .exc-hike-card {
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 140px); gap: 14px;
            padding: 16px 18px; border-radius: 16px; border: 1px solid var(--line); background: #fbfcff; align-items: start;
        }
        .exc-hike-card h3 { margin: 0 0 6px; font-size: 17px; font-weight: 700; }
        .exc-hike-card p { margin: 0; font-size: 15px; line-height: 1.45; color: #36435f; }
        .exc-hike-meta { font-size: 13px; color: var(--muted); margin-bottom: 8px; font-weight: 600; }
        .exc-hike-card-media { border-radius: 12px; overflow: hidden; border: 1px solid var(--line); aspect-ratio: 4/3; max-height: 120px; }
        .exc-hike-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .exc-inc-sec { margin: 0; }
        .exc-inc-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .exc-inc-box { padding: 18px 18px; border-radius: 16px; border: 1px solid var(--line); }
        .exc-inc-box h3 { margin: 0 0 12px; font-size: 17px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .exc-inc-ic { width: 28px; height: 28px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 900; }
        .exc-inc-ic--ok { background: var(--accent); color: #fff; }
        .exc-inc-ic--no { background: var(--accent-strong); color: #fff; }
        .exc-inc-box--yes { background: var(--accent-soft); border-color: rgba(26, 93, 200, 0.22); }
        .exc-inc-box--no { background: rgba(26, 93, 200, 0.05); }
        .exc-inc-box ul { margin: 0; padding-left: 18px; font-size: 15px; line-height: 1.45; color: #36435f; }
        .exc-inc-box li + li { margin-top: 6px; }

        .exc-docs-sec { margin: 0; }
        .exc-docs-lead { font-size: 15px; line-height: 1.5; color: #36435f; margin: 0 0 12px; }
        .exc-docs-list { display: grid; gap: 10px; }
        .exc-docs-list a {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 16px; border-radius: 14px; border: 1px solid var(--line); background: #fbfcff;
            text-decoration: none; color: var(--accent); font-weight: 600; font-size: 15px;
        }

        .exc-dates-sec { margin: 0; }
        .exc-dates-wrap { overflow-x: auto; border-radius: 14px; border: 1px solid var(--line); background: #fff; }
        .exc-dates-table { width: 100%; min-width: 640px; border-collapse: collapse; font-size: 15px; }
        .exc-dates-table th { text-align: left; padding: 12px 14px; background: rgba(26, 93, 200, 0.09); color: var(--accent); font-weight: 700; font-size: 14px; border-bottom: 1px solid var(--line); }
        .exc-dates-table td { padding: 14px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--text); }
        .exc-dates-table small { display: block; color: var(--muted); font-size: 13px; font-weight: 600; margin-top: 4px; }
        .exc-dates-actions { white-space: nowrap; }
        .exc-cta-btn--sm { padding: 10px 14px !important; font-size: 14px !important; min-height: 0 !important; }

        .exc-side-card { border-radius: 18px; border: 1px solid var(--line); background: #fff; box-shadow: 0 14px 36px rgba(17, 38, 77, 0.07); padding: 20px 18px 18px; }
        .exc-side-title { margin: 0 0 14px; font-size: 18px; font-weight: 800; color: var(--text); }
        .exc-side-facts { display: grid; gap: 12px; }
        .exc-side-fact { display: grid; grid-template-columns: 40px minmax(0, 1fr); gap: 10px; align-items: start; }
        .exc-sic { width: 22px; height: 22px; color: var(--accent); margin-top: 2px; }
        .exc-side-fact-label { display: block; font-size: 12px; font-weight: 700; letter-spacing: 0.03em; text-transform: uppercase; color: #7a869f; }
        .exc-side-fact-val { display: block; font-size: 16px; font-weight: 700; color: var(--text); line-height: 1.25; margin-top: 2px; }

        .exc-side-card--price { text-align: center; }
        .exc-side-price-hint { margin: 0; font-size: 13px; color: var(--muted); font-weight: 600; }
        .exc-side-price-big { margin: 8px 0 14px; font-size: clamp(1.45rem, 3vw, 1.85rem); font-weight: 900; color: var(--accent); }
        .exc-cta-btn {
            width: 100%; min-height: 52px; border: 0; border-radius: 12px; cursor: pointer;
            background: var(--accent) !important; color: #fff !important; font-size: 16px !important; font-weight: 800 !important;
            box-shadow: 0 10px 24px rgba(26, 93, 200, 0.28);
            transition: background 0.18s ease, box-shadow 0.18s ease, transform 0.12s ease;
        }
        .exc-cta-btn:hover {
            background: #c62828 !important;
            box-shadow: 0 10px 26px rgba(198, 40, 40, 0.35);
            filter: none;
        }
        .exc-cta-btn:active { transform: translateY(1px); }
        .exc-side-trust { margin: 12px 0 0; font-size: 13px; color: #4a5878; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .exc-trust-dot { width: 22px; height: 22px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); display: inline-flex; align-items: center; justify-content: center; font-weight: 900; font-size: 12px; }

        .exc-pop-sec { margin: 0; }
        .exc-pop-loading { margin: 8px 0 0; font-size: 15px; color: var(--muted); }
        .exc-pop-empty { margin: 8px 0 0; font-size: 15px; line-height: 1.5; color: var(--muted); }
        .exc-pop-empty a { color: var(--accent); font-weight: 700; text-decoration: underline; }
        .exc-pop-scroll {
            display: flex; gap: 18px; overflow-x: auto; padding: 4px 2px 12px;
            scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;
        }
        .exc-pop-card {
            flex: 0 0 min(280px, 86vw); scroll-snap-align: start;
            border-radius: 16px; border: 1px solid var(--line); background: #fff;
            box-shadow: 0 10px 28px rgba(17, 38, 77, 0.06); overflow: hidden;
            display: flex; flex-direction: column; min-height: 100%;
        }
        .exc-pop-card-media { aspect-ratio: 16/10; background: #e8eef7; overflow: hidden; position: relative; }
        .exc-pop-badges {
            position: absolute; left: 8px; right: 8px; top: 8px; display: flex; flex-wrap: wrap; gap: 6px; pointer-events: none;
        }
        .exc-pop-badge {
            display: inline-block; padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 800; line-height: 1.2;
        }
        .exc-pop-badge--top { background: #ffc107; color: #1a2233; }
        .exc-pop-badge--night { background: rgba(26, 34, 51, 0.78); color: #fff; }
        .exc-pop-card-media img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .exc-pop-card-body { padding: 14px 14px 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
        .exc-pop-card-title { margin: 0; font-size: 16px; font-weight: 800; line-height: 1.25; color: var(--text); }
        .exc-pop-card-meta { margin: 0; font-size: 13px; color: var(--muted); line-height: 1.35; }
        .exc-pop-card-price { margin: auto 0 0; font-size: 18px; font-weight: 900; color: var(--accent); }
        .exc-pop-card-price small { font-size: 12px; font-weight: 700; color: var(--muted); }
        .exc-pop-card-cta {
            display: block; text-align: center; margin-top: 10px; padding: 11px 12px; border-radius: 10px;
            background: var(--accent); color: #fff !important; font-weight: 800; font-size: 14px; text-decoration: none;
            transition: background 0.18s ease;
        }
        .exc-pop-card-cta:hover { background: #c62828; color: #fff !important; }

        @media (max-width: 960px) {
            body.anex-excursion-detail-view .anex-excursion-detail-layout {
                grid-template-columns: 1fr;
            }
            body.anex-excursion-detail-view .anex-excursion-detail-sidebar-col { order: -1; }
            body.anex-excursion-detail-view .anex-exc-sidebar-stack { position: static; }
            .exc-gal-mosaic { grid-template-columns: 1fr; }
            .exc-gal-stack { grid-template-columns: 1fr 1fr; grid-template-rows: none; }
            .exc-hike-card { grid-template-columns: 1fr; }
            .exc-hike-card-media { max-height: 200px; order: -1; }
            .exc-inc-grid { grid-template-columns: 1fr; }
        }

        .price-filter-row {
            display: grid;
            grid-template-columns: 1.4fr 1.4fr 0.9fr 0.95fr 0.9fr;
            gap: 6px;
        }

        .price-filter-cell {
            display: grid;
            gap: 4px;
            min-height: 80px;
            padding: 14px 16px;
            border: 1px solid var(--line);
            border-radius: var(--radius-md);
            background: #ffffff;
        }

        .price-filter-cell span {
            color: var(--muted);
            font-size: 16px;
            font-weight: 600;
        }

        .price-filter-cell strong {
            color: var(--text);
            font-size: 16px;
            font-weight: 600;
            line-height: 1.2;
        }

        .tour-price-table-wrap,
        .low-price-calendar-wrap {
            overflow-x: auto;
            border-radius: var(--radius-md);
        }

        .tour-price-table,
        .low-price-calendar {
            width: 100%;
            min-width: 940px;
            border-collapse: collapse;
            background: #ffffff;
        }

        .tour-price-table th,
        .tour-price-table td,
        .low-price-calendar th,
        .low-price-calendar td {
            padding: 18px 20px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid var(--line);
            color: var(--text);
            font-size: 16px;
            font-weight: 400;
        }

        .tour-price-table th,
        .low-price-calendar th {
            background: rgba(26, 93, 200, 0.08);
            color: var(--accent);
            font-size: 16px;
            font-weight: 600;
        }

        .tour-price-table small,
        .low-price-calendar small {
            display: block;
            color: var(--muted);
            font-size: 15px;
            font-weight: 800;
        }

        .table-buy-button {
            min-width: 160px;
            min-height: 48px;
            padding: 10px 16px;
            border: 0;
            border-radius: var(--radius-md);
            background: #f31624;
            color: #ffffff;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .low-price-calendar th,
        .low-price-calendar td {
            min-width: 138px;
            text-align: center;
            border-right: 1px solid var(--line);
        }

        .low-price-calendar .calendar-side {
            min-width: 130px;
            background: rgba(26, 93, 200, 0.04);
            text-align: center;
        }

        .calendar-price-low {
            color: #0a7b55 !important;
        }

        .calendar-price-active {
            background: rgba(26, 93, 200, 0.1);
            box-shadow: inset 0 0 0 2px var(--accent);
        }

        /* ── Mobile: tour-price-table as stacked cards ── */
        @media (max-width: 820px) {
            .tour-price-table-wrap {
                overflow-x: visible;
                border-radius: 0;
                background: transparent;
            }

            .tour-price-table {
                min-width: 0;
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                background: transparent;
            }

            .tour-price-table thead {
                display: none;
            }

            .tour-price-table tbody tr {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
                padding: 14px;
                margin-bottom: 14px;
                border-radius: 16px;
                background: #fff;
                border: 1px solid rgba(196, 208, 228, 0.9);
                box-shadow: 0 8px 20px rgba(12, 31, 68, 0.08);
            }

            .tour-price-table td {
                display: block;
                min-width: 0;
                margin: 0;
                padding: 10px 12px;
                border: 1px solid rgba(205, 216, 233, 0.9) !important;
                border-radius: 12px;
                background: #f8faff;
                font-size: 14px;
                position: relative;
            }

            /* Date + city span full row */
            .tour-price-table td:first-child {
                grid-column: 1 / -1;
                padding: 12px 14px;
                border-radius: 14px;
                background: #f2f6fd;
                font-size: 16px;
            }

            /* Buy button spans full row */
            .tour-price-table td:last-child {
                grid-column: 1 / -1;
                background: transparent;
                border: 0 !important;
                padding: 4px 0 0;
            }

            .tour-price-table td[data-label]::before {
                display: block;
                content: attr(data-label);
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: var(--muted);
                margin-bottom: 2px;
            }

            .tour-price-table td:first-child::before,
            .tour-price-table td:last-child::before {
                display: none;
            }

            .table-buy-button {
                width: 100%;
                min-width: 0;
                min-height: 52px;
                border-radius: 14px;
                font-size: 19px;
                font-weight: 900;
            }

            /* ── Calendar: sticky first column + compact ── */
            .low-price-calendar-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: var(--radius-md);
            }

            .low-price-calendar {
                min-width: 0;
            }

            .low-price-calendar .calendar-side {
                position: sticky;
                left: 0;
                z-index: 2;
                background: rgba(0,48,135,0.07);
                min-width: 68px;
            }

            .low-price-calendar th,
            .low-price-calendar td {
                min-width: 80px;
                padding: 10px 8px;
                font-size: 13px;
            }

            .low-price-calendar thead th {
                position: sticky;
                top: 0;
                z-index: 1;
                background: rgba(0,48,135,0.08);
            }
        }

        .advisor-cta {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 24px;
            overflow: hidden;
            min-height: 260px;
            padding: 42px;
            border-radius: var(--radius-xl);
            background:
                linear-gradient(135deg, rgba(7, 18, 42, 0.92) 0%, rgba(26, 93, 200, 0.92) 52%, rgba(15, 61, 155, 0.88) 100%);
            color: #ffffff;
        }

        .advisor-cta h2 {
            margin: 0;
            color: #ffffff;
            font-size: clamp(34px, 4vw, 48px);
        }

        .advisor-cta p {
            margin: 12px 0 0;
            max-width: 760px;
            font-size: 22px;
            font-weight: 600;
            line-height: 1.45;
            color: #f2f6ff !important;
        }

        .advisor-form {
            display: grid;
            grid-template-columns: minmax(170px, 0.85fr) minmax(260px, 1.25fr) minmax(220px, 1fr);
            gap: 16px;
            margin-top: 34px;
        }

        .advisor-form input {
            min-height: 62px;
            padding: 0 18px;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: var(--radius-md);
            color: var(--text);
            font: inherit;
            font-size: 18px;
            font-weight: 800;
        }

        .advisor-submit {
            min-height: 62px;
            border: 0;
            border-radius: var(--radius-md);
            background: #1a5dc8;
            color: #ffffff;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        .advisor-submit:hover,
        .advisor-submit:focus-visible {
            background: #1348a8;
            color: #ffffff;
        }

        .advisor-lead-status {
            margin: 12px 0 0;
            min-height: 22px;
            font-size: 14px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.92);
        }

        .advisor-person {
            align-self: end;
            display: grid;
            gap: 14px;
            justify-items: center;
        }

        .advisor-avatar {
            display: block;
            width: 190px;
            height: 190px;
            border-radius: 999px;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.45);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.22);
            background: rgba(255, 255, 255, 0.12);
        }

        .advisor-badge {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            background: rgba(255, 255, 255, 0.16);
            font-size: 18px;
            font-weight: 900;
            text-align: center;
        }

        .advisor-badge strong {
            display: block;
            font-size: 17px;
            line-height: 1.25;
        }

        .advisor-badge span {
            display: block;
            margin-top: 6px;
            font-size: 14px;
            font-weight: 700;
            opacity: 0.92;
        }

        .booking-benefits,
        .similar-grid {
            display: grid;
            gap: 18px;
        }

        .detail-section--facilities .facility-section-subtitle {
            margin: 0 0 18px;
            font-size: 16px;
            line-height: 1.5;
        }

        .facility-shell {
            display: grid;
            gap: 28px;
        }

        .facility-groups-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .facility-group-card {
            padding: 20px 20px 18px;
            border: 1px solid rgba(220, 228, 242, 0.95);
            border-radius: var(--radius-lg);
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(17, 38, 77, 0.05);
        }

        .facility-group-card h3 {
            margin: 0 0 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
            color: var(--text);
            font-size: 18px;
            line-height: 1.2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .facility-group-card h3 svg {
            width: 18px;
            height: 18px;
            color: #2b6fdd;
            flex-shrink: 0;
        }

        .facility-list {
            display: grid;
            gap: 8px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .facility-list li {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            color: #36435f;
            font-size: 15px;
            line-height: 1.4;
        }

        .facility-list li::before {
            content: "✓";
            flex-shrink: 0;
            color: var(--accent);
            font-weight: 900;
            margin-top: 2px;
        }

        .booking-benefits {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .booking-benefit-card {
            min-height: 250px;
            padding: 38px 28px;
            border-radius: var(--radius-lg);
            border: 1px solid rgba(220, 228, 242, 0.75);
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(247, 250, 255, 0.9)),
                rgba(255, 255, 255, 0.9);
            box-shadow: 0 16px 38px rgba(20, 41, 84, 0.07);
        }

        .booking-benefit-card h3 {
            margin: 0 0 18px;
            color: var(--text);
            font-size: 24px;
        }

        .booking-benefit-card p {
            margin: 0;
            color: #36435f;
            font-size: 20px;
            line-height: 1.4;
        }

        .similar-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        a.similar-card {
            display: grid;
            grid-template-rows: auto 1fr;
            overflow: hidden;
            border: 1px solid rgba(220, 228, 242, 0.75);
            border-radius: var(--radius-lg);
            background: #ffffff;
            color: inherit;
            text-decoration: none;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        a.similar-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        a.similar-card:focus-visible {
            outline: 3px solid rgba(26, 93, 200, 0.35);
            outline-offset: 2px;
        }

        .similar-card img {
            display: block;
            width: 100%;
            height: 170px;
            object-fit: cover;
            background: #e8eef7;
        }

        .similar-card-placeholder {
            height: 170px;
            background: linear-gradient(135deg, #e8eef7, #f2f5fb);
        }

        .similar-card-body {
            display: grid;
            gap: 8px;
            padding: 16px;
        }

        .similar-card h3 {
            overflow: hidden;
            margin: 0;
            color: var(--text);
            font-size: 18px;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .similar-card p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            font-weight: 600;
        }

        .similar-card strong {
            color: var(--text);
            font-size: 24px;
            font-weight: 800;
        }

        .similar-card-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 6px;
            min-height: 48px;
            padding: 10px 16px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(26, 93, 200, 0.14);
            background: rgba(26, 93, 200, 0.06);
            color: var(--accent);
            font-size: 15px;
            font-weight: 800;
        }

        a.similar-card:hover .similar-card-cta {
            background: var(--accent);
            color: #ffffff;
            border-color: transparent;
        }

        .reviews-carousel {
            position: relative;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 10px;
            align-items: center;
        }

        .reviews-track {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 10px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding: 4px 2px 10px;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }

        .review-slide {
            flex: 0 0 calc((100% - 20px) / 3);
            min-width: 0;
            scroll-snap-align: start;
            box-sizing: border-box;
            padding: 12px 14px 10px;
            border-radius: 14px;
            border: 1px solid rgba(220, 228, 242, 0.95);
            background: #ffffff;
            box-shadow: 0 6px 18px rgba(17, 38, 77, 0.05);
        }

        @media (min-width: 1200px) {
            .review-slide {
                flex: 0 0 calc((100% - 30px) / 4);
            }
        }

        .review-slide-title {
            margin: 0 0 6px;
            font-size: 14px;
            line-height: 1.25;
            color: var(--text);
        }

        .review-slide p {
            margin: 0 0 8px;
            font-size: 13px;
            line-height: 1.45;
            white-space: normal;
            display: -webkit-box;
            -webkit-line-clamp: 5;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .reviews-carousel .review-meta {
            font-size: 11px;
            letter-spacing: 0.06em;
        }

        .reviews-nav {
            align-self: center;
            width: 36px;
            height: 36px;
            border: 1px solid rgba(220, 228, 242, 0.95);
            border-radius: 10px;
            background: #ffffff;
            color: var(--accent);
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(17, 38, 77, 0.06);
        }

        .reviews-nav:hover {
            border-color: rgba(26, 93, 200, 0.2);
        }

        @media (max-width: 900px) {
            .reviews-track {
                gap: 8px;
                padding: 0 0 8px;
            }

            .reviews-carousel {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .reviews-nav {
                display: none;
            }

            .review-slide {
                flex: 0 0 100%;
                width: 100%;
                max-width: 100%;
                padding: 12px 14px;
                border-radius: 14px;
            }
        }

        .booking-backdrop {
            position: fixed;
            inset: 0;
            z-index: 10001;
            display: grid;
            place-items: center;
            padding: 18px;
            background: rgba(9, 18, 38, 0.52);
        }

        .booking-backdrop[hidden] {
            display: none !important;
        }

        .booking-modal {
            width: min(560px, 100%);
            max-height: 92dvh;
            overflow-y: auto;
            border-radius: 20px;
            border: 1px solid rgba(205, 216, 233, 0.95);
            background: #ffffff;
            box-shadow: 0 28px 70px rgba(9, 18, 38, 0.28);
        }

        .booking-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 22px 24px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafe 100%);
        }

        .booking-head h3 {
            margin: 0;
            font-size: 22px;
            line-height: 1.15;
        }

        .booking-head p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .booking-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border: 1px solid rgba(193, 207, 229, 0.95);
            border-radius: 999px;
            background: #e9eef8;
            color: #1f2b46 !important;
            font-size: 30px;
            font-weight: 700;
            line-height: 1;
            text-shadow: none;
            cursor: pointer;
            transition: background .15s ease, color .15s ease, border-color .15s ease, transform .15s ease;
        }

        .booking-close:hover,
        .booking-close:focus-visible {
            background: #e90f25;
            border-color: #e90f25;
            color: #ffffff !important;
            transform: translateY(-1px);
            outline: 0;
        }

        .booking-close:focus-visible {
            box-shadow: 0 0 0 3px rgba(10, 25, 58, 0.22);
        }

        .booking-form {
            display: grid;
            gap: 12px;
            padding: 18px 24px 24px;
        }

        /* Tour offer summary inside booking modal */
        .booking-offer-summary {
            margin: 0 22px;
            padding: 14px 16px;
            border-radius: 12px;
            background: rgba(0,48,135,0.05);
            border: 1px solid rgba(0,48,135,0.1);
            display: grid;
            gap: 8px;
        }

        .booking-offer-summary[hidden] { display: none !important; }

        .bos-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
            font-size: 13px;
        }

        .bos-row[hidden] { display: none !important; }

        .bos-label {
            color: var(--muted);
            font-weight: 700;
            flex-shrink: 0;
        }

        .bos-value {
            font-weight: 800;
            color: var(--text);
            text-align: right;
        }

        .bos-price {
            color: #f31624;
            font-size: 15px;
        }

        .booking-form label {
            display: grid;
            gap: 6px;
            color: var(--text);
            font-size: 13px;
            font-weight: 800;
        }

        .booking-form input,
        .booking-form textarea {
            width: 100%;
            min-height: 46px;
            padding: 12px 13px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #ffffff;
            color: var(--text);
            font: inherit;
            transition: border-color .12s ease, box-shadow .12s ease;
        }

        .booking-form input:focus,
        .booking-form textarea:focus {
            border-color: #1a5dc8;
            box-shadow: 0 0 0 3px rgba(26, 93, 200, 0.14);
            outline: 0;
        }

        .booking-form textarea {
            min-height: 94px;
            resize: vertical;
        }

        .booking-submit {
            min-height: 50px;
            border: 0;
            border-radius: 12px;
            background: #e90f25 !important;
            color: #ffffff !important;
            text-shadow: none !important;
            font-weight: 800;
            cursor: pointer;
            transition: filter .12s ease, transform .12s ease, box-shadow .12s ease;
        }

        .booking-submit:hover,
        .booking-submit:focus-visible {
            background: #cf0019 !important;
            color: #ffffff !important;
            filter: none;
            transform: translateY(-1px);
            outline: 0;
            box-shadow: 0 10px 24px rgba(207, 0, 25, 0.28);
        }

        .booking-submit:active {
            transform: translateY(0);
        }

        .booking-submit:disabled {
            background: #b8c2d6 !important;
            color: #ffffff !important;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .ps-native-select {
            width: 100%;
            min-height: 48px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            padding: 10px 14px;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .booking-status {
            min-height: 20px;
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .booking-status.is-success {
            color: #0d7a3f;
        }

        .booking-status.is-error {
            color: #c41e3a;
        }

        .booking-success-card {
            display: grid;
            gap: 8px;
            margin-top: 12px;
            padding: 16px 18px;
            border: 1px solid rgba(13, 122, 63, 0.18);
            border-radius: 14px;
            background: rgba(13, 122, 63, 0.07);
            color: #0d7a3f;
        }

        .booking-success-card[hidden] {
            display: none !important;
        }

        .booking-success-card strong {
            font-size: 15px;
        }

        .detail-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(13, 26, 54, 0.56);
            backdrop-filter: blur(10px);
        }

        .detail-backdrop[hidden] {
            display: none !important;
        }

        .detail-modal {
            width: min(1080px, 100%);
            max-height: calc(100vh - 40px);
            overflow: auto;
            border-radius: 28px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(14, 30, 60, 0.28);
        }

        .detail-head {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 18px;
            padding: 24px 24px 18px;
            border-bottom: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
        }

        .detail-head h3 {
            margin: 0 0 6px;
            font-size: clamp(24px, 4vw, 34px);
            line-height: 1;
            letter-spacing: 0;
        }

        .detail-head p {
            margin: 0;
            color: var(--muted);
        }

        .detail-close {
            flex: 0 0 auto;
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.08);
            color: var(--accent);
            font-size: 30px;
            line-height: 1;
            cursor: pointer;
        }

        .detail-content {
            display: grid;
            gap: 26px;
            padding: 22px 24px 28px;
        }

        .detail-loading {
            padding: 26px 24px 32px;
            color: var(--muted);
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .gallery-grid a {
            display: block;
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #edf2fb;
        }

        .gallery-grid img {
            display: block;
            width: 100%;
            height: 156px;
            object-fit: cover;
        }

        .facts-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .fact-card,
        .content-card {
            border: 1px solid var(--line);
            border-radius: 20px;
            background: #ffffff;
        }

        .fact-card {
            padding: 16px 18px;
        }

        .fact-card small {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .fact-card strong {
            font-size: 18px;
            line-height: 1.25;
            letter-spacing: 0;
        }

        .content-card {
            padding: 18px 20px;
        }

        .content-card h4 {
            margin: 0 0 12px;
            font-size: 18px;
            letter-spacing: 0;
        }

        .content-card p {
            margin: 0;
            color: #35405b;
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .tag {
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.06);
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .review-meta {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .flight-list {
            display: grid;
            gap: 14px;
        }

        .flight-card {
            display: grid;
            gap: 16px;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: #ffffff;
            overflow: hidden;
        }

        .flight-card > strong {
            display: block;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
            color: var(--text);
            font-size: 20px;
        }

        .flight-route-line {
            display: grid;
            grid-template-columns: minmax(160px, 1.05fr) minmax(120px, 0.9fr) 86px minmax(120px, 0.9fr);
            gap: 14px;
            align-items: center;
        }

        .flight-point {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .flight-point small,
        .flight-airline small {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        .flight-point b {
            color: var(--text);
            font-size: clamp(22px, 2vw, 26px);
            line-height: 1;
        }

        .flight-point span,
        .flight-airline strong {
            color: #6b7388;
            font-size: 14px;
            font-weight: 700;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .flight-middle {
            display: grid;
            justify-items: center;
            gap: 6px;
            color: var(--accent);
            font-weight: 800;
        }

        .flight-plane {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            background: rgba(26, 93, 200, 0.08);
        }

        .flight-plane svg {
            width: 22px;
            height: 22px;
            fill: currentColor;
        }

        .flight-airline {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .detail-note {
            color: var(--muted);
            font-size: 16px;
            font-weight: 400;
        }

        @keyframes swipe-hint {
            0%, 100% {
                transform: translateX(0);
                opacity: 0.7;
            }
            50% {
                transform: translateX(4px);
                opacity: 1;
            }
        }

        @keyframes shimmer {
            0% {
                background-position: 100% 0;
            }
            100% {
                background-position: 0 0;
            }
        }

        @media (max-width: 1100px) {
            .hotel-photo-offer,
            .advisor-cta {
                grid-template-columns: 1fr;
            }

            .best-offer-card {
                position: static;
            }

            .price-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .facility-groups-grid,
            .booking-benefits,
            .similar-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .advisor-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 980px) {
            .page-shell {
                padding-inline: 10px;
                padding-bottom: 56px;
            }

            .hero-stage {
                min-height: auto;
                border-radius: 0 0 26px 26px;
            }

            .site-header {
                padding: 18px 18px 0;
            }

            .header-nav,
            .header-cta {
                display: none;
            }

            .menu-toggle {
                display: inline-flex;
            }

            .hero-layout {
                min-height: auto;
                gap: 22px;
                padding: 108px 18px 18px;
            }

            .hero-copy h1 {
                max-width: 100%;
                font-size: clamp(42px, 9vw, 68px);
            }

            .hero-copy p {
                font-size: 16px;
            }

            .hero-search-card {
                width: 100%;
                padding: 18px;
            }

            .hero-search-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-search-grid,
            .site-footer,
            .footer-links {
                grid-template-columns: 1fr;
            }

            .footer-bottom {
                align-items: flex-start;
                flex-direction: column;
            }

            .hero-benefits--in-hero {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .stats-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .about-card {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
                padding: 24px;
            }

            .about-copy {
                grid-column: 1;
                grid-row: 1;
                max-width: none;
            }

            .about-visual {
                grid-column: 1;
                grid-row: 3;
                height: clamp(320px, 65vw, 480px);
                max-height: 480px;
            }

            .about-visual img {
                height: 100%;
                object-position: center 25%;
            }

            .about-points {
                grid-column: 1;
                grid-row: 2;
            }

            .about-point {
                flex-direction: column;
                align-items: flex-start;
            }

            .widget-inner {
                padding: 26px 22px 28px;
            }

            .toolbar {
                flex-direction: column;
            }

            .hotel-card {
                flex-basis: calc((100% - 18px) / 2);
            }

            .directions-section {
                padding: 24px;
            }

            .directions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hits-grid,
            .tour-detail-hero,
            .tour-detail-sections,
            .tour-facts-inline {
                grid-template-columns: 1fr;
            }

            .nav-btn {
                width: 44px;
                height: 44px;
            }

            .gallery-grid,
            .facts-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .hero-benefits--in-hero {
                display: none !important;
            }

            .hero-benefits-section {
                display: block;
            }
        }

        @media (max-width: 640px) {
            .page-shell {
                padding-top: 0;
                padding-inline: 8px;
                padding-bottom: 44px;
            }

            .hero-stage {
                border-radius: 0 0 22px 22px;
            }

            .site-header {
                padding: 14px 14px 0;
            }

            .brand {
                gap: 0;
            }

            .brand-logo {
                min-width: 132px;
            }

            .brand-logo-main {
                font-size: 34px;
            }

            .brand-logo-sub {
                font-size: 10px;
                letter-spacing: 0.22em;
            }

            .brand-mark {
                width: 40px;
                height: 40px;
                border-radius: 14px;
                font-size: 15px;
            }

            .brand-text strong {
                font-size: 15px;
            }

            .brand-text span {
                font-size: 11px;
            }

            .menu-toggle {
                min-height: 42px;
                padding-inline: 14px;
                font-size: 13px;
            }

            .hero-layout {
                gap: 18px;
                padding: 88px 14px 14px;
            }

            .hero-copy {
                gap: 14px;
            }

            .hero-copy h1 {
                font-size: clamp(36px, 11vw, 56px);
                line-height: 0.92;
            }

            .hero-copy p {
                font-size: 15px;
            }

            .hero-search-card {
                gap: 14px;
                padding: 14px;
                border-radius: 18px;
            }

            .search-field select {
                min-height: 46px;
                padding-inline: 12px;
                font-size: 13px;
            }

            .hero-search-actions {
                align-items: stretch;
            }

            .hero-primary {
                width: 100%;
            }

            .site-footer {
                gap: 24px;
                margin-inline: -8px;
                margin-bottom: -44px;
                padding: 30px 18px 24px;
                border-radius: 0;
            }

            .footer-cta a {
                width: 100%;
            }

            .stats-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px 12px;
                border-radius: 16px;
            }

            .stat-card span {
                font-size: 14px;
            }

            .about-card {
                padding: 18px;
                border-radius: 18px;
            }

            .about-copy p {
                font-size: 15px;
            }

            .about-visual {
                height: clamp(300px, 65vw, 420px);
                max-height: 420px;
            }

            .widget-frame {
                border-radius: 20px;
            }

            .widget-inner {
                padding: 22px 16px 22px;
            }

            .meta-stack,
            .country-switcher {
                gap: 8px;
            }

            .country-switcher {
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 4px;
                scrollbar-width: none;
            }

            .country-switcher::-webkit-scrollbar {
                display: none;
            }

            .country-pill,
            .meta-pill {
                min-height: 40px;
                padding-inline: 12px;
                font-size: 12px;
            }

            .hotel-card {
                flex-basis: 86%;
            }

            .directions-section {
                padding: 20px 16px;
                border-radius: 20px;
            }

            .hits-section {
                padding: 20px 16px;
                border-radius: 20px;
            }

            .directions-head {
                align-items: flex-start;
            }

            .directions-grid {
                grid-template-columns: 1fr;
            }

            .hits-grid {
                grid-template-columns: 1fr;
            }

            .tour-detail-page {
                padding-inline: 8px;
            }

            .tour-detail-topbar,
            .tour-detail-cta {
                grid-template-columns: 1fr;
                flex-direction: column;
                align-items: stretch;
            }

            .detail-site-header {
                flex-direction: column;
                align-items: flex-start;
                margin-inline: -8px;
                padding: 14px 16px;
            }

            .detail-header-nav {
                margin-left: 0;
            }

            .tour-detail-media,
            .tour-detail-media img {
                min-height: 280px;
            }

            .tour-tabs {
                overflow-x: auto;
                flex-wrap: nowrap;
                top: 0;
                scrollbar-width: none;
            }

            .tour-tabs a {
                flex: 0 0 auto;
                min-height: 48px;
                padding: 13px 16px;
                white-space: nowrap;
            }

            .direction-top {
                flex-direction: column;
            }

            .direction-top h3 {
                font-size: 24px;
            }

            .direction-footer {
                flex-direction: column;
                align-items: stretch;
            }

            .nav-btn {
                display: none;
            }

            .swipe-note {
                font-size: 12px;
            }

            .detail-backdrop {
                padding: 0;
            }

            .detail-modal {
                width: 100%;
                max-height: 100vh;
                border-radius: 0;
            }

            .hotel-detail-shell {
                gap: 30px;
            }

            .hotel-detail-title h1 {
                font-size: clamp(30px, 9vw, 42px);
            }

            .hotel-location-line,
            .hotel-info-copy,
            .facility-list li,
            .booking-benefit-card p {
                font-size: 16px;
            }

            #tour-info {
                width: 100%;
                max-width: 100%;
            }

            #tour-info .detail-secondary-button {
                min-width: 0;
                width: auto !important;
            }

            .hotel-gallery-mosaic {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: minmax(100px, 120px) minmax(100px, 120px) minmax(72px, 88px);
                min-height: 0;
                max-height: none;
            }

            .best-offer-card,
            .advisor-cta {
                padding: 22px;
            }

            .best-offer-grid,
            .price-filter-row,
            .facility-groups-grid,
            .booking-benefits,
            .similar-grid {
                grid-template-columns: 1fr;
            }

            .facility-groups-grid {
                gap: 12px;
            }

            .facility-group-card {
                padding: 16px 16px 14px;
            }

            #best-offer .best-offer-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px 12px;
            }

            .mobile-menu {
                padding-left: 28px;
            }

            .mobile-menu-panel {
                width: 100%;
                padding-inline: 16px;
            }

            .detail-head,
            .detail-content {
                padding-inline: 16px;
            }

            .gallery-grid,
            .facts-grid {
                grid-template-columns: 1fr;
            }

            .flight-card {
                gap: 14px;
                padding: 16px;
            }

            .flight-route-line {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .flight-middle {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 10px;
                padding: 4px 0;
            }

            .flight-middle span:last-child {
                display: none;
            }

            .flight-point {
                padding-left: 52px;
            }

            .flight-point b {
                font-size: 28px;
            }

            .flight-airline {
                align-items: center;
            }

            .flight-airline strong {
                color: var(--text);
                font-size: 16px;
            }
        }

        /* ——— Каталог-пошук у hero (форма всередині картки, як раніше «швидкий підбір») ——— */
        .hero-search-card--catalog {
            position: relative;
        }

        .hero-search-card--catalog .hero-catalog-form {
            display: grid;
            gap: 14px;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
            backdrop-filter: none;
        }

        .hero-catalog-cta {
            display: flex;
            justify-content: flex-start;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 4px;
        }

        .hero-legacy-sync {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
            pointer-events: none;
        }

        .hero-catalog-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--accent);
            letter-spacing: 0;
        }

        .hero-catalog-grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 10px 12px;
            align-items: end;
        }

        .ps-field {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .hero-catalog-form .ps-field label.ps-label,
        .hero-catalog-form .ps-field span.ps-label {
            font-size: 11px;
            font-weight: 800;
            color: #5d6b87;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ps-field input,
        .ps-field select,
        .ps-field .ps-inputlike {
            min-height: 46px;
            padding: 0 12px;
            border-radius: var(--radius-md, 12px);
            border: 1px solid var(--line);
            background: #fff;
            font: inherit;
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
            transition: border-color .16s ease, box-shadow .16s ease, color .16s ease, background-color .16s ease;
        }

        .ps-inputlike {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            text-align: left;
            color: #1f2a44;
            -webkit-text-fill-color: #1f2a44;
        }

        .ps-inputlike:hover,
        .ps-inputlike:focus-visible {
            border-color: #b9c9e6;
            background: #ffffff;
            color: #1b2741;
            -webkit-text-fill-color: #1b2741;
            box-shadow: 0 0 0 2px rgba(25, 93, 198, 0.10);
        }

        .ps-inputlike:focus-visible {
            outline: 2px solid rgba(25, 93, 198, 0.45);
            outline-offset: 1px;
        }

        .ps-inputlike-label {
            display: block;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: inherit;
            -webkit-text-fill-color: currentColor;
        }

        .ps-inputlike-label.is-placeholder {
            color: #6a7691;
            font-weight: 600;
            -webkit-text-fill-color: #6a7691;
        }

        .ps-inputlike:hover .ps-inputlike-label.is-placeholder,
        .ps-inputlike:focus-visible .ps-inputlike-label.is-placeholder {
            color: #566483;
            -webkit-text-fill-color: #566483;
        }

        .ps-inputlike-chevron {
            flex: 0 0 auto;
            width: 16px;
            height: 16px;
            color: #5f6f90;
        }

        #ps-country-picker,
        #ps-country-picker:hover,
        #ps-country-picker:focus,
        #ps-country-picker:focus-visible,
        #ps-country-picker:active,
        #ps-from-picker,
        #ps-from-picker:hover,
        #ps-from-picker:focus,
        #ps-from-picker:focus-visible,
        #ps-from-picker:active {
            background: #ffffff !important;
            background-image: none !important;
            color: #1f2a44 !important;
            -webkit-text-fill-color: #1f2a44 !important;
            filter: none !important;
            text-shadow: none !important;
        }

        #ps-country-picker:hover,
        #ps-country-picker:focus,
        #ps-country-picker:focus-visible,
        #ps-country-picker:active,
        #ps-from-picker:hover,
        #ps-from-picker:focus,
        #ps-from-picker:focus-visible,
        #ps-from-picker:active {
            border-color: #b9c9e6 !important;
            box-shadow: 0 0 0 2px rgba(25, 93, 198, 0.10) !important;
        }

        .ps-country-wrap { grid-column: span 4; position: relative; min-width: 0; z-index: 9400; }
        .hero-search-card--catalog,
        .hero-search-card--catalog .hero-catalog-form,
        .hero-search-card--catalog .hero-catalog-grid { overflow: visible !important; }
        .hero-catalog-grid > .ps-field--grow:not(.ps-country-wrap) { grid-column: span 4; }
        .hero-catalog-grid > .ps-field--date { grid-column: span 2; }
        .hero-catalog-grid > .ps-field--narrow { grid-column: span 2; }
        .hero-catalog-grid > .ps-submit { grid-column: span 4; }

        @media (max-width: 720px) {
            .hero-catalog-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
            .ps-country-wrap { grid-column: 1 / -1; }
            .hero-catalog-grid > .ps-field--grow:not(.ps-country-wrap) { grid-column: 1 / -1; }
            .hero-catalog-grid > .ps-field--date { grid-column: span 3; }
            .hero-catalog-grid > .ps-field--narrow { grid-column: span 3; }
            .hero-catalog-grid > .ps-submit { grid-column: 1 / -1; }
        }

        .ps-hidden-select,
        .ps-country-native {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            margin: -1px !important;
            padding: 0 !important;
            border: 0 !important;
            clip: rect(0 0 0 0) !important;
            overflow: hidden !important;
            white-space: nowrap !important;
            pointer-events: none !important;
        }

        .ps-picker-backdrop {
            position: fixed;
            inset: 0;
            z-index: 12000;
            background: rgba(11, 18, 34, 0.44);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
        }

        .ps-picker-backdrop.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .ps-picker {
            position: fixed;
            top: 84px;
            left: 50%;
            width: min(1180px, calc(100vw - 30px));
            max-height: calc(100vh - 108px);
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #fff;
            box-shadow: 0 22px 44px rgba(13, 22, 45, 0.18);
            z-index: 12010;
            opacity: 0;
            pointer-events: none;
            transform: translateX(-50%) scale(.985);
            display: grid;
            grid-template-rows: auto auto minmax(0, 1fr) auto;
            overflow: hidden;
            transition: opacity .18s ease, transform .18s ease;
        }

        .ps-picker.is-open {
            opacity: 1;
            pointer-events: auto;
            transform: translateX(-50%) scale(1);
        }

        .ps-picker.is-mode-from .ps-picker-col--primary {
            display: none;
        }

        .ps-picker.is-mode-from .ps-picker-body {
            grid-template-columns: 1fr;
        }

        .ps-picker-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--line);
            background: #fff;
        }

        .ps-picker-back {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid #d7dfed;
            background: #ffffff;
            color: #2a344c;
            font-size: 30px;
            line-height: 1;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .ps-picker-back:hover,
        .ps-picker-back:focus-visible {
            background: #f4f7fd;
            border-color: #c4d0e5;
            color: #1b263f;
            outline: none;
        }

        .ps-picker-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #1b2741;
        }

        .ps-picker-close {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            border: 1px solid #d7dfed;
            background: #ffffff;
            color: #1f2a44;
            font-size: 22px;
            font-weight: 700;
            font-family: inherit;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background .16s ease, border-color .16s ease, color .16s ease, box-shadow .16s ease;
            -webkit-text-fill-color: #1f2a44;
        }

        .ps-picker-close:hover,
        .ps-picker-close:focus-visible {
            background: #f31624;
            border-color: #f31624;
            color: #ffffff;
            -webkit-text-fill-color: #ffffff;
            box-shadow: 0 8px 18px rgba(243, 22, 36, 0.25);
            outline: none;
        }

        .ps-picker-search-wrap {
            padding: 10px 16px;
            border-bottom: 1px solid var(--line);
            background: #fff;
        }

        .ps-picker-search {
            width: 100%;
            min-height: 44px;
            border-radius: 10px;
            border: 1px solid #cbd5ea;
            background: #fff;
            font: inherit;
            font-size: 15px;
            padding: 0 14px;
            color: #1f2a44;
        }

        .ps-picker-body {
            display: grid;
            grid-template-columns: minmax(300px, 0.92fr) minmax(0, 1fr);
            min-height: 0;
            overflow: hidden;
            background: #fff;
        }

        .ps-picker-col {
            min-height: 0;
            overflow: auto;
        }

        .ps-picker-col + .ps-picker-col {
            border-left: 1px solid var(--line);
        }

        .ps-picker-col-title {
            position: sticky;
            top: 0;
            z-index: 2;
            margin: 0;
            padding: 10px 14px;
            background: #f7faff;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7792;
            border-bottom: 1px solid var(--line);
        }

        .ps-picker-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .ps-picker-item {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #edf1f8;
            border-radius: 0;
            background: #fff;
            color: #1f2a44;
            font: inherit;
            font-size: 18px;
            font-weight: 700;
            text-align: left;
            cursor: pointer;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .ps-picker-item small {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            font-weight: 700;
            color: #7b869f;
        }

        .ps-hotel-item {
            align-items: flex-start;
            gap: 12px;
        }

        .ps-hotel-thumb {
            width: 64px;
            height: 48px;
            border-radius: 8px;
            flex: 0 0 auto;
            object-fit: cover;
            background: #e9eef9;
            border: 1px solid #dbe3f2;
        }

        .ps-hotel-body {
            min-width: 0;
            flex: 1;
            display: grid;
            gap: 3px;
        }

        .ps-hotel-name {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
            color: #1f2a44;
        }

        .ps-hotel-meta {
            font-size: 14px;
            font-weight: 600;
            color: #5f6c89;
            line-height: 1.35;
            white-space: normal;
        }

        .ps-hotel-stars {
            font-size: 14px;
            color: #f6b73c;
            letter-spacing: 0.04em;
            font-weight: 900;
        }

        .ps-picker-item:hover,
        .ps-picker-item:focus-visible {
            background: #f5f8ff;
            outline: none;
        }

        .ps-picker-item.is-active {
            background: #edf3ff;
            color: #12387e;
        }

        .ps-picker-arrow {
            flex: 0 0 auto;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            background: transparent;
            color: #55627f;
            font-size: 30px;
            line-height: 1;
            transition: color .16s ease, transform .16s ease;
        }

        .ps-picker-item:hover .ps-picker-arrow,
        .ps-picker-item:focus-visible .ps-picker-arrow {
            color: #1f2a44;
            transform: translateX(1px);
        }

        .ps-resort-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ps-from-meta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #7b7b7b;
            font-size: 16px;
            font-weight: 600;
            white-space: nowrap;
        }

        .ps-from-meta svg {
            width: 20px;
            height: 20px;
            color: #7b7b7b;
            flex: 0 0 auto;
        }

        .ps-resort-check {
            width: 26px;
            height: 26px;
            border-radius: 7px;
            border: 2px solid #d2d8e4;
            background: #fff;
            flex: 0 0 auto;
            position: relative;
        }

        .ps-resort-check.is-on {
            border-color: #2b9f5b;
            background: #2b9f5b;
        }

        .ps-resort-check.is-on::after {
            content: "";
            position: absolute;
            left: 7px;
            top: 2px;
            width: 6px;
            height: 13px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .ps-resort-group {
            border-bottom: 1px solid #edf1f8;
            background: #fff;
        }

        .ps-resort-group-head {
            width: 100%;
            border: 0;
            background: transparent;
            font: inherit;
            font-size: 17px;
            font-weight: 700;
            color: #1f2a44;
            text-align: left;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
        }

        .ps-resort-sublist {
            display: none;
            background: #fbfcff;
            border-top: 1px solid #edf1f8;
        }

        .ps-resort-group.is-open .ps-resort-sublist {
            display: block;
        }

        .ps-resort-subitem {
            width: 100%;
            border: 0;
            border-bottom: 1px solid #edf1f8;
            background: transparent;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            color: #1f2a44;
            text-align: left;
            padding: 12px 18px 12px 48px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .ps-resort-subitem:last-child {
            border-bottom: 0;
        }

        .ps-resort-subitem:hover,
        .ps-resort-group-head:hover {
            background: #f5f8ff;
        }

        .ps-picker-footer {
            padding: 10px 16px 14px;
            border-top: 1px solid var(--line);
            background: #fff;
            display: flex;
            justify-content: stretch;
        }

        .ps-picker-apply {
            min-height: 52px;
            border: 0;
            border-radius: 10px;
            width: 100%;
            padding: 0 18px;
            background: #f31624;
            color: #fff;
            -webkit-text-fill-color: #fff;
            font: inherit;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            box-shadow: 0 10px 24px rgba(243, 22, 36, 0.24);
            transition: filter .16s ease, transform .12s ease, box-shadow .16s ease;
        }

        .ps-picker-apply:hover,
        .ps-picker-apply:focus-visible {
            filter: brightness(1.04);
            transform: translateY(-1px);
            color: #fff;
            -webkit-text-fill-color: #fff;
            box-shadow: 0 12px 28px rgba(243, 22, 36, 0.3);
            outline: none;
        }

        .ps-picker #ps-picker-apply,
        button.ps-picker-apply#ps-picker-apply,
        .ps-picker #ps-picker-apply:hover,
        .ps-picker #ps-picker-apply:focus-visible,
        .ps-picker #ps-picker-apply:active,
        .ps-picker #ps-picker-apply:visited {
            background: #f31624 !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }

        .ps-picker-empty {
            margin: 0;
            padding: 16px 18px;
            color: #71809e;
            font-size: 14px;
            font-weight: 600;
        }

        @media (max-width: 980px) {
            .ps-picker-body {
                grid-template-columns: 1fr;
            }
            .ps-picker-col + .ps-picker-col {
                border-left: none;
                border-top: 1px solid var(--line);
            }
        }

        @media (max-width: 720px) {
            .ps-picker-backdrop {
                background: rgba(11, 18, 34, 0.46);
                opacity: 0;
                pointer-events: none;
                transition: opacity .2s ease;
            }
            .ps-picker-backdrop.is-open {
                opacity: 1;
                pointer-events: auto;
            }
            .ps-picker {
                width: 100vw;
                max-height: min(92vh, 92dvh);
                border-radius: 18px 18px 0 0;
                inset: auto 0 0 0;
                transform: translateY(100%);
                position: fixed;
                z-index: 12010;
                left: 0;
            }
            .ps-picker.is-open {
                transform: translateY(0);
            }
            .ps-picker-title {
                font-size: 20px;
            }
            .ps-picker-head {
                padding: 14px 16px;
            }
            .ps-picker-search-wrap {
                padding: 12px 16px;
            }
            .ps-picker-footer {
                padding: 12px 16px calc(14px + env(safe-area-inset-bottom, 0px));
            }
            .ps-picker-apply {
                width: 100%;
            }
            .ps-picker.is-mobile-step-countries .ps-picker-col--secondary {
                display: none;
            }
            .ps-picker.is-mobile-step-regions .ps-picker-col--primary {
                display: none;
            }
            .ps-picker.is-mobile-step-regions .ps-picker-back {
                display: inline-flex;
            }
            .ps-picker-item {
                font-size: 18px;
                padding: 18px 20px;
            }
            .ps-picker-arrow {
                width: 24px;
                height: 24px;
                font-size: 28px;
            }
            .ps-resort-group-head {
                font-size: 18px;
                padding: 14px 18px;
            }
            .ps-resort-subitem {
                font-size: 17px;
                padding: 12px 14px 12px 40px;
            }
            .ps-from-meta {
                font-size: 15px;
            }
            .ps-from-meta svg {
                width: 18px;
                height: 18px;
            }
            .ps-resort-check {
                width: 30px;
                height: 30px;
            }
            .ps-resort-check.is-on::after {
                left: 9px;
                top: 4px;
                width: 7px;
                height: 14px;
            }
        }

        .ps-submit {
            justify-self: stretch;
            min-height: 50px;
            padding: 0 22px;
            border: 0;
            border-radius: 14px;
            background: #f31624;
            color: #fff;
            font: inherit;
            font-size: 15px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(243, 22, 36, 0.28);
        }

        .ps-submit:hover {
            filter: brightness(1.05);
        }

        .ps-submit,
        .ps-submit:hover,
        .ps-submit:focus,
        .ps-submit:active {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            text-shadow: none !important;
            opacity: 1 !important;
        }

        .search-results-page {
            display: none;
            padding: 18px 0 48px;
        }

        .search-results-page.is-open {
            display: block;
        }

        .search-results-inner {
            width: min(1240px, 100%);
            margin: 0 auto;
            padding-inline: 0;
            display: grid;
            grid-template-columns: minmax(0, 260px) minmax(0, 1fr);
            gap: 22px 28px;
            align-items: start;
        }

        .search-filters {
            position: sticky;
            top: 18px;
            padding: 18px;
            border-radius: var(--radius-lg, 18px);
            border: 1px solid var(--line);
            background: #fff;
        }

        .search-filters-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
        }

        .search-filters-head h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
        }

        .search-filters-reset {
            border: 0;
            padding: 0;
            background: none;
            color: var(--accent);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-decoration: underline;
        }

        .filter-block {
            margin-top: 0;
            border-bottom: 1px solid var(--line);
            padding: 12px 0;
        }
        .filter-block:last-child { border-bottom: none; }

        .filter-label-row {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            background: none;
            border: none;
            padding: 4px 6px;
            margin: -4px -6px;
            border-radius: 8px;
            cursor: pointer;
            font: inherit;
            text-align: left;
            transition: background .15s;
        }
        .filter-label-row:hover { background: rgba(26,93,200,.07); }
        .filter-label-icon {
            width: 28px; height: 28px;
            border-radius: 8px;
            background: rgba(26,93,200,.1);
            color: var(--accent);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .filter-block > span.filter-label,
        .filter-label-row .filter-label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            flex: 1;
            margin: 0;
            text-transform: none;
            letter-spacing: 0;
        }
        .filter-toggle-icon {
            color: var(--muted);
            flex-shrink: 0;
            transition: transform .2s;
        }
        .filter-block.is-collapsed .filter-toggle-icon { transform: rotate(180deg); }
        .filter-block-body {
            margin-top: 12px;
            overflow: hidden;
            transition: max-height .25s ease;
        }
        .filter-block.is-collapsed .filter-block-body {
            max-height: 0 !important;
            margin-top: 0;
        }

        .filter-block input[type="number"] {
            min-height: 40px;
            padding: 0 10px;
            border-radius: 10px;
            border: 1px solid var(--line);
            font: inherit;
            font-weight: 600;
            font-size: 13px;
            width: 100%;
        }

        /* Budget range row */
        .sf-budget-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .sf-budget-row input { flex: 1; min-width: 0; }
        .sf-budget-row span { color: var(--muted); font-size: 13px; flex-shrink: 0; }

        /* Range slider */
        .sf-range-wrap {
            position: relative;
            height: 20px;
            margin-bottom: 4px;
        }
        .sf-range {
            -webkit-appearance: none;
            appearance: none;
            position: absolute;
            width: 100%;
            height: 4px;
            background: transparent;
            pointer-events: none;
            top: 50%;
            transform: translateY(-50%);
        }
        .sf-range::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 18px; height: 18px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid #fff;
            box-shadow: 0 1px 4px rgba(0,0,0,.2);
            cursor: pointer;
            pointer-events: all;
        }
        .sf-range::-moz-range-thumb {
            width: 16px; height: 16px;
            border-radius: 50%;
            background: var(--accent);
            border: 2px solid #fff;
            cursor: pointer;
            pointer-events: all;
        }
        .sf-range-track {
            position: absolute;
            height: 4px;
            background: var(--line);
            width: 100%;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 2px;
        }
        .sf-range-fill {
            position: absolute;
            height: 4px;
            background: var(--accent);
            top: 50%;
            transform: translateY(-50%);
            border-radius: 2px;
        }
        .sf-range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Chips */
        .sf-chip-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }
        .sf-chip-grid label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid var(--line);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            background: #f7f9fd;
            transition: all .15s;
            user-select: none;
        }
        .sf-chip-grid label:hover { border-color: var(--accent); background: rgba(26,93,200,.06); }
        .sf-chip-grid label:has(input:checked) {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .sf-chip-grid input { display: none; }

        .sf-chip-grid--col {
            flex-direction: column;
            gap: 6px;
        }
        .sf-chip-grid--col label {
            border-radius: 10px;
            justify-content: flex-start;
            width: 100%;
        }

        /* Star chips */
        .sf-star-chip-grid label span { display: flex; align-items: center; gap: 4px; }

        /* Hotel search */
        .sf-hotel-search-wrap {
            position: relative;
            margin-bottom: 8px;
        }
        .sf-hotel-search-input {
            width: 100%;
            padding: 9px 36px 9px 12px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font: inherit;
            font-size: 13px;
        }
        .sf-hotel-search-input:focus { outline: none; border-color: var(--accent); }
        .sf-hotel-search-icon {
            position: absolute;
            right: 10px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
        }
        .sf-hotel-list {
            max-height: 200px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
            scrollbar-width: thin;
        }
        .sf-hotel-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 4px;
            font-size: 13px;
            cursor: pointer;
            border-bottom: 1px solid var(--line);
            user-select: none;
        }
        .sf-hotel-list label:last-child { border-bottom: none; }
        .sf-hotel-list input[type="checkbox"] { flex-shrink: 0; accent-color: var(--accent); }

        .search-results-main {
            min-width: 0;
        }

        .search-back-browse {
            margin-bottom: 12px;
            padding: 10px 16px;
            border-radius: 12px;
            border: 1px solid var(--line);
            background: #fff;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            color: var(--accent);
            transition: border-color .18s, background .18s;
        }
        .search-back-browse:hover {
            border-color: var(--accent);
            background: rgba(26,93,200,.06);
        }

        .search-results-banner {
            margin-bottom: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            background: linear-gradient(90deg, rgba(26, 93, 200, 0.12), rgba(26, 93, 200, 0.04));
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
        }

        .search-results-count {
            margin: 0 0 14px;
            font-size: 22px;
            font-weight: 900;
        }
        .search-results-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0 0 14px;
        }
        .search-results-head .search-results-count {
            margin: 0;
        }
        .search-sort {
            margin-left: auto;
            min-width: 280px;
            max-width: 100%;
            min-height: 48px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            padding: 10px 14px;
            font: inherit;
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
        }

        .search-results-loading {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border: 1px solid #d7e2f4;
            border-radius: 12px;
            background: #fff;
            font-weight: 700;
            color: #3d4d6f;
        }

        .search-results-loading::before {
            content: "";
            width: 16px;
            height: 16px;
            border-radius: 999px;
            border: 2px solid #c8d8f3;
            border-top-color: #1a5dc8;
            animation: search-loader-spin .9s linear infinite;
        }

        /* Author `display` on .search-results-loading overrides the UA [hidden] rule — keep loader invisible when hidden. */
        .search-results-loading[hidden] {
            display: none !important;
        }

        @keyframes search-loader-spin {
            to { transform: rotate(360deg); }
        }

        body.anex-catalog-lite .hero-search-card--catalog,
        body.anex-catalog-lite #search-results-page {
            display: none !important;
        }

        .catalog-lite-note {
            display: none;
            margin-top: 18px;
            padding: 18px 20px;
            border: 1px solid rgba(0, 48, 135, 0.14);
            border-radius: 18px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(241, 246, 255, 0.98));
            box-shadow: 0 14px 34px rgba(18, 36, 74, 0.08);
        }

        body.anex-catalog-lite .catalog-lite-note {
            display: grid;
            gap: 12px;
        }

        .catalog-lite-note strong {
            display: block;
            color: #003087;
            font-size: 1.05rem;
        }

        .catalog-lite-note p {
            margin: 0;
            color: #50575e;
        }

        .catalog-lite-note-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .catalog-lite-note-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 16px;
            border-radius: 999px;
            background: #003087;
            color: #fff !important;
            font-weight: 800;
            text-decoration: none !important;
        }

        .catalog-lite-note-link.catalog-lite-note-link--ghost {
            background: rgba(0, 48, 135, 0.08);
            color: #003087 !important;
        }

        .ps-picker-item,
        .showcase-tab,
        .country-pill {
            color: #003087 !important;
        }

        .ps-picker-item.is-muted,
        .showcase-tab .tab-price {
            color: #50575e !important;
        }

        .search-result-row {
            display: grid;
            grid-template-columns: minmax(0, 200px) minmax(0, 1fr) minmax(0, 220px);
            gap: 16px 18px;
            padding: 16px;
            margin-bottom: 14px;
            border-radius: var(--radius-lg, 18px);
            border: 1px solid var(--line);
            background: #fff;
            align-items: stretch;
            transition: border-color .18s, transform .18s;
        }
        .search-result-row:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        .search-result-photo {
            border-radius: 14px;
            overflow: hidden;
            background: #e8eef7;
            min-height: 140px;
        }

        .search-result-photo img {
            width: 100%;
            height: 100%;
            min-height: 140px;
            object-fit: cover;
            display: block;
        }

        .search-result-body h3 {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 900;
        }

        .search-result-meta {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .search-result-offers-mini {
            font-size: 12px;
            color: #44506b;
            line-height: 1.45;
        }

        .search-result-side {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            gap: 6px;
            text-align: right;
        }

        .search-result-stay,
        .search-result-depart {
            font-size: 13px;
            color: #5f6c89;
            font-weight: 700;
            line-height: 1.25;
        }

        .search-result-price {
            font-size: 20px;
            font-weight: 900;
            color: var(--text);
        }

        .search-result-price-note {
            font-size: 13px;
            color: #5f6c89;
            font-weight: 800;
            line-height: 1.2;
        }

        .search-result-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 12px;
            background: #f31624;
            color: #fff !important;
            font-weight: 900;
            font-size: 14px;
            text-decoration: none !important;
            text-shadow: none;
        }

        .search-result-cta:hover,
        .search-result-cta:focus,
        .search-result-cta:active,
        .search-result-cta:visited {
            filter: brightness(1.06);
            color: #fff !important;
            text-decoration: none !important;
        }

        .search-result-cta:focus-visible {
            outline: 3px solid rgba(10, 25, 58, 0.24);
            outline-offset: 2px;
        }

        .search-results-pagination {
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-page-numbers {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .search-page-btn,
        .search-page-number {
            min-height: 40px;
            padding: 8px 14px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            color: var(--text);
            font: inherit;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }
        .search-page-btn:hover,
        .search-page-number:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: rgba(26,93,200,.06);
        }
        .search-page-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }
        .search-page-number.is-active {
            border-color: var(--accent);
            background: var(--accent);
            color: #fff;
        }

        /* Режим каталогу: лишаємо форму в hero, ховаємо лише текст і переваги */
        body.popular-search-open .hero-stage .hero-layout > .hero-copy,
        body.popular-search-open .hero-stage .hero-layout > .hero-benefits--in-hero {
            display: none !important;
        }

        body.popular-search-open .hero-stage .hero-layout {
            min-height: 0;
            align-content: start;
            padding-top: 96px;
            padding-bottom: 22px;
        }

        body.popular-search-open .hero-stage {
            min-height: 0;
            margin-bottom: 0;
        }

        body.popular-search-open .about-section,
        body.popular-search-open .directions-section,
        body.popular-search-open .hits-section,
        body.popular-search-open .hero-benefits-section {
            display: none !important;
        }

        body.popular-search-open #offers-section .toolbar,
        body.popular-search-open #offers-section .carousel-shell {
            display: none !important;
        }

        /* При пошуку — offers-section видно нижче результатів */
        body.popular-search-open #offers-section {
            display: block;
        }

        body.popular-search-open .site-footer {
            margin-top: 0;
        }

        /* ── Мобільні фільтри ── */
        .sf-mobile-bar {
            display: none;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .sf-mobile-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 44px;
            padding: 10px 18px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: #fff;
            font: inherit;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            color: var(--text);
            box-shadow: 0 4px 14px rgba(7,19,42,0.07);
        }

        .sf-mobile-count {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
        }

        .sf-mobile-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(7,19,42,0.44);
            z-index: 5000;
        }

        .sf-mobile-backdrop.is-open { display: block; }

        .search-filters-drawer {
            position: fixed;
            inset: auto 0 0 0;
            max-height: 82dvh;
            overflow-y: auto;
            z-index: 5100;
            background: #fff;
            border-radius: 22px 22px 0 0;
            padding: 20px 18px 32px;
            box-shadow: 0 -14px 40px rgba(7,19,42,0.18);
            transform: translateY(110%);
            transition: transform 0.32s cubic-bezier(0.34, 1.1, 0.64, 1);
        }

        .search-filters-drawer.is-open {
            transform: translateY(0);
        }

        .sf-drawer-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .sf-drawer-head h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
        }

        .sf-drawer-close {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--line);
            background: none;
            cursor: pointer;
            font-size: 20px;
            line-height: 1;
            color: var(--text);
        }

        @media (max-width: 820px) {
            .sf-mobile-bar { display: flex; }

            /* Sidebar приховуємо — фільтри тепер в drawer */
            .search-filters { display: none; }

            .search-results-inner {
                grid-template-columns: 1fr;
            }

            .search-result-row {
                grid-template-columns: 1fr;
            }

            .search-result-side {
                align-items: flex-start;
                text-align: left;
            }
            .search-results-pagination {
                justify-content: flex-start;
            }
            .search-sort {
                width: 100%;
                min-width: 0;
                margin-left: 0;
            }
        }
    </style>
<?php if (!$_anex_embed): ?>
</head>
<body class="<?php echo esc_attr( trim( ( $detail_tour_key !== '' ? 'tour-detail-view ' : '' ) . ( ! empty( $anex_catalog_lite ) ? 'anex-catalog-lite' : '' ) ) ); ?>">
<?php endif; ?>
    <main class="page-shell anex-embed-root">
        <section class="hero-stage">
            <div class="hero-video" aria-hidden="true">
                <video autoplay muted loop playsinline preload="metadata" poster="<?php echo esc_url($hero_video_poster); ?>">
                    <source src="<?php echo esc_url($hero_video_url); ?>" type="video/mp4">
                </video>
            </div>

            <header class="site-header">
                <a class="brand" href="<?php echo esc_url(home_url('/' . ITTOUR_HOTELS_WIDGET_PAGE_SLUG . '/')); ?>">
                    <span class="brand-logo" aria-label="Anex Tour Львів">
                        <span class="brand-logo-main">anex</span>
                        <span class="brand-logo-sub" style="color:#e53535;font-size:13px;letter-spacing:0">♥</span>
                        <span class="brand-logo-sub" style="letter-spacing:1px">Tour</span>
                    </span>
                </a>

                <nav class="header-nav" aria-label="Основна навігація">
                    <a href="#offers-section">Тури</a>
                    <a href="#popular-directions-title">Країни</a>
                    <a href="#about-service">Про нас</a>
                    <a href="tel:+380979451781">Контакти</a>
                </nav>

                <div style="display:flex;align-items:center;gap:10px">
                    <div style="display:flex;flex-direction:column;text-align:right;line-height:1.3">
                        <a class="header-cta" href="tel:+380979451781" style="font-size:14px;font-weight:700">+380979451781</a>
                        <span style="font-size:11px;color:#4ade80;font-weight:500">● Ми завжди онлайн</span>
                    </div>
                </div>
                <button type="button" class="menu-toggle" id="menu-toggle" aria-expanded="false" aria-controls="mobile-menu">Меню</button>
            </header>

            <div class="hero-layout">
                <div class="hero-copy">
                    <span class="eyebrow">Офіційне франчайзингове агентство Anex Tour</span>
                    <h1>Відкривайте світ разом з Anex Tour Львів</h1>
                    <p>
                        Підберемо ідеальний відпочинок під ваш бюджет.
                        Оберіть країну й місто виїзду — покажемо актуальні готелі та ціни.
                    </p>
                    <div class="catalog-lite-note">
                        <div>
                            <strong>Каталог працює як вітрина актуальних напрямків</strong>
                            <p>Ми прибрали важкий пошук із цієї сторінки. Обирайте країну нижче, переглядайте готелі та залишайте заявку на конкретну пропозицію.</p>
                        </div>
                        <div class="catalog-lite-note-actions">
                            <a class="catalog-lite-note-link" href="#offers-section">Перейти до добірки</a>
                            <a class="catalog-lite-note-link catalog-lite-note-link--ghost" href="#popular-directions-title">Популярні напрямки</a>
                        </div>
                    </div>
                </div>

                <div class="hero-search-card hero-search-card--catalog">
                    <form class="hero-catalog-form" id="popular-search-form" autocomplete="off">
                        <p class="hero-catalog-title">Пошук туру в каталозі</p>
                        <div class="hero-catalog-grid">
                            <div class="ps-field ps-field--grow ps-country-wrap">
                                <span class="ps-label" id="ps-country-label">Країна, курорт, готель</span>
                                <input type="hidden" id="ps-country-id" value="">
                                <input type="hidden" id="ps-region-id" value="">
                                <input type="hidden" id="ps-hotel-id" value="">
                                <input type="text" id="ps-country-q" class="ps-country-native" tabindex="-1" aria-hidden="true">
                                <button type="button" id="ps-country-picker" class="ps-inputlike" aria-labelledby="ps-country-label" aria-haspopup="dialog" aria-controls="ps-picker">
                                    <span class="ps-inputlike-label is-placeholder" id="ps-country-picker-label">Країна, курорт, готель</span>
                                    <svg class="ps-inputlike-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                            <div class="ps-field ps-field--grow">
                                <label class="ps-label" for="ps-from">Звідки</label>
                                <select id="ps-from" class="ps-hidden-select"></select>
                                <input type="hidden" id="ps-from-ids" value="">
                                <button type="button" id="ps-from-picker" class="ps-inputlike" aria-label="Оберіть місто вильоту" aria-haspopup="dialog" aria-controls="ps-picker">
                                    <span class="ps-inputlike-label is-placeholder" id="ps-from-picker-label">Оберіть країну призначення</span>
                                    <svg class="ps-inputlike-chevron" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                            </div>
                            <div class="ps-field ps-field--date">
                                <label class="ps-label" for="ps-d1">Дата від</label>
                                <input id="ps-d1" type="text" inputmode="numeric" placeholder="дд.мм.рр" maxlength="8" aria-label="Дата початку туру">
                            </div>
                            <div class="ps-field ps-field--date">
                                <label class="ps-label" for="ps-d2">Дата до</label>
                                <input id="ps-d2" type="text" inputmode="numeric" placeholder="дд.мм.рр" maxlength="8" aria-label="Дата кінця туру">
                            </div>
                            <div class="ps-field ps-field--narrow">
                                <label class="ps-label" for="ps-n1">Ночей від</label>
                                <input id="ps-n1" type="text" inputmode="numeric" maxlength="2" placeholder="6">
                            </div>
                            <div class="ps-field ps-field--narrow">
                                <label class="ps-label" for="ps-n2">Ночей до</label>
                                <input id="ps-n2" type="text" inputmode="numeric" maxlength="2" placeholder="8">
                            </div>
                            <div class="ps-field ps-field--narrow">
                                <label class="ps-label" for="ps-adults">Дорослих</label>
                                <select id="ps-adults" class="ps-native-select" aria-label="Кількість дорослих">
                                    <option value="1">1</option>
                                    <option value="2" selected>2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                            <div class="ps-field ps-field--narrow">
                                <label class="ps-label" for="ps-children">Дітей</label>
                                <select id="ps-children" class="ps-native-select" aria-label="Кількість дітей">
                                    <option value="0" selected>0</option>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                </select>
                            </div>
                            <button type="submit" class="ps-submit">Шукати</button>
                        </div>
                    </form>
                    <div class="hero-legacy-sync" aria-hidden="true">
                        <div class="search-field">
                            <label for="country-select">Країна</label>
                            <select id="country-select"></select>
                        </div>
                        <div class="search-field">
                            <label for="departure-select">Місто виїзду</label>
                            <select id="departure-select"></select>
                        </div>
                        <div class="country-switcher" id="country-switcher"></div>
                        <span class="meta-pill" id="window-pill"></span>
                        <span class="meta-pill" id="departure-pill"></span>
                        <span class="meta-pill" id="hero-selected-country"></span>
                        <div class="hero-search-caption" id="hero-search-caption"></div>
                    </div>
                </div>

                <div class="ps-picker-backdrop" id="ps-picker-backdrop"></div>
                <section class="ps-picker" id="ps-picker" role="dialog" aria-modal="true" aria-labelledby="ps-picker-title" aria-hidden="true">
                    <div class="ps-picker-head">
                        <button type="button" class="ps-picker-back" id="ps-picker-back" aria-label="Назад">‹</button>
                        <h3 class="ps-picker-title" id="ps-picker-title">Країна, курорт, готель</h3>
                        <button type="button" class="ps-picker-close" id="ps-picker-close" aria-label="Закрити">×</button>
                    </div>
                    <div class="ps-picker-search-wrap">
                        <input type="text" class="ps-picker-search" id="ps-picker-search" placeholder="Пошук за назвою країни або міста" autocomplete="off">
                    </div>
                    <div class="ps-picker-body">
                        <div class="ps-picker-col ps-picker-col--primary">
                            <p class="ps-picker-col-title">Країна призначення</p>
                            <ul class="ps-picker-list" id="ps-country-list"></ul>
                        </div>
                        <div class="ps-picker-col ps-picker-col--secondary">
                            <p class="ps-picker-col-title" id="ps-secondary-title">Курорти</p>
                            <ul class="ps-picker-list" id="ps-from-list"></ul>
                        </div>
                    </div>
                    <div class="ps-picker-footer">
                        <button type="button" class="ps-picker-apply" id="ps-picker-apply">Обрати</button>
                    </div>
                </section>

                <div class="hero-benefits hero-benefits--in-hero" aria-label="Коротко про сервіс">
                    <article class="hero-benefit">
                        <span>Досвід</span>
                        <strong>10+ років</strong>
                        <p>Підбираємо тури так, щоб рішення було швидким і впевненим.</p>
                    </article>
                    <article class="hero-benefit">
                        <span>Клієнти</span>
                        <strong>2 500+</strong>
                        <p>Супроводжуємо бронювання від першого кліку до підтвердження.</p>
                    </article>
                    <article class="hero-benefit">
                        <span>Напрямки</span>
                        <strong>40+</strong>
                        <p>Показуємо актуальні пропозиції по країнах, які зараз у попиті.</p>
                    </article>
                    <article class="hero-benefit">
                        <span>Комфорт</span>
                        <strong>1 000+</strong>
                        <p>Щороку допомагаємо знаходити зручні маршрути та готелі для відпочинку.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="about-section">
            <article class="about-card" id="about-service">
                <div class="about-copy">
                    <h2>Чому саме ми?</h2>
                    <p>
                        Офіційне франчайзингове агентство Anex Tour. Ми знаємо як зробити
                        ваш відпочинок незабутнім і підбираємо тур під будь-який бюджет.
                    </p>
                </div>
                <div class="about-points">
                    <article class="about-point">
                        <span class="about-point-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                        </span>
                        <p class="about-point-text"><strong>Надійно</strong> — офіційне франчайзингове агентство Anex Tour.</p>
                    </article>
                    <article class="about-point">
                        <span class="about-point-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </span>
                        <p class="about-point-text"><strong>10+ років досвіду</strong> — знаємо, що вам запропонувати.</p>
                    </article>
                    <article class="about-point">
                        <span class="about-point-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
                        </span>
                        <p class="about-point-text"><strong>24/7</strong> — завжди на зв'язку, завжди поряд.</p>
                    </article>
                    <article class="about-point">
                        <span class="about-point-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        </span>
                        <p class="about-point-text"><strong>Офіс у Львові</strong> — вул. Героїв УПА, 6. Завітайте особисто.</p>
                    </article>
                </div>
                <figure class="about-visual">
                    <img src="<?php echo esc_url($about_image_url); ?>" alt="Менеджер Anex Tour Львів допомагає з бронюванням подорожі" loading="lazy" width="600" height="800">
                    <figcaption class="about-badge">
                        <strong>Підтримка на кожному етапі</strong>
                        <span>Від підбору туру до фінального підтвердження</span>
                    </figcaption>
                </figure>
            </article>
        </section>

        <section class="hero-benefits-section" aria-label="Коротко про сервіс">
            <div class="hero-benefits hero-benefits--below">
                <article class="hero-benefit">
                    <span>Досвід</span>
                    <strong>10+ років</strong>
                    <p>Підбираємо тури так, щоб рішення було швидким і впевненим.</p>
                </article>
                <article class="hero-benefit">
                    <span>Клієнти</span>
                    <strong>2 500+</strong>
                    <p>Супроводжуємо бронювання від першого кліку до підтвердження.</p>
                </article>
                <article class="hero-benefit">
                    <span>Напрямки</span>
                    <strong>40+</strong>
                    <p>Показуємо актуальні пропозиції по країнах, які зараз у попиті.</p>
                </article>
                <article class="hero-benefit">
                    <span>Підтримка</span>
                    <strong>24/7</strong>
                    <p>Щороку допомагаємо знаходити зручні маршрути та готелі для відпочинку.</p>
                </article>
            </div>
        </section>

        <div class="mobile-menu" id="mobile-menu" hidden>
            <div class="mobile-menu-panel" role="dialog" aria-modal="true" aria-labelledby="mobile-menu-title">
                <div class="mobile-menu-head">
                    <div class="brand-text">
                        <strong id="mobile-menu-title">Anex Tour Львів</strong>
                        <span>Офіційне агентство Anex Tour</span>
                    </div>
                    <button type="button" class="menu-close" id="menu-close" aria-label="Закрити меню">×</button>
                </div>

                <nav class="mobile-nav">
                    <a href="#offers-section">Добірка готелів</a>
                    <a href="#popular-directions-title">Популярні напрямки</a>
                    <a href="#about-service">Що робить нас особливими</a>
                </nav>

                <div class="mobile-card" id="contacts-menu">
                    <strong>Контакти</strong>
                    <a href="tel:+380979451781">+380979451781</a>
                    <a href="mailto:info@anextour.lviv.ua">info@anextour.lviv.ua</a>
                    <span>Щодня 09:00–20:00</span>
                    <span>Львів, вул. Героїв УПА, 6</span>
                </div>

                <div class="mobile-card">
                    <strong>Соцмережі</strong>
                    <div class="mobile-socials">
                        <a class="social-link" href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8Zm4.2 3.2A4.8 4.8 0 1 1 12 16.8a4.8 4.8 0 0 1 0-9.6Zm0 2A2.8 2.8 0 1 0 12 14.8a2.8 2.8 0 0 0 0-5.6Zm5-2.35a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3Z"/></svg>
                        </a>
                        <a class="social-link" href="https://t.me/" target="_blank" rel="noopener" aria-label="Telegram">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 4.18 18.35 19.5c-.24 1.08-.9 1.34-1.82.84l-4.96-3.66-2.39 2.3c-.26.26-.49.49-1 .49l.35-5.05 9.2-8.31c.4-.36-.09-.56-.62-.2L5.74 13.07.84 11.54c-1.06-.33-1.08-1.06.22-1.57L20.2 2.6c.89-.32 1.67.22 1.4 1.58Z"/></svg>
                        </a>
                        <a class="social-link" href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.05 8.45V6.9c0-.75.5-.93.85-.93h2.15V2.17L14.08 2.15c-3.3 0-4.05 2.47-4.05 4.05v2.25H7.42v3.92h2.61V22h4.02v-9.63h3.38l.45-3.92h-3.83Z"/></svg>
                        </a>
                        <a class="social-link" href="https://www.viber.com/" target="_blank" rel="noopener" aria-label="Viber">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.2 2.9A10.7 10.7 0 0 0 6.8 2.9C3.7 3.8 2 6.2 2 9.7v3.2c0 3.3 1.5 5.5 4.3 6.5v2.2c0 .5.6.8 1 .4l2.3-2.1h3.8c5.3 0 8.6-2.9 8.6-8.3V9.7c0-3.5-1.7-5.9-4.8-6.8Zm2.7 8.7c0 4.2-2.3 6.2-6.5 6.2H9.1l-.6.1-1 .9v-.9l-1.1-.3c-1.7-.6-2.5-2-2.5-4.6V9.7c0-2.6 1-4.1 3.4-4.8a8.7 8.7 0 0 1 9.4 0c2.3.7 3.2 2.2 3.2 4.8v1.9Zm-5.1 3.6c-.7-.2-1.5-.6-2.4-1.2-.8-.6-1.5-1.3-2.1-2.1-.6-.9-1-1.7-1.2-2.4-.1-.4 0-.8.3-1.1l.8-.7c.2-.2.6-.2.8.1l1 1.4c.2.2.2.5 0 .8l-.3.5c.3.6.7 1.1 1.2 1.5.4.5.9.9 1.5 1.2l.5-.3c.3-.2.6-.2.8 0l1.4 1c.3.2.3.6.1.8l-.7.8c-.3.3-.7.4-1.1.3Z"/></svg>
                        </a>
                    </div>
                </div>

                <p class="mobile-note">Актуальні контакти, месенджери й години роботи можна оновити в одному місці шаблону.</p>
            </div>
        </div>

        <section class="search-results-page" id="search-results-page" hidden>
            <div class="search-results-inner">
                <aside class="search-filters" id="search-filters-aside" aria-label="Фільтри пошуку">
                    <div class="search-filters-head">
                        <h2>Фільтри</h2>
                        <button type="button" class="search-filters-reset" id="search-filters-reset">Скинути все</button>
                    </div>

                    <!-- Рейтинг готелю -->
                    <div class="filter-block" id="fb-rating">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                            </span>
                            <span class="filter-label">Рейтинг готелю</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-chip-grid" id="sf-rating-chips">
                                <label><input type="checkbox" name="sf_rating" value="1-5"><span>1-5</span></label>
                                <label><input type="checkbox" name="sf_rating" value="6"><span>6</span></label>
                                <label><input type="checkbox" name="sf_rating" value="7"><span>7</span></label>
                                <label><input type="checkbox" name="sf_rating" value="8"><span>8</span></label>
                                <label><input type="checkbox" name="sf_rating" value="9+"><span>9+</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- Бюджет -->
                    <div class="filter-block" id="fb-budget">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                            </span>
                            <span class="filter-label">Бюджет</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-budget-row">
                                <input type="number" id="sf-price-min" min="0" step="500" value="0" aria-label="Мінімальна ціна" placeholder="0 грн">
                                <span>-</span>
                                <input type="number" id="sf-price-max" min="0" step="500" value="200000" aria-label="Максимальна ціна" placeholder="200 000 гр">
                            </div>
                            <div class="sf-range-wrap">
                                <div class="sf-range-track"><div class="sf-range-fill" id="sf-range-fill"></div></div>
                                <input type="range" id="sf-range-min" min="0" max="200000" step="500" value="0" class="sf-range sf-range-min">
                                <input type="range" id="sf-range-max" min="0" max="200000" step="500" value="200000" class="sf-range sf-range-max">
                            </div>
                            <div class="sf-range-labels"><span id="sf-range-label-min">0 грн</span><span>∞</span></div>
                        </div>
                    </div>

                    <!-- Категорія готелю (зірки) -->
                    <div class="filter-block" id="fb-stars">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="#f59e0b" width="16" height="16"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                            </span>
                            <span class="filter-label">Категорія готелю</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-chip-grid sf-star-chip-grid" id="sf-star-filters">
                                <label><input type="checkbox" name="sf_star" value="2"><span><svg viewBox="0 0 24 24" fill="#f59e0b" width="13" height="13"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> 2</span></label>
                                <label><input type="checkbox" name="sf_star" value="3"><span><svg viewBox="0 0 24 24" fill="#f59e0b" width="13" height="13"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> 3</span></label>
                                <label><input type="checkbox" name="sf_star" value="4"><span><svg viewBox="0 0 24 24" fill="#f59e0b" width="13" height="13"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> 4</span></label>
                                <label><input type="checkbox" name="sf_star" value="5"><span><svg viewBox="0 0 24 24" fill="#f59e0b" width="13" height="13"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg> 5</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- Харчування -->
                    <div class="filter-block" id="fb-meals">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-4.5-6.72-5-8.99-5-2.28 0-9.01.5-9.01 5h18zM8.09 2.2 8.05 1h1.97l-.04 1.2C8.97 2.33 8.09 2.2 8.09 2.2z"/></svg>
                            </span>
                            <span class="filter-label">Харчування</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-chip-grid" id="sf-meal-chips"></div>
                        </div>
                    </div>

                    <!-- Готелі (пошук по назві) -->
                    <div class="filter-block filter-block--collapsible" id="fb-hotels">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/></svg>
                            </span>
                            <span class="filter-label">Готелі</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-hotel-search-wrap">
                                <input type="text" id="sf-hotel-search" placeholder="Пошук готелю" class="sf-hotel-search-input" autocomplete="off">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" class="sf-hotel-search-icon"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                            </div>
                            <div class="sf-hotel-list" id="sf-hotel-list"></div>
                        </div>
                    </div>

                    <!-- Пляж -->
                    <div class="filter-block filter-block--collapsible" id="fb-beach">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M13.127 14.56l1.43-1.43 6.44 6.443L19.57 21zm4.293-5.73l2.86-2.86c-3.95-3.95-10.35-3.96-14.3-.02 3.93-1.3 8.31-.25 11.44 2.88zM5.95 5.98c-3.94 3.95-3.93 10.35.02 14.3l2.86-2.86C5.7 14.29 4.65 9.91 5.95 5.98zm-.02-.02-.01.01c-.38 3.01.67 6.07 3.09 8.49l8.49-8.49c-2.42-2.42-5.48-3.47-8.49-3.09L2.47 0 1.06 1.41l4.87 4.55z"/></svg>
                            </span>
                            <span class="filter-label">Пляж</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-chip-grid sf-chip-grid--col" id="sf-beach-chips">
                                <label><input type="checkbox" name="sf_beach" value="Безвітряна бухта"><span>Безвітряна бухта</span></label>
                                <label><input type="checkbox" name="sf_beach" value="Перша лінія"><span>Перша лінія</span></label>
                                <label><input type="checkbox" name="sf_beach" value="Друга лінія"><span>Друга лінія</span></label>
                                <label><input type="checkbox" name="sf_beach" value="Третя лінія і далі"><span>Третя лінія і далі</span></label>
                                <label><input type="checkbox" name="sf_beach" value="Кораловий риф"><span>Кораловий риф</span></label>
                                <label><input type="checkbox" name="sf_beach" value="Піщаний пляж"><span>Піщаний пляж</span></label>
                                <label><input type="checkbox" name="sf_beach" value="Піщано-гальковий пляж"><span>Піщано-гальковий пляж</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- В готелі -->
                    <div class="filter-block filter-block--collapsible" id="fb-inhotel">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8h5z"/></svg>
                            </span>
                            <span class="filter-label">В готелі</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-chip-grid sf-chip-grid--col" id="sf-inhotel-chips">
                                <label><input type="checkbox" name="sf_inhotel" value="Готель для дорослих"><span>Готель для дорослих</span></label>
                                <label><input type="checkbox" name="sf_inhotel" value="Велика територія"><span>Велика територія</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- Спорт і розваги -->
                    <div class="filter-block filter-block--collapsible" id="fb-sports">
                        <button type="button" class="filter-label-row" data-filter-toggle>
                            <span class="filter-label-icon">
                                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29l-1.43-1.43z"/></svg>
                            </span>
                            <span class="filter-label">Спорт і розваги</span>
                            <svg class="filter-toggle-icon" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M7 14l5-5 5 5H7z"/></svg>
                        </button>
                        <div class="filter-block-body">
                            <div class="sf-chip-grid sf-chip-grid--col" id="sf-sports-chips">
                                <label><input type="checkbox" name="sf_sports" value="Аквапарк або гірки"><span>Аквапарк або гірки</span></label>
                            </div>
                        </div>
                    </div>

                    <!-- hidden operator list (збережено для JS) -->
                    <div id="sf-operator-list" style="display:none"></div>
                </aside>
                <div class="search-results-main">
                    <button type="button" class="search-back-browse" id="search-back-browse">← Назад до підбору</button>
                    <div class="sf-mobile-bar">
                        <button type="button" class="sf-mobile-toggle" id="sf-mobile-toggle">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 5h2V3H3v2zm0 8h2v-2H3v2zm0 8h2v-2H3v2zm4-16v2h14V5H7zm0 10h14v-2H7v2zm0 8h14v-2H7v2z"/></svg>
                            Фільтри
                        </button>
                        <span class="sf-mobile-count" id="sf-mobile-count"></span>
                    </div>
                    <div class="search-results-banner" id="search-results-banner">Шукаємо тури у багатьох туроператорів…</div>
                    <div class="search-results-head">
                        <h2 class="search-results-count" id="search-results-count"></h2>
                        <select id="search-sort" class="search-sort" aria-label="Сортування результатів">
                            <option value="recommended">Спочатку рекомендовані</option>
                            <option value="price_asc">Спочатку дешевше</option>
                            <option value="price_desc">Спочатку дорожче</option>
                            <option value="name_asc">Назва А-Я</option>
                            <option value="stars_desc">Рейтинг готелю</option>
                        </select>
                    </div>
                    <div class="search-results-loading" id="search-results-loading" hidden>Завантаження…</div>
                    <div class="search-results-list" id="search-results-list"></div>
                    <div class="search-results-pagination" id="search-results-pagination" hidden>
                        <button type="button" class="search-page-btn" id="search-page-prev">← Попередня</button>
                        <div class="search-page-numbers" id="search-page-numbers"></div>
                        <button type="button" class="search-page-btn" id="search-page-next">Наступна →</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="widget-frame" id="offers-section">
            <div class="widget-inner">
                <div class="toolbar">
                    <div class="toolbar-copy">
                        <h2>Гарячі тури</h2>
                        <p>Актуальні готелі по найпопулярніших країнах — оновлюються автоматично.</p>
                    </div>
                </div>
                <div class="country-showcase" id="country-showcase">
                    <div class="showcase-skeleton" id="showcase-skeleton">
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="directions-section" aria-labelledby="popular-directions-title">
            <div class="directions-head">
                <div>
                    <h2 id="popular-directions-title">Популярні напрямки</h2>
                    <p>
                        Країни та курорти, які зараз найчастіше трапляються в актуальних пропозиціях.
                        Блок оновлюється за актуальними пропозиціями, щоб ви бачили дійсно живу картину попиту.
                    </p>
                </div>
            </div>
            <div class="directions-grid" id="directions-grid"></div>
        </section>

        <section class="hits-section" aria-labelledby="tour-hits-title">
            <div class="hits-head">
                <div>
                    <h2 id="tour-hits-title">Хіти турів</h2>
                    <p>Добірки для різних сценаріїв відпочинку: море, сімейна поїздка, короткий переліт або premium-готелі. Оберіть і ми відкриємо актуальні пропозиції.</p>
                </div>
            </div>
            <div class="hits-grid">
                <button class="hit-card" type="button" data-country="318">
                    <span>Море</span>
                    <strong>Туреччина all inclusive</strong>
                    <p>Готелі з сильним сервісом, пляжем і зручним вильотом.</p>
                </button>
                <button class="hit-card" type="button" data-country="338">
                    <span>Сім'я</span>
                    <strong>Єгипет для відпочинку взимку</strong>
                    <p>Тепле море, короткий переліт і великий вибір готелів.</p>
                </button>
                <button class="hit-card" type="button" data-country="372">
                    <span>Європа</span>
                    <strong>Греція з красивими курортами</strong>
                    <p>Спокійний формат, острови та готелі для пар і сімей.</p>
                </button>
                <button class="hit-card" type="button" data-country="16">
                    <span>Premium</span>
                    <strong>ОАЕ для міського відпочинку</strong>
                    <p>Готелі високого рівня, шопінг і комфортна логістика.</p>
                </button>
            </div>
        </section>

        <section class="tour-detail-page" id="tour-detail-page" aria-live="polite">
            <header class="detail-site-header">
                <a class="brand" href="<?php echo esc_url(home_url('/' . ITTOUR_HOTELS_WIDGET_PAGE_SLUG . '/')); ?>">
                    <span class="brand-logo" aria-label="Anex Tour Львів">
                        <span class="brand-logo-main">anex</span>
                        <span class="brand-logo-sub" style="letter-spacing:1px">Tour</span>
                    </span>
                </a>
                <nav class="detail-header-nav" aria-label="Навігація сторінки туру">
                    <a href="<?php echo esc_url($detail_back_url); ?>#offers-section">Пошук турів</a>
                    <a href="tel:+380979451781">Контакти</a>
                </nav>
                <a class="tour-back-link" href="<?php echo esc_url($detail_back_url); ?>">Повернутися до підбору</a>
            </header>
            <div class="detail-loading" id="detail-loading">Завантаження деталей туру…</div>
            <div class="detail-content" id="detail-content" hidden></div>
        </section>

        <footer class="site-footer">
            <div class="footer-brand">
                <a class="brand" href="<?php echo esc_url(home_url('/' . ITTOUR_HOTELS_WIDGET_PAGE_SLUG . '/')); ?>">
                    <span class="brand-logo" aria-label="Anex Tour Львів">
                        <span class="brand-logo-main">anex</span>
                        <span class="brand-logo-sub" style="letter-spacing:1px">Tour</span>
                    </span>
                </a>
                <p>Підбираємо тури з актуальними цінами, перевіряємо деталі вильоту та супроводжуємо бронювання до підтвердження.</p>
                <div class="footer-cta">
                    <a class="footer-cta-primary" href="tel:+380979451781">Зателефонувати</a>
                    <a class="footer-cta-secondary" href="https://t.me/" target="_blank" rel="noopener">Написати в Telegram</a>
                </div>
            </div>

            <div class="footer-links">
                <div class="footer-col">
                    <strong>Навігація</strong>
                    <a href="#offers-section">Актуальні готелі</a>
                    <a href="#popular-directions-title">Популярні напрямки</a>
                    <a href="#about-service">Про сервіс</a>
                </div>

                <div class="footer-col">
                    <strong>Контакти</strong>
                    <a href="tel:+380979451781">+380979451781</a>
                    <a href="mailto:info@anextour.lviv.ua">info@anextour.lviv.ua</a>
                    <p>Щодня 09:00-20:00</p>
                </div>

                <div class="footer-col">
                    <strong>Допомога</strong>
                    <a href="#offers-section">Підібрати тур</a>
                    <a href="#about-service">Умови бронювання</a>
                    <a href="tel:+380979451781">Консультація менеджера</a>
                </div>
            </div>

            <div class="footer-bottom">
                <span>Anex Tour Львів. Офіційне франчайзингове агентство. Підберемо ідеальний відпочинок під ваш бюджет.</span>
                <div class="footer-socials" aria-label="Соцмережі">
                    <a href="https://www.instagram.com/" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.8 2h8.4A5.8 5.8 0 0 1 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8A5.8 5.8 0 0 1 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2Zm0 2A3.8 3.8 0 0 0 4 7.8v8.4A3.8 3.8 0 0 0 7.8 20h8.4a3.8 3.8 0 0 0 3.8-3.8V7.8A3.8 3.8 0 0 0 16.2 4H7.8Zm4.2 3.2A4.8 4.8 0 1 1 12 16.8a4.8 4.8 0 0 1 0-9.6Zm0 2A2.8 2.8 0 1 0 12 14.8a2.8 2.8 0 0 0 0-5.6Zm5-2.35a1.15 1.15 0 1 1 0 2.3 1.15 1.15 0 0 1 0-2.3Z"/></svg>
                    </a>
                    <a href="https://t.me/" target="_blank" rel="noopener" aria-label="Telegram">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.6 4.18 18.35 19.5c-.24 1.08-.9 1.34-1.82.84l-4.96-3.66-2.39 2.3c-.26.26-.49.49-1 .49l.35-5.05 9.2-8.31c.4-.36-.09-.56-.62-.2L5.74 13.07.84 11.54c-1.06-.33-1.08-1.06.22-1.57L20.2 2.6c.89-.32 1.67.22 1.4 1.58Z"/></svg>
                    </a>
                    <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.05 8.45V6.9c0-.75.5-.93.85-.93h2.15V2.17L14.08 2.15c-3.3 0-4.05 2.47-4.05 4.05v2.25H7.42v3.92h2.61V22h4.02v-9.63h3.38l.45-3.92h-3.83Z"/></svg>
                    </a>
                </div>
            </div>
        </footer>
    </main>

    <!-- Mobile filter drawer -->
    <div class="sf-mobile-backdrop" id="sf-mobile-backdrop"></div>
    <div class="search-filters-drawer" id="search-filters-drawer" aria-label="Фільтри пошуку" role="dialog" aria-modal="true">
        <div class="sf-drawer-head">
            <h2>Фільтри</h2>
            <button type="button" class="sf-drawer-close" id="sf-drawer-close" aria-label="Закрити фільтри">×</button>
        </div>
        <div id="sf-drawer-body">
            <!-- буде заповнено JS з aside#search-filters-aside -->
        </div>
        <div style="margin-top:18px">
            <button type="button" class="search-filters-reset" id="sf-drawer-reset" style="font:inherit;font-size:14px;font-weight:800;color:var(--muted);background:none;border:1px solid var(--line);border-radius:10px;padding:10px 18px;cursor:pointer">Скинути всі фільтри</button>
        </div>
    </div>

    <div class="booking-backdrop" id="booking-backdrop" hidden>
        <div class="booking-modal" role="dialog" aria-modal="true" aria-labelledby="booking-title">
            <div class="booking-head">
                <div>
                    <h3 id="booking-title">Заявка на бронювання</h3>
                    <p id="booking-tour-name">Менеджер перевірить тур і звʼяжеться з вами.</p>
                </div>
                <button type="button" class="booking-close" id="booking-close" aria-label="Закрити">×</button>
            </div>
            <div class="booking-offer-summary" id="booking-offer-summary" hidden>
                <div class="bos-row" id="bos-date-row" hidden><span class="bos-label">Дата вильоту</span><span class="bos-value" id="bos-date"></span></div>
                <div class="bos-row" id="bos-city-row" hidden><span class="bos-label">Звідки</span><span class="bos-value" id="bos-city"></span></div>
                <div class="bos-row" id="bos-nights-row" hidden><span class="bos-label">Тривалість</span><span class="bos-value" id="bos-nights"></span></div>
                <div class="bos-row" id="bos-room-row" hidden><span class="bos-label">Номер</span><span class="bos-value" id="bos-room"></span></div>
                <div class="bos-row" id="bos-meal-row" hidden><span class="bos-label">Харчування</span><span class="bos-value" id="bos-meal"></span></div>
                <div class="bos-row" id="bos-price-row" hidden><span class="bos-label">Вартість</span><span class="bos-value bos-price" id="bos-price"></span></div>
                <div class="bos-row" id="bos-operator-row" hidden style="display:none!important"><span class="bos-label">Туроператор</span><span class="bos-value" id="bos-operator"></span></div>
            </div>
            <form class="booking-form" id="booking-form">
                <input type="hidden" name="tour_key" id="booking-tour-key" value="">
                <input type="hidden" name="tour_title" id="booking-tour-title" value="">
                <input type="hidden" name="tour_date" id="booking-tour-date" value="">
                <input type="hidden" name="tour_city" id="booking-tour-city" value="">
                <input type="hidden" name="tour_nights" id="booking-tour-nights" value="">
                <input type="hidden" name="tour_room" id="booking-tour-room" value="">
                <input type="hidden" name="tour_meal" id="booking-tour-meal" value="">
                <input type="hidden" name="tour_price" id="booking-tour-price" value="">
                <input type="hidden" name="tour_operator" id="booking-tour-operator" value="">
                <input type="hidden" name="page_url" value="<?php echo esc_url((is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '')); ?>">
                <label>
                    Ваше імʼя
                    <input type="text" name="name" autocomplete="name" required>
                </label>
                <label>
                    Телефон
                    <input type="tel" name="phone" autocomplete="tel" required placeholder="+380">
                </label>
                <label>
                    Email
                    <input type="email" name="email" autocomplete="email">
                </label>
                <label>
                    Коментар
                    <textarea name="message" placeholder="Побажання щодо туру, кількість туристів або зручний час дзвінка"></textarea>
                </label>
                <button type="submit" class="booking-submit">Відправити заявку</button>
                <p class="booking-status" id="booking-status"></p>
                <div class="booking-success-card" id="booking-success-card" hidden>
                    <strong>Заявку прийнято</strong>
                    <span>Менеджер перевірить актуальність пропозиції та звʼяжеться з вами найближчим часом.</span>
                </div>
            </form>
        </div>
    </div>

    <script>
    (() => {
        const ajaxUrl = <?php echo wp_json_encode($ajax_url); ?>;
        const nonce = <?php echo wp_json_encode($nonce); ?>;
        const SEARCH_V2_ENDPOINT = <?php echo wp_json_encode( $search_v2_endpoint, JSON_UNESCAPED_SLASHES ); ?>;
        const SEARCH_V2_REST_NONCE = <?php echo wp_json_encode( $search_v2_rest_nonce ); ?>;
        const SEARCH_V2_SHADOW_ENABLED = <?php echo wp_json_encode( $search_v2_shadow_enabled ); ?>;
        const ANEX_AGENCY_TELEGRAM = <?php echo wp_json_encode( $anex_agency_telegram, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;
        const ANEX_AGENCY_VIBER = <?php echo wp_json_encode( $anex_agency_viber, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>;
        const IMG_BASE = 'https://www.ittour.com.ua/';
        const ALL_COUNTRIES = <?php echo wp_json_encode($all_countries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map((country) => ({
            ...country,
            id: String(country.id),
        }));
        const FEATURED_COUNTRIES = <?php echo wp_json_encode($featured_countries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>.map((country) => ({
            ...country,
            id: String(country.id),
        }));
        const DEFAULT_COUNTRY_ID = FEATURED_COUNTRIES[0] ? FEATURED_COUNTRIES[0].id : (ALL_COUNTRIES[0] ? ALL_COUNTRIES[0].id : '');
        const PS_FROM_MAX_SELECT = 3;
        const KYIV_CITY_ID = '2014';
        const SEARCH_WINDOW_LIMIT = 2;
        const SEARCH_MIN_SHOW_HOTELS = 1;
        const DEPARTURE_CANDIDATE_LIMIT = 2;
        const OFFERS_VISIBLE_LIMIT = 6;
        const SEARCH_RESULTS_PER_PAGE = 24;
        const POPULAR_SEARCH_BATCH_HOTELS = 24;
        const EXCURSION_DISPLAY_CAP = 48;
        const COUNTRY_PLACEHOLDER_TEXT = 'Всі країни';
        const FROM_PLACEHOLDER_TEXT = 'Оберіть до 3 міст';
        const DETAIL_TOUR_KEY = <?php echo wp_json_encode($detail_tour_key); ?>;
        const DETAIL_HOTEL_ID = <?php echo wp_json_encode($detail_hotel_id); ?>;
        const DETAIL_BASE_URL = <?php echo wp_json_encode($detail_back_url); ?>;
        const CATALOG_BASE_URL = <?php echo wp_json_encode($catalog_base_url); ?>;
        const HOTEL_DETAIL_NAV_BASE = <?php echo wp_json_encode($hotel_detail_nav_base); ?>;
        const EXCURSION_DETAIL_NAV_BASE = <?php echo wp_json_encode($excursion_detail_nav_base); ?>;
        const SITE_HOME_URL = <?php echo wp_json_encode($site_home_url); ?>;
        const ABOUT_IMAGE_URL = <?php echo wp_json_encode($about_image_url); ?>;
        const PRESET_SEARCH   = <?php echo wp_json_encode($preset_country ? ['countryId' => $preset_country] : null); ?>;
        const ANEX_CATALOG_LITE = window.ANEX_CATALOG_LITE === true;
        const countryMetaById = new Map(ALL_COUNTRIES.map((country) => [country.id, country]));
        const apiMemoryCache = new Map();
        const apiPending = new Map();
        const searchCache = new Map();
        const windowCache = new Map();
        const departureCache = new Map();
        const hotelThumbCache = new Map();
        const hotelThumbPending = new Map();
        let activeCountryId = DEFAULT_COUNTRY_ID;
        let activeDepartureId = '';

        const track = document.getElementById('carousel-track');
        const countrySwitcher = document.getElementById('country-switcher');
        const countrySelect = document.getElementById('country-select');
        const departureSelect = document.getElementById('departure-select');
        const windowPill = document.getElementById('window-pill');
        const departurePill = document.getElementById('departure-pill');
        const heroSelectedCountry = document.getElementById('hero-selected-country');
        const selectedCountryPill = document.getElementById('selected-country-pill');
        const heroOpenOffers = document.getElementById('hero-open-offers');
        const heroSearchCaption = document.getElementById('hero-search-caption');
        const navPrev = document.getElementById('nav-prev');
        const navNext = document.getElementById('nav-next');
        const detailLoading = document.getElementById('detail-loading');
        const detailContent = document.getElementById('detail-content');
        const directionsGrid = document.getElementById('directions-grid');
        const offersSection = document.getElementById('offers-section');
        const popularSearchForm = document.getElementById('popular-search-form');
        const psCountryId = document.getElementById('ps-country-id');
        const psRegionId = document.getElementById('ps-region-id');
        const psHotelId = document.getElementById('ps-hotel-id');
        const psCountryQ = document.getElementById('ps-country-q');
        const psCountryPicker = document.getElementById('ps-country-picker');
        const psCountryPickerLabel = document.getElementById('ps-country-picker-label');
        const psFrom = document.getElementById('ps-from');
        const psFromIds = document.getElementById('ps-from-ids');
        const psFromPicker = document.getElementById('ps-from-picker');
        const psFromPickerLabel = document.getElementById('ps-from-picker-label');
        const psD1 = document.getElementById('ps-d1');
        const psD2 = document.getElementById('ps-d2');
        const psN1 = document.getElementById('ps-n1');
        const psN2 = document.getElementById('ps-n2');
        const psAdults = document.getElementById('ps-adults');
        const psChildren = document.getElementById('ps-children');
        const psPickerBackdrop = document.getElementById('ps-picker-backdrop');
        const psPicker = document.getElementById('ps-picker');
        const psPickerTitle = document.getElementById('ps-picker-title');
        const psPickerBack = document.getElementById('ps-picker-back');
        const psPickerClose = document.getElementById('ps-picker-close');
        const psPickerSearch = document.getElementById('ps-picker-search');
        const psCountryList = document.getElementById('ps-country-list');
        const psFromList = document.getElementById('ps-from-list');
        const psSecondaryTitle = document.getElementById('ps-secondary-title');
        const psPickerApply = document.getElementById('ps-picker-apply');
        const searchResultsPage = document.getElementById('search-results-page');
        const searchBackBrowse = document.getElementById('search-back-browse');
        const searchResultsList = document.getElementById('search-results-list');
        const searchSort = document.getElementById('search-sort');
        const searchResultsPagination = document.getElementById('search-results-pagination');
        const searchPagePrev = document.getElementById('search-page-prev');
        const searchPageNext = document.getElementById('search-page-next');
        const searchPageNumbers = document.getElementById('search-page-numbers');
        const searchResultsCount = document.getElementById('search-results-count');
        const searchResultsLoading = document.getElementById('search-results-loading');
        const searchResultsBanner = document.getElementById('search-results-banner');
        const sfPriceMax = document.getElementById('sf-price-max');
        const sfPriceMin = document.getElementById('sf-price-min');
        const sfRangeMin = document.getElementById('sf-range-min');
        const sfRangeMax = document.getElementById('sf-range-max');
        const sfStarFilters = document.getElementById('sf-star-filters');
        const sfMealChips = document.getElementById('sf-meal-chips');
        const sfOperatorList = document.getElementById('sf-operator-list');
        const sfRatingChips = document.getElementById('sf-rating-chips');
        const sfHotelSearch = document.getElementById('sf-hotel-search');
        const sfHotelList = document.getElementById('sf-hotel-list');
        const searchFiltersReset = document.getElementById('search-filters-reset');

        function hardenPickerInputsVisual() {
            const nodes = [document.getElementById('ps-country-picker'), document.getElementById('ps-from-picker')];
            nodes.forEach((node) => {
                if (!node) return;
                node.style.setProperty('background', '#ffffff', 'important');
                node.style.setProperty('background-image', 'none', 'important');
                node.style.setProperty('color', '#1f2a44', 'important');
                node.style.setProperty('-webkit-text-fill-color', '#1f2a44', 'important');
            });
        }

        // ── Filter collapsible toggles ──
        document.querySelectorAll('[data-filter-toggle]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const block = btn.closest('.filter-block');
                if (block) block.classList.toggle('is-collapsed');
            });
        });

        // ── Budget range slider sync ──
        function syncRangeTrack() {
            if (!sfRangeMin || !sfRangeMax) return;
            const min = parseInt(sfRangeMin.value, 10);
            const max = parseInt(sfRangeMax.value, 10);
            const pMin = (min / 200000) * 100;
            const pMax = (max / 200000) * 100;
            const fill = document.getElementById('sf-range-fill');
            if (fill) { fill.style.left = pMin + '%'; fill.style.width = (pMax - pMin) + '%'; }
            const labelMin = document.getElementById('sf-range-label-min');
            if (labelMin) labelMin.textContent = new Intl.NumberFormat('uk-UA').format(min) + ' грн';
        }
        if (sfRangeMin) sfRangeMin.addEventListener('input', function() {
            if (sfPriceMin) sfPriceMin.value = sfRangeMin.value;
            if (parseInt(sfRangeMin.value) > parseInt(sfRangeMax.value)) sfRangeMax.value = sfRangeMin.value;
            popularSearchState.page = 1;
            syncRangeTrack(); applySearchClientFiltersAndRender();
        });
        if (sfRangeMax) sfRangeMax.addEventListener('input', function() {
            if (sfPriceMax) sfPriceMax.value = sfRangeMax.value;
            if (parseInt(sfRangeMax.value) < parseInt(sfRangeMin.value)) sfRangeMin.value = sfRangeMax.value;
            popularSearchState.page = 1;
            syncRangeTrack(); applySearchClientFiltersAndRender();
        });
        if (sfPriceMin) sfPriceMin.addEventListener('change', function() {
            popularSearchState.page = 1;
            if (sfRangeMin) sfRangeMin.value = sfPriceMin.value; syncRangeTrack(); applySearchClientFiltersAndRender();
        });
        if (sfPriceMax) sfPriceMax.addEventListener('change', function() {
            popularSearchState.page = 1;
            if (sfRangeMax) sfRangeMax.value = sfPriceMax.value; syncRangeTrack(); applySearchClientFiltersAndRender();
        });
        syncRangeTrack();

        // ── Hotel search filter ──
        if (sfHotelSearch) sfHotelSearch.addEventListener('input', function() {
            const q = sfHotelSearch.value.trim().toLowerCase();
            if (!sfHotelList) return;
            sfHotelList.querySelectorAll('label').forEach(function(lbl) {
                const txt = lbl.textContent.toLowerCase();
                lbl.style.display = (!q || txt.includes(q)) ? '' : 'none';
            });
        });
        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuClose = document.getElementById('menu-close');
        const bookingBackdrop = document.getElementById('booking-backdrop');
        const bookingClose = document.getElementById('booking-close');
        const bookingForm = document.getElementById('booking-form');
        const bookingStatus = document.getElementById('booking-status');
        const bookingSuccessCard = document.getElementById('booking-success-card');
        const bookingTourName = document.getElementById('booking-tour-name');
        const bookingTourKey = document.getElementById('booking-tour-key');
        const bookingTourTitle = document.getElementById('booking-tour-title');
        const bookingOfferSummary = document.getElementById('booking-offer-summary');

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('.best-offer-lead-backdrop:not([hidden])').forEach((backdrop) => {
                backdrop.hidden = true;
            });
        });

        document.addEventListener('click', (event) => {
            const scrollBtn = event.target.closest && event.target.closest('.detail-scroll-to-prices');
            if (scrollBtn) {
                event.preventDefault();
                const pricesSection = document.getElementById('tour-prices');
                if (pricesSection) {
                    pricesSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                return;
            }
            const bestOfferLeadOpen = event.target.closest && event.target.closest('.best-offer-lead-open');
            if (bestOfferLeadOpen) {
                event.preventDefault();
                const card = bestOfferLeadOpen.closest('#best-offer');
                const backdrop = card && card.querySelector('.best-offer-lead-backdrop');
                if (backdrop) {
                    backdrop.hidden = false;
                    const first = backdrop.querySelector('input[name="name"]');
                    if (first) {
                        window.setTimeout(() => first.focus(), 0);
                    }
                }
                return;
            }
            const bestOfferLeadClose = event.target.closest && event.target.closest('.best-offer-lead-close');
            if (bestOfferLeadClose) {
                event.preventDefault();
                const backdrop = bestOfferLeadClose.closest('.best-offer-lead-backdrop');
                if (backdrop) {
                    backdrop.hidden = true;
                }
                return;
            }
            if (event.target && event.target.classList && event.target.classList.contains('best-offer-lead-backdrop')) {
                event.target.hidden = true;
                return;
            }
            const bookingBtn = event.target.closest && event.target.closest('.booking-open');
            if (!bookingBtn) {
                return;
            }
            event.preventDefault();
            const tourKey = bookingBtn.getAttribute('data-tour-key') || DETAIL_TOUR_KEY;
            const fromAttr = bookingBtn.getAttribute('data-tour-title');
            const detailTitleEl = document.querySelector('.hotel-detail-title h1');
            const tourTitle =
                (fromAttr && fromAttr.trim()) ||
                (detailTitleEl && detailTitleEl.textContent ? detailTitleEl.textContent.trim() : '') ||
                '';
            const offerData = {
                date: bookingBtn.getAttribute('data-tour-date') || '',
                city: bookingBtn.getAttribute('data-tour-city') || '',
                nights: bookingBtn.getAttribute('data-tour-nights') || '',
                room: bookingBtn.getAttribute('data-tour-room') || '',
                meal: bookingBtn.getAttribute('data-tour-meal') || '',
                price: bookingBtn.getAttribute('data-tour-price') || '',
                operator: bookingBtn.getAttribute('data-tour-operator') || '',
            };
            openBookingForm(tourKey, tourTitle, offerData);
        });
        document.addEventListener('submit', (event) => {
            const form = event.target && event.target.closest && event.target.closest('.advisor-lead-form');
            if (!form) {
                return;
            }
            event.preventDefault();
            const status = form.querySelector('.advisor-lead-status');
            submitLeadFromForm(form, status);
        });

        function esc(value) {
            const node = document.createElement('div');
            node.textContent = value == null ? '' : String(value);
            return node.innerHTML;
        }

        function escAttr(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;');
        }

        function decodeHtml(value) {
            const node = document.createElement('textarea');
            node.innerHTML = value == null ? '' : String(value);
            return node.value;
        }

        function stripHtml(value) {
            const node = document.createElement('div');
            node.innerHTML = value == null ? '' : String(value);
            return (node.textContent || '').trim();
        }

        function fixMediaUrl(value) {
            if (!value || typeof value !== 'string') {
                return '';
            }
            let url = value.trim();
            if (url.startsWith('//')) {
                url = 'https:' + url;
            }
            if (url.startsWith('http://')) {
                url = 'https://' + url.slice(7);
            }
            if (!/^https?:\/\//i.test(url)) {
                url = IMG_BASE.replace(/\/$/, '') + '/' + url.replace(/^\//, '');
            }
            return url;
        }

        function apiError(data) {
            return data && typeof data === 'object' && !Array.isArray(data) && data.error
                ? data.error_desc || data.error
                : '';
        }

        function apiErrorCode(data) {
            if (!data || typeof data !== 'object' || Array.isArray(data)) {
                return 0;
            }
            return Number(data.error_code || 0) || 0;
        }

        function showApiLimitMessage(code, fallback) {
            const messages = {
                107: 'Пошук тимчасово заблокований. Спробуйте пізніше.',
                108: 'Перевищено ліміт пошуку за годину. Спробуйте пізніше.',
                109: 'Перевищено ліміт пошуку на день. Спробуйте завтра.',
            };
            const text = messages[code] || fallback || messages[108];
            if (searchResultsBanner) {
                searchResultsBanner.textContent = text;
            }
            if (searchResultsList) {
                searchResultsList.innerHTML = '<p class="error-state">' + esc(text) + '</p>';
            }
            if (searchResultsLoading) {
                searchResultsLoading.hidden = true;
            }
            if (searchResultsPagination) {
                searchResultsPagination.hidden = true;
            }
        }

        function offerHasTransport(offer) {
            if (!offer || typeof offer !== 'object') {
                return false;
            }
            if (Number(offer.type) === 2) {
                return false;
            }
            const transport = String(offer.transport_type || '').toLowerCase();
            if (transport === 'flight' || transport === 'bus') {
                return true;
            }
            const flights = offer.flights;
            if (flights && ((flights.from && flights.from.length) || (flights.to && flights.to.length))) {
                return true;
            }
            return false;
        }

        function transportOnlyOffers(offers) {
            return (offers || []).filter((offer) => offerHasTransport(offer));
        }

        function stayOnlyOffers(offers) {
            return (offers || []).filter((offer) => !offerHasTransport(offer));
        }

        function hasTransportOffers(offers) {
            return transportOnlyOffers(offers).length > 0;
        }

        function transportIncludedLabel(offer) {
            return offerHasTransport(offer) ? 'Пакетний тур' : 'Лише проживання';
        }

        function minPriceWithTransport(cards) {
            const prices = (cards || [])
                .filter((card) => card && card.hasTransport === true)
                .map((card) => Number(card.priceUAH || 0))
                .filter((price) => price > 0);
            return prices.length ? Math.min.apply(null, prices) : Infinity;
        }

        function setSearchResultsLoading(active) {
            if (!searchResultsLoading) {
                return;
            }
            searchResultsLoading.hidden = !active;
            if (active) {
                searchResultsLoading.removeAttribute('hidden');
                try {
                    searchResultsLoading.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                } catch (e) {}
            } else {
                searchResultsLoading.setAttribute('hidden', '');
            }
        }

        function stableQuery(query) {
            const input = query || {};
            return Object.keys(input).sort().reduce((carry, key) => {
                carry[key] = input[key];
                return carry;
            }, {});
        }

        function apiCacheTtl(path) {
            if (path.startsWith('module/params/destinations')) {
                return 10 * 60 * 1000;
            }
            if (path.startsWith('module/search-list')) {
                return 2 * 60 * 60 * 1000;
            }
            if (path.startsWith('module/params') || path.startsWith('dictionary/')) {
                return 24 * 60 * 60 * 1000;
            }
            if (path.startsWith('tour/') || path.startsWith('tour-excursion/') || path.startsWith('hotel/')) {
                return 6 * 60 * 60 * 1000;
            }
            if (path.startsWith('showcase/')) {
                return 30 * 60 * 1000;
            }
            return 60 * 60 * 1000;
        }

        function apiCacheKey(path, query) {
            return path + '::' + JSON.stringify(stableQuery(query));
        }

        function readBrowserCache(key) {
            try {
                const raw = sessionStorage.getItem('ittour:' + key);
                if (!raw) {
                    return null;
                }
                const cached = JSON.parse(raw);
                if (!cached || Date.now() > Number(cached.expires || 0)) {
                    sessionStorage.removeItem('ittour:' + key);
                    return null;
                }
                return cached.data;
            } catch (error) {
                return null;
            }
        }

        function writeBrowserCache(key, data, ttl) {
            try {
                sessionStorage.setItem('ittour:' + key, JSON.stringify({
                    expires: Date.now() + ttl,
                    data,
                }));
            } catch (error) {
            }
        }

        function unwrapApiPayload(data) {
            if (data && typeof data === 'object' && !Array.isArray(data) && Object.prototype.hasOwnProperty.call(data, 'data')) {
                const code = Number(data.code || 0);
                const inner = data.data;
                if (code || (inner && typeof inner === 'object')) {
                    return inner;
                }
            }
            return data;
        }

        async function api(path, query, method) {
            const httpMethod = String(method || 'GET').toUpperCase();
            const useCache = httpMethod === 'GET';
            const cacheKey = apiCacheKey(httpMethod + ':' + path, query || {});
            if (useCache) {
                const memoryHit = apiMemoryCache.get(cacheKey);
                if (memoryHit && Date.now() < memoryHit.expires) {
                    return memoryHit.data;
                }

                const browserHit = readBrowserCache(cacheKey);
                if (browserHit) {
                    const normalizedHit = unwrapApiPayload(browserHit);
                    apiMemoryCache.set(cacheKey, {
                        expires: Date.now() + apiCacheTtl(path),
                        data: normalizedHit,
                    });
                    return normalizedHit;
                }
            }

            if (apiPending.has(cacheKey)) {
                return apiPending.get(cacheKey);
            }

            const body = new URLSearchParams();
            body.set('action', 'ittour_lab_public');
            body.set('nonce', nonce);
            body.set('path', path);
            body.set('lang', 'uk');
            body.set('method', httpMethod);
            body.set('query', JSON.stringify(query || {}));

            const request = fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body,
                })
                .then((response) => response.json())
                .then((payload) => {
                    if (!payload.success) {
                        throw new Error((payload.data && payload.data.message) || 'Помилка проксі-запиту');
                    }
                    const data = unwrapApiPayload(payload.data && payload.data.data);
                    if (apiError(data)) {
                        const apiIssue = new Error(apiError(data));
                        apiIssue.code = apiErrorCode(data);
                        throw apiIssue;
                    }
                    if (useCache) {
                        const ttl = apiCacheTtl(path);
                        apiMemoryCache.set(cacheKey, {
                            expires: Date.now() + ttl,
                            data,
                        });
                        writeBrowserCache(cacheKey, data, ttl);
                    }
                    return data;
                })
                .finally(() => {
                    apiPending.delete(cacheKey);
                });

            apiPending.set(cacheKey, request);
            return request;
        }

        function formatApiDate(date) {
            const dd = String(date.getDate()).padStart(2, '0');
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const yy = String(date.getFullYear()).slice(-2);
            return dd + '.' + mm + '.' + yy;
        }

        function formatHumanDate(value) {
            if (!value) {
                return '—';
            }
            if (/^\d{2}\.\d{2}\.\d{2}$/.test(value)) {
                const [dd, mm, yy] = value.split('.');
                return dd + '.' + mm + '.20' + yy;
            }
            if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
                const [yy, mm, dd] = value.split('-');
                return dd + '.' + mm + '.' + yy;
            }
            return value;
        }

        function formatMoneyUAH(value) {
            if (value == null || Number.isNaN(Number(value))) {
                return '—';
            }
            return new Intl.NumberFormat('uk-UA').format(Math.round(Number(value))) + ' грн';
        }

        function starsMarkup(rating) {
            const count = Math.min(5, Math.max(0, parseInt(rating, 10) || 0));
            return count ? '★'.repeat(count) : '★★★';
        }

        function reviewLabel(rate) {
            const value = Number(rate);
            if (!value) {
                return 'Є відгуки';
            }
            if (value >= 9) {
                return 'Чудово';
            }
            if (value >= 8.5) {
                return 'Блискуче';
            }
            if (value >= 8) {
                return 'Дуже добре';
            }
            if (value >= 7) {
                return 'Добре';
            }
            if (value >= 6) {
                return 'Непогано';
            }
            return 'Є відгуки';
        }

        function buildCandidateWindows() {
            const base = new Date();
            base.setHours(12, 0, 0, 0);
            const offsets = [14, 21, 28, 35, 42, 49, 56, 63];

            return offsets.slice(0, SEARCH_WINDOW_LIMIT).map((offset) => {
                const start = new Date(base);
                start.setDate(start.getDate() + offset);

                const end = new Date(start);
                end.setDate(end.getDate() + 7);

                return {
                    date_from: formatApiDate(start),
                    date_till: formatApiDate(end),
                };
            });
        }

        function requestBase(countryId, window, fromCityId) {
            const query = {
                type: '1',
                kind: '1',
                country: countryId,
                adult_amount: '2',
                child_amount: '0',
                hotel_rating: '3:78',
                night_from: '5',
                night_till: '9',
                date_from: window.date_from,
                date_till: window.date_till,
                items_per_page: '24',
                hotel_info: '1',
                hotel_image: '1',
                currency: '2',
            };
            if (fromCityId) {
                query.from_city = String(fromCityId);
            }
            return query;
        }

        function dedupeHotels(offers) {
            const seen = new Set();
            const unique = [];

            for (const offer of offers || []) {
                const hotelId = String(offer.hotel_id || offer.hotel || Math.random());
                if (seen.has(hotelId)) {
                    continue;
                }
                seen.add(hotelId);
                unique.push(offer);
            }

            return unique;
        }

        function sortHotels(offers) {
            return [...offers].sort((left, right) => {
                const leftCount = Number(left.hotel_review_count || 0);
                const rightCount = Number(right.hotel_review_count || 0);
                if (leftCount !== rightCount) {
                    return rightCount - leftCount;
                }

                const leftRate = Number(left.hotel_review_rate || 0);
                const rightRate = Number(right.hotel_review_rate || 0);
                if (leftRate !== rightRate) {
                    return rightRate - leftRate;
                }

                const leftPrice = Number((left.prices && left.prices['2']) || left.price || 0);
                const rightPrice = Number((right.prices && right.prices['2']) || right.price || 0);
                if (leftPrice !== rightPrice) {
                    return leftPrice - rightPrice;
                }

                return String(left.hotel || '').localeCompare(String(right.hotel || ''), 'uk');
            });
        }

        function pickImage(offer) {
            const candidates = []
                .concat((offer && offer.hotel_images) || [])
                .concat((offer && offer.images) || []);
            const main = candidates.find((item) => String(item.is_main) === '1' || item.is_main === 1);
            const image = main || candidates[0];
            if (typeof image === 'string') {
                return fixMediaUrl(image);
            }
            return image ? fixMediaUrl(image.full || image.web || image.thumb || image.url || image.src || image.image || image.photo || '') : '';
        }

        function cardFromOffer(offer, window) {
            return {
                key: offer.key || '',
                hotelId: String(offer.hotel_id || ''),
                name: offer.hotel || 'Готель',
                country: offer.country || '',
                region: offer.region || '',
                rating: offer.hotel_rating || '',
                reviewRate: offer.hotel_review_rate || null,
                reviewCount: offer.hotel_review_count || null,
                image: pickImage(offer),
                priceUAH: offer.prices && offer.prices['2'] != null ? offer.prices['2'] : offer.price || null,
                dateFrom: offer.date_from || window.date_from,
                duration: offer.duration || offer.hnight || null,
                mealType: offer.meal_type_full || offer.meal_type || '',
                departureName: offer.from_city_name || offer.from_city || '',
                hasTransport: offerHasTransport(offer),
            };
        }

        function detailUrl(card) {
            const url = new URL(HOTEL_DETAIL_NAV_BASE || DETAIL_BASE_URL || window.location.href, window.location.origin);
            url.searchParams.set('tour_key', card.key || '');
            if (card.hotelId) {
                url.searchParams.set('hotel_id', card.hotelId);
            }
            return url.toString();
        }

        function activeCountryName() {
            const country = countryMetaById.get(activeCountryId);
            return country && country.name ? country.name : 'Обрана країна';
        }

        function activeDepartureName() {
            if (!activeDepartureId) {
                return 'Місто виїзду не обрано';
            }
            const cities = departureCache.get(activeCountryId) || [];
            const city = cities.find((item) => String(item.id) === String(activeDepartureId));
            return city && city.name ? city.name : 'Обране місто виїзду';
        }

        function syncCountryLabels() {
            const label = activeCountryName();
            if (heroSelectedCountry) {
                heroSelectedCountry.textContent = label;
            }
            if (selectedCountryPill) {
                selectedCountryPill.textContent = label;
            }
            if (countrySelect) {
                countrySelect.value = activeCountryId;
            }
            if (departurePill) {
                departurePill.textContent = 'Виїзд: ' + activeDepartureName();
            }
        }

        function scrollToOffers() {
            if (!offersSection) {
                return;
            }
            offersSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }

        const popularSearchState = {
            rawHotels: [],
            excursionOffersPool: [],
            lastQuery: null,
            page: 1,
            loadedTarget: POPULAR_SEARCH_BATCH_HOTELS,
            loadingMore: false,
            sortBy: 'recommended',
        };

        function defaultSearchDatesPs() {
            const a = new Date();
            a.setHours(12, 0, 0, 0);
            a.setDate(a.getDate() + 14);
            const b = new Date(a);
            b.setDate(b.getDate() + 7);
            return { d1: formatApiDate(a), d2: formatApiDate(b) };
        }

        function bindDateMask(input) {
            if (!input) {
                return;
            }
            input.addEventListener('input', () => {
                let value = String(input.value || '').replace(/\D/g, '').slice(0, 8);
                if (value.length >= 5) {
                    value = value.slice(0, 2) + '.' + value.slice(2, 4) + '.' + value.slice(4);
                } else if (value.length >= 3) {
                    value = value.slice(0, 2) + '.' + value.slice(2);
                }
                input.value = value;
            });
        }

        function bindShortNumericMask(input, maxDigits, fallback) {
            if (!input) {
                return;
            }
            input.addEventListener('input', () => {
                input.value = String(input.value || '').replace(/\D/g, '').slice(0, maxDigits);
            });
            input.addEventListener('blur', () => {
                if (!input.value) {
                    input.value = fallback;
                }
            });
        }

        function pickHotelThumbFromSearch(h, firstOffer) {
            const fo = firstOffer || {};
            const merged = {
                hotel_images: Array.isArray(h.images) ? h.images : [],
                images: (fo && fo.hotel_images) || [],
            };
            const url = pickImage(merged) || pickImage(fo);
            return url || '';
        }

        function activeTravelers() {
            const adults = Math.max(1, parseInt(psAdults && psAdults.value, 10) || 2);
            const children = Math.max(0, parseInt(psChildren && psChildren.value, 10) || 0);
            return { adults, children };
        }

        function travelerPriceLabel(adults, children) {
            const a = Math.max(1, parseInt(adults, 10) || 2);
            const c = Math.max(0, parseInt(children, 10) || 0);
            if (c > 0) {
                return 'за ' + a + ' дорослих + ' + c + ' дітей';
            }
            return 'за ' + a + ' дорослих';
        }

        function offerPriceForTravelers(offer, adults, children) {
            const o = offer || {};
            const prices = (o.prices && typeof o.prices === 'object') ? o.prices : null;
            const a = Math.max(1, parseInt(adults, 10) || 2);
            const c = Math.max(0, parseInt(children, 10) || 0);
            if (prices) {
                const total = String(a + c);
                const candidates = [
                    total,
                    String(a),
                    c > 0 ? (String(a) + '+' + String(c)) : '',
                    c > 0 ? (String(a) + '_' + String(c)) : '',
                    c > 0 ? (String(a) + '-' + String(c)) : '',
                    '2',
                ].filter(Boolean);
                for (const key of candidates) {
                    const raw = prices[key];
                    const value = Number(raw);
                    if (Number.isFinite(value) && value > 0) {
                        return value;
                    }
                }
            }
            const fallback = Number(o.price);
            return Number.isFinite(fallback) && fallback > 0 ? fallback : null;
        }

        function hotelMinPriceUAH(h, adults, children) {
            const offers = h.offers || [];
            const pool = transportOnlyOffers(offers);
            let min = Infinity;
            pool.forEach((o) => {
                const p = offerPriceForTravelers(o, adults, children);
                if (Number.isFinite(p) && p > 0 && p < min) {
                    min = p;
                }
            });
            if (min !== Infinity) {
                return min;
            }
            if (h.min_price != null && Number.isFinite(Number(h.min_price)) && Number(h.min_price) > 0) {
                return Number(h.min_price);
            }
            offers.forEach((o) => {
                const p = offerPriceForTravelers(o, adults, children);
                if (Number.isFinite(p) && p > 0 && p < min) {
                    min = p;
                }
            });
            if (min !== Infinity) {
                return min;
            }
            return null;
        }

        function operatorLabelFromOffer(o) {
            return firstValue(o || {}, ['operator_name', 'operator', 'tour_operator_name', 'tour_operator']) || '';
        }

        function mealLabelFromOffer(o) {
            return firstValue(o || {}, ['meal_type_full', 'meal_type', 'meal']) || '';
        }

        const regionCache = new Map();
        let psPickerMode = 'country';
        let psPickerCountryId = '';
        let psMobileCountryStep = 'countries';
        const psExpandedRegionParents = new Set();
        const destinationSearchCache = new Map();

        function normalizeSearchToken(value) {
            return String(value || '')
                .trim()
                .toLowerCase()
                .replace(/ё/g, 'е')
                .replace(/є/g, 'е')
                .replace(/і/g, 'и')
                .replace(/ї/g, 'и');
        }

        function isPsMobileViewport() {
            return window.matchMedia('(max-width: 720px)').matches;
        }

        function titleCaseWords(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/\b([a-zа-яіїєґ])/gu, (m) => m.toUpperCase());
        }

        function displayRegionName(region, asParent) {
            const raw = String(region && region.name ? region.name : '').trim();
            if (!raw) {
                return '';
            }
            if (!asParent) {
                return raw;
            }
            const hasLetters = /[A-Za-zА-Яа-яІіЇїЄєҐґ]/.test(raw);
            if (hasLetters && raw === raw.toUpperCase()) {
                return 'Регіон ' + titleCaseWords(raw);
            }
            return raw;
        }

        function currentPsCountryName() {
            const id = (psCountryId && psCountryId.value) || '';
            const meta = countryMetaById.get(String(id));
            return meta && meta.name ? meta.name : '';
        }

        function currentPsRegionName() {
            if (!psRegionId) {
                return '';
            }
            return String(psRegionId.getAttribute('data-name') || '').trim();
        }

        function currentPsHotelName() {
            if (!psHotelId) {
                return '';
            }
            return String(psHotelId.getAttribute('data-name') || '').trim();
        }

        function setPsHotelValue(hotelId, hotelName) {
            if (!psHotelId) {
                return;
            }
            psHotelId.value = String(hotelId || '');
            psHotelId.setAttribute('data-name', String(hotelName || '').trim());
        }

        function setPsRegionValue(regionId, regionName) {
            if (!psRegionId) {
                return;
            }
            psRegionId.value = String(regionId || '');
            psRegionId.setAttribute('data-name', String(regionName || '').trim());
            if (regionId) {
                setPsHotelValue('', '');
            }
            updatePsPickerLabels();
        }

        function currentPsFromNames() {
            const ids = getPsFromSelectedIds();
            if (!ids.length || !psFrom) {
                return [];
            }
            return ids.map((id) => {
                const opt = [...psFrom.options].find((item) => String(item.value) === String(id));
                return opt ? String(opt.textContent || '').trim() : '';
            }).filter(Boolean);
        }

        function updatePsPickerLabels() {
            const countryName = currentPsCountryName();
            const regionName = currentPsRegionName();
            const hotelName = currentPsHotelName();
            if (psCountryPickerLabel) {
                psCountryPickerLabel.textContent = hotelName
                    ? hotelName
                    : (countryName
                    ? (regionName ? (countryName + ' · ' + regionName) : countryName)
                    : 'Країна, курорт, готель');
                psCountryPickerLabel.classList.toggle('is-placeholder', !(countryName || hotelName));
            }
            if (psCountryQ) {
                psCountryQ.value = hotelName || countryName || '';
            }

            const fromNames = currentPsFromNames();
            if (psFromPickerLabel) {
                if (fromNames.length) {
                    psFromPickerLabel.textContent = fromNames.length > 2
                        ? (fromNames.slice(0, 2).join(', ') + ' +' + (fromNames.length - 2))
                        : fromNames.join(', ');
                    psFromPickerLabel.classList.remove('is-placeholder');
                } else if (!countryName) {
                    psFromPickerLabel.textContent = 'Оберіть країну призначення';
                    psFromPickerLabel.classList.add('is-placeholder');
                } else {
                    psFromPickerLabel.textContent = FROM_PLACEHOLDER_TEXT;
                    psFromPickerLabel.classList.add('is-placeholder');
                }
            }
        }

        function updatePsPickerActionLabel() {
            if (!psPickerApply) {
                return;
            }
            if (psPickerMode === 'country' && isPsMobileViewport() && psMobileCountryStep === 'countries') {
                psPickerApply.textContent = 'Далі';
            } else if (psPickerMode === 'from') {
                const count = getPsFromSelectedIds().length;
                psPickerApply.textContent = count ? ('Обрати (' + count + '/' + PS_FROM_MAX_SELECT + ')') : 'Обрати';
            } else {
                psPickerApply.textContent = 'Обрати';
            }
        }

        function ensurePsPickerPortal() {
            if (!psPicker || !psPickerBackdrop || !document.body) {
                return;
            }
            if (psPickerBackdrop.parentElement !== document.body) {
                document.body.appendChild(psPickerBackdrop);
            }
            if (psPicker.parentElement !== document.body) {
                document.body.appendChild(psPicker);
            }
        }

        function closePsPicker() {
            if (!psPicker || !psPickerBackdrop) {
                return;
            }
            psPicker.classList.remove('is-open', 'is-mobile-step-countries', 'is-mobile-step-regions');
            psPicker.classList.remove('is-mode-from');
            psPickerBackdrop.classList.remove('is-open');
            psPicker.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('ps-picker-open');
            document.body.style.overflow = '';
            updatePsPickerActionLabel();
        }

        async function getCountryRegions(countryId) {
            const id = String(countryId || '');
            if (!id) {
                return [];
            }
            if (regionCache.has(id)) {
                return regionCache.get(id);
            }
            try {
                const data = await api('module/params', { country: id });
                const regions = (Array.isArray(data.regions) ? data.regions : [])
                    .filter((item) => String(item && item.country_id ? item.country_id : '') === id)
                    .map((item) => ({
                        id: String(item.id || ''),
                        name: String(item.name || ''),
                        parent_id: String(item.parent_id || '0'),
                    }))
                    .filter((item) => item.id && item.name)
                    .sort((a, b) => a.name.localeCompare(b.name, 'uk'));
                regionCache.set(id, regions);
                return regions;
            } catch (error) {
                regionCache.set(id, []);
                return [];
            }
        }

        function buildRegionTree(regions) {
            const byId = new Map();
            const childrenByParent = new Map();
            (regions || []).forEach((region) => {
                byId.set(String(region.id), region);
            });
            (regions || []).forEach((region) => {
                const parentId = String(region.parent_id || '0');
                if (parentId !== '0' && byId.has(parentId)) {
                    if (!childrenByParent.has(parentId)) {
                        childrenByParent.set(parentId, []);
                    }
                    childrenByParent.get(parentId).push(region);
                }
            });

            const roots = (regions || []).filter((region) => {
                const parentId = String(region.parent_id || '0');
                return parentId === '0' || !byId.has(parentId);
            });
            roots.sort((a, b) => a.name.localeCompare(b.name, 'uk'));
            childrenByParent.forEach((items) => items.sort((a, b) => a.name.localeCompare(b.name, 'uk')));
            return { roots, childrenByParent };
        }

        const departureCountryGroups = {
            'Україна': [
                'Київ', 'Львів', 'Одеса', 'Запоріжжя', 'Харків', 'Дніпро', 'Біла Церква', 'Вінниця', 'Долина', 'Дубно',
                'Жашків', 'Житомир', 'Івано-Франківськ', 'Ізмаїл', "Кам'янець-Подільський", 'Калуш', 'Коломия', 'Кривий Ріг',
                'Кропивницький', 'Кременець', 'Луцьк', 'Миколаїв', 'Полтава', 'Рівне', 'Тернопіль', 'Ужгород',
                'Хмельницький', 'Черкаси', 'Чернівці', 'Чернігів', 'Берегово', 'Мукачево',
            ],
            'Молдова': ['Кишинів'],
            'Польща': ['Варшава', 'Вроцлав', 'Гданськ', 'Жешув', 'Катовіце', 'Краків', 'Лодзь', 'Люблін', 'Бидгощ', 'Познань', 'Зелена Гура', 'Ольштин'],
            'Румунія': ['Бакеу', 'Бая-Маре', 'Брашов', 'Бухарест', 'Клуж-Напока', 'Крайова', 'Орада', 'Сібіу', 'Сучава', 'Тімішоара', 'Ясси'],
            'Німеччина': ['Берлін', 'Дюссельдорф', 'Кельн', 'Франкфурт-на-Майні', 'Нюрнберг', 'Дрезден', 'Дортмунд', 'Мюнхен', 'Кассель', 'Падерборн', 'Фрідріхсхафен'],
            'Чехія': ['Прага', 'Острава', 'Брно'],
            'Угорщина': ['Будапешт'],
            'Литва': ['Вільнюс'],
            'Латвія': ['Рига'],
            'Естонія': ['Таллінн'],
            'Австрія': ['Відень'],
            'Італія': ['Мілан', 'Рим', 'Турин', 'Болонья'],
            'Іспанія': ['Мадрид'],
            'Бельгія': ['Брюссель'],
            'Казахстан': ['Алмати', 'Астана', 'Актобе', 'Атирау'],
            'Словаччина': ['Братислава'],
            'Швейцарія': ['Цюрих'],
            'Нідерланди': ['Амстердам'],
        };

        const departureCityToCountry = (() => {
            const map = new Map();
            Object.entries(departureCountryGroups).forEach(([countryName, cities]) => {
                (cities || []).forEach((cityName) => {
                    map.set(normalizeSearchToken(cityName), countryName);
                });
            });
            return map;
        })();

        function departureCountryLabel(city) {
            const byName = departureCityToCountry.get(normalizeSearchToken(city && city.name ? city.name : ''));
            if (byName) {
                return byName;
            }
            return 'Інші країни';
        }

        const BUS_DESTINATION_COUNTRIES = new Set([
            normalizeSearchToken('Болгарія'),
            normalizeSearchToken('Греція'),
            normalizeSearchToken('Чорногорія'),
            normalizeSearchToken('Хорватія'),
            normalizeSearchToken('Албанія'),
            normalizeSearchToken('Туреччина'),
            normalizeSearchToken('Італія'),
        ]);

        const DEPARTURE_GROUP_ORDER = [
            'Україна', 'Молдова', 'Польща', 'Румунія', 'Німеччина', 'Чехія', 'Угорщина',
            'Литва', 'Латвія', 'Естонія', 'Казахстан', 'Австрія', 'Італія', 'Іспанія', 'Бельгія',
            'Словаччина', 'Швейцарія', 'Нідерланди', 'Інші країни',
        ];

        const UA_AIR_DEPARTURE_IDS = new Set([
            '2014', '143', '1745', '449', '1212', '1344', '1352', '1360',
        ]);

        const BUS_TRANSPORT_ICON =
            '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
                '<path d="M4 16a2 2 0 0 0 2 2v2h2v-2h8v2h2v-2a2 2 0 0 0 2-2V7c0-2.6-3.58-3-8-3S4 4.4 4 7v9zm2 0v-4h12v4H6zm0-6V7h12v3H6zm2.5 3.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm7 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"></path>' +
            '</svg>';

        const PLANE_TRANSPORT_ICON =
            '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
                '<path d="M2.5 17.5v-1.667h15V17.5zm1.542-4.167L1.667 9.292l1.292-.25 1.458 1.291 4-1.083-3.375-5.708 1.625-.5 5.708 5.125 4.167-1.125a1.27 1.27 0 0 1 1.208.25q.542.437.542 1.166 0 .459-.281.813a1.38 1.38 0 0 1-.719.479z"></path>' +
            '</svg>';

        function currentDestinationCountryId() {
            return String((psCountryId && psCountryId.value) || activeCountryId || '');
        }

        function currentDestinationCountryToken() {
            const meta = countryMetaById.get(currentDestinationCountryId());
            return normalizeSearchToken(meta && meta.name ? meta.name : '');
        }

        function parseFromCityIds(raw) {
            return String(raw || '')
                .split(',')
                .map((part) => part.trim())
                .filter(Boolean)
                .slice(0, PS_FROM_MAX_SELECT);
        }

        function getPsFromSelectedIds() {
            if (psFromIds && psFromIds.value) {
                return parseFromCityIds(psFromIds.value);
            }
            if (psFrom && psFrom.value) {
                return [String(psFrom.value)];
            }
            if (activeDepartureId) {
                return [String(activeDepartureId)];
            }
            return [];
        }

        function setPsFromSelectedIds(ids) {
            const next = parseFromCityIds((ids || []).join(','));
            if (psFromIds) {
                psFromIds.value = next.join(',');
            }
            if (psFrom) {
                const primary = next[0] || '';
                if (primary && [...psFrom.options].some((opt) => String(opt.value) === primary)) {
                    psFrom.value = primary;
                } else {
                    psFrom.value = '';
                }
            }
            activeDepartureId = next[0] || '';
            if (departureSelect) {
                departureSelect.value = activeDepartureId;
            }
            updatePsPickerLabels();
        }

        function citySupportsAirDeparture(city) {
            const id = String(city && city.id ? city.id : '');
            if (UA_AIR_DEPARTURE_IDS.has(id)) {
                return true;
            }
            const name = normalizeSearchToken(city && city.name ? city.name : '');
            const airHubTokens = [
                'київ', 'львів', 'одес', 'харків', 'дніпр', 'запоріж', 'кишинів', 'варшав',
                'краків', 'бухарест', 'праг', 'будапешт', 'вільнюс', 'рига', 'таллін', 'алмат',
            ];
            return airHubTokens.some((token) => name.includes(token));
        }

        function filterDepartureCitiesForDestination(cities, destinationCountryId) {
            const destToken = (() => {
                const meta = countryMetaById.get(String(destinationCountryId || ''));
                return normalizeSearchToken(meta && meta.name ? meta.name : '');
            })();
            const busOnlyDestination = BUS_DESTINATION_COUNTRIES.has(destToken);
            return (cities || []).filter((city) => {
                const depCountry = departureCountryLabel(city);
                if (depCountry === 'Україна') {
                    return busOnlyDestination;
                }
                return true;
            });
        }

        function departureTransportMeta(city) {
            const departureCountry = departureCountryLabel(city);
            const selectedCountryName = currentDestinationCountryToken();
            const isBusRoute = departureCountry === 'Україна' && BUS_DESTINATION_COUNTRIES.has(selectedCountryName);
            if (isBusRoute || (departureCountry === 'Україна' && !citySupportsAirDeparture(city))) {
                return { label: 'автобусом', icon: BUS_TRANSPORT_ICON };
            }
            return { label: 'на літаку', icon: PLANE_TRANSPORT_ICON };
        }

        function sortDepartureGroupNames(a, b) {
            const ai = DEPARTURE_GROUP_ORDER.indexOf(a);
            const bi = DEPARTURE_GROUP_ORDER.indexOf(b);
            return (ai < 0 ? 999 : ai) - (bi < 0 ? 999 : bi);
        }

        function hotelThumbFromMeta(hotel) {
            if (!hotel || typeof hotel !== 'object') {
                return ABOUT_IMAGE_URL;
            }
            const directKeys = ['image', 'img', 'thumb', 'thumbnail', 'photo', 'photo_url', 'main_image', 'main_photo', 'picture', 'preview'];
            for (const key of directKeys) {
                const value = hotel[key];
                if (typeof value === 'string' && value.trim()) {
                    return value.trim();
                }
            }
            const listKeys = ['images', 'photos', 'gallery'];
            for (const key of listKeys) {
                const list = hotel[key];
                if (!Array.isArray(list) || !list.length) {
                    continue;
                }
                const first = list[0];
                if (typeof first === 'string' && first.trim()) {
                    return first.trim();
                }
                if (first && typeof first === 'object') {
                    const nested = first.url || first.src || first.image || first.photo;
                    if (typeof nested === 'string' && nested.trim()) {
                        return nested.trim();
                    }
                }
            }
            return ABOUT_IMAGE_URL;
        }

        function firstHotelImageFromOffer(offer) {
            const list = []
                .concat(Array.isArray(offer && offer.hotel_images) ? offer.hotel_images : [])
                .concat(Array.isArray(offer && offer.images) ? offer.images : []);
            for (const item of list) {
                if (typeof item === 'string') {
                    const stringUrl = fixMediaUrl(item);
                    if (stringUrl) {
                        return stringUrl;
                    }
                    continue;
                }
                if (!item || typeof item !== 'object') {
                    continue;
                }
                const raw = item.thumb || item.full || item.web || item.url || item.src || item.image || item.photo || '';
                const url = fixMediaUrl(raw);
                if (url) {
                    return url;
                }
            }
            return '';
        }

        function excursionThumbFromOffer(offer) {
            if (!offer || typeof offer !== 'object') {
                return '';
            }
            const fromList = firstHotelImageFromOffer(offer);
            if (fromList) {
                return fromList;
            }
            const single = offer.images;
            if (single && typeof single === 'object' && !Array.isArray(single)) {
                const u = fixMediaUrl(single.full || single.web || single.thumb || '');
                if (u) {
                    return u;
                }
            }
            if (typeof offer.image === 'string' && offer.image.trim()) {
                return fixMediaUrl(offer.image.trim());
            }
            const hikes = Array.isArray(offer.hikes) ? offer.hikes : [];
            for (let i = 0; i < hikes.length; i++) {
                const h = hikes[i];
                const u = fixMediaUrl((h && (h.image || h.img || h.thumb)) || '');
                if (u) {
                    return u;
                }
            }
            const dayDetail = Array.isArray(offer.day_detail) ? offer.day_detail : [];
            for (let i = 0; i < dayDetail.length; i++) {
                const d = dayDetail[i];
                const u = fixMediaUrl((d && d.image) || '');
                if (u) {
                    return u;
                }
            }
            const ci = offer.country_images;
            if (Array.isArray(ci) && ci[0]) {
                return fixMediaUrl(ci[0].full || ci[0].thumb || '');
            }
            return '';
        }

        function thumbSearchDateWindows() {
            const base = new Date();
            base.setHours(12, 0, 0, 0);
            const windows = [];
            if (psD1 && psD2 && psD1.value && psD2.value) {
                windows.push({
                    date_from: String(psD1.value),
                    date_till: String(psD2.value),
                    night_from: String((psN1 && psN1.value) || '1'),
                    night_till: String((psN2 && psN2.value) || '30'),
                });
            }
            const offsets = [1, 15, 29, 43, 57, 71];
            offsets.forEach((start) => {
                windows.push({
                    date_from: formatApiDate(addDays(base, start)),
                    // API range limit: up to 12 days.
                    date_till: formatApiDate(addDays(base, start + 11)),
                    night_from: '1',
                    night_till: '30',
                });
            });
            return windows;
        }

        function dedupeThumbWindows(windows) {
            const uniq = [];
            const seen = new Set();
            (windows || []).forEach((win) => {
                if (!win || !win.date_from || !win.date_till) {
                    return;
                }
                const key = String(win.date_from) + '::' + String(win.date_till) + '::' + String(win.night_from || '1') + '::' + String(win.night_till || '30');
                if (seen.has(key)) {
                    return;
                }
                seen.add(key);
                uniq.push({
                    date_from: String(win.date_from),
                    date_till: String(win.date_till),
                    night_from: String(win.night_from || '1'),
                    night_till: String(win.night_till || '30'),
                });
            });
            return uniq;
        }

        function bestOfferImage(offers) {
            const list = Array.isArray(offers) ? offers : [];
            for (const offer of list) {
                const image = firstHotelImageFromOffer(offer);
                if (image) {
                    return image;
                }
            }
            return '';
        }

        async function fetchHotelThumbBySearch(hotel) {
            const hotelId = String(hotel && hotel.id ? hotel.id : '');
            if (!hotelId) {
                return '';
            }
            if (hotelThumbCache.has(hotelId)) {
                return hotelThumbCache.get(hotelId);
            }
            if (hotelThumbPending.has(hotelId)) {
                return hotelThumbPending.get(hotelId);
            }
            const countryId = String(hotel && hotel.country_id ? hotel.country_id : '');
            const ratingIdRaw = String(hotel && (hotel.hotel_rating_id || hotel.hotel_rating_name) ? (hotel.hotel_rating_id || hotel.hotel_rating_name) : '');
            const ratingId = /^\d+$/.test(ratingIdRaw) ? ratingIdRaw : '78';
            const windows = dedupeThumbWindows(thumbSearchDateWindows());

            const request = (async () => {
                for (const win of windows.slice(0, 1)) {
                    const query = {
                        type: '1',
                        country: countryId,
                        hotel: hotelId,
                        hotel_rating: ratingId,
                        adult_amount: '2',
                        child_amount: '0',
                        night_from: String(win.night_from),
                        night_till: String(win.night_till),
                        date_from: String(win.date_from),
                        date_till: String(win.date_till),
                        hotel_info: '1',
                        hotel_image: '1',
                        currency: '2',
                        items_per_page: '4',
                    };
                    try {
                        const data = await api('module/search-list', query);
                        const offers = Array.isArray(data && data.offers) ? data.offers : [];
                        const url = bestOfferImage(offers);
                        if (url) {
                            hotelThumbCache.set(hotelId, url);
                            return url;
                        }
                    } catch (error) {
                    }
                }
                hotelThumbCache.set(hotelId, '');
                return '';
            })().finally(() => {
                hotelThumbPending.delete(hotelId);
            });

            hotelThumbPending.set(hotelId, request);
            return request;
        }

        async function fetchHotelThumbByHotelId(hotelId, hotel) {
            const id = String(hotelId || '');
            if (!id) {
                return '';
            }
            if (hotelThumbCache.has(id)) {
                return hotelThumbCache.get(id);
            }
            if (hotelThumbPending.has(id)) {
                return hotelThumbPending.get(id);
            }

            const request = (async () => {
                const rawCountryId = String((hotel && hotel.country_id) || '');
                const countryId = /^\d+$/.test(rawCountryId) ? rawCountryId : '';
                if (!countryId) {
                    return '';
                }
                try {
                    const data = await api('module/search-list', {
                        type: '1',
                        kind: '1',
                        country: countryId,
                        hotel: id,
                        hotel_rating: '1:78',
                        adult_amount: String((psAdults && psAdults.value) || '2'),
                        child_amount: String((psChildren && psChildren.value) || '0'),
                        night_from: String((psN1 && psN1.value) || '1'),
                        night_till: String((psN2 && psN2.value) || '30'),
                        date_from: String((psD1 && psD1.value) || formatApiDate(addDays(new Date(), 14))),
                        date_till: String((psD2 && psD2.value) || formatApiDate(addDays(new Date(), 25))),
                        hotel_info: '1',
                        hotel_image: '1',
                        items_per_page: '4',
                        currency: '2',
                    });
                    const offers = Array.isArray(data && data.offers) ? data.offers : [];
                    const url = bestOfferImage(offers);
                    if (url) {
                        hotelThumbCache.set(id, url);
                        return url;
                    }
                } catch (error) {
                }
                hotelThumbCache.set(id, '');
                return '';
            })().finally(() => {
                hotelThumbPending.delete(id);
            });

            hotelThumbPending.set(id, request);
            return request;
        }

        function replacePickerHotelThumb(hotelId, imageUrl) {
            const id = String(hotelId || '');
            const url = String(imageUrl || '');
            if (!id || !url || !psCountryList) {
                return;
            }
            psCountryList.querySelectorAll('img[data-ps-hotel-thumb-for="' + id + '"]').forEach((img) => {
                if (!img || !(img instanceof HTMLImageElement)) {
                    return;
                }
                if (img.src !== url) {
                    img.src = url;
                }
            });
        }

        async function hydrateHotelThumbs(hotels) {
            const queue = Array.isArray(hotels) ? hotels.slice(0, 24) : [];
            if (!queue.length) {
                return;
            }
            let cursor = 0;
            const workers = Array.from({ length: Math.min(4, queue.length) }, async () => {
                while (cursor < queue.length) {
                    const idx = cursor;
                    cursor += 1;
                    const hotel = queue[idx];
                    const hotelId = String(hotel && hotel.id ? hotel.id : '');
                    if (!hotelId) {
                        continue;
                    }
                    const url = await fetchHotelThumbBySearch(hotel);
                    if (url) {
                        replacePickerHotelThumb(hotelId, url);
                    }
                }
            });
            await Promise.all(workers);
        }

        function renderPsFromList(cities, query) {
            if (!psFromList) {
                return;
            }
            const q = normalizeSearchToken(query);
            const destinationId = currentDestinationCountryId();
            const filtered = filterDepartureCitiesForDestination(cities || [], destinationId);
            const list = filtered.filter((city) => {
                const cityLabel = 'з ' + String(city.genitive_case || city.name || '');
                const countryLabel = departureCountryLabel(city);
                return !q || normalizeSearchToken(cityLabel).includes(q) || normalizeSearchToken(countryLabel).includes(q);
            });
            if (!list.length) {
                psFromList.innerHTML = '<li><p class="ps-picker-empty">Немає міст вильоту для обраного напрямку.</p></li>';
                return;
            }
            const selectedIds = new Set(getPsFromSelectedIds());
            const groups = new Map();
            list.forEach((city) => {
                const groupName = departureCountryLabel(city);
                if (!groups.has(groupName)) {
                    groups.set(groupName, []);
                }
                groups.get(groupName).push(city);
            });
            const sortedGroups = [...groups.entries()].sort((left, right) => sortDepartureGroupNames(left[0], right[0]));
            psFromList.innerHTML = sortedGroups.map(([groupName, items]) => {
                items.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'uk'));
                return '' +
                    '<li class="ps-from-group">' +
                        '<p class="ps-picker-col-title ps-from-group-title">' + esc(groupName) + '</p>' +
                        items.map((city) => {
                            const id = String(city.id || '');
                            const isSelected = selectedIds.has(id);
                            const selectedClass = isSelected ? ' is-active' : '';
                            const isChecked = isSelected ? ' is-on' : '';
                            const transport = departureTransportMeta(city);
                            const cityText = 'з ' + String(city.genitive_case || city.name || '');
                            return '' +
                                '<button type="button" class="ps-picker-item ps-from-item' + selectedClass + '" data-ps-from="' + escAttr(id) + '">' +
                                    '<span class="ps-resort-row"><span class="ps-resort-check' + isChecked + '"></span><span>' + esc(cityText) + '</span></span>' +
                                    '<span class="ps-from-meta"><span>' + esc(transport.label) + '</span>' + transport.icon + '</span>' +
                                '</button>';
                        }).join('') +
                    '</li>';
            }).join('');

            psFromList.querySelectorAll('button[data-ps-from]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const id = String(btn.getAttribute('data-ps-from') || '');
                    if (!id) {
                        return;
                    }
                    let next = getPsFromSelectedIds();
                    if (next.includes(id)) {
                        next = next.filter((item) => item !== id);
                    } else if (next.length >= PS_FROM_MAX_SELECT) {
                        return;
                    } else {
                        next = next.concat([id]);
                    }
                    setPsFromSelectedIds(next);
                    renderPsFromList(filtered, query);
                });
            });
        }

        function renderRegionTree(regions, query) {
            if (!psFromList) {
                return;
            }
            const q = normalizeSearchToken(query);
            const selectedRegion = psRegionId && psRegionId.value ? String(psRegionId.value) : '';
            const { roots, childrenByParent } = buildRegionTree(regions);
            const showRoots = roots.filter((root) => {
                const children = childrenByParent.get(String(root.id)) || [];
                if (!q) {
                    return true;
                }
                if (normalizeSearchToken(root.name).includes(q)) {
                    return true;
                }
                return children.some((child) => normalizeSearchToken(child.name).includes(q));
            });

            if (!showRoots.length) {
                psFromList.innerHTML = '<li><p class="ps-picker-empty">Курортів не знайдено.</p></li>';
                return;
            }

            const allChecked = !selectedRegion;
            psFromList.innerHTML = '' +
                '<li>' +
                    '<button type="button" class="ps-picker-item" data-ps-region-all="1">' +
                        '<span class="ps-resort-row"><span class="ps-resort-check' + (allChecked ? ' is-on' : '') + '"></span><span>Всі курорти</span></span>' +
                    '</button>' +
                '</li>' +
                showRoots.map((root) => {
                    const rootId = String(root.id);
                    const children = childrenByParent.get(rootId) || [];
                    const shouldOpen = psExpandedRegionParents.has(rootId) || children.some((child) => String(child.id) === selectedRegion);
                    const rootChecked = selectedRegion === rootId;
                    const label = displayRegionName(root, children.length > 0);
                    const childRows = children.filter((child) => {
                        return !q || normalizeSearchToken(child.name).includes(q) || normalizeSearchToken(label).includes(q);
                    }).map((child) => {
                        const childId = String(child.id);
                        const isChecked = selectedRegion === childId;
                        return '' +
                            '<button type="button" class="ps-resort-subitem" data-ps-region="' + escAttr(childId) + '" data-ps-region-name="' + escAttr(displayRegionName(child, false)) + '">' +
                                '<span class="ps-resort-check' + (isChecked ? ' is-on' : '') + '"></span>' +
                                '<span>' + esc(displayRegionName(child, false)) + '</span>' +
                            '</button>';
                    }).join('');
                    return '' +
                        '<li class="ps-resort-group' + (shouldOpen ? ' is-open' : '') + '">' +
                            '<div class="ps-resort-group-head-wrap">' +
                                '<button type="button" class="ps-resort-group-head" data-ps-region="' + escAttr(rootId) + '" data-ps-region-name="' + escAttr(label) + '">' +
                                    '<span class="ps-resort-row"><span class="ps-resort-check' + (rootChecked ? ' is-on' : '') + '"></span><span>' + esc(label) + '</span></span>' +
                                    (children.length ? '<span class="ps-picker-arrow" data-ps-region-toggle="' + escAttr(rootId) + '">' + (shouldOpen ? '⌃' : '⌄') + '</span>' : '') +
                                '</button>' +
                            '</div>' +
                            (children.length ? '<div class="ps-resort-sublist">' + childRows + '</div>' : '') +
                        '</li>';
                }).join('');

            const allBtn = psFromList.querySelector('button[data-ps-region-all]');
            if (allBtn) {
                allBtn.addEventListener('click', () => {
                    setPsRegionValue('', '');
                    renderRegionTree(regions, query);
                });
            }
            psFromList.querySelectorAll('button[data-ps-region]').forEach((btn) => {
                btn.addEventListener('click', (event) => {
                    const id = String(btn.getAttribute('data-ps-region') || '');
                    const name = String(btn.getAttribute('data-ps-region-name') || '');
                    if (!id) {
                        return;
                    }
                    setPsRegionValue(id, name);
                    if (isPsMobileViewport()) {
                        closePsPicker();
                        return;
                    }
                    renderRegionTree(regions, query);
                });
            });
            psFromList.querySelectorAll('[data-ps-region-toggle]').forEach((toggle) => {
                toggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const id = String(toggle.getAttribute('data-ps-region-toggle') || '');
                    if (!id) {
                        return;
                    }
                    if (psExpandedRegionParents.has(id)) {
                        psExpandedRegionParents.delete(id);
                    } else {
                        psExpandedRegionParents.add(id);
                    }
                    renderRegionTree(regions, query);
                });
            });
        }

        function normalizeHotelRating(raw) {
            if (raw == null) {
                return 0;
            }
            const value = String(raw);
            const match = value.match(/\d+/);
            const num = match ? parseInt(match[0], 10) : 0;
            if (!Number.isFinite(num) || num < 0) {
                return 0;
            }
            return Math.min(5, num);
        }

        function starsInline(count) {
            const n = Math.max(0, Math.min(5, parseInt(count, 10) || 0));
            return n ? '★'.repeat(n) : '';
        }

        async function searchDestinations(query) {
            const q = String(query || '').trim();
            if (q.length < 3) {
                return { countries: [], regions: [], hotels: [] };
            }
            const cacheKey = q.toLowerCase();
            if (destinationSearchCache.has(cacheKey)) {
                const cached = destinationSearchCache.get(cacheKey);
                const hasItems = cached && (
                    (Array.isArray(cached.hotels) && cached.hotels.length) ||
                    (Array.isArray(cached.regions) && cached.regions.length) ||
                    (Array.isArray(cached.countries) && cached.countries.length)
                );
                if (hasItems) {
                    return cached;
                }
                destinationSearchCache.delete(cacheKey);
            }
            const req = { type: '1', query: q };
            const normalizePayload = (data) => ({
                countries: Array.isArray(data.countries) ? data.countries : [],
                regions: Array.isArray(data.regions) ? data.regions : [],
                hotels: Array.isArray(data.hotels) ? data.hotels : [],
            });
            const isEmptyPayload = (payload) => !payload.countries.length && !payload.regions.length && !payload.hotels.length;

            let data = await api('module/params/destinations', req);
            let payload = normalizePayload(data);

            // Some sessions keep stale empty cache; retry once with cache drop.
            if (isEmptyPayload(payload)) {
                const apiKey = apiCacheKey('GET:module/params/destinations', req);
                apiMemoryCache.delete(apiKey);
                try {
                    sessionStorage.removeItem('ittour:' + apiKey);
                } catch (error) {
                }
                data = await api('module/params/destinations', req);
                payload = normalizePayload(data);
            }

            destinationSearchCache.set(cacheKey, payload);
            return payload;
        }

        function renderCountryRegionHits(destinations) {
            if (!psCountryList || !psFromList) {
                return false;
            }
            const countries = Array.isArray(destinations && destinations.countries) ? destinations.countries : [];
            const regions = Array.isArray(destinations && destinations.regions) ? destinations.regions : [];
            if (!countries.length && !regions.length) {
                return false;
            }

            psCountryList.innerHTML = '' +
                countries.slice(0, 16).map((country) => {
                    const countryId = String(country.id || '');
                    const countryName = String(country.name || '');
                    return '<li><button type="button" class="ps-picker-item" data-ps-country-hit="' + escAttr(countryId) + '" data-ps-country-name="' + escAttr(countryName) + '">' +
                        '<span>' + esc(countryName) + '</span><span class="ps-picker-arrow">›</span></button></li>';
                }).join('') +
                regions.slice(0, 20).map((region) => {
                    const regionId = String(region.id || '');
                    const countryId = String(region.country_id || '');
                    const regionName = String(region.name || '');
                    const countryName = String(region.country_name || '');
                    return '<li><button type="button" class="ps-picker-item" data-ps-region-hit="' + escAttr(regionId) + '" data-ps-country-hit="' + escAttr(countryId) + '" data-ps-region-name="' + escAttr(regionName) + '">' +
                        '<span><strong>' + esc(regionName) + '</strong><small>' + esc(countryName || 'Регіон') + '</small></span><span class="ps-picker-arrow">›</span></button></li>';
                }).join('');

            psFromList.innerHTML = '<li><p class="ps-picker-empty">Оберіть країну або регіон ліворуч.</p></li>';

            psCountryList.querySelectorAll('button[data-ps-country-hit]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const countryId = String(btn.getAttribute('data-ps-country-hit') || '');
                    const regionId = String(btn.getAttribute('data-ps-region-hit') || '');
                    const regionName = String(btn.getAttribute('data-ps-region-name') || '');
                    if (!countryId) return;
                    setPsHotelValue('', '');
                    await setPsCountryById(countryId, { keepDeparture: true, clearRegion: true });
                    if (regionId && psRegionId) {
                        psRegionId.value = regionId;
                        psRegionId.setAttribute('data-name', regionName);
                    }
                    updatePsPickerLabels();
                    closePsPicker();
                });
            });
            return true;
        }

        async function renderHotelSearchResults(destinations) {
            if (!psCountryList || !psFromList) {
                return false;
            }
            const hotels = (destinations && Array.isArray(destinations.hotels)) ? destinations.hotels : [];
            if (!hotels.length) {
                return false;
            }
            psCountryList.innerHTML = hotels.slice(0, 24).map((hotel) => {
                const hotelId = String(hotel.id || '');
                const countryId = String(hotel.country_id || '');
                const regionId = String(hotel.region_id || '');
                const hotelName = String(hotel.name || 'Готель');
                const countryName = String(hotel.country_name || '');
                const regionName = String(hotel.region_name || '');
                const rating = normalizeHotelRating(hotel.hotel_rating_name || hotel.hotel_rating_id);
                const stars = starsInline(rating);
                const meta = [regionName, countryName].filter(Boolean).join(', ');
                const thumbUrl = hotelThumbFromMeta(hotel);
                return '' +
                    '<li>' +
                        '<button type="button" class="ps-picker-item ps-hotel-item" data-ps-hotel-id="' + escAttr(hotelId) + '" data-ps-country-id="' + escAttr(countryId) + '" data-ps-region-id="' + escAttr(regionId) + '" data-ps-hotel-name="' + escAttr(hotelName) + '" data-ps-region-name="' + escAttr(regionName) + '">' +
                            '<img class="ps-hotel-thumb" data-ps-hotel-thumb-for="' + escAttr(hotelId) + '" src="' + escAttr(thumbUrl) + '" alt="" loading="lazy" onerror="this.onerror=null;this.src=' + "'" + escAttr(ABOUT_IMAGE_URL) + "'" + ';">' +
                            '<span class="ps-hotel-body">' +
                                '<span class="ps-hotel-name">' + esc(hotelName) + '</span>' +
                                (stars ? '<span class="ps-hotel-stars">' + esc(stars) + '</span>' : '') +
                                '<span class="ps-hotel-meta">' + esc(meta || 'Локація уточнюється') + '</span>' +
                            '</span>' +
                        '</button>' +
                    '</li>';
            }).join('');

            psFromList.innerHTML = '<li><p class="ps-picker-empty">Оберіть готель зі списку ліворуч або продовжуйте пошук.</p></li>';

            psCountryList.querySelectorAll('button[data-ps-hotel-id]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const hotelId = String(btn.getAttribute('data-ps-hotel-id') || '');
                    const countryId = String(btn.getAttribute('data-ps-country-id') || '');
                    const regionId = String(btn.getAttribute('data-ps-region-id') || '');
                    const hotelName = String(btn.getAttribute('data-ps-hotel-name') || '');
                    const regionName = String(btn.getAttribute('data-ps-region-name') || '');
                    if (!hotelId || !countryId) {
                        return;
                    }
                    await setPsCountryById(countryId, { keepDeparture: true, clearRegion: false, keepHotel: true });
                    if (psRegionId) {
                        psRegionId.value = regionId;
                        psRegionId.setAttribute('data-name', regionName);
                    }
                    setPsHotelValue(hotelId, hotelName);
                    updatePsPickerLabels();
                    closePsPicker();
                });
            });
            hydrateHotelThumbs(hotels);
            return true;
        }

        async function renderCountryMode() {
            if (!psCountryList || !psFromList) {
                return;
            }
            const query = psPickerSearch ? psPickerSearch.value : '';
            const q = normalizeSearchToken(query);
            if (query && query.trim().length >= 3) {
                try {
                    const hits = await searchDestinations(query);
                    const renderedHotels = await renderHotelSearchResults(hits);
                    if (renderedHotels) {
                        return;
                    }
                    const renderedCountryRegion = renderCountryRegionHits(hits);
                    if (renderedCountryRegion) {
                        return;
                    }
                } catch (e) {
                }
            }
            const currentCountry = psPickerCountryId || (psCountryId && psCountryId.value ? psCountryId.value : '');
            const countries = ALL_COUNTRIES.filter((country) => !q || normalizeSearchToken(country.name || '').includes(q));
            psCountryList.innerHTML = countries.map((country) => {
                const isActive = String(country.id) === String(currentCountry);
                return '' +
                    '<li>' +
                        '<button type="button" class="ps-picker-item' + (isActive ? ' is-active' : '') + '" data-ps-country="' + escAttr(country.id) + '">' +
                            '<span>' + esc(country.name || '') + '</span>' +
                            '<span class="ps-picker-arrow">›</span>' +
                        '</button>' +
                    '</li>';
            }).join('') || '<li><p class="ps-picker-empty">Нічого не знайдено.</p></li>';

            psCountryList.querySelectorAll('button[data-ps-country]').forEach((btn) => {
                const id = String(btn.getAttribute('data-ps-country') || '');
                btn.addEventListener('mouseenter', async () => {
                    if (isPsMobileViewport() || !id) {
                        return;
                    }
                    if (psPickerCountryId === id) {
                        return;
                    }
                    psPickerCountryId = id;
                    await renderCountryMode();
                });
                btn.addEventListener('click', async () => {
                    if (!id) {
                        return;
                    }
                    setPsHotelValue('', '');
                    await setPsCountryById(id, { keepDeparture: true, clearRegion: true });
                    psPickerCountryId = id;
                    if (isPsMobileViewport()) {
                        psMobileCountryStep = 'regions';
                        if (psPicker) {
                            psPicker.classList.remove('is-mobile-step-countries');
                            psPicker.classList.add('is-mobile-step-regions');
                        }
                        if (psPickerTitle) {
                            const meta = countryMetaById.get(String(id));
                            psPickerTitle.textContent = meta && meta.name ? meta.name : 'Курорти';
                        }
                        updatePsPickerActionLabel();
                    }
                    await renderCountryMode();
                });
            });

            if (!currentCountry) {
                psFromList.innerHTML = '<li><p class="ps-picker-empty">Спочатку оберіть країну.</p></li>';
                return;
            }
            const regions = await getCountryRegions(currentCountry);
            renderRegionTree(regions, query);
        }

        async function renderFromMode() {
            if (!psFromList) {
                return;
            }
            const countryId = (psCountryId && psCountryId.value) || activeCountryId;
            const query = psPickerSearch ? psPickerSearch.value : '';
            if (!countryId) {
                psFromList.innerHTML = '<li><p class="ps-picker-empty">Спочатку оберіть країну призначення.</p></li>';
                return;
            }
            const cities = await getDepartureCities(countryId);
            renderPsFromList(cities, query);
        }

        async function renderPsPicker() {
            if (!psCountryList || !psFromList) {
                return;
            }
            if (psPickerMode === 'from') {
                if (psSecondaryTitle) {
                    psSecondaryTitle.textContent = 'Місто вильоту';
                }
                psCountryList.innerHTML = '';
                await renderFromMode();
                return;
            }
            if (psSecondaryTitle) {
                psSecondaryTitle.textContent = 'Курорти';
            }
            await renderCountryMode();
        }

        async function openPsPicker(mode) {
            if (!psPicker || !psPickerBackdrop) {
                return;
            }
            ensurePsPickerPortal();
            psPickerMode = mode === 'from' ? 'from' : 'country';
            psPickerCountryId = (psCountryId && psCountryId.value) || activeCountryId || DEFAULT_COUNTRY_ID || '';
            psMobileCountryStep = 'countries';
            if (psPickerTitle) {
                psPickerTitle.textContent = psPickerMode === 'from' ? 'Звідки' : 'Країна, курорт, готель';
            }
            if (psPickerSearch) {
                psPickerSearch.value = '';
            }
            if (psPickerMode === 'country' && isPsMobileViewport()) {
                psPicker.classList.add('is-mobile-step-countries');
                psPicker.classList.remove('is-mobile-step-regions');
            } else {
                psPicker.classList.remove('is-mobile-step-countries', 'is-mobile-step-regions');
            }
            if (psPickerMode === 'from') {
                psPicker.classList.add('is-mode-from');
                if (psPickerTitle) {
                    psPickerTitle.textContent = 'Звідки (до ' + PS_FROM_MAX_SELECT + ' міст)';
                }
            } else {
                psPicker.classList.remove('is-mode-from');
            }
            updatePsPickerActionLabel();
            await renderPsPicker();
            psPicker.setAttribute('aria-hidden', 'false');
            psPicker.classList.add('is-open');
            psPickerBackdrop.classList.add('is-open');
            if (isPsMobileViewport()) {
                document.body.classList.add('ps-picker-open');
                document.body.style.overflow = 'hidden';
            } else {
                document.body.classList.remove('ps-picker-open');
                document.body.style.overflow = '';
            }
            if (psPickerSearch) {
                setTimeout(() => psPickerSearch.focus(), 30);
            }
        }

        async function setPsCountryById(countryId, options) {
            const opts = options || {};
            if (!psCountryId) {
                return;
            }
            const nextId = String(countryId || '');
            if (!nextId) {
                psCountryId.value = '';
                if (opts.clearRegion && psRegionId) {
                    setPsRegionValue('', '');
                }
                updatePsPickerLabels();
                return;
            }
            psCountryId.value = nextId;
            activeCountryId = nextId;
            if (opts.clearRegion || !opts.keepHotel) {
                setPsHotelValue('', '');
            }
            if (countrySelect) {
                countrySelect.value = nextId;
            }
            if (opts.clearRegion && psRegionId) {
                setPsRegionValue('', '');
            }
            if (!opts.keepDeparture) {
                setPsFromSelectedIds([]);
            }
            await refreshPsFromSelect({ keepCurrent: Boolean(opts.keepDeparture) });
            updatePsPickerLabels();
            syncCountryLabels();
        }

        function setPsCountryPlaceholder() {
            if (!psCountryId) {
                return;
            }
            psCountryId.value = '';
            setPsHotelValue('', '');
            if (psRegionId) {
                setPsRegionValue('', '');
            }
            setPsFromSelectedIds([]);
            if (psFrom) {
                psFrom.innerHTML = '<option value="">' + esc(FROM_PLACEHOLDER_TEXT) + '</option>';
            }
            updatePsPickerLabels();
        }

        async function refreshPsFromSelect(options) {
            const opts = options || {};
            if (!psFrom) {
                return;
            }
            const id = psCountryId && psCountryId.value ? psCountryId.value : activeCountryId;
            if (!id) {
                psFrom.innerHTML = '<option value="">' + esc(FROM_PLACEHOLDER_TEXT) + '</option>';
                setPsFromSelectedIds([]);
                return;
            }
            const cities = filterDepartureCitiesForDestination(await getDepartureCities(id), id);
            const prevIds = opts.keepCurrent
                ? getPsFromSelectedIds()
                : [];
            psFrom.innerHTML = '<option value="">' + esc(FROM_PLACEHOLDER_TEXT) + '</option>' + cities.map((c) =>
                '<option value="' + escAttr(String(c.id)) + '">' + esc(c.name || '') + '</option>',
            ).join('');
            const validPrev = prevIds.filter((cityId) =>
                cities.some((city) => String(city.id) === String(cityId)),
            );
            if (validPrev.length) {
                setPsFromSelectedIds(validPrev);
            } else {
                setPsFromSelectedIds([]);
            }
        }

        function syncPsFormFromHero() {
            if (DETAIL_TOUR_KEY || !psCountryId) {
                return;
            }
            if (!psCountryId.value) {
                setPsCountryPlaceholder();
            } else {
                updatePsPickerLabels();
            }
            const dates = defaultSearchDatesPs();
            if (psD1 && !psD1.value) {
                psD1.value = dates.d1;
            }
            if (psD2 && !psD2.value) {
                psD2.value = dates.d2;
            }
        }

        function readPopularSearchParamsFromForm() {
            if (!psCountryId || !psCountryId.value) {
                psCountryId && (psCountryId.value = activeCountryId || DEFAULT_COUNTRY_ID || '');
            }
            return {
                country: psCountryId && psCountryId.value ? psCountryId.value : activeCountryId,
                region: psRegionId && psRegionId.value ? psRegionId.value : '',
                hotel: psHotelId && psHotelId.value ? psHotelId.value : '',
                from: getPsFromSelectedIds().join(','),
                d1: psD1 ? psD1.value.trim() : '',
                d2: psD2 ? psD2.value.trim() : '',
                n1: psN1 ? String(Math.max(1, parseInt(psN1.value, 10) || 6)) : '6',
                n2: psN2 ? String(Math.max(1, parseInt(psN2.value, 10) || 8)) : '8',
                adults: psAdults ? String(Math.min(4, Math.max(1, parseInt(psAdults.value, 10) || 2))) : '2',
                children: psChildren ? String(Math.min(3, Math.max(0, parseInt(psChildren.value, 10) || 0))) : '0',
            };
        }

        function writePopularSearchToUrl(params, mode) {
            const base = CATALOG_BASE_URL || DETAIL_BASE_URL || window.location.pathname;
            const u = new URL(base, window.location.origin);
            const modeInput = document.getElementById('ps-search-mode');
            const currentUrlMode = new URL(window.location.href).searchParams.get('mode');
            const currentMode = String((modeInput && modeInput.value) || currentUrlMode || '').trim();
            u.searchParams.set('country_id', params.country);
            u.searchParams.delete('country');
            u.searchParams.delete('search');
            if (currentMode === 'excursion') {
                u.searchParams.set('mode', 'excursion');
            } else {
                u.searchParams.delete('mode');
            }
            if (params.region) {
                u.searchParams.set('region', params.region);
            } else {
                u.searchParams.delete('region');
            }
            if (params.hotel) {
                u.searchParams.set('hotel_id', params.hotel);
            } else {
                u.searchParams.delete('hotel_id');
                u.searchParams.delete('hotel');
            }
            u.searchParams.set('from', params.from);
            u.searchParams.set('d1', params.d1);
            u.searchParams.set('d2', params.d2);
            u.searchParams.set('n1', params.n1);
            u.searchParams.set('n2', params.n2);
            u.searchParams.set('adults', params.adults);
            u.searchParams.set('children', params.children);
            const hash = window.location.hash || '';
            if (mode === 'replace') {
                window.history.replaceState({}, '', u.pathname + u.search + hash);
            } else {
                window.history.pushState({}, '', u.pathname + u.search + hash);
            }
        }

        function stripLegacySearchQueryFromUrl() {
            const u = new URL(window.location.href);
            if (u.searchParams.get('search') !== '1') {
                return;
            }
            u.searchParams.delete('search');
            [
                'from', 'd1', 'd2', 'n1', 'n2', 'adults', 'children', 'region', 'hotel_id', 'hotel',
                'mode', 'from_city', 'date_from', 'date_till', 'night_from', 'night_till',
                'adult', 'child', 'transport_type', 'country',
            ].forEach((key) => u.searchParams.delete(key));
            const next = u.pathname + (u.search ? u.search : '') + (window.location.hash || '');
            window.history.replaceState({}, '', next);
        }

        function readPopularSearchFromUrl() {
            if (ANEX_CATALOG_LITE) {
                return null;
            }
            const u = new URL(window.location.href);
            const legacySearch = u.searchParams.get('search') === '1';
            const hasSearchPayload = legacySearch || [
                'from', 'd1', 'd2', 'n1', 'n2', 'adults', 'children',
                'region', 'hotel_id', 'hotel',
            ].some((key) => {
                const value = u.searchParams.get(key);
                return value != null && String(value).trim() !== '';
            });
            if (!hasSearchPayload) {
                return null;
            }
            return {
                country: u.searchParams.get('country_id') || u.searchParams.get('country') || '',
                region: u.searchParams.get('region') || '',
                hotel: u.searchParams.get('hotel_id') || u.searchParams.get('hotel') || '',
                from: u.searchParams.get('from') || '',
                d1: u.searchParams.get('d1') || '',
                d2: u.searchParams.get('d2') || '',
                n1: u.searchParams.get('n1') || '6',
                n2: u.searchParams.get('n2') || '8',
                adults: u.searchParams.get('adults') || '2',
                children: u.searchParams.get('children') || '0',
            };
        }

        function buildSearchV2ShadowUrl(params) {
            const url = new URL(SEARCH_V2_ENDPOINT, window.location.origin);
            const map = {
                country_id: params.country,
                region: params.region,
                hotel_id: params.hotel,
                from: params.from,
                d1: params.d1,
                d2: params.d2,
                n1: params.n1,
                n2: params.n2,
                adults: params.adults,
                children: params.children,
                limit: '24',
                shadow: '1',
            };
            Object.entries(map).forEach(([key, value]) => {
                if (value != null && String(value).trim() !== '') {
                    url.searchParams.set(key, String(value));
                }
            });
            return url;
        }

        function rememberSearchV2Shadow(entry) {
            try {
                const key = 'anex:search-v2-shadow';
                const list = JSON.parse(sessionStorage.getItem(key) || '[]');
                list.unshift(entry);
                sessionStorage.setItem(key, JSON.stringify(list.slice(0, 20)));
            } catch (e) {}
        }

        async function runSearchV2Shadow(params, context) {
            const ctx = context || {};
            if (!SEARCH_V2_SHADOW_ENABLED || !SEARCH_V2_ENDPOINT || ctx.loadMore) {
                return;
            }
            const startedAt = (window.performance && typeof window.performance.now === 'function')
                ? window.performance.now()
                : Date.now();
            try {
                const response = await fetch(buildSearchV2ShadowUrl(params).toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-WP-Nonce': SEARCH_V2_REST_NONCE,
                    },
                });
                const payload = await response.json().catch(() => null);
                const endedAt = (window.performance && typeof window.performance.now === 'function')
                    ? window.performance.now()
                    : Date.now();
                const meta = payload && payload.meta ? payload.meta : {};
                const cache = payload && payload.cache ? payload.cache : {};
                const entry = {
                    ts: new Date().toISOString(),
                    http: response.status,
                    ok: Boolean(response.ok && payload && payload.ok),
                    status: payload && payload.status ? String(payload.status) : 'error',
                    source: payload && payload.source ? String(payload.source) : '',
                    count: Number(meta.count || (payload && Array.isArray(payload.cards) ? payload.cards.length : 0) || 0),
                    cache_hit: Boolean(cache.hit),
                    cache_stale: Boolean(cache.stale),
                    api_calls: Number(meta.api_calls || 0),
                    origin_api_calls: Number(meta.origin_api_calls || 0),
                    latency_ms: Math.round(endedAt - startedAt),
                    country: String(params.country || ''),
                    from: String(params.from || ''),
                    d1: String(params.d1 || ''),
                    d2: String(params.d2 || ''),
                    n1: String(params.n1 || ''),
                    n2: String(params.n2 || ''),
                };
                rememberSearchV2Shadow(entry);
                if (window.console && typeof window.console.info === 'function') {
                    window.console.info('[Anex Search V2 shadow]', entry);
                }
            } catch (e) {
                rememberSearchV2Shadow({
                    ts: new Date().toISOString(),
                    ok: false,
                    status: 'network_error',
                    message: (e && e.message) || 'Search V2 shadow failed',
                });
                if (window.console && typeof window.console.warn === 'function') {
                    window.console.warn('[Anex Search V2 shadow]', e);
                }
            }
        }

        function currentSearchModeFromState() {
            const modeInput = document.getElementById('ps-search-mode');
            const urlMode = new URL(window.location.href).searchParams.get('mode');
            return String((modeInput && modeInput.value) || urlMode || 'hotel').trim();
        }

        function searchRenderCacheKey(params, mode) {
            const payload = {
                mode: String(mode || 'hotel'),
                country: String(params && params.country || ''),
                region: String(params && params.region || ''),
                hotel: String(params && params.hotel || ''),
                from: String(params && params.from || ''),
                d1: String(params && params.d1 || ''),
                d2: String(params && params.d2 || ''),
                n1: String(params && params.n1 || ''),
                n2: String(params && params.n2 || ''),
                adults: String(params && params.adults || ''),
                children: String(params && params.children || ''),
            };
            return 'anex:search-render:v4:' + JSON.stringify(payload);
        }

        function saveSearchRenderCache(params, mode) {
            if (!searchResultsList) return;
            try {
                const key = searchRenderCacheKey(params, mode);
                const payload = {
                    ts: Date.now(),
                    html: searchResultsList.innerHTML || '',
                    count: searchResultsCount ? (searchResultsCount.textContent || '') : '',
                    banner: searchResultsBanner ? (searchResultsBanner.textContent || '') : '',
                };
                sessionStorage.setItem(key, JSON.stringify(payload));
            } catch (e) {}
        }

        function restoreSearchRenderCache(params, mode) {
            if (!searchResultsList) return false;
            try {
                const key = searchRenderCacheKey(params, mode);
                const raw = sessionStorage.getItem(key);
                if (!raw) return false;
                const payload = JSON.parse(raw);
                if (!payload || typeof payload !== 'object') return false;
                if (!payload.ts || (Date.now() - Number(payload.ts)) > (1000 * 60 * 20)) return false;
                const html = String(payload.html || '');
                if (!html) return false;
                searchResultsList.innerHTML = html;
                if (searchResultsCount) searchResultsCount.textContent = String(payload.count || '');
                if (searchResultsBanner) searchResultsBanner.textContent = String(payload.banner || '');
                if (searchResultsLoading) searchResultsLoading.hidden = true;
                if (searchResultsPagination) searchResultsPagination.hidden = true;
                return true;
            } catch (e) {
                return false;
            }
        }

        async function applyPopularSearchParamsToForm(p) {
            if (!p) {
                return;
            }
            if (p.country) {
                await setPsCountryById(p.country, { keepDeparture: true, clearRegion: false });
            } else {
                updatePsPickerLabels();
            }
            if (psRegionId) {
                setPsRegionValue('', '');
                if (p.region) {
                    const regions = await getCountryRegions(psCountryId && psCountryId.value ? psCountryId.value : activeCountryId);
                    const regionMeta = regions.find((r) => String(r.id) === String(p.region));
                    if (regionMeta) {
                        setPsRegionValue(regionMeta.id, displayRegionName(regionMeta, true));
                    }
                }
            }
            if (psHotelId) {
                setPsHotelValue(p.hotel || '', '');
            }
            if (p.from) {
                const fromIds = parseFromCityIds(p.from).filter((cityId) =>
                    psFrom && [...psFrom.options].some((o) => o.value === String(cityId)),
                );
                setPsFromSelectedIds(fromIds);
            } else {
                setPsFromSelectedIds([]);
            }
            if (psD1) {
                psD1.value = p.d1 || defaultSearchDatesPs().d1;
            }
            if (psD2) {
                psD2.value = p.d2 || defaultSearchDatesPs().d2;
            }
            if (psN1) {
                psN1.value = p.n1;
            }
            if (psN2) {
                psN2.value = p.n2;
            }
            if (psAdults) {
                psAdults.value = p.adults;
            }
            if (psChildren) {
                psChildren.value = p.children;
            }
            updatePsPickerLabels();
        }

        function openPopularSearchUI() {
            document.body.classList.add('popular-search-open');
            if (searchResultsPage) {
                searchResultsPage.hidden = false;
                searchResultsPage.classList.add('is-open');
            }
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function closePopularSearchUI() {
            document.body.classList.remove('popular-search-open');
            if (searchResultsPage) {
                searchResultsPage.hidden = true;
                searchResultsPage.classList.remove('is-open');
            }
            const base = CATALOG_BASE_URL || DETAIL_BASE_URL || window.location.pathname;
            const u = new URL(base, window.location.origin);
            const hash = window.location.hash || '';
            window.history.replaceState({}, '', u.pathname + hash);
        }

        function selectedStarValues() {
            if (!sfStarFilters) {
                return [];
            }
            return [...sfStarFilters.querySelectorAll('input[name="sf_star"]:checked')].map((i) => parseInt(i.value, 10)).filter((n) => n >= 2 && n <= 5);
        }

        function selectedMealValues() {
            if (!sfMealChips) {
                return [];
            }
            return [...sfMealChips.querySelectorAll('input[type="checkbox"]:checked')].map((i) => i.value);
        }

        function selectedOperatorValues() {
            if (!sfOperatorList) {
                return [];
            }
            return [...sfOperatorList.querySelectorAll('input[type="checkbox"]:checked')].map((i) => i.value);
        }

        function selectedRatingValues() {
            if (!sfRatingChips) return [];
            return [...sfRatingChips.querySelectorAll('input[name="sf_rating"]:checked')].map((i) => i.value);
        }
        function selectedHotelValues() {
            if (!sfHotelList) return [];
            return [...sfHotelList.querySelectorAll('input[type="checkbox"]:checked')].map((i) => i.value);
        }
        function ratingInBucket(rate, bucket) {
            const r = parseFloat(rate) || 0;
            if (bucket === '1-5') return r < 6;
            if (bucket === '6')   return r >= 6  && r < 7;
            if (bucket === '7')   return r >= 7  && r < 8;
            if (bucket === '8')   return r >= 8  && r < 9;
            if (bucket === '9+')  return r >= 9;
            return false;
        }

        function hotelPassesClientFilters(h) {
            const maxP = sfPriceMax ? parseInt(sfPriceMax.value, 10) : 200000;
            const minPBudget = sfPriceMin ? parseInt(sfPriceMin.value, 10) : 0;
            const priceCap = Number.isFinite(maxP) ? maxP : 200000;
            const priceFloor = Number.isFinite(minPBudget) ? minPBudget : 0;
            const hotelMinP = hotelMinPriceUAH(h);
            if (hotelMinP != null && hotelMinP > priceCap) return false;
            if (hotelMinP != null && priceFloor > 0 && hotelMinP < priceFloor) return false;

            const stars = selectedStarValues();
            if (stars.length) {
                const hr = parseInt(h.hotel_rating, 10) || 0;
                if (!stars.includes(hr)) return false;
            }

            const ratings = selectedRatingValues();
            if (ratings.length) {
                const rate = h.hotel_review_rate || (h.offers && h.offers[0] && h.offers[0].hotel_review_rate) || 0;
                const ok = ratings.some((b) => ratingInBucket(rate, b));
                if (!ok) return false;
            }

            const meals = selectedMealValues();
            if (meals.length) {
                const ok = (h.offers || []).some((o) => {
                    const m = mealLabelFromOffer(o).trim();
                    return m && meals.some((sel) => sel === m);
                });
                if (!ok) return false;
            }

            const selectedHotels = selectedHotelValues();
            if (selectedHotels.length) {
                const name = (h.hotel || '').toLowerCase();
                if (!selectedHotels.some((n) => n.toLowerCase() === name)) return false;
            }

            return true;
        }

        function excursionPassesClientFilters(offer) {
            const maxP = sfPriceMax ? parseInt(sfPriceMax.value, 10) : 200000;
            const minPBudget = sfPriceMin ? parseInt(sfPriceMin.value, 10) : 0;
            const priceCap = Number.isFinite(maxP) ? maxP : 200000;
            const priceFloor = Number.isFinite(minPBudget) ? minPBudget : 0;
            const price = Number((offer && offer.prices && offer.prices['2']) != null ? offer.prices['2'] : ((offer && offer.price) || 0));
            if (price > 0 && price > priceCap) return false;
            if (price > 0 && priceFloor > 0 && price < priceFloor) return false;
            return true;
        }

        function excursionOfferPriceUAH(offer) {
            const price = Number((offer && offer.prices && offer.prices['2']) != null ? offer.prices['2'] : (offer && offer.price) || 0);
            return Number.isFinite(price) && price > 0 ? price : Infinity;
        }

        function excursionOfferDateTs(offer) {
            const value = String((offer && offer.date_from) || '').trim();
            if (!value) return Infinity;
            const ts = Date.parse(value);
            return Number.isFinite(ts) ? ts : Infinity;
        }

        function excursionDedupeSignature(offer) {
            const name = normalizeSearchToken((offer && offer.name) || '');
            const countries = normalizeSearchToken(Array.isArray(offer && offer.country_names) ? offer.country_names.join(' ') : ((offer && offer.country) || ''));
            const cities = normalizeSearchToken(Array.isArray(offer && offer.city_names) ? offer.city_names.join(' ') : ((offer && offer.city) || ''));
            const keyPart = normalizeSearchToken(String((offer && offer.key) || (offer && offer.id) || ''));
            if (keyPart) {
                return 'key:' + keyPart;
            }
            const duration = normalizeSearchToken(String((offer && offer.duration) || ''));
            return [name, countries, cities, duration].join('|');
        }

        function excursionPickBetterVariation(left, right) {
            const leftPrimary = left && left.__excursion_scope !== 'fallback';
            const rightPrimary = right && right.__excursion_scope !== 'fallback';
            if (leftPrimary !== rightPrimary) {
                return leftPrimary ? left : right;
            }
            const lp = excursionOfferPriceUAH(left);
            const rp = excursionOfferPriceUAH(right);
            if (lp !== rp) {
                return lp < rp ? left : right;
            }
            const ld = excursionOfferDateTs(left);
            const rd = excursionOfferDateTs(right);
            return ld <= rd ? left : right;
        }

        function dedupeExcursionOffers(list) {
            const map = new Map();
            (list || []).forEach((offer) => {
                const sig = excursionDedupeSignature(offer);
                if (!sig || sig === '|||') {
                    return;
                }
                const prev = map.get(sig);
                map.set(sig, prev ? excursionPickBetterVariation(prev, offer) : offer);
            });
            return [...map.values()].sort((a, b) => {
                const af = a && a.__excursion_scope === 'fallback' ? 1 : 0;
                const bf = b && b.__excursion_scope === 'fallback' ? 1 : 0;
                if (af !== bf) return af - bf;
                const pa = excursionOfferPriceUAH(a);
                const pb = excursionOfferPriceUAH(b);
                if (pa !== pb) return pa - pb;
                return excursionOfferDateTs(a) - excursionOfferDateTs(b);
            });
        }

        function setSearchUiMode(mode) {
            const isExc = mode === 'excursion';
            const filtersKeepForExcursion = ['fb-budget'];
            const allBlocks = ['fb-rating', 'fb-budget', 'fb-stars', 'fb-meals', 'fb-hotels', 'fb-beach', 'fb-inhotel', 'fb-sports'];
            allBlocks.forEach((id) => {
                const el = document.getElementById(id);
                if (!el) return;
                if (!isExc) {
                    el.hidden = false;
                    return;
                }
                el.hidden = !filtersKeepForExcursion.includes(id);
            });
            const fallbackHideByLabel = ['Рейтинг готелю', 'Категорія готелю', 'Харчування', 'Готелі', 'Пляж', 'В готелі', 'Спорт і розваги'];
            document.querySelectorAll('#search-filters-aside .filter-block').forEach((block) => {
                const labelNode = block.querySelector('.filter-label');
                const label = (labelNode && labelNode.textContent ? labelNode.textContent : '').trim();
                if (!label) return;
                if (isExc && fallbackHideByLabel.includes(label)) {
                    block.hidden = true;
                }
            });
            if (searchSort) {
                if (isExc) {
                    searchSort.value = 'recommended';
                    searchSort.closest('label') && (searchSort.closest('label').style.display = 'none');
                } else {
                    searchSort.closest('label') && (searchSort.closest('label').style.display = '');
                }
            }
        }

        function buildHotelListFilter(hotels) {
            if (!sfHotelList) return;
            const names = [...new Set((hotels || []).map((h) => h.hotel || '').filter(Boolean))].sort((a, b) => a.localeCompare(b, 'uk'));
            const q = sfHotelSearch ? sfHotelSearch.value.trim().toLowerCase() : '';
            sfHotelList.innerHTML = names.map((name) => {
                const show = !q || name.toLowerCase().includes(q);
                return '<label style="' + (show ? '' : 'display:none') + '">' +
                    '<input type="checkbox" name="sf_hotel" value="' + escAttr(name) + '"> ' + esc(name) + '</label>';
            }).join('');
            sfHotelList.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
                cb.addEventListener('change', applySearchClientFiltersAndRender);
            });
        }

        function buildMealAndOperatorFilters(hotels) {
            if (!hotels || !hotels.length) {
                if (sfMealChips) {
                    sfMealChips.innerHTML = '';
                }
                if (sfOperatorList) {
                    sfOperatorList.innerHTML = '';
                }
                return;
            }
            const meals = new Set();
            const operators = new Set();
            (hotels || []).forEach((h) => {
                (h.offers || []).forEach((o) => {
                    const m = mealLabelFromOffer(o);
                    if (m) {
                        meals.add(m);
                    }
                    const op = operatorLabelFromOffer(o);
                    if (op) {
                        operators.add(op);
                    }
                });
            });
            if (sfMealChips) {
                const mealArr = [...meals].sort((a, b) => a.localeCompare(b, 'uk'));
                sfMealChips.innerHTML = mealArr.map((m) =>
                    '<label><input type="checkbox" name="sf_meal" value="' + escAttr(m) + '"><span>' + esc(m) + '</span></label>',
                ).join('');
                sfMealChips.querySelectorAll('input').forEach((i) => i.addEventListener('change', applySearchClientFiltersAndRender));
            }
            if (sfOperatorList) {
                const opArr = [...operators].sort((a, b) => a.localeCompare(b, 'uk'));
                sfOperatorList.innerHTML = opArr.map((op) =>
                    '<label style="display:none;gap:8px;align-items:center;font-weight:600">' +
                    '<input type="checkbox" name="sf_op" value="' + escAttr(op) + '">' +
                    '<span>' + esc(op) + '</span></label>',
                ).join('');
            }
            buildHotelListFilter(hotels);
        }

        function resetSearchSidebarFilters() {
            if (sfPriceMax) { sfPriceMax.value = '200000'; }
            if (sfPriceMin) { sfPriceMin.value = '0'; }
            if (sfRangeMax) { sfRangeMax.value = '200000'; }
            if (sfRangeMin) { sfRangeMin.value = '0'; }
            syncRangeTrack();
            if (sfRatingChips) {
                sfRatingChips.querySelectorAll('input[type="checkbox"]').forEach((i) => { i.checked = false; });
            }
            if (sfStarFilters) {
                sfStarFilters.querySelectorAll('input[type="checkbox"]').forEach((i) => { i.checked = false; });
            }
            buildMealAndOperatorFilters(popularSearchState.rawHotels || []);
        }

        function renderSearchResultRows(hotels) {
            if (!searchResultsList) {
                return;
            }
            const travelers = activeTravelers();
            const winStub = { date_from: (psD1 && psD1.value.trim()) || '', date_till: (psD2 && psD2.value.trim()) || '' };
            searchResultsList.innerHTML = (hotels || []).map((h) => {
                const offers = h.offers || [];
                const visibleOffers = detailVisibleOffers(offers);
                const first = visibleOffers.find((o) => o && o.key) || visibleOffers[0] || offers.find((o) => o && o.key) || offers[0] || {};
                const mergedOffer = Object.assign({}, first, {
                    hotel_id: first.hotel_id || h.hotel_id,
                    hotel: first.hotel || h.hotel,
                    key: first.key || '',
                });
                const img = pickHotelThumbFromSearch(h, first);
                const stars = starsMarkup(h.hotel_rating);
                const minP = hotelMinPriceUAH(h, travelers.adults, travelers.children);
                const mini = visibleOffers.slice(0, 4).map((o) => {
                    const df = o.date_from ? formatHumanDate(o.date_from) : '';
                    const pr = formatMoneyUAH(offerPriceForTravelers(o, travelers.adults, travelers.children));
                    return df + ' · ' + pr + (detailOfferIsStayOnly(o) ? ' · лише проживання' : '');
                }).join(' · ');
                const card = cardFromOffer(mergedOffer, { date_from: first.date_from || winStub.date_from, date_till: first.date_till || first.date_to || winStub.date_till });
                const href = detailUrl(card);
                const dep = detailOfferCity(first, mergedOffer);
                const firstNights = Number(first.duration || first.hnight || 0);
                const fallbackN1 = Math.max(1, parseInt(psN1 && psN1.value, 10) || 0);
                const fallbackN2 = Math.max(1, parseInt(psN2 && psN2.value, 10) || 0);
                const hasPackageOffer = hasTransportOffers(visibleOffers.length ? visibleOffers : offers);
                const nightsLabel = firstNights > 0
                    ? 'на ' + firstNights + ' ночей'
                    : (fallbackN1 && fallbackN2
                        ? (fallbackN1 === fallbackN2 ? 'на ' + fallbackN1 + ' ночей' : 'на ' + fallbackN1 + '-' + fallbackN2 + ' ночей')
                        : '');
                const travelersLabel = travelerPriceLabel(travelers.adults, travelers.children);
                const media = img
                    ? '<div class="search-result-photo"><img src="' + escAttr(img) + '" alt="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></div>'
                    : '<div class="search-result-photo" data-search-photo-for="' + escAttr(String(h.hotel_id || first.hotel_id || '')) + '"></div>';
                return '' +
                    '<article class="search-result-row">' +
                    media +
                    '<div class="search-result-body">' +
                    '<div class="stars" aria-hidden="true">' + esc(stars) + '</div>' +
                    '<h3>' + esc(h.hotel || first.hotel || 'Готель') + '</h3>' +
                    '<div class="search-result-meta">' + esc([h.country, h.region].filter(Boolean).join(', ')) + '</div>' +
                    '<div class="search-result-offers-mini">' + esc(mini || 'Варіанти уточнюються') + '</div>' +
                    '</div>' +
                    '<div class="search-result-side">' +
                    '<div class="search-result-stay">' + esc(nightsLabel) + '</div>' +
                    '<div class="search-result-depart">' + esc(dep ? 'Виїзд з ' + dep : '') + '</div>' +
                    '<div class="search-result-price">' + esc(minP != null ? 'від ' + formatMoneyUAH(minP) : 'Ціну уточнюємо') + '</div>' +
                    '<div class="search-result-price-note">' + esc(minP != null ? (travelersLabel + (hasPackageOffer ? '' : ' · лише проживання без транспорту')) : (visibleOffers.length ? 'Є лише варіанти проживання без транспорту' : travelersLabel)) + '</div>' +
                    '<a class="search-result-cta" href="' + escAttr(href) + '" data-key="' + escAttr(card.key) + '" data-hotel-id="' + escAttr(card.hotelId) + '">Переглянути деталі</a>' +
                    '</div>' +
                    '</article>';
            }).join('');

            void hydrateSearchResultThumbs(hotels || []);

            searchResultsList.querySelectorAll('a.search-result-cta').forEach((link) => {
                link.addEventListener('click', () => {
                    const key = link.getAttribute('data-key') || '';
                    const hotelId = link.getAttribute('data-hotel-id') || '';
                    const hotel = (hotels || []).find((item) => String(item.hotel_id) === String(hotelId));
                    const fo = hotel && hotel.offers && hotel.offers[0] ? hotel.offers[0] : {};
                    const c = cardFromOffer(fo, winStub);
                    if (c && c.key) {
                        try {
                            sessionStorage.setItem('ittour:last-card:' + c.key, JSON.stringify(c));
                        } catch (e) {
                        }
                    }
                });
            });
        }

        async function hydrateSearchResultThumbs(hotels) {
            if (!searchResultsList || !Array.isArray(hotels) || !hotels.length) {
                return;
            }
            const queue = [];
            hotels.forEach((hotel) => {
                const hotelId = String(hotel && hotel.hotel_id ? hotel.hotel_id : '');
                if (!hotelId) {
                    return;
                }
                const holder = searchResultsList.querySelector('[data-search-photo-for="' + hotelId + '"]');
                if (!holder) {
                    return;
                }
                queue.push({ hotelId, holder, hotel });
            });
            if (!queue.length) {
                return;
            }

            let cursor = 0;
            const workers = Array.from({ length: Math.min(3, queue.length) }, async () => {
                while (cursor < queue.length) {
                    const idx = cursor;
                    cursor += 1;
                    const item = queue[idx];
                    if (!item || !item.holder) {
                        continue;
                    }
                    const url = await fetchHotelThumbByHotelId(item.hotelId, item.hotel);
                    if (!url || !item.holder.isConnected) {
                        continue;
                    }
                    item.holder.innerHTML = '<img src="' + escAttr(url) + '" alt="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">';
                    item.holder.removeAttribute('data-search-photo-for');
                }
            });
            await Promise.all(workers);
        }

        function offersToHotelsShape(offers) {
            const map = new Map();
            (offers || []).forEach((offer) => {
                const hotelId = String(offer.hotel_id || offer.hotel || '');
                if (!hotelId) {
                    return;
                }
                if (!map.has(hotelId)) {
                    map.set(hotelId, {
                        hotel_id: hotelId,
                        hotel: offer.hotel || '',
                        hotel_rating: offer.hotel_stars || offer.hotel_rating || '',
                        country_id: offer.country_id || '',
                        country: offer.country || '',
                        region: offer.region || '',
                        min_price: null,
                        images: offer.hotel_images || [],
                        offers: [],
                    });
                }
                const hotel = map.get(hotelId);
                hotel.offers.push(offer);
                const price = Number((offer.prices && offer.prices['2']) != null ? offer.prices['2'] : (offer.price || 0));
                if (price > 0 && (hotel.min_price == null || price < hotel.min_price)) {
                    hotel.min_price = price;
                }
            });
            return [...map.values()];
        }

        async function renderSearchNoResultsWithSuggestions(message) {
            if (!searchResultsList) {
                return;
            }
            searchResultsList.innerHTML = '<p class="empty-state">На жаль, за вашим запитом нічого не знайдено. Показуємо найближчі популярні варіанти…</p>';
            if (searchResultsPagination) {
                searchResultsPagination.hidden = true;
            }
            if (searchResultsBanner) {
                searchResultsBanner.textContent = message || 'Показуємо найближчі популярні варіанти.';
            }

            const countryIds = [];
            const lastCountry = popularSearchState.lastQuery && popularSearchState.lastQuery.country ? String(popularSearchState.lastQuery.country) : '';
            if (lastCountry) {
                countryIds.push(lastCountry);
            }
            (FEATURED_COUNTRIES || []).forEach((country) => {
                const id = String(country.id || '');
                if (id && !countryIds.includes(id)) {
                    countryIds.push(id);
                }
            });

            const windows = buildCandidateWindows().slice(0, 2);
            const collected = [];
            const seen = new Set();
            for (const countryId of countryIds.slice(0, 3)) {
                for (const win of windows) {
                    try {
                        const query = {
                            type: '1',
                            kind: '1',
                            country: countryId,
                            adult_amount: String((psAdults && psAdults.value) || '2'),
                            child_amount: String((psChildren && psChildren.value) || '0'),
                            hotel_rating: '1:78',
                            night_from: String((psN1 && psN1.value) || '5'),
                            night_till: String((psN2 && psN2.value) || '9'),
                            date_from: win.date_from,
                            date_till: win.date_till,
                            items_per_page: '24',
                            hotel_info: '1',
                            hotel_image: '1',
                            currency: '2',
                        };
                        const data = await api('showcase/hot-offers/search', query);
                        const offers = Array.isArray(data && data.offers) ? data.offers : [];
                        offers.forEach((offer) => {
                            const key = String(offer.hotel_id || '') + '::' + String(offer.key || '');
                            if (!key || seen.has(key)) {
                                return;
                            }
                            seen.add(key);
                            collected.push(offer);
                        });
                    } catch (error) {
                    }
                }
            }

            const fallbackHotels = offersToHotelsShape(collected).slice(0, 12);
            if (!fallbackHotels.length) {
                searchResultsList.innerHTML = '<p class="empty-state">На жаль, за вашим запитом нічого не знайдено. Спробуйте змінити дати, кількість ночей або країну.</p>';
                return;
            }
            if (searchResultsCount) {
                searchResultsCount.textContent = 'Підібрано ' + fallbackHotels.length + ' популярних готелів';
            }
            if (searchResultsBanner) {
                searchResultsBanner.textContent = 'Точних збігів немає. Показуємо найближчі популярні пропозиції.';
            }
            renderSearchResultRows(fallbackHotels);
        }

        function renderSearchPagination(totalItems, currentPage, perPage, canLoadMore) {
            if (!searchResultsPagination || !searchPagePrev || !searchPageNext || !searchPageNumbers) {
                return;
            }
            const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
            if (totalItems <= perPage && !canLoadMore) {
                searchResultsPagination.hidden = true;
                searchPageNumbers.innerHTML = '';
                return;
            }

            searchResultsPagination.hidden = false;
            searchPagePrev.disabled = currentPage <= 1;
            searchPageNext.disabled = currentPage >= totalPages && !canLoadMore;
            searchPageNumbers.innerHTML = '';

            const pages = [];
            pages.push(1);
            for (let p = currentPage - 1; p <= currentPage + 1; p++) {
                if (p > 1 && p < totalPages) pages.push(p);
            }
            if (totalPages > 1) pages.push(totalPages);
            const uniq = [...new Set(pages)].sort((a, b) => a - b);
            let prev = 0;
            uniq.forEach((p) => {
                if (p - prev > 1) {
                    const dots = document.createElement('span');
                    dots.textContent = '...';
                    dots.style.color = 'var(--muted)';
                    dots.style.fontWeight = '700';
                    searchPageNumbers.appendChild(dots);
                }
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'search-page-number' + (p === currentPage ? ' is-active' : '');
                btn.textContent = String(p);
                btn.addEventListener('click', () => {
                    popularSearchState.page = p;
                    applySearchClientFiltersAndRender();
                    searchResultsPage && searchResultsPage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                searchPageNumbers.appendChild(btn);
                prev = p;
            });
        }

        function applySearchClientFiltersAndRender() {
            const modeInput = document.getElementById('ps-search-mode');
            const urlMode = new URL(window.location.href).searchParams.get('mode');
            const mode = String((modeInput && modeInput.value) || urlMode || 'hotel').trim();
            if (mode === 'excursion') {
                const pool = popularSearchState.excursionOffersPool || [];
                const usedFallback = Boolean(popularSearchState.excursionUsedFallback);
                if (!pool.length) {
                    return;
                }
                let list = dedupeExcursionOffers(pool.filter((o) => excursionPassesClientFilters(o)));
                const sortBy = popularSearchState.sortBy || 'recommended';
                if (sortBy === 'price_asc') {
                    list = [...list].sort((a, b) => {
                        const pa = excursionOfferPriceUAH(a);
                        const pb = excursionOfferPriceUAH(b);
                        if (pa === Infinity && pb === Infinity) return 0;
                        if (pa === Infinity) return 1;
                        if (pb === Infinity) return -1;
                        return pa - pb;
                    });
                } else if (sortBy === 'price_desc') {
                    list = [...list].sort((a, b) => {
                        const pa = excursionOfferPriceUAH(a);
                        const pb = excursionOfferPriceUAH(b);
                        if (pa === Infinity && pb === Infinity) return 0;
                        if (pa === Infinity) return 1;
                        if (pb === Infinity) return -1;
                        return pb - pa;
                    });
                } else if (sortBy === 'name_asc') {
                    list = [...list].sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'uk'));
                }
                if (searchResultsCount) {
                    searchResultsCount.textContent = 'Знайдено ' + list.length + ' турів';
                }
                if (searchResultsBanner) {
                    searchResultsBanner.textContent = list.length
                        ? (usedFallback ? 'Точних екскурсійних програм мало — показуємо доступні та найближчі популярні варіанти.' : 'Екскурсійні пропозиції за вашим запитом.')
                        : 'Екскурсійні тури не знайдено за цим запитом (спробуйте змінити бюджет у фільтрах).';
                }
                if (searchResultsPagination) {
                    searchResultsPagination.hidden = true;
                }
                if (!list.length) {
                    if (searchResultsList) {
                        searchResultsList.innerHTML = '<p class="empty-state">Нічого не знайдено за обраними фільтрами. Спробуйте змінити діапазон цін або скинути фільтри.</p>';
                    }
                    return;
                }
                renderExcursionRows(list.slice(0, EXCURSION_DISPLAY_CAP));
                return;
            }

            let list = (popularSearchState.rawHotels || []).filter((h) => hotelPassesClientFilters(h));
            const sortBy = popularSearchState.sortBy || 'recommended';
            if (sortBy === 'price_asc') {
                list = [...list].sort((a, b) => {
                    const pa = hotelMinPriceUAH(a);
                    const pb = hotelMinPriceUAH(b);
                    if (pa == null && pb == null) return 0;
                    if (pa == null) return 1;
                    if (pb == null) return -1;
                    return pa - pb;
                });
            } else if (sortBy === 'price_desc') {
                list = [...list].sort((a, b) => {
                    const pa = hotelMinPriceUAH(a);
                    const pb = hotelMinPriceUAH(b);
                    if (pa == null && pb == null) return 0;
                    if (pa == null) return 1;
                    if (pb == null) return -1;
                    return pb - pa;
                });
            } else if (sortBy === 'name_asc') {
                list = [...list].sort((a, b) => String(a.hotel || '').localeCompare(String(b.hotel || ''), 'uk'));
            } else if (sortBy === 'stars_desc') {
                list = [...list].sort((a, b) => Number(b.hotel_rating || 0) - Number(a.hotel_rating || 0));
            }
            if (searchResultsCount) {
                searchResultsCount.textContent = 'Знайдено ' + list.length + ' готелів';
            }
            if (!list.length) {
                if (searchResultsPagination) {
                    searchResultsPagination.hidden = true;
                }
                if (searchResultsList) {
                    if (!popularSearchState.rawHotels || !popularSearchState.rawHotels.length) {
                        void renderSearchNoResultsWithSuggestions('Спробуйте змінити дати, кількість ночей або виберіть інше місто виїзду.');
                    } else {
                        searchResultsList.innerHTML = '<p class="empty-state">Нічого не знайдено за обраними фільтрами. Спробуйте змінити бюджет або скинути фільтри.</p>';
                    }
                }
                return;
            }
            const totalPages = Math.max(1, Math.ceil(list.length / SEARCH_RESULTS_PER_PAGE));
            if (popularSearchState.page > totalPages) {
                popularSearchState.page = totalPages;
            }
            if (popularSearchState.page < 1) {
                popularSearchState.page = 1;
            }
            const start = (popularSearchState.page - 1) * SEARCH_RESULTS_PER_PAGE;
            const pageItems = list.slice(start, start + SEARCH_RESULTS_PER_PAGE);
            renderSearchResultRows(pageItems);
            const canLoadMore = list.length > 0 && list.length >= (popularSearchState.loadedTarget || POPULAR_SEARCH_BATCH_HOTELS);
            renderSearchPagination(list.length, popularSearchState.page, SEARCH_RESULTS_PER_PAGE, canLoadMore);
        }

        function renderExcursionRows(offers) {
            if (!searchResultsList) return;
            const adults = Math.max(1, parseInt(psAdults && psAdults.value, 10) || 2);
            const children = Math.max(0, parseInt(psChildren && psChildren.value, 10) || 0);
            const travelersLabel = travelerPriceLabel(adults, children);
            const buildExcursionDetailUrl = (offer) => {
                const base = EXCURSION_DETAIL_NAV_BASE || CATALOG_BASE_URL || DETAIL_BASE_URL || window.location.href;
                const url = new URL(base, window.location.origin);
                const countries = Array.isArray(offer.country_names) ? offer.country_names.join(', ') : (offer.country || '');
                const cities = Array.isArray(offer.city_names) ? offer.city_names.join(', ') : '';
                const image = excursionThumbFromOffer(offer);
                url.searchParams.set('excursion_detail', '1');
                url.searchParams.set('exc_key', String(offer.key || ''));
                url.searchParams.set('exc_name', String(offer.name || 'Екскурсійний тур'));
                url.searchParams.set('exc_country', String(countries || ''));
                url.searchParams.set('exc_cities', String(cities || ''));
                url.searchParams.set('exc_from', String(offer.from_city || ''));
                url.searchParams.set('exc_date', String(offer.date_from || ''));
                url.searchParams.set('exc_nights', String(offer.duration || ''));
                url.searchParams.set('exc_price', String((offer.prices && offer.prices['2']) != null ? offer.prices['2'] : (offer.price || '')));
                url.searchParams.set('exc_image', String(image || ''));
                return url.toString();
            };
            searchResultsList.innerHTML = (offers || []).map((offer) => {
                const name = offer.name || 'Екскурсійний тур';
                const countries = Array.isArray(offer.country_names) ? offer.country_names.join(', ') : (offer.country || '');
                const cities = Array.isArray(offer.city_names) ? offer.city_names.slice(0, 4).join(', ') : '';
                const fromCity = offer.from_city || '';
                const dateFrom = offer.date_from ? formatHumanDate(offer.date_from) : '';
                const nights = Number(offer.duration || 0);
                const price = Number((offer.prices && offer.prices['2']) != null ? offer.prices['2'] : (offer.price || 0));
                const image = excursionThumbFromOffer(offer);
                return '' +
                    '<article class="search-result-row">' +
                        (image ? '<div class="search-result-photo"><img src="' + escAttr(image) + '" alt="" loading="lazy"></div>' : '<div class="search-result-photo"></div>') +
                        '<div class="search-result-body">' +
                            '<h3>' + esc(name) + '</h3>' +
                            '<div class="search-result-meta">' + esc(countries || 'Маршрут уточнюється') + '</div>' +
                            '<div class="search-result-offers-mini">' + esc(cities || 'Міста маршруту уточнюються') + '</div>' +
                        '</div>' +
                        '<div class="search-result-side">' +
                            '<div class="search-result-stay">' + esc(nights > 0 ? ('на ' + nights + ' ночей') : '') + '</div>' +
                            '<div class="search-result-depart">' + esc(fromCity ? ('Виїзд з ' + fromCity) : '') + '</div>' +
                            '<div class="search-result-price">' + esc(price > 0 ? ('від ' + formatMoneyUAH(price)) : 'Ціну уточнюємо') + '</div>' +
                            '<div class="search-result-price-note">' + esc(travelersLabel + (dateFrom ? (' · ' + dateFrom) : '')) + '</div>' +
                            '<a class="search-result-cta" href="' + escAttr(buildExcursionDetailUrl(offer)) + '">Деталі</a>' +
                        '</div>' +
                    '</article>';
            }).join('');
        }

        async function runExcursionSearchFromForm(params) {
            popularSearchState.excursionOffersPool = [];
            popularSearchState.excursionUsedFallback = false;
            const offerUniqueKey = (offer) => String(offer && (offer.key || offer.id || '')) + '::' + String(offer && offer.date_from || '') + '::' + String(offer && offer.name || '');
            const collectOffers = (target, seenSet, offers, scope) => {
                (offers || []).forEach((offer) => {
                    const key = offerUniqueKey(offer);
                    if (!seenSet.has(key)) {
                        seenSet.add(key);
                        target.push(scope ? Object.assign({}, offer, { __excursion_scope: scope }) : offer);
                    }
                });
            };
            const cleanExcursionQuery = (query) => {
                const cleaned = {};
                Object.keys(query || {}).forEach((key) => {
                    const value = query[key];
                    if (value != null && String(value).trim() !== '') {
                        cleaned[key] = String(value);
                    }
                });
                return cleaned;
            };
            const excursionQueryKey = (query) => JSON.stringify(stableQuery(cleanExcursionQuery(query)));
            const fetchExcursionOffers = async (query) => {
                const data = await api('module-excursion/search', cleanExcursionQuery(query));
                return Array.isArray(data && data.offers) ? data.offers : [];
            };
            const fetchExcursionCountryIds = async () => {
                try {
                    const data = await api('module-excursion/params', {});
                    return (Array.isArray(data && data.countries) ? data.countries : [])
                        .map((country) => String(country && country.id || '').trim())
                        .filter((id) => /^\d+$/.test(id) && Number(id) > 0);
                } catch (e) {
                    return [];
                }
            };
            const runExcursionBatch = async (queries, target, seenSet, stopAt, maxCalls, scope) => {
                const unique = [];
                const keys = new Set();
                (queries || []).forEach((query) => {
                    const key = excursionQueryKey(query);
                    if (!keys.has(key)) {
                        keys.add(key);
                        unique.push(query);
                    }
                });
                const limited = unique.slice(0, Math.max(1, maxCalls || 8));
                let cursor = 0;
                const workers = Array.from({ length: Math.min(2, limited.length) }, async () => {
                    while (cursor < limited.length && dedupeExcursionOffers(target).length < stopAt) {
                        const index = cursor;
                        cursor += 1;
                        try {
                            const offers = await fetchExcursionOffers(limited[index]);
                            collectOffers(target, seenSet, offers, scope);
                        } catch (e) {}
                    }
                });
                await Promise.all(workers);
            };
            const makeExcursionQuery = (countryId, win, overrides) => ({
                country: String(countryId),
                date_from: String(win.date_from || ''),
                date_till: String(win.date_till || ''),
                night_from: String(params.n1 || '2'),
                night_till: String(params.n2 || '21'),
                adult: String(params.adults || '2'),
                child: String(params.children || '0'),
                transport_type: '2',
                page: '1',
                items_per_page: '60',
                ...(overrides || {}),
            });
            const buildExcursionFallbackWindows = () => {
                const base = new Date();
                base.setHours(12, 0, 0, 0);
                return [0, 28, 56, 84, 112, 140].map((offset) => {
                    const start = new Date(base);
                    start.setDate(start.getDate() + offset);
                    const end = new Date(start);
                    end.setDate(end.getDate() + 27);
                    return { date_from: formatApiDate(start), date_till: formatApiDate(end) };
                });
            };
            const groupCountryIds = (ids) => {
                const groups = [];
                for (let i = 0; i < ids.length; i += 10) {
                    groups.push(ids.slice(i, i + 10).join(':'));
                }
                return groups;
            };

            const countryCandidates = [];
            if (params.country) {
                countryCandidates.push(String(params.country));
            } else {
                (FEATURED_COUNTRIES || []).forEach((country) => {
                    const id = String(country.id || '');
                    if (id && !countryCandidates.includes(id)) {
                        countryCandidates.push(id);
                    }
                });
                if (!countryCandidates.length && DEFAULT_COUNTRY_ID) {
                    countryCandidates.push(String(DEFAULT_COUNTRY_ID));
                }
            }

            const windows = [];
            if (params.d1 && params.d2) {
                windows.push({ date_from: String(params.d1), date_till: String(params.d2) });
            }
            buildCandidateWindows().slice(0, 2).forEach((w) => windows.push({ date_from: w.date_from, date_till: w.date_till }));

            const merged = [];
            const seen = new Set();
            const windowMap = new Map();
            windows.forEach((win) => {
                if (win && win.date_from && win.date_till) {
                    windowMap.set(String(win.date_from) + '::' + String(win.date_till), win);
                }
            });
            const primaryWindows = Array.from(windowMap.values()).slice(0, 3);
            const fromCityIds = parseFromCityIds(params.from).slice(0, 2);
            const primaryFromIds = ['', ...fromCityIds].filter((value, index, source) => source.indexOf(value) === index);
            const primaryQueries = [];
            countryCandidates.slice(0, 2).forEach((countryId) => {
                primaryWindows.forEach((win) => {
                    primaryFromIds.forEach((fromId) => {
                        primaryQueries.push(makeExcursionQuery(countryId, win, fromId ? { from_city: fromId } : {}));
                    });
                });
            });
            await runExcursionBatch(primaryQueries, merged, seen, 30, 8, 'primary');

            const TARGET_POOL_MIN = 18;
            let mergedUnique = dedupeExcursionOffers(merged);

            if (mergedUnique.length < TARGET_POOL_MIN) {
                const excursionCountryIds = await fetchExcursionCountryIds();
                const extraCountries = [
                    ...(params.country ? [String(params.country)] : []),
                    ...excursionCountryIds,
                    '318', '338', '320', '16', '372', '376', '49', '420', '434', '39',
                ].filter((value, index, source) => source.indexOf(value) === index);
                const fallbackQueries = [];
                groupCountryIds(extraCountries).forEach((countryGroup) => {
                    buildExcursionFallbackWindows().forEach((win) => {
                        fallbackQueries.push(makeExcursionQuery(countryGroup, win, {
                            night_from: '1',
                            night_till: '21',
                            transport_type: '',
                            items_per_page: '100',
                        }));
                    });
                });
                const beforeFallbackCount = mergedUnique.length;
                await runExcursionBatch(fallbackQueries, merged, seen, TARGET_POOL_MIN + 12, 18, 'fallback');
                mergedUnique = dedupeExcursionOffers(merged);
                popularSearchState.excursionUsedFallback = mergedUnique.length > beforeFallbackCount;
            }

            if (!mergedUnique.length) {
                popularSearchState.excursionOffersPool = [];
                popularSearchState.excursionUsedFallback = false;
                popularSearchState.rawHotels = [];
                if (searchResultsBanner) {
                    searchResultsBanner.textContent = 'Екскурсійні тури не знайдено за цим запитом.';
                }
                if (searchResultsCount) {
                    searchResultsCount.textContent = 'Знайдено 0 турів';
                }
                if (searchResultsList) {
                    void renderSearchNoResultsWithSuggestions('Спробуйте змінити дати, кількість ночей або країну призначення.');
                }
                if (searchResultsPagination) {
                    searchResultsPagination.hidden = true;
                }
                setSearchResultsLoading(false);
                saveSearchRenderCache(params, 'excursion');
                return;
            }

            popularSearchState.excursionOffersPool = mergedUnique;
            popularSearchState.rawHotels = [];
            if (searchResultsPagination) {
                searchResultsPagination.hidden = true;
            }
            setSearchResultsLoading(false);
            applySearchClientFiltersAndRender();
            saveSearchRenderCache(params, 'excursion');
        }

        async function runPopularSearchFromForm(options) {
            const opts = options || {};
            if (DETAIL_TOUR_KEY || !popularSearchForm) {
                return;
            }
            const params = readPopularSearchParamsFromForm();
            if (!params.country) {
                return;
            }
            if (!opts.skipPush) {
                writePopularSearchToUrl(params, 'push');
            }
            openPopularSearchUI();
            const modeInput = document.getElementById('ps-search-mode');
            const urlMode = new URL(window.location.href).searchParams.get('mode');
            const searchMode = String((modeInput && modeInput.value) || urlMode || 'hotel').trim();
            setSearchUiMode(searchMode);
            setSearchResultsLoading(true);
            if (searchResultsList && !opts.loadMore) {
                searchResultsList.innerHTML = '';
            }
            if (searchResultsBanner) {
                searchResultsBanner.textContent = opts.loadMore ? 'Довантажуємо наступну сторінку…' : 'Шукаємо тури…';
            }
            if (searchResultsCount) {
                searchResultsCount.textContent = '';
            }
            if (searchMode !== 'excursion') {
                void runSearchV2Shadow(params, { loadMore: Boolean(opts.loadMore) });
            }
            if (searchMode === 'excursion') {
                try {
                    await runExcursionSearchFromForm(params);
                } catch (e) {
                    if (searchResultsList) {
                        searchResultsList.innerHTML = '<p class="error-state">' + esc((e && e.message) || 'Помилка пошуку екскурсій') + '</p>';
                    }
                } finally {
                    setSearchResultsLoading(false);
                }
                return;
            }

            const n1 = Math.max(1, Math.min(parseInt(params.n1, 10) || 6, parseInt(params.n2, 10) || 8));
            const n2 = Math.max(n1, parseInt(params.n2, 10) || 8);
            popularSearchState.lastQuery = { country: params.country, from: params.from };
            if (!opts.keepPage) {
                popularSearchState.page = 1;
            }
            if (!opts.loadMore) {
                popularSearchState.loadedTarget = POPULAR_SEARCH_BATCH_HOTELS;
                popularSearchState.loadingMore = false;
            }
            const targetHotels = Math.max(
                POPULAR_SEARCH_BATCH_HOTELS,
                Number(opts.targetHotels || popularSearchState.loadedTarget || POPULAR_SEARCH_BATCH_HOTELS),
            );

            /* Будуємо вікна: спочатку з форми, потім автоматичні */
            function windowsToTry() {
                const wins = [];
                if (params.d1 && params.d2) {
                    wins.push({ date_from: params.d1, date_till: params.d2, exact: true });
                }
                buildCandidateWindows().forEach((w) => wins.push({ ...w, exact: false }));
                return wins;
            }

            function buildQuery(win, nightFrom, nightTill, fromCity) {
                const q = {
                    type: '1',
                    kind: '1',
                    country: String(params.country),
                    adult_amount: String(params.adults),
                    child_amount: String(params.children),
                    hotel_rating: '3:78',
                    night_from: String(nightFrom),
                    night_till: String(nightTill),
                    date_from: win.date_from,
                    date_till: win.date_till,
                    items_per_page: '48',
                    hotel_info: '1',
                    hotel_image: '1',
                    currency: '2',
                };
                if (fromCity) {
                    q.from_city = String(fromCity);
                }
                if (params.region) {
                    q.region = String(params.region);
                }
                if (params.hotel) {
                    q.hotel = String(params.hotel);
                }
                return q;
            }

            function buildLooseQuery(countryId, win, nightFrom, nightTill) {
                const q = {
                    type: '1',
                    kind: '1',
                    country: String(countryId || params.country),
                    adult_amount: String(params.adults),
                    child_amount: String(params.children),
                    hotel_rating: '1:78',
                    night_from: String(nightFrom),
                    night_till: String(nightTill),
                    date_from: win.date_from,
                    date_till: win.date_till,
                    items_per_page: '60',
                    hotel_info: '1',
                    hotel_image: '1',
                    currency: '2',
                };
                if (params.region && String(countryId || params.country) === String(params.country)) {
                    q.region = String(params.region);
                }
                return q;
            }

            function broadWindowsToTry() {
                const base = new Date();
                base.setHours(12, 0, 0, 0);
                return [14, 28, 42, 56].map((offset) => {
                    const start = new Date(base);
                    start.setDate(start.getDate() + offset);
                    const end = new Date(start);
                    end.setDate(end.getDate() + 14);
                    return { date_from: formatApiDate(start), date_till: formatApiDate(end), exact: false };
                });
            }

            /* Конвертуємо search-list офери у формат hotels */
            function offersToHotels(offers) {
                const map = new Map();
                (offers || []).forEach((o) => {
                    const hid = String(o.hotel_id || o.hotel || '');
                    if (!map.has(hid)) {
                        map.set(hid, {
                            hotel_id: hid,
                            hotel: o.hotel || '',
                            hotel_rating: o.hotel_stars || o.hotel_rating || '',
                            country_id: o.country_id || params.country || '',
                            country: o.country || '',
                            region: o.region || '',
                            min_price: null,
                            images: o.hotel_images || [],
                            offers: [],
                        });
                    }
                    const h = map.get(hid);
                    const p = Number((o.prices && o.prices['2']) != null ? o.prices['2'] : (o.price || 0));
                    if (p > 0 && offerHasTransport(o) && (h.min_price === null || p < h.min_price)) {
                        h.min_price = p;
                    }
                    h.offers.push(o);
                });
                return [...map.values()];
            }

            let hotels = [];
            let usedFallback = false;
            let searchRenderedOnce = false;
            const wins = windowsToTry();
            const hotelsById = new Map();

            function mergeHotels(nextHotels) {
                (nextHotels || []).forEach((hotel) => {
                    const hotelId = String(hotel.hotel_id || hotel.hotel || '');
                    if (!hotelId) return;
                    if (!hotelsById.has(hotelId)) {
                        hotelsById.set(hotelId, {
                            ...hotel,
                            offers: [...(hotel.offers || [])],
                        });
                        return;
                    }
                    const prev = hotelsById.get(hotelId);
                    const mergedOffers = dedupeHotels([...(prev.offers || []), ...(hotel.offers || [])]);
                    prev.offers = mergedOffers;
                    if (!prev.country_id && hotel.country_id) {
                        prev.country_id = hotel.country_id;
                    }
                    if (hotel.min_price != null && (prev.min_price == null || Number(hotel.min_price) < Number(prev.min_price))) {
                        prev.min_price = hotel.min_price;
                    }
                    if ((!prev.images || !prev.images.length) && hotel.images && hotel.images.length) {
                        prev.images = hotel.images;
                    }
                });
                hotels = [...hotelsById.values()].sort((a, b) => {
                    const pa = hotelMinPriceUAH(a);
                    const pb = hotelMinPriceUAH(b);
                    if (pa == null && pb == null) return 0;
                    if (pa == null) return 1;
                    if (pb == null) return -1;
                    return pa - pb;
                });
            }

            function enoughHotels() {
                return hotels.length >= targetHotels;
            }

            function flushSearchResults(partial) {
                popularSearchState.rawHotels = hotels;
                popularSearchState.loadedTarget = hotels.length;
                applySearchClientFiltersAndRender();
                if (partial && !searchRenderedOnce && hotels.length >= SEARCH_MIN_SHOW_HOTELS) {
                    searchRenderedOnce = true;
                    setSearchResultsLoading(false);
                    if (searchResultsBanner) {
                        searchResultsBanner.textContent = enoughHotels()
                            ? 'Пропозиції за вашим запитом.'
                            : ('Знайдено ' + hotels.length + ' — шукаємо ще…');
                    }
                }
            }

            async function searchOffers(getQuery) {
                try {
                    const data = await api('module/search-list', getQuery());
                    const code = apiErrorCode(data);
                    if (code === 107 || code === 108 || code === 109) {
                        showApiLimitMessage(code, apiError(data));
                        throw new Error('api_limit');
                    }
                    return dedupeHotels(sortHotels(data.offers || []));
                } catch (e) {
                    if (e && e.message === 'api_limit') {
                        throw e;
                    }
                    return [];
                }
            }

            let apiLimitHit = false;
            /* Раунд 1: паралельно перші вікна + основний діапазон ночей (до 3 міст виїзду) */
            const round1Wins = wins.slice(0, 2);
            const fromCityIds = parseFromCityIds(params.from);
            const fromIdsForSearch = fromCityIds.length ? fromCityIds : [''];
            let round1Batches = [];
            try {
                round1Batches = await Promise.all(
                    round1Wins.flatMap((win) =>
                        fromIdsForSearch.map((fromId) =>
                            searchOffers(() => buildQuery(win, n1, n2, fromId || null)),
                        ),
                    ),
                );
            } catch (e) {
                if (e && e.message === 'api_limit') {
                    apiLimitHit = true;
                    round1Batches = [];
                }
            }
            round1Batches.forEach((offers, idx) => {
                if (!offers.length) {
                    return;
                }
                mergeHotels(offersToHotels(offers));
                if (!round1Wins[idx].exact) {
                    usedFallback = true;
                }
            });
            flushSearchResults(true);
            if (!apiLimitHit && !enoughHotels() && hotels.length < SEARCH_MIN_SHOW_HOTELS) {
                outer: for (const win of round1Wins) {
                    for (const fromId of fromIdsForSearch) {
                        let offers = [];
                        try {
                            offers = await searchOffers(() => buildQuery(win, Math.max(1, n1 - 2), n2 + 2, fromId || null));
                        } catch (e) {
                            if (e && e.message === 'api_limit') {
                                apiLimitHit = true;
                                break outer;
                            }
                        }
                        if (!offers.length) {
                            continue;
                        }
                        mergeHotels(offersToHotels(offers));
                        usedFallback = true;
                        flushSearchResults(true);
                        if (enoughHotels() || hotels.length >= SEARCH_MIN_SHOW_HOTELS) {
                            break outer;
                        }
                    }
                }
            }

            /* Раунд 2: без міста виїзду — лише якщо ще мало */
            if (!apiLimitHit && !enoughHotels() && hotels.length < SEARCH_MIN_SHOW_HOTELS) {
                for (const win of round1Wins) {
                    let offers = [];
                    try {
                        offers = await searchOffers(() => buildQuery(win, n1, n2, null));
                    } catch (e) {
                        if (e && e.message === 'api_limit') {
                            apiLimitHit = true;
                            break;
                        }
                    }
                    if (offers.length) {
                        mergeHotels(offersToHotels(offers));
                        usedFallback = true;
                        flushSearchResults(true);
                        if (enoughHotels() || hotels.length >= SEARCH_MIN_SHOW_HOTELS) {
                            break;
                        }
                    }
                }
            }

            /* Раунд 3: ширший пошук по країні — обмежено 2 вікнами */
            if (!apiLimitHit && !enoughHotels()) {
                for (const win of broadWindowsToTry().slice(0, 2)) {
                    let offers = [];
                    try {
                        offers = await searchOffers(() => buildLooseQuery(params.country, win, n1, n2));
                    } catch (e) {
                        if (e && e.message === 'api_limit') {
                            apiLimitHit = true;
                            break;
                        }
                    }
                    if (offers.length) {
                        mergeHotels(offersToHotels(offers));
                        usedFallback = true;
                        flushSearchResults(true);
                        if (enoughHotels()) {
                            break;
                        }
                    }
                }
            }

            if (searchResultsBanner) {
                if (hotels.length === 0) {
                    searchResultsBanner.textContent = 'Поки немає доступних пропозицій навіть у розширеному пошуку. Спробуйте іншу країну або дату.';
                } else if (usedFallback) {
                    searchResultsBanner.textContent = 'Точних збігів немає — показуємо найближчі доступні пропозиції.';
                } else {
                    searchResultsBanner.textContent = 'Пропозиції за вашим запитом.';
                }
            }

            popularSearchState.rawHotels = hotels;
            popularSearchState.loadedTarget = hotels.length;
            popularSearchState.loadingMore = false;
            if (!opts.loadMore) {
                resetSearchSidebarFilters();
            }
            applySearchClientFiltersAndRender();
            saveSearchRenderCache(params, 'hotel');

            setSearchResultsLoading(false);
        }

        function initPopularSearchFlow() {
            if (ANEX_CATALOG_LITE || DETAIL_TOUR_KEY || !popularSearchForm) {
                return;
            }
            ensurePsPickerPortal();
            hardenPickerInputsVisual();
            bindDateMask(psD1);
            bindDateMask(psD2);
            bindShortNumericMask(psN1, 2, '6');
            bindShortNumericMask(psN2, 2, '8');
            const dates = defaultSearchDatesPs();
            if (psD1 && !psD1.value) {
                psD1.value = dates.d1;
            }
            if (psD2 && !psD2.value) {
                psD2.value = dates.d2;
            }
            syncPsFormFromHero();
            void refreshPsFromSelect();

            popularSearchForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await refreshPsFromSelect({ keepCurrent: true });
                await runPopularSearchFromForm({});
            });

            if (psCountryPicker) {
                psCountryPicker.addEventListener('click', async () => {
                    hardenPickerInputsVisual();
                    await openPsPicker('country');
                });
                psCountryPicker.addEventListener('mouseenter', hardenPickerInputsVisual);
                psCountryPicker.addEventListener('focus', hardenPickerInputsVisual);
            }

            if (psFrom) {
                psFrom.addEventListener('change', () => {
                    if (psFrom.value) {
                        setPsFromSelectedIds([psFrom.value]);
                    }
                    if (departureSelect) {
                        departureSelect.value = psFrom.value;
                    }
                });
            }

            if (psFromPicker) {
                psFromPicker.addEventListener('click', async () => {
                    hardenPickerInputsVisual();
                    if (!psCountryId || !psCountryId.value) {
                        const fallbackCountry = activeCountryId || DEFAULT_COUNTRY_ID || '';
                        if (fallbackCountry) {
                            await setPsCountryById(fallbackCountry, { keepDeparture: true });
                        }
                    }
                    await openPsPicker('from');
                });
                psFromPicker.addEventListener('mouseenter', hardenPickerInputsVisual);
                psFromPicker.addEventListener('focus', hardenPickerInputsVisual);
            }

            if (psPickerBack) {
                psPickerBack.addEventListener('click', async () => {
                    if (psPickerMode !== 'country' || !isPsMobileViewport()) {
                        return;
                    }
                    psMobileCountryStep = 'countries';
                    if (psPicker) {
                        psPicker.classList.add('is-mobile-step-countries');
                        psPicker.classList.remove('is-mobile-step-regions');
                    }
                    if (psPickerTitle) {
                        psPickerTitle.textContent = 'Країна, курорт, готель';
                    }
                    if (psPickerSearch) {
                        psPickerSearch.value = '';
                    }
                    await renderPsPicker();
                    updatePsPickerActionLabel();
                });
            }

            if (psPickerClose) {
                psPickerClose.addEventListener('click', closePsPicker);
            }
            if (psPickerBackdrop) {
                psPickerBackdrop.addEventListener('click', closePsPicker);
            }
            if (psPickerApply) {
                psPickerApply.addEventListener('click', async () => {
                    if (psPickerMode === 'country' && isPsMobileViewport() && psMobileCountryStep === 'countries') {
                        if (!psPickerCountryId) {
                            return;
                        }
                        psMobileCountryStep = 'regions';
                        if (psPicker) {
                            psPicker.classList.remove('is-mobile-step-countries');
                            psPicker.classList.add('is-mobile-step-regions');
                        }
                        if (psPickerTitle) {
                            const meta = countryMetaById.get(String(psPickerCountryId));
                            psPickerTitle.textContent = meta && meta.name ? meta.name : 'Курорти';
                        }
                        await renderPsPicker();
                        updatePsPickerActionLabel();
                        return;
                    }
                    closePsPicker();
                });
            }
            if (psPickerSearch) {
                psPickerSearch.addEventListener('input', () => {
                    void renderPsPicker();
                });
            }

            if (searchBackBrowse) {
                searchBackBrowse.addEventListener('click', () => {
                    closePopularSearchUI();
                });
            }

            if (searchFiltersReset) {
                searchFiltersReset.addEventListener('click', () => {
                    popularSearchState.page = 1;
                    resetSearchSidebarFilters();
                    const modeInput = document.getElementById('ps-search-mode');
                    const mode = String((modeInput && modeInput.value) || new URL(window.location.href).searchParams.get('mode') || 'hotel').trim();
                    if (mode === 'excursion') {
                        void runPopularSearchFromForm({ skipPush: true });
                        return;
                    }
                    applySearchClientFiltersAndRender();
                });
            }
            if (searchSort) {
                searchSort.addEventListener('change', () => {
                    popularSearchState.sortBy = searchSort.value || 'recommended';
                    popularSearchState.page = 1;
                    applySearchClientFiltersAndRender();
                });
            }

            if (searchPagePrev) {
                searchPagePrev.addEventListener('click', () => {
                    popularSearchState.page = Math.max(1, (popularSearchState.page || 1) - 1);
                    applySearchClientFiltersAndRender();
                    searchResultsPage && searchResultsPage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
            if (searchPageNext) {
                searchPageNext.addEventListener('click', async () => {
                    const nextPage = (popularSearchState.page || 1) + 1;
                    const neededHotels = nextPage * SEARCH_RESULTS_PER_PAGE;
                    if ((popularSearchState.rawHotels || []).length < neededHotels && !popularSearchState.loadingMore) {
                        popularSearchState.loadingMore = true;
                        try {
                            await runPopularSearchFromForm({
                                skipPush: true,
                                loadMore: true,
                                keepPage: true,
                                targetHotels: neededHotels,
                            });
                        } finally {
                            popularSearchState.loadingMore = false;
                        }
                    }
                    popularSearchState.page = nextPage;
                    applySearchClientFiltersAndRender();
                    searchResultsPage && searchResultsPage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }

            const filterAside = document.getElementById('search-filters-aside');
            if (filterAside) {
                filterAside.addEventListener('change', () => {
                    const modeInput = document.getElementById('ps-search-mode');
                    const mode = String((modeInput && modeInput.value) || new URL(window.location.href).searchParams.get('mode') || 'hotel').trim();
                    if (mode === 'excursion') {
                        void runPopularSearchFromForm({ skipPush: true });
                        return;
                    }
                    popularSearchState.page = 1;
                    applySearchClientFiltersAndRender();
                });
            }

            /* ── Mobile filter drawer ── */
            (function () {
                const toggleBtn = document.getElementById('sf-mobile-toggle');
                const backdrop = document.getElementById('sf-mobile-backdrop');
                const drawer = document.getElementById('search-filters-drawer');
                const drawerClose = document.getElementById('sf-drawer-close');
                const drawerBody = document.getElementById('sf-drawer-body');
                const drawerReset = document.getElementById('sf-drawer-reset');
                const filterAsideEl = document.getElementById('search-filters-aside');
                const mobileCountEl = document.getElementById('sf-mobile-count');

                if (!toggleBtn || !drawer) return;

                function syncDrawer() {
                    if (!filterAsideEl || !drawerBody) return;
                    /* Clone filter blocks into drawer */
                    drawerBody.innerHTML = '';
                    filterAsideEl.querySelectorAll('.filter-block').forEach(block => {
                        const clone = block.cloneNode(true);
                        clone.querySelectorAll('[id]').forEach(el => {
                            el.id = 'drawer-' + el.id;
                        });
                        clone.querySelectorAll('[for]').forEach(el => {
                            el.htmlFor = 'drawer-' + el.htmlFor;
                        });
                        /* Sync values from aside to drawer */
                        clone.querySelectorAll('input[type="number"]').forEach(inp => {
                            const orig = filterAsideEl.querySelector('#' + inp.id.replace('drawer-', ''));
                            if (orig) inp.value = orig.value;
                        });
                        clone.querySelectorAll('input[type="checkbox"]').forEach(inp => {
                            const orig = filterAsideEl.querySelector('input[type="checkbox"][name="' + inp.name + '"][value="' + inp.value + '"]');
                            if (orig) inp.checked = orig.checked;
                        });
                        drawerBody.appendChild(clone);
                    });

                    /* Push changes back to the real aside and trigger filters */
                    drawerBody.addEventListener('change', (e) => {
                        const input = e.target;
                        if (!input.matches('input')) return;
                        if (input.type === 'number') {
                            const orig = filterAsideEl.querySelector('#' + input.id.replace('drawer-', ''));
                            if (orig) { orig.value = input.value; popularSearchState.page = 1; applySearchClientFiltersAndRender(); }
                        }
                        if (input.type === 'checkbox') {
                            const orig = filterAsideEl.querySelector('input[type="checkbox"][name="' + input.name + '"][value="' + input.value + '"]');
                            if (orig) { orig.checked = input.checked; popularSearchState.page = 1; applySearchClientFiltersAndRender(); }
                        }
                        updateMobileCount();
                    }, { once: false });
                }

                function updateMobileCount() {
                    if (!mobileCountEl || !filterAsideEl) return;
                    let n = 0;
                    const pmax = filterAsideEl.querySelector('#sf-price-max');
                    if (pmax && Number(pmax.value) < 200000) n++;
                    filterAsideEl.querySelectorAll('input[type="checkbox"]:checked').forEach(() => n++);
                    mobileCountEl.textContent = n > 0 ? n + ' активних' : '';
                }

                function openDrawer() {
                    syncDrawer();
                    drawer.classList.add('is-open');
                    backdrop.classList.add('is-open');
                    document.body.style.overflow = 'hidden';
                }

                function closeDrawer() {
                    drawer.classList.remove('is-open');
                    backdrop.classList.remove('is-open');
                    document.body.style.overflow = '';
                }

                toggleBtn.addEventListener('click', openDrawer);
                backdrop.addEventListener('click', closeDrawer);
                if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
                if (drawerReset) {
                    drawerReset.addEventListener('click', () => {
                        resetSearchSidebarFilters();
                        applySearchClientFiltersAndRender();
                        /* Sync reset to drawer inputs */
                        if (drawerBody) {
                            drawerBody.querySelectorAll('input[type="number"]').forEach(inp => { inp.value = '200000'; });
                            drawerBody.querySelectorAll('input[type="checkbox"]').forEach(inp => { inp.checked = false; });
                        }
                        updateMobileCount();
                    });
                }

                /* Update count after any desktop filter change too */
                if (filterAsideEl) {
                    filterAsideEl.addEventListener('change', updateMobileCount);
                }
            })();

            window.addEventListener('popstate', () => {
                const p = readPopularSearchFromUrl();
                if (p) {
                    void (async () => {
                        await applyPopularSearchParamsToForm(p);
                        openPopularSearchUI();
                        const mode = currentSearchModeFromState();
                        if (!restoreSearchRenderCache(p, mode)) {
                            await runPopularSearchFromForm({ skipPush: true });
                        }
                    })();
                } else {
                    closePopularSearchUI();
                }
            });

            void (async () => {
                await refreshPsFromSelect();
                const initial = readPopularSearchFromUrl();
                if (initial) {
                    await applyPopularSearchParamsToForm(initial);
                    openPopularSearchUI();
                    const mode = currentSearchModeFromState();
                    if (!restoreSearchRenderCache(initial, mode)) {
                        await runPopularSearchFromForm({ skipPush: true });
                    }
                }
            })();
        }

        function openMobileMenu() {
            if (!mobileMenu) {
                return;
            }
            mobileMenu.hidden = false;
            document.body.classList.add('menu-open');
            if (menuToggle) {
                menuToggle.setAttribute('aria-expanded', 'true');
            }
        }

        function closeMobileMenu() {
            if (!mobileMenu) {
                return;
            }
            mobileMenu.hidden = true;
            document.body.classList.remove('menu-open');
            if (menuToggle) {
                menuToggle.setAttribute('aria-expanded', 'false');
            }
        }

        function openBookingForm(tourKey, tourTitle, offerData) {
            if (!bookingBackdrop || !bookingForm) {
                return;
            }
            bookingForm.reset();
            if (bookingTourKey) {
                bookingTourKey.value = tourKey || DETAIL_TOUR_KEY || '';
            }
            if (bookingTourTitle) {
                bookingTourTitle.value = tourTitle || '';
            }
            if (bookingTourName) {
                bookingTourName.textContent = tourTitle ? 'Тур: ' + tourTitle : 'Менеджер перевірить тур і звʼяжеться з вами.';
            }

            /* Populate offer detail summary */
            const od = offerData || {};
            const summaryEl = bookingOfferSummary || document.getElementById('booking-offer-summary');
            function setField(rowId, valId, hiddenId, value) {
                const row = document.getElementById(rowId);
                const val = document.getElementById(valId);
                const hid = hiddenId ? document.getElementById(hiddenId) : null;
                if (hid) hid.value = value || '';
                if (row && val) {
                    if (value) { val.textContent = value; row.hidden = false; }
                    else { row.hidden = true; }
                }
            }
            if (summaryEl) {
                summaryEl.hidden = !(od.date || od.city || od.nights || od.room || od.meal || od.price || od.operator);
            }
            setField('bos-date-row', 'bos-date', 'booking-tour-date', od.date || '');
            setField('bos-city-row', 'bos-city', 'booking-tour-city', od.city || '');
            setField('bos-nights-row', 'bos-nights', 'booking-tour-nights', od.nights || '');
            setField('bos-room-row', 'bos-room', 'booking-tour-room', od.room || '');
            setField('bos-meal-row', 'bos-meal', 'booking-tour-meal', od.meal || '');
            setField('bos-price-row', 'bos-price', 'booking-tour-price', od.price || '');
            setField('bos-operator-row', 'bos-operator', 'booking-tour-operator', od.operator || '');

            if (bookingStatus) {
                bookingStatus.classList.remove('is-success', 'is-error');
                bookingStatus.textContent = '';
            }
            if (bookingSuccessCard) {
                bookingSuccessCard.hidden = true;
            }
            bookingBackdrop.hidden = false;
        }

        function closeBookingForm() {
            if (bookingBackdrop) {
                bookingBackdrop.hidden = true;
            }
            if (bookingSuccessCard) {
                bookingSuccessCard.hidden = true;
            }
        }

        function hotelDetailSlots() {
            return {
                head: document.getElementById('anex-detail-head'),
                info: document.getElementById('anex-detail-info'),
                prices: document.getElementById('anex-detail-prices'),
                calendar: document.getElementById('anex-detail-calendar'),
                facilities: document.getElementById('anex-detail-facilities'),
                reviews: document.getElementById('anex-detail-reviews'),
                similarPrice: document.getElementById('anex-detail-similar-price'),
                similarBeach: document.getElementById('anex-detail-similar-beach'),
            };
        }

        function renderDetailSlotsNotice(title, message, linkHref, linkLabel) {
            const slots = hotelDetailSlots();
            const visibleSlots = Object.values(slots).filter(Boolean);
            if (!visibleSlots.length) {
                return false;
            }

            const safeTitle = esc(title || 'Картка готелю');
            const safeMessage = esc(message || '');
            const safeLinkHref = linkHref ? escAttr(linkHref) : '';
            const safeLinkLabel = esc(linkLabel || '');
            const linkMarkup = safeLinkHref && safeLinkLabel
                ? '<a class="detail-secondary-button" href="' + safeLinkHref + '">' + safeLinkLabel + '</a>'
                : '';

            const markup =
                '<section class="detail-section">' +
                    '<h2>' + safeTitle + '</h2>' +
                    '<p class="detail-note">' + safeMessage + '</p>' +
                    linkMarkup +
                '</section>';

            const mainSlot = slots.head || visibleSlots[0];
            if (mainSlot) {
                mainSlot.innerHTML = markup;
            }
            visibleSlots.forEach((slot) => {
                if (slot !== mainSlot) {
                    slot.innerHTML = '';
                }
            });
            return true;
        }

        function mountDetailSlotsFromContent() {
            const slots = hotelDetailSlots();
            const hasSlots = Object.values(slots).some(Boolean);
            if (!hasSlots || !detailContent) {
                return;
            }
            const shell = detailContent.querySelector('.hotel-detail-shell');
            if (!shell) {
                return;
            }
            const bySelector = (selector) => shell.querySelector(selector);
            const pairs = [
                [slots.head, '.hotel-detail-head'],
                [slots.info, '#tour-info'],
                [slots.prices, '#tour-prices'],
                [slots.calendar, '#tour-calendar'],
                [slots.facilities, '#tour-facilities'],
                [slots.reviews, '#tour-reviews'],
                [slots.similarPrice, '#tour-similar-price'],
                [slots.similarBeach, '#tour-similar-beach'],
            ];
            pairs.forEach(([slot, selector]) => {
                if (!slot) return;
                const node = bySelector(selector);
                slot.innerHTML = '';
                if (node) {
                    slot.appendChild(node);
                }
            });
            detailContent.innerHTML = '';
            detailContent.style.display = 'none';
            detailContent.setAttribute('aria-hidden', 'true');
        }

        async function submitLeadFromForm(form, statusEl) {
            const status = statusEl || null;
            if (status) {
                status.textContent = 'Відправляємо заявку...';
            }
            const body = new URLSearchParams(new FormData(form));
            body.set('action', 'ittour_lab_booking');
            body.set('nonce', nonce);
            if (!body.get('message')) {
                body.set('message', 'Запит з блоку консультації на сторінці туру.');
            }
            const pageUrlField = bookingForm ? bookingForm.querySelector('input[name="page_url"]') : null;
            if (pageUrlField && pageUrlField.value) {
                body.set('page_url', pageUrlField.value);
            } else {
                body.set('page_url', window.location.href);
            }
            try {
                const response = await fetch(ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                });
                const payload = await response.json();
                if (!payload.success) {
                    throw new Error((payload.data && payload.data.message) || 'Не вдалося відправити заявку');
                }
                if (status) {
                    status.textContent = 'Заявку відправлено. Менеджер скоро звʼяжеться з вами.';
                }
                form.reset();
                const offerLeadBackdrop = form.closest('.best-offer-lead-backdrop');
                if (offerLeadBackdrop) {
                    window.setTimeout(() => {
                        offerLeadBackdrop.hidden = true;
                        if (status) {
                            status.textContent = '';
                        }
                    }, 1200);
                }
            } catch (error) {
                if (status) {
                    status.textContent = error.message || 'Не вдалося відправити заявку';
                }
            }
        }

        function initReviewsCarousel(root) {
            if (!root) {
                return;
            }
            root.querySelectorAll('.reviews-carousel').forEach((carousel) => {
                const track = carousel.querySelector('.reviews-track');
                const prev = carousel.querySelector('.reviews-nav-prev');
                const next = carousel.querySelector('.reviews-nav-next');
                if (!track || !prev || !next) {
                    return;
                }
                const step = () => Math.max(160, track.clientWidth);
                const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                prev.addEventListener('click', () => {
                    track.scrollBy({ left: -step(), behavior: reduceMotion ? 'auto' : 'smooth' });
                });
                next.addEventListener('click', () => {
                    track.scrollBy({ left: step(), behavior: reduceMotion ? 'auto' : 'smooth' });
                });
            });
        }

        function sortDepartureCities(cities) {
            const list = [...(cities || [])];
            list.sort((left, right) => {
                const groupSort = sortDepartureGroupNames(
                    departureCountryLabel(left),
                    departureCountryLabel(right),
                );
                if (groupSort !== 0) {
                    return groupSort;
                }
                return String(left.name || '').localeCompare(String(right.name || ''), 'uk');
            });
            return list;
        }

        async function getDepartureCities(countryId) {
            const cacheKey = String(countryId);
            if (departureCache.has(cacheKey)) {
                return departureCache.get(cacheKey);
            }

            try {
                const data = await api('module/params/' + countryId, { entity: 'from_city' });
                const cities = sortDepartureCities(data.from_cities || []);
                departureCache.set(cacheKey, cities);
                return cities;
            } catch (error) {
                departureCache.set(cacheKey, []);
                return [];
            }
        }

        function populateCountrySelect() {
            if (!countrySelect) {
                return;
            }
            countrySelect.innerHTML = ALL_COUNTRIES.map((country) => {
                return '<option value="' + escAttr(country.id) + '">' + esc(country.name) + '</option>';
            }).join('');
            countrySelect.value = activeCountryId;
            if (heroSearchCaption) {
                heroSearchCaption.textContent = 'У добірці доступно ' + ALL_COUNTRIES.length + ' країн. Основний сценарій — вибір країни у вкладках та картках нижче.';
            }
        }

        async function populateDepartureSelect(countryId) {
            if (!departureSelect) {
                return;
            }

            departureSelect.innerHTML = '<option value="">Підбираємо міста виїзду…</option>';
            departureSelect.disabled = true;

            const cities = await getDepartureCities(countryId);
            if (!cities.length) {
                activeDepartureId = '';
                departureSelect.innerHTML = '<option value="">Немає доступних міст виїзду</option>';
                departureSelect.disabled = true;
                syncCountryLabels();
                return;
            }

            const filtered = filterDepartureCitiesForDestination(cities, countryId);
            if (!filtered.length) {
                activeDepartureId = '';
                departureSelect.innerHTML = '<option value="">Немає доступних міст виїзду</option>';
                departureSelect.disabled = true;
                setPsFromSelectedIds([]);
                syncCountryLabels();
                return;
            }

            const currentIds = getPsFromSelectedIds().filter((cityId) =>
                filtered.some((city) => String(city.id) === String(cityId)),
            );
            if (!currentIds.length) {
                setPsFromSelectedIds([]);
            }

            departureSelect.innerHTML = '<option value="">Оберіть місто виїзду</option>' + filtered.map((city) => {
                return '<option value="' + escAttr(city.id) + '">' + esc(city.name) + '</option>';
            }).join('');
            departureSelect.value = getPsFromSelectedIds()[0] || '';
            activeDepartureId = departureSelect.value || '';
            departureSelect.disabled = false;
            syncCountryLabels();
        }

        function renderCountryPills() {
            syncCountryLabels();
            if (!countrySwitcher) {
                return;
            }
            countrySwitcher.innerHTML = FEATURED_COUNTRIES.map((country) => {
                const active = country.id === activeCountryId ? ' active' : '';
                return '<button type="button" class="country-pill' + active + '" data-country="' + escAttr(country.id) + '">' + esc(country.name) + '</button>';
            }).join('');

            countrySwitcher.querySelectorAll('.country-pill').forEach((button) => {
                button.addEventListener('click', async () => {
                    const countryId = button.getAttribute('data-country');
                    if (!countryId || countryId === activeCountryId) {
                        return;
                    }
                    await setActiveCountry(countryId, false);
                });
            });
        }

        async function setActiveCountry(countryId, shouldLoadOffers = false) {
            if (!countryId) {
                return;
            }
            activeCountryId = String(countryId);
            renderCountryPills();
            await populateDepartureSelect(activeCountryId);
            if (shouldLoadOffers) {
                await loadCountry(activeCountryId);
            }
        }

        function renderSkeletons() {
            if (!track) {
                return;
            }
            track.innerHTML = '<div class="skeleton-row">' +
                '<div class="skeleton-card"></div>'.repeat(4) +
                '</div>';
            updateNav();
        }

        function renderEmpty(message) {
            if (!track) {
                return;
            }
            track.innerHTML = '<div class="empty-state">' + esc(message) + '</div>';
            updateNav();
        }

        function renderError(message) {
            if (!track) {
                return;
            }
            track.innerHTML = '<div class="error-state">' + esc(message) + '</div>';
            updateNav();
        }

        function renderCards(payload) {
            if (windowPill) {
                const windowStart = payload && payload.window ? payload.window.date_from : '';
                const windowEnd = payload && payload.window ? (payload.window.date_till || payload.window.date_from) : '';
                windowPill.innerHTML = 'Найближче вікно: <strong>' + esc(formatHumanDate(windowStart)) + ' - ' + esc(formatHumanDate(windowEnd)) + '</strong>';
            }

            if (!track) {
                return;
            }
            track.innerHTML = payload.cards.map((card, index) => {
                const reviewBlock = card.reviewRate
                    ? '<div class="review-chip"><div class="review-copy"><strong>' + esc(reviewLabel(card.reviewRate)) + '</strong><span>' + esc((card.reviewCount || 0) + ' відгуків') + '</span></div><div class="review-score">' + esc(Number(card.reviewRate).toFixed(1)) + '</div></div>'
                    : '';
                const imageMarkup = card.image
                    ? '<img src="' + escAttr(card.image) + '" alt="' + escAttr(card.name) + '" loading="' + (index < 4 ? 'eager' : 'lazy') + '" referrerpolicy="no-referrer-when-downgrade">'
                    : '';
                const mediaClass = card.image ? 'hotel-media' : 'hotel-media no-image';
                const duration = card.duration ? card.duration + ' ночей' : 'Наявність уточнюється';
                const url = detailUrl(card);
                const transportLine = transportIncludedLabel(card) + (card.departureName ? ' • ' + card.departureName : '');
                const locationLine = [card.country, card.region].filter(Boolean).join(', ');

                return '' +
                    '<article class="hotel-card">' +
                        '<div class="' + mediaClass + '">' +
                            imageMarkup +
                            reviewBlock +
                        '</div>' +
                        '<div class="hotel-body">' +
                            '<div class="stars">' + esc(starsMarkup(card.rating)) + '</div>' +
                            '<h3 class="hotel-title">' + esc(card.name) + '</h3>' +
                            '<p class="hotel-location">' + esc(locationLine) + '</p>' +
                            '<div class="hotel-meta">' +
                                '<span>' + esc(transportLine) + '</span>' +
                                '<span>' + esc('Від ' + formatHumanDate(card.dateFrom)) + '</span>' +
                                '<span>' + esc(duration) + (card.mealType ? ' • ' + esc(card.mealType) : '') + '</span>' +
                            '</div>' +
                            '<div class="hotel-price">Пакетний тур за 2 дорослих<strong>' + esc(formatMoneyUAH(card.priceUAH)) + '</strong></div>' +
                            '<a class="card-action" href="' + escAttr(url) + '" data-key="' + escAttr(card.key) + '" data-hotel-id="' + escAttr(card.hotelId) + '">Переглянути деталі</a>' +
                        '</div>' +
                    '</article>';
            }).join('');

            track.querySelectorAll('.card-action').forEach((button) => {
                button.addEventListener('click', () => {
                    const key = button.getAttribute('data-key') || '';
                    const hotelId = button.getAttribute('data-hotel-id') || '';
                    const card = payload.cards.find((item) => item.key === key && item.hotelId === hotelId);
                    if (!card) {
                        return;
                    }
                    try {
                        sessionStorage.setItem('ittour:last-card:' + key, JSON.stringify(card));
                    } catch (error) {
                    }
                });
            });

            updateNav();
        }

        function minPrice(cards) {
            const values = (cards || [])
                .filter((card) => card && card.hasTransport === true)
                .map((card) => Number(card.priceUAH || 0))
                .filter((value) => value > 0);
            return values.length ? Math.min(...values) : null;
        }

        function summarizeRegions(cards) {
            const regionMap = new Map();

            (cards || []).forEach((card) => {
                const region = (card.region || '').trim();
                if (!region) {
                    return;
                }

                const current = regionMap.get(region) || {
                    count: 0,
                    minPrice: Infinity,
                };

                current.count += 1;

                const price = Number(card.priceUAH || 0);
                if (price > 0 && price < current.minPrice) {
                    current.minPrice = price;
                }

                regionMap.set(region, current);
            });

            return [...regionMap.entries()]
                .sort((left, right) => {
                    if (left[1].count !== right[1].count) {
                        return right[1].count - left[1].count;
                    }
                    return left[1].minPrice - right[1].minPrice;
                })
                .slice(0, 4)
                .map(([name]) => name);
        }

        function directionImage(country, payload) {
            return fixMediaUrl(country.image || '') || (((payload && payload.cards) && payload.cards[0] && payload.cards[0].image) || '');
        }

        function directionCard(country, payload) {
            const allCards = payload ? (payload.allCards || payload.cards || []) : [];
            return {
                id: country.id,
                name: country.name,
                image: directionImage(country, payload),
                price: minPrice(allCards),
                regions: summarizeRegions(allCards),
                live: allCards.length > 0,
            };
        }

        function renderDirectionsSkeletons() {
            if (!directionsGrid) {
                return;
            }
            directionsGrid.innerHTML = Array.from({ length: 6 }, () => '<div class="direction-skeleton"></div>').join('');
        }

        function renderDirections(items) {
            if (!directionsGrid) {
                return;
            }
            directionsGrid.innerHTML = items.map((item, index) => {
                const image = item.image
                    ? '<img src="' + escAttr(item.image) + '" alt="' + escAttr(item.name) + '" loading="' + (index < 3 ? 'eager' : 'lazy') + '" referrerpolicy="no-referrer-when-downgrade">'
                    : '';
                const tags = item.regions.length
                    ? item.regions.map((region) => '<span class="direction-tag">' + esc(region) + '</span>').join('')
                    : '<span class="direction-tag">Курорти уточнюються</span>';
                const price = item.price ? '<span class="direction-price">від ' + esc(formatMoneyUAH(item.price)) + '</span>' : '';
                const note = item.live
                    ? 'Ціни показуємо тільки для пакетних пропозицій з транспортом'
                    : 'Відкрийте країну, щоб підтягнути доступні курорти та ціни';

                return '' +
                    '<article class="direction-card">' +
                        '<div class="direction-media">' +
                            image +
                            '<span class="direction-badge">Актуально зараз</span>' +
                        '</div>' +
                        '<div class="direction-body">' +
                            '<div class="direction-top">' +
                                '<h3>' + esc(item.name) + '</h3>' +
                                price +
                            '</div>' +
                            '<p class="direction-copy">Курорти, які зараз найчастіше трапляються в актуальних пропозиціях для доступних міст виїзду.</p>' +
                            '<div class="direction-tags">' + tags + '</div>' +
                            '<div class="direction-footer">' +
                                '<span class="direction-note">' + esc(note) + '</span>' +
                                '<button type="button" class="direction-action" data-country="' + escAttr(item.id) + '">Показати готелі</button>' +
                            '</div>' +
                        '</div>' +
                    '</article>';
            }).join('');

            directionsGrid.querySelectorAll('.direction-action').forEach((button) => {
                button.addEventListener('click', async () => {
                    const countryId = button.getAttribute('data-country');
                    if (!countryId) {
                        return;
                    }

                    await setActiveCountry(countryId, true);
                    scrollToOffers();
                });
            });
        }

        async function loadDirections() {
            if (!directionsGrid) {
                return;
            }
            const items = FEATURED_COUNTRIES.map((country) => {
                let payload = null;
                const preferredKey = country.id + ':' + (activeDepartureId || 'auto') + ':showcase';
                if (searchCache.has(preferredKey)) {
                    payload = searchCache.get(preferredKey) || null;
                } else {
                    for (const [key, value] of searchCache.entries()) {
                        if (key.indexOf(country.id + ':') === 0 && value) {
                            payload = value;
                            break;
                        }
                    }
                }
                return directionCard(country, payload);
            });
            renderDirections(items);
        }

        async function findCountryPayload(countryId) {
            const departureCities = filterDepartureCitiesForDestination(await getDepartureCities(countryId), countryId);
            const selectedIds = getPsFromSelectedIds().filter((cityId) =>
                departureCities.some((city) => String(city.id) === String(cityId)),
            );
            const fallbackIds = departureCities.slice(0, DEPARTURE_CANDIDATE_LIMIT).map((city) => String(city.id || ''));
            const candidateIds = Array.from(new Set((selectedIds.length ? selectedIds : fallbackIds).filter(Boolean)));
            if (!candidateIds.length) {
                candidateIds.push('');
            }

            let lastError = null;
            for (const departureId of candidateIds) {
                const departure = departureCities.find((city) => String(city.id) === String(departureId));
                const cacheKey = countryId + ':' + (departureId || 'auto') + ':showcase';
                if (searchCache.has(cacheKey)) {
                    const cached = searchCache.get(cacheKey);
                    if (cached) {
                        return cached;
                    }
                    continue;
                }

                const queries = [
                    {
                        showcase_number: '1',
                        country: String(countryId),
                        hotel_rating: '3:78',
                        night_from: '3',
                        night_till: '14',
                        page: '1',
                        items_per_page: '36',
                        hotel_image: '1',
                        ...(departureId ? { from_city: departureId } : {}),
                    },
                    {
                        showcase_number: '1',
                        country: String(countryId),
                        hotel_rating: '3:78:79',
                        night_from: '1',
                        night_till: '21',
                        page: '1',
                        items_per_page: '36',
                        hotel_image: '1',
                        ...(departureId ? { from_city: departureId } : {}),
                    },
                ];
                if (departureId) {
                    queries.push({
                        showcase_number: '1',
                        country: String(countryId),
                        hotel_rating: '3:78',
                        night_from: '3',
                        night_till: '14',
                        page: '1',
                        items_per_page: '36',
                        hotel_image: '1',
                    });
                }

                for (const query of queries) {
                    try {
                        const data = await api('showcase/hot-offers/search', query);
                        const uniqueOffers = sortHotels(dedupeHotels(data.offers || [])).filter((offer) => offerHasTransport(offer));
                        if (!uniqueOffers.length) {
                            continue;
                        }

                        const window = {
                            date_from: String(uniqueOffers[0].date_from || ''),
                            date_till: String(uniqueOffers[0].date_till || uniqueOffers[0].date_from || ''),
                        };
                        const allCards = uniqueOffers.slice(0, 36).map((offer) => cardFromOffer(offer, window));
                        const payload = {
                            window,
                            departureId: departureId || '',
                            departureName: (departure && departure.name) || (allCards[0] && allCards[0].departureName) || '',
                            cards: allCards.slice(0, OFFERS_VISIBLE_LIMIT),
                            allCards,
                        };
                        searchCache.set(cacheKey, payload);
                        return payload;
                    } catch (error) {
                        if (error && (error.code === 107 || error.code === 108 || error.code === 109)) {
                            throw error;
                        }
                        lastError = error;
                    }
                }

                searchCache.set(cacheKey, null);
            }

            if (lastError) {
                throw lastError;
            }
            return null;
        }

        async function loadCountry(countryId) {
            renderSkeletons();
            if (windowPill) {
                windowPill.textContent = 'Підбираємо найближче вікно вильоту…';
            }

            try {
                const payload = await findCountryPayload(countryId);
                if (!payload || !payload.cards.length) {
                    renderEmpty('Зараз не вдалося знайти пропозиції для цієї країни у доступних містах виїзду. Спробуйте інший напрямок.');
                    if (windowPill) {
                        windowPill.textContent = 'Пропозиції не знайдено';
                    }
                    loadDirections();
                    return;
                }
                activeDepartureId = payload.departureId || activeDepartureId;
                if (departureSelect && activeDepartureId) {
                    departureSelect.value = activeDepartureId;
                }
                syncCountryLabels();
                renderCards(payload);
                loadDirections();
            } catch (error) {
                if (error && (error.code === 107 || error.code === 108 || error.code === 109)) {
                    renderEmpty(error.code === 109
                        ? 'Перевищено денний ліміт вітрини. Спробуйте завтра.'
                        : 'Вітрина тимчасово недоступна через ліміт API. Спробуйте трохи пізніше.');
                    if (windowPill) {
                        windowPill.textContent = 'Ліміт API';
                    }
                    return;
                }
                const message = String(error.message || error || '');
                if (/Превышен лимит запросов|Hour limit reached/i.test(message)) {
                    renderEmpty('Сервіс пошуку тимчасово обмежив кількість запитів. Спробуйте ще раз трохи пізніше.');
                    if (windowPill) {
                        windowPill.textContent = 'Пошук тимчасово обмежений';
                    }
                    return;
                }
                renderError(message);
                if (windowPill) {
                    windowPill.textContent = 'Не вдалося завантажити дані';
                }
            }
        }

        function updateNav() {
            if (!track || !navPrev || !navNext) {
                return;
            }
            const maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 2);
            const current = track.scrollLeft;
            const canScroll = track.scrollWidth > track.clientWidth + 10;

            navPrev.disabled = !canScroll || current <= 8;
            navNext.disabled = !canScroll || current >= maxScroll;
        }

        function scrollTrack(direction) {
            if (!track) {
                return;
            }
            track.scrollBy({
                left: direction * Math.max(track.clientWidth * 0.92, 320),
                behavior: 'smooth',
            });
        }

        function firstValue(source, keys) {
            for (const key of keys) {
                if (source && source[key] != null && String(source[key]).trim() !== '') {
                    return String(source[key]).trim();
                }
            }
            return '';
        }

        function compactTime(value) {
            const raw = String(value || '').trim();
            if (!raw) {
                return '';
            }
            const match = raw.match(/(\d{1,2})[:.](\d{2})/);
            if (!match) {
                return raw;
            }
            return match[1].padStart(2, '0') + ':' + match[2];
        }

        function normalizeFlightItem(item, label) {
            const flightNo = firstValue(item, ['flight_number', 'flight_no', 'number', 'num', 'code', 'name']);
            const airline = firstValue(item, ['airline', 'airline_name', 'company', 'company_name', 'carrier']);
            const departTime = compactTime(firstValue(item, ['dep_time', 'departure_time', 'time_from', 'time_start', 'time', 'from_time']));
            const arriveTime = compactTime(firstValue(item, ['arr_time', 'arrival_time', 'time_to', 'time_end', 'to_time']));
            const departDate = firstValue(item, ['dep_date', 'departure_date', 'date_from', 'date_start', 'date']);
            const arriveDate = firstValue(item, ['arr_date', 'arrival_date', 'date_to', 'date_end']);
            const from = firstValue(item, ['from', 'airport_from', 'from_airport', 'city_from', 'departure_airport']);
            const to = firstValue(item, ['to', 'airport_to', 'to_airport', 'city_to', 'arrival_airport']);
            const flightClass = firstValue(item, ['class', 'flight_class', 'tariff', 'tariff_name']);

            return {
                label,
                flightNo: flightNo || 'Рейс',
                airline,
                departTime,
                arriveTime,
                departDate,
                arriveDate,
                from,
                to,
                flightClass,
            };
        }

        function flightItems(flights, info) {
            const entries = [];
            const source = flights && typeof flights === 'object' ? flights : {};
            const fallback = info && info.flights && typeof info.flights === 'object' ? info.flights : {};

            [['to', 'Туди'], ['from', 'Назад']].forEach(([key, label]) => {
                const list = Array.isArray(source[key])
                    ? source[key]
                    : (Array.isArray(fallback[key]) ? fallback[key] : []);
                list.forEach((item) => {
                    entries.push(normalizeFlightItem(item, label));
                });
            });

            return entries;
        }

        function renderReviews(reviews) {
            if (!Array.isArray(reviews) || !reviews.length) {
                return '<p class="detail-note">Відгуки для цього готелю зараз не повернулися окремим методом.</p>';
            }

            const slides = reviews.slice(0, 12).map((review) => {
                const title = decodeHtml(review.title || review.service_name || 'Відгук');
                const text = decodeHtml(review.text_full || review.text || '').replace(/\s+/g, ' ').trim();
                const meta = [
                    review.service_name || '',
                    review.published_date ? formatHumanDate(review.published_date.slice(0, 10)) : '',
                    review.user_name || '',
                ].filter(Boolean).join(' • ');

                return '' +
                    '<article class="review-slide">' +
                        '<h4 class="review-slide-title">' + esc(title) + '</h4>' +
                        '<p>' + esc(text) + '</p>' +
                        '<div class="review-meta">' + esc(meta) + '</div>' +
                    '</article>';
            }).join('');

            return '' +
                '<div class="reviews-carousel" aria-roledescription="carousel">' +
                '<button type="button" class="reviews-nav reviews-nav-prev" aria-label="Попередні відгуки">' +
                '<span aria-hidden="true">‹</span></button>' +
                '<div class="reviews-track">' + slides + '</div>' +
                '<button type="button" class="reviews-nav reviews-nav-next" aria-label="Наступні відгуки">' +
                '<span aria-hidden="true">›</span></button>' +
                '</div>';
        }

        function savedDetailCard(key) {
            if (!key) {
                return null;
            }
            try {
                const raw = sessionStorage.getItem('ittour:last-card:' + key);
                return raw ? JSON.parse(raw) : null;
            } catch (error) {
                return null;
            }
        }

        function parseTourDate(value) {
            const raw = String(value || '').trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                const parts = raw.split('-').map(Number);
                return new Date(parts[0], parts[1] - 1, parts[2], 12, 0, 0, 0);
            }
            if (/^\d{2}\.\d{2}\.\d{2,4}$/.test(raw)) {
                const parts = raw.split('.');
                const year = Number(parts[2].length === 2 ? '20' + parts[2] : parts[2]);
                return new Date(year, Number(parts[1]) - 1, Number(parts[0]), 12, 0, 0, 0);
            }
            return null;
        }

        function addDays(date, amount) {
            const next = new Date(date.getTime());
            next.setDate(next.getDate() + amount);
            return next;
        }

        function shortApiDate(date) {
            return String(date.getDate()).padStart(2, '0') + '.' + String(date.getMonth() + 1).padStart(2, '0') + '.' + String(date.getFullYear()).slice(-2);
        }

        function shortCalendarDate(date) {
            return String(date.getDate()).padStart(2, '0') + '.' + String(date.getMonth() + 1).padStart(2, '0');
        }

        function detailDateLabel(value) {
            const date = value instanceof Date ? value : parseTourDate(value);
            return date ? new Intl.DateTimeFormat('uk-UA', { day: 'numeric', month: 'long' }).format(date) : formatHumanDate(value);
        }

        function detailPriceValue(offer) {
            const value = offer && offer.prices && offer.prices['2'] != null ? offer.prices['2'] : (offer && offer.price != null ? offer.price : null);
            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed : null;
        }

        function detailOfferQuery(info, start, end, nightFrom, nightTill, limit, hotelOnly) {
            const query = {
                type: '1',
                kind: '1',
                country: String(info.country_id || ''),
                adult_amount: String(info.adult_amount || 2),
                child_amount: String(info.child_amount || 0),
                hotel_rating: '3:78',
                night_from: String(nightFrom || 5),
                night_till: String(nightTill || 9),
                date_from: shortApiDate(start),
                date_till: shortApiDate(end),
                items_per_page: String(limit || 20),
                hotel_info: '1',
                currency: '2',
            };
            if (info.from_city_id) {
                query.from_city = String(info.from_city_id);
            }
            if (hotelOnly && info.hotel_id) {
                query.hotel = String(info.hotel_id);
            }
            return query;
        }

        async function detailLoadOffers(info) {
            const start = parseTourDate(info.date_from) || addDays(new Date(), 14);
            const end = addDays(start, 7);
            const nights = Number(info.duration || info.hnight || 7) || 7;
            const query = detailOfferQuery(info, start, end, Math.max(1, nights - 2), nights + 2, 40, true);
            const data = await api('module/search-list', query).catch(() => ({ offers: [] }));
            return (Array.isArray(data.offers) ? data.offers : []).filter((offer) => String(offer.hotel_id || '') === String(info.hotel_id || ''));
        }

        async function detailLoadSimilar(info) {
            const start = parseTourDate(info.date_from) || addDays(new Date(), 14);
            const end = addDays(start, 10);
            const nights = Number(info.duration || info.hnight || 7) || 7;
            const query = detailOfferQuery(info, start, end, Math.max(1, nights - 2), nights + 2, 24, false);
            const data = await api('module/search-list', query).catch(() => ({ offers: [] }));
            const hotelId = String(info.hotel_id || '');
            const seen = new Set();
            return (Array.isArray(data.offers) ? data.offers : []).filter((offer) => {
                const id = String(offer.hotel_id || offer.hotel || '');
                if (!id || id === hotelId || seen.has(id)) {
                    return false;
                }
                seen.add(id);
                return true;
            }).slice(0, 8);
        }

        function detailImages(info, offer, card) {
            const hotelInfo = info.hotel_info || {};
            const source = []
                .concat(Array.isArray(hotelInfo.images) ? hotelInfo.images : [])
                .concat(Array.isArray(offer && offer.hotel_images) ? offer.hotel_images : []);
            const seen = new Set();
            const images = [];
            const cptGallery = Array.isArray(window.anexCptHotelGallery) ? window.anexCptHotelGallery : [];
            cptGallery.forEach((raw) => {
                const url = fixMediaUrl(raw || '');
                if (url && !seen.has(url)) {
                    seen.add(url);
                    images.push(url);
                }
            });
            source.forEach((image) => {
                const url = fixMediaUrl(image.full || image.web || image.thumb || '');
                if (url && !seen.has(url)) {
                    seen.add(url);
                    images.push(url);
                }
            });
            if (!images.length && card.image) {
                images.push(fixMediaUrl(card.image));
            }
            return images;
        }

        function detailGallery(images, title) {
            if (!images.length) {
                return '<div class="hotel-gallery-mosaic"><div class="hotel-gallery-item hotel-gallery-main"></div></div>';
            }
            const visible = images.slice(0, 7);
            const posClass = [
                'hotel-gallery-left-top',
                'hotel-gallery-main',
                'hotel-gallery-left-bottom',
                'hotel-gallery-bottom-1',
                'hotel-gallery-bottom-2',
                'hotel-gallery-bottom-3',
                'hotel-gallery-bottom-4',
            ];
            return '<div class="hotel-gallery-mosaic">' + visible.map((url, index) => {
                const cls = posClass[index] ? (' ' + posClass[index]) : '';
                const more = index === 6 && images.length > 7 ? '<span class="hotel-gallery-more">+' + (images.length - 7) + ' фото</span>' : '';
                return '<a class="hotel-gallery-item' + cls + '" href="' + escAttr(url) + '" target="_blank" rel="noopener"><img src="' + escAttr(url) + '" alt="' + escAttr(title) + '" loading="' + (index < 2 ? 'eager' : 'lazy') + '" referrerpolicy="no-referrer-when-downgrade">' + more + '</a>';
            }).join('') + '</div>';
        }

        function detailOfferCity(offer, fallback) {
            const direct = firstValue(offer, ['from_city', 'from_city_name']) || firstValue(fallback || {}, ['from_city', 'from_city_name']);
            if (direct) {
                return direct;
            }
            if (activeDepartureId) {
                const cities = departureCache.get(activeCountryId) || [];
                const city = cities.find((item) => String(item.id) === String(activeDepartureId));
                if (city && city.name) {
                    return city.name;
                }
            }
            return 'Місто виїзду уточнить менеджер';
        }

        function detailDuration(offer) {
            const value = firstValue(offer, ['duration', 'hnight']);
            return value ? value + ' ночей' : 'Уточнюється';
        }

        function detailMeal(offer) {
            return firstValue(offer, ['meal_type_full', 'meal_full', 'meal_type']) || 'Уточнюється';
        }

        function detailRoom(offer) {
            return firstValue(offer, ['room_type', 'room', 'room_name']) || 'STANDARD ROOM';
        }

        function detailOperator(offer, fallback) {
            return firstValue(offer, ['operator', 'operator_name', 'tour_operator', 'tour_operator_name']) || firstValue(fallback || {}, ['spo']).split(/[.-]/)[0] || 'Туроператор';
        }

        function detailVisibleOffers(offers) {
            const packaged = transportOnlyOffers(offers || []);
            if (packaged.length) {
                return packaged;
            }
            return stayOnlyOffers(offers || []).slice(0, 2).map((offer) => Object.assign({}, offer, { __stayOnly: true }));
        }

        function detailOfferIsStayOnly(offer) {
            return Boolean(offer && offer.__stayOnly) || !offerHasTransport(offer);
        }

        function detailBestOffer(info, offer, title) {
            const current = offer || info;
            const tourKeyAttr = escAttr(info.key || DETAIL_TOUR_KEY);
            const tourTitleAttr = escAttr(title);
            const tgHref = escAttr(ANEX_AGENCY_TELEGRAM || 'https://t.me/');
            const vbHref = escAttr(ANEX_AGENCY_VIBER || 'viber://chat?number=%2B380979451781');
            const consultFormInner =
                '<input type="hidden" name="tour_key" value="' + tourKeyAttr + '">' +
                '<input type="hidden" name="tour_title" value="' + tourTitleAttr + '">' +
                '<input type="hidden" name="email" value="">' +
                '<input type="hidden" name="message" value="Запит з картки «Найкраща пропозиція» (сторінка туру).">' +
                '<input type="text" name="name" autocomplete="name" required placeholder="Ваше імʼя" aria-label="Ваше імʼя">' +
                '<input type="tel" name="phone" autocomplete="tel" required placeholder="+380 (XX) XXX-XX-XX" aria-label="Телефон">' +
                '<button type="submit" class="best-offer-lead-submit">Надіслати</button>' +
                '<p class="advisor-lead-status" role="status" aria-live="polite"></p>';
            const consultBlock =
                '<div class="best-offer-consult">' +
                    '<button type="button" class="best-offer-lead-open">Звʼязатись</button>' +
                    '<div class="best-offer-lead-backdrop" hidden>' +
                        '<div class="best-offer-lead-dialog" role="dialog" aria-modal="true" aria-labelledby="best-offer-lead-sr-title">' +
                            '<h2 id="best-offer-lead-sr-title" class="best-offer-lead-sr-only">Швидкий запит</h2>' +
                            '<button type="button" class="best-offer-lead-close" aria-label="Закрити">×</button>' +
                            '<form class="advisor-lead-form best-offer-lead-form best-offer-lead-form--modal" novalidate>' + consultFormInner + '</form>' +
                        '</div>' +
                    '</div>' +
                    '<p class="best-offer-messenger-caption">Звʼязок з нами в месенджерах</p>' +
                    '<div class="best-offer-messengers">' +
                        '<a class="best-offer-msg best-offer-msg--tg" href="' + tgHref + '" target="_blank" rel="noopener noreferrer" aria-label="Написати в Telegram">' +
                            '<svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>' +
                        '</a>' +
                        '<a class="best-offer-msg best-offer-msg--vb" href="' + vbHref + '" rel="noopener" aria-label="Написати у Viber">' +
                            '<svg viewBox="0 0 24 24" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M11.4 0C9.473.028 5.333.344 3.02 2.467 1.302 4.187.696 6.7.633 9.817.57 12.933.488 18.776 6.12 20.36h.003l-.004 2.416s-.037.977.61 1.177c.777.242 1.234-.5 1.98-1.302.407-.44.972-1.084 1.397-1.58 3.85.326 6.812-.416 7.15-.525.776-.252 5.176-.816 5.892-6.657.74-6.02-.36-9.83-2.34-11.546-.596-.55-3.006-2.3-8.375-2.323 0 0-.395-.025-1.037-.017zm.058 1.693c.545-.004.88.017.88.017 4.542.02 6.717 1.388 7.222 1.846 1.675 1.435 2.53 4.868 1.906 9.897v.002c-.604 4.878-4.174 5.184-4.832 5.395-.28.09-2.882.737-6.153.524 0 0-2.436 2.94-3.197 3.704-.12.12-.26.167-.352.144-.13-.033-.166-.188-.165-.414l.02-4.018c-4.762-1.32-4.485-6.292-4.43-8.895.054-2.604.543-4.738 1.996-6.173 1.96-1.773 5.474-2.018 7.11-2.03zm.38 2.602c-.167 0-.303.135-.304.302 0 .167.133.303.3.305 1.624.01 2.946.537 4.028 1.592 1.073 1.046 1.62 2.468 1.633 4.334.002.167.14.3.307.3.166-.002.3-.138.3-.304-.014-1.984-.618-3.596-1.816-4.764-1.19-1.16-2.692-1.753-4.447-1.765zm-3.96.695c-.19-.032-.4.005-.616.117l-.01.002c-.43.247-.816.562-1.146.932-.002.004-.006.004-.008.008-.267.323-.42.638-.46.948-.008.046-.01.093-.007.14 0 .136.022.27.065.4l.013.01c.135.48.473 1.276 1.205 2.604.42.768.903 1.5 1.446 2.186.27.344.56.673.87.984l.132.132c.31.308.64.6.984.87.686.543 1.418 1.027 2.186 1.447 1.328.733 2.126 1.07 2.604 1.206l.01.014c.13.042.265.064.402.063.046.002.092 0 .138-.008.31-.036.627-.19.948-.46.004 0 .003-.002.008-.005.37-.33.683-.72.93-1.148l.003-.01c.225-.432.15-.842-.18-1.12-.004 0-.698-.58-1.037-.83-.36-.255-.73-.492-1.113-.71-.51-.285-1.032-.106-1.248.174l-.447.564c-.23.283-.657.246-.657.246-3.12-.796-3.955-3.955-3.955-3.955s-.037-.426.248-.656l.563-.448c.277-.215.456-.737.17-1.248-.217-.383-.454-.756-.71-1.115-.25-.34-.826-1.033-.83-1.035-.137-.165-.31-.265-.502-.297zm4.49.88c-.158.002-.29.124-.3.282-.01.167.115.312.282.324 1.16.085 2.017.466 2.645 1.15.63.688.93 1.524.906 2.57-.002.168.13.306.3.31.166.003.305-.13.31-.297.025-1.175-.334-2.193-1.067-2.994-.74-.81-1.777-1.253-3.05-1.346h-.024zm.463 1.63c-.16.002-.29.127-.3.287-.008.167.12.31.288.32.523.028.875.175 1.113.422.24.245.388.62.416 1.164.01.167.15.295.318.287.167-.008.295-.15.287-.317-.03-.644-.215-1.178-.58-1.557-.367-.378-.893-.574-1.52-.607h-.018z"/></svg>' +
                        '</a>' +
                    '</div>' +
                '</div>';
            return '<aside class="best-offer-card" id="best-offer"><h2>Найкраща пропозиція</h2><div class="best-offer-grid">' +
                '<div class="best-offer-fact"><span class="best-offer-fact-label"><svg class="best-offer-fact-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2a1 1 0 0 1 1 1v1h8V3a1 1 0 1 1 2 0v1h1a3 3 0 0 1 3 3v2H2V7a3 3 0 0 1 3-3h1V3a1 1 0 0 1 1-1Zm15 9v8a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3v-8h20Zm-6 3h-2v2h2v-2Z"/></svg>Дата</span><strong>' + esc(detailDateLabel(current.date_from || info.date_from)) + '</strong></div>' +
                '<div class="best-offer-fact"><span class="best-offer-fact-label"><svg class="best-offer-fact-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2.6 13.2 10 15l4.8 6.2c.3.4.9.2.9-.3l-.4-5.1 4.8 1.2c.9.2 1.8-.3 2-1.2.2-.9-.4-1.8-1.3-2L15.9 12l4.9-1.8c.9-.3 1.4-1.2 1.1-2.1-.3-.9-1.2-1.4-2.1-1.1l-4.8 1.8.4-5.1c0-.5-.6-.7-.9-.3L10 9.6 2.6 11.4c-.5.1-.8.5-.8.9s.3.8.8.9Z"/></svg>Виїзд</span><strong>' + esc(detailOfferCity(current, info)) + '</strong></div>' +
                '<div class="best-offer-fact"><span class="best-offer-fact-label"><svg class="best-offer-fact-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 1 0 20 10 10 0 0 1 0-20Zm1 5a1 1 0 1 0-2 0v6c0 .3.1.5.3.7l3.6 3.6a1 1 0 0 0 1.4-1.4L13 12.6V7Z"/></svg>Тривалість</span><strong>' + esc(detailDuration(current)) + '</strong></div>' +
                '<div class="best-offer-fact"><span class="best-offer-fact-label"><svg class="best-offer-fact-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 2a1 1 0 0 1 .9.6l1.5 3.4h5.2l1.5-3.4a1 1 0 1 1 1.8.8l-1.2 2.6h.3A3 3 0 0 1 20 9v8a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3h.3L6.1 3.4A1 1 0 0 1 7 2Zm1 9a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>Харчування</span><strong>' + esc(detailMeal(current)) + '</strong></div>' +
                '</div><p class="best-offer-included">' + esc(detailOfferIsStayOnly(current) ? 'У вартості показано лише проживання. Переліт та інші послуги менеджер підтвердить окремо.' : 'У вартість включено: переліт/транспорт, трансфер, страховка та проживання у готелі.') + '</p>' +
                '<div class="best-offer-price"><strong>' + esc(formatMoneyUAH(detailPriceValue(current))) + '</strong><span>За ' + esc(info.adult_amount || 2) + ' дор.</span></div>' +
                '<button type="button" class="detail-buy-button detail-scroll-to-prices best-offer-prices-btn">Дивитись всі ціни</button>' +
                consultBlock +
                '</aside>';
        }

        function detailInfoSection(info, title) {
            const hotelInfo = info.hotel_info || {};
            const text = [hotelInfo.description, hotelInfo.disposition, hotelInfo.featureshotel].map(stripHtml).filter(Boolean).join('\n\n') || stripHtml(info.comment || 'Опис готелю зараз уточнюється.');
            const paragraphs = text.split(/\n{2,}/).slice(0, 3).map((item) => '<p>' + esc(item) + '</p>').join('');
            return '<section class="detail-section" id="tour-info"><h2>Інформація про готель ' + esc(title) + '</h2><div class="hotel-info-copy">' + paragraphs + '</div><button type="button" class="detail-secondary-button booking-open" data-tour-key="' + escAttr(info.key || DETAIL_TOUR_KEY) + '" data-tour-title="' + escAttr(title) + '">Детальніше</button></section>';
        }

        function detailPriceTable(info, offers, title) {
            const rows = detailVisibleOffers(offers.length ? offers : [info]).slice(0, 5);
            const start = parseTourDate(info.date_from) || new Date();
            const end = addDays(start, Number(info.duration || info.hnight || 7) || 7);
            return '<section class="detail-section" id="tour-prices"><h2>Ціни на тури в готель ' + esc(title) + ', ' + esc(info.hotel_rating_kn || ((info.hotel_rating || '') + '*')) + '</h2>' +
                '<div class="price-filter-row"><div class="price-filter-cell"><span>Звідки</span><strong>' + esc(detailOfferCity(info, info)) + '</strong></div><div class="price-filter-cell"><span>Початок туру</span><strong>' + esc(detailDateLabel(start) + ' - ' + detailDateLabel(end)) + '</strong></div><div class="price-filter-cell"><span>Тривалість</span><strong>' + esc((info.duration || info.hnight || 7) + ' ночей') + '</strong></div><div class="price-filter-cell"><span>Туристи</span><strong>' + esc((info.adult_amount || 2) + ' дорослих') + '</strong></div><button type="button" class="detail-buy-button booking-open" data-tour-key="' + escAttr(info.key || DETAIL_TOUR_KEY) + '" data-tour-title="' + escAttr(title) + '">Шукати</button></div>' +
                '<div class="tour-price-table-wrap"><table class="tour-price-table"><thead><tr><th>Дата вильоту</th><th>Тривалість</th><th>Номер</th><th>Харчування</th><th>Вартість</th><th></th></tr></thead><tbody>' +
                rows.map((offer) => {
                    const offerDate = detailDateLabel(offer.date_from || info.date_from);
                    const offerCity = detailOfferCity(offer, info);
                    const offerNights = detailDuration(offer);
                    const offerRoom = detailRoom(offer);
                    const offerMeal = offer.meal_type || '';
                    const offerMealFull = detailMeal(offer);
                    const offerPrice = formatMoneyUAH(detailPriceValue(offer));
                    const offerPriceCaption = detailOfferIsStayOnly(offer) ? 'Лише проживання' : 'Пакетний тур';
                    const offerOperator = detailOperator(offer, info);
                    return '<tr>' +
                        '<td data-label="Дата вильоту">' + esc(offerDate) + '<small>з ' + esc(offerCity) + '</small></td>' +
                        '<td data-label="Тривалість">' + esc(offerNights) + '</td>' +
                        '<td data-label="Номер">' + esc(offerRoom) + '</td>' +
                        '<td data-label="Харчування">' + esc(offerMeal) + '<small>' + esc(offerMealFull) + '</small></td>' +
                        '<td data-label="Вартість">' + esc(offerPrice) + '<small>' + esc(offerPriceCaption) + '</small></td>' +
                        '<td><button type="button" class="table-buy-button booking-open"' +
                            ' data-tour-key="' + escAttr(offer.key || info.key || DETAIL_TOUR_KEY) + '"' +
                            ' data-tour-title="' + escAttr(title) + '"' +
                            ' data-tour-date="' + escAttr(offerDate) + '"' +
                            ' data-tour-city="' + escAttr(offerCity) + '"' +
                            ' data-tour-nights="' + escAttr(offerNights) + '"' +
                            ' data-tour-room="' + escAttr(offerRoom) + '"' +
                            ' data-tour-meal="' + escAttr(offerMeal + (offerMealFull ? ' — ' + offerMealFull : '')) + '"' +
                            ' data-tour-price="' + escAttr(offerPrice) + '"' +
                            ' data-tour-operator="' + escAttr(offerOperator) + '"' +
                            '>Купити онлайн</button></td>' +
                        '</tr>';
                }).join('') +
                '</tbody></table></div>' + (offers.length > 5 ? '<button type="button" class="detail-secondary-button booking-open" data-tour-key="' + escAttr(info.key || DETAIL_TOUR_KEY) + '" data-tour-title="' + escAttr(title) + '">Показати ще ' + (offers.length - 5) + ' пропозиції</button>' : '') + '</section>';
        }

        function detailCalendar(info, offers, title) {
            const base = parseTourDate(info.date_from) || new Date();
            const dates = Array.from({ length: 7 }, (_, index) => addDays(base, index));
            const nights = [5, 6, 7, 8, 9];
            const prices = new Map();
            const allPrices = [];
            (offers.length ? offers : [info]).forEach((offer) => {
                const date = parseTourDate(offer.date_from);
                const night = Number(offer.duration || offer.hnight || 0);
                const price = detailPriceValue(offer);
                if (!date || !night || price == null) {
                    return;
                }
                const key = shortCalendarDate(date) + ':' + night;
                if (!prices.has(key) || price < prices.get(key).price) {
                    prices.set(key, { price, tourKey: offer.key || info.key });
                }
                allPrices.push(price);
            });
            const min = allPrices.length ? Math.min.apply(null, allPrices) : null;
            return '<section class="detail-section" id="tour-calendar"><h2>Календар низьких цін в готель ' + esc(title) + ', ' + esc(info.hotel_rating_kn || ((info.hotel_rating || '') + '*')) + '</h2><div class="low-price-calendar-wrap"><table class="low-price-calendar"><thead><tr><th class="calendar-side">Дата</th>' + dates.map((date) => '<th>' + esc(shortCalendarDate(date)) + '</th>').join('') + '</tr></thead><tbody>' +
                nights.map((night) => '<tr><th class="calendar-side">' + night + ' ночей</th>' + dates.map((date) => {
                    const found = prices.get(shortCalendarDate(date) + ':' + night);
                    const cls = (found && found.price === min ? ' calendar-price-low' : '') + (found && String(found.tourKey || '') === String(info.key || DETAIL_TOUR_KEY) ? ' calendar-price-active' : '');
                    return '<td class="' + cls + '">' + (found ? esc(formatMoneyUAH(found.price)) : '-') + '</td>';
                }).join('') + '</tr>').join('') + '</tbody></table></div></section>';
        }

        function detailFacilities(info) {
            const list = Array.isArray(info.hotel_info && info.hotel_info.hotel_facilities) ? info.hotel_info.hotel_facilities : [];
            if (!list.length) {
                return '';
            }
            const iconByText = (value) => {
                const text = normalizeSearchToken(value || '');
                if (/пляж|море|риф|басейн|аквапарк/.test(text)) {
                    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 17c2 0 2-1 4-1s2 1 4 1 2-1 4-1 2 1 4 1 2-1 4-1v2c-2 0-2 1-4 1s-2-1-4-1-2 1-4 1-2-1-4-1-2 1-4 1-2-1-4-1v-2Zm2-7 3.2-2.4a2 2 0 0 1 2.4 0L14 10l3.4-2.5a2 2 0 0 1 2.4 0L23 10v2l-3.2-2.4a2 2 0 0 0-2.4 0L14 12l-3.4-2.5a2 2 0 0 0-2.4 0L5 12v-2Z"/></svg>';
                }
                if (/дит|дитяч/.test(text)) {
                    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6Zm-7 9a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1h-2v-1a3 3 0 0 0-3-3h-4a3 3 0 0 0-3 3v1H5Zm2 2h10a2 2 0 0 1 2 2v6h-2v-6H7v6H5v-6a2 2 0 0 1 2-2Z"/></svg>';
                }
                if (/спорт|трен|зал|фітнес|анімац/.test(text)) {
                    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 10h3V8h2v8H6v-2H3v-4Zm18 0v4h-3v2h-2V8h2v2h3Zm-10-1h2v6h-2V9Z"/></svg>';
                }
                if (/номер|tv|телефон|wi-fi|wifi|інтернет|кондиц|балкон|тераса|ванна|душ|міні-бар/.test(text)) {
                    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v9h-2v-2H4v2H2V7a2 2 0 0 1 2-2Zm0 7h16V7H4v5Zm6 7h4v2h-4v-2Z"/></svg>';
                }
                if (/готел|ресторан|кафе|бар|паркінг|трансфер|сейф|пральн|лікар/.test(text)) {
                    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3 21V3h8v6h4V3h6v18h-8v-6H9v6H3Zm2-2h2v-6h8v6h4V5h-2v6H9V5H5v14Z"/></svg>';
                }
                return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2 2 7l10 5 10-5-10-5Zm0 8L2 5v2l10 5 10-5V5l-10 5Zm0 4L2 9v2l10 5 10-5V9l-10 5Z"/></svg>';
            };
            const grouped = list.reduce((carry, item) => {
                const category = item.category || 'В готелі';
                carry[category] = carry[category] || [];
                carry[category].push(item.name);
                return carry;
            }, {});
            return '<section class="detail-section detail-section--facilities" id="tour-facilities">' +
                '<h2>Послуги та зручності ' + esc(info.hotel || 'готелю') + '</h2>' +
                '<p class="facility-section-subtitle">Групи зручностей за даними готелю.</p>' +
                '<div class="facility-shell">' +
                '<div class="facility-groups-grid">' +
                Object.keys(grouped).slice(0, 8).map((category) =>
                    '<article class="facility-group-card"><h3>' + iconByText(category) + esc(category) + '</h3>' +
                    '<ul class="facility-list">' +
                    grouped[category].slice(0, 12).map((name) => '<li>' + esc(name) + '</li>').join('') +
                    '</ul></article>',
                ).join('') +
                '</div></div></section>';
        }

        function detailFlightsMarkup(flights, info) {
            const items = flightItems(flights, info);
            if (!items.length) {
                return '<p class="detail-note">Оператор не передав години рейсів для цього оффера. Менеджер уточнить розклад перед бронюванням.</p>';
            }
            return '<div class="flight-list">' + items.map((item) => {
                const departDate = item.departDate ? formatHumanDate(item.departDate) : '';
                const arriveDate = item.arriveDate ? formatHumanDate(item.arriveDate) : '';
                const airline = item.airline || item.flightNo || 'Авіакомпанія уточнюється';
                return '<div class="flight-card"><strong>' + esc(item.label) + '</strong><div class="flight-route-line"><div class="flight-airline"><span class="flight-plane"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.6 13.2 10 15l4.8 6.2c.3.4.9.2.9-.3l-.4-5.1 4.8 1.2c.9.2 1.8-.3 2-1.2.2-.9-.4-1.8-1.3-2L15.9 12l4.9-1.8c.9-.3 1.4-1.2 1.1-2.1-.3-.9-1.2-1.4-2.1-1.1l-4.8 1.8.4-5.1c0-.5-.6-.7-.9-.3L10 9.6 2.6 11.4c-.5.1-.8.5-.8.9s.3.8.8.9Z"/></svg></span><div><small>' + esc(item.flightNo || '') + '</small><strong>' + esc(airline) + '</strong></div></div><div class="flight-point"><small>' + esc(departDate || 'Дата уточнюється') + '</small><b>' + esc(item.departTime || '-') + '</b><span>' + esc(item.from || 'Аеропорт вильоту') + '</span></div><div class="flight-middle"><span>' + esc(item.flightClass || 'Економ') + '</span><span>→</span></div><div class="flight-point"><small>' + esc(arriveDate || departDate || 'Дата уточнюється') + '</small><b>' + esc(item.arriveTime || '-') + '</b><span>' + esc(item.to || 'Аеропорт прильоту') + '</span></div></div></div>';
            }).join('') + '</div>';
        }

        function detailCtas(info, title) {
            const key = escAttr(info.key || DETAIL_TOUR_KEY);
            const label = escAttr(title);
            return '<section class="advisor-cta"><div><h2>Набридло шукати ідеальний готель?</h2><p>Довірте свій вибір професіоналам. Залишіть запит і отримайте добірку готелів за 5 хвилин</p>' +
                '<form class="advisor-lead-form advisor-form" novalidate>' +
                '<input type="hidden" name="tour_key" value="' + key + '">' +
                '<input type="hidden" name="tour_title" value="' + label + '">' +
                '<input type="hidden" name="email" value="">' +
                '<input type="text" name="name" autocomplete="name" required placeholder="Ваше імʼя" aria-label="Ваше імʼя">' +
                '<input type="tel" name="phone" autocomplete="tel" required placeholder="+380 (XX) XXX-XX-XX" aria-label="Телефон">' +
                '<button type="submit" class="advisor-submit">Залишити запит</button>' +
                '</form>' +
                '<p class="advisor-lead-status" role="status" aria-live="polite"></p>' +
                '</div><div class="advisor-person"><div class="advisor-badge"><strong>Підбір від турагенції</strong><span>Консультація та варіанти під ваші дати й бюджет</span></div><img class="advisor-avatar" src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=480&h=480&q=80" alt="" width="190" height="190" loading="lazy" decoding="async" referrerpolicy="no-referrer-when-downgrade"></div></section>' +
                '<section class="detail-section"><h2>Заощаджуйте час - бронюйте тури онлайн!</h2><div class="booking-benefits"><article class="booking-benefit-card"><h3>Найкращі пропозиції</h3><p>Не потрібно перевіряти десятки сайтів - усі пропозиції вже тут.</p></article><article class="booking-benefit-card"><h3>Швидке онлайн бронювання</h3><p>Менеджер швидко перевірить обраний номер і наявність місць.</p></article><article class="booking-benefit-card"><h3>Безпечне бронювання</h3><p>Заявка фіксує ваш інтерес, а оплату погоджуємо після підтвердження.</p></article></div></section>';
        }

        function detailSimilar(offers, heading) {
            if (!offers.length) {
                return '';
            }
            return '<section class="detail-section"><h2>' + esc(heading) + '</h2><div class="similar-grid">' + offers.slice(0, 4).map((offer) => {
                const image = detailImages({ hotel_info: {} }, offer, {})[0] || '';
                const name = offer.hotel || 'Готель';
                const href = escAttr(detailUrl(cardFromOffer(offer)));
                return '<a class="similar-card" href="' + href + '">' + (image ? '<img src="' + escAttr(image) + '" alt="' + escAttr(name) + '" loading="lazy" referrerpolicy="no-referrer-when-downgrade">' : '<div class="similar-card-placeholder" aria-hidden="true"></div>') + '<div class="similar-card-body"><div class="stars">' + esc(starsMarkup(offer.hotel_rating)) + '</div><h3>' + esc(name) + '</h3><p>' + esc([offer.country, offer.region].filter(Boolean).join(', ')) + '</p><strong>' + esc(formatMoneyUAH(detailPriceValue(offer))) + '</strong><span class="similar-card-cta">Переглянути деталі</span></div></a>';
            }).join('') + '</div></section>';
        }

        async function renderTourDetailPage() {
            const key = DETAIL_TOUR_KEY || '';
            const slots = hotelDetailSlots();
            const hasSlots = Object.values(slots).some(Boolean);
            if (!key || (!detailContent && !hasSlots)) {
                return;
            }

            const saved = savedDetailCard(key) || {};
            const card = {
                key,
                hotelId: DETAIL_HOTEL_ID || saved.hotelId || '',
                name: saved.name || 'Деталі туру',
                country: saved.country || '',
                region: saved.region || '',
                rating: saved.rating || '',
                reviewRate: saved.reviewRate || null,
                reviewCount: saved.reviewCount || null,
                image: saved.image || '',
                priceUAH: saved.priceUAH || null,
                dateFrom: saved.dateFrom || '',
                duration: saved.duration || null,
                mealType: saved.mealType || '',
                departureName: saved.departureName || '',
            };

            if (detailLoading) {
                detailLoading.hidden = false;
            }
            if (detailContent) {
                detailContent.hidden = true;
                detailContent.innerHTML = '';
            }

            try {
                const [info, flights, reviews] = await Promise.all([
                    api('tour/info/' + card.key, {}),
                    api('tour/flights/' + card.key, {}).catch(() => ({ from: [], to: [] })),
                    card.hotelId ? api('hotel/' + card.hotelId + '/reviews', { type: 'tripadvisor,tophotels' }).catch(() => []) : Promise.resolve([]),
                ]);

                if (apiError(info)) {
                    throw new Error(apiError(info));
                }

                const hotelInfo = info.hotel_info || {};
                const title = stripHtml(info.hotel || info.hotel_name || hotelInfo.name || card.name || 'Деталі туру');
                const hydratedInfo = {
                    ...info,
                    hotel_id: info.hotel_id || card.hotelId,
                    key: info.key || card.key,
                };
                const [detailOffers, similarOffers] = await Promise.all([
                    detailLoadOffers(hydratedInfo),
                    detailLoadSimilar(hydratedInfo),
                ]);
                const rawTableOffers = detailOffers.length ? detailOffers : [hydratedInfo];
                const tableOffers = detailVisibleOffers(rawTableOffers);
                const bestOffer = tableOffers.slice().sort((left, right) => (detailPriceValue(left) || Infinity) - (detailPriceValue(right) || Infinity))[0] || hydratedInfo;
                const imagesV2 = detailImages(hydratedInfo, bestOffer, card);
                const hotelLocation = [info.region || card.region, info.country || card.country].filter(Boolean).join(', ');
                const mapLink = hotelInfo.lat && hotelInfo.lng
                    ? '<a class="hotel-map-cta" href="https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(hotelInfo.lat + ',' + hotelInfo.lng) + '" target="_blank" rel="noopener" aria-label="Відкрити розташування готелю в Google Картах">' +
                        '<svg class="hotel-map-cta__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
                        '<path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 10.5A3.5 3.5 0 1 1 12 6a3.5 3.5 0 0 1 0 6.5z"/>' +
                        '</svg><span class="hotel-map-cta__label">Показати на карті</span></a>'
                    : '';

                const detailMarkup = '' +
                    '<div class="hotel-detail-shell">' +
                        '<section class="hotel-detail-head">' +
                            '<nav class="tour-breadcrumbs" aria-label="Навігація">' +
                                '<a href="' + escAttr(SITE_HOME_URL) + '">Головна</a><span>/</span>' +
                                '<a href="' + escAttr(CATALOG_BASE_URL) + '#offers-section">Пошук турів</a><span>/</span>' +
                                '<span>' + esc(title) + '</span>' +
                            '</nav>' +
                            '<div class="hotel-detail-title">' +
                                '<div class="stars">' + esc(starsMarkup(info.hotel_rating || card.rating)) + '</div>' +
                                '<h1>' + esc(title) + '</h1>' +
                                '<div class="hotel-location-line"><span class="hotel-location-line__text">' + esc(hotelLocation || 'Локація уточнюється') + '</span>' + mapLink + '</div>' +
                            '</div>' +
                            '<div class="hotel-photo-offer">' + detailGallery(imagesV2, title) + detailBestOffer(hydratedInfo, bestOffer, title) + '</div>' +
                        '</section>' +
                        detailInfoSection(hydratedInfo, title) +
                        detailPriceTable(hydratedInfo, tableOffers, title) +
                        detailCalendar(hydratedInfo, tableOffers, title) +
                        detailCtas(hydratedInfo, title) +
                        detailFacilities(hydratedInfo) +
                        '<section class="detail-section" id="tour-flights"><h2>Рейси</h2>' + detailFlightsMarkup(flights, info) + '</section>' +
                        '<section class="detail-section" id="tour-reviews"><h2>Відгуки</h2>' + renderReviews(reviews) + '</section>' +
                        '<div id="tour-similar-price">' + detailSimilar(similarOffers.slice(0, 4), 'Готелі з аналогічною ціною на тури') + '</div>' +
                        '<div id="tour-similar-beach">' + detailSimilar(similarOffers.slice(4, 8), 'Готелі з аналогічним пляжем') + '</div>' +
                        '<p class="detail-note">Інформація актуальна на момент завантаження сторінки. Остаточні деталі бронювання менеджер підтвердить перед оформленням туру.</p>' +
                    '</div>';

                if (detailContent) {
                    detailContent.innerHTML = detailMarkup;
                    if (detailLoading) {
                        detailLoading.hidden = true;
                    }
                    detailContent.hidden = false;
                    detailContent.style.display = '';
                    detailContent.removeAttribute('aria-hidden');
                    mountDetailSlotsFromContent();
                    initReviewsCarousel(detailContent);
                } else if (hasSlots) {
                    const host = document.createElement('div');
                    host.innerHTML = detailMarkup;
                    const shell = host.querySelector('.hotel-detail-shell');
                    const bySelector = (selector) => shell ? shell.querySelector(selector) : null;
                    const pairs = [
                        [slots.head, '.hotel-detail-head'],
                        [slots.info, '#tour-info'],
                        [slots.prices, '#tour-prices'],
                        [slots.calendar, '#tour-calendar'],
                        [slots.facilities, '#tour-facilities'],
                        [slots.reviews, '#tour-reviews'],
                        [slots.similarPrice, '#tour-similar-price'],
                        [slots.similarBeach, '#tour-similar-beach'],
                    ];
                    pairs.forEach(([slot, selector]) => {
                        if (!slot) return;
                        const node = bySelector(selector);
                        slot.innerHTML = '';
                        if (node) {
                            slot.appendChild(node);
                        }
                    });
                    if (detailLoading) {
                        detailLoading.hidden = true;
                    }
                }
                initReviewsCarousel(document);
            } catch (error) {
                if (detailLoading) {
                    detailLoading.hidden = true;
                }
                if (detailContent) {
                    detailContent.hidden = false;
                }
                const text = error && error.message ? error.message : String(error);
                const slotted = renderDetailSlotsNotice(
                    'Не вдалося завантажити картку готелю',
                    text,
                    CATALOG_BASE_URL,
                    'Повернутися до каталогу',
                );
                if (!slotted && detailContent) {
                    detailContent.innerHTML = '<div class="error-state">' + esc(text) + '</div>';
                }
            }
        }

        if (navPrev) {
            navPrev.addEventListener('click', () => scrollTrack(-1));
        }
        if (navNext) {
            navNext.addEventListener('click', () => scrollTrack(1));
        }
        if (track) {
            track.addEventListener('scroll', updateNav, { passive: true });
        }
        window.addEventListener('resize', updateNav);
        window.addEventListener('scroll', () => {
            document.body.classList.toggle('header-scrolled', window.scrollY > 24);
        }, { passive: true });

        (function initAboutPointScroll() {
            const cards = document.querySelectorAll('.about-point');
            if (!cards.length) {
                return;
            }
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduceMotion || !('IntersectionObserver' in window)) {
                cards.forEach((el) => el.classList.add('is-active'));
                return;
            }
            const observer = new IntersectionObserver(
                (entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting && entry.intersectionRatio >= 0.22) {
                            entry.target.classList.add('is-active');
                        }
                    });
                },
                { root: null, rootMargin: '-5% 0px -8% 0px', threshold: [0, 0.15, 0.3, 0.5, 0.75, 1] },
            );
            cards.forEach((el) => observer.observe(el));
        })();

        if (heroOpenOffers) {
            heroOpenOffers.addEventListener('click', async () => {
                await loadCountry(activeCountryId);
                scrollToOffers();
            });
        }

        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                if (mobileMenu && !mobileMenu.hidden) {
                    closeMobileMenu();
                    return;
                }
                openMobileMenu();
            });
        }

        if (menuClose) {
            menuClose.addEventListener('click', closeMobileMenu);
        }

        if (bookingClose) {
            bookingClose.addEventListener('click', closeBookingForm);
        }

        if (bookingBackdrop) {
            bookingBackdrop.addEventListener('click', (event) => {
                if (event.target === bookingBackdrop) {
                    closeBookingForm();
                }
            });
        }

        if (bookingForm) {
            bookingForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submitButton = bookingForm.querySelector('button[type="submit"]');
                if (bookingStatus) {
                    bookingStatus.classList.remove('is-success', 'is-error');
                    bookingStatus.textContent = 'Відправляємо заявку…';
                }
                if (bookingSuccessCard) {
                    bookingSuccessCard.hidden = true;
                }
                if (submitButton) {
                    submitButton.disabled = true;
                }
                const body = new URLSearchParams(new FormData(bookingForm));
                body.set('action', 'ittour_lab_booking');
                body.set('nonce', nonce);
                try {
                    const response = await fetch(ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body,
                    });
                    const raw = await response.text();
                    let payload = null;
                    try {
                        payload = JSON.parse(raw);
                    } catch (parseError) {
                        throw new Error('Сервер повернув некоректну відповідь. Спробуйте ще раз або перевірте Anex Tour → Заявки.');
                    }
                    if (!payload.success) {
                        throw new Error((payload.data && payload.data.message) || 'Не вдалося відправити заявку');
                    }
                    if (bookingStatus) {
                        bookingStatus.classList.add('is-success');
                        bookingStatus.classList.remove('is-error');
                        bookingStatus.textContent = (payload.data && payload.data.message)
                            || 'Заявку збережено. Менеджер звʼяжеться з вами найближчим часом.';
                    }
                    if (bookingSuccessCard) {
                        bookingSuccessCard.hidden = false;
                    }
                    bookingForm.reset();
                    setTimeout(closeBookingForm, 2200);
                } catch (error) {
                    if (bookingStatus) {
                        bookingStatus.classList.add('is-error');
                        bookingStatus.classList.remove('is-success');
                        bookingStatus.textContent = error.message || 'Не вдалося відправити заявку';
                    }
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                }
            });
        }

        if (mobileMenu) {
            mobileMenu.addEventListener('click', (event) => {
                if (event.target === mobileMenu) {
                    closeMobileMenu();
                }
            });
            mobileMenu.querySelectorAll('a[href^="#"]').forEach((link) => {
                link.addEventListener('click', () => {
                    closeMobileMenu();
                });
            });
        }

        if (countrySelect) {
            countrySelect.addEventListener('change', async (event) => {
                const countryId = event.target.value || '';
                if (!countryId) {
                    return;
                }
                await setActiveCountry(countryId, false);
                syncPsFormFromHero();
                void refreshPsFromSelect();
            });
        }

        if (departureSelect) {
            departureSelect.addEventListener('change', async (event) => {
                const nextId = event.target.value || '';
                if (nextId) {
                    setPsFromSelectedIds([nextId]);
                } else {
                    setPsFromSelectedIds([]);
                }
                syncCountryLabels();
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && mobileMenu && !mobileMenu.hidden) {
                closeMobileMenu();
            }
            if (event.key === 'Escape' && bookingBackdrop && !bookingBackdrop.hidden) {
                closeBookingForm();
            }
            if (event.key === 'Escape' && psPicker && psPicker.classList.contains('is-open')) {
                closePsPicker();
            }
        });

        document.querySelectorAll('.hit-card[data-country]').forEach((button) => {
            button.addEventListener('click', async () => {
                const countryId = button.getAttribute('data-country') || '';
                if (!countryId) {
                    return;
                }
                await setActiveCountry(countryId, true);
                scrollToOffers();
            });
        });

        function excursionDetailStartDate(dateFromRaw) {
            const raw = String(dateFromRaw || '').trim();
            let d;
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                d = new Date(raw + 'T12:00:00');
            } else if (/^\d{2}\.\d{2}\.\d{2}$/.test(raw)) {
                const p = raw.split('.');
                d = new Date(2000 + Number(p[2]), Number(p[1]) - 1, Number(p[0]), 12, 0, 0, 0);
            } else {
                d = new Date();
                d.setHours(12, 0, 0, 0);
            }
            if (Number.isNaN(d.getTime())) {
                d = new Date();
                d.setHours(12, 0, 0, 0);
            }
            return d;
        }

        function excursionDetailDateFromDmy(dateFromRaw) {
            return formatApiDate(excursionDetailStartDate(dateFromRaw));
        }

        function excursionDetailDateTillDmy(dateFromRaw) {
            const d = excursionDetailStartDate(dateFromRaw);
            const end = new Date(d.getTime());
            /* API: date_till не більше ~30 днів від date_from */
            end.setDate(end.getDate() + 28);
            return formatApiDate(end);
        }

        function excursionTourInfoQuery(dateFromRaw) {
            return {
                date_from: excursionDetailDateFromDmy(dateFromRaw),
                date_till: excursionDetailDateTillDmy(dateFromRaw),
                hikes: 'true',
                includes: 'true',
                desc: 'true',
                limit_images: '30',
                day_detail: 'true',
                hotels: 'true',
                accomodations: 'true',
            };
        }

        function excursionSanitizeTourHtml(html) {
            let s = String(html || '');
            s = s.replace(/<script[\s\S]*?<\/script>/gi, '');
            s = s.replace(/<style[\s\S]*?<\/style>/gi, '');
            s = s.replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
            s = s.replace(/javascript:/gi, '');
            s = s.replace(/<iframe[\s\S]*?<\/iframe>/gi, '');
            return s;
        }

        function excursionHtmlHasMarkup(html) {
            return /<[a-z][\s\S]*>/i.test(String(html || ''));
        }

        function excursionPickInsurance(info) {
            if (!info || typeof info !== 'object') {
                return '';
            }
            const v = info.insurance;
            if (typeof v === 'string' && v.trim()) {
                return v.trim();
            }
            if (v === true || v === 1 || v === '1') {
                return 'Так';
            }
            if (v === false || v === 0 || v === '0') {
                return 'Ні';
            }
            const alt = info.insurance_included || info.medical_insurance || info.insurance_name;
            if (typeof alt === 'string' && alt.trim()) {
                return alt.trim();
            }
            return '';
        }

        function excursionPickVisa(info) {
            if (!info || typeof info !== 'object') {
                return '';
            }
            const v = info.visa ?? info.visa_info ?? info.need_visa;
            if (typeof v === 'string' && v.trim()) {
                return v.trim();
            }
            if (v === true || v === 1 || v === '1') {
                return 'Так';
            }
            if (v === false || v === 0 || v === '0') {
                return 'Ні';
            }
            return '';
        }

        function excursionFormatTransport(info) {
            if (!info || typeof info !== 'object') {
                return '';
            }
            const label = info.transport_type;
            if (typeof label === 'string' && label.trim().length > 0 && /[A-Za-zА-Яа-яІіЇїЄєҐґ]/.test(label)) {
                return label.trim();
            }
            const id = Number(info.transport_type_id);
            if (id === 2) return 'Автобус';
            if (id === 1) return 'Авіапереліт';
            return '';
        }

        function excursionPlainParagraphs(text) {
            const t = String(text || '').trim();
            if (!t) {
                return '';
            }
            const parts = t.split(/\n{2,}/).map((s) => s.trim()).filter(Boolean);
            if (!parts.length) {
                return '';
            }
            return '<div class="hotel-info-copy">' + parts.map((p) => '<p>' + esc(p.replace(/\n+/g, ' ')) + '</p>').join('') + '</div>';
        }

        function normalizeTourImageItem(raw) {
            if (raw == null) {
                return null;
            }
            if (typeof raw === 'string') {
                const u = fixMediaUrl(raw.trim());
                return u ? { thumb: u, full: u, web: u, is_main: 0 } : null;
            }
            if (typeof raw === 'object') {
                const thumb = fixMediaUrl(String(raw.thumb || raw.thumbnail || ''));
                const full = fixMediaUrl(String(raw.full || raw.large || raw.big || ''));
                const web = fixMediaUrl(String(raw.web || raw.url || raw.src || raw.image || raw.photo || ''));
                const best = web || full || thumb;
                if (!best) {
                    return null;
                }
                return {
                    thumb: thumb || full || web,
                    full: full || web || thumb,
                    web: web || full || thumb,
                    is_main: raw.is_main,
                };
            }
            return null;
        }

        function collectTourInfoGalleryImages(info) {
            const out = [];
            const seen = new Set();
            const pushItem = (raw) => {
                const n = normalizeTourImageItem(raw);
                if (!n) {
                    return;
                }
                const key = String(n.web || n.full || n.thumb || '').split('?')[0];
                if (!key || seen.has(key)) {
                    return;
                }
                seen.add(key);
                out.push(n);
            };
            const pushList = (arr) => {
                if (Array.isArray(arr)) {
                    arr.forEach(pushItem);
                }
            };
            if (!info || typeof info !== 'object') {
                return out;
            }
            pushList(info.images);
            pushList(info.hotel_images);
            pushList(info.photos);
            pushList(info.gallery);
            pushList(info.tour_images);
            const hi = info.hotel_info && typeof info.hotel_info === 'object' ? info.hotel_info : null;
            if (hi) {
                pushList(hi.images);
                pushList(hi.photos);
                pushList(hi.gallery);
            }
            (Array.isArray(info.hikes) ? info.hikes : []).forEach((h) => {
                if (h && (h.image || h.img)) {
                    pushItem({ thumb: h.image || h.img, web: h.image || h.img, full: h.image || h.img });
                }
            });
            (Array.isArray(info.day_detail) ? info.day_detail : []).forEach((d) => {
                if (d && d.image) {
                    pushItem({ thumb: d.image, web: d.image, full: d.image });
                }
            });
            const ci = info.country_images;
            if (Array.isArray(ci)) {
                pushList(ci);
            }
            (Array.isArray(info.countries) ? info.countries : []).forEach((c) => {
                if (c && Array.isArray(c.images)) {
                    pushList(c.images);
                }
            });
            out.sort((a, b) => Number(b.is_main === 1 || b.is_main === '1') - Number(a.is_main === 1 || a.is_main === '1'));
            return out;
        }

        function buildExcursionDetailState(info, q) {
            const hotelInfo = info && info.hotel_info && typeof info.hotel_info === 'object' ? info.hotel_info : {};
            const images = collectTourInfoGalleryImages(info);
            const mainImageObj = images.find((item) => Number(item.is_main) === 1) || images[0] || null;
            const mainImage = fixMediaUrl(mainImageObj && (mainImageObj.web || mainImageObj.full || mainImageObj.thumb) || '');
            const cityObjs = Array.isArray(info && info.cities) ? info.cities : [];
            const cityNames = cityObjs.map((c) => (c && c.name) || '').filter(Boolean);
            const countriesObjs = Array.isArray(info && info.countries) ? info.countries : [];
            const routeCountries = countriesObjs.map((c) => (c && c.name) || '').filter(Boolean);
            const countryNameById = {};
            countriesObjs.forEach((c) => {
                if (c && c.id != null) {
                    countryNameById[String(c.id)] = String(c.name || '').trim();
                }
            });
            const cityRouteLabels = cityObjs.map((c) => {
                const nm = (c && c.name) ? String(c.name).trim() : '';
                const cid = c && c.country_id != null ? String(c.country_id) : '';
                const cnm = cid ? (countryNameById[cid] || '') : '';
                if (nm && cnm) {
                    return nm + ' (' + cnm + ')';
                }
                return nm || cnm;
            }).filter(Boolean);
            const descRaw = String(info.description || '');
            const rootDescPlain = stripHtml(descRaw).trim();
            const hotelDescHtml = hotelInfo.description_html || hotelInfo.description || String(info.hotel_description || '') || '';
            const hotelDesc = stripHtml(hotelDescHtml).trim();
            const combinedDesc = (rootDescPlain || hotelDesc).trim();
            const descriptionHtml = excursionSanitizeTourHtml(descRaw);
            const toursName = (info.name || info.tour_name || info.hotel || q.name || '').trim();
            const routeLine = [
                routeCountries.join(' · ') || info.country || q.country,
                info.region || '',
            ].filter(Boolean).join(' · ');
            const citiesLine = cityNames.length ? cityNames.join(' · ') : (q.cities || '');
            const dayDetailRaw = Array.isArray(info.day_detail) ? info.day_detail : [];
            const dayDetail = dayDetailRaw.map((d) => {
                if (!d || typeof d !== 'object') {
                    return null;
                }
                const descStr = String(d.description || '');
                const t = String(d.title || '').trim();
                if (!t && !stripHtml(descStr).trim()) {
                    return null;
                }
                return {
                    title: t || 'День',
                    country: String(d.country || '').trim(),
                    city: String(d.city || '').trim(),
                    description: descStr,
                    descriptionHtml: excursionSanitizeTourHtml(descStr),
                    image: d.image ? String(d.image) : '',
                };
            }).filter(Boolean);
            const hikes = Array.isArray(info.hikes) ? info.hikes : [];
            const include = Array.isArray(info.include) ? info.include : [];
            const notInclude = Array.isArray(info.not_include) ? info.not_include : [];
            const documents = Array.isArray(info.documents) ? info.documents : [];
            const docLead = stripHtml(info.document_description || '').trim();
            const op = (info.tour_operator_name || info.operator_name || info.operator || info.spo || '').toString().trim();
            const transferYes = Number(info.transfer) === 1 || String(info.transfer || '').toLowerCase() === 'true';
            const insurance = excursionPickInsurance(info);
            const visa = excursionPickVisa(info);
            const nightMoves = info.night_moves != null && String(info.night_moves).trim() !== '' ? String(info.night_moves).trim() : '';
            const dateRows = [];
            const accList = Array.isArray(info.accomodations) ? info.accomodations : [];
            accList.forEach((ac) => {
                const dts = Array.isArray(ac && ac.dates) ? ac.dates : [];
                dts.forEach((dt) => {
                    const prObj = dt && dt.prices && typeof dt.prices === 'object' ? dt.prices : null;
                    const p2 = prObj ? (prObj['2'] != null ? prObj['2'] : prObj[2]) : null;
                    dateRows.push({
                        dateFrom: String(dt.date_from || dt.date || ''),
                        dateTill: String(dt.date_to || dt.date_till || dt.date_until || ''),
                        meal: [info.meal_type_full || info.meal_type, ac && ac.name].filter(Boolean).join(' · '),
                        room: (info.room_type || '—').toString(),
                        price: Number(p2 != null ? p2 : 0),
                    });
                });
            });
            const countryIdNumeric = (v) => {
                const s = String(v == null ? '' : v).trim();
                return /^\d+$/.test(s) ? s : '';
            };
            let countryId = countryIdNumeric(info.country_id);
            if (!countryId && countriesObjs[0]) {
                countryId = countryIdNumeric(countriesObjs[0].id ?? countriesObjs[0].country_id);
            }
            if (!countryId && cityObjs[0]) {
                countryId = countryIdNumeric(cityObjs[0].country_id);
            }
            if (!countryId) {
                countryId = countryIdNumeric(info.country);
            }
            if (!dateRows.length && (info.date_from || q.dateFromRaw)) {
                const p2 = info.prices && info.prices['2'];
                dateRows.push({
                    dateFrom: String(info.date_from || q.dateFromRaw || ''),
                    dateTill: '',
                    meal: (info.meal_type_full || info.meal_type || '—').toString(),
                    room: (info.room_type || '—').toString(),
                    price: Number(p2 != null ? p2 : (q.priceRaw || 0)),
                });
            }
            return {
                key: String(info.key || info.tour_id || q.key || ''),
                name: toursName || q.name,
                country: routeLine || q.country,
                citiesLine,
                cityNames,
                image: mainImage || q.image,
                galleryImages: images,
                fromCity: info.from_city || q.fromCity,
                dateFromRaw: info.date_from || q.dateFromRaw,
                dateFrom: info.date_from ? formatHumanDate(info.date_from) : q.dateFrom,
                nights: Number(info.hnight || info.duration || q.nights || 0),
                mealType: (info.meal_type_full || info.meal_type || '').toString(),
                roomType: (info.room_type || '').toString(),
                accomodation: (info.accomodation || '').toString(),
                operator: op,
                transport: excursionFormatTransport(info),
                transfer: transferYes ? 'Трансфер включено у вартість' : '',
                program: citiesLine || q.cities,
                description: combinedDesc.slice(0, 4000),
                descriptionHtml,
                hotelDescription: hotelDesc.slice(0, 2000),
                hotelLabel: (info.hotel && String(info.hotel).trim() && String(info.hotel).trim() !== toursName) ? String(info.hotel).trim() : '',
                price: Number((info.prices && info.prices['2']) != null ? info.prices['2'] : (info.price || q.priceRaw || 0)),
                depTimeList: (info.dep_time_list || '').toString().trim(),
                dayDetail,
                hikes,
                include: include.map((s) => stripHtml(String(s || '')).trim()).filter(Boolean),
                notInclude: notInclude.map((s) => stripHtml(String(s || '')).trim()).filter(Boolean),
                documents,
                docLead,
                dateRows,
                countryId,
                cityRouteLabels,
                insurance,
                visa,
                nightMoves,
            };
        }

        function excursionOffersFromSearchPayload(data) {
            if (!data || typeof data !== 'object') {
                return [];
            }
            if (Array.isArray(data.offers)) {
                return data.offers;
            }
            if (Array.isArray(data.tours)) {
                return data.tours;
            }
            if (Array.isArray(data.results)) {
                return data.results;
            }
            return [];
        }

        async function moduleExcursionSearchMergeVariants(baseQuery) {
            const query = {};
            Object.keys(baseQuery || {}).forEach((key) => {
                const value = baseQuery[key];
                if (value != null && String(value).trim() !== '') {
                    query[key] = String(value);
                }
            });
            const merged = [];
            const seen = new Set();
            const offerUniqueKey = (offer) => String(offer && (offer.key || offer.id || offer.tour_id || '')) + '::' + String(offer && offer.date_from || '') + '::' + String(offer && offer.name || '');
            try {
                const data = await api('module-excursion/search', query);
                const list = excursionOffersFromSearchPayload(data);
                list.forEach((offer) => {
                    const uk = offerUniqueKey(offer);
                    if (!offer || seen.has(uk) || merged.length >= 32) {
                        return;
                    }
                    seen.add(uk);
                    merged.push(offer);
                });
            } catch (e) {
            }
            return merged;
        }

        function mountExcursionDetailLayout() {
            const root = document.getElementById('anex-excursion-detail-bootstrap');
            if (!root) {
                return;
            }
            const main = root.querySelector('#anex-excursion-detail-main-col');
            const side = root.querySelector('#anex-excursion-detail-sidebar-col');
            if (!main || !side) {
                return;
            }
            [
                'anex-exc-detail-head',
                'anex-exc-detail-gallery',
                'anex-exc-detail-program',
                'anex-exc-detail-hikes',
                'anex-exc-detail-included',
                'anex-exc-detail-dates',
                'anex-exc-detail-docs',
                'anex-exc-detail-popular',
            ].forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    main.appendChild(el);
                }
            });
            const stack = document.createElement('div');
            stack.className = 'anex-exc-sidebar-stack';
            ['anex-exc-detail-info', 'anex-exc-detail-price'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    stack.appendChild(el);
                }
            });
            if (stack.children.length) {
                side.appendChild(stack);
            }
        }

        function renderExcursionDetailPageFromUrl() {
            const u = new URL(window.location.href);
            if (u.searchParams.get('excursion_detail') !== '1') {
                return false;
            }
            const boot = document.getElementById('anex-excursion-detail-bootstrap');
            const slotIds = [
                'anex-exc-detail-head',
                'anex-exc-detail-info',
                'anex-exc-detail-program',
                'anex-exc-detail-price',
                'anex-exc-detail-gallery',
                'anex-exc-detail-hikes',
                'anex-exc-detail-included',
                'anex-exc-detail-docs',
                'anex-exc-detail-dates',
                'anex-exc-detail-popular',
            ];
            const hasAnySlot = slotIds.some((id) => document.getElementById(id));
            const hasCompact = !!document.querySelector('.anex-excursion-detail-compact');
            const hasBootGrid = !!(boot && boot.querySelector('#anex-excursion-detail-main-col'));
            if (!hasAnySlot && !hasCompact && !hasBootGrid) {
                return false;
            }

            document.body.classList.add('anex-excursion-detail-view');
            mountExcursionDetailLayout();

            const headSlot = document.getElementById('anex-exc-detail-head');
            const infoSlot = document.getElementById('anex-exc-detail-info');
            const programSlot = document.getElementById('anex-exc-detail-program');
            const priceSlot = document.getElementById('anex-exc-detail-price');
            const gallerySlot = document.getElementById('anex-exc-detail-gallery');
            const hikesSlot = document.getElementById('anex-exc-detail-hikes');
            const includedSlot = document.getElementById('anex-exc-detail-included');
            const docsSlot = document.getElementById('anex-exc-detail-docs');
            const datesSlot = document.getElementById('anex-exc-detail-dates');
            const popularSlot = document.getElementById('anex-exc-detail-popular');
            if (!headSlot && !infoSlot && !programSlot && !priceSlot && !gallerySlot && !hikesSlot && !includedSlot && !docsSlot && !datesSlot && !popularSlot) {
                return false;
            }

            const name = u.searchParams.get('exc_name') || 'Екскурсійний тур';
            const country = u.searchParams.get('exc_country') || '';
            const cities = u.searchParams.get('exc_cities') || '';
            const fromCity = u.searchParams.get('exc_from') || '';
            const dateFromRaw = u.searchParams.get('exc_date') || '';
            const nightsRaw = u.searchParams.get('exc_nights') || '';
            const priceRaw = Number(u.searchParams.get('exc_price') || 0);
            const image = u.searchParams.get('exc_image') || '';
            const key = u.searchParams.get('exc_key') || u.searchParams.get('tour_key') || '';
            const dateFrom = dateFromRaw ? formatHumanDate(dateFromRaw) : '';
            const nights = parseInt(nightsRaw, 10) || 0;
            const cityNamesFromUrl = cities
                ? cities.split(/\s*[·,]+\s*/).map((s) => s.trim()).filter(Boolean)
                : [];

            const excS = (which) => {
                const map = {
                    cal: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Zm0 16H5V10h14v10Z"/></svg>',
                    clock: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2Zm.5 5H11v6.25l4.2 2.5.8-1.3-3.5-2.05V7Z"/></svg>',
                    pin: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2C8.1 2 5 5.1 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.9-3.1-7-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg>',
                    bus: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 16c0 .88.39 1.67 1 2.2V20h2v-2h8v2h2v-1.8c.61-.53 1-1.32 1-2.2V6c0-2.21-1.79-4-4-4H8C5.79 2 4 3.79 4 6v10Zm2 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1Zm10 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1Zm1-5H5V8h12v3Z"/></svg>',
                    fork: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 9H9V2H7v7H5V2H3v7c0 2.12 1.66 3.84 3.75 3.97V22h2.5v-9.03C11.34 12.84 13 11.12 13 9V2h-2v7Zm8-7v8h2.5c0 3.05-2.47 5.53-5.5 5.95V22h-2v-6.05C10.47 12.53 8 10.05 8 7V2h2v5h2V2h2v5h2V2h2v5h2V2h2Z"/></svg>',
                    shield: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V5l-8-3Zm0 2.18 6 2.25v4.57c0 4.52-3.13 8.71-6 9.81-2.87-1.1-6-5.29-6-9.81V6.43l6-2.25Z"/></svg>',
                    passport: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-6-4h-6Zm8 14H4V6h5v3h5v9Zm-9-9h2v2H9v-2Z"/></svg>',
                    moon: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3a9 9 0 1 0 9 9c0-.46-.04-.92-.1-1.36a5.39 5.39 0 0 1-4.4 2.36 5.5 5.5 0 0 1-5.5-5.5c0-1.79.86-3.39 2.18-4.4-.44-.06-.9-.1-1.36-.1Z"/></svg>',
                    mic: '<svg class="exc-sic" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 14a3 3 0 0 0 3-3V5a3 3 0 1 0-6 0v6a3 3 0 0 0 3 3zm7-3a7 7 0 0 1-6 6.93V21h-2v-3.07A7 7 0 0 1 5 11h2a5 5 0 0 0 10 0h2z"/></svg>',
                };
                return map[which] || '';
            };

            const renderHead = (state) => {
                if (!headSlot) {
                    return;
                }
                const title = state.name || name;
                const route = state.country || country || 'Маршрут уточнюється';
                const chipSrc = (state.cityRouteLabels && state.cityRouteLabels.length
                    ? state.cityRouteLabels
                    : (state.cityNames && state.cityNames.length ? state.cityNames : cityNamesFromUrl));
                const chips = chipSrc
                    .slice(0, 8)
                    .map((cn) => '<span class="exc-tag">' + esc(String(cn)) + '</span>')
                    .join('');
                const codeLine = (state.key || key)
                    ? '<p class="exc-tour-code">код: <span>' + esc(String(state.key || key)) + '</span></p>'
                    : '';
                headSlot.innerHTML = '' +
                    '<section class="exc-head">' +
                        '<nav class="tour-breadcrumbs" aria-label="Навігація">' +
                            '<a href="' + escAttr(SITE_HOME_URL) + '">Головна</a><span>/</span>' +
                            '<a href="' + escAttr(CATALOG_BASE_URL) + '?mode=excursion">Екскурсійні тури</a><span>/</span>' +
                            '<span>' + esc(title) + '</span>' +
                        '</nav>' +
                        '<h1 class="exc-head-title">' + esc(title) + '</h1>' +
                        codeLine +
                        '<p class="exc-head-route">' + esc(route) + '</p>' +
                        (chips ? '<div class="exc-tag-row">' + chips + '</div>' : '') +
                    '</section>';
            };

            const renderInfo = (state) => {
                if (!infoSlot) {
                    return;
                }
                const nNights = state.nights > 0 ? state.nights : (nights > 0 ? nights : 0);
                let dur = '—';
                if (nNights > 0) {
                    dur = (nNights + 1) + ' днів · ' + nNights + ' ночей';
                }
                const rows = [];
                rows.push({ icon: 'cal', label: 'Дата туру', val: state.dateFrom || dateFrom || '—' });
                rows.push({ icon: 'clock', label: 'Тривалість туру', val: dur });
                rows.push({ icon: 'pin', label: 'Виїзд', val: state.fromCity || fromCity ? ('з ' + (state.fromCity || fromCity)) : '—' });
                if (state.mealType) {
                    rows.push({ icon: 'fork', label: 'Харчування', val: state.mealType });
                }
                if (state.transport) {
                    rows.push({ icon: 'bus', label: 'Транспорт', val: state.transport });
                }
                if (state.nightMoves) {
                    rows.push({ icon: 'moon', label: 'Нічні переїзди', val: state.nightMoves });
                }
                if (state.insurance) {
                    rows.push({ icon: 'shield', label: 'Страховка', val: state.insurance });
                }
                if (state.visa) {
                    rows.push({ icon: 'passport', label: 'Віза', val: state.visa });
                }
                if (state.operator) {
                    rows.push({ icon: 'mic', label: 'Організатор', val: state.operator });
                }
                const list = rows.map((r) =>
                    '<div class="exc-side-fact">' +
                        excS(r.icon) +
                        '<div><span class="exc-side-fact-label">' + esc(r.label) + '</span><strong class="exc-side-fact-val">' + esc(r.val) + '</strong></div>' +
                    '</div>',
                ).join('');
                infoSlot.innerHTML = '' +
                    '<section class="exc-side-card exc-side-card--info">' +
                        '<h2 class="exc-side-title">У вартість включено</h2>' +
                        '<div class="exc-side-facts">' + list + '</div>' +
                    '</section>';
            };

            const bindProgramAcc = () => {
                if (!programSlot) {
                    return;
                }
                const btn = programSlot.querySelector('.exc-acc-collapse');
                if (!btn || btn.dataset.bound) {
                    return;
                }
                btn.dataset.bound = '1';
                btn.addEventListener('click', () => {
                    const anyOpen = programSlot.querySelector('.exc-day-acc[open]');
                    if (anyOpen) {
                        programSlot.querySelectorAll('.exc-day-acc').forEach((d) => { d.open = false; });
                        btn.textContent = 'Розгорнути всі';
                    } else {
                        programSlot.querySelectorAll('.exc-day-acc').forEach((d) => { d.open = true; });
                        btn.textContent = 'Згорнути всі';
                    }
                });
            };

            const renderProgram = (state) => {
                if (!programSlot) {
                    return;
                }
                const pts = (state.cityRouteLabels && state.cityRouteLabels.length
                    ? state.cityRouteLabels
                    : (state.cityNames && state.cityNames.length ? state.cityNames : cityNamesFromUrl));
                let routeHtml = '';
                if (pts.length) {
                    routeHtml = '<div class="exc-route-block"><h2 class="exc-sec-h">Маршрут</h2><div class="exc-route-line">' +
                        pts.map((city, i) =>
                            '<span class="exc-route-step"><span class="exc-route-badge">' + (i + 1) + '</span><span class="exc-route-city">' + esc(String(city)) + '</span></span>',
                        ).join('<span class="exc-route-gap">—</span>') + '</div></div>';
                }
                const days = (state.dayDetail || []).filter((d) => d && (d.title || (d.description && stripHtml(String(d.description)).trim())));
                let progBody = '';
                let descBlock = '';
                let hotelBlock = '';
                const dayBodyHtml = (d) => {
                    const raw = String(d.description || '');
                    const html = d.descriptionHtml || excursionSanitizeTourHtml(raw);
                    if (excursionHtmlHasMarkup(html) && stripHtml(html).trim()) {
                        return '<div class="exc-day-body exc-day-body--html">' + html + '</div>';
                    }
                    const plain = stripHtml(raw).trim();
                    return plain ? '<div class="exc-day-body">' + esc(plain) + '</div>' : '';
                };
                if (days.length) {
                    progBody = '<div class="exc-acc-head"><h2 class="exc-sec-h">Програма</h2><button type="button" class="exc-acc-collapse">Згорнути всі</button></div>';
                    progBody += days.map((d, idx) => {
                        const t = esc(String(d.title || 'День').trim());
                        const loc = [d.country, d.city].filter(Boolean).map((x) => esc(String(x))).join(' · ');
                        const img = d.image ? fixMediaUrl(d.image) : '';
                        const media = img ? '<div class="exc-day-media"><img src="' + escAttr(img) + '" alt="" loading="lazy"></div>' : '';
                        const bodyTxt = dayBodyHtml(d);
                        const openAttr = idx === 0 ? ' open' : '';
                        return '<details class="exc-day-acc"' + openAttr + '><summary>' + t + '</summary>' +
                            '<div class="exc-day-inner">' +
                            (loc ? '<p class="exc-day-loc">' + loc + '</p>' : '') +
                            bodyTxt + media + '</div></details>';
                    }).join('');
                    if (state.descriptionHtml && excursionHtmlHasMarkup(state.descriptionHtml) && stripHtml(state.descriptionHtml).trim()) {
                        descBlock = '<div class="exc-pro-extra"><h3 class="exc-sec-h2">Деталі туру</h3><div class="exc-program-html">' + state.descriptionHtml + '</div></div>';
                    } else if (state.description) {
                        descBlock = '<div class="exc-pro-extra"><h3 class="exc-sec-h2">Деталі туру</h3>' + excursionPlainParagraphs(state.description) + '</div>';
                    }
                    if (state.hotelDescription) {
                        hotelBlock = '<div class="exc-pro-extra"><h3 class="exc-sec-h2">Проживання</h3>' + excursionPlainParagraphs(state.hotelDescription) + '</div>';
                    }
                } else {
                    progBody = '<div class="exc-acc-head"><h2 class="exc-sec-h">Програма</h2></div>';
                    const parts = [];
                    if (state.descriptionHtml && excursionHtmlHasMarkup(state.descriptionHtml) && stripHtml(state.descriptionHtml).trim()) {
                        parts.push('<div class="exc-program-html">' + state.descriptionHtml + '</div>');
                    } else if (state.description) {
                        parts.push(excursionPlainParagraphs(state.description));
                    }
                    if (state.hotelDescription) {
                        parts.push('<h3 class="exc-sec-h2">Проживання</h3>' + excursionPlainParagraphs(state.hotelDescription));
                    }
                    const pt = (state.program || cities || '').trim();
                    if (pt) {
                        parts.push('<p class="exc-fallback">' + esc(pt) + '</p>');
                    }
                    if (!parts.length) {
                        parts.push('<p class="exc-fallback">' + esc('Детальна програма з\'явиться після уточнення у менеджера.') + '</p>');
                    }
                    progBody += '<div class="exc-program-fallback-wrap">' + parts.join('') + '</div>';
                }
                const extras = [];
                if (state.transport) {
                    extras.push('Транспорт: ' + state.transport);
                }
                if (state.transfer) {
                    extras.push(state.transfer);
                }
                if (state.accomodation) {
                    extras.push('Розміщення: ' + state.accomodation);
                }
                programSlot.innerHTML = '' +
                    '<section class="exc-main-program">' +
                        routeHtml +
                        '<div class="exc-program-wrap">' + progBody + '</div>' +
                        (extras.length ? '<ul class="exc-mini-list">' + extras.map((item) => '<li>' + esc(item) + '</li>').join('') + '</ul>' : '') +
                        descBlock + hotelBlock +
                    '</section>';
                bindProgramAcc();
            };

            const renderGallery = (state) => {
                if (!gallerySlot) {
                    return;
                }
                let imgs = (state.galleryImages || []).filter((im) => im && (im.thumb || im.full || im.web));
                if (!imgs.length && (state.image || image)) {
                    const u0 = fixMediaUrl(state.image || image);
                    imgs = [{ thumb: u0, full: u0, web: u0 }];
                }
                if (!imgs.length) {
                    gallerySlot.innerHTML = '';
                    return;
                }
                const main = imgs[0];
                const s1 = imgs[1];
                const s2 = imgs[2];
                const more = Math.max(0, imgs.length - 3);
                const cell = (im) => {
                    const href = fixMediaUrl(im.web || im.full || im.thumb);
                    const thumb = fixMediaUrl(im.thumb || im.full || im.web);
                    return '<a class="exc-gal-cell" href="' + escAttr(href) + '" target="_blank" rel="noopener noreferrer"><img src="' + escAttr(thumb) + '" alt="" loading="lazy"></a>';
                };
                let mosaic = '<div class="exc-gal-mosaic"><div class="exc-gal-main">' + cell(main) + '</div>';
                if (s1) {
                    mosaic += '<div class="exc-gal-stack">';
                    mosaic += '<div class="exc-gal-stack-row">' + cell(s1) + '</div>';
                    if (s2) {
                        mosaic += '<div class="exc-gal-stack-row exc-gal-stack-row--last">' + cell(s2) +
                            (more > 0 ? '<span class="exc-gal-more">+' + more + ' фото</span>' : '') +
                            '</div>';
                    } else if (more > 0) {
                        mosaic += '<div class="exc-gal-stack-row"><span class="exc-gal-more">+' + more + ' фото</span></div>';
                    }
                    mosaic += '</div>';
                }
                mosaic += '</div>';
                gallerySlot.innerHTML = '<section class="exc-gallery-sec">' + mosaic + '</section>';
            };

            const renderHikes = (state) => {
                if (!hikesSlot) {
                    return;
                }
                const list = (state.hikes || []).filter((h) => h && (h.name || h.description));
                if (!list.length) {
                    hikesSlot.innerHTML = '';
                    return;
                }
                hikesSlot.innerHTML = '' +
                    '<section class="exc-hikes-sec">' +
                        '<h2 class="exc-sec-h">Екскурсії та активності</h2>' +
                        '<div class="exc-hike-grid">' +
                            list.map((h) => {
                                const nm = esc(String(h.name || '').trim());
                                const loc = [h.country, h.city].filter(Boolean).join(' · ');
                                const txt = stripHtml(String(h.description || '')).trim();
                                const im = h.image ? fixMediaUrl(h.image) : '';
                                const media = im
                                    ? '<div class="exc-hike-card-media"><img src="' + escAttr(im) + '" alt="" loading="lazy"></div>'
                                    : '<div aria-hidden="true"></div>';
                                return '<article class="exc-hike-card"><div><div class="exc-hike-meta">' + esc(loc) + '</div><h3>' + nm + '</h3><p>' + esc(txt) + '</p></div>' + media + '</article>';
                            }).join('') +
                        '</div>' +
                    '</section>';
            };

            const renderIncluded = (state) => {
                if (!includedSlot) {
                    return;
                }
                const inc = state.include || [];
                const ninc = state.notInclude || [];
                if (!inc.length && !ninc.length) {
                    includedSlot.innerHTML = '';
                    return;
                }
                const colYes = inc.length
                    ? '<div class="exc-inc-box exc-inc-box--yes"><h3><span class="exc-inc-ic exc-inc-ic--ok">✓</span> В екскурсію включено</h3><ul>' + inc.map((i) => '<li>' + esc(i) + '</li>').join('') + '</ul></div>'
                    : '';
                const colNo = ninc.length
                    ? '<div class="exc-inc-box exc-inc-box--no"><h3><span class="exc-inc-ic exc-inc-ic--no">✕</span> Додатково оплачується</h3><ul>' + ninc.map((i) => '<li>' + esc(i) + '</li>').join('') + '</ul></div>'
                    : '';
                includedSlot.innerHTML = '' +
                    '<section class="exc-inc-sec">' +
                        '<div class="exc-inc-grid">' + colYes + colNo + '</div>' +
                    '</section>';
            };

            const renderDocs = (state) => {
                if (!docsSlot) {
                    return;
                }
                const docs = state.documents || [];
                const lead = state.docLead || '';
                if (!docs.length && !lead) {
                    docsSlot.innerHTML = '';
                    return;
                }
                const links = docs.map((doc) => {
                    const ttl = esc(String(doc.title || 'Документ').trim());
                    const file = fixMediaUrl(doc.file || doc.url || '');
                    if (!file) {
                        return '';
                    }
                    return '<a href="' + escAttr(file) + '" target="_blank" rel="noopener">' + ttl + '<span aria-hidden="true"> ↗</span></a>';
                }).filter(Boolean).join('');
                const leadHtml = lead ? '<p class="exc-docs-lead">' + esc(lead) + '</p>' : '';
                docsSlot.innerHTML = '' +
                    '<section class="exc-docs-sec">' +
                        '<h2 class="exc-sec-h">Документи та памʼятка</h2>' +
                        leadHtml +
                        (links ? '<div class="exc-docs-list">' + links + '</div>' : '') +
                    '</section>';
            };

            const renderDates = (state) => {
                if (!datesSlot) {
                    return;
                }
                const rows = state.dateRows || [];
                if (!rows.length) {
                    datesSlot.innerHTML = '';
                    return;
                }
                const tourKeyAttr = state.key || key || '';
                const titleEsc = escAttr(state.name || name);
                const tbody = rows.slice(0, 16).map((r) => {
                    const d1 = r.dateFrom ? formatHumanDate(r.dateFrom) : '—';
                    const d2 = r.dateTill ? formatHumanDate(r.dateTill) : '—';
                    const mealShort = String(r.meal || '—').split('·')[0].trim();
                    const mealSub = String(r.meal || '').includes('·') ? String(r.meal).split('·').slice(1).join('·').trim() : '';
                    const priceText = r.price > 0 ? formatMoneyUAH(r.price) : '—';
                    const fc = state.fromCity || fromCity;
                    return '<tr>' +
                        '<td data-label="Виїзд"><strong>' + esc(d1) + '</strong>' + (fc ? '<small>з ' + esc(fc) + '</small>' : '') + '</td>' +
                        '<td data-label="Повернення"><strong>' + esc(d2) + '</strong></td>' +
                        '<td data-label="Харчування"><strong>' + esc(mealShort) + '</strong>' + (mealSub ? '<small>' + esc(mealSub) + '</small>' : '') + '</td>' +
                        '<td data-label="Номер"><strong>' + esc(r.room || '—') + '</strong></td>' +
                        '<td data-label="Вартість"><strong>' + esc(priceText) + '</strong></td>' +
                        '<td class="exc-dates-actions">' +
                            '<button type="button" class="exc-cta-btn exc-cta-btn--sm detail-buy-button booking-open" data-tour-key="' + escAttr(tourKeyAttr) + '" data-tour-title="' + titleEsc + '">Купити онлайн</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');
                datesSlot.innerHTML = '' +
                    '<section class="exc-dates-sec">' +
                        '<h2 class="exc-sec-h">Дати виїздів</h2>' +
                        '<div class="exc-dates-wrap">' +
                            '<table class="exc-dates-table">' +
                                '<thead><tr><th>Виїзд</th><th>Повернення</th><th>Харчування</th><th>Номер</th><th>Вартість</th><th></th></tr></thead>' +
                                '<tbody>' + tbody + '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</section>';
            };

            const fetchFromCityIdForHotOffers = async (countryId) => {
                const cid = String(countryId || '').trim();
                if (!cid) {
                    return '';
                }
                try {
                    const data = await api('showcase/hot-offers/filters', { showcase_number: '1' });
                    const rows = Array.isArray(data && data.from_cities) ? data.from_cities : [];
                    const hit = rows.find((r) => String(r.country_id || '') === cid) || rows[0];
                    if (hit && hit.id != null) {
                        return String(hit.id).trim();
                    }
                } catch (e) {
                }
                return '';
            };

            const fetchShowcaseHotOffersForPopular = async (countryId, excludeKey) => {
                const cidNum = parseInt(String(countryId || '').trim(), 10);
                if (!Number.isFinite(cidNum) || cidNum <= 0) {
                    return [];
                }
                const fromCityId = await fetchFromCityIdForHotOffers(String(cidNum));
                const ex = String(excludeKey || '').trim();
                const attempts = [];
                const push = (q) => attempts.push(q);
                push({
                    showcase_number: '1',
                    country: String(cidNum),
                    hotel_rating: '3:78',
                    night_from: '1',
                    night_till: '21',
                    page: '1',
                    items_per_page: '24',
                    hotel_image: '1',
                    ...(fromCityId ? { from_city: fromCityId } : {}),
                });
                if (fromCityId) {
                    push({
                        showcase_number: '1',
                        country: String(cidNum),
                        hotel_rating: '3:78',
                        night_from: '1',
                        night_till: '21',
                        page: '1',
                        items_per_page: '24',
                        hotel_image: '1',
                    });
                }
                push({
                    showcase_number: '1',
                    country: String(cidNum),
                    hotel_rating: '78:79:7',
                    night_from: '2',
                    night_till: '21',
                    page: '1',
                    items_per_page: '24',
                    hotel_image: '1',
                    ...(fromCityId ? { from_city: fromCityId } : {}),
                });
                push({
                    showcase_number: '1',
                    country: String(cidNum),
                    hotel_rating: '3:78',
                    night_from: '3',
                    night_till: '14',
                    page: '1',
                    items_per_page: '32',
                    hotel_image: '1',
                    ...(fromCityId ? { from_city: fromCityId } : {}),
                });
                for (const q of attempts) {
                    try {
                        const data = await api('showcase/hot-offers/search', q);
                        let offers = Array.isArray(data && data.offers) ? data.offers : [];
                        if (ex) {
                            offers = offers.filter((o) => String(o.key || '') !== ex);
                        }
                        if (offers.length) {
                            return offers.slice(0, 10).map((o) => Object.assign({}, o, {
                                __isHotelHotOffer: true,
                                name: String(o.hotel || o.region || o.country || 'Тур').trim(),
                                city_names: o.region ? [String(o.region)] : [],
                                country_names: o.country ? [String(o.country)] : [],
                            }));
                        }
                    } catch (e) {
                    }
                }
                return [];
            };

            const fetchExcursionPopularForDetail = async (countryId, excludeKey) => {
                const cid = String(countryId || '').trim();
                if (!cid) {
                    return [];
                }
                const start = new Date();
                start.setHours(12, 0, 0, 0);
                const end = new Date(start.getTime());
                end.setDate(end.getDate() + 56);
                const baseQ = {
                    country: cid,
                    date_from: formatApiDate(start),
                    date_till: formatApiDate(end),
                    night_from: '1',
                    night_till: '30',
                    adult: '2',
                    child: '0',
                    page: '1',
                    items_per_page: '32',
                };
                let offers = await moduleExcursionSearchMergeVariants({ ...baseQ, transport_type: '2' });
                if (!offers.length) {
                    offers = await moduleExcursionSearchMergeVariants({ ...baseQ });
                }
                const ex = String(excludeKey || '').trim();
                const exNum = /^\d+$/.test(ex) ? ex : '';
                if (ex || exNum) {
                    offers = offers.filter((o) => {
                        const k = String(o && o.key != null ? o.key : '').trim();
                        const tid = String(o && o.tour_id != null ? o.tour_id : '').trim();
                        if (ex && (k === ex || tid === ex)) {
                            return false;
                        }
                        if (exNum && (k === exNum || tid === exNum)) {
                            return false;
                        }
                        return true;
                    });
                }
                return dedupeExcursionOffers(offers).slice(0, 10);
            };

            const loadExcursionDetailPopular = async (state) => {
                const popularEl = document.getElementById('anex-exc-detail-popular');
                if (!popularEl) {
                    return;
                }
                const catalogLink = String(CATALOG_BASE_URL || SITE_HOME_URL || '/');
                const catalogHref = catalogLink + (catalogLink.includes('?') ? '&' : '?') + 'mode=excursion';
                const emptyBlock = (bodyHtml) => '' +
                    '<section class="exc-pop-sec">' +
                        '<h2 class="exc-sec-h">Популярні екскурсії</h2>' +
                        '<div class="exc-pop-empty">' + bodyHtml + '</div>' +
                    '</section>';
                const cid = String(state.countryId || '').trim() || String(DEFAULT_COUNTRY_ID || '').trim();
                if (!cid) {
                    popularEl.innerHTML = emptyBlock('<p>Не вдалося визначити країну для підбірки. Перегляньте <a href="' + escAttr(catalogHref) + '">каталог екскурсій</a>.</p>');
                    return;
                }
                popularEl.innerHTML = '' +
                    '<section class="exc-pop-sec">' +
                        '<h2 class="exc-sec-h">Популярні екскурсії</h2>' +
                        '<p class="exc-pop-loading">Завантаження…</p>' +
                    '</section>';
                let offers = await fetchExcursionPopularForDetail(cid, state.key || key);
                let sectionTitle = 'Популярні екскурсії';
                if (!offers.length) {
                    offers = await fetchShowcaseHotOffersForPopular(cid, state.key || key);
                    sectionTitle = 'Гарячі тури в країні';
                }
                if (!offers.length) {
                    popularEl.innerHTML = emptyBlock('<p>За обраними параметрами зараз немає інших турів у цьому напрямку. Перегляньте <a href="' + escAttr(catalogHref) + '">усі екскурсійні тури</a>.</p>');
                    return;
                }
                const base = EXCURSION_DETAIL_NAV_BASE || CATALOG_BASE_URL || window.location.href;
                const buildExcUrl = (offer) => {
                    const url = new URL(base, window.location.origin);
                    const countries = Array.isArray(offer.country_names) ? offer.country_names.join(', ') : (offer.country || '');
                    const cityNames = Array.isArray(offer.city_names) ? offer.city_names.join(', ') : '';
                    const imgUrl = excursionThumbFromOffer(offer);
                    url.searchParams.set('excursion_detail', '1');
                    url.searchParams.set('exc_key', String(offer.key || ''));
                    url.searchParams.set('exc_name', String(offer.name || 'Екскурсійний тур'));
                    url.searchParams.set('exc_country', String(countries || ''));
                    url.searchParams.set('exc_cities', String(cityNames || ''));
                    url.searchParams.set('exc_from', String(offer.from_city || ''));
                    url.searchParams.set('exc_date', String(offer.date_from || ''));
                    url.searchParams.set('exc_nights', String(offer.duration || ''));
                    url.searchParams.set('exc_price', String((offer.prices && offer.prices['2']) != null ? offer.prices['2'] : (offer.price || '')));
                    url.searchParams.set('exc_image', String(imgUrl || ''));
                    return url.toString();
                };
                const buildPopularItemHref = (offer) => {
                    if (offer && offer.__isHotelHotOffer) {
                        return detailUrl({
                            key: String(offer.key || ''),
                            hotelId: offer.hotel_id != null ? String(offer.hotel_id) : '',
                        });
                    }
                    return buildExcUrl(offer);
                };
                const cards = offers.map((offer) => {
                    const nm = esc(String(offer.name || 'Тур').trim());
                    const href = escAttr(buildPopularItemHref(offer));
                    const im = excursionThumbFromOffer(offer);
                    const meta = Array.isArray(offer.city_names) && offer.city_names.length
                        ? esc(offer.city_names.slice(0, 3).join(' · '))
                        : esc(String(offer.country || ''));
                    const nmoves = Number(offer.night_moves || 0) || 0;
                    const co = Number(offer.count_order || 0) || 0;
                    const badgeTop = co >= 3 ? '<span class="exc-pop-badge exc-pop-badge--top">ТОП продажів</span>' : '';
                    const badgeNight = nmoves > 0 ? '<span class="exc-pop-badge exc-pop-badge--night">' + nmoves + ' нічні переїзди</span>' : '';
                    const tt = String(offer.transport_type || '').toLowerCase();
                    const badgeTrans = offer.__isHotelHotOffer && tt
                        ? ('<span class="exc-pop-badge exc-pop-badge--night">' + esc(tt === 'flight' ? 'Авіа' : (tt === 'bus' ? 'Автобус' : tt)) + '</span>')
                        : '';
                    const badges = (badgeTop || badgeNight || badgeTrans) ? '<div class="exc-pop-badges">' + badgeTop + badgeNight + badgeTrans + '</div>' : '';
                    const price = Number((offer.prices && offer.prices['2']) != null ? offer.prices['2'] : (offer.price || 0));
                    const priceLine = price > 0 ? ('від ' + formatMoneyUAH(price)) : 'Ціну уточнюємо';
                    const imgTag = im ? '<img src="' + escAttr(im) + '" alt="" loading="lazy">' : '';
                    const media = '<div class="exc-pop-card-media">' + imgTag + badges + '</div>';
                    const ctaLabel = offer.__isHotelHotOffer ? 'Деталі туру' : 'Переглянути деталі';
                    return '<article class="exc-pop-card">' + media +
                        '<div class="exc-pop-card-body">' +
                            '<h3 class="exc-pop-card-title">' + nm + '</h3>' +
                            '<p class="exc-pop-card-meta">' + meta + '</p>' +
                            '<div class="exc-pop-card-price">' + esc(priceLine) + ' <small>за одного</small></div>' +
                            '<a class="exc-pop-card-cta" href="' + href + '">' + esc(ctaLabel) + '</a>' +
                        '</div></article>';
                }).join('');
                popularEl.innerHTML = '<section class="exc-pop-sec"><h2 class="exc-sec-h">' + esc(sectionTitle) + '</h2><div class="exc-pop-scroll">' + cards + '</div></section>';
            };

            const renderPrice = (state) => {
                if (!priceSlot) {
                    return;
                }
                const shownPrice = state.price > 0 ? state.price : priceRaw;
                const tourKeyAttr = state.key || key || '';
                priceSlot.innerHTML = '' +
                    '<section class="exc-side-card exc-side-card--price">' +
                        '<p class="exc-side-price-hint">Ціна за одну людину</p>' +
                        '<p class="exc-side-price-big">' + esc(shownPrice > 0 ? ('від ' + formatMoneyUAH(shownPrice)) : 'Ціна уточнюється') + '</p>' +
                        '<button type="button" class="exc-cta-btn detail-buy-button booking-open" data-tour-key="' + escAttr(tourKeyAttr) + '" data-tour-title="' + escAttr(state.name || name) + '">Купити онлайн</button>' +
                        '<p class="exc-side-trust"><span class="exc-trust-dot">✓</span> Гарантовано мінімальна ціна</p>' +
                    '</section>';
            };

            const applyExcursionDetailState = (state) => {
                renderHead(state);
                renderInfo(state);
                renderProgram(state);
                renderGallery(state);
                renderHikes(state);
                renderIncluded(state);
                renderDates(state);
                renderDocs(state);
                renderPrice(state);
                void loadExcursionDetailPopular(state);
            };

            const baseState = {
                key,
                name,
                country,
                citiesLine: cities,
                cityNames: cityNamesFromUrl,
                cityRouteLabels: cityNamesFromUrl.slice(),
                image,
                galleryImages: [],
                countryId: '',
                fromCity,
                dateFromRaw,
                dateFrom,
                nights,
                mealType: '',
                roomType: '',
                accomodation: '',
                operator: '',
                transport: '',
                transfer: '',
                program: cities,
                description: '',
                descriptionHtml: '',
                hotelDescription: '',
                hotelLabel: '',
                price: priceRaw,
                depTimeList: '',
                dayDetail: [],
                hikes: [],
                include: [],
                notInclude: [],
                documents: [],
                docLead: '',
                dateRows: [],
                insurance: '',
                visa: '',
                nightMoves: '',
            };

            applyExcursionDetailState(baseState);

            if (key) {
                api('tour-excursion/info/' + encodeURIComponent(key), excursionTourInfoQuery(dateFromRaw))
                    .then((data) => {
                        const info = data && typeof data === 'object' ? data : {};
                        const state = buildExcursionDetailState(info, {
                            name,
                            country,
                            cities,
                            fromCity,
                            dateFromRaw,
                            dateFrom,
                            nights,
                            priceRaw,
                            image,
                            key,
                        });
                        applyExcursionDetailState(state);
                    })
                    .catch(() => {});
            }
            return true;
        }

        if (DETAIL_TOUR_KEY) {
            renderTourDetailPage();
            return;
        }

        if (renderExcursionDetailPageFromUrl()) {
            return;
        }

        renderDetailSlotsNotice(
            'Оберіть готель у каталозі',
            'Щоб побачити детальну картку, відкрийте готель зі сторінки пошуку турів. На цю сторінку має передаватись параметр tour_key у URL.',
            CATALOG_BASE_URL,
            'Перейти до каталогу',
        );

        populateCountrySelect();
        renderCountryPills();
        renderDirectionsSkeletons();

        void setActiveCountry(
            PRESET_SEARCH && PRESET_SEARCH.countryId ? PRESET_SEARCH.countryId : activeCountryId,
            false
        ).then(() => {
            if (!ANEX_CATALOG_LITE) {
                initPopularSearchFlow();
            } else {
                stripLegacySearchQueryFromUrl();
            }
        });
        void loadDirections();

        /* ── Шоукейс популярних країн з табами ── */
        async function renderCountryShowcase() {
            const showcase = document.getElementById('country-showcase');
            if (!showcase) return;

            const CARDS_PER_COUNTRY = 4;
            const wins = buildCandidateWindows();
            const cardsCache = new Map();    /* countryId → cards[] */
            const fetchPromises = new Map(); /* countryId → Promise — щоб не дублювати запити */

            function showcaseSearchQuery(country, win) {
                return {
                    type: '1', kind: '1',
                    country: String(country.id),
                    adult_amount: '2', child_amount: '0',
                    hotel_rating: '3:78',
                    night_from: '5', night_till: '10',
                    date_from: win.date_from, date_till: win.date_till,
                    items_per_page: '12', hotel_info: '1', hotel_image: '1', currency: '2',
                };
            }

            function fetchCardsForCountry(country) {
                if (cardsCache.has(country.id)) return Promise.resolve(cardsCache.get(country.id));
                if (fetchPromises.has(country.id)) return fetchPromises.get(country.id);
                const promise = (async () => {
                    try {
                        const batch = await Promise.all(
                            wins.map((win) => api('module/search-list', showcaseSearchQuery(country, win))),
                        );
                        for (let i = 0; i < batch.length; i++) {
                            const offers = dedupeHotels(sortHotels(batch[i].offers || []));
                            if (offers.length > 0) {
                                const cards = offers.slice(0, CARDS_PER_COUNTRY).map((o) => cardFromOffer(o, wins[i]));
                                cardsCache.set(country.id, cards);
                                return cards;
                            }
                        }
                    } catch (e) {}
                    cardsCache.set(country.id, []);
                    return [];
                })();
                fetchPromises.set(country.id, promise);
                return promise;
            }

            function renderTabCards(panelEl, cards) {
                if (!cards.length) {
                    panelEl.innerHTML = '<p class="empty-state">Немає доступних пропозицій по цій країні.</p>';
                    return;
                }
                panelEl.innerHTML = '<div class="showcase-cards">' +
                    cards.map((card) => {
                        const reviewBlock = card.reviewRate
                            ? '<div class="review-chip"><div class="review-copy"><strong>' + esc(reviewLabel(card.reviewRate)) + '</strong><span>' + esc((card.reviewCount || 0) + ' відгуків') + '</span></div><div class="review-score">' + esc(Number(card.reviewRate).toFixed(1)) + '</div></div>'
                            : '';
                        const imageMarkup = card.image
                            ? '<img src="' + escAttr(card.image) + '" alt="' + escAttr(card.name) + '" loading="lazy" referrerpolicy="no-referrer-when-downgrade">'
                            : '';
                        const duration = card.duration ? card.duration + ' ночей' : '';
                        const url = detailUrl(card);
                        return '<article class="hotel-card">' +
                            '<div class="' + (card.image ? 'hotel-media' : 'hotel-media no-image') + '">' + imageMarkup + reviewBlock + '</div>' +
                            '<div class="hotel-body">' +
                                '<div class="stars">' + esc(starsMarkup(card.rating)) + '</div>' +
                                '<h3 class="hotel-title">' + esc(card.name) + '</h3>' +
                                '<p class="hotel-location">' + esc(card.region || card.country) + '</p>' +
                                '<div class="hotel-meta">' +
                                    '<span>' + esc('Від ' + formatHumanDate(card.dateFrom)) + '</span>' +
                                    (duration ? '<span>' + esc(duration + (card.mealType ? ' · ' + card.mealType : '')) + '</span>' : '') +
                                '</div>' +
                                '<div class="hotel-price">Ціна за 2 дорослих<strong>' + esc(formatMoneyUAH(card.priceUAH)) + '</strong></div>' +
                                '<a class="card-action" href="' + escAttr(url) + '" data-key="' + escAttr(card.key) + '">Переглянути деталі</a>' +
                            '</div>' +
                        '</article>';
                    }).join('') +
                '</div>';
                panelEl.querySelectorAll('.card-action').forEach((btn) => {
                    const key = btn.getAttribute('data-key') || '';
                    btn.addEventListener('click', () => {
                        const card = cards.find((c) => c.key === key);
                        if (card) try { sessionStorage.setItem('ittour:last-card:' + key, JSON.stringify(card)); } catch (e) {}
                    });
                });
            }

            showcase.innerHTML =
                '<div class="showcase-tabs" id="showcase-tabs-row"></div>' +
                '<div class="showcase-panels" id="showcase-panels"></div>';
            const tabsRow = showcase.querySelector('#showcase-tabs-row');
            const panelsEl = showcase.querySelector('#showcase-panels');

            function activateTab(countryId) {
                tabsRow.querySelectorAll('.showcase-tab').forEach((b) =>
                    b.classList.toggle('is-active', b.getAttribute('data-country') === String(countryId)));
                panelsEl.querySelectorAll('.showcase-panel').forEach((p) =>
                    p.classList.toggle('is-active', p.getAttribute('data-country') === String(countryId)));
            }

            function updateTabPrice(countryId, cards) {
                const el = document.getElementById('tab-price-' + escAttr(countryId));
                if (!el) return;
                const min = minPriceWithTransport(cards);
                el.textContent = min < Infinity ? 'від ' + formatMoneyUAH(min) : '';
            }

            /* Рендеримо таби і панелі */
            const panels = [];
            FEATURED_COUNTRIES.forEach((country, idx) => {
                const tab = document.createElement('button');
                tab.type = 'button';
                tab.className = 'showcase-tab' + (idx === 0 ? ' is-active' : '');
                tab.setAttribute('data-country', country.id);
                tab.innerHTML = esc(country.name) +
                    '<span class="tab-price" id="tab-price-' + escAttr(country.id) + '"></span>';
                tabsRow.appendChild(tab);

                const panel = document.createElement('div');
                panel.className = 'showcase-panel' + (idx === 0 ? ' is-active' : '');
                panel.setAttribute('data-country', country.id);
                panel.innerHTML =
                    '<div class="showcase-skeleton">' +
                        '<div class="skeleton-card"></div>'.repeat(CARDS_PER_COUNTRY) +
                    '</div>';
                panelsEl.appendChild(panel);
                panels.push(panel);

                tab.addEventListener('click', () => {
                    activateTab(country.id);
                    /* Якщо вже є дані в кеші — рендеримо миттєво */
                    if (cardsCache.has(country.id)) {
                        if (!panel.querySelector('.showcase-cards')) {
                            renderTabCards(panel, cardsCache.get(country.id));
                        }
                        return;
                    }
                    /* Чекаємо той самий Promise (без нового запиту) */
                    fetchCardsForCountry(country).then((cards) => {
                        renderTabCards(panel, cards);
                        updateTabPrice(country.id, cards);
                    });
                });
            });

            if (FEATURED_COUNTRIES[0]) {
                fetchCardsForCountry(FEATURED_COUNTRIES[0]).then((cards) => {
                    updateTabPrice(FEATURED_COUNTRIES[0].id, cards);
                    const panel = panels[0];
                    if (panel && !panel.querySelector('.showcase-cards')) {
                        renderTabCards(panel, cards);
                    }
                });
            }
        }

        void renderCountryShowcase();
    })();
    </script>
<?php if (!$_anex_embed): ?>
</body>
</html>
<?php exit;
endif; ?>
