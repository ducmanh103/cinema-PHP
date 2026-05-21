<?php
/**
 * index.php — Trang chủ: Banner Slider + Phim Đang Chiếu + Phim Sắp Chiếu
 */
get_header();

$banner_movies  = cinema_get_banner_movies( 5 );
$now_showing    = cinema_get_cached_movies( 'Now Showing', 8 );
$coming_soon    = cinema_get_cached_movies( 'Coming Soon', 4 );
$slider_interval = get_theme_mod( 'cinema_slider_interval', 5000 );
?>

<!-- ================================================================
     HERO BANNER SLIDER
================================================================ -->
<section class="hero-slider" id="hero-slider"
    data-interval="<?php echo esc_attr( $slider_interval ); ?>">

    <?php if ( $banner_movies ) : foreach ( $banner_movies as $i => $movie ) : ?>
    <div class="slide <?php echo $i === 0 ? 'active' : ''; ?>"
         style="--slide-bg: url('<?php echo esc_url( cinema_asset_url( $movie->BannerUrl ) ); ?>')">
        <div class="slide-overlay"></div>
        <div class="slide-content container">
            <div class="slide-meta">
                <span class="slide-badge">🎬 Đang Chiếu</span>
                <?php
                $genres = $GLOBALS['wpdb']->get_col( $GLOBALS['wpdb']->prepare(
                    "SELECT g.GenreName FROM cinema_genres g
                     JOIN cinema_movie_genres mg ON g.GenreId = mg.GenreId
                     WHERE mg.MovieId = %d LIMIT 2",
                    $movie->MovieId
                ) );
                if ( $genres ) echo '<span class="slide-genre">' . esc_html( implode( ' • ', $genres ) ) . '</span>';
                ?>
            </div>
            <h1 class="slide-title"><?php echo esc_html( $movie->Title ); ?></h1>
            <p class="slide-desc"><?php echo esc_html( wp_trim_words( $movie->Description, 25 ) ); ?></p>
            <div class="slide-info">
                <span>⏱ <?php echo cinema_format_duration( $movie->Duration ); ?></span>
                <?php if ( $movie->ReleaseDate ) : ?>
                <span>📅 <?php echo date( 'd/m/Y', strtotime( $movie->ReleaseDate ) ); ?></span>
                <?php endif; ?>
            </div>
            <div class="slide-actions">
                <a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>"
                   class="btn btn-primary btn-lg">Đặt Vé Ngay</a>
                <a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>"
                   class="btn btn-ghost btn-lg"><span class="btn-info-icon">i</span> Xem Chi Tiết</a>
            </div>
        </div>
        <div class="slide-poster">
            <img src="<?php echo esc_url( cinema_asset_url( $movie->PosterUrl ) ); ?>"
                 alt="<?php echo esc_attr( $movie->Title ); ?>"
                 loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
        </div>
    </div>
    <?php endforeach; endif; ?>

    <!-- Slider Controls -->
    <button class="slider-prev" id="slider-prev" aria-label="Slide trước">&#10094;</button>
    <button class="slider-next" id="slider-next" aria-label="Slide tiếp theo">&#10095;</button>
    <div class="slider-dots" id="slider-dots">
        <?php if ( $banner_movies ) foreach ( $banner_movies as $i => $m ) : ?>
        <button class="dot <?php echo $i === 0 ? 'active' : ''; ?>"
                data-index="<?php echo $i; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
        <?php endforeach; ?>
    </div>
</section>

<!-- ================================================================
     PHIM ĐANG CHIẾU
================================================================ -->
<section class="section movies-section" id="now-showing">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">
                <span class="title-accent">●</span> Phim Đang Chiếu
            </h2>
            <a href="<?php echo home_url('/phim/'); ?>" class="btn btn-outline btn-sm">Xem Tất Cả →</a>
        </div>

        <div class="movies-grid" id="now-showing-grid">
            <?php if ( $now_showing ) : foreach ( $now_showing as $movie ) : ?>
            <article class="movie-card" data-movie-id="<?php echo esc_attr( $movie->MovieId ); ?>">
                <a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>"
                   class="card-link" aria-label="<?php echo esc_attr( $movie->Title ); ?>">
                    <div class="card-poster">
                        <img src="<?php echo esc_url( cinema_asset_url( $movie->PosterUrl ) ); ?>"
                             alt="<?php echo esc_attr( $movie->Title ); ?>"
                             loading="lazy">
                        <div class="card-overlay">
                            <div class="card-actions">
                                <span class="btn btn-primary btn-sm">Đặt Vé</span>
                                <span class="btn btn-ghost btn-sm"><span class="btn-info-icon">i</span> Chi Tiết</span>
                            </div>
                        </div>
                        <span class="card-badge badge-now">Đang Chiếu</span>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title"><?php echo esc_html( $movie->Title ); ?></h3>
                        <div class="card-meta">
                            <span class="card-duration">⏱ <?php echo cinema_format_duration( $movie->Duration ); ?></span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach;
            else : ?>
            <p class="no-results">Chưa có phim đang chiếu.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ================================================================
     PHIM SẮP CHIẾU
================================================================ -->
<?php if ( $coming_soon ) : ?>
<section class="section movies-section coming-soon-section" id="coming-soon">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">
                <span class="title-accent">●</span> Sắp Ra Mắt
            </h2>
            <a href="<?php echo home_url('/phim/?status=coming-soon'); ?>" class="btn btn-outline btn-sm">Xem Tất Cả →</a>
        </div>
        <div class="movies-grid movies-grid--coming">
            <?php foreach ( $coming_soon as $movie ) : ?>
            <article class="movie-card movie-card--coming">
                <a href="<?php echo esc_url( home_url( '/phim/' . $movie->Slug . '/' ) ); ?>" class="card-link">
                    <div class="card-poster">
                        <img src="<?php echo esc_url( cinema_asset_url( $movie->PosterUrl ) ); ?>"
                             alt="<?php echo esc_attr( $movie->Title ); ?>" loading="lazy">
                        <div class="card-overlay">
                            <span class="coming-label">Sắp Ra Mắt</span>
                        </div>
                        <span class="card-badge badge-coming">Sắp Chiếu</span>
                    </div>
                    <div class="card-info">
                        <h3 class="card-title"><?php echo esc_html( $movie->Title ); ?></h3>
                        <?php if ( $movie->ReleaseDate ) : ?>
                        <p class="card-release">📅 <?php echo date( 'd/m/Y', strtotime( $movie->ReleaseDate ) ); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ================================================================
     CTA BANNER
================================================================ -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content">
            <h2>Tải Ứng Dụng Cinema</h2>
            <p>Đặt vé nhanh hơn — Nhận ưu đãi độc quyền</p>
            <div class="cta-actions">
                <a href="#" class="btn btn-primary btn-lg">App Store</a>
                <a href="#" class="btn btn-outline btn-lg">Google Play</a>
            </div>
        </div>
    </div>
</section>

<!-- Slider JS -->
<script>
(function () {
    const slider   = document.getElementById('hero-slider');
    if (!slider) return;
    const slides   = slider.querySelectorAll('.slide');
    const dots     = slider.querySelectorAll('.dot');
    const interval = parseInt(slider.dataset.interval, 10) || 5000;
    let current    = 0;
    let timer;

    function goTo(n) {
        slides[current].classList.remove('active');
        dots[current] && dots[current].classList.remove('active');
        current = (n + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current] && dots[current].classList.add('active');
    }

    function start() { timer = setInterval(() => goTo(current + 1), interval); }
    function stop()  { clearInterval(timer); }

    document.getElementById('slider-prev') && document.getElementById('slider-prev').addEventListener('click', () => { stop(); goTo(current - 1); start(); });
    document.getElementById('slider-next') && document.getElementById('slider-next').addEventListener('click', () => { stop(); goTo(current + 1); start(); });
    dots.forEach((d, i) => d.addEventListener('click', () => { stop(); goTo(i); start(); }));

    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);
    start();
})();
</script>

<?php get_footer(); ?>
