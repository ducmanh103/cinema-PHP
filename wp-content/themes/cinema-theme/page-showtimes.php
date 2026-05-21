<?php
/**
 * page-showtimes.php — Trang lịch chiếu tổng hợp
 */
get_header();

$selected_date = isset( $_GET['date'] ) ? sanitize_text_field( $_GET['date'] ) : date( 'Y-m-d' );
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
    $selected_date = date( 'Y-m-d' );
}

$showtimes = cinema_get_all_showtimes( $selected_date );
$by_theater = [];
foreach ( $showtimes as $showtime ) {
    $by_theater[ $showtime->TheaterName ][ $showtime->MovieTitle ][] = $showtime;
}
?>

<section class="page-hero compact-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang Chủ</a>
            <span>›</span>
            <span>Lịch Chiếu</span>
        </nav>
        <h1>Lịch Chiếu</h1>
        <p>Chọn ngày, rạp và suất chiếu phù hợp để đặt vé nhanh.</p>
    </div>
</section>

<section class="section schedule-page">
    <div class="container">
        <div class="date-picker schedule-date-picker">
            <?php
            for ( $i = 0; $i < 7; $i++ ) :
                $date = date( 'Y-m-d', strtotime( "+{$i} days" ) );
                $active = $date === $selected_date ? 'active' : '';
                $label = $i === 0 ? 'Hôm Nay' : ( $i === 1 ? 'Ngày Mai' : date( 'd/m', strtotime( $date ) ) );
                $day_name = [ 'CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7' ][ date( 'w', strtotime( $date ) ) ];
                ?>
                <a href="<?php echo esc_url( add_query_arg( 'date', $date, home_url( '/lich-chieu/' ) ) ); ?>"
                   class="date-btn <?php echo esc_attr( $active ); ?>">
                    <span class="date-day"><?php echo esc_html( $day_name ); ?></span>
                    <span class="date-label"><?php echo esc_html( $label ); ?></span>
                </a>
            <?php endfor; ?>
        </div>

        <?php if ( $by_theater ) : ?>
            <?php foreach ( $by_theater as $theater_name => $movies ) : ?>
                <div class="schedule-theater">
                    <h2 class="schedule-theater-name">📍 <?php echo esc_html( $theater_name ); ?></h2>

                    <?php foreach ( $movies as $movie_title => $times ) :
                        $movie = $times[0];
                        ?>
                        <article class="schedule-movie">
                            <a class="schedule-poster" href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>">
                                <img src="<?php echo esc_url( cinema_asset_url( $movie->PosterUrl ) ); ?>" alt="<?php echo esc_attr( $movie_title ); ?>" loading="lazy">
                            </a>
                            <div class="schedule-details">
                                <div class="schedule-movie-head">
                                    <h3><a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>"><?php echo esc_html( $movie_title ); ?></a></h3>
                                    <span><?php echo esc_html( $movie->City ); ?></span>
                                </div>
                                <div class="showtime-times">
                                    <?php foreach ( $times as $st ) :
                                        $available = intval( $st->AvailableSeats );
                                        if ( $available > 0 ) : ?>
                                            <a href="<?php echo esc_url( home_url( "/ve/{$st->ShowtimeId}/" ) ); ?>"
                                               class="showtime-btn"
                                               title="<?php echo esc_attr( "Còn {$available} ghế - {$st->RoomName}" ); ?>">
                                                <span class="st-time"><?php echo esc_html( date( 'H:i', strtotime( $st->StartTime ) ) ); ?></span>
                                                <span class="st-price"><?php echo esc_html( $st->RoomName ); ?> · <?php echo esc_html( cinema_format_price( $st->Price ) ); ?></span>
                                            </a>
                                        <?php else : ?>
                                            <span class="showtime-btn showtime-disabled" title="Đã hết vé">
                                                <span class="st-time"><?php echo esc_html( date( 'H:i', strtotime( $st->StartTime ) ) ); ?></span>
                                                <span class="st-price">Hết ghế</span>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="empty-panel">
                <h2>Chưa có lịch chiếu</h2>
                <p>Ngày <?php echo esc_html( date( 'd/m/Y', strtotime( $selected_date ) ) ); ?> chưa có suất chiếu khả dụng. Vui lòng chọn ngày khác.</p>
                <a href="<?php echo esc_url( home_url( '/phim/' ) ); ?>" class="btn btn-outline">Khám phá phim khác</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
