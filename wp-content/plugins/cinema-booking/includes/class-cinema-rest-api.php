<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_REST_API {
    const NS = 'cinema/v1';

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( self::NS, '/movies', [
            [ 'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_movies' ], 'permission_callback' => '__return_true' ],
            [ 'methods' => WP_REST_Server::CREATABLE, 'callback' => [ $this, 'create_movie' ], 'permission_callback' => [ $this, 'can_admin' ] ],
        ] );
        register_rest_route( self::NS, '/movies/(?P<id>\d+)', [
            [ 'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_movie' ], 'permission_callback' => '__return_true' ],
            [ 'methods' => WP_REST_Server::EDITABLE, 'callback' => [ $this, 'update_movie' ], 'permission_callback' => [ $this, 'can_admin' ] ],
            [ 'methods' => WP_REST_Server::DELETABLE, 'callback' => [ $this, 'delete_movie' ], 'permission_callback' => [ $this, 'can_admin' ] ],
        ] );

        register_rest_route( self::NS, '/showtimes/bymovie/(?P<movie_id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_showtimes_by_movie' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/showtimes/bydate/(?P<date>\d{4}-\d{2}-\d{2})', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_showtimes_by_date' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/showtimes/(?P<id>\d+)', [
            [ 'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_showtime' ], 'permission_callback' => '__return_true' ],
            [ 'methods' => WP_REST_Server::DELETABLE, 'callback' => [ $this, 'delete_showtime' ], 'permission_callback' => [ $this, 'can_admin' ] ],
        ] );
        register_rest_route( self::NS, '/showtimes/(?P<id>\d+)/seats', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_seats' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( self::NS, '/showtimes', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'create_showtime' ],
            'permission_callback' => [ $this, 'can_admin' ],
        ] );

        register_rest_route( self::NS, '/tickets/book', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'book_ticket' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( self::NS, '/tickets/my', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'my_tickets' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( self::NS, '/tickets/(?P<id>\d+)/cancel', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [ $this, 'cancel_ticket' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );

        register_rest_route( self::NS, '/users', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [ $this, 'get_users' ],
            'permission_callback' => [ $this, 'can_admin' ],
        ] );
        register_rest_route( self::NS, '/users/(?P<id>\d+)', [
            [ 'methods' => WP_REST_Server::READABLE, 'callback' => [ $this, 'get_user' ], 'permission_callback' => [ $this, 'can_admin' ] ],
            [ 'methods' => WP_REST_Server::DELETABLE, 'callback' => [ $this, 'delete_user' ], 'permission_callback' => [ $this, 'can_admin' ] ],
        ] );
        register_rest_route( self::NS, '/users/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [ $this, 'change_user_status' ],
            'permission_callback' => [ $this, 'can_admin' ],
        ] );

        register_rest_route( self::NS, '/seat-holds', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'create_hold' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
        register_rest_route( self::NS, '/seat-holds/release-expired', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [ $this, 'release_expired_holds' ],
            'permission_callback' => [ $this, 'can_admin' ],
        ] );
        register_rest_route( self::NS, '/seat-holds/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [ $this, 'release_hold' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
    }

    public function can_admin() {
        return current_user_can( 'manage_options' );
    }

    public function is_logged_in() {
        return is_user_logged_in();
    }

    public function get_movies() {
        global $wpdb;
        return rest_ensure_response( $wpdb->get_results(
            "SELECT m.*, GROUP_CONCAT(g.GenreName ORDER BY g.GenreName SEPARATOR ', ') AS Genres
             FROM cinema_movies m
             LEFT JOIN cinema_movie_genres mg ON mg.MovieId = m.MovieId
             LEFT JOIN cinema_genres g ON g.GenreId = mg.GenreId
             GROUP BY m.MovieId
             ORDER BY m.MovieId DESC"
        ) );
    }

    public function get_movie( WP_REST_Request $request ) {
        global $wpdb;
        $movie = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM cinema_movies WHERE MovieId = %d', absint( $request['id'] ) ) );
        return $movie ? rest_ensure_response( $movie ) : new WP_Error( 'not_found', 'Không tìm thấy phim.', [ 'status' => 404 ] );
    }

    public function create_movie( WP_REST_Request $request ) {
        global $wpdb;
        $data = $this->movie_payload( $request );
        if ( empty( $data['Title'] ) || empty( $data['Duration'] ) ) {
            return new WP_Error( 'invalid_movie', 'Tên phim và thời lượng là bắt buộc.', [ 'status' => 400 ] );
        }
        $data['CreatedAt'] = current_time( 'mysql' );
        $wpdb->insert( 'cinema_movies', $data );
        return rest_ensure_response( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM cinema_movies WHERE MovieId = %d', $wpdb->insert_id ) ) );
    }

    public function update_movie( WP_REST_Request $request ) {
        global $wpdb;
        $movie_id = absint( $request['id'] );
        if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT MovieId FROM cinema_movies WHERE MovieId = %d', $movie_id ) ) ) {
            return new WP_Error( 'not_found', 'Không tìm thấy phim.', [ 'status' => 404 ] );
        }
        $wpdb->update( 'cinema_movies', $this->movie_payload( $request ), [ 'MovieId' => $movie_id ] );
        return new WP_REST_Response( null, 204 );
    }

    public function delete_movie( WP_REST_Request $request ) {
        global $wpdb;
        $movie_id = absint( $request['id'] );
        $has_showtimes = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM cinema_showtimes WHERE MovieId = %d', $movie_id ) );
        if ( $has_showtimes ) {
            return new WP_Error( 'has_showtimes', 'Không thể xoá phim đã có suất chiếu.', [ 'status' => 400 ] );
        }
        $wpdb->delete( 'cinema_movie_genres', [ 'MovieId' => $movie_id ] );
        $deleted = $wpdb->delete( 'cinema_movies', [ 'MovieId' => $movie_id ] );
        return $deleted ? new WP_REST_Response( null, 204 ) : new WP_Error( 'not_found', 'Không tìm thấy phim.', [ 'status' => 404 ] );
    }

    private function movie_payload( WP_REST_Request $request ) {
        $title = sanitize_text_field( $request['title'] ?? $request['Title'] ?? '' );
        return [
            'Title' => $title,
            'Duration' => absint( $request['duration'] ?? $request['Duration'] ?? 0 ),
            'Description' => sanitize_textarea_field( $request['description'] ?? $request['Description'] ?? '' ),
            'ReleaseDate' => sanitize_text_field( $request['releaseDate'] ?? $request['ReleaseDate'] ?? '' ) ?: null,
            'PosterUrl' => esc_url_raw( $request['posterUrl'] ?? $request['PosterUrl'] ?? '' ),
            'BannerUrl' => esc_url_raw( $request['bannerUrl'] ?? $request['BannerUrl'] ?? '' ),
            'Slug' => sanitize_title( $request['slug'] ?? $request['Slug'] ?? $title ),
            'Status' => sanitize_text_field( $request['status'] ?? $request['Status'] ?? 'Now Showing' ),
        ];
    }

    public function get_showtimes_by_movie( WP_REST_Request $request ) {
        global $wpdb;
        return rest_ensure_response( $wpdb->get_results( $wpdb->prepare(
            "SELECT st.*, m.Title AS MovieTitle, r.RoomName, t.Name AS TheaterName, t.City
             FROM cinema_showtimes st
             JOIN cinema_movies m ON m.MovieId = st.MovieId
             JOIN cinema_rooms r ON r.RoomId = st.RoomId
             JOIN cinema_theaters t ON t.TheaterId = r.TheaterId
             WHERE st.MovieId = %d AND st.StartTime > NOW()
             ORDER BY st.StartTime ASC",
            absint( $request['movie_id'] )
        ) ) );
    }

    public function get_showtimes_by_date( WP_REST_Request $request ) {
        return rest_ensure_response( cinema_get_all_showtimes( sanitize_text_field( $request['date'] ) ) );
    }

    public function get_showtime( WP_REST_Request $request ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT st.*, m.Title AS MovieTitle, r.RoomName, t.Name AS TheaterName
             FROM cinema_showtimes st
             JOIN cinema_movies m ON m.MovieId = st.MovieId
             JOIN cinema_rooms r ON r.RoomId = st.RoomId
             JOIN cinema_theaters t ON t.TheaterId = r.TheaterId
             WHERE st.ShowtimeId = %d",
            absint( $request['id'] )
        ) );
        return $row ? rest_ensure_response( $row ) : new WP_Error( 'not_found', 'Không tìm thấy suất chiếu.', [ 'status' => 404 ] );
    }

    public function get_seats( WP_REST_Request $request ) {
        return rest_ensure_response( cinema_get_seat_map( absint( $request['id'] ) ) );
    }

    public function create_showtime( WP_REST_Request $request ) {
        global $wpdb;
        $movie_id = absint( $request['movieId'] ?? $request['MovieId'] ?? 0 );
        $room_id = absint( $request['roomId'] ?? $request['RoomId'] ?? 0 );
        $start_time = sanitize_text_field( $request['startTime'] ?? $request['StartTime'] ?? '' );
        $price = (float) ( $request['price'] ?? $request['Price'] ?? 0 );
        if ( ! $movie_id || ! $room_id || ! $start_time || $price <= 0 ) {
            return new WP_Error( 'invalid_showtime', 'Dữ liệu suất chiếu không hợp lệ.', [ 'status' => 400 ] );
        }
        $duration = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT Duration FROM cinema_movies WHERE MovieId = %d', $movie_id ) );
        $start = date( 'Y-m-d H:i:s', strtotime( $start_time ) );
        $end = $duration ? date( 'Y-m-d H:i:s', strtotime( $start . " +{$duration} minutes" ) ) : null;
        if ( $end && $this->showtime_overlaps( $room_id, $start, $end ) ) {
            return new WP_Error( 'showtime_overlap', 'Thời gian chiếu bị trùng với một suất chiếu khác trong cùng phòng chiếu.', [ 'status' => 400 ] );
        }
        $wpdb->insert( 'cinema_showtimes', [
            'MovieId' => $movie_id,
            'RoomId' => $room_id,
            'StartTime' => $start,
            'EndTime' => $end,
            'Price' => $price,
        ] );
        return rest_ensure_response( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM cinema_showtimes WHERE ShowtimeId = %d', $wpdb->insert_id ) ) );
    }

    private function showtime_overlaps( $room_id, $new_start, $new_end ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT st.StartTime, COALESCE(st.EndTime, DATE_ADD(st.StartTime, INTERVAL m.Duration MINUTE)) AS EndTime
             FROM cinema_showtimes st
             JOIN cinema_movies m ON m.MovieId = st.MovieId
             WHERE st.RoomId = %d
               AND st.StartTime BETWEEN DATE_SUB(%s, INTERVAL 1 DAY) AND DATE_ADD(%s, INTERVAL 1 DAY)",
            $room_id,
            $new_start,
            $new_end
        ) );

        $new_start_ts = strtotime( $new_start );
        $new_end_ts = strtotime( $new_end );
        foreach ( $rows as $row ) {
            if ( $new_start_ts < strtotime( $row->EndTime ) && strtotime( $row->StartTime ) < $new_end_ts ) {
                return true;
            }
        }
        return false;
    }

    public function delete_showtime( WP_REST_Request $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM cinema_tickets WHERE ShowtimeId = %d', $id ) ) ) {
            return new WP_Error( 'has_tickets', 'Không thể xoá suất chiếu đã có vé.', [ 'status' => 400 ] );
        }
        $wpdb->delete( 'cinema_seat_holds', [ 'ShowtimeId' => $id ] );
        $deleted = $wpdb->delete( 'cinema_showtimes', [ 'ShowtimeId' => $id ] );
        return $deleted ? new WP_REST_Response( null, 204 ) : new WP_Error( 'not_found', 'Không tìm thấy suất chiếu.', [ 'status' => 404 ] );
    }

    public function book_ticket( WP_REST_Request $request ) {
        $user_id = Cinema_Auth::get_current_cinema_user_id();
        $result = Cinema_Booking::book_tickets(
            absint( $request['showtimeId'] ?? $request['ShowtimeId'] ?? 0 ),
            array_map( 'absint', (array) ( $request['seatIds'] ?? $request['SeatIds'] ?? [] ) ),
            $user_id,
            sanitize_text_field( $request['paymentMethod'] ?? $request['PaymentMethod'] ?? 'VNPay' )
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'transaction_id' => $result ] );
    }

    public function my_tickets() {
        global $wpdb;
        $user_id = Cinema_Auth::get_current_cinema_user_id();
        return rest_ensure_response( $wpdb->get_results( $wpdb->prepare(
            "SELECT tk.*, m.Title AS MovieTitle, st.StartTime, r.RoomName, s.SeatNumber, s.SeatType,
                    COALESCE(p.Amount, CASE WHEN s.SeatType = 'VIP' THEN st.Price + 10000 ELSE st.Price END) AS Amount,
                    p.Status AS PaymentStatus
             FROM cinema_tickets tk
             JOIN cinema_showtimes st ON st.ShowtimeId = tk.ShowtimeId
             JOIN cinema_movies m ON m.MovieId = st.MovieId
             JOIN cinema_rooms r ON r.RoomId = st.RoomId
             LEFT JOIN cinema_seats s ON s.SeatId = tk.SeatId
             LEFT JOIN cinema_payments p ON p.TicketId = tk.TicketId
             WHERE tk.UserId = %d
             ORDER BY tk.BookingTime DESC",
            $user_id
        ) ) );
    }

    public function cancel_ticket( WP_REST_Request $request ) {
        $ok = Cinema_Booking::cancel_ticket( absint( $request['id'] ), Cinema_Auth::get_current_cinema_user_id() );
        return $ok ? rest_ensure_response( [ 'message' => 'Đã huỷ vé thành công.' ] ) : new WP_Error( 'not_found', 'Không thể huỷ vé.', [ 'status' => 404 ] );
    }

    public function get_users() {
        global $wpdb;
        return rest_ensure_response( $wpdb->get_results(
            'SELECT u.UserId, u.Username, u.FullName, u.Email, u.Status, r.RoleName FROM cinema_users u JOIN cinema_roles r ON r.RoleId = u.RoleId ORDER BY u.UserId DESC'
        ) );
    }

    public function get_user( WP_REST_Request $request ) {
        global $wpdb;
        $user = $wpdb->get_row( $wpdb->prepare(
            'SELECT u.UserId, u.Username, u.FullName, u.Email, u.Status, r.RoleName FROM cinema_users u JOIN cinema_roles r ON r.RoleId = u.RoleId WHERE u.UserId = %d',
            absint( $request['id'] )
        ) );
        return $user ? rest_ensure_response( $user ) : new WP_Error( 'not_found', 'Không tìm thấy người dùng.', [ 'status' => 404 ] );
    }

    public function change_user_status( WP_REST_Request $request ) {
        global $wpdb;
        $status = sanitize_text_field( $request['status'] ?? 'Active' );
        $wpdb->update( 'cinema_users', [ 'Status' => $status ], [ 'UserId' => absint( $request['id'] ) ] );
        return new WP_REST_Response( null, 204 );
    }

    public function delete_user( WP_REST_Request $request ) {
        global $wpdb;
        $id = absint( $request['id'] );
        if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM cinema_tickets WHERE UserId = %d', $id ) ) ) {
            return new WP_Error( 'has_tickets', 'Không thể xoá người dùng đã có lịch sử đặt vé.', [ 'status' => 400 ] );
        }
        $deleted = $wpdb->delete( 'cinema_users', [ 'UserId' => $id ] );
        return $deleted ? new WP_REST_Response( null, 204 ) : new WP_Error( 'not_found', 'Không tìm thấy người dùng.', [ 'status' => 404 ] );
    }

    public function create_hold( WP_REST_Request $request ) {
        $result = Cinema_Seat::hold_seats(
            absint( $request['showtimeId'] ?? $request['ShowtimeId'] ?? 0 ),
            array_map( 'absint', (array) ( $request['seatIds'] ?? $request['SeatIds'] ?? [] ) ),
            Cinema_Auth::get_current_cinema_user_id()
        );
        return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'expires_at' => $result ] );
    }

    public function release_expired_holds() {
        global $wpdb;
        $count = $wpdb->query( "UPDATE cinema_seat_holds SET Status = 'Expired', ReleasedAt = NOW() WHERE Status = 'Active' AND ExpiresAt <= NOW()" );
        return rest_ensure_response( [ 'released_count' => (int) $count, 'released_at' => current_time( 'mysql' ) ] );
    }

    public function release_hold( WP_REST_Request $request ) {
        global $wpdb;
        $hold = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM cinema_seat_holds WHERE SeatHoldId = %d', absint( $request['id'] ) ) );
        if ( ! $hold ) {
            return new WP_Error( 'not_found', 'Không tìm thấy hold.', [ 'status' => 404 ] );
        }
        $current_user = Cinema_Auth::get_current_cinema_user_id();
        if ( (int) $hold->UserId !== $current_user && ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'forbidden', 'Không có quyền huỷ hold này.', [ 'status' => 403 ] );
        }
        $wpdb->update( 'cinema_seat_holds', [ 'Status' => 'Released', 'ReleasedAt' => current_time( 'mysql' ) ], [ 'SeatHoldId' => (int) $hold->SeatHoldId ] );
        return new WP_REST_Response( null, 204 );
    }
}

new Cinema_REST_API();
