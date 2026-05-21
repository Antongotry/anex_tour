<?php
/**
 * Import hotels via module/params/destinations (not search-list).
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

class Anex_Sync_Hotels {

	private const DEST_PATH = 'module/params/destinations';
	private const PARAMS_PATH = 'module/params';
	private const MAX_REGION_QUERIES = 25;

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
			$state['status']      = 'completed';
			$state['finished_at'] = current_time( 'mysql' );
			Anex_Sync_Log::append( 'Завершено. Створено: ' . (int) $state['created'] . ', оновлено: ' . (int) $state['updated'] );
			Anex_Sync_Log::save_state( $state );
			return $state;
		}

		$country_id = (int) $ids[ $index ];
		$names      = self::country_names_map();
		$country_name = $names[ $country_id ] ?? ( 'Країна #' . $country_id );

		$state['current_country'] = $country_name . ' (' . $country_id . ')';
		Anex_Sync_Log::append( 'Країна: ' . $state['current_country'] );

		$hotels = self::fetch_hotels_for_country( $country_id, $country_name, $state );
		if ( ( $state['status'] ?? '' ) === 'failed' ) {
			Anex_Sync_Log::save_state( $state );
			return $state;
		}

		$seen = [];
		foreach ( $hotels as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$hid = (string) ( $row['id'] ?? $row['hotel_id'] ?? '' );
			if ( $hid === '' || isset( $seen[ $hid ] ) ) {
				continue;
			}
			$seen[ $hid ] = true;
			if ( empty( $row['country_id'] ) ) {
				$row['country_id'] = (string) $country_id;
			}
			if ( empty( $row['country_name'] ) ) {
				$row['country_name'] = $country_name;
			}
			$result = anex_upsert_hotel_from_api_row( $row );
			if ( $result['post_id'] > 0 ) {
				if ( $result['created'] ) {
					++$state['created'];
				} else {
					++$state['updated'];
				}
			}
		}

		Anex_Sync_Log::append( '  Унікальних готелів: ' . count( $seen ) );

		$state['country_index'] = $index + 1;
		if ( $state['country_index'] >= count( $ids ) ) {
			$state['status']      = 'completed';
			$state['finished_at'] = current_time( 'mysql' );
			Anex_Sync_Log::append( 'Усі країни оброблено.' );
		}

		Anex_Sync_Log::save_state( $state );
		return $state;
	}

	/**
	 * @param array<string, mixed> $state Passed by reference semantics via return merge — mutated in caller.
	 * @return array<int, array<string, mixed>>
	 */
	private static function fetch_hotels_for_country( int $country_id, string $country_name, array &$state ): array {
		$all     = [];
		$regions = [];

		$payload = self::destinations_query( $country_name, $state );
		if ( $payload === null ) {
			return [];
		}

		foreach ( $payload['hotels'] ?? [] as $h ) {
			if ( is_array( $h ) ) {
				$all[] = $h;
			}
		}
		foreach ( $payload['regions'] ?? [] as $r ) {
			if ( is_array( $r ) && (int) ( $r['country_id'] ?? 0 ) === $country_id ) {
				$regions[] = $r;
			}
		}

		$queries_done = 1;
		foreach ( array_slice( $regions, 0, self::MAX_REGION_QUERIES ) as $region ) {
			$rname = trim( (string) ( $region['name'] ?? '' ) );
			if ( mb_strlen( $rname ) < 3 ) {
				continue;
			}
			$rp = self::destinations_query( $rname, $state );
			if ( $rp === null ) {
				return $all;
			}
			foreach ( $rp['hotels'] ?? [] as $h ) {
				if ( is_array( $h ) ) {
					$all[] = $h;
				}
			}
			++$queries_done;
		}

		Anex_Sync_Log::append( '  Запитів destinations: ' . $queries_done );
		return $all;
	}

	/**
	 * @return array{countries:array,regions:array,hotels:array}|null
	 */
	private static function destinations_query( string $query, array &$state ): ?array {
		$q = trim( $query );
		if ( mb_strlen( $q ) < 3 ) {
			return [ 'countries' => [], 'regions' => [], 'hotels' => [] ];
		}

		if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
			$state['status']     = 'failed';
			$state['last_error'] = 'ittour_lab_api_fetch недоступний';
			++$state['api_errors'];
			Anex_Sync_Log::append( 'Помилка: API не підключено' );
			return null;
		}

		++$state['api_calls'];
		$result = ittour_lab_api_fetch( self::DEST_PATH, [ 'type' => '1', 'query' => $q ], 'uk' );

		if ( is_wp_error( $result ) ) {
			++$state['api_errors'];
			$state['status']     = 'failed';
			$state['last_error'] = $result->get_error_message();
			Anex_Sync_Log::append( 'API HTTP: ' . $state['last_error'] );
			return null;
		}

		$data = $result['data'] ?? [];
		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$api_error = '';
		if ( ! empty( $data['error'] ) ) {
			$api_error = (string) ( $data['error_desc'] ?? $data['error'] ?? 'API error' );
		}
		if ( $api_error !== '' ) {
			++$state['api_errors'];
			$state['status']     = 'failed';
			$state['last_error'] = $api_error;
			Anex_Sync_Log::append( 'API: ' . $api_error );
			if ( stripos( $api_error, 'ліміт' ) !== false || stripos( $api_error, 'limit' ) !== false ) {
				Anex_Sync_Log::append( 'Зупинено: можливо вичерпано ліміт API. Спробуйте пізніше.' );
			}
			return null;
		}

		return [
			'countries' => is_array( $data['countries'] ?? null ) ? $data['countries'] : [],
			'regions'   => is_array( $data['regions'] ?? null ) ? $data['regions'] : [],
			'hotels'    => is_array( $data['hotels'] ?? null ) ? $data['hotels'] : [],
		];
	}

	/**
	 * @return array<int, string> country_id => name
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
	 * Sideload featured images for up to $limit hotels missing thumbnail.
	 *
	 * @return array{processed:int, errors:int, message:string}
	 */
	public static function sync_photos_batch( int $limit = 5 ): array {
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
				],
				'fields'         => 'ids',
			]
		);

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$processed = 0;
		$errors    = 0;

		foreach ( $q->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( has_post_thumbnail( $post_id ) ) {
				continue;
			}
			$url = (string) get_post_meta( $post_id, $keys['thumb_url'], true );
			if ( $url === '' ) {
				continue;
			}
			$att_id = media_sideload_image( $url, $post_id, null, 'id' );
			if ( is_wp_error( $att_id ) ) {
				++$errors;
				update_post_meta( $post_id, $keys['sync_error'], $att_id->get_error_message() );
				continue;
			}
			set_post_thumbnail( $post_id, (int) $att_id );
			++$processed;
			delete_post_meta( $post_id, $keys['sync_error'] );
		}

		return [
			'processed' => $processed,
			'errors'    => $errors,
			'message'   => sprintf( 'Завантажено фото: %d, помилок: %d', $processed, $errors ),
		];
	}
}
