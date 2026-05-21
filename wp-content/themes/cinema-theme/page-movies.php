<?php
/**
 * page-movies.php — Danh sách phim
 */
get_header();

$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$status_map = [
    'now-showing' => 'Now Showing',
    'coming-soon' => 'Coming Soon',
    'ended'       => 'Ended',
];
$selected_status = $status_map[ $status ] ?? null;
$movies = cinema_get_movies_by_status( $selected_status );

$groups = [
    'Now Showing' => [
        'title' => 'Phim Đang Chiếu',
        'badge' => 'Đang Chiếu',
        'class' => 'badge-now',
        'empty' => 'Chưa có phim đang chiếu.',
    ],
    'Coming Soon' => [
        'title' => 'Phim Sắp Chiếu',
        'badge' => 'Sắp Chiếu',
        'class' => 'badge-coming',
        'empty' => 'Chưa có phim sắp chiếu.',
    ],
    'Ended' => [
        'title' => 'Phim Đã Kết Thúc',
        'badge' => 'Ngừng Chiếu',
        'class' => 'badge-ended',
        'empty' => 'Chưa có phim đã kết thúc.',
    ],
];

$movies_by_status = [];
foreach ( $movies as $movie ) {
    $movies_by_status[ $movie->Status ][] = $movie;
}
?>

<section class="page-hero compact-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang Chủ</a>
            <span>›</span>
            <span>Phim</span>
        </nav>
        <h1>Danh Sách Phim</h1>
        <p>Khám phá phim đang chiếu, phim sắp ra mắt và chọn suất chiếu phù hợp.</p>
    </div>
</section>

<section class="section movies-list-page">
    <div class="container">
        <div class="movie-tabs">
            <a class="<?php echo ! $selected_status ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/phim/' ) ); ?>">Tất cả</a>
            <a class="<?php echo 'Now Showing' === $selected_status ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/phim/?status=now-showing' ) ); ?>">Đang chiếu</a>
            <a class="<?php echo 'Coming Soon' === $selected_status ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/phim/?status=coming-soon' ) ); ?>">Sắp chiếu</a>
            <a class="<?php echo 'Ended' === $selected_status ? 'active' : ''; ?>" href="<?php echo esc_url( home_url( '/phim/?status=ended' ) ); ?>">Đã kết thúc</a>
        </div>

        <?php foreach ( $groups as $group_status => $group ) :
            if ( $selected_status && $selected_status !== $group_status ) {
                continue;
            }

            $group_movies = $movies_by_status[ $group_status ] ?? [];
            if ( ! $group_movies ) {
                continue;
            }
            ?>
            <div class="section-header movies-list-header">
                <h2 class="section-title"><span class="title-accent">●</span> <?php echo esc_html( $group['title'] ); ?></h2>
            </div>
            <div class="movies-grid">
                <?php foreach ( $group_movies as $movie ) : ?>
                    <article class="movie-card <?php echo 'Ended' === $movie->Status ? 'movie-card-ended' : ''; ?>">
                        <a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>" class="card-link" aria-label="<?php echo esc_attr( 'Xem chi tiết ' . $movie->Title ); ?>">
                            <div class="card-poster">
                                <?php if ( $movie->PosterUrl ) : ?>
                                    <img src="<?php echo esc_url( cinema_asset_url( $movie->PosterUrl ) ); ?>" alt="<?php echo esc_attr( $movie->Title ); ?>" loading="lazy">
                                <?php else : ?>
                                    <div class="poster-placeholder">🎬</div>
                                <?php endif; ?>
                                <div class="card-overlay">
                                    <div class="card-actions">
                                        <span class="btn btn-primary btn-sm">Xem Chi Tiết</span>
                                        <?php if ( 'Now Showing' === $movie->Status ) : ?>
                                            <span class="btn btn-ghost btn-sm">Đặt Vé</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="card-badge <?php echo esc_attr( $group['class'] ); ?>"><?php echo esc_html( $group['badge'] ); ?></span>
                            </div>
                            <div class="card-info">
                                <h3 class="card-title"><?php echo esc_html( $movie->Title ); ?></h3>
                                <div class="card-meta movie-list-meta">
                                    <span>⏱ <?php echo esc_html( cinema_format_duration( $movie->Duration ) ); ?></span>
                                    <?php if ( $movie->Genres ) : ?>
                                        <span><?php echo esc_html( $movie->Genres ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ( $movie->ReleaseDate ) : ?>
                                    <p class="card-release">📅 <?php echo esc_html( date( 'd/m/Y', strtotime( $movie->ReleaseDate ) ) ); ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php if ( ! $movies ) : ?>
            <div class="empty-panel">
                <h2>Chưa có phim</h2>
                <p>Danh sách phim hiện đang trống.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
