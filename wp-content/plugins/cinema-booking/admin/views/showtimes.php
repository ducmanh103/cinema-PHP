<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$movies = $wpdb->get_results( "SELECT MovieId, Title, Duration FROM cinema_movies WHERE Status <> 'Ended' ORDER BY Title ASC" );
$rooms = $wpdb->get_results(
    "SELECT r.RoomId, r.RoomName, r.SeatCount, t.Name AS TheaterName
     FROM cinema_rooms r
     JOIN cinema_theaters t ON t.TheaterId = r.TheaterId
     ORDER BY t.Name ASC, r.RoomName ASC"
);
$showtimes = $wpdb->get_results(
    "SELECT st.*, m.Title, m.Duration, r.RoomName, r.SeatCount, t.Name AS TheaterName,
            COUNT(tk.TicketId) AS TicketCount
     FROM cinema_showtimes st
     JOIN cinema_movies m ON m.MovieId = st.MovieId
     JOIN cinema_rooms r ON r.RoomId = st.RoomId
     JOIN cinema_theaters t ON t.TheaterId = r.TheaterId
     LEFT JOIN cinema_tickets tk ON tk.ShowtimeId = st.ShowtimeId
     GROUP BY st.ShowtimeId
     ORDER BY st.StartTime DESC
     LIMIT 200"
);

Cinema_Admin::admin_header( 'Suất chiếu', 'Danh sách lịch chiếu phim' );
?>

<div class="cinema-box">
    <div class="cinema-box-header">
        <h2 class="cinema-box-title">📅 Danh sách suất chiếu (<?php echo count( $showtimes ); ?>)</h2>
        <button type="button" class="button button-primary" data-dialog="create-showtime">+ Thêm suất chiếu mới</button>
    </div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px">ID</th>
                    <th>Phim</th>
                    <th>Rạp</th>
                    <th>Phòng</th>
                    <th style="width:150px">Giờ chiếu</th>
                    <th style="width:110px">Giá vé</th>
                    <th style="width:110px">Trạng thái</th>
                    <th style="width:90px">Vé</th>
                    <th style="width:160px">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $showtimes ) : foreach ( $showtimes as $showtime ) :
                    $start_ts = strtotime( $showtime->StartTime );
                    $end_ts = $showtime->EndTime ? strtotime( $showtime->EndTime ) : strtotime( $showtime->StartTime . ' +' . intval( $showtime->Duration ) . ' minutes' );
                    if ( $start_ts > time() ) {
                        $status = '<span class="cinema-label cinema-label-success">Sắp chiếu</span>';
                    } elseif ( $end_ts > time() ) {
                        $status = '<span class="cinema-label cinema-label-warning">Đang chiếu</span>';
                    } else {
                        $status = '<span class="cinema-label cinema-label-default">Đã chiếu</span>';
                    }
                    ?>
                    <tr>
                        <td><?php echo intval( $showtime->ShowtimeId ); ?></td>
                        <td><strong><?php echo esc_html( $showtime->Title ); ?></strong></td>
                        <td><?php echo esc_html( $showtime->TheaterName ); ?></td>
                        <td><?php echo esc_html( $showtime->RoomName ); ?></td>
                        <td><?php echo esc_html( date( 'H:i d/m/Y', $start_ts ) ); ?></td>
                        <td><?php echo esc_html( Cinema_Admin::format_price( $showtime->Price ) ); ?></td>
                        <td><?php echo $status; ?></td>
                        <td><?php echo intval( $showtime->TicketCount ); ?>/<?php echo intval( $showtime->SeatCount ); ?></td>
                        <td>
                            <button type="button" class="button button-small" data-dialog="edit-showtime-<?php echo intval( $showtime->ShowtimeId ); ?>">Sửa</button>
                            <form method="post" class="cinema-inline-form" onsubmit="return confirm('Xoá suất chiếu #<?php echo intval( $showtime->ShowtimeId ); ?>?');">
                                <?php Cinema_Admin::nonce_fields( 'delete_showtime' ); ?>
                                <input type="hidden" name="showtime_id" value="<?php echo intval( $showtime->ShowtimeId ); ?>">
                                <button type="submit" class="button button-small button-link-delete">Xoá</button>
                            </form>
                        </td>
                    </tr>

                    <dialog class="cinema-modal" id="edit-showtime-<?php echo intval( $showtime->ShowtimeId ); ?>">
                        <form method="post">
                            <div class="cinema-modal-header">
                                <h2>Sửa suất chiếu #<?php echo intval( $showtime->ShowtimeId ); ?></h2>
                                <button type="button" class="cinema-modal-close" data-close>&times;</button>
                            </div>
                            <div class="cinema-modal-body">
                                <?php
                                Cinema_Admin::nonce_fields( 'save_showtime' );
                                include CINEMA_PLUGIN_DIR . 'admin/views/partials/showtime-form.php';
                                ?>
                                <p class="submit">
                                    <button type="submit" class="button button-primary">Cập nhật</button>
                                    <button type="button" class="button" data-close>Huỷ</button>
                                </p>
                            </div>
                        </form>
                    </dialog>
                <?php endforeach; else : ?>
                    <tr><td colspan="9">Chưa có suất chiếu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog class="cinema-modal" id="create-showtime">
    <form method="post">
        <div class="cinema-modal-header">
            <h2>Thêm suất chiếu mới</h2>
            <button type="button" class="cinema-modal-close" data-close>&times;</button>
        </div>
        <div class="cinema-modal-body">
            <?php
            Cinema_Admin::nonce_fields( 'save_showtime' );
            $showtime = (object) [
                'ShowtimeId' => 0,
                'MovieId' => 0,
                'RoomId' => 0,
                'StartTime' => '',
                'Price' => '',
            ];
            include CINEMA_PLUGIN_DIR . 'admin/views/partials/showtime-form.php';
            ?>
            <p class="submit">
                <button type="submit" class="button button-primary">Lưu suất chiếu</button>
                <button type="button" class="button" data-close>Huỷ</button>
            </p>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-dialog]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.dialog);
        if (modal && modal.showModal) modal.showModal();
    });
});
document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog').close());
});
</script>

<?php Cinema_Admin::admin_footer(); ?>
