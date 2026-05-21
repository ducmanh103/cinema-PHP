<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_Ajax {
    public function __construct() {
        // Hold ghế
        add_action( 'wp_ajax_cinema_hold_seats', [ $this, 'ajax_hold_seats' ] );
        // Release ghế
        add_action( 'wp_ajax_cinema_release_seats', [ $this, 'ajax_release_seats' ] );
        // Book vé
        add_action( 'wp_ajax_cinema_book_tickets', [ $this, 'ajax_book_tickets' ] );
        // Hủy vé
        add_action( 'wp_ajax_cinema_cancel_ticket', [ $this, 'ajax_cancel_ticket' ] );

        // VNPay — tạo đơn (FE redirect tới `order_url`)
        add_action( 'wp_ajax_cinema_vnpay_create', [ $this, 'ajax_vnpay_create' ] );
        // Return URL từ VNPay (no nonce, có thể user không login ở cookie session)
        add_action( 'wp_ajax_cinema_vnpay_return',        [ $this, 'handle_vnpay_return' ] );
        add_action( 'wp_ajax_nopriv_cinema_vnpay_return', [ $this, 'handle_vnpay_return' ] );
    }

    private function check_auth() {
        check_ajax_referer( 'cinema_booking_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'Vui lòng đăng nhập.' ], 401 );
        }
        $cinema_user_id = Cinema_Auth::get_current_cinema_user_id();
        if ( ! $cinema_user_id ) {
            wp_send_json_error( [ 'message' => 'Lỗi tài khoản Cinema.' ], 403 );
        }
        return $cinema_user_id;
    }

    public function ajax_hold_seats() {
        $cinema_user_id = $this->check_auth();

        $showtime_id = isset( $_POST['showtime_id'] ) ? intval( $_POST['showtime_id'] ) : 0;
        $seat_ids    = isset( $_POST['seat_ids'] ) ? json_decode( stripslashes( $_POST['seat_ids'] ), true ) : [];

        if ( ! $showtime_id || empty( $seat_ids ) || ! is_array( $seat_ids ) ) {
            wp_send_json_error( [ 'message' => 'Dữ liệu không hợp lệ.' ] );
        }

        $result = Cinema_Seat::hold_seats( $showtime_id, $seat_ids, $cinema_user_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'expires_at' => $result ] );
    }

    public function ajax_release_seats() {
        // Có thể gọi từ beforeunload nên bỏ qua check referer
        $cinema_user_id = Cinema_Auth::get_current_cinema_user_id();
        if ( ! $cinema_user_id ) wp_send_json_error();

        $showtime_id = isset( $_POST['showtime_id'] ) ? intval( $_POST['showtime_id'] ) : 0;
        $seat_ids    = isset( $_POST['seat_ids'] ) ? json_decode( stripslashes( $_POST['seat_ids'] ), true ) : [];

        if ( $showtime_id && ! empty( $seat_ids ) ) {
            Cinema_Seat::release_seats( $showtime_id, $seat_ids, $cinema_user_id );
        }
        wp_send_json_success();
    }

    public function ajax_book_tickets() {
        $cinema_user_id = $this->check_auth();

        $showtime_id    = isset( $_POST['showtime_id'] ) ? intval( $_POST['showtime_id'] ) : 0;
        $seat_ids       = isset( $_POST['seat_ids'] ) ? json_decode( stripslashes( $_POST['seat_ids'] ), true ) : [];
        $method         = isset( $_POST['method'] ) ? sanitize_text_field( $_POST['method'] ) : 'VNPay';
        $transaction_id = isset( $_POST['transaction_id'] ) ? sanitize_text_field( $_POST['transaction_id'] ) : null;

        if ( ! $showtime_id || empty( $seat_ids ) ) {
            wp_send_json_error( [ 'message' => 'Dữ liệu không hợp lệ.' ] );
        }

        // Nếu là VNPay → bắt buộc đã có transaction_id (đã thanh toán xong)
        if ( 'VNPay' === $method && empty( $transaction_id ) ) {
            wp_send_json_error( [ 'message' => 'Chưa hoàn tất thanh toán VNPay.' ] );
        }

        $result = Cinema_Booking::book_tickets( $showtime_id, $seat_ids, $cinema_user_id, $method, $transaction_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'transaction_id' => $result ] );
    }

    public function ajax_cancel_ticket() {
        // Khác nonce name với booking
        check_ajax_referer( 'cinema_cancel_nonce', 'nonce' );
        $cinema_user_id = Cinema_Auth::get_current_cinema_user_id();

        $ticket_id = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;

        if ( $ticket_id && Cinema_Booking::cancel_ticket( $ticket_id, $cinema_user_id ) ) {
            wp_send_json_success();
        } else {
            wp_send_json_error( [ 'message' => 'Không thể hủy vé này.' ] );
        }
    }

    // ============================================================
    // VNPay
    // ============================================================

    /**
     * Tạo đơn VNPay → trả về order_url (FE redirect tới đó).
     *
     * Pattern y hệt project barbercut LOPAS_Payment_Controller::create_payment().
     */
    public function ajax_vnpay_create() {
        global $wpdb;
        $cinema_user_id = $this->check_auth();

        $showtime_id = isset( $_POST['showtime_id'] ) ? intval( $_POST['showtime_id'] ) : 0;
        $seat_ids    = isset( $_POST['seat_ids'] ) ? json_decode( stripslashes( $_POST['seat_ids'] ), true ) : [];

        if ( ! $showtime_id || empty( $seat_ids ) || ! is_array( $seat_ids ) ) {
            wp_send_json_error( [ 'message' => 'Dữ liệu không hợp lệ.' ] );
        }
        $seat_ids = array_values( array_unique( array_map( 'absint', $seat_ids ) ) );

        // Tính tổng tiền (giống logic book_tickets)
        $showtime = $wpdb->get_row( $wpdb->prepare(
            "SELECT st.Price, m.Title
             FROM cinema_showtimes st
             JOIN cinema_movies m ON st.MovieId = m.MovieId
             WHERE st.ShowtimeId = %d AND st.StartTime > NOW()", $showtime_id
        ) );
        if ( ! $showtime ) {
            wp_send_json_error( [ 'message' => 'Suất chiếu không hợp lệ.' ] );
        }

        $placeholders = implode( ',', array_fill( 0, count( $seat_ids ), '%d' ) );
        $seats = $wpdb->get_results( $wpdb->prepare(
            "SELECT SeatId, SeatType FROM cinema_seats WHERE SeatId IN ($placeholders)",
            ...$seat_ids
        ) );

        $total = 0;
        foreach ( $seats as $s ) {
            $total += ( $s->SeatType === 'VIP' ) ? ( $showtime->Price + 10000 ) : $showtime->Price;
        }

        if ( $total <= 0 ) {
            wp_send_json_error( [ 'message' => 'Số tiền không hợp lệ.' ] );
        }

        $order_code = Cinema_VNPay::generate_order_code();
        $order_info = Cinema_VNPay::ascii( 'Thanh toan ve xem phim: ' . $showtime->Title );

        $order = Cinema_VNPay::create_payment_url( (int) $total, $order_code, $order_info );
        if ( is_wp_error( $order ) ) {
            wp_send_json_error( [ 'message' => $order->get_error_message() ] );
        }

        // Lưu pending order vào transient để dùng khi handle_vnpay_return verify + book vé
        set_transient( 'cinema_vnp_order_' . $order_code, [
            'showtime_id'    => $showtime_id,
            'seat_ids'       => $seat_ids,
            'cinema_user_id' => $cinema_user_id,
            'amount'         => $order['amount'],
        ], 30 * MINUTE_IN_SECONDS );

        wp_send_json_success( [
            'order_code' => $order_code,
            'order_url'  => $order['order_url'],
            'amount'     => $order['amount'],
        ] );
    }

    /**
     * Return handler — VNPay redirect user về đây sau khi thanh toán.
     *
     * Flow:
     *   1. Verify chữ ký HMAC-SHA512 từ vnp_SecureHash
     *   2. Parse response → lấy order_code (vnp_TxnRef) + response_code
     *   3. Lấy pending order từ transient → book vé (idempotent)
     *   4. Redirect về /profile/?payment=success|failed|...
     */
    public function handle_vnpay_return() {
        $response = $_GET;

        if ( empty( $response ) || empty( $response['vnp_SecureHash'] ) ) {
            wp_safe_redirect( home_url( '/profile/?payment=invalid' ) );
            exit;
        }

        // 1. Verify chữ ký
        if ( ! Cinema_VNPay::verify_response( $response ) ) {
            wp_safe_redirect( home_url( '/profile/?payment=invalid' ) );
            exit;
        }

        $parsed     = Cinema_VNPay::parse_response( $response );
        $order_code = $parsed['order_code'];

        if ( empty( $order_code ) ) {
            wp_safe_redirect( home_url( '/profile/?payment=invalid' ) );
            exit;
        }

        // 2. Lấy pending order đã lưu khi tạo
        $pending = get_transient( 'cinema_vnp_order_' . $order_code );
        if ( empty( $pending ) || empty( $pending['seat_ids'] ) || empty( $pending['showtime_id'] ) ) {
            wp_safe_redirect( home_url( '/profile/?payment=expired' ) );
            exit;
        }

        // 3. Idempotent: nếu đã book rồi thì thôi
        if ( ! empty( $pending['booked'] ) ) {
            wp_safe_redirect( home_url( '/profile/?payment=success' ) );
            exit;
        }

        // 4. Check response code
        if ( ! Cinema_VNPay::is_payment_success( $parsed['response_code'] ) ) {
            // 24 = Customer huỷ
            $reason = ( '24' === (string) $parsed['response_code'] ) ? 'cancelled' : 'failed';
            wp_safe_redirect( home_url( '/profile/?payment=' . $reason ) );
            exit;
        }

        // 5. Book vé (transaction_id = mã giao dịch VNPay nếu có, fallback order_code)
        $transaction_id = ! empty( $parsed['transaction_code'] ) ? $parsed['transaction_code'] : $order_code;
        $booking = Cinema_Booking::book_tickets(
            (int) $pending['showtime_id'],
            $pending['seat_ids'],
            (int) $pending['cinema_user_id'],
            'VNPay',
            $transaction_id
        );

        if ( is_wp_error( $booking ) ) {
            // Đã thu tiền nhưng book fail — log để hoàn tiền thủ công
            error_log( '[Cinema VNPay] Booking failed sau khi paid: ' . $order_code . ' — ' . $booking->get_error_message() );
            wp_safe_redirect( home_url( '/profile/?payment=book_failed' ) );
            exit;
        }

        $pending['booked'] = true;
        set_transient( 'cinema_vnp_order_' . $order_code, $pending, 30 * MINUTE_IN_SECONDS );

        wp_safe_redirect( home_url( '/profile/?payment=success' ) );
        exit;
    }
}
new Cinema_Ajax();
