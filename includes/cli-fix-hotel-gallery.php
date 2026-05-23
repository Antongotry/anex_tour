<?php
/**
 * Галерея: search-list + tour/info (hotel_info.images, до 20 фото).
 *
 * wp eval-file wp-content/plugins/anex-tour/includes/cli-fix-hotel-gallery.php
 *
 * @package AnexTour
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file .../cli-fix-hotel-gallery.php\n";
	exit( 1 );
}

if ( ! function_exists( 'ittour_lab_api_fetch' ) || ! function_exists( 'anex_import_hotel_gallery' ) ) {
	echo "Plugin anex-tour not loaded.\n";
	exit( 1 );
}

if ( function_exists( 'set_time_limit' ) ) {
	set_time_limit( 600 );
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

$api_calls    = 0;
$tour_calls   = 0;
$hotels_done  = 0;
$images_added = 0;

foreach ( $by_country as $country_id => $hotel_map ) {
	echo "Country {$country_id}: " . count( $hotel_map ) . " hotels\n";

	++$api_calls;
	$offers = anex_fetch_search_list_offers_for_country( (string) $country_id );
	if ( is_wp_error( $offers ) ) {
		echo '  API: ' . $offers->get_error_message() . "\n";
		continue;
	}

	/** @var array<string, array<string, bool>> $urls_by_hotel */
	$urls_by_hotel = [];
	/** @var array<string, string> $keys_by_hotel */
	$keys_by_hotel = [];
	foreach ( $offers as $offer ) {
		if ( ! is_array( $offer ) ) {
			continue;
		}
		$hid = (string) ( $offer['hotel_id'] ?? $offer['hotel'] ?? '' );
		if ( $hid === '' || ! isset( $hotel_map[ $hid ] ) ) {
			continue;
		}
		if ( ! isset( $urls_by_hotel[ $hid ] ) ) {
			$urls_by_hotel[ $hid ] = [];
		}
		if ( ! isset( $keys_by_hotel[ $hid ] ) && ! empty( $offer['key'] ) ) {
			$keys_by_hotel[ $hid ] = (string) $offer['key'];
		}
		foreach ( anex_extract_hotel_gallery_url_map( $offer ) as $url => $is_main ) {
			if ( $is_main ) {
				$urls_by_hotel[ $hid ][ $url ] = true;
			} else {
				$urls_by_hotel[ $hid ][ $url ] = $urls_by_hotel[ $hid ][ $url ] ?? false;
			}
		}
	}

	foreach ( $hotel_map as $hid => $post_id ) {
		$url_map = $urls_by_hotel[ $hid ] ?? [];
		if ( ! empty( $keys_by_hotel[ $hid ] ) ) {
			++$tour_calls;
			foreach ( anex_fetch_hotel_gallery_url_map_from_tour_info( $keys_by_hotel[ $hid ] ) as $url => $is_main ) {
				if ( $is_main ) {
					$url_map[ $url ] = true;
				} else {
					$url_map[ $url ] = $url_map[ $url ] ?? false;
				}
			}
		}
		if ( $url_map === [] ) {
			echo "  #{$hid} post {$post_id}: no images in offers\n";
			continue;
		}

		$before = count( anex_hotel_get_gallery_ids( $post_id ) );
		$result = anex_import_hotel_gallery( $post_id, $url_map );
		$delta  = (int) $result['added'];
		$images_added += $delta;
		++$hotels_done;
		echo "  #{$hid} post {$post_id}: urls " . count( $url_map ) . ", gallery {$before} -> {$result['total']}, +{$delta} new\n";
	}
}

echo "\nDone. search-list: {$api_calls}, tour/info: {$tour_calls}, hotels: {$hotels_done}, new images: {$images_added}\n";
