<?php
/**
 * One-off / WP-CLI: підтягнути URL і featured image з module/search-list по країні.
 *
 * wp eval-file wp-content/plugins/anex-tour/includes/cli-fix-hotel-photos.php
 *
 * @package AnexTour
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file .../cli-fix-hotel-photos.php\n";
	exit( 1 );
}

if ( ! function_exists( 'ittour_lab_api_fetch' ) || ! function_exists( 'anex_sideload_hotel_thumbnail' ) ) {
	echo "Plugin anex-tour (API) not loaded.\n";
	exit( 1 );
}

$posts = get_posts(
	[
		'post_type'      => ANEX_HOTEL_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]
);

$by_country = [];
foreach ( $posts as $post ) {
	$cid = (string) get_post_meta( $post->ID, '_anex_country_id', true );
	$hid = (string) get_post_meta( $post->ID, '_anex_ittour_hotel_id', true );
	if ( $cid === '' || $hid === '' ) {
		continue;
	}
	if ( ! isset( $by_country[ $cid ] ) ) {
		$by_country[ $cid ] = [];
	}
	$by_country[ $cid ][ $hid ] = (int) $post->ID;
}

$from_offset = 21;
$date_from   = wp_date( 'd.m.y', strtotime( '+' . $from_offset . ' days' ) );
$date_till   = wp_date( 'd.m.y', strtotime( '+' . ( $from_offset + 11 ) . ' days' ) );

$attached = 0;
$url_only = 0;
$miss     = 0;
$api_calls = 0;

foreach ( $by_country as $country_id => $hotel_map ) {
	echo "Country {$country_id}: " . count( $hotel_map ) . " hotels\n";

	++$api_calls;
	$result = ittour_lab_api_fetch(
		'module/search-list',
		[
			'type'           => '1',
			'kind'           => '1',
			'country'        => (string) $country_id,
			'adult_amount'   => '2',
			'child_amount'   => '0',
			'hotel_rating'   => '1:78',
			'night_from'     => '7',
			'night_till'     => '14',
			'date_from'      => $date_from,
			'date_till'      => $date_till,
			'items_per_page' => '120',
			'hotel_info'     => '1',
			'hotel_image'    => '1',
			'currency'       => '2',
		],
		'uk'
	);

	if ( is_wp_error( $result ) ) {
		echo "  HTTP error: " . $result->get_error_message() . "\n";
		continue;
	}

	$data = $result['data'] ?? [];
	if ( ! is_array( $data ) || ! empty( $data['error'] ) ) {
		echo '  API error: ' . (string) ( $data['error_desc'] ?? $data['error'] ?? 'unknown' ) . "\n";
		continue;
	}

	$offers = $data['offers'] ?? [];
	if ( ! is_array( $offers ) ) {
		$offers = [];
	}

	$found_ids  = [];
	$done_posts = [];
	foreach ( $offers as $offer ) {
		if ( ! is_array( $offer ) ) {
			continue;
		}
		$hid = (string) ( $offer['hotel_id'] ?? $offer['hotel'] ?? '' );
		if ( $hid === '' || ! isset( $hotel_map[ $hid ] ) ) {
			continue;
		}
		$post_id = (int) $hotel_map[ $hid ];
		if ( isset( $done_posts[ $post_id ] ) ) {
			continue;
		}
		$found_ids[ $hid ]        = true;
		$done_posts[ $post_id ] = true;

		anex_hotel_clear_photo_skip( $post_id );
		$url = anex_extract_hotel_thumb_url( $offer );
		if ( $url === '' ) {
			echo "  #{$hid} no image in offer\n";
			++$miss;
			continue;
		}

		update_post_meta( $post_id, '_anex_thumb_url', $url );
		$status = anex_sideload_hotel_thumbnail( $post_id, $url );
		echo "  #{$hid} post {$post_id}: {$status}\n";
		if ( 'attached' === $status ) {
			++$attached;
		} elseif ( 'url_only' === $status ) {
			++$url_only;
		} else {
			++$miss;
		}
	}

	foreach ( $hotel_map as $hid => $post_id ) {
		if ( ! isset( $found_ids[ $hid ] ) ) {
			echo "  #{$hid} not in search-list offers\n";
			++$miss;
		}
	}
}

echo "\nDone. API calls: {$api_calls}. Attached: {$attached}, URL only: {$url_only}, miss: {$miss}\n";
