<?php
/**
 * page-profile.php — Lịch sử vé của người dùng
 */
if ( ! is_user_logged_in() ) {
    $current_url = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : home_url( '/' );
    wp_safe_redirect( add_query_arg( 'redirect_to', $current_url, home_url( '/dang-nhap/' ) ) );
    exit;
}

global $wpdb;
$wp_user_id = get_current_user_id();
$current_user = wp_get_current_user();

// Lấy cinema_user tương ứng
$cinema_user = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM cinema_users WHERE WpUserId = %d", $wp_user_id
) );

$page    = max( 1, intval( $_GET['paged'] ?? 1 ) );
$limit   = 10;
$offset  = ( $page - 1 ) * $limit;
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );

$tickets = [];
$total   = 0;

if ( $cinema_user ) {
    $cinema_user_id = (int) $cinema_user->UserId;
    $where_sql = 'WHERE tk.UserId = %d';
    $where_args = [ $cinema_user_id ];
    if ( in_array( $status_filter, [ 'Booked', 'Cancelled', 'Used' ], true ) ) {
        $where_sql   .= ' AND tk.Status = %s';
        $where_args[] = $status_filter;
    }

    $total = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM cinema_tickets tk $where_sql",
        $where_args
    ) );

    $tickets = $wpdb->get_results( $wpdb->prepare(
        "SELECT tk.*, st.StartTime, st.Price,
                COALESCE(p.Amount, CASE WHEN s.SeatType = 'VIP' THEN st.Price + 10000 ELSE st.Price END) AS PaidAmount,
                p.Method AS PaymentMethod,
                p.Status AS PaymentStatus,
                m.Title AS MovieTitle, m.PosterUrl,
                r.RoomName, t.Name AS TheaterName,
                s.SeatNumber, s.SeatType
         FROM cinema_tickets tk
         JOIN cinema_showtimes st ON tk.ShowtimeId = st.ShowtimeId
         JOIN cinema_movies m     ON st.MovieId    = m.MovieId
         JOIN cinema_rooms r      ON st.RoomId     = r.RoomId
         JOIN cinema_theaters t   ON r.TheaterId   = t.TheaterId
         LEFT JOIN cinema_seats s ON s.SeatId = tk.SeatId
         LEFT JOIN cinema_payments p ON p.TicketId = tk.TicketId
         $where_sql
         ORDER BY tk.BookingTime DESC
         LIMIT %d OFFSET %d",
        array_merge( $where_args, [ $limit, $offset ] )
    ) );
}

$total_pages = $total > 0 ? ceil( $total / $limit ) : 1;

get_header();
?>

<div class="profile-page section">
<div class="container">

    <div class="profile-layout">

        <!-- Sidebar -->
        <aside class="profile-sidebar">
            <div class="profile-avatar">
                <?php echo get_avatar( $wp_user_id, 80, '', '', ['class' => 'avatar-img'] ); ?>
                <h3 class="profile-name"><?php echo esc_html( $current_user->display_name ); ?></h3>
                <p class="profile-email"><?php echo esc_html( $current_user->user_email ); ?></p>
            </div>
            <nav class="profile-nav">
                <a href="<?php echo home_url('/profile/'); ?>" class="active">🎟 Vé Của Tôi</a>
                <a href="<?php echo home_url('/profile/?section=settings'); ?>">⚙ Cài Đặt</a>
                <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>">🚪 Đăng Xuất</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="profile-main">
            <div class="profile-header-row">
                <h1 class="page-title">Vé Của Tôi</h1>
                <!-- Filter -->
                <div class="ticket-filter">
                    <?php
                    $filters = ['' => 'Tất Cả', 'Booked' => 'Đã Đặt', 'Used' => 'Đã Dùng', 'Cancelled' => 'Đã Hủy'];
                    foreach ( $filters as $val => $label ) :
                        $active = $status_filter === $val ? 'active' : '';
                    ?>
                    <a href="?status=<?php echo esc_attr( rawurlencode( $val ) ); ?>" class="filter-btn <?php echo esc_attr( $active ); ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
            // Payment-status banner sau khi quay về từ VNPay
            $payment_flag = isset( $_GET['payment'] ) ? sanitize_text_field( wp_unslash( $_GET['payment'] ) ) : '';
            $payment_messages = [
                'success'     => [ 'success', '✅ Thanh toán thành công! Vé của bạn đã được xác nhận.' ],
                'failed'      => [ 'danger',  '❌ Thanh toán thất bại. Vui lòng thử lại.' ],
                'cancelled'   => [ 'warning', '⚠ Bạn đã hủy giao dịch tại cổng VNPay. Vui lòng đặt vé lại nếu cần.' ],
                'pending'     => [ 'warning', '⏳ Giao dịch đang xử lý. Vui lòng kiểm tra lại sau ít phút.' ],
                'expired'     => [ 'warning', '⌛ Đơn thanh toán đã hết hạn. Vui lòng đặt vé lại.' ],
                'book_failed' => [ 'danger',  '⚠ Đã thanh toán nhưng đặt vé không thành công. Vui lòng liên hệ hỗ trợ để được hoàn tiền.' ],
                'invalid'     => [ 'danger',  '⚠ Giao dịch không hợp lệ.' ],
            ];
            if ( isset( $payment_messages[ $payment_flag ] ) ) :
                [ $cls, $msg ] = $payment_messages[ $payment_flag ];
            ?>
            <div class="payment-notice notice-<?php echo esc_attr( $cls ); ?>" role="status">
                <span><?php echo esc_html( $msg ); ?></span>
                <a href="<?php echo esc_url( home_url( '/profile/' ) ); ?>" class="payment-notice-close" aria-label="Đóng">×</a>
            </div>
            <?php endif; ?>

            <?php if ( ! $cinema_user ) : ?>
            <div class="notice notice-warning">
                <p>⚠ Tài khoản chưa được kích hoạt đầy đủ. Hãy liên hệ admin.</p>
            </div>

            <?php elseif ( empty( $tickets ) ) : ?>
            <div class="empty-tickets">
                <div class="empty-icon">🎟</div>
                <h3>Chưa có vé nào</h3>
                <p>Hãy đặt vé để xem phim yêu thích của bạn!</p>
                <a href="<?php echo home_url('/'); ?>" class="btn btn-primary">Xem Phim Ngay</a>
            </div>

            <?php else : ?>
            <div class="tickets-list">
                <?php foreach ( $tickets as $ticket ) :
                    $is_past    = strtotime( $ticket->StartTime ) < time();
                    $status_map = [ 'Booked' => ['label'=>'Đã Đặt','class'=>'success'], 'Used' => ['label'=>'Đã Dùng','class'=>'info'], 'Cancelled' => ['label'=>'Đã Hủy','class'=>'danger'] ];
                    $st         = $status_map[ $ticket->Status ] ?? ['label'=>$ticket->Status,'class'=>'default'];
                ?>
                <article class="ticket-card <?php echo $is_past ? 'ticket-past' : ''; ?>">
                    <div class="ticket-poster">
                        <img src="<?php echo esc_url( cinema_asset_url( $ticket->PosterUrl ) ); ?>"
                             alt="<?php echo esc_attr( $ticket->MovieTitle ); ?>">
                    </div>
                    <div class="ticket-info">
                        <h3 class="ticket-movie"><?php echo esc_html( $ticket->MovieTitle ); ?></h3>
                        <div class="ticket-meta">
                            <span>📍 <?php echo esc_html( $ticket->TheaterName ); ?></span>
                            <span>🎭 <?php echo esc_html( $ticket->RoomName ); ?></span>
                            <span>📅 <?php echo date( 'd/m/Y H:i', strtotime( $ticket->StartTime ) ); ?></span>
                            <?php if ( $ticket->SeatId && $ticket->SeatNumber ) : ?>
                            <span>💺 Ghế <?php echo esc_html( $ticket->SeatNumber ); ?> (<?php echo esc_html( $ticket->SeatType ); ?>)</span>
                            <?php endif; ?>
                            <span>💰 <?php echo cinema_format_price( $ticket->PaidAmount ); ?></span>
                        </div>
                    </div>
                    <div class="ticket-status">
                        <span class="status-badge status-<?php echo esc_attr( $st['class'] ); ?>">
                            <?php echo esc_html( $st['label'] ); ?>
                        </span>
                        <div class="ticket-id">#<?php echo esc_html( str_pad( $ticket->TicketId, 6, '0', STR_PAD_LEFT ) ); ?></div>
                        <?php if ( $ticket->Status === 'Booked' && ! $is_past ) : ?>
                        <button class="btn btn-outline btn-sm cancel-ticket"
                                data-ticket-id="<?php echo esc_attr( $ticket->TicketId ); ?>">
                            Hủy Vé
                        </button>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
            <nav class="pagination" aria-label="Phân trang vé">
                <?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
                <a href="?status=<?php echo esc_attr( rawurlencode( $status_filter ) ); ?>&amp;paged=<?php echo (int) $p; ?>"
                   class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                    <?php echo (int) $p; ?>
                </a>
                <?php endfor; ?>
            </nav>
            <?php endif; ?>

            <?php endif; ?>
        </main>

    </div><!-- .profile-layout -->
</div><!-- .container -->
</div><!-- .profile-page -->

<script>
document.querySelectorAll('.cancel-ticket').forEach(btn => {
    btn.addEventListener('click', function() {
        if (!confirm('Bạn có chắc muốn hủy vé này không?')) return;
        const ticketId = this.dataset.ticketId;
        fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=cinema_cancel_ticket&ticket_id=${ticketId}&nonce=<?php echo wp_create_nonce("cinema_cancel_nonce"); ?>`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Lỗi: ' + (data.data?.message || 'Không thể hủy vé'));
            }
        });
    });
});
</script>

<?php get_footer(); ?>
