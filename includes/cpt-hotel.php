<?php
/**
 * CPT «Готелі» — картки готелів з IT-Tour (статичний шар).
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

const ANEX_HOTEL_POST_TYPE      = 'anex_hotel';
const ANEX_HOTEL_SYNC_OPTION    = 'anex_hotel_sync_state';
const ANEX_HOTEL_COUNTRIES_OPT  = 'anex_hotel_sync_countries';
const ANEX_HOTEL_SYNC_PHOTOS_OPT = 'anex_hotel_sync_with_photos';

function anex_hotel_sync_photos_enabled(): bool {
	return (bool) get_option( ANEX_HOTEL_SYNC_PHOTOS_OPT, true );
}

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
		'photo_skip'      => '_anex_photo_skip',
		'gallery_ids'     => '_anex_gallery_ids',
		'gallery_urls'    => '_anex_gallery_urls',
	];
}

/** Максимум фото в галереї CPT з одного офера / search-list. */
function anex_hotel_gallery_max_images(): int {
	return (int) apply_filters( 'anex_hotel_gallery_max_images', 20 );
}

function anex_hotel_mark_photo_skip( int $post_id, string $reason ): void {
	$keys = anex_hotel_meta_keys();
	update_post_meta( $post_id, $keys['photo_skip'], '1' );
	update_post_meta( $post_id, $keys['sync_error'], mb_substr( $reason, 0, 500 ) );
}

function anex_hotel_clear_photo_skip( int $post_id ): void {
	$keys = anex_hotel_meta_keys();
	delete_post_meta( $post_id, $keys['photo_skip'] );
}

/** Скинути «пропуск» для повторної спроби завантаження фото. */
function anex_hotel_reset_all_photo_skips(): int {
	$posts = get_posts(
		[
			'post_type'      => ANEX_HOTEL_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => anex_hotel_meta_keys()['photo_skip'],
		]
	);
	foreach ( $posts as $post_id ) {
		anex_hotel_clear_photo_skip( (int) $post_id );
	}
	return count( $posts );
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

/**
 * На сторінці картки готелю (hotel_id у URL) — галерея з CPT для JS каталогу.
 */
add_action( 'wp_footer', 'anex_hotel_output_cpt_gallery_script', 5 );

function anex_hotel_output_cpt_gallery_script(): void {
	if ( is_admin() ) {
		return;
	}
	$hotel_id = isset( $_GET['hotel_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['hotel_id'] ) ) : '';
	if ( $hotel_id === '' || ! ctype_digit( $hotel_id ) ) {
		return;
	}
	$post = anex_find_hotel_post_by_ittour_id( $hotel_id );
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	$urls = [];
	foreach ( anex_hotel_get_gallery_items( $post->ID ) as $item ) {
		if ( ! empty( $item['url'] ) ) {
			$urls[] = $item['url'];
		}
	}
	$urls = array_values( array_unique( $urls ) );
	if ( $urls === [] ) {
		return;
	}
	echo '<script>window.anexCptHotelGallery=' . wp_json_encode( $urls, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . ';</script>' . "\n";
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
			$new['anex_gallery']   = 'Галерея';
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
		case 'anex_gallery':
			$gids = anex_hotel_get_gallery_ids( $post_id );
			echo esc_html( (string) count( $gids ) );
			if ( count( $gids ) > 0 ) {
				echo ' <span style="color:#666">(медіа)</span>';
			}
			break;
		case 'anex_thumb':
			$items = anex_hotel_get_gallery_items( $post_id );
			if ( $items === [] ) {
				$url = (string) get_post_meta( $post_id, $keys['thumb_url'], true );
				if ( $url !== '' ) {
					echo '<img src="' . esc_url( $url ) . '" alt="" style="width:48px;height:36px;object-fit:cover;border-radius:4px;border:1px solid #ddd" loading="lazy" />';
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;
			}
			echo '<div style="display:flex;gap:3px;flex-wrap:wrap;max-width:200px">';
			foreach ( array_slice( $items, 0, 4 ) as $item ) {
				$src = $item['thumb'] ?: $item['url'];
				$border = ! empty( $item['is_featured'] ) ? '2px solid #2271b1' : '1px solid #ddd';
				echo '<img src="' . esc_url( $src ) . '" alt="" style="width:44px;height:33px;object-fit:cover;border-radius:3px;border:' . esc_attr( $border ) . '" loading="lazy" />';
			}
			if ( count( $items ) > 4 ) {
				echo '<span style="font-size:11px;color:#666;align-self:center">+' . ( count( $items ) - 4 ) . '</span>';
			}
			echo '</div>';
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
		'anex_hotel_gallery',
		'Фото готелю',
		'anex_hotel_gallery_meta_box_render',
		ANEX_HOTEL_POST_TYPE,
		'side',
		'high'
	);
	add_meta_box(
		'anex_hotel_ittour',
		'IT-Tour',
		'anex_hotel_meta_box_render',
		ANEX_HOTEL_POST_TYPE,
		'normal',
		'high'
	);
}

function anex_hotel_gallery_meta_box_render( WP_Post $post ): void {
	$items    = anex_hotel_get_gallery_items( $post->ID );
	$featured = array_values( array_filter( $items, static fn( array $i ): bool => ! empty( $i['is_featured'] ) ) );
	$extra    = array_values( array_filter( $items, static fn( array $i ): bool => empty( $i['is_featured'] ) ) );
	$total    = count( $items );

	echo '<style>
		.anex-hotel-gallery-box .anex-hg-main{margin:0 0 12px}
		.anex-hotel-gallery-box .anex-hg-main img{width:100%;height:auto;border-radius:6px;border:2px solid #2271b1;display:block}
		.anex-hotel-gallery-box .anex-hg-label{font-weight:600;margin:0 0 6px;font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#50575e}
		.anex-hotel-gallery-box .anex-hg-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;max-height:320px;overflow-y:auto;padding:2px}
		.anex-hotel-gallery-box .anex-hg-grid a{display:block;border-radius:4px;overflow:hidden;border:1px solid #dcdcde}
		.anex-hotel-gallery-box .anex-hg-grid img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block}
		.anex-hotel-gallery-box .anex-hg-empty{color:#646970;font-size:12px;margin:0}
		.anex-hotel-gallery-box .anex-hg-count{margin-top:10px;font-size:12px;color:#50575e}
	</style>';
	echo '<div class="anex-hotel-gallery-box">';

	if ( $featured !== [] ) {
		$main = $featured[0];
		echo '<p class="anex-hg-label">Головне фото</p>';
		echo '<div class="anex-hg-main"><a href="' . esc_url( $main['url'] ) . '" target="_blank" rel="noopener">';
		echo '<img src="' . esc_url( $main['url'] ) . '" alt="" loading="lazy" /></a></div>';
	} else {
		echo '<p class="anex-hg-empty">Головне фото не задано. Встановіть «Зображення запису» або запустіть завантаження фото в sync.</p>';
	}

	echo '<p class="anex-hg-label">Додаткові фото (' . count( $extra ) . ')</p>';
	if ( $extra === [] ) {
		echo '<p class="anex-hg-empty">Немає додаткових. На сторінці <a href="' . esc_url( admin_url( 'admin.php?page=anex-hotel-sync' ) ) . '">Sync готелів</a> натисніть «Завантажити фото» або CLI галереї.</p>';
	} else {
		echo '<div class="anex-hg-grid">';
		foreach ( $extra as $item ) {
			echo '<a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener" title="Відкрити повний розмір">';
			echo '<img src="' . esc_url( $item['url'] ) . '" alt="" style="max-width:100%" loading="lazy" /></a>';
		}
		echo '</div>';
	}

	echo '<p class="anex-hg-count">Всього в медіа: <strong>' . (int) $total . '</strong> (1 головне + ' . count( $extra ) . ' додат.)</p>';
	echo '<p class="description" style="margin-top:8px">Редагувати порядок можна в медіатеці; повторний імпорт — через Sync / CLI.</p>';
	echo '</div>';
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
	$items = anex_hotel_get_gallery_items( $post->ID );
	echo '<tr><th>Фото</th><td><strong>' . count( $items ) . '</strong> у медіа ';
	echo '(<a href="#anex_hotel_gallery">див. блок «Фото готелю» справа</a>)</td></tr>';
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
	$hotel_id = '';
	foreach ( [ 'id', 'hotel_id', 'hotel' ] as $key ) {
		if ( empty( $row[ $key ] ) ) {
			continue;
		}
		$val = trim( (string) $row[ $key ] );
		if ( $val !== '' && ctype_digit( $val ) ) {
			$hotel_id = $val;
			break;
		}
	}
	if ( $hotel_id === '' ) {
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

/**
 * Завантажити прев’ю в медіатеку та встановити featured image.
 */
/**
 * Нормалізація URL з IT-Tour (як fixMediaUrl у каталозі).
 */
function anex_fix_media_url( string $value ): string {
	$url = trim( $value );
	if ( $url === '' ) {
		return '';
	}
	if ( str_starts_with( $url, '//' ) ) {
		$url = 'https:' . $url;
	}
	if ( str_starts_with( $url, 'http://' ) ) {
		$url = 'https://' . substr( $url, 7 );
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://www.ittour.com.ua/' . ltrim( $url, '/' );
	}
	return $url;
}

/**
 * IT-Tour thumb URL → full (file_name_small/1_320x240.jpg → file_name/1.jpg).
 */
function anex_normalize_ittour_image_url_to_full( string $url ): string {
	$url = anex_fix_media_url( $url );
	if ( $url === '' ) {
		return '';
	}
	if ( str_contains( $url, 'file_name_small' ) ) {
		$url = str_replace( '/file_name_small/', '/file_name/', $url );
		$url = preg_replace( '/_(\d+)x(\d+)(\.(jpe?g|png|gif|webp))$/i', '$3', $url );
	}
	return $url;
}

/**
 * Найкраща якість: full → web → thumb (не навпаки).
 *
 * @param array<string, mixed> $item
 */
function anex_pick_best_url_from_image_item( array $item ): string {
	foreach ( [ 'full', 'web', 'thumb', 'url', 'src', 'image', 'photo', 'large', 'big' ] as $k ) {
		if ( ! empty( $item[ $k ] ) && is_string( $item[ $k ] ) ) {
			$fixed = anex_normalize_ittour_image_url_to_full( $item[ $k ] );
			if ( $fixed !== '' ) {
				return $fixed;
			}
		}
	}
	return '';
}

/**
 * @param array<int|string, mixed> $list
 */
function anex_pick_image_url_from_list( array $list ): string {
	$main = null;
	foreach ( $list as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		if ( isset( $item['is_main'] ) && ( 1 === (int) $item['is_main'] || '1' === (string) $item['is_main'] ) ) {
			$main = $item;
			break;
		}
	}
	$im = $main ?? ( $list[0] ?? null );
	if ( ! is_array( $im ) ) {
		return '';
	}
	return anex_pick_best_url_from_image_item( $im );
}

/**
 * Усі URL фото з офера (для галереї). Ключ = URL, значення is_main.
 *
 * @return array<string, bool>
 */
function anex_extract_hotel_gallery_url_map( array $hotel ): array {
	$map = [];
	if ( ! empty( $hotel['hotel_info'] ) && is_array( $hotel['hotel_info'] ) ) {
		$map = array_merge( $map, anex_extract_hotel_gallery_url_map( $hotel['hotel_info'] ) );
	}

	$lists = [];
	foreach ( [ 'hotel_images', 'images', 'photos', 'gallery' ] as $key ) {
		if ( ! empty( $hotel[ $key ] ) && is_array( $hotel[ $key ] ) ) {
			$lists[] = $hotel[ $key ];
		}
	}
	$is_list = array_keys( $hotel ) === range( 0, count( $hotel ) - 1 );
	if ( $is_list && $hotel !== [] ) {
		$lists[] = $hotel;
	}

	foreach ( $lists as $list ) {
		foreach ( $list as $item ) {
			if ( is_string( $item ) && trim( $item ) !== '' ) {
				$url = anex_normalize_ittour_image_url_to_full( $item );
				if ( $url !== '' ) {
					$map[ $url ] = $map[ $url ] ?? false;
				}
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			$is_main = isset( $item['is_main'] ) && ( 1 === (int) $item['is_main'] || '1' === (string) $item['is_main'] );
			$url     = anex_pick_best_url_from_image_item( $item );
			if ( $url !== '' ) {
				if ( $is_main ) {
					$map[ $url ] = true;
				} else {
					$map[ $url ] = $map[ $url ] ?? false;
				}
			}
		}
	}

	// main спочатку.
	uasort(
		$map,
		static function ( bool $a, bool $b ): int {
			return (int) $b <=> (int) $a;
		}
	);

	return $map;
}

/**
 * @return int[]
 */
function anex_hotel_get_gallery_ids( int $post_id ): array {
	$raw = get_post_meta( $post_id, anex_hotel_meta_keys()['gallery_ids'], true );
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

/**
 * Дані для адмінки / фронту: головне + додаткові фото.
 *
 * @return array<int, array{id:int, url:string, thumb:string, is_featured:bool}>
 */
function anex_hotel_get_gallery_items( int $post_id ): array {
	$featured_id = (int) get_post_thumbnail_id( $post_id );
	$items       = [];
	$seen        = [];

	if ( $featured_id > 0 ) {
		$full  = (string) wp_get_attachment_image_url( $featured_id, 'full' );
		$thumb = (string) wp_get_attachment_image_url( $featured_id, 'medium' );
		if ( $full !== '' || $thumb !== '' ) {
			$items[]           = [
				'id'           => $featured_id,
				'url'          => $full !== '' ? $full : $thumb,
				'thumb'        => $thumb !== '' ? $thumb : $full,
				'is_featured'  => true,
			];
			$seen[ $featured_id ] = true;
		}
	}

	foreach ( anex_hotel_get_gallery_ids( $post_id ) as $att_id ) {
		if ( isset( $seen[ $att_id ] ) ) {
			continue;
		}
		$full  = (string) wp_get_attachment_image_url( $att_id, 'full' );
		$thumb = (string) wp_get_attachment_image_url( $att_id, 'medium' );
		if ( $full === '' && $thumb === '' ) {
			continue;
		}
		$items[] = [
			'id'          => $att_id,
			'url'         => $full !== '' ? $full : $thumb,
			'thumb'       => $thumb !== '' ? $thumb : $full,
			'is_featured' => false,
		];
		$seen[ $att_id ] = true;
	}

	return $items;
}

/**
 * URL додаткових фото (без головного).
 *
 * @return string[]
 */
function anex_hotel_get_extra_gallery_urls( int $post_id ): array {
	$featured_id = (int) get_post_thumbnail_id( $post_id );
	$urls        = [];
	foreach ( anex_hotel_get_gallery_items( $post_id ) as $item ) {
		if ( $featured_id > 0 && (int) $item['id'] === $featured_id ) {
			continue;
		}
		if ( ! empty( $item['url'] ) ) {
			$urls[] = $item['url'];
		}
	}
	return array_values( array_unique( $urls ) );
}

function anex_hotel_save_gallery_ids( int $post_id, array $ids ): void {
	$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
	update_post_meta( $post_id, anex_hotel_meta_keys()['gallery_ids'], wp_json_encode( $ids ) );
}

function anex_find_attachment_by_source_url( string $url ): int {
	global $wpdb;
	$url = trim( $url );
	if ( $url === '' ) {
		return 0;
	}
	$found = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_anex_source_url' AND meta_value = %s LIMIT 1",
			$url
		)
	);
	return $found > 0 ? $found : 0;
}

/**
 * @return int attachment ID або 0
 */
function anex_sideload_attachment_from_url( int $post_id, string $url ): int {
	$url = anex_normalize_ittour_image_url_to_full( trim( $url ) );
	if ( $url === '' ) {
		return 0;
	}

	$existing = anex_find_attachment_by_source_url( $url );
	if ( $existing > 0 ) {
		return $existing;
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = anex_download_image_temp_file( $url );
	if ( is_wp_error( $tmp ) ) {
		$att_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( is_wp_error( $att_id ) ) {
			return 0;
		}
		$att_id = (int) $att_id;
		update_post_meta( $att_id, '_anex_source_url', $url );
		return $att_id;
	}

	$path     = wp_parse_url( $url, PHP_URL_PATH );
	$basename = $path ? basename( (string) $path ) : '';
	if ( ! $basename || ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $basename ) ) {
		$basename = 'hotel-' . (int) get_post_meta( $post_id, anex_hotel_meta_keys()['ittour_hotel_id'], true ) . '-' . wp_generate_password( 4, false ) . '.jpg';
	}

	$file_array = [
		'name'     => sanitize_file_name( $basename ),
		'tmp_name' => $tmp,
	];
	$att_id     = media_handle_sideload( $file_array, $post_id );
	if ( file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}

	if ( is_wp_error( $att_id ) ) {
		return 0;
	}

	$att_id = (int) $att_id;
	update_post_meta( $att_id, '_anex_source_url', $url );
	return $att_id;
}

/**
 * Завантажити галерею в медіатеку (не лише featured).
 *
 * @param array<string, bool> $url_map URL => is_main
 * @return array{added:int, total:int, featured:int}
 */
function anex_import_hotel_gallery( int $post_id, array $url_map, int $max_images = 0 ): array {
	if ( $max_images <= 0 ) {
		$max_images = anex_hotel_gallery_max_images();
	}

	$keys       = anex_hotel_meta_keys();
	$gallery    = anex_hotel_get_gallery_ids( $post_id );
	$have_ids   = array_fill_keys( $gallery, true );
	$url_list   = array_keys( $url_map );
	$added      = 0;
	$featured   = (int) get_post_thumbnail_id( $post_id );
	$main_url   = '';

	foreach ( $url_map as $u => $is_main ) {
		if ( $is_main ) {
			$main_url = $u;
			break;
		}
	}

	update_post_meta( $post_id, $keys['gallery_urls'], wp_json_encode( $url_list ) );

	foreach ( array_slice( $url_list, 0, $max_images ) as $url ) {
		$url    = anex_normalize_ittour_image_url_to_full( $url );
		$att_id = anex_sideload_attachment_from_url( $post_id, $url );
		if ( $att_id <= 0 ) {
			continue;
		}
		if ( ! isset( $have_ids[ $att_id ] ) ) {
			$gallery[]            = $att_id;
			$have_ids[ $att_id ] = true;
			++$added;
		}
		if ( $featured <= 0 && ( $url_map[ $url ] ?? false ) ) {
			$featured = $att_id;
		}
	}

	if ( $featured <= 0 && ! empty( $gallery ) ) {
		$featured = (int) $gallery[0];
	}

	if ( $featured > 0 ) {
		set_post_thumbnail( $post_id, $featured );
		$thumb = $main_url !== '' ? $main_url : ( $url_list[0] ?? '' );
		if ( $thumb !== '' ) {
			update_post_meta( $post_id, $keys['thumb_url'], $thumb );
		}
	}

	anex_hotel_save_gallery_ids( $post_id, $gallery );

	return [
		'added'    => $added,
		'total'    => count( $gallery ),
		'featured' => $featured,
	];
}

/**
 * search-list по країні — сирі офери.
 *
 * @return array<int, array<string, mixed>>|WP_Error
 */
function anex_fetch_search_list_offers_for_country( string $country_id ) {
	$from_offset = 21;
	$result      = ittour_lab_api_fetch(
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
			'date_from'      => wp_date( 'd.m.y', strtotime( '+' . $from_offset . ' days' ) ),
			'date_till'      => wp_date( 'd.m.y', strtotime( '+' . ( $from_offset + 11 ) . ' days' ) ),
			'items_per_page' => '120',
			'hotel_info'     => '1',
			'hotel_image'    => '1',
			'currency'       => '2',
		],
		'uk'
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$data = $result['data'] ?? [];
	if ( ! is_array( $data ) || ! empty( $data['error'] ) ) {
		return new WP_Error(
			'anex_search_list',
			(string) ( $data['error_desc'] ?? $data['error'] ?? 'search-list error' )
		);
	}
	$offers = $data['offers'] ?? [];
	return is_array( $offers ) ? $offers : [];
}

/**
 * Перше фото з API hotel/{id}/hotel-images або hotel/info.
 */
function anex_fetch_hotel_thumb_from_api( string $hotel_id, string $country_id = '' ): string {
	$hotel_id = trim( $hotel_id );
	if ( $hotel_id === '' || ! ctype_digit( $hotel_id ) || ! function_exists( 'ittour_lab_api_fetch' ) ) {
		return '';
	}

	// hotel/{id}/hotel-images на цьому API часто «Неверный адрес» — фото з search-list.
	return anex_fetch_hotel_thumb_via_search_list( $hotel_id, $country_id );
}

/**
 * Один search-list по країні, офер з hotel_images (як у каталозі).
 */
function anex_fetch_hotel_thumb_via_search_list( string $hotel_id, string $country_id = '' ): string {
	$hotel_id = trim( $hotel_id );
	if ( $hotel_id === '' || ! ctype_digit( $hotel_id ) || ! function_exists( 'ittour_lab_api_fetch' ) ) {
		return '';
	}

	if ( $country_id === '' ) {
		$post = anex_find_hotel_post_by_ittour_id( $hotel_id );
		if ( $post instanceof WP_Post ) {
			$country_id = (string) get_post_meta( $post->ID, '_anex_country_id', true );
		}
	}
	if ( $country_id === '' ) {
		return '';
	}

	$offers = anex_fetch_search_list_offers_for_country( $country_id );
	if ( is_wp_error( $offers ) ) {
		return '';
	}
	foreach ( $offers as $offer ) {
		if ( ! is_array( $offer ) ) {
			continue;
		}
		$oid = (string) ( $offer['hotel_id'] ?? $offer['hotel'] ?? '' );
		if ( $oid !== $hotel_id ) {
			continue;
		}
		return anex_extract_hotel_thumb_url( $offer );
	}
	return '';
}

/**
 * URL прев’ю для запису готелю (meta або API).
 */
function anex_hotel_resolve_thumb_url( int $post_id ): string {
	$keys = anex_hotel_meta_keys();
	$url  = (string) get_post_meta( $post_id, $keys['thumb_url'], true );
	if ( $url !== '' ) {
		return $url;
	}
	$hotel_id   = (string) get_post_meta( $post_id, $keys['ittour_hotel_id'], true );
	$country_id = (string) get_post_meta( $post_id, $keys['country_id'], true );
	$url        = anex_fetch_hotel_thumb_from_api( $hotel_id, $country_id );
	if ( $url !== '' ) {
		update_post_meta( $post_id, $keys['thumb_url'], $url );
	}
	return $url;
}

/**
 * Повна галерея з tour/info/{key} (hotel_info.images, до 20 фото).
 *
 * @return array<string, bool>
 */
function anex_fetch_hotel_gallery_url_map_from_tour_info( string $offer_key ): array {
	$offer_key = trim( $offer_key );
	if ( $offer_key === '' || ! function_exists( 'ittour_lab_api_fetch' ) ) {
		return [];
	}

	$result = ittour_lab_api_fetch(
		'tour/info/' . $offer_key,
		[ 'limit_images' => (string) anex_hotel_gallery_max_images() ],
		'uk'
	);
	if ( is_wp_error( $result ) ) {
		return [];
	}
	$data = $result['data'] ?? $result;
	if ( ! is_array( $data ) || ! empty( $data['error'] ) ) {
		return [];
	}
	return anex_extract_hotel_gallery_url_map( $data );
}

/**
 * Усі hotel_images з search-list для готелю (об’єднання оферів) + tour/info.
 *
 * @return array<string, bool> URL => is_main
 */
function anex_fetch_hotel_gallery_url_map_from_search_list( string $hotel_id, string $country_id = '' ): array {
	$hotel_id = trim( $hotel_id );
	if ( $hotel_id === '' || ! ctype_digit( $hotel_id ) ) {
		return [];
	}
	if ( $country_id === '' ) {
		$post = anex_find_hotel_post_by_ittour_id( $hotel_id );
		if ( $post instanceof WP_Post ) {
			$country_id = (string) get_post_meta( $post->ID, '_anex_country_id', true );
		}
	}
	if ( $country_id === '' ) {
		return [];
	}

	$offers = anex_fetch_search_list_offers_for_country( $country_id );
	if ( is_wp_error( $offers ) ) {
		return [];
	}

	$map        = [];
	$offer_key  = '';
	foreach ( $offers as $offer ) {
		if ( ! is_array( $offer ) ) {
			continue;
		}
		$oid = (string) ( $offer['hotel_id'] ?? $offer['hotel'] ?? '' );
		if ( $oid !== $hotel_id ) {
			continue;
		}
		if ( $offer_key === '' && ! empty( $offer['key'] ) ) {
			$offer_key = (string) $offer['key'];
		}
		foreach ( anex_extract_hotel_gallery_url_map( $offer ) as $url => $is_main ) {
			if ( $is_main ) {
				$map[ $url ] = true;
			} else {
				$map[ $url ] = $map[ $url ] ?? false;
			}
		}
	}

	if ( $offer_key !== '' ) {
		foreach ( anex_fetch_hotel_gallery_url_map_from_tour_info( $offer_key ) as $url => $is_main ) {
			if ( $is_main ) {
				$map[ $url ] = true;
			} else {
				$map[ $url ] = $map[ $url ] ?? false;
			}
		}
	}

	return $map;
}

/**
 * @return array{added:int, total:int, featured:int}
 */
function anex_import_hotel_gallery_from_search_list( int $post_id ): array {
	$keys     = anex_hotel_meta_keys();
	$hotel_id = (string) get_post_meta( $post_id, $keys['ittour_hotel_id'], true );
	$country  = (string) get_post_meta( $post_id, $keys['country_id'], true );
	$map      = anex_fetch_hotel_gallery_url_map_from_search_list( $hotel_id, $country );
	if ( $map === [] ) {
		return [ 'added' => 0, 'total' => count( anex_hotel_get_gallery_ids( $post_id ) ), 'featured' => (int) get_post_thumbnail_id( $post_id ) ];
	}
	return anex_import_hotel_gallery( $post_id, $map );
}

/**
 * @return string attached|url_only|failed
 */
function anex_sideload_hotel_thumbnail( int $post_id, string $url = '' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		return 'attached';
	}
	$keys = anex_hotel_meta_keys();
	anex_hotel_clear_photo_skip( $post_id );

	if ( $url === '' ) {
		$url = anex_hotel_resolve_thumb_url( $post_id );
	}
	$url = anex_normalize_ittour_image_url_to_full( trim( $url ) );
	if ( $url === '' ) {
		anex_hotel_mark_photo_skip( $post_id, 'Немає URL (hotel-images + info)' );
		return 'failed';
	}

	update_post_meta( $post_id, $keys['thumb_url'], $url );

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = anex_download_image_temp_file( $url );
	if ( is_wp_error( $tmp ) ) {
		$att_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( ! is_wp_error( $att_id ) ) {
			set_post_thumbnail( $post_id, (int) $att_id );
			delete_post_meta( $post_id, $keys['sync_error'] );
			anex_import_hotel_gallery_from_search_list( $post_id );
			return 'attached';
		}
		update_post_meta( $post_id, $keys['sync_error'], 'URL є, медіа: ' . $tmp->get_error_message() );
		return 'url_only';
	}

	$path     = wp_parse_url( $url, PHP_URL_PATH );
	$basename = $path ? basename( (string) $path ) : '';
	if ( ! $basename || ! preg_match( '/\.(jpe?g|png|gif|webp)$/i', $basename ) ) {
		$basename = 'hotel-' . (int) get_post_meta( $post_id, $keys['ittour_hotel_id'], true ) . '.jpg';
	}

	$file_array = [
		'name'     => sanitize_file_name( $basename ),
		'tmp_name' => $tmp,
	];
	$att_id     = media_handle_sideload( $file_array, $post_id );
	if ( file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}

	if ( is_wp_error( $att_id ) ) {
		update_post_meta( $post_id, $keys['sync_error'], 'URL є, attach: ' . $att_id->get_error_message() );
		return 'url_only';
	}

	set_post_thumbnail( $post_id, (int) $att_id );
	delete_post_meta( $post_id, $keys['sync_error'] );
	anex_import_hotel_gallery_from_search_list( $post_id );
	return 'attached';
}

/**
 * @return string|WP_Error шлях до тимчасового файлу
 */
function anex_download_image_temp_file( string $url ) {
	$resp = wp_remote_get(
		$url,
		[
			'timeout'     => 45,
			'redirection' => 5,
			'headers'     => [
				'Referer'     => 'https://www.ittour.com.ua/',
				'Accept'      => 'image/*,*/*;q=0.8',
				'User-Agent'  => 'Mozilla/5.0 (compatible; AneXTour-WP/' . ( defined( 'ANEX_VERSION' ) ? ANEX_VERSION : '1' ) . ')',
			],
		]
	);

	if ( is_wp_error( $resp ) ) {
		return $resp;
	}

	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'anex_img_http', 'HTTP ' . $code );
	}

	$body = wp_remote_retrieve_body( $resp );
	if ( ! is_string( $body ) || strlen( $body ) < 200 ) {
		return new WP_Error( 'anex_img_empty', 'Порожня відповідь' );
	}

	$tmp = wp_tempnam( $url );
	if ( ! $tmp ) {
		return new WP_Error( 'anex_img_tmp', 'Не вдалося створити tmp' );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	if ( false === file_put_contents( $tmp, $body ) ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'anex_img_write', 'Не вдалося записати файл' );
	}

	return $tmp;
}

/**
 * Перезаписати файл вкладення з full-URL (якщо раніше зкачали thumb).
 */
function anex_replace_attachment_file_from_url( int $att_id, string $url ): bool {
	$att_id = (int) $att_id;
	$url    = anex_normalize_ittour_image_url_to_full( $url );
	if ( $att_id <= 0 || $url === '' ) {
		return false;
	}

	$tmp = anex_download_image_temp_file( $url );
	if ( is_wp_error( $tmp ) ) {
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$path     = wp_parse_url( $url, PHP_URL_PATH );
	$basename = $path ? basename( (string) $path ) : 'hotel.jpg';
	$basename = sanitize_file_name( $basename );

	$old_file = get_attached_file( $att_id );
	if ( ! $old_file ) {
		wp_delete_file( $tmp );
		return false;
	}

	$dir  = dirname( $old_file );
	$dest = trailingslashit( $dir ) . $basename;
	if ( $dest !== $old_file && file_exists( $dest ) ) {
		$dest = trailingslashit( $dir ) . wp_unique_filename( $dir, $basename );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
	if ( ! @rename( $tmp, $dest ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.copy_copy
		if ( ! @copy( $tmp, $dest ) ) {
			wp_delete_file( $tmp );
			return false;
		}
		wp_delete_file( $tmp );
	}

	if ( $dest !== $old_file ) {
		update_attached_file( $att_id, $dest );
		if ( file_exists( $old_file ) ) {
			wp_delete_file( $old_file );
		}
	}

	$meta = wp_generate_attachment_metadata( $att_id, $dest );
	if ( is_array( $meta ) ) {
		wp_update_attachment_metadata( $att_id, $meta );
	}
	update_post_meta( $att_id, '_anex_source_url', $url );

	return true;
}

/**
 * Чи збережено вкладення у низькій якості (thumb / малий файл).
 */
function anex_attachment_needs_quality_upgrade( int $att_id ): bool {
	$source = (string) get_post_meta( $att_id, '_anex_source_url', true );
	if ( $source !== '' && str_contains( $source, 'file_name_small' ) ) {
		return true;
	}
	$file = get_attached_file( $att_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return false;
	}
	$size = (int) filesize( $file );
	if ( $size > 0 && $size < 45000 ) {
		return true;
	}
	$meta = wp_get_attachment_metadata( $att_id );
	if ( is_array( $meta ) && isset( $meta['width'], $meta['height'] ) ) {
		if ( (int) $meta['width'] <= 400 || (int) $meta['height'] <= 300 ) {
			return true;
		}
	}
	return false;
}

function anex_extract_hotel_thumb_url( array $hotel ): string {
	if ( ! empty( $hotel['hotel_info'] ) && is_array( $hotel['hotel_info'] ) ) {
		$nested = anex_extract_hotel_thumb_url( $hotel['hotel_info'] );
		if ( $nested !== '' ) {
			return $nested;
		}
	}

	$direct = [ 'full', 'web', 'image', 'img', 'photo', 'photo_url', 'main_image', 'main_photo', 'picture', 'preview', 'thumb', 'thumbnail' ];
	foreach ( $direct as $key ) {
		if ( ! empty( $hotel[ $key ] ) && is_string( $hotel[ $key ] ) ) {
			$fixed = anex_fix_media_url( $hotel[ $key ] );
			if ( $fixed !== '' ) {
				return $fixed;
			}
		}
	}

	foreach ( [ 'hotel_images', 'images', 'photos', 'gallery' ] as $list_key ) {
		if ( empty( $hotel[ $list_key ] ) || ! is_array( $hotel[ $list_key ] ) ) {
			continue;
		}
		$picked = anex_pick_image_url_from_list( $hotel[ $list_key ] );
		if ( $picked !== '' ) {
			return $picked;
		}
	}

	// Відповідь API інколи — чистий список зображень.
	$is_list = array_keys( $hotel ) === range( 0, count( $hotel ) - 1 );
	if ( $is_list && $hotel !== [] ) {
		$picked = anex_pick_image_url_from_list( $hotel );
		if ( $picked !== '' ) {
			return $picked;
		}
	}

	$single = $hotel['images'] ?? null;
	if ( is_array( $single ) && ! isset( $single[0] ) ) {
		$picked = anex_pick_best_url_from_image_item( $single );
		if ( $picked !== '' ) {
			return $picked;
		}
	}

	return '';
}
