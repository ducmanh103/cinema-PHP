<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$paid_statuses = Cinema_Admin::PAID_STATUSES;
$total_movies = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM cinema_movies' );
$total_users = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM cinema_users' );
$total_tickets = (int) $wpdb->get_var( "SELECT COUNT(*) FROM cinema_tickets WHERE Status = 'Booked'" );
$total_revenue = (float) $wpdb->get_var( "SELECT COALESCE(SUM(Amount),0) FROM cinema_payments WHERE Status IN ({$paid_statuses})" );

$recent_tickets = $wpdb->get_results(
    "SELECT tk.TicketId, tk.BookingTime, tk.Status,
            u.FullName, u.Username,
            m.Title AS MovieTitle, st.StartTime, r.RoomName, s.SeatNumber,
            p.Amount, p.Status AS PaymentStatus
     FROM cinema_tickets tk
     JOIN cinema_users u ON u.UserId = tk.UserId
     JOIN cinema_showtimes st ON st.ShowtimeId = tk.ShowtimeId
     JOIN cinema_movies m ON m.MovieId = st.MovieId
     JOIN cinema_rooms r ON r.RoomId = st.RoomId
     LEFT JOIN cinema_seats s ON s.SeatId = tk.SeatId
     LEFT JOIN cinema_payments p ON p.TicketId = tk.TicketId
     ORDER BY tk.BookingTime DESC
     LIMIT 20"
);

Cinema_Admin::admin_header( 'Dashboard', 'Cinema Management' );
?>

<div class="cinema-stats">
    <div class="cinema-small-box cinema-bg-aqua">
        <div class="inner"><h3><?php echo esc_html( $total_movies ); ?></h3><p>Tổng phim</p></div>
        <div class="icon">🎞</div>
        <a class="footer" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-movies' ) ); ?>">Xem tất cả</a>
    </div>
    <div class="cinema-small-box cinema-bg-green">
        <div class="inner"><h3><?php echo esc_html( $total_users ); ?></h3><p>Người dùng</p></div>
        <div class="icon">👥</div>
        <a class="footer" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-users' ) ); ?>">Quản lý người dùng</a>
    </div>
    <div class="cinema-small-box cinema-bg-yellow">
        <div class="inner"><h3><?php echo esc_html( $total_tickets ); ?></h3><p>Tổng vé đặt</p></div>
        <div class="icon">🎟</div>
        <a class="footer" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-showtimes' ) ); ?>">Xem suất chiếu</a>
    </div>
    <div class="cinema-small-box cinema-bg-red">
        <div class="inner"><h3><?php echo esc_html( Cinema_Admin::format_price( $total_revenue ) ); ?></h3><p>Doanh thu</p></div>
        <div class="icon">💰</div>
        <a class="footer" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-revenue' ) ); ?>">Thống kê</a>
    </div>
</div>

<div class="cinema-box">
    <div class="cinema-box-header">
        <h2 class="cinema-box-title">⚡ Thao tác nhanh</h2>
    </div>
    <div class="cinema-box-body cinema-actions">
        <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-movies' ) ); ?>">Quản lý phim</a>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-showtimes' ) ); ?>">Quản lý suất chiếu</a>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-users' ) ); ?>">Quản lý người dùng</a>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-tickets' ) ); ?>">Quản lý vé</a>
        <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-revenue' ) ); ?>">Thống kê doanh thu</a>
    </div>
</div>

<div class="cinema-box">
    <div class="cinema-box-header">
        <h2 class="cinema-box-title">🎟 Vé đặt gần đây</h2>
    </div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px">#</th>
                    <th>Khách hàng</th>
                    <th>Phim</th>
                    <th>Phòng</th>
                    <th>Suất chiếu</th>
                    <th>Ghế</th>
                    <th>Giá</th>
                    <th>Thanh toán</th>
                    <th>Thời gian đặt</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $recent_tickets ) : foreach ( $recent_tickets as $ticket ) : ?>
                    <tr>
                        <td>#<?php echo intval( $ticket->TicketId ); ?></td>
                        <td><?php echo esc_html( $ticket->FullName ?: $ticket->Username ); ?></td>
                        <td><strong><?php echo esc_html( $ticket->MovieTitle ); ?></strong></td>
                        <td><?php echo esc_html( $ticket->RoomName ); ?></td>
                        <td><?php echo esc_html( date( 'H:i d/m/Y', strtotime( $ticket->StartTime ) ) ); ?></td>
                        <td><?php echo esc_html( $ticket->SeatNumber ?: 'N/A' ); ?></td>
                        <td><?php echo esc_html( Cinema_Admin::format_price( $ticket->Amount ) ); ?></td>
                        <td><?php echo Cinema_Admin::payment_status_label( $ticket->PaymentStatus ); ?></td>
                        <td><?php echo esc_html( date( 'H:i d/m/Y', strtotime( $ticket->BookingTime ) ) ); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="9">Chưa có vé nào được đặt.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php Cinema_Admin::admin_footer(); ?>
