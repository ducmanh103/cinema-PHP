<?php
/**
 * wp-config-sample.php — Template để copy thành wp-config.php
 *
 * Hướng dẫn deploy:
 *   1. cp wp-config-sample.php wp-config.php
 *   2. Sửa DB credentials theo VPS thật
 *   3. Generate salts mới: https://api.wordpress.org/secret-key/1.1/salt/
 *   4. Upload nhưng GIỮ wp-config.php KHÔNG trong git (đã có .gitignore)
 */

$cinema_http_host = $_SERVER['HTTP_HOST'] ?? '';
$cinema_is_local  = (
    $cinema_http_host === ''
    || stripos( $cinema_http_host, 'localhost' )  !== false
    || stripos( $cinema_http_host, '127.0.0.1' )  !== false
);
define( 'CINEMA_ENV', $cinema_is_local ? 'local' : 'production' );

// ============== DATABASE ==============
if ( CINEMA_ENV === 'local' ) {
    define( 'DB_NAME',     'cinema_wp' );
    define( 'DB_USER',     'root' );
    define( 'DB_PASSWORD', '' );
    define( 'DB_HOST',     'localhost' );
} else {
    define( 'DB_NAME',     'YOUR_DB_NAME' );
    define( 'DB_USER',     'YOUR_DB_USER' );
    define( 'DB_PASSWORD', 'YOUR_DB_PASS' );
    define( 'DB_HOST',     'localhost' );
}
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', 'utf8mb4_unicode_ci' );

// ============== SALTS — GENERATE MỚI ==============
define( 'AUTH_KEY',         'put your unique phrase here' );
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );

$table_prefix = 'wp_';

// ============== DEBUG ==============
define( 'WP_DEBUG',         CINEMA_ENV === 'local' );
define( 'WP_DEBUG_LOG',     CINEMA_ENV === 'local' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG',     false );

// ============== PERFORMANCE ==============
define( 'WP_POST_REVISIONS',          5 );
define( 'AUTOSAVE_INTERVAL',          300 );
define( 'EMPTY_TRASH_DAYS',           7 );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE',        false );
define( 'WP_MEMORY_LIMIT',            '256M' );
define( 'WP_MAX_MEMORY_LIMIT',        '512M' );
define( 'WP_CACHE',                   true );
if ( CINEMA_ENV === 'production' ) {
    define( 'DISABLE_WP_CRON', true );
}

// ============== SECURITY ==============
define( 'FS_METHOD',          'direct' );
define( 'DISALLOW_FILE_EDIT', CINEMA_ENV === 'production' );

$cinema_is_https = ( ! empty( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) !== 'off' )
                || ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' );
define( 'FORCE_SSL_ADMIN', $cinema_is_https );
if ( ( $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' ) === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}

// ============== URLS ==============
if ( ! defined( 'WP_HOME' ) && $cinema_http_host !== '' ) {
    $scheme = $cinema_is_https ? 'https' : 'http';
    define( 'WP_HOME',    $scheme . '://' . $cinema_http_host . '/cinema' );
    define( 'WP_SITEURL', $scheme . '://' . $cinema_http_host . '/cinema' );
}

// ============== BOOTSTRAP ==============
if ( ! defined( 'ABSPATH' ) ) define( 'ABSPATH', __DIR__ . '/' );
require_once ABSPATH . 'wp-settings.php';
