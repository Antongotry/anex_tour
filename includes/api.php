<?php
/**
 * Anex Tour — API proxy functions (IT-Tour API).
 */

defined( 'ABSPATH' ) || exit;

/* ─── Token ─── */
function ittour_lab_get_token(): string {
    if ( defined( 'ITTOUR_API_TOKEN' ) && ITTOUR_API_TOKEN !== '' ) {
        return (string) ITTOUR_API_TOKEN;
    }
    return trim( (string) get_option( ITTOUR_LAB_TOKEN_OPTION, '' ) );
}

/* ─── Path validation ─── */
function ittour_lab_validate_path( string $path ): bool {
    $path = trim( $path, '/' );
    if ( $path === '' || str_contains( $path, '..' ) ) {
        return false;
    }
    return (bool) preg_match(
        '#^(module|module-excursion|showcase|dictionary|tour|tour-excursion|hotel|search|get-result|order|order-excursion|charter)(/[\w./-]*)?$#',
        $path
    );
}

/* ─── Query sanitization ─── */
function ittour_lab_sanitize_query_params( array $query ): array {
    $out = [];
    foreach ( $query as $k => $v ) {
        $k = (string) $k;
        if ( ! preg_match( '/^[a-zA-Z0-9_:.-]{1,48}$/', $k ) ) continue;
        if ( is_array( $v ) ) continue;
        if ( is_bool( $v ) )        $out[ $k ] = $v ? '1' : '0';
        elseif ( is_int( $v ) || is_float( $v ) ) $out[ $k ] = (string) $v;
        elseif ( is_string( $v ) )  $out[ $k ] = mb_substr( $v, 0, 512 );
    }
    if ( count( $out ) > 40 ) $out = array_slice( $out, 0, 40, true );
    return $out;
}

/* ─── Cache ─── */
function ittour_lab_cache_key( string $path, array $query, string $lang, string $method = 'GET' ): string {
    return 'ittour_lab_' . md5( wp_json_encode( [ strtoupper( $method ), $path, $query, $lang ] ) );
}

function ittour_lab_cache_ttl( string $path, array $data = [] ): int {
    if ( ! empty( $data['error_code'] ) && (string) $data['error_code'] === '108' ) return 15 * MINUTE_IN_SECONDS;
    if ( str_starts_with( $path, 'module/search-list' ) )                           return 6 * HOUR_IN_SECONDS;
    if ( str_starts_with( $path, 'module-excursion/search' ) )                       return 6 * HOUR_IN_SECONDS;
    if ( str_starts_with( $path, 'showcase/hot-offers/' ) )                          return 30 * MINUTE_IN_SECONDS;
    if ( str_starts_with( $path, 'module/params' ) || str_starts_with( $path, 'dictionary/' ) ) return DAY_IN_SECONDS;
    if ( str_starts_with( $path, 'tour/info' ) || str_starts_with( $path, 'tour-excursion/' ) || str_starts_with( $path, 'tour/flights' ) || str_starts_with( $path, 'hotel/' ) ) return 6 * HOUR_IN_SECONDS;
    return HOUR_IN_SECONDS;
}

/* ─── Main fetch ─── */
/**
 * @return array{code:int,data:mixed}|WP_Error
 */
function ittour_lab_api_fetch( string $path, array $query, string $lang = 'uk', string $method = 'GET' ) {
    $path = trim( $path, '/' );
    if ( ! ittour_lab_validate_path( $path ) ) return new WP_Error( 'ittour_bad_path', 'Недопустимий path.', [ 'status' => 400 ] );
    $method = strtoupper( trim( $method ) );
    if ( ! in_array( $method, [ 'GET', 'POST' ], true ) ) {
        return new WP_Error( 'ittour_bad_method', 'Недопустимий HTTP method. Дозволено тільки GET/POST.', [ 'status' => 400 ] );
    }

    $token = ittour_lab_get_token();
    if ( $token === '' ) return new WP_Error( 'ittour_no_token', 'Не задано токен IT-Tour API. Перейдіть у Налаштування → Anex Tour.', [ 'status' => 500 ] );

    if ( ! in_array( $lang, [ 'uk', 'ru' ], true ) ) $lang = 'uk';

    $url   = ITTOUR_LAB_API_BASE . $path;
    $query = ittour_lab_sanitize_query_params( $query );
    if ( 'GET' === $method && $query !== [] ) {
        $url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    }

    $use_cache = ( 'GET' === $method );
    $cache_key = ittour_lab_cache_key( $path, $query, $lang, $method );
    if ( $use_cache ) {
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) && isset( $cached['code'] ) && array_key_exists( 'data', $cached ) ) return $cached;
    }

    $lock_key = $cache_key . '_lock';
    if ( $use_cache && get_transient( $lock_key ) ) {
        for ( $i = 0; $i < 8; $i++ ) {
            usleep( 250000 );
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) && isset( $cached['code'] ) && array_key_exists( 'data', $cached ) ) return $cached;
        }
    }
    if ( $use_cache ) {
        set_transient( $lock_key, 1, 12 );
    }

    $request_args = [
        'timeout' => 50,
        'headers' => [
            'Authorization'   => $token,
            'Accept-Language' => $lang,
            'User-Agent'      => 'WordPress/AneXTour-Plugin/' . ANEX_VERSION,
        ],
    ];
    if ( 'POST' === $method && $query !== [] ) {
        $request_args['body'] = $query;
    }

    $resp = wp_remote_request( $url, array_merge( $request_args, [ 'method' => $method ] ) );

    if ( is_wp_error( $resp ) ) {
        if ( $use_cache ) {
            delete_transient( $lock_key );
        }
        return new WP_Error( 'ittour_http', $resp->get_error_message(), [ 'status' => 502 ] );
    }

    $code   = (int) wp_remote_retrieve_response_code( $resp );
    $body   = (string) wp_remote_retrieve_body( $resp );
    $data   = json_decode( $body, true );
    if ( JSON_ERROR_NONE !== json_last_error() ) $data = [ '_raw' => $body ];
    $result = [ 'code' => $code ?: 200, 'data' => $data ];

    if ( $use_cache ) {
        if ( is_array( $data ) ) {
            $is_offer_search = str_starts_with( $path, 'module/search' )
                || str_starts_with( $path, 'module-excursion/search' )
                || str_starts_with( $path, 'showcase/hot-offers/search' );
            $is_empty = $is_offer_search && isset( $data['offers'] ) && is_array( $data['offers'] ) && count( $data['offers'] ) === 0;
            if ( $is_empty ) {
                set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
            } else {
                set_transient( $cache_key, $result, ittour_lab_cache_ttl( $path, $data ) );
            }
        } elseif ( ( $code ?: 200 ) < 400 ) {
            set_transient( $cache_key, $result, ittour_lab_cache_ttl( $path ) );
        }
        delete_transient( $lock_key );
    }
    return $result;
}

/* ─── Public AJAX proxy ─── */
function ittour_lab_ajax_public(): void {
    check_ajax_referer( 'ittour_lab_public', 'nonce' );
    $path  = isset( $_POST['path'] ) ? trim( wp_unslash( (string) $_POST['path'] ), '/' ) : '';
    $lang  = isset( $_POST['lang'] )  ? wp_unslash( (string) $_POST['lang'] ) : 'uk';
    $method = isset( $_POST['method'] ) ? strtoupper( trim( wp_unslash( (string) $_POST['method'] ) ) ) : 'GET';
    $raw   = isset( $_POST['query'] ) ? wp_unslash( (string) $_POST['query'] ) : '{}';
    $query = json_decode( $raw, true );
    if ( ! is_array( $query ) ) $query = [];

    $result = ittour_lab_api_fetch( $path, $query, $lang, $method );
    if ( is_wp_error( $result ) ) {
        $err_data = $result->get_error_data();
        $status   = 400;
        if ( is_array( $err_data ) && isset( $err_data['status'] ) ) $status = (int) $err_data['status'];
        wp_send_json_error( [ 'message' => $result->get_error_message() ], $status );
    }
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_ittour_lab_public',        'ittour_lab_ajax_public' );
add_action( 'wp_ajax_nopriv_ittour_lab_public', 'ittour_lab_ajax_public' );

/* ─── REST endpoint (admin only) ─── */
add_action( 'rest_api_init', static function () {
    register_rest_route( 'ittour/v1', '/proxy', [
        'methods'             => [ 'GET', 'POST' ],
        'callback'            => static function ( WP_REST_Request $req ) {
            if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'forbidden', 'Forbidden', [ 'status' => 403 ] );
            $path   = trim( (string) $req->get_param( 'path' ), '/' );
            $method = strtoupper( (string) $req->get_method() );
            $params = 'POST' === $method ? $req->get_body_params() : $req->get_query_params();
            unset( $params['path'] );
            $lang = isset( $params['_lang'] ) ? (string) $params['_lang'] : 'uk';
            unset( $params['_lang'] );
            $result = ittour_lab_api_fetch( $path, $params, $lang, $method );
            if ( is_wp_error( $result ) ) return $result;
            return new WP_REST_Response( $result['data'], $result['code'] );
        },
        'permission_callback' => static fn() => current_user_can( 'manage_options' ),
        'args'                => [ 'path' => [ 'required' => true, 'sanitize_callback' => static fn( $v ) => sanitize_text_field( (string) $v ) ] ],
    ] );
} );
