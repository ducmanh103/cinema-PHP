<?php
/**
 * single-movie.php — Trang chi tiết phim
 * URL: /phim/[slug]/
 */
global $wpdb;

// Lấy slug từ URL query var hoặc URL segment
$slug = get_query_var( 'cinema_movie_slug' );
if ( ! $slug ) {
    // Fallback: lấy từ cuối URL (loại bỏ query string trước)
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $path        = wp_parse_url( $request_uri, PHP_URL_PATH );
    $slug        = basename( untrailingslashit( $path ) );
}

$movie = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM cinema_movies WHERE Slug = %s", sanitize_title( $slug )
) );

if ( ! $movie ) {
    wp_safe_redirect( home_url( '/phim/' ) );
    exit;
}

// Lấy thể loại
$genres = $wpdb->get_col( $wpdb->prepare(
    "SELECT g.GenreName FROM cinema_genres g
     JOIN cinema_movie_genres mg ON g.GenreId = mg.GenreId
     WHERE mg.MovieId = %d", $movie->MovieId
) );

// Ngày chiếu hôm nay và các ngày tới (7 ngày)
$selected_date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : date('Y-m-d');
$showtimes     = cinema_get_showtimes( $movie->MovieId, $selected_date );

// Nhóm suất chiếu theo rạp
$by_theater = [];
foreach ( $showtimes as $st ) {
    $by_theater[ $st->TheaterName ][] = $st;
}

get_header();
?>

<?php
$banner_src = ! empty( $movie->BannerUrl ) ? cinema_asset_url( $movie->BannerUrl ) : cinema_asset_url( $movie->PosterUrl );
$has_banner = ! empty( $movie->BannerUrl );
?>

<!-- ================================================================
     MOVIE BANNER
================================================================ -->
<section class="movie-hero <?php echo $has_banner ? 'has-banner' : 'no-banner'; ?>"
         style="--banner: url('<?php echo esc_url( $banner_src ); ?>')">
    <div class="movie-hero-bg" aria-hidden="true"></div>
    <div class="movie-hero-overlay" aria-hidden="true"></div>
    <div class="movie-hero-fade" aria-hidden="true"></div>

    <div class="container movie-hero-inner">

        <!-- Poster -->
        <div class="movie-poster-wrap">
            <img class="movie-poster" src="<?php echo esc_url( cinema_asset_url( $movie->PosterUrl ) ); ?>"
                 alt="Poster <?php echo esc_attr( $movie->Title ); ?>" loading="eager">
            <span class="poster-status status-<?php echo sanitize_html_class( strtolower( str_replace(' ', '-', $movie->Status) ) ); ?>">
                <?php
                $labels = [ 'Now Showing' => 'Đang Chiếu', 'Coming Soon' => 'Sắp Chiếu', 'Ended' => 'Đã Kết Thúc' ];
                echo esc_html( $labels[ $movie->Status ] ?? $movie->Status );
                ?>
            </span>
        </div>

        <!-- Info -->
        <div class="movie-info">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo home_url('/'); ?>">Trang Chủ</a>
                <span>›</span>
                <a href="<?php echo home_url('/phim/'); ?>">Phim</a>
                <span>›</span>
                <span><?php echo esc_html( $movie->Title ); ?></span>
            </nav>

            <h1 class="movie-title"><?php echo esc_html( $movie->Title ); ?></h1>

            <div class="movie-meta-row">
                <?php if ( $genres ) : ?>
                <div class="movie-genres">
                    <?php foreach ( $genres as $g ) : ?>
                    <span class="genre-tag"><?php echo esc_html( $g ); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <span class="movie-duration">⏱ <?php echo cinema_format_duration( $movie->Duration ); ?></span>
                <?php if ( $movie->ReleaseDate ) : ?>
                <span class="movie-release">📅 Khởi chiếu: <?php echo date( 'd/m/Y', strtotime( $movie->ReleaseDate ) ); ?></span>
                <?php endif; ?>
            </div>

            <p class="movie-desc"><?php echo nl2br( esc_html( $movie->Description ) ); ?></p>

            <div class="movie-cta">
                <?php if ( $movie->Status === 'Now Showing' ) : ?>
                <a href="#showtimes" class="btn btn-primary btn-lg scroll-to">
                    🎟 Chọn Suất Chiếu
                </a>
                <?php elseif ( $movie->Status === 'Coming Soon' ) : ?>
                <button class="btn btn-outline btn-lg" disabled>Chưa Mở Bán</button>
                <?php endif; ?>
                <?php if ( ! empty( $movie->TrailerUrl ) ) : ?>
                <a href="<?php echo esc_url( $movie->TrailerUrl ); ?>" class="btn btn-ghost btn-lg" target="_blank" rel="noopener">
                    ▶ Xem Trailer
                </a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</section>

<!-- ================================================================
     CHỌN NGÀY + SUẤT CHIẾU
================================================================ -->
<?php if ( $movie->Status === 'Now Showing' ) : ?>
<section class="showtimes-section section" id="showtimes">
    <div class="container">
        <h2 class="section-title"><span class="title-accent">●</span> Lịch Chiếu</h2>

        <!-- Date Picker -->
        <div class="date-picker" id="date-picker">
            <?php
            for ( $i = 0; $i < 7; $i++ ) :
                $d        = date( 'Y-m-d', strtotime( "+{$i} days" ) );
                $label    = $i === 0 ? 'Hôm Nay' : ( $i === 1 ? 'Ngày Mai' : date( 'd/m', strtotime( "+{$i} days" ) ) );
                $day_name = ['CN','T2','T3','T4','T5','T6','T7'][ date('w', strtotime("+{$i} days")) ];
                $active   = $d === $selected_date ? 'active' : '';
            ?>
            <a href="?date=<?php echo $d; ?>#showtimes"
               class="date-btn <?php echo $active; ?>">
                <span class="date-day"><?php echo $day_name; ?></span>
                <span class="date-label"><?php echo $label; ?></span>
            </a>
            <?php endfor; ?>
        </div>

        <!-- Showtimes By Theater -->
        <?php if ( $by_theater ) : foreach ( $by_theater as $theater_name => $times ) : ?>
        <div class="theater-block">
            <h3 class="theater-name">📍 <?php echo esc_html( $theater_name ); ?></h3>
            <div class="showtime-times">
                <?php foreach ( $times as $st ) :
                    $time_str = date( 'H:i', strtotime( $st->StartTime ) ); ?>
                <a href="<?php echo esc_url( home_url( "/ve/{$st->ShowtimeId}/" ) ); ?>"
                   class="showtime-btn"
                   title="<?php echo esc_attr( $st->RoomName ); ?> — <?php echo cinema_format_price( $st->Price ); ?>">
                    <span class="st-time"><?php echo $time_str; ?></span>
                    <span class="st-price"><?php echo cinema_format_price( $st->Price ); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach;
        else : ?>
        <div class="no-showtimes">
            <p>😔 Không có suất chiếu cho ngày <strong><?php echo date( 'd/m/Y', strtotime( $selected_date ) ); ?></strong></p>
            <p>Vui lòng chọn ngày khác.</p>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<script>
// Smooth scroll to showtimes
document.querySelectorAll('.scroll-to').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});
</script>

<?php get_footer(); ?>
