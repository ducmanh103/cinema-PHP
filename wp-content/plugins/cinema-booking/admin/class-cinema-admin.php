<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_Admin {
    const PAID_STATUSES = "'Completed','Paid'";

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_init', [ $this, 'handle_actions' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menus() {
        add_menu_page(
            'CinemaHub Admin',
            'CinemaHub',
            'manage_options',
            'cinema-dashboard',
            [ $this, 'view_dashboard' ],
            self::menu_icon_uri(),
            30
        );

        add_submenu_page(
            'cinema-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'cinema-dashboard',
            [ $this, 'view_dashboard' ]
        );

        add_submenu_page(
            'cinema-dashboard',
            'Quản lý phim',
            'Quản lý phim',
            'manage_options',
            'cinema-movies',
            [ $this, 'view_movies' ]
        );

        add_submenu_page(
            'cinema-dashboard',
            'Quản lý suất chiếu',
            'Suất chiếu',
            'manage_options',
            'cinema-showtimes',
            [ $this, 'view_showtimes' ]
        );

        add_submenu_page(
            'cinema-dashboard',
            'Quản lý người dùng',
            'Người dùng',
            'manage_options',
            'cinema-users',
            [ $this, 'view_users' ]
        );

        add_submenu_page(
            'cinema-dashboard',
            'Quản lý vé',
            'Vé đã đặt',
            'manage_options',
            'cinema-tickets',
            [ $this, 'view_tickets' ]
        );

        add_submenu_page(
            'cinema-dashboard',
            'Thống kê doanh thu',
            'Doanh thu',
            'manage_options',
            'cinema-revenue',
            [ $this, 'view_revenue' ]
        );

        add_submenu_page(
            null,
            'Chi tiết phim',
            'Chi tiết phim',
            'manage_options',
            'cinema-movie-detail',
            [ $this, 'view_movie_detail' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'cinema' ) ) {
            return;
        }

        wp_enqueue_style(
            'cinema-admin',
            CINEMA_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            CINEMA_PLUGIN_VERSION
        );
    }

    public function handle_actions() {
        if ( empty( $_POST['cinema_admin_action'] ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Bạn không có quyền truy cập chức năng này.' );
        }

        check_admin_referer( 'cinema_admin_action', 'cinema_admin_nonce' );

        $action = sanitize_key( $_POST['cinema_admin_action'] );
        $result = false;

        switch ( $action ) {
            case 'save_movie':
                $result = $this->save_movie();
                break;
            case 'delete_movie':
                $result = $this->delete_movie();
                break;
            case 'save_showtime':
                $result = $this->save_showtime();
                break;
            case 'delete_showtime':
                $result = $this->delete_showtime();
                break;
            case 'change_user_role':
                $result = $this->change_user_role();
                break;
            case 'toggle_user_status':
                $result = $this->toggle_user_status();
                break;
            case 'delete_ticket':
                $result = $this->delete_ticket();
                break;
            case 'cancel_ticket':
                $result = $this->cancel_ticket();
                break;
            case 'mark_payment_completed':
                $result = $this->mark_payment_completed();
                break;
        }

        $redirect = wp_get_referer() ?: admin_url( 'admin.php?page=cinema-dashboard' );
        $redirect = remove_query_arg( [ 'cinema_message', 'cinema_error' ], $redirect );

        if ( is_wp_error( $result ) ) {
            $redirect = add_query_arg( 'cinema_error', $result->get_error_message(), $redirect );
        } else {
            $redirect = add_query_arg( 'cinema_message', $result ?: 'Đã cập nhật dữ liệu.', $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    private function save_movie() {
        global $wpdb;

        $movie_id = absint( $_POST['movie_id'] ?? 0 );
        $is_update = $movie_id > 0;
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        if ( '' === $title ) {
            return new WP_Error( 'missing_title', 'Vui lòng nhập tên phim.' );
        }

        $duration = max( 1, absint( $_POST['duration'] ?? 0 ) );
        $slug = sanitize_title( $_POST['slug'] ?? $title );
        $slug = $this->unique_movie_slug( $slug, $movie_id );

        $data = [
            'Title'       => $title,
            'Duration'    => $duration,
            'Description' => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
            'ReleaseDate' => $this->normalize_date( $_POST['release_date'] ?? '' ),
            'PosterUrl'   => esc_url_raw( $_POST['poster_url'] ?? '' ),
            'BannerUrl'   => esc_url_raw( $_POST['banner_url'] ?? '' ),
            'Slug'        => $slug,
            'Status'      => sanitize_text_field( $_POST['status'] ?? 'Now Showing' ),
        ];

        if ( $movie_id ) {
            $ok = $wpdb->update( 'cinema_movies', $data, [ 'MovieId' => $movie_id ] );
        } else {
            $data['CreatedAt'] = current_time( 'mysql' );
            $ok = $wpdb->insert( 'cinema_movies', $data );
            $movie_id = (int) $wpdb->insert_id;
        }

        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể lưu phim: ' . $wpdb->last_error );
        }

        $genre_ids = array_map( 'absint', (array) ( $_POST['genre_ids'] ?? [] ) );
        $wpdb->delete( 'cinema_movie_genres', [ 'MovieId' => $movie_id ] );
        foreach ( array_filter( array_unique( $genre_ids ) ) as $genre_id ) {
            $wpdb->insert( 'cinema_movie_genres', [ 'MovieId' => $movie_id, 'GenreId' => $genre_id ], [ '%d', '%d' ] );
        }

        $this->clear_theme_cache();
        return $is_update ? 'Đã lưu phim.' : 'Đã thêm phim mới.';
    }

    private function delete_movie() {
        global $wpdb;
        $movie_id = absint( $_POST['movie_id'] ?? 0 );
        if ( ! $movie_id ) {
            return new WP_Error( 'invalid_movie', 'Phim không hợp lệ.' );
        }

        $showtime_count = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM cinema_showtimes WHERE MovieId = %d',
            $movie_id
        ) );
        if ( $showtime_count > 0 ) {
            return new WP_Error( 'movie_has_showtimes', 'Không thể xoá phim đã có suất chiếu. Hãy xoá suất chiếu trước.' );
        }

        $wpdb->delete( 'cinema_movie_genres', [ 'MovieId' => $movie_id ] );
        $ok = $wpdb->delete( 'cinema_movies', [ 'MovieId' => $movie_id ] );
        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể xoá phim: ' . $wpdb->last_error );
        }

        $this->clear_theme_cache();
        return 'Đã xoá phim.';
    }

    private function save_showtime() {
        global $wpdb;

        $showtime_id = absint( $_POST['showtime_id'] ?? 0 );
        $movie_id = absint( $_POST['movie_id'] ?? 0 );
        $room_id = absint( $_POST['room_id'] ?? 0 );
        $start_time = $this->normalize_datetime( $_POST['start_time'] ?? '' );
        $price = max( 0, (float) ( $_POST['price'] ?? 0 ) );

        if ( ! $movie_id || ! $room_id || ! $start_time || $price <= 0 ) {
            return new WP_Error( 'invalid_showtime', 'Vui lòng nhập đủ phim, phòng, thời gian và giá vé.' );
        }

        $duration = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT Duration FROM cinema_movies WHERE MovieId = %d',
            $movie_id
        ) );
        $end_time = $duration ? date( 'Y-m-d H:i:s', strtotime( $start_time . " +{$duration} minutes" ) ) : null;

        if ( $duration && $this->showtime_overlaps( $room_id, $start_time, $end_time, $showtime_id ) ) {
            return new WP_Error( 'showtime_overlap', 'Thời gian chiếu bị trùng với một suất chiếu khác trong cùng phòng chiếu.' );
        }

        $data = [
            'MovieId'   => $movie_id,
            'RoomId'    => $room_id,
            'StartTime' => $start_time,
            'EndTime'   => $end_time,
            'Price'     => $price,
        ];

        if ( $showtime_id ) {
            $ok = $wpdb->update( 'cinema_showtimes', $data, [ 'ShowtimeId' => $showtime_id ] );
        } else {
            $ok = $wpdb->insert( 'cinema_showtimes', $data );
        }

        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể lưu suất chiếu: ' . $wpdb->last_error );
        }

        return 'Đã lưu suất chiếu.';
    }

    private function delete_showtime() {
        global $wpdb;
        $showtime_id = absint( $_POST['showtime_id'] ?? 0 );
        if ( ! $showtime_id ) {
            return new WP_Error( 'invalid_showtime', 'Suất chiếu không hợp lệ.' );
        }

        $ticket_count = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM cinema_tickets WHERE ShowtimeId = %d',
            $showtime_id
        ) );
        if ( $ticket_count > 0 ) {
            return new WP_Error( 'showtime_has_tickets', 'Không thể xoá suất chiếu đã có vé đặt.' );
        }

        $wpdb->delete( 'cinema_seat_holds', [ 'ShowtimeId' => $showtime_id ] );
        $ok = $wpdb->delete( 'cinema_showtimes', [ 'ShowtimeId' => $showtime_id ] );
        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể xoá suất chiếu: ' . $wpdb->last_error );
        }

        return 'Đã xoá suất chiếu.';
    }

    private function change_user_role() {
        global $wpdb;
        $user_id = absint( $_POST['user_id'] ?? 0 );
        $role_id = absint( $_POST['role_id'] ?? 0 );

        if ( ! $user_id || ! $role_id ) {
            return new WP_Error( 'invalid_user', 'Người dùng hoặc vai trò không hợp lệ.' );
        }

        if ( $this->is_last_admin( $user_id ) && 1 !== $role_id ) {
            return new WP_Error( 'last_admin', 'Không thể đổi vai trò của Admin cuối cùng.' );
        }

        $ok = $wpdb->update( 'cinema_users', [ 'RoleId' => $role_id ], [ 'UserId' => $user_id ] );
        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể đổi vai trò: ' . $wpdb->last_error );
        }

        return 'Đã đổi vai trò người dùng.';
    }

    private function toggle_user_status() {
        global $wpdb;
        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            return new WP_Error( 'invalid_user', 'Người dùng không hợp lệ.' );
        }

        if ( $this->is_last_admin( $user_id ) ) {
            return new WP_Error( 'last_admin', 'Không thể khoá Admin cuối cùng.' );
        }

        $current = $wpdb->get_var( $wpdb->prepare( 'SELECT Status FROM cinema_users WHERE UserId = %d', $user_id ) );
        $new_status = 'Active' === $current ? 'Locked' : 'Active';

        $ok = $wpdb->update( 'cinema_users', [ 'Status' => $new_status ], [ 'UserId' => $user_id ] );
        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể đổi trạng thái: ' . $wpdb->last_error );
        }

        return 'Đã cập nhật trạng thái người dùng.';
    }

    private function delete_ticket() {
        global $wpdb;
        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            return new WP_Error( 'invalid_ticket', 'Vé không hợp lệ.' );
        }

        $ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT ShowtimeId, SeatId FROM cinema_tickets WHERE TicketId = %d', $ticket_id ) );
        if ( ! $ticket ) {
            return new WP_Error( 'ticket_not_found', 'Không tìm thấy vé.' );
        }

        $wpdb->delete( 'cinema_payments', [ 'TicketId' => $ticket_id ] );
        if ( $ticket->SeatId ) {
            $wpdb->delete( 'cinema_seat_holds', [ 'ShowtimeId' => (int) $ticket->ShowtimeId, 'SeatId' => (int) $ticket->SeatId ] );
        }
        $ok = $wpdb->delete( 'cinema_tickets', [ 'TicketId' => $ticket_id ] );
        if ( false === $ok ) {
            return new WP_Error( 'db_error', 'Không thể xoá vé: ' . $wpdb->last_error );
        }

        return 'Đã xoá vé.';
    }

    private function cancel_ticket() {
        global $wpdb;
        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            return new WP_Error( 'invalid_ticket', 'Vé không hợp lệ.' );
        }

        $wpdb->update( 'cinema_tickets', [ 'Status' => 'Cancelled' ], [ 'TicketId' => $ticket_id ] );
        $wpdb->update( 'cinema_payments', [ 'Status' => 'Refunded' ], [ 'TicketId' => $ticket_id ] );
        return 'Đã huỷ vé.';
    }

    private function mark_payment_completed() {
        global $wpdb;
        $ticket_id = absint( $_POST['ticket_id'] ?? 0 );
        if ( ! $ticket_id ) {
            return new WP_Error( 'invalid_ticket', 'Vé không hợp lệ.' );
        }

        $payment_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT PaymentId FROM cinema_payments WHERE TicketId = %d', $ticket_id ) );
        if ( $payment_id ) {
            $wpdb->update(
                'cinema_payments',
                [ 'Status' => 'Completed', 'PaidAt' => current_time( 'mysql' ) ],
                [ 'TicketId' => $ticket_id ]
            );
        } else {
            $price = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT CASE WHEN s.SeatType = 'VIP' THEN st.Price + 10000 ELSE st.Price END
                 FROM cinema_tickets tk
                 JOIN cinema_showtimes st ON st.ShowtimeId = tk.ShowtimeId
                 LEFT JOIN cinema_seats s ON s.SeatId = tk.SeatId
                 WHERE tk.TicketId = %d",
                $ticket_id
            ) );
            $wpdb->insert( 'cinema_payments', [
                'TicketId' => $ticket_id,
                'Amount' => $price,
                'Method' => 'Cash',
                'Status' => 'Completed',
                'TransactionId' => 'ADMIN' . date( 'YmdHis' ),
                'PaidAt' => current_time( 'mysql' ),
            ] );
        }

        return 'Đã xác nhận thanh toán.';
    }

    private function showtime_overlaps( $room_id, $new_start, $new_end, $ignore_showtime_id = 0 ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT st.ShowtimeId, st.StartTime, COALESCE(st.EndTime, DATE_ADD(st.StartTime, INTERVAL m.Duration MINUTE)) AS EndTime
             FROM cinema_showtimes st
             JOIN cinema_movies m ON m.MovieId = st.MovieId
             WHERE st.RoomId = %d
               AND st.ShowtimeId <> %d
               AND st.StartTime BETWEEN DATE_SUB(%s, INTERVAL 1 DAY) AND DATE_ADD(%s, INTERVAL 1 DAY)",
            $room_id,
            $ignore_showtime_id,
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

    private function is_last_admin( $user_id ) {
        global $wpdb;
        $role_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT RoleId FROM cinema_users WHERE UserId = %d', $user_id ) );
        if ( 1 !== $role_id ) {
            return false;
        }

        $admins = (int) $wpdb->get_var( "SELECT COUNT(*) FROM cinema_users WHERE RoleId = 1 AND Status = 'Active'" );
        return $admins <= 1;
    }

    private function unique_movie_slug( $slug, $movie_id = 0 ) {
        global $wpdb;
        $base = $slug ?: 'phim';
        $candidate = $base;
        $i = 2;

        while ( true ) {
            $exists = (int) $wpdb->get_var( $wpdb->prepare(
                'SELECT MovieId FROM cinema_movies WHERE Slug = %s AND MovieId <> %d LIMIT 1',
                $candidate,
                $movie_id
            ) );

            if ( ! $exists ) {
                return $candidate;
            }

            $candidate = $base . '-' . $i;
            $i++;
        }
    }

    private function normalize_date( $date ) {
        $date = sanitize_text_field( $date );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : null;
    }

    private function normalize_datetime( $datetime ) {
        $datetime = sanitize_text_field( $datetime );
        if ( ! $datetime ) {
            return null;
        }

        $timestamp = strtotime( $datetime );
        return $timestamp ? date( 'Y-m-d H:i:s', $timestamp ) : null;
    }

    private function clear_theme_cache() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cinema_%'" );
    }

    public function view_dashboard() {
        require CINEMA_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function view_movies() {
        require CINEMA_PLUGIN_DIR . 'admin/views/movies.php';
    }

    public function view_showtimes() {
        require CINEMA_PLUGIN_DIR . 'admin/views/showtimes.php';
    }

    public function view_users() {
        require CINEMA_PLUGIN_DIR . 'admin/views/users.php';
    }

    public function view_tickets() {
        require CINEMA_PLUGIN_DIR . 'admin/views/tickets.php';
    }

    public function view_revenue() {
        require CINEMA_PLUGIN_DIR . 'admin/views/revenue.php';
    }

    public function view_movie_detail() {
        require CINEMA_PLUGIN_DIR . 'admin/views/movie-detail.php';
    }

    public static function admin_header( $title, $subtitle = '' ) {
        $message = isset( $_GET['cinema_message'] ) ? sanitize_text_field( wp_unslash( $_GET['cinema_message'] ) ) : '';
        $error = isset( $_GET['cinema_error'] ) ? sanitize_text_field( wp_unslash( $_GET['cinema_error'] ) ) : '';
        ?>
        <div class="wrap cinema-admin">
            <div class="cinema-brand-bar">
                <a class="cinema-brand" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-dashboard' ) ); ?>" aria-label="CinemaHub Admin">
                    <span class="cinema-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="24" cy="24" r="22" fill="#e50914"/>
                            <circle cx="24" cy="24" r="22" fill="#ff4757" fill-opacity="0.18"/>
                            <circle cx="24" cy="24" r="22" fill="none" stroke="#ffb3b8" stroke-opacity="0.45" stroke-width="1"/>
                            <g fill="#0f1014">
                                <circle cx="24" cy="8.5" r="2.3"/>
                                <circle cx="39.5" cy="24" r="2.3"/>
                                <circle cx="24" cy="39.5" r="2.3"/>
                                <circle cx="8.5" cy="24" r="2.3"/>
                                <circle cx="35" cy="13" r="1.9" opacity="0.85"/>
                                <circle cx="35" cy="35" r="1.9" opacity="0.85"/>
                                <circle cx="13" cy="35" r="1.9" opacity="0.85"/>
                                <circle cx="13" cy="13" r="1.9" opacity="0.85"/>
                            </g>
                            <circle cx="24" cy="24" r="9.5" fill="#ffffff"/>
                            <path d="M21 18.5 L31 24 L21 29.5 Z" fill="#e50914"/>
                        </svg>
                    </span>
                    <span class="cinema-brand-text">
                        <span class="cinema-brand-name"><span class="cinema-brand-name-main">Cinema</span><span class="cinema-brand-name-accent">Hub</span></span>
                        <span class="cinema-brand-tag">ADMIN · BOOK · WATCH · ENJOY</span>
                    </span>
                </a>
                <a class="cinema-home-link" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">↗ Về trang chính</a>
            </div>
            <div class="cinema-page-title">
                <div>
                    <h1><?php echo esc_html( $title ); ?></h1>
                    <?php if ( $subtitle ) : ?><p><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>
                </div>
            </div>
            <?php if ( $message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
            <?php endif; ?>
            <?php if ( $error ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
            <?php endif; ?>
        <?php
    }

    /**
     * Build a base64 data URI for the WP admin sidebar menu icon.
     * Uses a single light-grey fill so it stays crisp at 20px and follows WP icon convention.
     */
    public static function menu_icon_uri() {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#a7aaad">'
            . '<path fill-rule="evenodd" clip-rule="evenodd" d="M10 1a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm0 2.4a6.6 6.6 0 1 1 0 13.2 6.6 6.6 0 0 1 0-13.2Z"/>'
            . '<circle cx="10" cy="10" r="2.4"/>'
            . '<circle cx="10" cy="5.4" r="0.85"/>'
            . '<circle cx="14.6" cy="10" r="0.85"/>'
            . '<circle cx="10" cy="14.6" r="0.85"/>'
            . '<circle cx="5.4" cy="10" r="0.85"/>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    public static function admin_footer() {
        echo '</div>';
    }

    public static function nonce_fields( $action ) {
        wp_nonce_field( 'cinema_admin_action', 'cinema_admin_nonce' );
        echo '<input type="hidden" name="cinema_admin_action" value="' . esc_attr( $action ) . '">';
    }

    public static function format_price( $amount ) {
        return number_format( (float) $amount, 0, ',', '.' ) . ' ₫';
    }

    public static function asset_url( $url ) {
        if ( empty( $url ) ) {
            return '';
        }

        if ( preg_match( '#^https?://#i', $url ) ) {
            return $url;
        }

        if ( 0 === strpos( $url, '/wp-content/' ) ) {
            return home_url( $url );
        }

        return $url;
    }

    public static function movie_status_label( $status ) {
        $map = [
            'Now Showing' => [ 'Đang chiếu', 'success' ],
            'Coming Soon' => [ 'Sắp chiếu', 'warning' ],
            'Ended'       => [ 'Ngừng chiếu', 'default' ],
        ];
        $item = $map[ $status ] ?? [ $status, 'default' ];
        return '<span class="cinema-label cinema-label-' . esc_attr( $item[1] ) . '">' . esc_html( $item[0] ) . '</span>';
    }

    public static function payment_status_label( $status ) {
        $map = [
            'Completed' => [ 'Đã thanh toán', 'success' ],
            'Paid'      => [ 'Đã thanh toán', 'success' ],
            'Pending'   => [ 'Chờ thanh toán', 'warning' ],
            'Refunded'  => [ 'Đã hoàn tiền', 'default' ],
            'Failed'    => [ 'Thất bại', 'danger' ],
        ];
        $item = $map[ $status ] ?? [ $status, 'default' ];
        return '<span class="cinema-label cinema-label-' . esc_attr( $item[1] ) . '">' . esc_html( $item[0] ) . '</span>';
    }
}

new Cinema_Admin();
