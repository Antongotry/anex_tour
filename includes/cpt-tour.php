<?php
/**
 * CPT «Тури» (екскурсійні) — статичний шар з IT-Tour module-excursion/search.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

const ANEX_TOUR_POST_TYPE       = 'anex_tour';
const ANEX_TOUR_SYNC_OPTION     = 'anex_tour_sync_state';
const ANEX_TOUR_COUNTRIES_OPT   = 'anex_tour_sync_countries';

function anex_tour_meta_keys(): array {
	return [
		'ittour_tour_key'  => '_anex_ittour_tour_key',
		'country_ids'      => '_anex_country_ids',
		'country_names'    => '_anex_country_names',
		'city_names'       => '_anex_city_names',
		'duration'         => '_anex_duration',
		'transport_type'   => '_anex_transport_type',
		'from_city'        => '_anex_from_city',
		'date_from'        => '_anex_date_from',
		'date_till'        => '_anex_date_till',
		'meal_type'        => '_anex_meal_type',
		'thumb_url'        => '_anex_thumb_url',
		'synced_at'        => '_anex_synced_at',
		'sync_error'       => '_anex_sync_error',
		'gallery_ids'      => '_anex_gallery_ids',
	];
}

function anex_tour_default_country_ids(): array {
	return function_exists( 'anex_hotel_default_country_ids' )
		? anex_hotel_default_country_ids()
		: [ 318, 338, 16, 372, 434, 39, 320, 376 ];
}

function anex_tour_sync_country_ids(): array {
	$saved = get_option( ANEX_TOUR_COUNTRIES_OPT, [] );
	if ( ! is_array( $saved ) || $saved === [] ) {
		return anex_tour_default_country_ids();
	}
	$out = [];
	foreach ( $saved as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$out[] = $id;
		}
	}
	return $out !== [] ? array_values( array_unique( $out ) ) : anex_tour_default_country_ids();
}

add_action( 'init', 'anex_register_tour_cpt' );

function anex_register_tour_cpt(): void {
	register_post_type(
		ANEX_TOUR_POST_TYPE,
		[
			'labels'              => [
				'name'          => 'Тури',
				'singular_name' => 'Тур',
				'add_new_item'  => 'Додати тур',
				'edit_item'     => 'Редагувати тур',
				'search_items'  => 'Шукати тури',
				'not_found'     => 'Турів не знайдено',
				'menu_name'     => 'Тури',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'rest_base'           => 'anex-tours',
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			'has_archive'         => false,
			'rewrite'             => false,
		]
	);
}

add_action( 'admin_menu', 'anex_tour_admin_submenu', 21 );

function anex_tour_admin_submenu(): void {
	add_submenu_page(
		'anex-tour',
		'Тури',
		'Тури',
		'manage_options',
		'edit.php?post_type=' . ANEX_TOUR_POST_TYPE
	);
}

add_filter( 'manage_' . ANEX_TOUR_POST_TYPE . '_posts_columns', 'anex_tour_list_columns' );
add_action( 'manage_' . ANEX_TOUR_POST_TYPE . '_posts_custom_column', 'anex_tour_render_column', 10, 2 );

function anex_tour_list_columns( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['anex_tour_key']  = 'Tour key';
			$new['anex_countries'] = 'Країни';
			$new['anex_thumb']     = 'Фото';
		}
	}
	return $new;
}

function anex_tour_render_column( string $column, int $post_id ): void {
	$keys = anex_tour_meta_keys();
	switch ( $column ) {
		case 'anex_tour_key':
			echo esc_html( (string) get_post_meta( $post_id, $keys['ittour_tour_key'], true ) );
			break;
		case 'anex_countries':
			$names = get_post_meta( $post_id, $keys['country_names'], true );
			if ( is_string( $names ) ) {
				$decoded = json_decode( $names, true );
				$names   = is_array( $decoded ) ? $decoded : [ $names ];
			}
			echo esc_html( is_array( $names ) ? implode( ', ', $names ) : '—' );
			break;
		case 'anex_thumb':
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, [ 64, 48 ] );
			} else {
				$url = (string) get_post_meta( $post_id, $keys['thumb_url'], true );
				echo $url !== '' ? '<img src="' . esc_url( $url ) . '" style="width:48px;height:36px;object-fit:cover" alt="" />' : '—';
			}
			break;
	}
}

function anex_find_tour_post_by_key( string $tour_key ): ?WP_Post {
	$tour_key = trim( $tour_key );
	if ( $tour_key === '' ) {
		return null;
	}
	$q = new WP_Query(
		[
			'post_type'      => ANEX_TOUR_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => anex_tour_meta_keys()['ittour_tour_key'],
					'value' => $tour_key,
				],
			],
			'fields'         => 'ids',
		]
	);
	if ( ! $q->have_posts() ) {
		return null;
	}
	$post = get_post( (int) $q->posts[0] );
	return $post instanceof WP_Post ? $post : null;
}

/**
 * @return array<string, bool> URL => is_main
 */
function anex_extract_tour_gallery_url_map( array $offer ): array {
	$map = [];
	if ( function_exists( 'anex_extract_hotel_gallery_url_map' ) ) {
		$map = anex_extract_hotel_gallery_url_map( $offer );
	}
	foreach ( [ 'country_images', 'images', 'tour_images' ] as $key ) {
		if ( empty( $offer[ $key ] ) || ! is_array( $offer[ $key ] ) ) {
			continue;
		}
		foreach ( $offer[ $key ] as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url = function_exists( 'anex_pick_best_url_from_image_item' )
				? anex_pick_best_url_from_image_item( $item )
				: '';
			if ( $url !== '' ) {
				$map[ $url ] = $map[ $url ] ?? false;
			}
		}
	}
	return $map;
}

function anex_extract_tour_thumb_url( array $offer ): string {
	$map = anex_extract_tour_gallery_url_map( $offer );
	if ( $map !== [] ) {
		foreach ( $map as $url => $is_main ) {
			if ( $is_main ) {
				return $url;
			}
		}
		return (string) array_key_first( $map );
	}
	return '';
}

/**
 * @return array{created:bool, post_id:int}
 */
function anex_upsert_tour_from_offer( array $offer ): array {
	$key = trim( (string) ( $offer['key'] ?? $offer['tour_key'] ?? '' ) );
	if ( $key === '' ) {
		return [ 'created' => false, 'post_id' => 0 ];
	}

	$name = trim( (string) ( $offer['name'] ?? $offer['tour_name'] ?? '' ) );
	if ( $name === '' ) {
		$name = 'Тур #' . $key;
	}

	$existing = anex_find_tour_post_by_key( $key );
	$created  = ! ( $existing instanceof WP_Post );

	$postarr = [
		'post_type'   => ANEX_TOUR_POST_TYPE,
		'post_title'  => $name,
		'post_status' => 'publish',
	];
	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = $existing->ID;
		$post_id       = wp_update_post( $postarr, true );
	} else {
		$post_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return [ 'created' => false, 'post_id' => 0 ];
	}

	$post_id = (int) $post_id;
	$keys    = anex_tour_meta_keys();

	$country_ids   = $offer['country_ids'] ?? [];
	$country_names = $offer['country_names'] ?? [];
	$city_names    = $offer['city_names'] ?? [];

	update_post_meta( $post_id, $keys['ittour_tour_key'], $key );
	update_post_meta( $post_id, $keys['country_ids'], wp_json_encode( is_array( $country_ids ) ? $country_ids : [] ) );
	update_post_meta( $post_id, $keys['country_names'], wp_json_encode( is_array( $country_names ) ? $country_names : [] ) );
	update_post_meta( $post_id, $keys['city_names'], wp_json_encode( is_array( $city_names ) ? $city_names : [] ) );
	update_post_meta( $post_id, $keys['duration'], (string) ( $offer['duration'] ?? '' ) );
	update_post_meta( $post_id, $keys['transport_type'], (string) ( $offer['transport_type'] ?? '' ) );
	update_post_meta( $post_id, $keys['from_city'], (string) ( $offer['from_city'] ?? '' ) );
	update_post_meta( $post_id, $keys['date_from'], (string) ( $offer['date_from'] ?? '' ) );
	update_post_meta( $post_id, $keys['date_till'], (string) ( $offer['date_till'] ?? '' ) );
	update_post_meta( $post_id, $keys['meal_type'], (string) ( $offer['meal_type_full'] ?? $offer['meal_type'] ?? '' ) );

	$thumb = anex_extract_tour_thumb_url( $offer );
	if ( $thumb !== '' ) {
		update_post_meta( $post_id, $keys['thumb_url'], $thumb );
	}

	update_post_meta( $post_id, $keys['synced_at'], current_time( 'mysql' ) );
	delete_post_meta( $post_id, $keys['sync_error'] );

	return [ 'created' => $created, 'post_id' => $post_id ];
}

/**
 * @return int[]
 */
function anex_tour_get_gallery_ids( int $post_id ): array {
	$raw = get_post_meta( $post_id, anex_tour_meta_keys()['gallery_ids'], true );
	if ( ! is_array( $raw ) ) {
		$decoded = json_decode( (string) $raw, true );
		$raw     = is_array( $decoded ) ? $decoded : [];
	}
	$out = [];
	foreach ( $raw as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$out[] = $id;
		}
	}
	return array_values( array_unique( $out ) );
}

function anex_tour_save_gallery_ids( int $post_id, array $ids ): void {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	update_post_meta( $post_id, anex_tour_meta_keys()['gallery_ids'], wp_json_encode( $ids ) );
}

/**
 * Завантажити фото туру (country_images + tour-excursion/info).
 */
function anex_import_tour_media( int $post_id, array $offer = [] ): void {
	if ( ! function_exists( 'anex_sideload_attachment_from_url' ) || ! function_exists( 'anex_import_hotel_gallery' ) ) {
		return;
	}

	$keys     = anex_tour_meta_keys();
	$tour_key = (string) get_post_meta( $post_id, $keys['ittour_tour_key'], true );
	$url_map  = $offer !== [] ? anex_extract_tour_gallery_url_map( $offer ) : [];

	if ( $tour_key !== '' && function_exists( 'ittour_lab_api_fetch' ) ) {
		$date_from = wp_date( 'd.m.y', strtotime( '+21 days' ) );
		$date_till = wp_date( 'd.m.y', strtotime( '+32 days' ) );
		$result    = ittour_lab_api_fetch(
			'tour-excursion/info/' . rawurlencode( $tour_key ),
			[
				'date_from'     => $date_from,
				'date_till'     => $date_till,
				'limit_images'  => '20',
			],
			'uk'
		);
		if ( ! is_wp_error( $result ) ) {
			$data = $result['data'] ?? $result;
			if ( is_array( $data ) && empty( $data['error'] ) && function_exists( 'anex_extract_hotel_gallery_url_map' ) ) {
				foreach ( anex_extract_hotel_gallery_url_map( $data ) as $url => $is_main ) {
					if ( $is_main ) {
						$url_map[ $url ] = true;
					} else {
						$url_map[ $url ] = $url_map[ $url ] ?? false;
					}
				}
			}
		}
	}

	if ( $url_map === [] ) {
		return;
	}

	anex_import_hotel_gallery( $post_id, $url_map, 15 );
}

add_action( 'rest_api_init', 'anex_tour_register_rest_fields' );

function anex_tour_register_rest_fields(): void {
	register_rest_field(
		ANEX_TOUR_POST_TYPE,
		'anex_meta',
		[
			'get_callback' => static function ( array $obj ) {
				$post_id = (int) ( $obj['id'] ?? 0 );
				if ( $post_id <= 0 ) {
					return [];
				}
				$keys = anex_tour_meta_keys();
				$urls = [];
				if ( function_exists( 'anex_hotel_get_gallery_items' ) ) {
					foreach ( anex_hotel_get_gallery_items( $post_id ) as $item ) {
						if ( ! empty( $item['url'] ) ) {
							$urls[] = $item['url'];
						}
					}
				}
				return [
					'tour_key'       => (string) get_post_meta( $post_id, $keys['ittour_tour_key'], true ),
					'country_names'  => json_decode( (string) get_post_meta( $post_id, $keys['country_names'], true ), true ) ?: [],
					'city_names'     => json_decode( (string) get_post_meta( $post_id, $keys['city_names'], true ), true ) ?: [],
					'thumb_url'      => (string) get_post_meta( $post_id, $keys['thumb_url'], true ),
					'gallery_urls'   => $urls,
				];
			},
			'schema'       => [
				'description' => 'Anex tour meta for catalog',
				'type'        => 'object',
			],
		]
	);
}
