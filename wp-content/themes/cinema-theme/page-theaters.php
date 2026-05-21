<?php
/**
 * page-theaters.php — Hệ thống rạp
 */
get_header();

$theaters = cinema_get_theaters();
$images = [
    'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=900&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?q=80&w=900&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1595769816263-9b910be24d5f?q=80&w=900&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=900&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1542204165-65bf26472b9b?q=80&w=900&auto=format&fit=crop',
];
?>

<section class="page-hero compact-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang Chủ</a>
            <span>›</span>
            <span>Rạp</span>
        </nav>
        <h1>Hệ Thống Rạp</h1>
        <p>Danh sách cụm rạp, địa chỉ, số phòng chiếu và lịch chiếu sắp tới.</p>
    </div>
</section>

<section class="section theaters-page">
    <div class="container">
        <?php if ( $theaters ) : ?>
            <div class="theaters-grid">
                <?php foreach ( $theaters as $index => $theater ) :
                    $image = $images[ $index % count( $images ) ];
                    ?>
                    <article class="theater-card">
                        <div class="theater-image">
                            <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $theater->Name ); ?>" loading="lazy">
                            <span class="theater-badge"><?php echo intval( $theater->UpcomingShowtimes ); ?> suất sắp tới</span>
                        </div>
                        <div class="theater-content">
                            <h2><?php echo esc_html( $theater->Name ); ?></h2>
                            <p class="theater-address">📍 <?php echo esc_html( $theater->Address ); ?></p>
                            <p class="theater-city">🏙 <?php echo esc_html( $theater->City ); ?></p>
                            <?php if ( $theater->Phone ) : ?>
                                <p class="theater-phone">☎ <?php echo esc_html( $theater->Phone ); ?></p>
                            <?php endif; ?>
                            <div class="theater-stats">
                                <span><?php echo intval( $theater->RoomCount ); ?> phòng</span>
                                <span><?php echo intval( $theater->SeatCount ); ?> ghế</span>
                            </div>
                            <a class="btn btn-outline btn-block" href="<?php echo esc_url( home_url( '/lich-chieu/' ) ); ?>">Xem Lịch Chiếu</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="empty-panel">
                <h2>Chưa có rạp</h2>
                <p>Hệ thống chưa có dữ liệu rạp chiếu.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
