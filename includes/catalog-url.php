<?php
/**
 * Єдине джерело правди для URL сторінки каталогу (пошук → редірект).
 *
 * @package Anex Tour
 */

defined( 'ABSPATH' ) || exit;

/**
 * Нормалізує значення з налаштувань: повний URL перетворює на шлях (slug / parent/child).
 */
function anex_normalize_settings_slug_input( string $raw ): string {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $raw ) ) {
		$parts = wp_parse_url( $raw );
		$path  = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		return trim( $path, '/' );
	}
	return trim( $raw, '/' );
}

/**
 * Знаходить опубліковану сторінку за ієрархічним slug (наприклад parent/hotel).
 */
function anex_resolve_published_page_url_by_path( string $slug ): ?string {
	$slug = trim( $slug, '/' );
	if ( '' === $slug ) {
		return null;
	}
	$page = get_page_by_path( $slug );
	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		return get_permalink( $page );
	}
	$maybe_id = url_to_postid( home_url( '/' . $slug . '/' ) );
	if ( $maybe_id > 0 ) {
		return get_permalink( $maybe_id );
	}
	$maybe_id2 = url_to_postid( site_url( '/' . $slug . '/' ) );
	if ( $maybe_id2 > 0 ) {
		return get_permalink( $maybe_id2 );
	}
	return null;
}

/**
 * Знаходить URL сторінки з шаблоном картки готелю (шорткод у контенті або в Elementor).
 * Якщо сторінок кілька — обирає ту, що має slug hotel, інакше не вгадує (null).
 */
function anex_discover_hotel_detail_template_page_url(): ?string {
	global $wpdb;

	$content_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s",
			'%[anex_hotel_detail_bootstrap%'
		)
	);
	// Elementor зберігає дані в postmeta — сам шорткод часто тільки там.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$elementor_ids = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_elementor_data'
		 WHERE p.post_status = 'publish' AND p.post_type = 'page'
		   AND (
				em.meta_value LIKE '%[anex_hotel_detail_bootstrap%'
				OR em.meta_value LIKE '%anex_hotel_detail_bootstrap%'
		   )"
	);

	$ids = array_unique(
		array_filter(
			array_map( 'intval', array_merge( (array) $content_ids, (array) $elementor_ids ) )
		)
	);

	if ( empty( $ids ) ) {
		return null;
	}
	if ( 1 === count( $ids ) ) {
		return get_permalink( $ids[0] );
	}
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( $p instanceof WP_Post && 'publish' === $p->post_status && 'hotel' === $p->post_name ) {
			return get_permalink( $p );
		}
	}
	return null;
}

/**
 * Повертає permalink сторінки, куди вести пошук з віджета [anex_search].
 *
 * @param array $atts Опційно: ['target' => 'https://.../katalog/'] — примусово.
 */
function anex_get_catalog_page_permalink( array $atts = [] ): string {
	if ( ! empty( $atts['target'] ) ) {
		return esc_url_raw( (string) $atts['target'] );
	}

	$try_slug = static function ( string $slug ): ?string {
		return anex_resolve_published_page_url_by_path( trim( $slug, '/' ) );
	};

	$opt = trim( (string) get_option( 'anex_slug_hotel_catalog', '' ), '/' );

	/*
	 * Головна проблема: у налаштуваннях часто залишається populyarni-goteli, а результати зібрані на /katalog/.
	 * Тому спочатку перевіряємо реальну сторінку «katalog» у WordPress — якщо вона є, редірект завжди туди
	 * (як при пошуку всередині каталогу через history.replaceState).
	 */
	$katalog_url = $try_slug( 'katalog' );
	if ( null !== $katalog_url ) {
		return $katalog_url;
	}

	/* Далі — slug з Anex Tour, якщо така сторінка існує. */
	if ( '' !== $opt ) {
		$opt_url = $try_slug( $opt );
		if ( null !== $opt_url ) {
			return $opt_url;
		}
	}

	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$catalog_pages = $wpdb->get_results(
		"SELECT DISTINCT p.ID, p.post_name, p.post_modified
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_elementor_data'
		 WHERE p.post_status = 'publish' AND p.post_type = 'page'
		   AND (
				p.post_content LIKE '%[anex_hotel_catalog%' OR
				p.post_content LIKE '%[anex_tour_results%' OR
				p.post_content LIKE '%[anex_tour_filters%' OR
				em.meta_value LIKE '%anex_hotel_catalog%' OR
				em.meta_value LIKE '%anex_tour_results%' OR
				em.meta_value LIKE '%anex_tour_filters%' OR
				em.meta_value LIKE '%anex_search%'
		   )
		 ORDER BY p.post_modified DESC"
	);

	if ( ! empty( $catalog_pages ) && is_array( $catalog_pages ) ) {
		foreach ( $catalog_pages as $row ) {
			if ( isset( $row->post_name ) && 'katalog' === $row->post_name ) {
				return get_permalink( (int) $row->ID );
			}
		}
		if ( '' !== $opt ) {
			foreach ( $catalog_pages as $row ) {
				if ( isset( $row->post_name ) && $opt === $row->post_name ) {
					return get_permalink( (int) $row->ID );
				}
			}
		}
		$first = $catalog_pages[0];
		return get_permalink( (int) $first->ID );
	}

	$candidates = array( 'katalog', 'populyarni-goteli' );
	if ( '' !== $opt && ! in_array( $opt, $candidates, true ) ) {
		array_unshift( $candidates, $opt );
	}

	foreach ( array_unique( $candidates ) as $slug ) {
		$url = $try_slug( $slug );
		if ( null !== $url ) {
			return $url;
		}
	}

	$patterns = array(
		'%[anex_hotel_catalog%',
		'%[anex_tour_results%',
		'%[anex_tour_filters%',
	);
	foreach ( $patterns as $like ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s ORDER BY post_modified DESC LIMIT 1",
				$like
			)
		);
		if ( $row && ! empty( $row->ID ) ) {
			return get_permalink( (int) $row->ID );
		}
	}

	$fallback = $opt ?: 'katalog';
	return home_url( '/' . $fallback . '/' );
}

/**
 * URL, на який ведуть кліки по готелю: окрема Elementor-сторінка з [anex_hotel_detail_*] або (якщо поле порожнє) сторінка каталогу.
 * Далі в рядок додаються ?tour_key=…&hotel_id=… — дані готелю одні й ті ж, міняється лише маршрут.
 */
function anex_get_hotel_detail_nav_base_url(): string {
	$slug = anex_normalize_settings_slug_input( (string) get_option( 'anex_slug_hotel_detail', '' ) );

	if ( '' !== $slug ) {
		$url = anex_resolve_published_page_url_by_path( $slug );
		if ( null !== $url ) {
			return $url;
		}
	}

	$discovered = anex_discover_hotel_detail_template_page_url();
	if ( null !== $discovered ) {
		return $discovered;
	}

	return anex_get_catalog_page_permalink( array() );
}

/**
 * Знаходить URL опублікованої сторінки екскурсійного пошуку.
 * Підтримує шорткоди в post_content та в Elementor (_elementor_data).
 */
function anex_discover_excursions_template_page_url(): ?string {
	global $wpdb;

	$likes = array(
		'%[anex_excursion_search%',
		'%[anex_excurs_search%',
	);

	$content_ids = array();
	foreach ( $likes as $like ) {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s",
				$like
			)
		);
		$content_ids = array_merge( $content_ids, (array) $ids );
	}

	$elementor_where = array();
	foreach ( $likes as $like ) {
		$elementor_where[] = $wpdb->prepare( 'em.meta_value LIKE %s', $like );
	}

	$elementor_sql = "SELECT p.ID FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_elementor_data'
		WHERE p.post_status = 'publish' AND p.post_type = 'page' AND (" . implode( ' OR ', $elementor_where ) . ')';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$elementor_ids = $wpdb->get_col( $elementor_sql );

	$ids = array_unique(
		array_filter(
			array_map( 'intval', array_merge( (array) $content_ids, (array) $elementor_ids ) )
		)
	);

	if ( empty( $ids ) ) {
		return null;
	}

	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( $p instanceof WP_Post && 'publish' === $p->post_status && in_array( $p->post_name, array( 'excurs', 'ekskursijni-tury', 'ekskursiyni-tury' ), true ) ) {
			return get_permalink( $p );
		}
	}

	return get_permalink( (int) $ids[0] );
}

/**
 * URL сторінки екскурсійного пошуку (автобусні/екскурсійні тури).
 *
 * Порядок:
 * 1) атрибут шорткоду target
 * 2) slug із опції anex_slug_excursions_catalog
 * 3) стандартний slug /excurs/
 */
function anex_get_excursions_page_permalink( array $atts = array() ): string {
	if ( ! empty( $atts['target'] ) ) {
		return esc_url_raw( (string) $atts['target'] );
	}

	$try_slug = static function ( string $slug ): ?string {
		return anex_resolve_published_page_url_by_path( trim( $slug, '/' ) );
	};

	$opt = trim( (string) get_option( 'anex_slug_excursions_catalog', '' ), '/' );
	if ( '' !== $opt ) {
		$opt_url = $try_slug( $opt );
		if ( null !== $opt_url ) {
			return $opt_url;
		}
	}

	$common_slugs = array( 'ekskursijni-tury', 'ekskursiyni-tury', 'excursions', 'excurs' );
	foreach ( $common_slugs as $slug ) {
		$url = $try_slug( $slug );
		if ( null !== $url ) {
			return $url;
		}
	}

	$discovered = anex_discover_excursions_template_page_url();
	if ( null !== $discovered ) {
		return $discovered;
	}

	return anex_get_catalog_page_permalink( array() );
}

/**
 * URL сторінки картки екскурсійного туру (Elementor + [anex_excursion_detail_*]).
 */
function anex_get_excursion_detail_nav_base_url(): string {
	$slug = anex_normalize_settings_slug_input( (string) get_option( 'anex_slug_excursion_detail', '' ) );
	if ( '' !== $slug ) {
		$url = anex_resolve_published_page_url_by_path( $slug );
		if ( null !== $url ) {
			return $url;
		}
	}

	global $wpdb;
	$content_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s",
			'%[anex_excursion_detail_bootstrap%'
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$elementor_ids = $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} em ON em.post_id = p.ID AND em.meta_key = '_elementor_data'
		 WHERE p.post_status = 'publish' AND p.post_type = 'page'
		   AND (em.meta_value LIKE '%[anex_excursion_detail_bootstrap%' OR em.meta_value LIKE '%anex_excursion_detail_bootstrap%')"
	);
	$ids = array_unique( array_filter( array_map( 'intval', array_merge( (array) $content_ids, (array) $elementor_ids ) ) ) );
	if ( ! empty( $ids ) ) {
		return get_permalink( (int) $ids[0] );
	}

	return anex_get_excursions_page_permalink( array() );
}

/**
 * Slugs сторінки каталогу без важкого пошуку (П.1 — зараз лише /katalog/).
 *
 * @return string[]
 */
function anex_get_katalog_landing_slugs(): array {
	return array_values(
		array_filter(
			array_unique(
				[
					'katalog',
				]
			)
		)
	);
}

/**
 * Чи це сторінка /katalog/ (Elementor + вкладки, без форми пошуку та search=1).
 */
function anex_is_katalog_landing_page(): bool {
	if ( is_admin() ) {
		return false;
	}
	if ( function_exists( 'is_page' ) && is_page( 'katalog' ) ) {
		return true;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	return in_array( $path, anex_get_katalog_landing_slugs(), true );
}

/**
 * Чи приховувати віджети пошуку / результатів на поточній сторінці.
 */
function anex_should_suppress_catalog_search_ui(): bool {
	return anex_is_katalog_landing_page();
}

