<?php
/**
 * functions.php — Cinema Theme Setup
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ================================================================
// THEME SETUP
// ================================================================
function cinema_theme_setup() {
    // Tiếng Việt
    load_theme_textdomain( 'cinema-theme', get_template_directory() . '/languages' );

    // HTML5 support
    add_theme_support( 'html5', [ 'search-form','comment-form','comment-list','gallery','caption','script','style' ] );

    // Featured images
    add_theme_support( 'post-thumbnails' );
    add_image_size( 'movie-poster',  300, 450, true );  // 2:3 ratio
    add_image_size( 'movie-banner', 1280, 480, true );  // Banner wide
    add_image_size( 'movie-card',    400, 600, true );  // Card

    // Title tag
    add_theme_support( 'title-tag' );

    // Custom logo
    add_theme_support( 'custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    // Menus
    register_nav_menus( [
        'primary' => __( 'Menu Chính', 'cinema-theme' ),
        'footer'  => __( 'Menu Footer', 'cinema-theme' ),
    ] );
}
add_action( 'after_setup_theme', 'cinema_theme_setup' );

// ================================================================
// ENQUEUE ASSETS — auto-switch minified trên production
// ================================================================
function cinema_enqueue_assets() {
    $ver  = defined( 'CINEMA_PLUGIN_VERSION' ) ? CINEMA_PLUGIN_VERSION : '1.3.0';
    $dir  = get_template_directory_uri();
    $base = get_template_directory();

    // Trên prod (WP_DEBUG=false) ưu tiên file .min nếu tồn tại
    $use_min = ! ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    $css_file = ( $use_min && file_exists( $base . '/assets/css/main.min.css' ) ) ? 'main.min.css' : 'main.css';
    $js_file  = ( $use_min && file_exists( $base . '/assets/js/seat-picker.min.js' ) ) ? 'seat-picker.min.js' : 'seat-picker.js';

    // Google Fonts — Noto Sans (tiếng Việt) + Inter (logo wordmark)
    wp_enqueue_style(
        'google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@600;700;800;900&family=Noto+Sans:ital,wght@0,300;0,400;0,600;0,700;1,400&display=swap',
        [],
        null
    );

    // Main CSS
    wp_enqueue_style( 'cinema-main', $dir . '/assets/css/' . $css_file, ['google-fonts'], $ver );

    // Theme style.css (chỉ header, không có CSS thực)
    wp_enqueue_style( 'cinema-style', get_stylesheet_uri(), ['cinema-main'], $ver );

    // Seat picker JS (load trên template booking hoặc route custom /ve/{showtime_id}/)
    $request_path = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    $site_path    = trim( wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( $site_path && 0 === strpos( $request_path, $site_path ) ) {
        $request_path = trim( substr( $request_path, strlen( $site_path ) ), '/' );
    }
    $is_booking_route = (bool) preg_match( '#^ve/[0-9]+/?$#', $request_path );

    if ( is_page_template( 'page-booking.php' ) || $is_booking_route ) {
        $js_path = $base . '/assets/js/' . $js_file;
        $js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : $ver;
        wp_enqueue_script( 'cinema-seat-picker', $dir . '/assets/js/' . $js_file, [], $js_ver, true );

        // Cho phép admin/staff thấy nút "demo paid" để test sandbox khi callback không reach localhost
        $is_staff = false;
        if ( is_user_logged_in() ) {
            global $wpdb;
            $role_name = $wpdb->get_var( $wpdb->prepare(
                "SELECT cr.RoleName FROM cinema_users cu
                 JOIN cinema_roles cr ON cr.RoleId = cu.RoleId
                 WHERE cu.WpUserId = %d", get_current_user_id()
            ) );
            $is_staff = in_array( $role_name, [ 'Admin', 'Staff' ], true );
        }

        wp_localize_script( 'cinema-seat-picker', 'cinemaAjax', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cinema_booking_nonce' ),
            'isStaff' => $is_staff,
        ] );
    }
}
add_action( 'wp_enqueue_scripts', 'cinema_enqueue_assets' );

// ================================================================
// PERFORMANCE — Cleanup <head>, defer JS, preload
// ================================================================

// Bỏ generator meta (không lộ phiên bản WP)
remove_action( 'wp_head', 'wp_generator' );

// Bỏ emoji scripts (~10KB JS mỗi page)
remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles',     'print_emoji_styles' );
remove_action( 'admin_print_styles',  'print_emoji_styles' );
remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
add_filter( 'tiny_mce_plugins', function( $plugins ) {
    return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
} );
add_filter( 'emoji_svg_url', '__return_false' );

// Bỏ RSS feed links, REST link, wlwmanifest, shortlink (không cần với cinema site)
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'feed_links',       2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

// Bỏ Dashicons trên frontend cho user không phải admin (giảm 1 CSS request)
add_action( 'wp_print_styles', function() {
    if ( ! is_admin_bar_showing() && ! is_user_logged_in() ) {
        wp_dequeue_style( 'dashicons' );
        wp_deregister_style( 'dashicons' );
    }
}, 100 );

// Defer non-critical JS (seat-picker đã ở footer, thêm defer cho clean)
add_filter( 'script_loader_tag', function( $tag, $handle ) {
    if ( is_admin() ) return $tag;
    $defer_handles = [ 'cinema-seat-picker' ];
    if ( in_array( $handle, $defer_handles, true ) ) {
        return str_replace( ' src=', ' defer src=', $tag );
    }
    return $tag;
}, 10, 2 );

// Preload critical CSS + font preconnect (giảm CLS / LCP)
add_action( 'wp_head', function() {
    $dir  = get_template_directory_uri();
    $base = get_template_directory();
    $use_min  = ! ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    $css_file = ( $use_min && file_exists( $base . '/assets/css/main.min.css' ) ) ? 'main.min.css' : 'main.css';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preload" as="style" href="<?php echo esc_url( $dir . '/assets/css/' . $css_file ); ?>">
    <?php
}, 1 );

// ================================================================
// DISABLE XML-RPC & HEARTBEAT (sau khi WP load)
// ================================================================
add_filter( 'xmlrpc_enabled', '__return_false' );

// Giảm tần suất Heartbeat API (giảm tải CPU)
function cinema_limit_heartbeat( $settings ) {
    $settings['interval'] = 60; // 60 giây thay vì 15 giây
    return $settings;
}
add_filter( 'heartbeat_settings', 'cinema_limit_heartbeat' );

// Tắt Heartbeat hoàn toàn trên frontend
function cinema_disable_heartbeat() {
    if ( ! is_admin() ) {
        wp_deregister_script( 'heartbeat' );
    }
}
add_action( 'init', 'cinema_disable_heartbeat', 1 );

// ================================================================
// CUSTOMIZER — Theme Options
// ================================================================
function cinema_customizer( $wp_customize ) {
    // Panel Cinema Settings
    $wp_customize->add_panel( 'cinema_options', [
        'title'    => 'Cinema Settings',
        'priority' => 200,
    ] );

    // --- Section: Branding ---
    $wp_customize->add_section( 'cinema_branding', [
        'title' => 'Thương Hiệu',
        'panel' => 'cinema_options',
    ] );

    // Primary Color
    $wp_customize->add_setting( 'cinema_primary_color', [
        'default'           => '#e50914',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ] );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'cinema_primary_color', [
        'label'   => 'Màu Primary',
        'section' => 'cinema_branding',
    ] ) );

    // Footer Text
    $wp_customize->add_setting( 'cinema_footer_text', [
        'default'           => '© ' . date('Y') . ' Cinema Booking. All rights reserved.',
        'sanitize_callback' => 'sanitize_text_field',
    ] );
    $wp_customize->add_control( 'cinema_footer_text', [
        'label'   => 'Footer Text',
        'section' => 'cinema_branding',
        'type'    => 'text',
    ] );

    // --- Section: Banner ---
    $wp_customize->add_section( 'cinema_banner', [
        'title' => 'Banner Slider',
        'panel' => 'cinema_options',
    ] );

    // Slider Interval
    $wp_customize->add_setting( 'cinema_slider_interval', [
        'default'           => 5000,
        'sanitize_callback' => 'absint',
    ] );
    $wp_customize->add_control( 'cinema_slider_interval', [
        'label'       => 'Tốc độ Slider (ms)',
        'description' => 'Mặc định: 5000ms = 5 giây',
        'section'     => 'cinema_banner',
        'type'        => 'number',
        'input_attrs' => [ 'min' => 2000, 'max' => 10000, 'step' => 500 ],
    ] );
}
add_action( 'customize_register', 'cinema_customizer' );

// Output CSS custom từ Customizer
function cinema_customizer_css() {
    $primary = get_theme_mod( 'cinema_primary_color', '#e50914' );
    ?>
    <style>
        :root { --color-primary: <?php echo esc_attr( $primary ); ?>; }
    </style>
    <?php
}
add_action( 'wp_head', 'cinema_customizer_css' );

// ================================================================
// TEMPLATE TAGS — Hàm helper dùng trong các template
// ================================================================

/**
 * cinema_get_movies() — Lấy phim theo trạng thái
 */
function cinema_get_movies( $status = 'Now Showing', $limit = 8 ) {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT * FROM cinema_movies WHERE Status = %s ORDER BY CreatedAt DESC LIMIT %d",
        $status, $limit
    );
    return $wpdb->get_results( $sql );
}

/**
 * cinema_get_all_movies() — Lấy tất cả phim (cho banner)
 */
function cinema_get_banner_movies( $limit = 5 ) {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT * FROM cinema_movies WHERE Status = 'Now Showing' AND BannerUrl != '' ORDER BY RAND() LIMIT %d",
        $limit
    );
    return $wpdb->get_results( $sql );
}

/**
 * cinema_get_movies_by_status() — Lấy phim theo trạng thái, kèm thể loại.
 */
function cinema_get_movies_by_status( $status = null ) {
    global $wpdb;

    $where = '';
    $params = [];
    if ( $status ) {
        $where = 'WHERE m.Status = %s';
        $params[] = $status;
    }

    $sql = "SELECT m.*,
                   GROUP_CONCAT(g.GenreName ORDER BY g.GenreName SEPARATOR ', ') AS Genres
            FROM cinema_movies m
            LEFT JOIN cinema_movie_genres mg ON mg.MovieId = m.MovieId
            LEFT JOIN cinema_genres g ON g.GenreId = mg.GenreId
            {$where}
            GROUP BY m.MovieId
            ORDER BY FIELD(m.Status, 'Now Showing', 'Coming Soon', 'Ended'), m.ReleaseDate DESC, m.MovieId DESC";

    return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql );
}

/**
 * cinema_get_theaters() — Lấy danh sách rạp kèm số phòng, số ghế, lịch sắp tới.
 */
function cinema_get_theaters() {
    global $wpdb;

    return $wpdb->get_results(
        "SELECT t.*,
                COUNT(DISTINCT r.RoomId) AS RoomCount,
                COALESCE(SUM(r.SeatCount), 0) AS SeatCount,
                COUNT(DISTINCT st.ShowtimeId) AS UpcomingShowtimes
         FROM cinema_theaters t
         LEFT JOIN cinema_rooms r ON r.TheaterId = t.TheaterId
         LEFT JOIN cinema_showtimes st ON st.RoomId = r.RoomId AND st.StartTime > NOW()
         GROUP BY t.TheaterId
         ORDER BY t.City ASC, t.Name ASC"
    );
}

/**
 * cinema_get_showtimes() — Lấy suất chiếu theo phim và ngày
 */
function cinema_get_showtimes( $movie_id, $date = null ) {
    global $wpdb;
    if ( ! $date ) $date = date('Y-m-d');

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT st.*, r.RoomName, t.Name AS TheaterName, t.City
         FROM cinema_showtimes st
         JOIN cinema_rooms r ON st.RoomId = r.RoomId
         JOIN cinema_theaters t ON r.TheaterId = t.TheaterId
         WHERE st.MovieId = %d
           AND DATE(st.StartTime) = %s
           AND st.StartTime > NOW()
         ORDER BY st.StartTime ASC",
        $movie_id, $date
    ) );
}

/**
 * cinema_get_all_showtimes() — Lấy toàn bộ lịch chiếu theo ngày.
 */
function cinema_get_all_showtimes( $date = null ) {
    global $wpdb;
    if ( ! $date ) $date = date( 'Y-m-d' );

    return $wpdb->get_results( $wpdb->prepare(
        "SELECT st.*, m.MovieId, m.Title AS MovieTitle, m.Slug, m.PosterUrl,
                r.RoomName, r.SeatCount, t.Name AS TheaterName, t.City,
                (r.SeatCount - COUNT(tk.TicketId)) AS AvailableSeats
         FROM cinema_showtimes st
         JOIN cinema_movies m ON st.MovieId = m.MovieId
         JOIN cinema_rooms r ON st.RoomId = r.RoomId
         JOIN cinema_theaters t ON r.TheaterId = t.TheaterId
         LEFT JOIN cinema_tickets tk
             ON tk.ShowtimeId = st.ShowtimeId AND tk.Status = 'Booked'
         WHERE DATE(st.StartTime) = %s
           AND st.StartTime > NOW()
         GROUP BY st.ShowtimeId
         ORDER BY t.Name ASC, m.Title ASC, st.StartTime ASC",
        $date
    ) );
}

/**
 * cinema_get_seat_map() — Trả về JSON trạng thái ghế cho showtime
 */
function cinema_get_seat_map( $showtime_id ) {
    global $wpdb;

    // Lấy RoomId của showtime
    $showtime = $wpdb->get_row( $wpdb->prepare(
        "SELECT RoomId FROM cinema_showtimes WHERE ShowtimeId = %d", $showtime_id
    ) );
    if ( ! $showtime ) return [];

    // Lấy tất cả ghế + trạng thái
    $seats = $wpdb->get_results( $wpdb->prepare(
        "SELECT s.SeatId, s.SeatNumber, s.SeatType,
                CASE
                    WHEN t.TicketId IS NOT NULL THEN 'booked'
                    WHEN sh.SeatHoldId IS NOT NULL AND sh.ExpiresAt > NOW() THEN 'held'
                    ELSE 'available'
                END AS Status
         FROM cinema_seats s
         LEFT JOIN cinema_tickets t
             ON t.SeatId = s.SeatId AND t.ShowtimeId = %d AND t.Status = 'Booked'
         LEFT JOIN cinema_seat_holds sh
             ON sh.SeatId = s.SeatId AND sh.ShowtimeId = %d AND sh.Status = 'Active' AND sh.ExpiresAt > NOW()
         WHERE s.RoomId = %d
         ORDER BY s.SeatNumber ASC",
        $showtime_id, $showtime_id, $showtime->RoomId
    ) );

    return $seats;
}

/**
 * cinema_format_price() — Format giá tiền VND
 */
function cinema_format_price( $price ) {
    return number_format( $price, 0, ',', '.' ) . ' ₫';
}

/**
 * cinema_format_duration() — Format thời lượng phim
 */
function cinema_format_duration( $minutes ) {
    $h = floor( $minutes / 60 );
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}p" : "{$m} phút";
}

/**
 * cinema_asset_url() — Chuẩn hóa URL asset khi WordPress chạy trong thư mục con.
 */
function cinema_asset_url( $url ) {
    if ( empty( $url ) ) {
        return '';
    }

    if ( preg_match( '#^https?://#i', $url ) ) {
        return $url;
    }

    if ( 0 === strpos( $url, '/wp-content/' ) ) {
        return home_url( $url );
    }

    return $url;
}

// ================================================================
// REWRITE RULES — Permalink đẹp cho phim và vé
// ================================================================
function cinema_rewrite_rules() {
    add_rewrite_rule( '^phim/([^/]+)/?$', 'index.php?cinema_movie_slug=$matches[1]', 'top' );
    add_rewrite_rule( '^phim/?$',         'index.php?cinema_static_page=movies', 'top' );
    add_rewrite_rule( '^rap-chieu-phim/?$', 'index.php?cinema_static_page=theaters', 'top' );
    add_rewrite_rule( '^ve/([0-9]+)/?$',  'index.php?cinema_showtime_id=$matches[1]', 'top' );
    add_rewrite_rule( '^lich-chieu/?$',   'index.php?cinema_static_page=showtimes', 'top' );
    add_rewrite_rule( '^tin-tuc/?$',      'index.php?cinema_static_page=news', 'top' );
    add_rewrite_rule( '^gioi-thieu/?$',   'index.php?cinema_static_page=about', 'top' );
    add_rewrite_rule( '^profile/?$',      'index.php?cinema_static_page=profile', 'top' );
}
add_action( 'init', 'cinema_rewrite_rules' );

function cinema_query_vars( $vars ) {
    $vars[] = 'cinema_movie_slug';
    $vars[] = 'cinema_showtime_id';
    $vars[] = 'cinema_static_page';
    return $vars;
}
add_filter( 'query_vars', 'cinema_query_vars' );

function cinema_template_include( $template ) {
    $static_page = get_query_var( 'cinema_static_page' );

    if ( $static_page ) {
        $templates = [
            'movies'    => 'page-movies.php',
            'theaters'  => 'page-theaters.php',
            'showtimes' => 'page-showtimes.php',
            'news'      => 'page-news.php',
            'about'     => 'page-about.php',
            'profile'   => 'page-profile.php',
        ];

        if ( isset( $templates[ $static_page ] ) ) {
            $custom_template = get_template_directory() . '/' . $templates[ $static_page ];
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }
    }

    return $template;
}
add_filter( 'template_include', 'cinema_template_include' );

function cinema_static_page_router() {
    if ( is_admin() ) {
        return;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $path = trim( wp_parse_url( $request_uri, PHP_URL_PATH ), '/' );
    $site_path = trim( wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( $site_path && 0 === strpos( $path, $site_path ) ) {
        $path = trim( substr( $path, strlen( $site_path ) ), '/' );
    }

    $templates = [
        'phim'          => 'page-movies.php',
        'rap-chieu-phim' => 'page-theaters.php',
        'lich-chieu' => 'page-showtimes.php',
        'tin-tuc'    => 'page-news.php',
        'gioi-thieu' => 'page-about.php',
        'dang-nhap'  => 'page-login.php',
        'dang-ky'    => 'page-register.php',
        'profile'    => 'page-profile.php',
        'truy-cap-bi-tu-choi' => 'page-access-denied.php',
        'privacy'    => 'page-privacy.php',
    ];

    if ( preg_match( '#^phim/([^/]+)$#', $path, $matches ) ) {
        set_query_var( 'cinema_movie_slug', sanitize_title( $matches[1] ) );
        status_header( 200 );
        nocache_headers();
        include get_template_directory() . '/single-movie.php';
        exit;
    }

    if ( preg_match( '#^ve/([0-9]+)$#', $path, $matches ) ) {
        set_query_var( 'cinema_showtime_id', absint( $matches[1] ) );
        status_header( 200 );
        nocache_headers();
        include get_template_directory() . '/page-booking.php';
        exit;
    }

    if ( isset( $templates[ $path ] ) ) {
        status_header( 200 );
        nocache_headers();
        include get_template_directory() . '/' . $templates[ $path ];
        exit;
    }
}
add_action( 'template_redirect', 'cinema_static_page_router', 0 );

function cinema_flush_rewrites_once() {
    $version = '2026-05-10-profile-route';
    if ( get_option( 'cinema_rewrite_version' ) !== $version ) {
        flush_rewrite_rules( false );
        update_option( 'cinema_rewrite_version', $version );
    }
}
add_action( 'init', 'cinema_flush_rewrites_once', 20 );

function cinema_add_primary_menu_links( $items, $args ) {
    if ( isset( $args->theme_location ) && 'primary' === $args->theme_location ) {
        $links = [
            '/lich-chieu/' => 'Lịch Chiếu',
            '/tin-tuc/'    => 'Tin Tức',
            '/gioi-thieu/' => 'Giới Thiệu',
        ];

        foreach ( $links as $path => $label ) {
            if ( false === strpos( $items, $path ) ) {
                $items .= '<li><a href="' . esc_url( home_url( $path ) ) . '">' . esc_html( $label ) . '</a></li>';
            }
        }
    }

    return $items;
}
add_filter( 'wp_nav_menu_items', 'cinema_add_primary_menu_links', 10, 2 );

// ================================================================
// TRANSIENTS CACHE — Output caching nhẹ
// ================================================================
function cinema_get_cached_movies( $status = 'Now Showing', $limit = 8 ) {
    $cache_key = "cinema_movies_{$status}_{$limit}";
    $cached    = get_transient( $cache_key );

    if ( false === $cached ) {
        $cached = cinema_get_movies( $status, $limit );
        set_transient( $cache_key, $cached, 15 * MINUTE_IN_SECONDS );
    }

    return $cached;
}

// Xóa cache khi plugin cinema cập nhật dữ liệu
function cinema_clear_movie_cache() {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cinema_%'" );
}
