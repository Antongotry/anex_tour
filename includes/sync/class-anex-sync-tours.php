<?php
/**
 * Import excursions (tours) via module-excursion/search per country.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

require_once ANEX_PLUGIN_DIR . 'includes/sync/class-anex-sync-log-tours.php';

class Anex_Sync_Tours {

	private const SEARCH_PATH = 'module-excursion/search';

	private static function api_date_offset( int $days ): string {
		return wp_date( 'd.m.y', strtotime( '+' . $days . ' days' ) );
	}

	/**
	 * Вікна дат як у каталозі (module-excursion дає мало оферів — потрібно більше вікон).
	 *
	 * @return array{date_from:string, date_till:string}[]
	 */
	private static function search_date_windows( bool $extended = false ): array {
		$offsets = $extended
			? [ 14, 21, 28, 35, 42, 49, 56, 63, 70, 77, 84, 98, 119, 140 ]
			: [ 14, 21, 35, 63, 98 ];
		$windows = [];
		foreach ( $offsets as $offset ) {
			$windows[] = [
				'date_from' => self::api_date_offset( $offset ),
				'date_till' => self::api_date_offset( $offset + 14 ),
			];
		}
		return $windows;
	}

	/** @return array<int, string> */
	private static function extra_country_ids(): array {
		return [ 49, 420 ];
	}

	public static function country_names_map(): array {
		return [
			318 => 'Туреччина',
			338 => 'Єгипет',
			16  => 'Греція',
			372 => 'ОАЕ',
			434 => 'Чорногорія',
			39  => 'Болгарія',
			320 => 'Іспанія',
			376 => 'Албанія',
		];
	}

	public static function process_next_country(): array {
		$state = Anex_Tour_Sync_Log::get_state();
		if ( ( $state['status'] ?? '' ) !== 'running' ) {
			return $state;
		}

		$ids   = $state['country_ids'] ?? [];
		$index = (int) ( $state['country_index'] ?? 0 );
		if ( ! is_array( $ids ) || $index >= count( $ids ) ) {
			return self::finish_run( $state );
		}

		$country_id   = (int) $ids[ $index ];
		$names        = self::country_names_map();
		$country_name = $names[ $country_id ] ?? ( 'Країна #' . $country_id );

		$state['current_country'] = $country_name . ' (' . $country_id . ')';
		Anex_Tour_Sync_Log::patch( [ 'current_country' => $state['current_country'] ] );
		Anex_Tour_Sync_Log::append( 'Країна: ' . $state['current_country'] );

		$ctx    = [ 'api_calls' => 0, 'api_errors' => 0, 'failed' => false, 'last_error' => '' ];
		$offers = self::fetch_tours_for_country( $country_id, $ctx );

		$state = Anex_Tour_Sync_Log::get_state();
		$state['api_calls']  += (int) $ctx['api_calls'];
		$state['api_errors'] += (int) $ctx['api_errors'];

		if ( $ctx['failed'] ) {
			$state['status']     = 'failed';
			$state['last_error'] = (string) $ctx['last_error'];
			Anex_Tour_Sync_Log::save_state( $state );
			return $state;
		}

		$created_before = (int) ( $state['created'] ?? 0 );
		$updated_before = (int) ( $state['updated'] ?? 0 );
		$imported       = 0;

		foreach ( $offers as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			$result = anex_upsert_tour_from_offer( $offer );
			if ( (int) ( $result['post_id'] ?? 0 ) <= 0 ) {
				continue;
			}
			++$imported;
			if ( ! empty( $result['created'] ) ) {
				++$state['created'];
			} else {
				++$state['updated'];
			}
			// anex_upsert_tour_from_offer → anex_enrich_tour_post (опис, ціни, фото).
		}

		Anex_Tour_Sync_Log::append( '  Турів з API: ' . count( $offers ) . ', імпортовано: ' . $imported );

		$state['country_index'] = $index + 1;
		if ( $state['country_index'] >= count( $ids ) ) {
			return self::finish_run( $state );
		}

		Anex_Tour_Sync_Log::save_state( $state );
		return $state;
	}

	/**
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 * @return array<int, array<string, mixed>>
	 */
	private static function fetch_tours_for_country( int $country_id, array &$ctx ): array {
		if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
			$ctx['failed']     = true;
			$ctx['last_error'] = 'ittour_lab_api_fetch missing';
			return [];
		}

		$by_key = [];
		$queries = self::build_search_queries_for_country( $country_id, false );

		foreach ( $queries as $query ) {
			++$ctx['api_calls'];
			$result = ittour_lab_api_fetch( self::SEARCH_PATH, $query, 'uk' );
			if ( is_wp_error( $result ) ) {
				++$ctx['api_errors'];
				$ctx['last_error'] = $result->get_error_message();
				Anex_Tour_Sync_Log::append( '  search err: ' . $ctx['last_error'] );
				continue;
			}

			$data = $result['data'] ?? [];
			if ( ! is_array( $data ) || ! empty( $data['error'] ) ) {
				++$ctx['api_errors'];
				$msg = (string) ( $data['error_desc'] ?? $data['error'] ?? 'API error' );
				if ( str_contains( $msg, 'Hour limit' ) || str_contains( $msg, 'limit' ) ) {
					$ctx['failed']     = true;
					$ctx['last_error'] = $msg;
				}
				Anex_Tour_Sync_Log::append( '  API: ' . $msg );
				continue;
			}

			foreach ( (array) ( $data['offers'] ?? [] ) as $offer ) {
				if ( ! is_array( $offer ) ) {
					continue;
				}
				$key = trim( (string) ( $offer['key'] ?? '' ) );
				if ( $key === '' ) {
					continue;
				}
				if ( ! isset( $by_key[ $key ] ) ) {
					$by_key[ $key ] = $offer;
				}
			}
		}

		return array_values( $by_key );
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function build_search_queries_for_country( int $country_id, bool $extended = false ): array {
		$queries = [];
		$base    = [
			'night_from'     => '1',
			'night_till'     => '21',
			'adult'          => '2',
			'child'          => '0',
			'items_per_page' => '60',
			'page'           => '1',
		];

		foreach ( self::search_date_windows( $extended ) as $win ) {
			$queries[] = array_merge(
				$base,
				[
					'country'   => (string) $country_id,
					'date_from' => $win['date_from'],
					'date_till' => $win['date_till'],
				]
			);
		}

		return $queries;
	}

	/**
	 * Скільки унікальних турів віддає API зараз (діагностика, обмежений набір запитів).
	 */
	public static function count_discoverable_tours(): int {
		$by_key = [];
		$ctx    = [ 'api_calls' => 0, 'api_errors' => 0, 'failed' => false, 'last_error' => '' ];
		$ids    = array_values( array_unique( array_merge( anex_tour_sync_country_ids(), self::extra_country_ids() ) ) );
		foreach ( $ids as $country_id ) {
			foreach ( self::fetch_tours_for_country_extended( (int) $country_id, $ctx ) as $offer ) {
				if ( is_array( $offer ) && ! empty( $offer['key'] ) ) {
					$by_key[ (string) $offer['key'] ] = true;
				}
			}
			if ( $ctx['failed'] ) {
				break;
			}
		}
		return count( $by_key );
	}

	/**
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 * @return array<int, array<string, mixed>>
	 */
	private static function fetch_tours_for_country_extended( int $country_id, array &$ctx ): array {
		$by_key = [];
		foreach ( self::build_search_queries_for_country( $country_id, true ) as $query ) {
			++$ctx['api_calls'];
			$result = ittour_lab_api_fetch( self::SEARCH_PATH, $query, 'uk' );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$data = $result['data'] ?? [];
			if ( ! is_array( $data ) || ! empty( $data['error'] ) ) {
				continue;
			}
			foreach ( (array) ( $data['offers'] ?? [] ) as $offer ) {
				if ( is_array( $offer ) && ! empty( $offer['key'] ) ) {
					$by_key[ (string) $offer['key'] ] = $offer;
				}
			}
		}
		return array_values( $by_key );
	}

	private static function finish_run( array $state ): array {
		$state['status']          = 'done';
		$state['finished_at']     = current_time( 'mysql' );
		$state['current_country'] = '';
		Anex_Tour_Sync_Log::save_state( $state );
		Anex_Tour_Sync_Log::append(
			sprintf(
				'Готово. Створено: %d, оновлено: %d, API: %d',
				(int) ( $state['created'] ?? 0 ),
				(int) ( $state['updated'] ?? 0 ),
				(int) ( $state['api_calls'] ?? 0 )
			)
		);
		return $state;
	}

	public static function get_tour_stats(): array {
		$counts = wp_count_posts( ANEX_TOUR_POST_TYPE );
		$total  = (int) ( $counts->publish ?? 0 );

		$with_thumb = 0;
		$q          = new WP_Query(
			[
				'post_type'      => ANEX_TOUR_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					],
				],
			]
		);
		$with_thumb = (int) $q->found_posts;

		return [
			'total'        => $total,
			'with_featured' => $with_thumb,
			'pending_photos' => max( 0, $total - $with_thumb ),
		];
	}
}
