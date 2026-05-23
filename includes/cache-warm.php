<?php
/**
 * Pre-warm IT-Tour search-list transients for featured countries (twice daily).
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

add_action( 'anex_warm_showcase_cache', 'anex_warm_showcase_cache_run' );

/**
 * Schedule cron on plugin load.
 */
add_action( 'init', static function (): void {
	if ( wp_next_scheduled( 'anex_warm_showcase_cache' ) ) {
		return;
	}
	wp_schedule_event( time() + 300, 'twicedaily', 'anex_warm_showcase_cache' );
}, 20 );

/**
 * One lightweight search per featured country (same window as front widgets).
 */
function anex_warm_showcase_cache_run(): void {
	if ( ! function_exists( 'ittour_lab_api_fetch' ) || ittour_lab_get_token() === '' ) {
		return;
	}

	$country_ids = [ '318', '338', '39', '372', '16', '434' ];
	$from_offset = 21;
	$query_base  = [
		'type'           => '1',
		'kind'           => '1',
		'adult_amount'   => '2',
		'child_amount'   => '0',
		'hotel_rating'   => '3:78',
		'night_from'     => '5',
		'night_till'     => '10',
		'date_from'      => wp_date( 'd.m.y', strtotime( '+' . $from_offset . ' days' ) ),
		'date_till'      => wp_date( 'd.m.y', strtotime( '+' . ( $from_offset + 7 ) . ' days' ) ),
		'items_per_page' => '12',
		'hotel_info'     => '0',
		'currency'       => '2',
	];

	foreach ( $country_ids as $country_id ) {
		$query             = $query_base;
		$query['country']  = (string) $country_id;
		$result            = ittour_lab_api_fetch( 'module/search-list', $query, 'uk' );
		if ( is_wp_error( $result ) ) {
			break;
		}
		$data = $result['data'] ?? null;
		if ( is_array( $data ) && ! empty( $data['error_code'] ) && (string) $data['error_code'] === '108' ) {
			break;
		}
	}
}
