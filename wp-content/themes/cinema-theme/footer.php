</main><!-- #main-content -->

<footer class="site-footer" id="site-footer">
    <div class="footer-inner container">

        <div class="footer-grid">
            <!-- Brand -->
            <div class="footer-col footer-brand">
                <a href="<?php echo esc_url( home_url('/') ); ?>" class="footer-logo" aria-label="<?php echo esc_attr( get_bloginfo('name') ); ?>">
                    <span class="logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <linearGradient id="cnLogoGradF" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#ff4757"/>
                                    <stop offset="55%" stop-color="#e50914"/>
                                    <stop offset="100%" stop-color="#8b0610"/>
                                </linearGradient>
                                <radialGradient id="cnLogoCenterF" cx="50%" cy="50%" r="50%">
                                    <stop offset="0%" stop-color="#ffffff"/>
                                    <stop offset="100%" stop-color="#f5f7fb"/>
                                </radialGradient>
                            </defs>
                            <circle cx="24" cy="24" r="22" fill="url(#cnLogoGradF)"/>
                            <circle cx="24" cy="24" r="22" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="1"/>
                            <g fill="#0f1014">
                                <circle cx="24" cy="8.5" r="2.3"/>
                                <circle cx="39.5" cy="24" r="2.3"/>
                                <circle cx="24" cy="39.5" r="2.3"/>
                                <circle cx="8.5" cy="24" r="2.3"/>
                                <circle cx="35" cy="13" r="1.9" opacity="0.85"/>
                                <circle cx="35" cy="35" r="1.9" opacity="0.85"/>
                                <circle cx="13" cy="35" r="1.9" opacity="0.85"/>
                                <circle cx="13" cy="13" r="1.9" opacity="0.85"/>
                            </g>
                            <circle cx="24" cy="24" r="9.5" fill="url(#cnLogoCenterF)"/>
                            <path d="M21 18.5 L31 24 L21 29.5 Z" fill="#e50914"/>
                        </svg>
                    </span>
                    <span class="logo-text">
                        <span class="logo-name">
                            <?php
                            $brand_name = get_bloginfo('name');
                            $brand_main = $brand_name;
                            $brand_sub  = '';
                            if ( strpos( $brand_name, ' ' ) !== false ) {
                                $parts      = explode( ' ', $brand_name, 2 );
                                $brand_main = $parts[0];
                                $brand_sub  = $parts[1];
                            }
                            ?>
                            <span class="logo-name-main"><?php echo esc_html( $brand_main ); ?></span><?php if ( $brand_sub ) : ?><span class="logo-name-accent"><?php echo esc_html( $brand_sub ); ?></span><?php endif; ?>
                        </span>
                        <span class="logo-tagline">BOOK · WATCH · ENJOY</span>
                    </span>
                </a>
                <p class="footer-desc">Hệ thống đặt vé rạp chiếu phim trực tuyến hàng đầu Việt Nam.</p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook">📘</a>
                    <a href="#" aria-label="YouTube">📺</a>
                    <a href="#" aria-label="Instagram">📸</a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4 class="footer-heading">Khám Phá</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo home_url('/'); ?>">Trang Chủ</a></li>
                    <li><a href="<?php echo home_url('/phim/'); ?>">Phim Đang Chiếu</a></li>
                    <li><a href="<?php echo home_url('/lich-chieu/'); ?>">Lịch Chiếu</a></li>
                    <li><a href="<?php echo home_url('/tin-tuc/'); ?>">Tin Tức</a></li>
                    <li><a href="<?php echo home_url('/gioi-thieu/'); ?>">Giới Thiệu</a></li>
                    <li><a href="<?php echo home_url('/phim/?status=coming-soon'); ?>">Phim Sắp Chiếu</a></li>
                    <li><a href="<?php echo home_url('/rap-chieu-phim/'); ?>">Rạp Chiếu Phim</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div class="footer-col">
                <h4 class="footer-heading">Hỗ Trợ</h4>
                <ul class="footer-links">
                    <li><a href="#">Hướng Dẫn Đặt Vé</a></li>
                    <li><a href="#">Chính Sách Hoàn Vé</a></li>
                    <li><a href="#">Câu Hỏi Thường Gặp</a></li>
                    <li><a href="#">Liên Hệ</a></li>
                </ul>
            </div>

            <!-- Contact -->
            <div class="footer-col">
                <h4 class="footer-heading">Liên Hệ</h4>
                <ul class="footer-contact">
                    <li>📞 1900 6017</li>
                    <li>📧 support@cinema.vn</li>
                    <li>🕐 8:00 - 22:00 (Hàng ngày)</li>
                </ul>
                <div class="footer-apps">
                    <a href="#" class="app-badge">📱 App Store</a>
                    <a href="#" class="app-badge">🤖 Google Play</a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p><?php echo esc_html( get_theme_mod( 'cinema_footer_text', '© ' . date('Y') . ' Cinema Booking. All rights reserved.' ) ); ?></p>
            <ul class="footer-legal">
                <li><a href="#">Điều Khoản</a></li>
                <li><a href="#">Bảo Mật</a></li>
                <li><a href="#">Cookie</a></li>
            </ul>
        </div>

    </div>
</footer>

<script>
// Header scroll effect
const header = document.getElementById('site-header');
if (header) {
    window.addEventListener('scroll', () => {
        header.classList.toggle('scrolled', window.scrollY > 50);
    }, { passive: true });
}

// Mobile nav toggle
const navToggle = document.getElementById('nav-toggle');
const siteNav   = document.getElementById('site-nav');
if (navToggle && siteNav) {
    const setNav = (open) => {
        navToggle.setAttribute('aria-expanded', open);
        navToggle.classList.toggle('open', open);
        siteNav.classList.toggle('open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    };
    navToggle.addEventListener('click', () => {
        const open = navToggle.getAttribute('aria-expanded') === 'true';
        setNav(!open);
    });
    // Close drawer when a nav link is tapped
    siteNav.addEventListener('click', (e) => {
        if (e.target.closest('a')) setNav(false);
    });
    // Close on escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && siteNav.classList.contains('open')) setNav(false);
    });
    // Reset on resize back to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && siteNav.classList.contains('open')) setNav(false);
    });
}

// User dropdown
const userTrigger  = document.getElementById('user-trigger');
const userDropdown = document.getElementById('user-dropdown');
if (userTrigger) {
    userTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = userTrigger.getAttribute('aria-expanded') === 'true';
        userTrigger.setAttribute('aria-expanded', !open);
        userDropdown.classList.toggle('show', !open);
    });
    document.addEventListener('click', (e) => {
        if (!userTrigger.contains(e.target) && !userDropdown.contains(e.target)) {
            userTrigger.setAttribute('aria-expanded', 'false');
            userDropdown.classList.remove('show');
        }
    });
}
</script>

<?php wp_footer(); ?>
</body>
</html>
