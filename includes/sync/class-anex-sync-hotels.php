<?php
/**
 * Import hotels: module/params (regions) + destinations + search-list fallback.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

class Anex_Sync_Hotels {

	private const DEST_PATH       = 'module/params/destinations';
	private const PARAMS_PATH     = 'module/params';
	private const SEARCH_PATH     = 'module/search-list';
	private const MAX_REGION_DEST = 30;

	/** IT-Tour search-list: date_from / date_till у форматі DD.MM.YY */
	private static function api_date_offset( int $days_from_now ): string {
		return wp_date( 'd.m.y', strtotime( '+' . $days_from_now . ' days' ) );
	}

	/** Вікно пошуку: API дозволяє не більше 12 днів між date_from і date_till (як у каталозі +11). */
	private static function search_list_date_window(): array {
		$from_offset = 21;
		return [
			'date_from' => self::api_date_offset( $from_offset ),
			'date_till' => self::api_date_offset( $from_offset + 11 ),
		];
	}

	/**
	 * Process one country from queue; returns updated state.
	 */
	public static function process_next_country(): array {
		$state = Anex_Sync_Log::get_state();
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
		Anex_Sync_Log::patch( [ 'current_country' => $state['current_country'] ] );
		Anex_Sync_Log::append( 'Країна: ' . $state['current_country'] );

		$ctx    = [ 'api_calls' => 0, 'api_errors' => 0, 'failed' => false, 'last_error' => '' ];
		$hotels = self::collect_hotels_for_country( $country_id, $country_name, $ctx );

		$state = Anex_Sync_Log::get_state();
		$state['api_calls']  += (int) $ctx['api_calls'];
		$state['api_errors'] += (int) $ctx['api_errors'];

		if ( $ctx['failed'] ) {
			$state['status']     = 'failed';
			$state['last_error'] = (string) $ctx['last_error'];
			Anex_Sync_Log::save_state( $state );
			return $state;
		}

		$before_created = (int) ( $state['created'] ?? 0 );
		$before_updated = (int) ( $state['updated'] ?? 0 );
		$seen           = [];
		$skipped        = 0;
		$post_ids       = [];
		foreach ( $hotels as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$hid = self::normalize_hotel_id( $row );
			if ( $hid === '' || isset( $seen[ $hid ] ) ) {
				if ( $hid === '' ) {
					++$skipped;
				}
				continue;
			}
			$seen[ $hid ] = true;
			if ( empty( $row['country_id'] ) ) {
				$row['country_id'] = (string) $country_id;
			}
			if ( empty( $row['country_name'] ) ) {
				$row['country_name'] = $country_name;
			}
			$row['id']       = $hid;
			$row['hotel_id'] = $hid;

			$result = anex_upsert_hotel_from_api_row( $row );
			if ( $result['post_id'] > 0 ) {
				$post_ids[] = (int) $result['post_id'];
				if ( $result['created'] ) {
					++$state['created'];
				} else {
					++$state['updated'];
				}
			} else {
				++$skipped;
			}
		}

		if ( anex_hotel_sync_photos_enabled() && $post_ids !== [] ) {
			$photo_stats = self::sideload_photos_for_ids( $post_ids, 15 );
			Anex_Sync_Log::append(
				sprintf(
					'  Фото (крок): +%d, помилок: %d, без прев’ю в WP: %d',
					(int) $photo_stats['processed'],
					(int) $photo_stats['errors'],
					(int) $photo_stats['still_pending']
				)
			);
			$state['photos_done'] = (int) ( $state['photos_done'] ?? 0 ) + (int) $photo_stats['processed'];
		}

		Anex_Sync_Log::append(
			sprintf(
				'  Готелів з API: %d, унікальних: %d, створено: +%d, оновлено: +%d, пропущено: %d',
				count( $hotels ),
				count( $seen ),
				(int) $state['created'] - $before_created,
				(int) $state['updated'] - $before_updated,
				$skipped
			)
		);

		$state['country_index'] = $index + 1;
		if ( $state['country_index'] >= count( $ids ) ) {
			return self::finish_run( $state );
		}

		Anex_Sync_Log::save_state( $state );
		return $state;
	}

	/**
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_hotels_for_country( int $country_id, string $country_name, array &$ctx ): array {
		$by_id = [];

		$regions = self::fetch_regions_for_country( $country_id, $ctx );
		Anex_Sync_Log::append( '  Регіонів у params: ' . count( $regions ) );

		$dest_queries = 0;
		foreach ( array_slice( $regions, 0, self::MAX_REGION_DEST ) as $region ) {
			$rname = trim( (string) ( $region['name'] ?? '' ) );
			if ( mb_strlen( $rname ) < 3 ) {
				continue;
			}
			$payload = self::destinations_query( $rname, $ctx );
			if ( $payload === null ) {
				break;
			}
			++$dest_queries;
			self::merge_destination_hotels( $by_id, $payload['hotels'] ?? [], $country_id, $country_name );
		}

		if ( mb_strlen( trim( $country_name ) ) >= 3 ) {
			$payload = self::destinations_query( trim( $country_name ), $ctx );
			if ( $payload !== null ) {
				++$dest_queries;
				self::merge_destination_hotels( $by_id, $payload['hotels'] ?? [], $country_id, $country_name );
			}
		}

		Anex_Sync_Log::append( '  Запитів destinations: ' . $dest_queries . ', знайдено готелів: ' . count( $by_id ) );

		if ( count( $by_id ) === 0 ) {
			Anex_Sync_Log::append( '  Fallback: 1× module/search-list (ліміт «Пошук турів»)' );
			$from_search = self::fetch_hotels_via_search_list( $country_id, $country_name, $ctx );
			foreach ( $from_search as $hid => $row ) {
				$by_id[ $hid ] = $row;
			}
			Anex_Sync_Log::append( '  Після search-list: ' . count( $by_id ) . ' готелів' );
		}

		return array_values( $by_id );
	}

	/**
	 * @param array<int, array<string, mixed>> $by_id
	 * @param array<int, mixed>                $hotels
	 */
	private static function merge_destination_hotels( array &$by_id, array $hotels, int $country_id, string $country_name ): void {
		foreach ( $hotels as $h ) {
			if ( ! is_array( $h ) ) {
				continue;
			}
			$hid = self::normalize_hotel_id( $h );
			if ( $hid === '' ) {
				continue;
			}
			if ( ! isset( $by_id[ $hid ] ) ) {
				$h['id']       = $hid;
				$h['hotel_id'] = $hid;
				if ( empty( $h['country_id'] ) ) {
					$h['country_id'] = (string) $country_id;
				}
				if ( empty( $h['country_name'] ) ) {
					$h['country_name'] = $country_name;
				}
				$by_id[ $hid ] = $h;
			}
		}
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function normalize_hotel_id( array $row ): string {
		foreach ( [ 'id', 'hotel_id', 'hotel' ] as $key ) {
			if ( ! isset( $row[ $key ] ) ) {
				continue;
			}
			$val = trim( (string) $row[ $key ] );
			if ( $val !== '' && ctype_digit( $val ) ) {
				return $val;
			}
		}
		return '';
	}

	/**
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 * @return array<int, array<string, mixed>>
	 */
	private static function fetch_regions_for_country( int $country_id, array &$ctx ): array {
		if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
			$ctx['failed']     = true;
			$ctx['last_error'] = 'ittour_lab_api_fetch недоступний';
			return [];
		}

		++$ctx['api_calls'];
		$result = ittour_lab_api_fetch( self::PARAMS_PATH, [ 'country' => (string) $country_id ], 'uk' );
		if ( is_wp_error( $result ) ) {
			++$ctx['api_errors'];
			$ctx['failed']     = true;
			$ctx['last_error'] = $result->get_error_message();
			Anex_Sync_Log::append( '  params помилка: ' . $ctx['last_error'] );
			return [];
		}

		$data = $result['data'] ?? [];
		if ( ! is_array( $data ) ) {
			return [];
		}
		if ( self::api_payload_error( $data, $ctx ) ) {
			return [];
		}

		$list = $data['regions'] ?? [];
		if ( ! is_array( $list ) ) {
			return [];
		}

		$out = [];
		foreach ( $list as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$id = (string) ( $r['id'] ?? '' );
			if ( $id === '' ) {
				continue;
			}
			$cid = (int) ( $r['country_id'] ?? 0 );
			if ( $cid > 0 && $cid !== $country_id ) {
				continue;
			}
			$out[] = [
				'id'   => $id,
				'name' => (string) ( $r['name'] ?? '' ),
			];
		}
		return $out;
	}

	/**
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 * @return array<string, array<string, mixed>>
	 */
	private static function fetch_hotels_via_search_list( int $country_id, string $country_name, array &$ctx ): array {
		if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
			return [];
		}

		$dates = self::search_list_date_window();
		$query = [
			'type'           => '1',
			'kind'           => '1',
			'country'        => (string) $country_id,
			'adult_amount'   => '2',
			'child_amount'   => '0',
			'hotel_rating'   => '1:78',
			'night_from'     => '7',
			'night_till'     => '14',
			'date_from'      => $dates['date_from'],
			'date_till'      => $dates['date_till'],
			'items_per_page' => '120',
			'hotel_info'     => '1',
			'currency'       => '2',
		];

		++$ctx['api_calls'];
		$result = ittour_lab_api_fetch( self::SEARCH_PATH, $query, 'uk' );
		if ( is_wp_error( $result ) ) {
			++$ctx['api_errors'];
			$ctx['failed']     = true;
			$ctx['last_error'] = $result->get_error_message();
			Anex_Sync_Log::append( '  search-list HTTP: ' . $ctx['last_error'] );
			return [];
		}

		$data = $result['data'] ?? [];
		if ( ! is_array( $data ) ) {
			return [];
		}
		if ( self::api_payload_error( $data, $ctx ) ) {
			return [];
		}

		$offers = $data['offers'] ?? [];
		if ( ! is_array( $offers ) ) {
			return [];
		}

		$by_id = [];
		foreach ( $offers as $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			$hid = self::normalize_hotel_id( $offer );
			if ( $hid === '' ) {
				continue;
			}
			if ( isset( $by_id[ $hid ] ) ) {
				continue;
			}
			$by_id[ $hid ] = [
				'id'              => $hid,
				'hotel_id'        => $hid,
				'name'            => (string) ( $offer['hotel_name'] ?? $offer['hotel'] ?? '' ),
				'country_id'      => (string) ( $offer['country_id'] ?? $country_id ),
				'country_name'    => (string) ( $offer['country_name'] ?? $country_name ),
				'region_id'       => (string) ( $offer['region_id'] ?? '' ),
				'region_name'     => (string) ( $offer['region_name'] ?? $offer['region'] ?? '' ),
				'hotel_rating'    => (string) ( $offer['hotel_rating'] ?? $offer['hotel_stars'] ?? '' ),
				'lat'             => $offer['lat'] ?? $offer['latitude'] ?? '',
				'lng'             => $offer['lng'] ?? $offer['longitude'] ?? '',
				'hotel_images'    => $offer['hotel_images'] ?? [],
				'image'           => is_array( $offer['hotel_images'] ?? null ) && ! empty( $offer['hotel_images'][0] )
					? ( is_string( $offer['hotel_images'][0] ) ? $offer['hotel_images'][0] : ( $offer['hotel_images'][0]['url'] ?? '' ) )
					: '',
			];
		}
		return $by_id;
	}

	/**
	 * @param array<string, mixed>                                          $data
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 */
	private static function api_payload_error( array $data, array &$ctx ): bool {
		if ( empty( $data['error'] ) ) {
			return false;
		}
		$msg               = (string) ( $data['error_desc'] ?? $data['error'] ?? 'API error' );
		++$ctx['api_errors'];
		$ctx['failed']     = true;
		$ctx['last_error'] = $msg;
		Anex_Sync_Log::append( '  API: ' . $msg );
		return true;
	}

	/**
	 * @param array{api_calls:int,api_errors:int,failed:bool,last_error:string} $ctx
	 * @return array{countries:array,regions:array,hotels:array}|null
	 */
	private static function destinations_query( string $query, array &$ctx ): ?array {
		$q = trim( $query );
		if ( mb_strlen( $q ) < 3 ) {
			return [ 'countries' => [], 'regions' => [], 'hotels' => [] ];
		}

		if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
			$ctx['failed']     = true;
			$ctx['last_error'] = 'ittour_lab_api_fetch недоступний';
			return null;
		}

		++$ctx['api_calls'];
		$result = ittour_lab_api_fetch( self::DEST_PATH, [ 'type' => '1', 'query' => $q ], 'uk' );

		if ( is_wp_error( $result ) ) {
			++$ctx['api_errors'];
			$ctx['failed']     = true;
			$ctx['last_error'] = $result->get_error_message();
			Anex_Sync_Log::append( '  destinations HTTP: ' . $ctx['last_error'] );
			return null;
		}

		$data = $result['data'] ?? [];
		if ( ! is_array( $data ) ) {
			$data = [];
		}
		if ( self::api_payload_error( $data, $ctx ) ) {
			return null;
		}

		return [
			'countries' => is_array( $data['countries'] ?? null ) ? $data['countries'] : [],
			'regions'   => is_array( $data['regions'] ?? null ) ? $data['regions'] : [],
			'hotels'    => is_array( $data['hotels'] ?? null ) ? $data['hotels'] : [],
		];
	}

	/**
	 * @return array<int, string>
	 */
	private static function country_names_map(): array {
		static $cache = null;
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$cache = [];
		if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
			return $cache;
		}
		$result = ittour_lab_api_fetch( self::PARAMS_PATH, [], 'uk' );
		if ( is_wp_error( $result ) ) {
			return $cache;
		}
		$data = $result['data'] ?? [];
		if ( ! is_array( $data ) ) {
			return $cache;
		}
		$list = $data['countries'] ?? [];
		if ( ! is_array( $list ) ) {
			return $cache;
		}
		foreach ( $list as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$id = (int) ( $c['id'] ?? 0 );
			if ( $id > 0 ) {
				$cache[ $id ] = (string) ( $c['name'] ?? '' );
			}
		}
		return $cache;
	}

	/**
	 * @param array<string, mixed> $state
	 * @return array<string, mixed>
	 */
	private static function finish_run( array $state ): array {
		$state['status']      = 'completed';
		$state['finished_at'] = current_time( 'mysql' );
		$stats                = self::get_hotel_stats();
		Anex_Sync_Log::append(
			sprintf(
				'Завершено. Створено: %d, оновлено: %d. Всього готелів у WP: %d, з фото: %d, без фото: %d',
				(int) ( $state['created'] ?? 0 ),
				(int) ( $state['updated'] ?? 0 ),
				(int) $stats['total'],
				(int) $stats['with_featured'],
				(int) $stats['pending_photos']
			)
		);
		Anex_Sync_Log::save_state( $state );
		return Anex_Sync_Log::get_state();
	}

	/**
	 * @return array{total:int,with_thumb_url:int,with_featured:int,pending_photos:int,no_thumb_url:int}
	 */
	public static function get_hotel_stats(): array {
		$counts = wp_count_posts( ANEX_HOTEL_POST_TYPE );
		$total  = (int) ( $counts->publish ?? 0 );
		return [
			'total'           => $total,
			'with_thumb_url'  => self::count_hotels_meta_query( 'thumb_url' ),
			'with_featured'   => self::count_hotels_with_thumbnail(),
			'pending_photos'  => self::count_pending_photos(),
			'no_thumb_url'    => max( 0, $total - self::count_hotels_meta_query( 'thumb_url' ) ),
		];
	}

	private static function count_hotels_meta_query( string $meta_field ): int {
		$keys = anex_hotel_meta_keys();
		if ( ! isset( $keys[ $meta_field ] ) ) {
			return 0;
		}
		$q = new WP_Query(
			[
				'post_type'      => ANEX_HOTEL_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => $keys[ $meta_field ],
						'compare' => 'EXISTS',
					],
					[
						'key'     => $keys[ $meta_field ],
						'value'   => '',
						'compare' => '!=',
					],
				],
			]
		);
		return (int) $q->found_posts;
	}

	public static function count_pending_photos(): int {
		$keys = anex_hotel_meta_keys();
		$q    = new WP_Query(
			[
				'post_type'      => ANEX_HOTEL_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => $keys['thumb_url'],
						'compare' => 'EXISTS',
					],
					[
						'key'     => $keys['thumb_url'],
						'value'   => '',
						'compare' => '!=',
					],
					[
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);
		return (int) $q->found_posts;
	}

	private static function count_hotels_with_thumbnail(): int {
		$q = new WP_Query(
			[
				'post_type'      => ANEX_HOTEL_POST_TYPE,
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
		return (int) $q->found_posts;
	}

	/**
	 * @param int[] $post_ids
	 * @return array{processed:int, errors:int, still_pending:int}
	 */
	public static function sideload_photos_for_ids( array $post_ids, int $max = 15 ): array {
		$processed = 0;
		$errors    = 0;
		$n         = 0;
		foreach ( $post_ids as $post_id ) {
			if ( $n >= $max ) {
				break;
			}
			$post_id = (int) $post_id;
			if ( $post_id <= 0 || has_post_thumbnail( $post_id ) ) {
				continue;
			}
			++$n;
			if ( anex_sideload_hotel_thumbnail( $post_id ) ) {
				++$processed;
			} else {
				++$errors;
			}
		}
		return [
			'processed'     => $processed,
			'errors'        => $errors,
			'still_pending' => self::count_pending_photos(),
		];
	}

	/**
	 * @return array{processed:int, errors:int, remaining:int, message:string}
	 */
	public static function sync_photos_batch( int $limit = 15 ): array {
		$keys = anex_hotel_meta_keys();
		$q    = new WP_Query(
			[
				'post_type'      => ANEX_HOTEL_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => $keys['thumb_url'],
						'compare' => 'EXISTS',
					],
					[
						'key'     => $keys['thumb_url'],
						'value'   => '',
						'compare' => '!=',
					],
					[
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					],
				],
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$stats = self::sideload_photos_for_ids( array_map( 'intval', $q->posts ), $limit );
		$remaining = (int) $stats['still_pending'];

		return [
			'processed' => (int) $stats['processed'],
			'errors'    => (int) $stats['errors'],
			'remaining' => $remaining,
			'message'   => sprintf(
				'Завантажено: %d, помилок: %d. Залишилось без фото: %d',
				(int) $stats['processed'],
				(int) $stats['errors'],
				$remaining
			),
		];
	}
}
