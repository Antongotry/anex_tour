<?php
/**
 * Plugin Name:  Anex Tour Widget
 * Plugin URI:   https://github.com/Antongotry/anex_tour
 * Description:  Пошук турів, каталог готелів і форма бронювання для турагентства Anex Tour. Вставляйте через шорткоди в Elementor або будь-який редактор.
 * Version:      1.5.24
 * Author:       Anex Tour Львів
 * Author URI:   https://anextour.com.ua
 * Text Domain:  anex-tour
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ─────────────────────────────────────────────
   Constants
───────────────────────────────────────────── */
define( 'ANEX_VERSION',     '1.5.24' );
define( 'ANEX_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ANEX_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ANEX_PLUGIN_FILE', __FILE__ );

// Backward-compat: keep ITTOUR_ constants so existing templates still work
if ( ! defined( 'ITTOUR_LAB_TOKEN_OPTION' ) ) {
    define( 'ITTOUR_LAB_TOKEN_OPTION',          'ittour_api_token' );
    define( 'ITTOUR_LAB_API_BASE',              'https://api.ittour.com.ua/' );
    define( 'ITTOUR_LAB_PAGE_SLUG',             get_option( 'anex_slug_tour_lab',      'ittour-lab' ) );
    define( 'ITTOUR_HOTELS_WIDGET_PAGE_SLUG',   get_option( 'anex_slug_hotel_catalog', 'populyarni-goteli' ) );
    define( 'ITTOUR_LAB_TEMPLATE',              ANEX_PLUGIN_DIR . 'templates/tour-lab.php' );
    define( 'ITTOUR_HOTELS_WIDGET_TEMPLATE',    ANEX_PLUGIN_DIR . 'templates/hotel-catalog.php' );
    define( 'ITTOUR_LAB_BOOKINGS_OPTION',       'ittour_lab_bookings' );
}

/* ─────────────────────────────────────────────
   Includes — API/бронювання не дублюємо, якщо вже є MU-плагін ittour-api-lab.php
───────────────────────────────────────────── */
$anex_bundled_api_loaded = false;
if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
	require_once ANEX_PLUGIN_DIR . 'includes/api.php';
	$anex_bundled_api_loaded = true;
}
require_once ANEX_PLUGIN_DIR . 'includes/rest-search-v2.php';
if ( ! function_exists( 'ittour_lab_ajax_booking' ) ) {
	require_once ANEX_PLUGIN_DIR . 'includes/booking.php';
}

require_once ANEX_PLUGIN_DIR . 'includes/catalog-url.php';
require_once ANEX_PLUGIN_DIR . 'includes/cpt-hotel.php';
require_once ANEX_PLUGIN_DIR . 'includes/cpt-tour.php';
require_once ANEX_PLUGIN_DIR . 'includes/admin.php';
require_once ANEX_PLUGIN_DIR . 'includes/admin-hotel-sync.php';
require_once ANEX_PLUGIN_DIR . 'includes/admin-tour-sync.php';
require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-log.php';
require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-hotels.php';
require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-log-tours.php';
require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-tours.php';
require_once ANEX_PLUGIN_DIR . 'includes/shortcodes.php';
require_once ANEX_PLUGIN_DIR . 'includes/cache-warm.php';

/* ─────────────────────────────────────────────
   Template filter — лише slug з налаштувань.
   НЕ підміняти Elementor-сторінки (напр. /katalog/) повним hotel-catalog.php — шорткоди рендерить тема/Elementor.
───────────────────────────────────────────── */
add_filter( 'template_include', function ( $template ) {
	$map = [
		ITTOUR_LAB_PAGE_SLUG           => ANEX_PLUGIN_DIR . 'templates/tour-lab.php',
		ITTOUR_HOTELS_WIDGET_PAGE_SLUG => ANEX_PLUGIN_DIR . 'templates/hotel-catalog.php',
	];
	foreach ( $map as $slug => $tpl ) {
		if ( is_page( $slug ) && is_readable( $tpl ) ) {
			return $tpl;
		}
	}
	return $template;
}, 99 );

/*
 * WordPress can treat `country` as a public query var (often taxonomy-driven),
 * which turns `/katalog/?search=1&country=...` into a 404 on hard reload.
 * Keep backward compatibility for old links by removing this var from main routing
 * only for catalog page requests. Frontend still reads raw `$_GET`.
 */
add_filter( 'request', static function ( array $query_vars ): array {
	if ( is_admin() || ! isset( $_GET['search'] ) || '1' !== (string) $_GET['search'] ) {
		return $query_vars;
	}
	$uri_path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$uri_path = trim( $uri_path, '/' );
	$catalog_slugs = array_filter(
		array_unique(
			array(
				'katalog',
				'populyarni-goteli',
				trim( (string) get_option( 'anex_slug_hotel_catalog', '' ), '/' ),
			)
		)
	);
	$is_catalog_request = in_array( $uri_path, $catalog_slugs, true );
	if ( ! $is_catalog_request ) {
		return $query_vars;
	}
	unset( $query_vars['country'] );
	return $query_vars;
}, 9 );

add_action( 'parse_request', static function (): void {
	if ( is_admin() ) {
		return;
	}
	if ( ! isset( $_GET['country'] ) || isset( $_GET['country_id'] ) ) {
		return;
	}
	$uri_path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$uri_path = trim( $uri_path, '/' );
	$catalog_slugs = array_filter(
		array_unique(
			array(
				'katalog',
				'populyarni-goteli',
				trim( (string) get_option( 'anex_slug_hotel_catalog', '' ), '/' ),
			)
		)
	);
	if ( ! in_array( $uri_path, $catalog_slugs, true ) ) {
		return;
	}
	$qs = wp_unslash( $_GET );
	$qs['country_id'] = (string) $qs['country'];
	unset( $qs['country'] );
	$target = home_url( '/' . $uri_path . '/' );
	$target = add_query_arg( $qs, $target );
	wp_safe_redirect( $target, 301 );
	exit;
}, 1 );

/**
 * П.1: старі посилання /katalog/?search=1 — редірект без search (country_id лишається для вкладки).
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( ! function_exists( 'anex_is_katalog_landing_page' ) || ! anex_is_katalog_landing_page() ) {
			return;
		}
		if ( ! isset( $_GET['search'] ) || '1' !== (string) $_GET['search'] ) {
			return;
		}
		$country_id = '';
		if ( ! empty( $_GET['country_id'] ) ) {
			$country_id = sanitize_text_field( wp_unslash( (string) $_GET['country_id'] ) );
		} elseif ( ! empty( $_GET['country'] ) ) {
			$country_id = sanitize_text_field( wp_unslash( (string) $_GET['country'] ) );
		}
		$target = function_exists( 'anex_get_catalog_page_permalink' )
			? anex_get_catalog_page_permalink( [] )
			: home_url( '/katalog/' );
		if ( '' !== $country_id ) {
			$target = add_query_arg( 'country_id', $country_id, $target );
		}
		wp_safe_redirect( $target, 301 );
		exit;
	},
	5
);

if ( ! $anex_bundled_api_loaded ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p><strong>Anex Tour:</strong> ';
			echo esc_html__( 'На сайті вже підключено IT-Tour API (must-use плагін). Дублікат функцій відключено — це нормально. Якщо потрібен лише Anex Tour без MU-версії, перенесіть або видаліть ', 'anex-tour' );
			echo '<code>wp-content/mu-plugins/ittour-api-lab.php</code>.</p></div>';
		}
	);
}

/* ─────────────────────────────────────────────
   Activation: flush rewrite rules
───────────────────────────────────────────── */
register_activation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
