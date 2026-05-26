<?php
/**
 * Anex Tour — Booking handler + email notification.
 */

defined( 'ABSPATH' ) || exit;

function ittour_lab_is_excursion_booking( array $booking ): bool {
    $page_url = (string) ( $booking['page_url'] ?? '' );
    $raw_tour_city = (string) ( $booking['tour_city'] ?? '' );
    $tour_city = function_exists( 'mb_strtolower' ) ? mb_strtolower( $raw_tour_city ) : strtolower( $raw_tour_city );
    $ukraine_departures = [
        'київ', 'киев', 'львів', 'львов', 'одеса', 'одесса', 'харків', 'харьков', 'дніпро', 'днепр',
        'біла церква', 'белая церковь', 'вінниця', 'винница', 'житомир', 'рівне', 'ровно',
        'тернопіль', 'тернополь', 'чернівці', 'черновцы', 'черкаси', 'ужгород', 'мукачево',
        'івано-франківськ', 'ивано-франковск', 'луцьк', 'луцк', 'полтава', 'хмельницький',
    ];

    if ( $page_url !== '' ) {
        $query = wp_parse_url( $page_url, PHP_URL_QUERY );
        if ( is_string( $query ) && $query !== '' ) {
            parse_str( $query, $params );
            if (
                ( isset( $params['mode'] ) && (string) $params['mode'] === 'excursion' )
                || isset( $params['excursion_detail'] )
                || ( isset( $params['transport_type'] ) && (string) $params['transport_type'] === '2' )
            ) {
                return true;
            }
        }
    }

    foreach ( $ukraine_departures as $city ) {
        if ( $city !== '' && str_contains( $tour_city, $city ) ) {
            return true;
        }
    }

    return false;
}

function ittour_lab_booking_fields( array $booking = [] ): array {
    $date_label = ittour_lab_is_excursion_booking( $booking ) ? 'Дата виїзду' : 'Дата вильоту';
    return [
        'name'       => 'Імʼя',
        'phone'      => 'Телефон',
        'email'      => 'Email',
        'tour_title' => 'Тур',
        'tour_key'   => 'Ключ туру',
        'tour_date'  => $date_label,
        'tour_city'  => 'Місто виїзду',
        'tour_nights'=> 'Ночей',
        'tour_room'  => 'Номер',
        'tour_meal'  => 'Харчування',
        'tour_price' => 'Вартість',
        'tour_operator' => 'Туроператор',
        'message'    => 'Коментар',
        'page_url'   => 'Сторінка',
    ];
}

function ittour_lab_ajax_booking(): void {
    check_ajax_referer( 'ittour_lab_public', 'nonce' );

    $name       = sanitize_text_field( wp_unslash( (string) ( $_POST['name']       ?? '' ) ) );
    $phone      = sanitize_text_field( wp_unslash( (string) ( $_POST['phone']      ?? '' ) ) );
    $email      = sanitize_email( wp_unslash( (string)      ( $_POST['email']      ?? '' ) ) );
    $tour_title = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_title'] ?? '' ) ) );
    $tour_key   = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_key']   ?? '' ) ) );
    $tour_date  = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_date']  ?? '' ) ) );
    $tour_city  = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_city']  ?? '' ) ) );
    $tour_nights= sanitize_text_field( wp_unslash( (string) ( $_POST['tour_nights']?? '' ) ) );
    $tour_room  = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_room']  ?? '' ) ) );
    $tour_meal  = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_meal']  ?? '' ) ) );
    $tour_price = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_price'] ?? '' ) ) );
    $tour_operator = sanitize_text_field( wp_unslash( (string) ( $_POST['tour_operator'] ?? '' ) ) );
    $message    = sanitize_textarea_field( wp_unslash( (string) ( $_POST['message'] ?? '' ) ) );
    $page_url   = esc_url_raw( wp_unslash( (string) ( $_POST['page_url'] ?? '' ) ) );

    if ( $name === '' || $phone === '' ) {
        wp_send_json_error( [ 'message' => 'Вкажіть імʼя та телефон.' ], 422 );
    }

    $booking = [
        'id'          => time() . '-' . wp_generate_password( 6, false, false ),
        'created_at'  => current_time( 'mysql' ),
        'name'        => $name,
        'phone'       => $phone,
        'email'       => $email,
        'tour_title'  => $tour_title,
        'tour_key'    => $tour_key,
        'tour_date'   => $tour_date,
        'tour_city'   => $tour_city,
        'tour_nights' => $tour_nights,
        'tour_room'   => $tour_room,
        'tour_meal'   => $tour_meal,
        'tour_price'  => $tour_price,
        'tour_operator' => $tour_operator,
        'message'     => $message,
        'page_url'    => $page_url,
        'ip'          => sanitize_text_field( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
    ];

    $bookings = get_option( ITTOUR_LAB_BOOKINGS_OPTION, [] );
    if ( ! is_array( $bookings ) ) $bookings = [];
    array_unshift( $bookings, $booking );
    $bookings = array_slice( $bookings, 0, 500 );
    update_option( ITTOUR_LAB_BOOKINGS_OPTION, $bookings, false );

    // Email notification
    $admin_email = get_option( 'admin_email' );
    $notify_email = sanitize_email( (string) get_option( 'anex_notify_email', $admin_email ) );
    $recipients = array_values(
        array_unique(
            array_filter(
                [
                    sanitize_email( (string) $admin_email ),
                    $notify_email,
                ],
                'is_email'
            )
        )
    );
    $subject = 'Нова заявка на тур — ' . ( $tour_title ?: $tour_key );
    $lines   = [ 'Нова заявка з сайту ' . get_bloginfo( 'name' ) . ':', '' ];
    foreach ( ittour_lab_booking_fields( $booking ) as $key => $label ) {
        if ( ! empty( $booking[ $key ] ) ) $lines[] = $label . ': ' . $booking[ $key ];
    }
    $lines[] = ''; $lines[] = 'ID заявки: ' . $booking['id'];
    $headers = [ 'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' <' . $admin_email . '>' ];
    if ( $email !== '' ) $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
    $mailed = ! empty( $recipients ) ? wp_mail( implode( ',', $recipients ), $subject, implode( "\n", $lines ), $headers ) : false;

    $message = 'Заявку збережено. Менеджер звʼяжеться з вами найближчим часом.';
    if ( ! $mailed ) {
        $message .= ' (Лист на email не надіслано — перевірте Anex Tour → Заявки або налаштуйте SMTP.)';
    }

    wp_send_json_success(
        [
            'message' => $message,
            'saved'   => true,
            'mailed'  => (bool) $mailed,
            'id'      => $booking['id'],
        ]
    );
}
add_action( 'wp_ajax_ittour_lab_booking',        'ittour_lab_ajax_booking' );
add_action( 'wp_ajax_nopriv_ittour_lab_booking', 'ittour_lab_ajax_booking' );
