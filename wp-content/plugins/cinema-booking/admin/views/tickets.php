<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$tickets = $wpdb->get_results(
    "SELECT tk.TicketId, tk.BookingTime, tk.Status,
            u.Username, u.FullName, u.Email,
            m.Title, st.StartTime, r.RoomName, s.SeatNumber, s.SeatType,
            COALESCE(p.Amount, CASE WHEN s.SeatType = 'VIP' THEN st.Price + 10000 ELSE st.Price END) AS Amount,
            p.Method, p.Status AS PaymentStatus
     FROM cinema_tickets tk
     JOIN cinema_users u ON tk.UserId = u.UserId
     JOIN cinema_showtimes st ON tk.ShowtimeId = st.ShowtimeId
     JOIN cinema_movies m ON st.MovieId = m.MovieId
     JOIN cinema_rooms r ON st.RoomId = r.RoomId
     LEFT JOIN cinema_seats s ON tk.SeatId = s.SeatId
     LEFT JOIN cinema_payments p ON p.TicketId = tk.TicketId
     ORDER BY tk.BookingTime DESC
     LIMIT 200"
);

Cinema_Admin::admin_header( 'Vé đã đặt', 'Quản lý giao dịch vé và thanh toán' );
?>

<div class="cinema-box">
    <div class="cinema-box-header">
        <h2 class="cinema-box-title">🎟 Vé đã đặt (<?php echo count( $tickets ); ?>)</h2>
    </div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:80px">Mã vé</th>
                    <th>Khách hàng</th>
                    <th>Phim</th>
                    <th>Suất chiếu</th>
                    <th style="width:70px">Ghế</th>
                    <th style="width:110px">Giá</th>
                    <th style="width:120px">Vé</th>
                    <th style="width:140px">Thanh toán</th>
                    <th style="width:130px">Ngày đặt</th>
                    <th style="width:170px">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $tickets ) : foreach ( $tickets as $ticket ) :
                    $is_future = strtotime( $ticket->StartTime ) > time();
                    ?>
                    <tr>
                        <td><strong>#<?php echo esc_html( str_pad( $ticket->TicketId, 6, '0', STR_PAD_LEFT ) ); ?></strong></td>
                        <td><?php echo esc_html( $ticket->FullName ?: $ticket->Username ); ?><br><small><?php echo esc_html( $ticket->Email ); ?></small></td>
                        <td><strong><?php echo esc_html( $ticket->Title ); ?></strong></td>
                        <td><?php echo esc_html( date( 'H:i d/m/Y', strtotime( $ticket->StartTime ) ) ); ?><br><small><?php echo esc_html( $ticket->RoomName ); ?></small></td>
                        <td><?php echo esc_html( $ticket->SeatNumber ?: 'N/A' ); ?></td>
                        <td><?php echo esc_html( Cinema_Admin::format_price( $ticket->Amount ) ); ?></td>
                        <td>
                            <?php if ( 'Booked' === $ticket->Status ) : ?>
                                <span class="cinema-label cinema-label-success">Đã đặt</span>
                            <?php elseif ( 'Cancelled' === $ticket->Status ) : ?>
                                <span class="cinema-label cinema-label-danger">Đã huỷ</span>
                            <?php else : ?>
                                <span class="cinema-label cinema-label-default"><?php echo esc_html( $ticket->Status ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo Cinema_Admin::payment_status_label( $ticket->PaymentStatus ); ?>
                            <br><small><?php echo esc_html( $ticket->Method ?: 'N/A' ); ?></small>
                        </td>
                        <td><?php echo esc_html( date( 'H:i d/m/Y', strtotime( $ticket->BookingTime ) ) ); ?></td>
                        <td>
                            <?php if ( 'Booked' === $ticket->Status && $is_future ) : ?>
                                <form method="post" class="cinema-inline-form" onsubmit="return confirm('Huỷ vé #<?php echo intval( $ticket->TicketId ); ?>?');">
                                    <?php Cinema_Admin::nonce_fields( 'cancel_ticket' ); ?>
                                    <input type="hidden" name="ticket_id" value="<?php echo intval( $ticket->TicketId ); ?>">
                                    <button type="submit" class="button button-small">Huỷ</button>
                                </form>
                            <?php endif; ?>
                            <?php if ( ! in_array( $ticket->PaymentStatus, [ 'Completed', 'Paid' ], true ) && 'Booked' === $ticket->Status ) : ?>
                                <form method="post" class="cinema-inline-form">
                                    <?php Cinema_Admin::nonce_fields( 'mark_payment_completed' ); ?>
                                    <input type="hidden" name="ticket_id" value="<?php echo intval( $ticket->TicketId ); ?>">
                                    <button type="submit" class="button button-small">Đã TT</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" class="cinema-inline-form" onsubmit="return confirm('Xoá vé #<?php echo intval( $ticket->TicketId ); ?> khỏi DB?');">
                                <?php Cinema_Admin::nonce_fields( 'delete_ticket' ); ?>
                                <input type="hidden" name="ticket_id" value="<?php echo intval( $ticket->TicketId ); ?>">
                                <button type="submit" class="button button-small button-link-delete">Xoá</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="10">Chưa có vé nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php Cinema_Admin::admin_footer(); ?>
