<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo is_single() ? get_the_excerpt() : get_bloginfo('description'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php wp_head(); ?>
</head>
<body <?php body_class('cinema-site'); ?>>
<?php wp_body_open(); ?>

<header class="site-header" id="site-header">
    <div class="header-inner container">

        <!-- Logo -->
        <a href="<?php echo esc_url( home_url('/') ); ?>" class="site-logo" aria-label="<?php echo esc_attr( get_bloginfo('name') ); ?> - Trang chủ">
            <?php if ( has_custom_logo() ): ?>
                <?php the_custom_logo(); ?>
            <?php else: ?>
                <span class="logo-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="cnLogoGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#ff4757"/>
                                <stop offset="55%" stop-color="#e50914"/>
                                <stop offset="100%" stop-color="#8b0610"/>
                            </linearGradient>
                            <radialGradient id="cnLogoCenter" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#ffffff"/>
                                <stop offset="100%" stop-color="#f5f7fb"/>
                            </radialGradient>
                        </defs>
                        <circle cx="24" cy="24" r="22" fill="url(#cnLogoGrad)"/>
                        <circle cx="24" cy="24" r="22" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="1"/>
                        <g class="logo-perfs" fill="#0f1014">
                            <circle cx="24" cy="8.5" r="2.3"/>
                            <circle cx="39.5" cy="24" r="2.3"/>
                            <circle cx="24" cy="39.5" r="2.3"/>
                            <circle cx="8.5" cy="24" r="2.3"/>
                            <circle cx="35" cy="13" r="1.9" opacity="0.85"/>
                            <circle cx="35" cy="35" r="1.9" opacity="0.85"/>
                            <circle cx="13" cy="35" r="1.9" opacity="0.85"/>
                            <circle cx="13" cy="13" r="1.9" opacity="0.85"/>
                        </g>
                        <circle cx="24" cy="24" r="9.5" fill="url(#cnLogoCenter)"/>
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
            <?php endif; ?>
        </a>

        <!-- Navigation -->
        <nav class="site-nav" id="site-nav" aria-label="Menu chính">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'nav-menu',
                'fallback_cb'    => function() {
                    echo '<ul class="nav-menu">
                        <li><a href="' . home_url('/') . '">Trang Chủ</a></li>
                        <li><a href="' . home_url('/phim/') . '">Phim</a></li>
                        <li><a href="' . home_url('/lich-chieu/') . '">Lịch Chiếu</a></li>
                        <li><a href="' . home_url('/rap-chieu-phim/') . '">Rạp</a></li>
                        <li><a href="' . home_url('/tin-tuc/') . '">Tin Tức</a></li>
                        <li><a href="' . home_url('/gioi-thieu/') . '">Giới Thiệu</a></li>
                    </ul>';
                },
            ]); ?>

            <!-- Mobile-only auth actions (hiển thị trong drawer) -->
            <div class="nav-mobile-actions">
                <?php if ( is_user_logged_in() ) :
                    $current_user = wp_get_current_user(); ?>
                    <div class="nav-user-info">
                        <span class="user-avatar">👤</span>
                        <span><?php echo esc_html( $current_user->display_name ); ?></span>
                    </div>
                    <a href="<?php echo esc_url( home_url('/profile/') ); ?>" class="btn btn-outline btn-block">🎟 Vé Của Tôi</a>
                    <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>" class="btn btn-ghost btn-block">🚪 Đăng Xuất</a>
                <?php else : ?>
                    <a href="<?php echo esc_url( home_url('/dang-nhap/') ); ?>" class="btn btn-outline btn-block">Đăng Nhập</a>
                    <a href="<?php echo esc_url( home_url('/dang-ky/') ); ?>" class="btn btn-primary btn-block">Đăng Ký</a>
                <?php endif; ?>
            </div>
        </nav>

        <!-- User Actions -->
        <div class="header-actions">
            <?php if ( is_user_logged_in() ):
                $current_user = wp_get_current_user(); ?>
                <div class="user-menu">
                    <button class="user-trigger" id="user-trigger" aria-expanded="false">
                        <span class="user-avatar">👤</span>
                        <span class="user-name"><?php echo esc_html( $current_user->display_name ); ?></span>
                        <span class="chevron">▾</span>
                    </button>
                    <div class="user-dropdown" id="user-dropdown">
                        <a href="<?php echo esc_url( home_url('/profile/') ); ?>">🎟 Vé Của Tôi</a>
                        <a href="<?php echo esc_url( admin_url() ); ?>">⚙ Quản Trị</a>
                        <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>">🚪 Đăng Xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo esc_url( home_url('/dang-nhap/') ); ?>" class="btn btn-outline btn-sm">Đăng Nhập</a>
                <a href="<?php echo esc_url( home_url('/dang-ky/') ); ?>" class="btn btn-primary btn-sm">Đăng Ký</a>
            <?php endif; ?>
        </div>

        <!-- Mobile Toggle -->
        <button class="nav-toggle" id="nav-toggle" aria-label="Mở menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>

    </div>
</header>

<main class="site-main" id="main-content">
