<?php
/**
 * Anex Tour — Booking handler + email notification.
 */

defined( 'ABSPATH' ) || exit;

function ittour_lab_booking_fields(): array {
    return [
        'name'       => 'Імʼя',
        'phone'      => 'Телефон',
        'email'      => 'Email',
        'tour_title' => 'Тур',
        'tour_key'   => 'Ключ туру',
        'tour_date'  => 'Дата вильоту',
        'tour_city'  => 'Місто вильоту',
        'tour_nights'=> 'Ночей',
        'tour_room'  => 'Номер',
        'tour_meal'  => 'Харчування',
        'tour_price' => 'Вартість',
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
    $notify_email = get_option( 'anex_notify_email', $admin_email );
    $subject = 'Нова заявка на тур — ' . ( $tour_title ?: $tour_key );
    $lines   = [ 'Нова заявка з сайту ' . get_bloginfo( 'name' ) . ':', '' ];
    foreach ( ittour_lab_booking_fields() as $key => $label ) {
        if ( ! empty( $booking[ $key ] ) ) $lines[] = $label . ': ' . $booking[ $key ];
    }
    $lines[] = ''; $lines[] = 'ID заявки: ' . $booking['id'];
    $headers = [ 'From: ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' <' . $admin_email . '>' ];
    if ( $email !== '' ) $headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
    wp_mail( $notify_email, $subject, implode( "\n", $lines ), $headers );

    wp_send_json_success( [ 'message' => 'Заявку відправлено. Менеджер звяжеться з вами найближчим часом.' ] );
}
add_action( 'wp_ajax_ittour_lab_booking',        'ittour_lab_ajax_booking' );
add_action( 'wp_ajax_nopriv_ittour_lab_booking', 'ittour_lab_ajax_booking' );
