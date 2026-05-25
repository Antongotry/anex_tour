<?php
/**
 * Search V2 REST facade.
 *
 * Keeps the frontend away from raw IT-Tour payloads and exposes one stable card DTO.
 *
 * @package AnexTour
 */

defined( 'ABSPATH' ) || exit;

const ANEX_SEARCH_V2_CACHE_TTL       = 10 * MINUTE_IN_SECONDS;
const ANEX_SEARCH_V2_STALE_CACHE_TTL = 6 * HOUR_IN_SECONDS;
const ANEX_SEARCH_V2_SHADOW_LOG_LIMIT = 50;

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'anex/v1',
			'/search',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'anex_search_v2_rest_search',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'anex/v1',
			'/health',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'anex_search_v2_rest_health',
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'anex/v1',
			'/shadow-log',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'anex_search_v2_rest_shadow_log',
				'permission_callback' => static fn(): bool => current_user_can( 'manage_options' ),
			]
		);
	}
);

function anex_search_v2_rest_health(): WP_REST_Response {
	return rest_ensure_response(
		[
			'ok'       => true,
			'version'  => ANEX_VERSION,
			'token'    => function_exists( 'ittour_lab_get_token' ) && ittour_lab_get_token() !== '',
			'features' => [
				'search_v2' => true,
				'cache'     => 'transient',
			],
		]
	);
}

function anex_search_v2_rest_shadow_log(): WP_REST_Response {
	$log = get_option( 'anex_search_v2_shadow_log', [] );
	return rest_ensure_response(
		[
			'ok'      => true,
			'entries' => is_array( $log ) ? array_values( $log ) : [],
		]
	);
}

function anex_search_v2_rest_search( WP_REST_Request $request ): WP_REST_Response {
	$params = anex_search_v2_normalize_request( $request );
	$cache_key = anex_search_v2_cache_key( $params );
	$cached = get_transient( $cache_key );

	if ( is_array( $cached ) && isset( $cached['fresh_until'], $cached['payload'] ) ) {
		$payload = $cached['payload'];
		$payload['cache'] = [
			'hit'        => true,
			'stale'      => time() > (int) $cached['fresh_until'],
			'fresh_until'=> (int) $cached['fresh_until'],
		];
		$payload['meta']['origin_api_calls'] = (int) ( $payload['meta']['api_calls'] ?? 0 );
		$payload['meta']['api_calls'] = 0;
		if ( time() <= (int) $cached['fresh_until'] ) {
			anex_search_v2_maybe_write_shadow_log( $request, $params, $payload );
			return rest_ensure_response( $payload );
		}
	}

	$api_calls = 0;
	$errors = [];
	$cards = [];
	$source = 'api';

	$primary = anex_search_v2_fetch( 'module/search-list', anex_search_v2_query( $params ), $api_calls, $errors );
	if ( is_array( $primary ) ) {
		$cards = anex_search_v2_cards_from_offers( anex_search_v2_offers( $primary ), $params, 'api' );
	}

	if ( $cards === [] ) {
		$fallback = anex_search_v2_fetch( 'showcase/hot-offers/search', anex_search_v2_showcase_query( $params ), $api_calls, $errors );
		if ( is_array( $fallback ) ) {
			$cards = anex_search_v2_cards_from_offers( anex_search_v2_offers( $fallback ), $params, 'fallback' );
			if ( $cards !== [] ) {
				$source = 'fallback';
			}
		}
	}

	if ( $cards === [] && is_array( $cached['payload'] ?? null ) ) {
		$payload = $cached['payload'];
		$payload['status'] = 'stale';
		$payload['source'] = 'stale_cache';
		$payload['cache'] = [
			'hit'   => true,
			'stale' => true,
		];
		$payload['meta']['api_calls'] = $api_calls;
		$payload['meta']['errors'] = $errors;
		anex_search_v2_maybe_write_shadow_log( $request, $params, $payload );
		return rest_ensure_response( $payload );
	}

	$status = $cards === [] ? 'empty' : ( $source === 'fallback' ? 'fallback' : 'ok' );
	$payload = [
		'ok'       => true,
		'status'   => $status,
		'source'   => $source,
		'query'    => $params,
		'cards'    => $cards,
		'messages' => anex_search_v2_messages( $status, $errors ),
		'cache'    => [
			'hit'   => false,
			'stale' => false,
		],
		'meta'     => [
			'count'     => count( $cards ),
			'api_calls' => $api_calls,
			'errors'    => $errors,
		],
	];

	set_transient(
		$cache_key,
		[
			'fresh_until' => time() + ANEX_SEARCH_V2_CACHE_TTL,
			'payload'     => $payload,
		],
		ANEX_SEARCH_V2_STALE_CACHE_TTL
	);

	anex_search_v2_maybe_write_shadow_log( $request, $params, $payload );

	return rest_ensure_response( $payload );
}

function anex_search_v2_maybe_write_shadow_log( WP_REST_Request $request, array $params, array $payload ): void {
	if ( (string) $request->get_param( 'shadow' ) !== '1' || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$meta = is_array( $payload['meta'] ?? null ) ? $payload['meta'] : [];
	$cache = is_array( $payload['cache'] ?? null ) ? $payload['cache'] : [];
	$cards = is_array( $payload['cards'] ?? null ) ? $payload['cards'] : [];
	$errors = is_array( $meta['errors'] ?? null ) ? $meta['errors'] : [];
	$entry = [
		'ts'               => current_time( 'mysql' ),
		'status'           => (string) ( $payload['status'] ?? '' ),
		'source'           => (string) ( $payload['source'] ?? '' ),
		'count'            => (int) ( $meta['count'] ?? count( $cards ) ),
		'cache_hit'        => ! empty( $cache['hit'] ),
		'cache_stale'      => ! empty( $cache['stale'] ),
		'api_calls'        => (int) ( $meta['api_calls'] ?? 0 ),
		'origin_api_calls' => (int) ( $meta['origin_api_calls'] ?? 0 ),
		'errors'           => count( $errors ),
		'country'          => (string) ( $params['country'] ?? '' ),
		'from'             => (string) ( $params['from'] ?? '' ),
		'region'           => (string) ( $params['region'] ?? '' ),
		'hotel'            => (string) ( $params['hotel'] ?? '' ),
		'd1'               => (string) ( $params['d1'] ?? '' ),
		'd2'               => (string) ( $params['d2'] ?? '' ),
		'n1'               => (int) ( $params['n1'] ?? 0 ),
		'n2'               => (int) ( $params['n2'] ?? 0 ),
		'adults'           => (int) ( $params['adults'] ?? 0 ),
		'children'         => (int) ( $params['children'] ?? 0 ),
	];

	$log = get_option( 'anex_search_v2_shadow_log', [] );
	if ( ! is_array( $log ) ) {
		$log = [];
	}
	array_unshift( $log, $entry );
	update_option( 'anex_search_v2_shadow_log', array_slice( $log, 0, ANEX_SEARCH_V2_SHADOW_LOG_LIMIT ), false );
}

function anex_search_v2_normalize_request( WP_REST_Request $request ): array {
	$country = anex_search_v2_digits( $request->get_param( 'country_id' ) ?: $request->get_param( 'country' ) );
	$from = anex_search_v2_first_id( (string) ( $request->get_param( 'from' ) ?: $request->get_param( 'from_city' ) ) );
	$dates = anex_search_v2_default_dates();

	return [
		'country'  => $country !== '' ? $country : '318',
		'from'     => $from,
		'region'   => anex_search_v2_digits( $request->get_param( 'region' ) ),
		'hotel'    => anex_search_v2_digits( $request->get_param( 'hotel_id' ) ?: $request->get_param( 'hotel' ) ),
		'd1'       => anex_search_v2_date( $request->get_param( 'd1' ) ?: $request->get_param( 'date_from' ), $dates['d1'] ),
		'd2'       => anex_search_v2_date( $request->get_param( 'd2' ) ?: $request->get_param( 'date_till' ), $dates['d2'] ),
		'n1'       => anex_search_v2_int_range( $request->get_param( 'n1' ) ?: $request->get_param( 'night_from' ), 1, 30, 6 ),
		'n2'       => anex_search_v2_int_range( $request->get_param( 'n2' ) ?: $request->get_param( 'night_till' ), 1, 30, 8 ),
		'adults'   => anex_search_v2_int_range( $request->get_param( 'adults' ) ?: $request->get_param( 'adult_amount' ), 1, 4, 2 ),
		'children' => anex_search_v2_int_range( $request->get_param( 'children' ) ?: $request->get_param( 'child_amount' ), 0, 3, 0 ),
		'limit'    => anex_search_v2_int_range( $request->get_param( 'limit' ), 1, 48, 24 ),
		'currency' => '2',
	];
}

function anex_search_v2_query( array $params ): array {
	$query = [
		'type'           => '1',
		'kind'           => '1',
		'country'        => $params['country'],
		'adult_amount'   => (string) $params['adults'],
		'child_amount'   => (string) $params['children'],
		'hotel_rating'   => '1:78',
		'night_from'     => (string) min( $params['n1'], $params['n2'] ),
		'night_till'     => (string) max( $params['n1'], $params['n2'] ),
		'date_from'      => $params['d1'],
		'date_till'      => $params['d2'],
		'items_per_page' => (string) $params['limit'],
		'hotel_info'     => '1',
		'hotel_image'    => '1',
		'currency'       => $params['currency'],
	];

	foreach ( [ 'from' => 'from_city', 'region' => 'region', 'hotel' => 'hotel' ] as $source => $target ) {
		if ( $params[ $source ] !== '' ) {
			$query[ $target ] = $params[ $source ];
		}
	}

	return $query;
}

function anex_search_v2_showcase_query( array $params ): array {
	$query = anex_search_v2_query( $params );
	$query['showcase_number'] = '1';
	$query['items_per_page'] = (string) min( 24, (int) $params['limit'] );
	unset( $query['hotel'], $query['region'] );
	return $query;
}

function anex_search_v2_fetch( string $path, array $query, int &$api_calls, array &$errors ): ?array {
	if ( ! function_exists( 'ittour_lab_api_fetch' ) ) {
		$errors[] = [ 'path' => $path, 'message' => 'API client unavailable' ];
		return null;
	}
	++$api_calls;
	$result = ittour_lab_api_fetch( $path, $query, 'uk' );
	if ( is_wp_error( $result ) ) {
		$errors[] = [ 'path' => $path, 'message' => $result->get_error_message() ];
		return null;
	}
	$data = is_array( $result['data'] ?? null ) ? $result['data'] : [];
	if ( ! empty( $data['error'] ) ) {
		$errors[] = [
			'path' => $path,
			'code' => (int) ( $data['error_code'] ?? 0 ),
			'message' => (string) ( $data['error_desc'] ?? $data['error'] ),
		];
		return null;
	}
	return $data;
}

function anex_search_v2_offers( array $data ): array {
	return is_array( $data['offers'] ?? null ) ? $data['offers'] : [];
}

function anex_search_v2_cards_from_offers( array $offers, array $params, string $source ): array {
	$by_hotel = [];
	foreach ( $offers as $offer ) {
		if ( ! is_array( $offer ) ) {
			continue;
		}
		$hotel_id = anex_search_v2_hotel_id( $offer );
		if ( $hotel_id === '' ) {
			continue;
		}
		$card = anex_search_v2_card_from_offer( $offer, $params, $source );
		if ( $card === null ) {
			continue;
		}
		if ( ! isset( $by_hotel[ $hotel_id ] ) || anex_search_v2_card_sort_price( $card ) < anex_search_v2_card_sort_price( $by_hotel[ $hotel_id ] ) ) {
			$by_hotel[ $hotel_id ] = $card;
		}
	}
	$cards = array_values( $by_hotel );
	usort(
		$cards,
		static fn( array $left, array $right ): int => anex_search_v2_card_sort_price( $left ) <=> anex_search_v2_card_sort_price( $right )
	);
	return $cards;
}

function anex_search_v2_card_from_offer( array $offer, array $params, string $source ): ?array {
	$hotel_id = anex_search_v2_hotel_id( $offer );
	$title = anex_search_v2_first( $offer, [ 'hotel', 'hotel_name', 'name' ] );
	if ( $hotel_id === '' || $title === '' ) {
		return null;
	}

	$price = anex_search_v2_price( $offer, (int) $params['adults'], (int) $params['children'] );
	$transport = anex_search_v2_transport( $offer );
	$type = anex_search_v2_price_type( $offer, $transport, $price );
	$image = anex_search_v2_image( $offer );
	$flags = [];

	if ( $source === 'fallback' ) {
		$flags[] = 'fallback';
	}
	if ( $image === '' ) {
		$flags[] = 'no_image';
	}
	if ( $transport === 'none' || $transport === 'unknown' ) {
		$flags[] = 'no_transport';
	}
	if ( $price === null ) {
		$flags[] = 'price_unknown';
	}

	return [
		'hotel_id'   => $hotel_id,
		'tour_key'   => (string) ( $offer['key'] ?? '' ),
		'title'      => $title,
		'country'    => anex_search_v2_first( $offer, [ 'country', 'country_name' ] ),
		'region'     => anex_search_v2_first( $offer, [ 'region', 'region_name' ] ),
		'stars'      => anex_search_v2_stars( $offer ),
		'date_from'  => anex_search_v2_first( $offer, [ 'date_from' ] ),
		'nights'     => (int) ( $offer['duration'] ?? $offer['hnight'] ?? $params['n1'] ),
		'from_city'  => anex_search_v2_first( $offer, [ 'from_city_name', 'from_city' ] ),
		'meal'       => anex_search_v2_first( $offer, [ 'meal_type_full', 'meal_full', 'meal_type', 'meal' ] ),
		'room'       => anex_search_v2_first( $offer, [ 'room_type', 'room', 'room_name' ] ),
		'price_uah'  => $price,
		'price_type' => $type,
		'image'      => $image,
		'operator'   => anex_search_v2_first( $offer, [ 'operator_name', 'operator', 'tour_operator_name', 'tour_operator', 'spo' ] ),
		'transport'  => $transport,
		'flags'      => array_values( array_unique( $flags ) ),
	];
}

function anex_search_v2_price_type( array $offer, string $transport, ?float $price ): string {
	if ( $price === null ) {
		return 'unknown';
	}
	if ( (int) ( $offer['type'] ?? 0 ) === 2 ) {
		return 'stay_only';
	}
	if ( $transport === 'flight' || $transport === 'bus' ) {
		return 'package';
	}
	return 'unknown';
}

function anex_search_v2_transport( array $offer ): string {
	$transport = strtolower( (string) ( $offer['transport_type'] ?? '' ) );
	if ( in_array( $transport, [ 'flight', 'bus' ], true ) ) {
		return $transport;
	}
	$flights = $offer['flights'] ?? null;
	if ( is_array( $flights ) && ( ! empty( $flights['from'] ) || ! empty( $flights['to'] ) ) ) {
		return 'flight';
	}
	return $transport !== '' ? $transport : 'unknown';
}

function anex_search_v2_price( array $offer, int $adults, int $children ): ?float {
	$prices = is_array( $offer['prices'] ?? null ) ? $offer['prices'] : [];
	$keys = [
		(string) ( $adults + $children ),
		(string) $adults,
		'2',
	];
	foreach ( $keys as $key ) {
		$value = isset( $prices[ $key ] ) ? (float) $prices[ $key ] : 0.0;
		if ( $value > 0 ) {
			return $value;
		}
	}
	$value = isset( $offer['price'] ) ? (float) $offer['price'] : 0.0;
	return $value > 0 ? $value : null;
}

function anex_search_v2_image( array $offer ): string {
	foreach ( [ 'hotel_images', 'images', 'photos', 'gallery' ] as $key ) {
		$list = $offer[ $key ] ?? null;
		if ( ! is_array( $list ) ) {
			continue;
		}
		foreach ( $list as $image ) {
			$url = anex_search_v2_image_url( $image );
			if ( $url !== '' ) {
				return $url;
			}
		}
	}
	foreach ( [ 'image', 'img', 'thumb', 'thumbnail', 'photo_url', 'main_image', 'preview' ] as $key ) {
		if ( ! empty( $offer[ $key ] ) && is_string( $offer[ $key ] ) ) {
			return anex_search_v2_absolute_image_url( $offer[ $key ] );
		}
	}
	return '';
}

function anex_search_v2_image_url( $image ): string {
	if ( is_string( $image ) ) {
		return anex_search_v2_absolute_image_url( $image );
	}
	if ( ! is_array( $image ) ) {
		return '';
	}
	foreach ( [ 'full', 'web', 'url', 'src', 'image', 'photo', 'large', 'thumb' ] as $key ) {
		if ( ! empty( $image[ $key ] ) && is_string( $image[ $key ] ) ) {
			return anex_search_v2_absolute_image_url( $image[ $key ] );
		}
	}
	return '';
}

function anex_search_v2_absolute_image_url( string $url ): string {
	$url = trim( $url );
	if ( $url === '' ) {
		return '';
	}
	if ( str_starts_with( $url, '//' ) ) {
		return 'https:' . $url;
	}
	if ( preg_match( '#^https?://#i', $url ) ) {
		return $url;
	}
	return 'https://img.ittour.com.ua/' . ltrim( $url, '/' );
}

function anex_search_v2_hotel_id( array $offer ): string {
	return anex_search_v2_digits( $offer['hotel_id'] ?? $offer['id'] ?? '' );
}

function anex_search_v2_card_sort_price( array $card ): float {
	return isset( $card['price_uah'] ) && $card['price_uah'] !== null ? (float) $card['price_uah'] : PHP_FLOAT_MAX;
}

function anex_search_v2_messages( string $status, array $errors ): array {
	if ( $status === 'fallback' ) {
		return [ 'Точних збігів немає. Показуємо найближчі популярні пропозиції.' ];
	}
	if ( $status === 'empty' ) {
		return [ 'За цим запитом пропозицій немає. Спробуйте інші дати або місто виїзду.' ];
	}
	if ( $errors !== [] ) {
		return [ 'Частина джерел тимчасово недоступна, показані доступні пропозиції.' ];
	}
	return [];
}

function anex_search_v2_cache_key( array $params ): string {
	ksort( $params );
	return 'anex_search_v2_' . md5( wp_json_encode( $params ) );
}

function anex_search_v2_default_dates(): array {
	$start = strtotime( '+14 days' );
	return [
		'd1' => wp_date( 'd.m.y', $start ),
		'd2' => wp_date( 'd.m.y', strtotime( '+7 days', $start ) ),
	];
}

function anex_search_v2_date( $value, string $fallback ): string {
	$value = trim( (string) $value );
	return preg_match( '/^\d{2}\.\d{2}\.\d{2}$/', $value ) ? $value : $fallback;
}

function anex_search_v2_digits( $value ): string {
	$value = preg_replace( '/\D+/', '', (string) $value );
	return is_string( $value ) ? $value : '';
}

function anex_search_v2_first_id( string $value ): string {
	$parts = preg_split( '/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
	return $parts ? anex_search_v2_digits( $parts[0] ) : '';
}

function anex_search_v2_int_range( $value, int $min, int $max, int $fallback ): int {
	$int = (int) $value;
	if ( $int < $min || $int > $max ) {
		return $fallback;
	}
	return $int;
}

function anex_search_v2_first( array $row, array $keys ): string {
	foreach ( $keys as $key ) {
		if ( isset( $row[ $key ] ) && trim( (string) $row[ $key ] ) !== '' ) {
			return trim( (string) $row[ $key ] );
		}
	}
	return '';
}

function anex_search_v2_stars( array $offer ): int {
	$value = anex_search_v2_first( $offer, [ 'hotel_stars', 'hotel_rating', 'hotel_rating_id', 'hotel_rating_name' ] );
	if ( preg_match( '/\d+/', $value, $match ) ) {
		return max( 0, min( 5, (int) $match[0] ) );
	}
	return 0;
}
