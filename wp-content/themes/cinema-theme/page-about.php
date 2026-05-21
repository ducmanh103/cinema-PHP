<?php
/**
 * page-about.php — Trang giới thiệu
 */
get_header();
?>

<section class="about-hero">
    <div class="container about-hero-content">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Trang Chủ</a>
            <span>›</span>
            <span>Giới Thiệu</span>
        </nav>

        <div class="about-hero-grid">
            <div class="about-hero-left">
                <span class="hero-kicker">Câu chuyện của chúng tôi</span>
                <h1><span class="brand-accent">CinemaHub</span></h1>
                <p>Không gian đặt vé và trải nghiệm điện ảnh dành cho khán giả yêu phim, kết nối phim hay, rạp tốt và dịch vụ thuận tiện trong cùng một hệ thống.</p>

                <div class="about-hero-stats">
                    <div class="hero-stat">
                        <span class="hero-stat-value">120+</span>
                        <span class="hero-stat-label">Phim đang chiếu</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">25</span>
                        <span class="hero-stat-label">Rạp đối tác</span>
                    </div>
                    <div class="hero-stat">
                        <span class="hero-stat-value">500K+</span>
                        <span class="hero-stat-label">Vé đã đặt</span>
                    </div>
                </div>
            </div>

            <div class="about-hero-right" aria-hidden="true">
                <div class="hero-visual">
                    <svg class="film-reel-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="reelGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#ff4757"/>
                                <stop offset="60%" stop-color="#e50914"/>
                                <stop offset="100%" stop-color="#8b0610"/>
                            </linearGradient>
                            <radialGradient id="reelCenter" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#ffffff"/>
                                <stop offset="100%" stop-color="#d8dae3"/>
                            </radialGradient>
                        </defs>
                        <circle cx="100" cy="100" r="92" fill="url(#reelGrad)"/>
                        <circle cx="100" cy="100" r="92" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="2"/>
                        <circle cx="100" cy="100" r="78" fill="none" stroke="rgba(0,0,0,0.35)" stroke-width="1"/>
                        <g fill="#0f1014">
                            <circle cx="100" cy="32" r="11"/>
                            <circle cx="168" cy="100" r="11"/>
                            <circle cx="100" cy="168" r="11"/>
                            <circle cx="32" cy="100" r="11"/>
                            <circle cx="148" cy="52" r="8.5" opacity="0.85"/>
                            <circle cx="148" cy="148" r="8.5" opacity="0.85"/>
                            <circle cx="52" cy="148" r="8.5" opacity="0.85"/>
                            <circle cx="52" cy="52" r="8.5" opacity="0.85"/>
                        </g>
                        <circle cx="100" cy="100" r="38" fill="url(#reelCenter)"/>
                        <path d="M86 78 L128 100 L86 122 Z" fill="#e50914"/>
                    </svg>

                    <div class="hero-floating-card fc-1">
                        <span class="fc-icon">🎬</span>
                        <span class="fc-text">Premium<small>Trải nghiệm 4K</small></span>
                    </div>
                    <div class="hero-floating-card fc-2">
                        <span class="fc-icon">🎟</span>
                        <span class="fc-text">Đặt vé nhanh<small>Chỉ 30 giây</small></span>
                    </div>
                    <div class="hero-floating-card fc-3">
                        <span class="fc-icon">⭐</span>
                        <span class="fc-text">4.9 / 5<small>Đánh giá</small></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section about-page">
    <div class="container">
        <div class="about-split">
            <div>
                <h2><span>Tầm nhìn</span> chiến lược</h2>
                <p>CinemaHub được xây dựng với mục tiêu đơn giản: giúp người xem tìm phim, chọn lịch chiếu và đặt vé dễ hơn, đồng thời giữ được cảm giác chỉn chu của một hệ thống rạp hiện đại.</p>
                <p>Từ danh sách phim, suất chiếu, sơ đồ ghế đến vé cá nhân, mọi thao tác đều được tổ chức để người dùng đi từ quyết định xem phim đến hoàn tất đặt vé trong ít bước nhất.</p>
                <a href="<?php echo esc_url( home_url( '/lich-chieu/' ) ); ?>" class="btn btn-primary btn-lg">Xem Lịch Chiếu</a>
            </div>
            <div class="about-photo">
                <img src="https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?q=80&w=900&auto=format&fit=crop" alt="Không gian rạp chiếu phim" loading="lazy">
            </div>
        </div>

        <div class="feature-grid">
            <article class="feature-box">
                <div class="feature-icon">▣</div>
                <h3>Công nghệ trình chiếu</h3>
                <p>Hình ảnh sắc nét, phòng chiếu tối ưu và trải nghiệm màn ảnh lớn đúng nghĩa.</p>
            </article>
            <article class="feature-box">
                <div class="feature-icon">◉</div>
                <h3>Âm thanh sống động</h3>
                <p>Âm trường rõ, mạnh và có chiều sâu để mỗi cảnh phim đều có sức nặng.</p>
            </article>
            <article class="feature-box">
                <div class="feature-icon">★</div>
                <h3>Dịch vụ thuận tiện</h3>
                <p>Đặt vé online, chọn ghế trực quan và quản lý vé ngay trong tài khoản.</p>
            </article>
        </div>

        <div class="about-cta">
            <h2>Sẵn sàng trải nghiệm?</h2>
            <p>Chọn phim đang chiếu và giữ chỗ trước khi tới rạp.</p>
            <a href="<?php echo esc_url( home_url( '/phim/' ) ); ?>" class="btn btn-primary btn-lg">Đặt Vé Ngay</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
