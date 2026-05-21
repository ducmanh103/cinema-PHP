<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$genres = $wpdb->get_results( 'SELECT * FROM cinema_genres ORDER BY GenreName ASC' );
$movies = $wpdb->get_results(
    "SELECT m.*,
            GROUP_CONCAT(g.GenreName ORDER BY g.GenreName SEPARATOR ', ') AS GenreNames,
            GROUP_CONCAT(g.GenreId ORDER BY g.GenreName SEPARATOR ',') AS GenreIds
     FROM cinema_movies m
     LEFT JOIN cinema_movie_genres mg ON mg.MovieId = m.MovieId
     LEFT JOIN cinema_genres g ON g.GenreId = mg.GenreId
     GROUP BY m.MovieId
     ORDER BY m.MovieId DESC"
);

$statuses = [
    'Now Showing' => 'Đang chiếu',
    'Coming Soon' => 'Sắp chiếu',
    'Ended'       => 'Ngừng chiếu',
];

Cinema_Admin::admin_header( 'Phim', 'Danh sách phim trong hệ thống' );
?>

<div class="cinema-box">
    <div class="cinema-box-header">
        <h2 class="cinema-box-title">🎞 Danh sách phim (<?php echo count( $movies ); ?>)</h2>
        <button type="button" class="button button-primary" data-dialog="create-movie">+ Thêm phim mới</button>
    </div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:60px">Poster</th>
                    <th style="width:70px">ID</th>
                    <th>Tên phim</th>
                    <th>Thể loại</th>
                    <th style="width:100px">Thời lượng</th>
                    <th style="width:110px">Khởi chiếu</th>
                    <th style="width:120px">Trạng thái</th>
                    <th style="width:190px">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $movies ) : foreach ( $movies as $movie ) :
                    $movie_genre_ids = array_filter( array_map( 'absint', explode( ',', (string) $movie->GenreIds ) ) );
                    ?>
                    <tr>
                        <td>
                            <?php if ( $movie->PosterUrl ) : ?>
                                <img class="cinema-table-thumb" src="<?php echo esc_url( Cinema_Admin::asset_url( $movie->PosterUrl ) ); ?>" alt="<?php echo esc_attr( $movie->Title ); ?>">
                            <?php else : ?>
                                <div class="cinema-table-thumb"></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo intval( $movie->MovieId ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $movie->Title ); ?></strong><br>
                            <small><a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>" target="_blank">Xem ngoài website</a></small>
                        </td>
                        <td><?php echo esc_html( $movie->GenreNames ?: 'Chưa phân loại' ); ?></td>
                        <td><?php echo intval( $movie->Duration ); ?> phút</td>
                        <td><?php echo $movie->ReleaseDate ? esc_html( date( 'd/m/Y', strtotime( $movie->ReleaseDate ) ) ) : 'N/A'; ?></td>
                        <td><?php echo Cinema_Admin::movie_status_label( $movie->Status ); ?></td>
                        <td>
                            <button type="button" class="button button-small" data-dialog="edit-movie-<?php echo intval( $movie->MovieId ); ?>">Sửa</button>
                            <a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-movie-detail&movie_id=' . intval( $movie->MovieId ) ) ); ?>">Chi tiết</a>
                            <form method="post" class="cinema-inline-form" onsubmit="return confirm('Xoá phim <?php echo esc_js( $movie->Title ); ?>?');">
                                <?php Cinema_Admin::nonce_fields( 'delete_movie' ); ?>
                                <input type="hidden" name="movie_id" value="<?php echo intval( $movie->MovieId ); ?>">
                                <button type="submit" class="button button-small button-link-delete">Xoá</button>
                            </form>
                        </td>
                    </tr>

                    <dialog class="cinema-modal" id="edit-movie-<?php echo intval( $movie->MovieId ); ?>">
                        <form method="post">
                            <div class="cinema-modal-header">
                                <h2>Sửa phim #<?php echo intval( $movie->MovieId ); ?></h2>
                                <button type="button" class="cinema-modal-close" data-close>&times;</button>
                            </div>
                            <div class="cinema-modal-body">
                                <?php Cinema_Admin::nonce_fields( 'save_movie' ); ?>
                                <input type="hidden" name="movie_id" value="<?php echo intval( $movie->MovieId ); ?>">
                                <?php include CINEMA_PLUGIN_DIR . 'admin/views/partials/movie-form.php'; ?>
                                <p class="submit">
                                    <button type="submit" class="button button-primary">Lưu phim</button>
                                    <button type="button" class="button" data-close>Huỷ</button>
                                </p>
                            </div>
                        </form>
                    </dialog>
                <?php endforeach; else : ?>
                    <tr><td colspan="8">Chưa có phim nào.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog class="cinema-modal" id="create-movie">
    <form method="post">
        <div class="cinema-modal-header">
            <h2>Thêm phim mới</h2>
            <button type="button" class="cinema-modal-close" data-close>&times;</button>
        </div>
        <div class="cinema-modal-body">
            <?php
            Cinema_Admin::nonce_fields( 'save_movie' );
            $movie = (object) [
                'MovieId' => 0,
                'Title' => '',
                'Duration' => 120,
                'Description' => '',
                'ReleaseDate' => '',
                'PosterUrl' => '',
                'BannerUrl' => '',
                'Slug' => '',
                'Status' => 'Now Showing',
            ];
            $movie_genre_ids = [];
            include CINEMA_PLUGIN_DIR . 'admin/views/partials/movie-form.php';
            ?>
            <p class="submit">
                <button type="submit" class="button button-primary">Thêm phim</button>
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
