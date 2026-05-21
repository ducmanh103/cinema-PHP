<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$movie_id = absint( $_GET['movie_id'] ?? 0 );
$movie = $wpdb->get_row( $wpdb->prepare(
    "SELECT m.*, GROUP_CONCAT(g.GenreName ORDER BY g.GenreName SEPARATOR ', ') AS GenreNames
     FROM cinema_movies m
     LEFT JOIN cinema_movie_genres mg ON mg.MovieId = m.MovieId
     LEFT JOIN cinema_genres g ON g.GenreId = mg.GenreId
     WHERE m.MovieId = %d
     GROUP BY m.MovieId",
    $movie_id
) );

Cinema_Admin::admin_header( 'Chi tiết phim', $movie ? $movie->Title : 'Không tìm thấy phim' );

if ( ! $movie ) : ?>
    <div class="notice notice-error"><p>Không tìm thấy phim.</p></div>
<?php Cinema_Admin::admin_footer(); return; endif;

$paid_statuses = Cinema_Admin::PAID_STATUSES;
$stats = $wpdb->get_row( $wpdb->prepare(
    "SELECT COUNT(tk.TicketId) AS TicketCount,
            COALESCE(SUM(CASE WHEN p.Status IN ({$paid_statuses}) THEN p.Amount ELSE 0 END),0) AS Revenue
     FROM cinema_showtimes st
     LEFT JOIN cinema_tickets tk ON tk.ShowtimeId = st.ShowtimeId AND tk.Status = 'Booked'
     LEFT JOIN cinema_payments p ON p.TicketId = tk.TicketId
     WHERE st.MovieId = %d",
    $movie_id
) );

$showtimes = $wpdb->get_results( $wpdb->prepare(
    "SELECT st.*, r.RoomName, t.Name AS TheaterName, COUNT(tk.TicketId) AS TicketCount
     FROM cinema_showtimes st
     JOIN cinema_rooms r ON r.RoomId = st.RoomId
     JOIN cinema_theaters t ON t.TheaterId = r.TheaterId
     LEFT JOIN cinema_tickets tk ON tk.ShowtimeId = st.ShowtimeId AND tk.Status = 'Booked'
     WHERE st.MovieId = %d
     GROUP BY st.ShowtimeId
     ORDER BY st.StartTime ASC",
    $movie_id
) );
?>

<div class="cinema-grid-2">
    <div class="cinema-box">
        <div class="cinema-box-header"><h2 class="cinema-box-title">ℹ Thông tin phim #<?php echo intval( $movie->MovieId ); ?></h2></div>
        <div class="cinema-box-body">
            <div style="display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap">
                <?php if ( $movie->PosterUrl ) : ?>
                    <img class="cinema-table-thumb" style="width:150px;height:220px" src="<?php echo esc_url( Cinema_Admin::asset_url( $movie->PosterUrl ) ); ?>" alt="<?php echo esc_attr( $movie->Title ); ?>">
                <?php endif; ?>
                <div style="flex:1; min-width:260px">
                    <h2 style="margin-top:0"><?php echo esc_html( $movie->Title ); ?></h2>
                    <p><?php echo Cinema_Admin::movie_status_label( $movie->Status ); ?></p>
                    <p><strong>Thể loại:</strong> <?php echo esc_html( $movie->GenreNames ?: 'Chưa phân loại' ); ?></p>
                    <p><strong>Thời lượng:</strong> <?php echo intval( $movie->Duration ); ?> phút</p>
                    <p><strong>Khởi chiếu:</strong> <?php echo $movie->ReleaseDate ? esc_html( date( 'd/m/Y', strtotime( $movie->ReleaseDate ) ) ) : 'N/A'; ?></p>
                    <p><strong>Slug:</strong> <?php echo esc_html( $movie->Slug ); ?></p>
                    <p><?php echo nl2br( esc_html( $movie->Description ) ); ?></p>
                    <p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-movies' ) ); ?>">Quay lại</a> <a class="button button-primary" href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>" target="_blank">Xem ngoài website</a></p>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="cinema-small-box cinema-bg-yellow">
            <div class="inner"><h3><?php echo intval( $stats->TicketCount ); ?></h3><p>Vé đã bán</p></div>
            <div class="icon">🎟</div>
        </div>
        <div class="cinema-small-box cinema-bg-green" style="margin-top:14px">
            <div class="inner"><h3><?php echo esc_html( Cinema_Admin::format_price( $stats->Revenue ) ); ?></h3><p>Doanh thu</p></div>
            <div class="icon">💰</div>
        </div>
    </div>
</div>

<div class="cinema-box">
    <div class="cinema-box-header"><h2 class="cinema-box-title">📅 Suất chiếu (<?php echo count( $showtimes ); ?>)</h2></div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>#</th><th>Rạp</th><th>Phòng</th><th>Giờ chiếu</th><th>Giá vé</th><th>Vé đã bán</th></tr></thead>
            <tbody>
                <?php if ( $showtimes ) : foreach ( $showtimes as $showtime ) : ?>
                    <tr>
                        <td><?php echo intval( $showtime->ShowtimeId ); ?></td>
                        <td><?php echo esc_html( $showtime->TheaterName ); ?></td>
                        <td><?php echo esc_html( $showtime->RoomName ); ?></td>
                        <td><?php echo esc_html( date( 'H:i d/m/Y', strtotime( $showtime->StartTime ) ) ); ?></td>
                        <td><?php echo esc_html( Cinema_Admin::format_price( $showtime->Price ) ); ?></td>
                        <td><?php echo intval( $showtime->TicketCount ); ?></td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="6">Chưa có suất chiếu nào cho phim này.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php Cinema_Admin::admin_footer(); ?>
