<?php
/**
 * wp-config.php — Cinema Booking
 *
 * Environment-aware:
 *   - Local (XAMPP)        : HTTP_HOST chứa 'localhost' hoặc '127.0.0.1'
 *   - Production (VPS)     : mọi host khác
 *
 * Một codebase chạy được cho cả 2 môi trường — không phải sửa file khi deploy.
 */

// ================================================================
// ENVIRONMENT DETECTION
// ================================================================
$cinema_http_host = $_SERVER['HTTP_HOST'] ?? '';
$cinema_is_local  = (
    $cinema_http_host === ''                                      // CLI / cron
    || stripos( $cinema_http_host, 'localhost' )  !== false
    || stripos( $cinema_http_host, '127.0.0.1' )  !== false
    || stripos( $cinema_http_host, '.local' )     !== false
    || stripos( $cinema_http_host, '.test' )      !== false
);
define( 'CINEMA_ENV', $cinema_is_local ? 'local' : 'production' );

// ================================================================
// CƠ SỞ DỮ LIỆU
// ================================================================
if ( CINEMA_ENV === 'local' ) {
    define( 'DB_NAME',     'cinema_wp' );
    define( 'DB_USER',     'root' );
    define( 'DB_PASSWORD', '' );
    define( 'DB_HOST',     'localhost' );
} else {
    // VPS — MySQL chạy local trên VPS, dùng user root.
    // Có thể override bằng biến môi trường (export DB_USER=... DB_PASSWORD=...) nếu muốn.
    define( 'DB_NAME',     getenv( 'DB_NAME' )     ?: 'cinema_wp' );
    define( 'DB_USER',     getenv( 'DB_USER' )     ?: 'root' );
    define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) ?: '' );
    define( 'DB_HOST',     getenv( 'DB_HOST' )     ?: 'localhost' );
}
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );

// ================================================================
// AUTHENTICATION KEYS & SALTS
// Đã regenerate từ https://api.wordpress.org/secret-key/1.1/salt/
// LƯU Ý: thay đổi salts sẽ logout toàn bộ user — đó là behaviour bình thường.
// ================================================================
define( 'AUTH_KEY',         'ozS#Tk7+%5%FkH$xc.Vm> C: ^*owNtv$c7U3o#Pf)wk 1WyP4tnDSRZVF@31B&a' );
define( 'SECURE_AUTH_KEY',  '|v@1-@+vZ!E8qv_;Nt-`r>EKQ;#Z,Ad(Io-h/K:(-^6:bP01)DdfITb|I^BX=D|3' );
define( 'LOGGED_IN_KEY',    '68Zt{FF%zHK5%??Em~/WOs+1Hv|A_PwfdlTbME`+]kM7,V &Or@2o3-{;Z;OXe9*' );
define( 'NONCE_KEY',        'm@kY|_76o]^ctn<C G}}.4U2JM$muXX5E*V@yU$+6uRv&ne,%.-b7fJG)SpxEj]C' );
define( 'AUTH_SALT',        '|kRT|K8J?_o|%lR4qp[Uc2hgm&qC=je#5/3?x3a+!@u^Di5[x%dxHG{,1%B!a2vD' );
define( 'SECURE_AUTH_SALT', 'EO 7zKVKHuP[Jo>(Et|>]>$#a2)M2CAVvcyf*h=bOu/SnFIUFvkv?xo= CRj[W?`' );
define( 'LOGGED_IN_SALT',   'r*5*sTPt7B=y{+|d]Q(haOFHxX{+YyX~+dhO^#{0SR<T!B1UGCmJR4EE[#5`~*e`' );
define( 'NONCE_SALT',       'b~QVJ;Z;)fv3v%KYCY+g;+Y*ry2RecL;:zF3Iv#7zMU|+_IK:$FU)lLvW+d#}sUK' );

// ================================================================
// TABLE PREFIX
// ================================================================
$table_prefix = 'wp_';

// ================================================================
// DEBUG — tự động theo môi trường
// ================================================================
if ( CINEMA_ENV === 'local' ) {
    define( 'WP_DEBUG',         true );
    define( 'WP_DEBUG_LOG',     true );
    define( 'WP_DEBUG_DISPLAY', false );
    define( 'SCRIPT_DEBUG',     false );
} else {
    define( 'WP_DEBUG',         false );
    define( 'WP_DEBUG_LOG',     false );
    define( 'WP_DEBUG_DISPLAY', false );
    define( 'SCRIPT_DEBUG',     false );
    @ini_set( 'display_errors', 0 );
}

// ================================================================
// PERFORMANCE
// ================================================================
define( 'WP_POST_REVISIONS',          5 );
define( 'AUTOSAVE_INTERVAL',          300 );
define( 'EMPTY_TRASH_DAYS',           7 );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE',        false );
define( 'WP_MEMORY_LIMIT',            '256M' );
define( 'WP_MAX_MEMORY_LIMIT',        '512M' );
define( 'WP_CACHE',                   true );  // sẵn sàng cho object cache plugin

// Tắt cron tự động trên prod — dùng system cron `wget -q -O- https://domain/wp-cron.php`
if ( CINEMA_ENV === 'production' ) {
    define( 'DISABLE_WP_CRON', true );
}

// ================================================================
// FILESYSTEM
// ================================================================
define( 'FS_METHOD', 'direct' );

// ================================================================
// SECURITY
// ================================================================
// Trên production, khoá Plugin/Theme editor trong wp-admin
define( 'DISALLOW_FILE_EDIT', CINEMA_ENV === 'production' );

// Tự bật SSL admin khi đang chạy HTTPS
$cinema_is_https = ( ! empty( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) !== 'off' )
                || ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' )
                || ( ( $_SERVER['SERVER_PORT'] ?? '' ) == 443 );
define( 'FORCE_SSL_ADMIN', $cinema_is_https );

// Nếu chạy sau reverse proxy (nginx → apache), respect X-Forwarded-Proto
if ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}

// ================================================================
// SITE PATH — tự detect từ filesystem (chạy đúng ở root, /cinema/, hoặc bất kỳ subdir nào)
// So sánh thư mục chứa wp-config.php với DOCUMENT_ROOT để tìm subpath.
// ================================================================
if ( ! defined( 'CINEMA_SITE_PATH' ) ) {
    $cinema_site_path = '';
    $cinema_doc_root  = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : '';

    if ( $cinema_doc_root !== '' ) {
        // Normalize separators (Windows vs Unix) + bỏ trailing slash
        $cinema_doc_root_norm = rtrim( str_replace( '\\', '/', realpath( $cinema_doc_root ) ?: $cinema_doc_root ), '/' );
        $cinema_abs_path      = rtrim( str_replace( '\\', '/', __DIR__ ), '/' );

        if ( $cinema_doc_root_norm !== '' && stripos( $cinema_abs_path, $cinema_doc_root_norm ) === 0 ) {
            $cinema_rel = substr( $cinema_abs_path, strlen( $cinema_doc_root_norm ) );
            $cinema_rel = '/' . ltrim( $cinema_rel, '/' );
            if ( $cinema_rel !== '/' ) {
                $cinema_site_path = rtrim( $cinema_rel, '/' );
            }
        }
    }

    // Fallback CLI / cron khi không có DOCUMENT_ROOT
    if ( $cinema_site_path === '' && CINEMA_ENV === 'local' ) {
        $cinema_site_path = '/cinema';
    }

    define( 'CINEMA_SITE_PATH', $cinema_site_path );
}

// ================================================================
// URLS — auto-detect, không hardcode
// ================================================================
if ( ! defined( 'WP_HOME' ) ) {
    if ( $cinema_http_host !== '' ) {
        $cinema_scheme = $cinema_is_https ? 'https' : 'http';
        define( 'WP_HOME',    $cinema_scheme . '://' . $cinema_http_host . CINEMA_SITE_PATH );
        define( 'WP_SITEURL', $cinema_scheme . '://' . $cinema_http_host . CINEMA_SITE_PATH );
    } else {
        // CLI fallback
        define( 'WP_HOME',    'http://localhost' . CINEMA_SITE_PATH );
        define( 'WP_SITEURL', 'http://localhost' . CINEMA_SITE_PATH );
    }
}

// ================================================================
// VNPAY — đặt key thật ở đây cho production (sandbox keys hardcode trong class)
// Đăng ký tại https://sandbox.vnpayment.vn để lấy TMN_CODE + HASH_SECRET
// ================================================================
// define( 'CINEMA_VNPAY_TMN_CODE',    'XXXXYYYY' );
// define( 'CINEMA_VNPAY_HASH_SECRET', 'YOUR_HASH_SECRET_HERE' );
// define( 'CINEMA_VNPAY_PAY_URL',     'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html' );
// define( 'CINEMA_VNPAY_RETURN_URL',  'https://your-domain.com/wp-admin/admin-ajax.php?action=cinema_vnpay_return' );

// ================================================================
// BOOTSTRAP
// ================================================================
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
