<?php
/**
 * Доповнити всі тури: країни, опис, ціни, фото.
 *
 * wp eval-file wp-content/plugins/anex-tour/includes/cli-enrich-tours.php
 *
 * @package AnexTour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! function_exists( 'anex_enrich_tour_post' ) ) {
	echo "anex_enrich_tour_post missing.\n";
	exit( 1 );
}

$posts = get_posts(
	[
		'post_type'      => ANEX_TOUR_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	]
);

foreach ( $posts as $post ) {
	$key = (string) get_post_meta( $post->ID, '_anex_ittour_tour_key', true );
	echo "Tour #{$post->ID} key={$key}…\n";
	$offer = function_exists( 'anex_fetch_excursion_offer_by_key' ) ? anex_fetch_excursion_offer_by_key( $key ) : [];
	anex_enrich_tour_post( (int) $post->ID, $key, $offer );
	$countries = anex_tour_get_country_names( (int) $post->ID );
	$price     = (string) get_post_meta( $post->ID, '_anex_price_label', true );
	$thumb     = has_post_thumbnail( $post->ID ) ? 'yes' : 'no';
	$gallery   = function_exists( 'anex_hotel_get_gallery_ids' )
		? count( anex_hotel_get_gallery_ids( (int) $post->ID ) )
		: 0;
	echo "  countries: " . implode( ', ', $countries ) . "\n";
	echo "  price: {$price}\n";
	echo "  featured: {$thumb}, gallery: {$gallery}\n";
}

echo "Done. " . count( $posts ) . " tours enriched.\n";
