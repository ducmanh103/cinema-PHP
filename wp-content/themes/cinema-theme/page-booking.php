<?php
/**
 * page-booking.php — Trang đặt vé: chọn ghế + thanh toán
 * URL: /ve/[showtime_id]/
 */
if ( ! is_user_logged_in() ) {
    $current_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url( '/' );
    wp_safe_redirect( add_query_arg( 'redirect_to', $current_url, home_url( '/dang-nhap/' ) ) );
    exit;
}

global $wpdb;
$showtime_id = absint( get_query_var( 'cinema_showtime_id' ) ?: ( $_GET['showtime'] ?? 0 ) );

if ( ! $showtime_id ) { wp_safe_redirect( home_url( '/' ) ); exit; }

$showtime = $wpdb->get_row( $wpdb->prepare(
    "SELECT st.*, m.Title, m.Duration, m.PosterUrl, m.Slug,
            r.RoomName, r.RoomId, t.Name AS TheaterName, t.City
     FROM cinema_showtimes st
     JOIN cinema_movies m   ON st.MovieId = m.MovieId
     JOIN cinema_rooms r    ON st.RoomId  = r.RoomId
     JOIN cinema_theaters t ON r.TheaterId = t.TheaterId
     WHERE st.ShowtimeId = %d AND st.StartTime > NOW()", $showtime_id
) );

if ( ! $showtime ) {
    wp_safe_redirect( home_url( '/' ) );
    exit;
}

$seat_map = cinema_get_seat_map( $showtime_id );

// Nhóm ghế theo hàng (A, B, C...)
$rows = [];
foreach ( $seat_map as $seat ) {
    $row = substr( $seat->SeatNumber, 0, 1 );
    $rows[ $row ][] = $seat;
}

$current_user = wp_get_current_user();
$cinema_user = $wpdb->get_row( $wpdb->prepare(
    "SELECT cu.*, cr.RoleName FROM cinema_users cu JOIN cinema_roles cr ON cr.RoleId = cu.RoleId WHERE cu.WpUserId = %d",
    get_current_user_id()
) );
$can_pay_cash = $cinema_user && in_array( $cinema_user->RoleName, [ 'Admin', 'Staff' ], true );

get_header();
?>

<div class="booking-page">
<div class="container">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo home_url('/'); ?>">Trang Chủ</a> ›
        <a href="<?php echo home_url('/phim/' . $showtime->Slug . '/'); ?>"><?php echo esc_html($showtime->Title); ?></a> ›
        <span>Chọn Ghế</span>
    </nav>

    <div class="booking-layout">

        <!-- LEFT: Seat Map -->
        <div class="booking-left">
            <div class="cinema-screen">
                <div class="screen-label">🎬 MÀN HÌNH</div>
                <div class="screen-curve"></div>
            </div>

            <!-- Seat Legend -->
            <div class="seat-legend">
                <span class="legend-item"><span class="seat-dot seat-available"></span> Trống</span>
                <span class="legend-item"><span class="seat-dot seat-selected"></span> Đang Chọn</span>
                <span class="legend-item"><span class="seat-dot seat-held"></span> Đang Giữ</span>
                <span class="legend-item"><span class="seat-dot seat-booked"></span> Đã Đặt</span>
                <span class="legend-item"><span class="seat-dot seat-vip"></span> VIP</span>
            </div>

            <!-- SVG Seat Grid -->
            <div class="seat-grid" id="seat-grid"
                 data-showtime="<?php echo esc_attr($showtime_id); ?>"
                 data-price-standard="<?php echo esc_attr($showtime->Price); ?>"
                 data-price-vip="<?php echo esc_attr($showtime->Price + 10000); ?>">

                <?php foreach ( $rows as $row_label => $seats ) : ?>
                <div class="seat-row">
                    <span class="row-label"><?php echo esc_html($row_label); ?></span>
                    <div class="seats">
                        <?php foreach ( $seats as $seat ) :
                            $classes = 'seat seat-' . esc_attr($seat->Status);
                            if ( $seat->SeatType === 'VIP' ) $classes .= ' seat-vip-type';
                        ?>
                        <button class="<?php echo $classes; ?>"
                                id="seat-<?php echo esc_attr($seat->SeatId); ?>"
                                data-seat-id="<?php echo esc_attr($seat->SeatId); ?>"
                                data-seat-number="<?php echo esc_attr($seat->SeatNumber); ?>"
                                data-seat-type="<?php echo esc_attr($seat->SeatType); ?>"
                                <?php echo $seat->Status !== 'available' ? 'disabled' : ''; ?>
                                aria-label="Ghế <?php echo esc_attr($seat->SeatNumber); ?> - <?php echo esc_attr($seat->SeatType); ?>">
                            <?php echo esc_html(substr($seat->SeatNumber, 1)); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- RIGHT: Booking Summary -->
        <div class="booking-right" id="booking-summary">
            <!-- Movie Info -->
            <div class="booking-movie">
                <img src="<?php echo esc_url( cinema_asset_url( $showtime->PosterUrl ) ); ?>"
                     alt="<?php echo esc_attr($showtime->Title); ?>"
                     class="booking-poster">
                <div>
                    <h2 class="booking-title"><?php echo esc_html($showtime->Title); ?></h2>
                    <p class="booking-meta">📍 <?php echo esc_html($showtime->TheaterName); ?></p>
                    <p class="booking-meta">🎭 <?php echo esc_html($showtime->RoomName); ?></p>
                    <p class="booking-meta">📅 <?php echo date('d/m/Y H:i', strtotime($showtime->StartTime)); ?></p>
                    <p class="booking-meta">⏱ <?php echo cinema_format_duration($showtime->Duration); ?></p>
                </div>
            </div>

            <!-- Hold Countdown (hiện khi đã hold ghế) -->
            <div class="hold-timer" id="hold-timer" style="display:none">
                <div class="timer-label">⏰ Thời gian giữ ghế:</div>
                <div class="timer-display" id="timer-display">10:00</div>
                <p class="timer-note">Ghế sẽ được giải phóng sau thời gian trên</p>
            </div>

            <!-- Selected Seats -->
            <div class="selected-seats" id="selected-seats">
                <h3>Ghế Đã Chọn</h3>
                <div class="seats-list" id="seats-list">
                    <p class="empty-state">Chưa chọn ghế nào</p>
                </div>
            </div>

            <!-- Price Summary -->
            <div class="price-summary" id="price-summary" style="display:none">
                <div class="price-row">
                    <span>Giá vé:</span>
                    <span id="price-detail">—</span>
                </div>
                <div class="price-total">
                    <span>Tổng cộng:</span>
                    <strong id="price-total">0 ₫</strong>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="payment-method" id="payment-method" style="display:none">
                <h3>Phương Thức Thanh Toán</h3>
                <?php if ( $can_pay_cash ) : ?>
                <label class="payment-option">
                    <input type="radio" name="payment" value="Cash" checked>
                    <span>Tiền Mặt (tại quầy)</span>
                </label>
                <?php endif; ?>
                <label class="payment-option payment-option-vnpay">
                    <input type="radio" name="payment" value="VNPay" <?php checked( ! $can_pay_cash ); ?>>
                    <span class="vnpay-logo" aria-hidden="true">
                        <svg viewBox="0 0 32 32" width="22" height="22" xmlns="http://www.w3.org/2000/svg">
                            <rect width="32" height="32" rx="7" fill="#0F4C9F"/>
                            <path d="M6.5 9h4.6l3.4 9.4L17.9 9h4.6l-5.6 14h-5z" fill="#fff"/>
                            <path d="M22.6 18.5h2.9v4.5h-2.9z" fill="#E50914"/>
                        </svg>
                    </span>
                    <span>VNPay <small>(QR / ATM / Visa)</small></span>
                </label>
            </div>

            <!-- Actions -->
            <div class="booking-actions">
                <button class="btn btn-primary btn-block btn-lg" id="btn-hold"
                        disabled style="display:none">
                    Đặt Vé
                </button>
                <button class="btn btn-success btn-block btn-lg" id="btn-confirm"
                        disabled style="display:none">
                    Thanh Toán
                </button>
                <button class="btn btn-outline btn-block" id="btn-cancel" style="display:none">
                    Hủy Chọn
                </button>
            </div>

            <!-- User Info (ẩn, dùng cho AJAX) -->
            <input type="hidden" id="current-user-id" value="<?php echo get_current_user_id(); ?>">
            <input type="hidden" id="showtime-id" value="<?php echo esc_attr($showtime_id); ?>">
        </div>

    </div><!-- .booking-layout -->
</div><!-- .container -->
</div><!-- .booking-page -->

<!-- Success Modal -->
<div class="modal-overlay" id="success-modal" style="display:none" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-icon success">✅</div>
        <h2 class="modal-title">Đặt Vé Thành Công!</h2>
        <p class="modal-desc" id="modal-booking-info">—</p>
        <div class="modal-actions">
            <a href="<?php echo home_url('/profile/'); ?>" class="btn btn-primary">Xem Vé Của Tôi</a>
            <a href="<?php echo home_url('/'); ?>" class="btn btn-outline">Về Trang Chủ</a>
        </div>
    </div>
</div>

<?php get_footer(); ?>
