<?php
/**
 * CPT «Готелі» — картки готелів з IT-Tour (статичний шар).
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

const ANEX_HOTEL_POST_TYPE     = 'anex_hotel';
const ANEX_HOTEL_SYNC_OPTION   = 'anex_hotel_sync_state';
const ANEX_HOTEL_COUNTRIES_OPT = 'anex_hotel_sync_countries';

function anex_hotel_meta_keys(): array {
	return [
		'ittour_hotel_id' => '_anex_ittour_hotel_id',
		'country_id'      => '_anex_country_id',
		'country_name'    => '_anex_country_name',
		'region_id'       => '_anex_region_id',
		'region_name'     => '_anex_region_name',
		'hotel_rating'    => '_anex_hotel_rating',
		'lat'             => '_anex_lat',
		'lng'             => '_anex_lng',
		'thumb_url'       => '_anex_thumb_url',
		'synced_at'       => '_anex_synced_at',
		'sync_error'      => '_anex_sync_error',
	];
}

function anex_hotel_default_country_ids(): array {
	return [ 318, 338, 16, 372, 434, 39, 320, 376 ];
}

function anex_hotel_sync_country_ids(): array {
	$saved = get_option( ANEX_HOTEL_COUNTRIES_OPT, [] );
	if ( ! is_array( $saved ) || $saved === [] ) {
		return anex_hotel_default_country_ids();
	}
	$out = [];
	foreach ( $saved as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$out[] = $id;
		}
	}
	return $out !== [] ? array_values( array_unique( $out ) ) : anex_hotel_default_country_ids();
}

add_action( 'init', 'anex_register_hotel_cpt' );

function anex_register_hotel_cpt(): void {
	register_post_type(
		ANEX_HOTEL_POST_TYPE,
		[
			'labels'              => [
				'name'               => 'Готелі',
				'singular_name'      => 'Готель',
				'add_new'            => 'Додати готель',
				'add_new_item'       => 'Додати готель',
				'edit_item'          => 'Редагувати готель',
				'new_item'           => 'Новий готель',
				'view_item'          => 'Переглянути',
				'search_items'       => 'Шукати готелі',
				'not_found'          => 'Готелів не знайдено',
				'not_found_in_trash' => 'У кошику порожньо',
				'menu_name'          => 'Готелі',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'slug' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-building',
		]
	);
}

add_action( 'admin_menu', 'anex_hotel_admin_submenu', 20 );

function anex_hotel_admin_submenu(): void {
	add_submenu_page(
		'anex-tour',
		'Готелі',
		'Готелі',
		'manage_options',
		'edit.php?post_type=' . ANEX_HOTEL_POST_TYPE
	);
}

add_filter( 'manage_' . ANEX_HOTEL_POST_TYPE . '_posts_columns', 'anex_hotel_list_columns' );
add_action( 'manage_' . ANEX_HOTEL_POST_TYPE . '_posts_custom_column', 'anex_hotel_render_column', 10, 2 );

function anex_hotel_list_columns( array $columns ): array {
	$new = [];
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['anex_ittour_id'] = 'IT-Tour ID';
			$new['anex_location']  = 'Локація';
			$new['anex_rating']    = 'Зірки';
			$new['anex_thumb']     = 'Прев’ю';
			$new['anex_synced']    = 'Sync';
		}
	}
	return $new;
}

function anex_hotel_render_column( string $column, int $post_id ): void {
	$keys = anex_hotel_meta_keys();
	switch ( $column ) {
		case 'anex_ittour_id':
			echo esc_html( (string) get_post_meta( $post_id, $keys['ittour_hotel_id'], true ) );
			break;
		case 'anex_location':
			$country = (string) get_post_meta( $post_id, $keys['country_name'], true );
			$region  = (string) get_post_meta( $post_id, $keys['region_name'], true );
			echo esc_html( trim( $region . ( $region && $country ? ', ' : '' ) . $country ) ?: '—' );
			break;
		case 'anex_rating':
			echo esc_html( (string) get_post_meta( $post_id, $keys['hotel_rating'], true ) ?: '—' );
			break;
		case 'anex_thumb':
			$url = (string) get_post_meta( $post_id, $keys['thumb_url'], true );
			if ( $url !== '' ) {
				echo '<img src="' . esc_url( $url ) . '" alt="" style="max-width:48px;max-height:36px;object-fit:cover;border-radius:3px" />';
			} else {
				echo '—';
			}
			break;
		case 'anex_synced':
			$at  = (string) get_post_meta( $post_id, $keys['synced_at'], true );
			$err = (string) get_post_meta( $post_id, $keys['sync_error'], true );
			if ( $err !== '' ) {
				echo '<span style="color:#b32d2e" title="' . esc_attr( $err ) . '">!</span> ';
			}
			echo esc_html( $at !== '' ? $at : '—' );
			break;
	}
}

add_action( 'add_meta_boxes', 'anex_hotel_meta_boxes' );

function anex_hotel_meta_boxes(): void {
	add_meta_box(
		'anex_hotel_ittour',
		'IT-Tour',
		'anex_hotel_meta_box_render',
		ANEX_HOTEL_POST_TYPE,
		'normal',
		'high'
	);
}

function anex_hotel_meta_box_render( WP_Post $post ): void {
	wp_nonce_field( 'anex_hotel_save', 'anex_hotel_nonce' );
	$keys = anex_hotel_meta_keys();
	$fields = [
		'ittour_hotel_id' => 'Hotel ID (IT-Tour)',
		'country_id'      => 'Country ID',
		'country_name'    => 'Країна',
		'region_id'       => 'Region ID',
		'region_name'     => 'Регіон / курорт',
		'hotel_rating'    => 'Рейтинг / зірки',
		'lat'             => 'Lat',
		'lng'             => 'Lng',
		'thumb_url'       => 'URL прев’ю (з API)',
	];
	echo '<table class="form-table"><tbody>';
	foreach ( $fields as $key => $label ) {
		$meta_key = $keys[ $key ];
		$value    = (string) get_post_meta( $post->ID, $meta_key, true );
		echo '<tr><th><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="' . esc_attr( $key ) . '" name="anex_hotel[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" /></td></tr>';
	}
	$synced = (string) get_post_meta( $post->ID, $keys['synced_at'], true );
	$err    = (string) get_post_meta( $post->ID, $keys['sync_error'], true );
	echo '<tr><th>Останній sync</th><td>' . esc_html( $synced ?: '—' ) . '</td></tr>';
	if ( $err !== '' ) {
		echo '<tr><th>Помилка sync</th><td><code>' . esc_html( $err ) . '</code></td></tr>';
	}
	echo '</tbody></table>';
}

add_action( 'save_post_' . ANEX_HOTEL_POST_TYPE, 'anex_hotel_save_meta', 10, 2 );

function anex_hotel_save_meta( int $post_id, WP_Post $post ): void {
	if ( ! isset( $_POST['anex_hotel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['anex_hotel_nonce'] ) ), 'anex_hotel_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['anex_hotel'] ) || ! is_array( $_POST['anex_hotel'] ) ) {
		return;
	}
	$keys = anex_hotel_meta_keys();
	$raw  = wp_unslash( $_POST['anex_hotel'] );
	foreach ( $keys as $field => $meta_key ) {
		if ( in_array( $field, [ 'synced_at', 'sync_error' ], true ) ) {
			continue;
		}
		if ( ! isset( $raw[ $field ] ) ) {
			continue;
		}
		update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $raw[ $field ] ) );
	}
}

function anex_find_hotel_post_by_ittour_id( $ittour_id ): ?WP_Post {
	$ittour_id = (string) $ittour_id;
	if ( $ittour_id === '' || ! ctype_digit( $ittour_id ) ) {
		return null;
	}
	$keys = anex_hotel_meta_keys();
	$q    = new WP_Query(
		[
			'post_type'      => ANEX_HOTEL_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => $keys['ittour_hotel_id'],
					'value' => $ittour_id,
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

function anex_upsert_hotel_from_api_row( array $row ): array {
	$hotel_id = (string) ( $row['id'] ?? $row['hotel_id'] ?? '' );
	if ( $hotel_id === '' || ! ctype_digit( $hotel_id ) ) {
		return [ 'created' => false, 'post_id' => 0 ];
	}

	$name = trim( (string) ( $row['name'] ?? $row['hotel_name'] ?? '' ) );
	if ( $name === '' ) {
		$name = 'Готель #' . $hotel_id;
	}

	$existing = anex_find_hotel_post_by_ittour_id( $hotel_id );
	$created  = ! ( $existing instanceof WP_Post );

	$postarr = [
		'post_type'   => ANEX_HOTEL_POST_TYPE,
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
	$keys    = anex_hotel_meta_keys();
	$rating  = (string) ( $row['hotel_rating_name'] ?? $row['hotel_rating_id'] ?? $row['hotel_rating'] ?? '' );
	$thumb   = anex_extract_hotel_thumb_url( $row );

	update_post_meta( $post_id, $keys['ittour_hotel_id'], $hotel_id );
	update_post_meta( $post_id, $keys['country_id'], (string) ( $row['country_id'] ?? '' ) );
	update_post_meta( $post_id, $keys['country_name'], (string) ( $row['country_name'] ?? '' ) );
	update_post_meta( $post_id, $keys['region_id'], (string) ( $row['region_id'] ?? '' ) );
	update_post_meta( $post_id, $keys['region_name'], (string) ( $row['region_name'] ?? '' ) );
	update_post_meta( $post_id, $keys['hotel_rating'], $rating );
	update_post_meta( $post_id, $keys['lat'], (string) ( $row['lat'] ?? $row['latitude'] ?? '' ) );
	update_post_meta( $post_id, $keys['lng'], (string) ( $row['lng'] ?? $row['longitude'] ?? '' ) );
	if ( $thumb !== '' ) {
		update_post_meta( $post_id, $keys['thumb_url'], $thumb );
	}
	update_post_meta( $post_id, $keys['synced_at'], current_time( 'mysql' ) );
	delete_post_meta( $post_id, $keys['sync_error'] );

	return [ 'created' => $created, 'post_id' => $post_id ];
}

function anex_extract_hotel_thumb_url( array $hotel ): string {
	$direct = [ 'image', 'img', 'thumb', 'thumbnail', 'photo', 'photo_url', 'main_image', 'main_photo', 'picture', 'preview' ];
	foreach ( $direct as $key ) {
		if ( ! empty( $hotel[ $key ] ) && is_string( $hotel[ $key ] ) ) {
			$url = trim( $hotel[ $key ] );
			if ( $url !== '' ) {
				return esc_url_raw( $url );
			}
		}
	}
	foreach ( [ 'images', 'photos', 'gallery' ] as $list_key ) {
		if ( empty( $hotel[ $list_key ] ) || ! is_array( $hotel[ $list_key ] ) ) {
			continue;
		}
		$first = $hotel[ $list_key ][0] ?? null;
		if ( is_string( $first ) && trim( $first ) !== '' ) {
			return esc_url_raw( trim( $first ) );
		}
		if ( is_array( $first ) ) {
			foreach ( [ 'url', 'src', 'image', 'photo' ] as $k ) {
				if ( ! empty( $first[ $k ] ) && is_string( $first[ $k ] ) ) {
					return esc_url_raw( trim( $first[ $k ] ) );
				}
			}
		}
	}
	return '';
}
