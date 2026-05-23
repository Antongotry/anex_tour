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
		'prices_json'      => '_anex_prices_json',
		'price_uah'        => '_anex_price_uah',
		'price_per_night'  => '_anex_price_per_night',
		'price_label'      => '_anex_price_label',
	];
}

/**
 * JSON meta без «u0421» (зберігаємо UTF-8, не \u з з’їденим slash).
 */
function anex_json_meta_encode( $data ): string {
	return (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
}

/**
 * @return array<int, string>
 */
function anex_json_meta_decode_list( $raw ): array {
	if ( is_array( $raw ) ) {
		return anex_sanitize_label_list( $raw );
	}
	$raw = (string) $raw;
	if ( $raw === '' ) {
		return [];
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		$fixed = anex_fix_unicode_escape_string( $raw );
		return $fixed !== '' ? [ $fixed ] : [];
	}
	return anex_sanitize_label_list( $decoded );
}

/**
 * @param array<int, mixed> $list
 * @return array<int, string>
 */
function anex_sanitize_label_list( array $list ): array {
	$out = [];
	foreach ( $list as $item ) {
		if ( ! is_scalar( $item ) ) {
			continue;
		}
		$s = anex_fix_unicode_escape_string( trim( (string) $item ) );
		if ( $s !== '' ) {
			$out[] = $s;
		}
	}
	return array_values( array_unique( $out ) );
}

function anex_fix_unicode_escape_string( string $s ): string {
	$s = trim( $s );
	if ( $s === '' ) {
		return '';
	}
	if ( preg_match( '/[\x{0400}-\x{04FF}]/u', $s ) ) {
		return $s;
	}
	if ( preg_match( '/u[0-9a-fA-F]{4}/', $s ) ) {
		$fixed = preg_replace_callback(
			'/u([0-9a-fA-F]{4})/',
			static function ( array $m ): string {
				return mb_chr( (int) hexdec( $m[1] ), 'UTF-8' );
			},
			$s
		);
		return is_string( $fixed ) ? $fixed : $s;
	}
	return $s;
}

/**
 * @return array<int, string>
 */
function anex_tour_get_country_names( int $post_id ): array {
	return anex_json_meta_decode_list( get_post_meta( $post_id, anex_tour_meta_keys()['country_names'], true ) );
}

/**
 * @return array<int, string>
 */
function anex_tour_get_city_names( int $post_id ): array {
	return anex_json_meta_decode_list( get_post_meta( $post_id, anex_tour_meta_keys()['city_names'], true ) );
}

/**
 * IT-Tour: ключ "2" у prices — зазвичай UAH за 2 дорослих.
 *
 * @return array{uah:int, per_night:int, label:string, raw:array<string, int>}
 */
function anex_tour_parse_prices_from_offer( array $offer ): array {
	$prices = $offer['prices'] ?? [];
	if ( ! is_array( $prices ) ) {
		return [ 'uah' => 0, 'per_night' => 0, 'label' => '', 'raw' => [] ];
	}
	$uah    = (int) ( $prices['2'] ?? $prices[2] ?? 0 );
	$nights = max( 1, (int) ( $offer['duration'] ?? 1 ) );
	$per    = $uah > 0 ? (int) round( $uah / $nights ) : 0;
	$label  = '';
	if ( $uah > 0 ) {
		$fmt_uah = number_format( $uah, 0, '', ' ' );
		$fmt_per = number_format( $per, 0, '', ' ' );
		$label   = sprintf( 'від %s ₴ за 2 дор. / %d н. (%s ₴/ніч)', $fmt_uah, $nights, $fmt_per );
	}
	return [
		'uah'       => $uah,
		'per_night' => $per,
		'label'     => $label,
		'raw'       => array_map( 'intval', $prices ),
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
			$new['anex_price']     = 'Ціна';
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
			$names = anex_tour_get_country_names( $post_id );
			echo esc_html( $names !== [] ? implode( ', ', $names ) : '—' );
			break;
		case 'anex_price':
			$label = (string) get_post_meta( $post_id, $keys['price_label'], true );
			echo esc_html( $label !== '' ? $label : '—' );
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
	update_post_meta( $post_id, $keys['country_ids'], anex_json_meta_encode( is_array( $country_ids ) ? $country_ids : [] ) );
	update_post_meta( $post_id, $keys['country_names'], anex_json_meta_encode( anex_sanitize_label_list( is_array( $country_names ) ? $country_names : [] ) ) );
	update_post_meta( $post_id, $keys['city_names'], anex_json_meta_encode( anex_sanitize_label_list( is_array( $city_names ) ? $city_names : [] ) ) );

	$prices = anex_tour_parse_prices_from_offer( $offer );
	update_post_meta( $post_id, $keys['prices_json'], anex_json_meta_encode( $prices['raw'] ) );
	update_post_meta( $post_id, $keys['price_uah'], (string) $prices['uah'] );
	update_post_meta( $post_id, $keys['price_per_night'], (string) $prices['per_night'] );
	update_post_meta( $post_id, $keys['price_label'], $prices['label'] );
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

	anex_enrich_tour_post( $post_id, $key, $offer );

	return [ 'created' => $created, 'post_id' => $post_id ];
}

/**
 * Знайти офер у module-excursion/search за tour key.
 *
 * @return array<string, mixed>
 */
function anex_fetch_excursion_offer_by_key( string $tour_key ): array {
	$tour_key = trim( $tour_key );
	if ( $tour_key === '' || ! function_exists( 'ittour_lab_api_fetch' ) ) {
		return [];
	}
	$win = [
		'date_from' => wp_date( 'd.m.y', strtotime( '+21 days' ) ),
		'date_till' => wp_date( 'd.m.y', strtotime( '+32 days' ) ),
	];
	foreach ( anex_tour_sync_country_ids() as $country_id ) {
		$result = ittour_lab_api_fetch(
			'module-excursion/search',
			array_merge(
				$win,
				[
					'country'        => (string) $country_id,
					'night_from'     => '2',
					'night_till'     => '21',
					'adult'          => '2',
					'child'          => '0',
					'items_per_page' => '60',
				]
			),
			'uk'
		);
		if ( is_wp_error( $result ) ) {
			continue;
		}
		$offers = $result['data']['offers'] ?? [];
		if ( ! is_array( $offers ) ) {
			continue;
		}
		foreach ( $offers as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			if ( (string) ( $offer['key'] ?? '' ) === $tour_key ) {
				return $offer;
			}
		}
	}
	return [];
}

/**
 * Опис, фото, країни з tour-excursion/info + завантаження в медіа.
 */
function anex_enrich_tour_post( int $post_id, string $tour_key = '', array $offer = [] ): void {
	$post_id  = (int) $post_id;
	$keys     = anex_tour_meta_keys();
	$tour_key = $tour_key !== '' ? $tour_key : (string) get_post_meta( $post_id, $keys['ittour_tour_key'], true );
	if ( $tour_key === '' ) {
		return;
	}

	if ( $offer === [] ) {
		$offer = anex_fetch_excursion_offer_by_key( $tour_key );
		if ( $offer !== [] ) {
			$prices = anex_tour_parse_prices_from_offer( $offer );
			update_post_meta( $post_id, $keys['prices_json'], anex_json_meta_encode( $prices['raw'] ) );
			update_post_meta( $post_id, $keys['price_uah'], (string) $prices['uah'] );
			update_post_meta( $post_id, $keys['price_per_night'], (string) $prices['per_night'] );
			update_post_meta( $post_id, $keys['price_label'], $prices['label'] );
			update_post_meta( $post_id, $keys['country_names'], anex_json_meta_encode( anex_sanitize_label_list( (array) ( $offer['country_names'] ?? [] ) ) ) );
			update_post_meta( $post_id, $keys['city_names'], anex_json_meta_encode( anex_sanitize_label_list( (array) ( $offer['city_names'] ?? [] ) ) ) );
		}
	}

	$date_from = wp_date( 'd.m.y', strtotime( '+21 days' ) );
	$date_till = wp_date( 'd.m.y', strtotime( '+32 days' ) );
	$detail    = [];
	if ( function_exists( 'ittour_lab_api_fetch' ) ) {
		$result = ittour_lab_api_fetch(
			'tour-excursion/info/' . rawurlencode( $tour_key ),
			[
				'date_from'    => $date_from,
				'date_till'    => $date_till,
				'limit_images' => '20',
			],
			'uk'
		);
		if ( ! is_wp_error( $result ) ) {
			$data = $result['data'] ?? $result;
			if ( is_array( $data ) && empty( $data['error'] ) ) {
				$detail = $data;
			}
		}
	}

	if ( $detail !== [] ) {
		$title = trim( (string) ( $detail['name'] ?? $detail['tour_name'] ?? '' ) );
		if ( $title !== '' ) {
			wp_update_post(
				[
					'ID'         => $post_id,
					'post_title' => $title,
				]
			);
		}

		$desc = (string) ( $detail['description'] ?? $detail['description_html'] ?? '' );
		if ( $desc !== '' ) {
			$content = wp_kses_post( $desc );
			wp_update_post(
				[
					'ID'           => $post_id,
					'post_content' => $content,
					'post_excerpt' => wp_trim_words( wp_strip_all_tags( $content ), 40 ),
				]
			);
		}

		$countries = [];
		foreach ( (array) ( $detail['countries'] ?? [] ) as $c ) {
			if ( is_array( $c ) && ! empty( $c['name'] ) ) {
				$countries[] = (string) $c['name'];
			}
		}
		if ( $countries !== [] ) {
			update_post_meta( $post_id, $keys['country_names'], anex_json_meta_encode( anex_sanitize_label_list( $countries ) ) );
		}

		$cities = [];
		foreach ( (array) ( $detail['cities'] ?? [] ) as $c ) {
			if ( is_array( $c ) && ! empty( $c['name'] ) ) {
				$cities[] = (string) $c['name'];
			}
		}
		if ( $cities !== [] ) {
			update_post_meta( $post_id, $keys['city_names'], anex_json_meta_encode( anex_sanitize_label_list( $cities ) ) );
		}
	}

	$url_map = $offer !== [] ? anex_extract_tour_gallery_url_map( $offer ) : [];
	if ( $detail !== [] && function_exists( 'anex_extract_hotel_gallery_url_map' ) ) {
		foreach ( anex_extract_hotel_gallery_url_map( $detail ) as $url => $is_main ) {
			$url_map[ $url ] = $is_main ? true : ( $url_map[ $url ] ?? false );
		}
		foreach ( (array) ( $detail['hikes'] ?? [] ) as $hike ) {
			if ( ! is_array( $hike ) || empty( $hike['image'] ) ) {
				continue;
			}
			$url = function_exists( 'anex_normalize_ittour_image_url_to_full' )
				? anex_normalize_ittour_image_url_to_full( (string) $hike['image'] )
				: (string) $hike['image'];
			if ( $url !== '' ) {
				$url_map[ $url ] = $url_map[ $url ] ?? false;
			}
		}
	}

	if ( $url_map !== [] && function_exists( 'anex_import_hotel_gallery' ) ) {
		anex_import_hotel_gallery( $post_id, $url_map, 20 );
		$first = (string) array_key_first( $url_map );
		if ( $first !== '' ) {
			update_post_meta( $post_id, $keys['thumb_url'], $first );
		}
	} elseif ( $offer !== [] ) {
		$thumb = anex_extract_tour_thumb_url( $offer );
		if ( $thumb !== '' && function_exists( 'anex_sideload_attachment_from_url' ) ) {
			update_post_meta( $post_id, $keys['thumb_url'], $thumb );
			$att_id = anex_sideload_attachment_from_url( $post_id, $thumb );
			if ( $att_id > 0 && ! has_post_thumbnail( $post_id ) ) {
				set_post_thumbnail( $post_id, $att_id );
			}
		}
	}
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

/** @deprecated Use anex_enrich_tour_post() */
function anex_import_tour_media( int $post_id, array $offer = [] ): void {
	$keys = anex_tour_meta_keys();
	$key  = (string) get_post_meta( $post_id, $keys['ittour_tour_key'], true );
	anex_enrich_tour_post( $post_id, $key, $offer );
}

add_action( 'add_meta_boxes', 'anex_tour_meta_boxes' );

function anex_tour_meta_boxes(): void {
	add_meta_box(
		'anex_tour_details',
		'Дані туру (IT-Tour)',
		'anex_tour_meta_box_render',
		ANEX_TOUR_POST_TYPE,
		'normal',
		'high'
	);
}

function anex_tour_meta_box_render( WP_Post $post ): void {
	$keys = anex_tour_meta_keys();
	echo '<table class="form-table"><tbody>';
	echo '<tr><th>Tour key</th><td><code>' . esc_html( (string) get_post_meta( $post->ID, $keys['ittour_tour_key'], true ) ) . '</code></td></tr>';
	echo '<tr><th>Країни</th><td>' . esc_html( implode( ', ', anex_tour_get_country_names( $post->ID ) ) ) . '</td></tr>';
	echo '<tr><th>Міста</th><td>' . esc_html( implode( ', ', anex_tour_get_city_names( $post->ID ) ) ) . '</td></tr>';
	echo '<tr><th>Ціна</th><td><strong>' . esc_html( (string) get_post_meta( $post->ID, $keys['price_label'], true ) ) . '</strong></td></tr>';
	echo '<tr><th>Тривалість</th><td>' . esc_html( (string) get_post_meta( $post->ID, $keys['duration'], true ) ) . ' н.</td></tr>';
	echo '<tr><th>Транспорт</th><td>' . esc_html( (string) get_post_meta( $post->ID, $keys['transport_type'], true ) ) . '</td></tr>';
	echo '<tr><th>Виїзд з</th><td>' . esc_html( (string) get_post_meta( $post->ID, $keys['from_city'], true ) ) . '</td></tr>';
	$gids = function_exists( 'anex_hotel_get_gallery_ids' ) ? anex_hotel_get_gallery_ids( $post->ID ) : [];
	echo '<tr><th>Фото в медіа</th><td><strong>' . count( $gids ) . '</strong>';
	if ( $gids !== [] && function_exists( 'anex_hotel_get_gallery_items' ) ) {
		echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">';
		foreach ( array_slice( anex_hotel_get_gallery_items( $post->ID ), 0, 12 ) as $item ) {
			echo '<img src="' . esc_url( $item['url'] ) . '" style="width:80px;height:60px;object-fit:cover;border-radius:4px" alt="" />';
		}
		echo '</div>';
	}
	echo '</td></tr>';
	echo '</tbody></table>';
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
					'country_names'  => anex_tour_get_country_names( $post_id ),
					'city_names'     => anex_tour_get_city_names( $post_id ),
					'price_label'    => (string) get_post_meta( $post_id, $keys['price_label'], true ),
					'price_uah'      => (int) get_post_meta( $post_id, $keys['price_uah'], true ),
					'price_per_night'=> (int) get_post_meta( $post_id, $keys['price_per_night'], true ),
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
