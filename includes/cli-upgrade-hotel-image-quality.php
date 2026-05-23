<?php
/**
 * Перекачати фото готелів у full-якості (було thumb 320×240).
 *
 * wp eval-file wp-content/plugins/anex-tour/includes/cli-upgrade-hotel-image-quality.php
 *
 * @package AnexTour
 */

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via: wp eval-file .../cli-upgrade-hotel-image-quality.php\n";
	exit( 1 );
}

if ( ! function_exists( 'anex_hotel_get_gallery_ids' ) ) {
	echo "Plugin anex-tour not loaded.\n";
	exit( 1 );
}

if ( function_exists( 'set_time_limit' ) ) {
	set_time_limit( 900 );
}

$posts   = get_posts(
	[
		'post_type'      => ANEX_HOTEL_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]
);
$upgraded = 0;
$failed   = 0;
$skipped  = 0;

foreach ( $posts as $post ) {
	$ids = anex_hotel_get_gallery_ids( $post->ID );
	$fid = (int) get_post_thumbnail_id( $post->ID );
	if ( $fid > 0 && ! in_array( $fid, $ids, true ) ) {
		$ids[] = $fid;
	}

	$post_up = 0;
	foreach ( array_unique( $ids ) as $att_id ) {
		$att_id = (int) $att_id;
		if ( $att_id <= 0 || ! anex_attachment_needs_quality_upgrade( $att_id ) ) {
			++$skipped;
			continue;
		}

		$source = (string) get_post_meta( $att_id, '_anex_source_url', true );
		$full   = $source !== '' ? anex_normalize_ittour_image_url_to_full( $source ) : '';

		if ( $full === '' ) {
			$hid = (string) get_post_meta( $post->ID, '_anex_ittour_hotel_id', true );
			$cid = (string) get_post_meta( $post->ID, '_anex_country_id', true );
			if ( $hid !== '' && $cid !== '' ) {
				$map = anex_fetch_hotel_gallery_url_map_from_search_list( $hid, $cid );
				$full = (string) array_key_first( $map );
			}
		}

		if ( $full === '' ) {
			++$failed;
			echo "  post {$post->ID} att {$att_id}: no full URL\n";
			continue;
		}

		if ( anex_replace_attachment_file_from_url( $att_id, $full ) ) {
			++$upgraded;
			++$post_up;
		} else {
			++$failed;
			echo "  post {$post->ID} att {$att_id}: download failed\n";
		}
	}

	if ( $post_up > 0 ) {
		$keys = anex_hotel_meta_keys();
		$main = (int) get_post_thumbnail_id( $post->ID );
		if ( $main > 0 ) {
			$u = (string) wp_get_attachment_url( $main );
			if ( $u !== '' ) {
				update_post_meta( $post->ID, $keys['thumb_url'], $u );
			}
		}
		echo "Hotel #{$post->ID}: upgraded {$post_up} images\n";
	}
}

echo "\nDone. upgraded: {$upgraded}, skipped (ok): {$skipped}, failed: {$failed}\n";
