<?php
/**
 * page-news.php — Trang tin tức điện ảnh
 */
get_header();

$news_items = [
    [
        'badge' => 'TIÊU ĐIỂM',
        'title' => 'Dune: Phần 2 tiếp tục khuấy động phòng vé với trải nghiệm thị giác quy mô lớn',
        'date'  => '12/04/2026',
        'image' => '/wp-content/uploads/movies/dune2-banner.jpg',
        'desc'  => 'Tác phẩm khoa học viễn tưởng đưa khán giả trở lại Arrakis với âm thanh, hình ảnh và nhịp kể đậm chất sử thi.',
        'featured' => true,
    ],
    [
        'badge' => 'REVIEW PHIM',
        'title' => 'Review Mai: Câu chuyện cảm xúc về tình yêu, gia đình và lựa chọn cá nhân',
        'date'  => '10/04/2026',
        'image' => '/wp-content/uploads/movies/mai-banner.jpg',
        'desc'  => 'Bộ phim Việt tạo điểm nhấn bằng diễn xuất gần gũi, bối cảnh đời thường và nhiều lát cắt tâm lý đáng chú ý.',
    ],
    [
        'badge' => 'KHUYẾN MÃI',
        'title' => 'Ưu đãi đặt vé online: tiết kiệm thời gian, giữ ghế trước khi tới rạp',
        'date'  => '05/04/2026',
        'image' => '/wp-content/uploads/movies/cinemaxmomo.jpg',
        'desc'  => 'Người dùng có thể chọn suất, chọn ghế và nhận thông tin vé ngay trong tài khoản cá nhân.',
    ],
    [
        'badge' => 'TIN VẮN',
        'title' => 'Các bom tấn hành động và hoạt hình sẵn sàng cho mùa phim hè',
        'date'  => '02/04/2026',
        'image' => '/wp-content/uploads/movies/marvel-phase-6.jpg',
        'desc'  => 'Lịch phát hành mùa hè ghi nhận nhiều tựa phim lớn, từ siêu anh hùng đến hoạt hình gia đình.',
    ],
];
?>

<section class="page-hero compact-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang Chủ</a>
            <span>›</span>
            <span>Tin Tức</span>
        </nav>
        <h1>Tin Tức Điện Ảnh</h1>
        <p>Cập nhật review phim, lịch ra rạp, ưu đãi và các điểm đáng chú ý trong tuần.</p>
    </div>
</section>

<section class="section news-page">
    <div class="container">
        <div class="news-grid">
            <?php foreach ( $news_items as $item ) : ?>
                <article class="news-card <?php echo ! empty( $item['featured'] ) ? 'news-card-featured' : ''; ?>">
                    <div class="news-image">
                        <span class="news-badge"><?php echo esc_html( $item['badge'] ); ?></span>
                        <img src="<?php echo esc_url( cinema_asset_url( $item['image'] ) ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="lazy">
                    </div>
                    <div class="news-content">
                        <span class="news-date">🕐 <?php echo esc_html( $item['date'] ); ?> · CinemaHub</span>
                        <h2><?php echo esc_html( $item['title'] ); ?></h2>
                        <p><?php echo esc_html( $item['desc'] ); ?></p>
                        <a href="#" class="btn btn-outline btn-sm">Đọc tiếp</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
