<?php
/**
 * version.php — Quản lý phiên bản Plugin Cinema Booking
 * Định nghĩa constants, tự động log thay đổi version vào cinema_changelog
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ================================================================
// CONSTANTS
// ================================================================
define( 'CINEMA_PLUGIN_VERSION',  '1.4.0' );
define( 'CINEMA_DB_VERSION',      '1.0.0' );   // Tăng khi thay đổi schema DB
define( 'CINEMA_PLUGIN_DIR',      plugin_dir_path( dirname( __FILE__ ) ) );
define( 'CINEMA_PLUGIN_URL',      plugin_dir_url(  dirname( __FILE__ ) ) );
define( 'CINEMA_PLUGIN_BASENAME', plugin_basename( dirname( __FILE__ ) . '/../cinema-booking.php' ) );
define( 'CINEMA_TEXT_DOMAIN',     'cinema-booking' );

// ================================================================
// VERSION CHECKER — So sánh version cũ/mới, ghi log nếu thay đổi
// ================================================================
function cinema_check_version() {
    $saved_version = get_option( 'cinema_plugin_version', '0.0.0' );

    if ( version_compare( $saved_version, CINEMA_PLUGIN_VERSION, '<' ) ) {
        // Chạy database migration
        cinema_run_migrations( $saved_version, CINEMA_PLUGIN_VERSION );

        // Log thay đổi
        cinema_log_version_change( $saved_version, CINEMA_PLUGIN_VERSION );

        // Cập nhật version đã lưu
        update_option( 'cinema_plugin_version', CINEMA_PLUGIN_VERSION );
    }
}
add_action( 'plugins_loaded', 'cinema_check_version' );

// ================================================================
// DATABASE MIGRATION — Chạy khi version thay đổi
// Dùng dbDelta() để tạo/nâng cấp bảng an toàn (không mất data)
// ================================================================
function cinema_run_migrations( $from_version, $to_version ) {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();

    // --- v1.0.0: Schema khởi tạo ---
    if ( version_compare( $from_version, '1.0.0', '<' ) ) {

        // Bảng Roles
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_roles` (
            `RoleId`   INT AUTO_INCREMENT PRIMARY KEY,
            `RoleName` VARCHAR(50) NOT NULL UNIQUE
        ) $charset;";
        dbDelta( $sql );

        // Bảng Users (custom — liên kết WP users qua WpUserId)
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_users` (
            `UserId`              INT AUTO_INCREMENT PRIMARY KEY,
            `WpUserId`            BIGINT UNSIGNED DEFAULT NULL,
            `Username`            VARCHAR(50)  NOT NULL UNIQUE,
            `PasswordHash`        VARCHAR(255) NOT NULL,
            `FullName`            VARCHAR(100),
            `Email`               VARCHAR(100),
            `Phone`               VARCHAR(20),
            `RoleId`              INT          NOT NULL DEFAULT 3,
            `Status`              VARCHAR(20)  NOT NULL DEFAULT 'Active',
            `FailedLoginAttempts` INT          NOT NULL DEFAULT 0,
            `LockoutEnd`          DATETIME     NULL,
            `CreatedAt`           DATETIME     NOT NULL DEFAULT NOW(),
            KEY `idx_wp_user` (`WpUserId`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Genres
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_genres` (
            `GenreId`   INT AUTO_INCREMENT PRIMARY KEY,
            `GenreName` VARCHAR(100) NOT NULL UNIQUE
        ) $charset;";
        dbDelta( $sql );

        // Bảng Movies
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_movies` (
            `MovieId`     INT AUTO_INCREMENT PRIMARY KEY,
            `Title`       VARCHAR(200) NOT NULL,
            `Duration`    INT          NOT NULL,
            `Description` TEXT,
            `ReleaseDate` DATE,
            `PosterUrl`   VARCHAR(500),
            `BannerUrl`   VARCHAR(500),
            `Slug`        VARCHAR(220),
            `Status`      VARCHAR(50)  NOT NULL DEFAULT 'Now Showing',
            `CreatedAt`   DATETIME     NOT NULL DEFAULT NOW(),
            KEY `idx_status` (`Status`),
            KEY `idx_slug`   (`Slug`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng MovieGenres
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_movie_genres` (
            `MovieId` INT NOT NULL,
            `GenreId` INT NOT NULL,
            PRIMARY KEY (`MovieId`, `GenreId`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Theaters
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_theaters` (
            `TheaterId` INT AUTO_INCREMENT PRIMARY KEY,
            `Name`      VARCHAR(150) NOT NULL,
            `Address`   VARCHAR(255),
            `City`      VARCHAR(100),
            `Phone`     VARCHAR(20)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Rooms
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_rooms` (
            `RoomId`    INT AUTO_INCREMENT PRIMARY KEY,
            `TheaterId` INT         NOT NULL,
            `RoomName`  VARCHAR(50) NOT NULL,
            `SeatCount` INT         NOT NULL DEFAULT 0,
            KEY `idx_theater` (`TheaterId`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Seats
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_seats` (
            `SeatId`     INT AUTO_INCREMENT PRIMARY KEY,
            `SeatNumber` VARCHAR(10) NOT NULL,
            `SeatType`   VARCHAR(20) NOT NULL DEFAULT 'Standard',
            `RoomId`     INT         NOT NULL,
            KEY `idx_room` (`RoomId`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Showtimes
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_showtimes` (
            `ShowtimeId` INT AUTO_INCREMENT PRIMARY KEY,
            `MovieId`    INT           NOT NULL,
            `RoomId`     INT           NOT NULL,
            `StartTime`  DATETIME      NOT NULL,
            `EndTime`    DATETIME      NULL,
            `Price`      DECIMAL(18,2) NOT NULL,
            KEY `idx_movie_time` (`MovieId`, `StartTime`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Tickets
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_tickets` (
            `TicketId`    INT AUTO_INCREMENT PRIMARY KEY,
            `ShowtimeId`  INT         NOT NULL,
            `SeatId`      INT         NULL,
            `UserId`      INT         NOT NULL,
            `BookingTime` DATETIME    NOT NULL DEFAULT NOW(),
            `Status`      VARCHAR(50) NOT NULL DEFAULT 'Booked',
            KEY `idx_user_status` (`UserId`, `Status`),
            KEY `idx_showtime`    (`ShowtimeId`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng SeatHolds
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_seat_holds` (
            `SeatHoldId` INT AUTO_INCREMENT PRIMARY KEY,
            `ShowtimeId` INT         NOT NULL,
            `SeatId`     INT         NOT NULL,
            `UserId`     INT         NOT NULL,
            `HeldAt`     DATETIME    NOT NULL DEFAULT NOW(),
            `ExpiresAt`  DATETIME    NOT NULL,
            `ReleasedAt` DATETIME    NULL,
            `Status`     VARCHAR(20) NOT NULL DEFAULT 'Active',
            KEY `idx_expires`        (`ExpiresAt`),
            KEY `idx_showtime_seat`  (`ShowtimeId`, `SeatId`, `Status`)
        ) $charset;";
        dbDelta( $sql );

        // Bảng Payments
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_payments` (
            `PaymentId`     INT AUTO_INCREMENT PRIMARY KEY,
            `TicketId`      INT           NOT NULL UNIQUE,
            `Amount`        DECIMAL(18,2) NOT NULL DEFAULT 0,
            `Method`        VARCHAR(50)   NOT NULL DEFAULT 'Cash',
            `Status`        VARCHAR(50)   NOT NULL DEFAULT 'Pending',
            `TransactionId` VARCHAR(100)  NULL,
            `PaidAt`        DATETIME      NOT NULL DEFAULT NOW()
        ) $charset;";
        dbDelta( $sql );

        // Bảng Changelog
        $sql = "CREATE TABLE IF NOT EXISTS `cinema_changelog` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `version`    VARCHAR(20)  NOT NULL,
            `type`       VARCHAR(50)  NOT NULL,
            `name`       VARCHAR(100) NOT NULL,
            `changed_at` DATETIME     NOT NULL DEFAULT NOW(),
            `notes`      TEXT
        ) $charset;";
        dbDelta( $sql );
    }

    // --- v1.1.0: Thêm cột Rating vào Movies ---
    // if ( version_compare( $from_version, '1.1.0', '<' ) ) {
    //     $wpdb->query( "ALTER TABLE cinema_movies ADD COLUMN Rating DECIMAL(3,1) DEFAULT 0.0" );
    // }
}

// ================================================================
// LOG VERSION CHANGE vào bảng cinema_changelog
// ================================================================
function cinema_log_version_change( $from, $to ) {
    global $wpdb;

    $wpdb->insert(
        'cinema_changelog',
        [
            'version'    => $to,
            'type'       => 'plugin',
            'name'       => 'cinema-booking',
            'changed_at' => current_time( 'mysql' ),
            'notes'      => "Upgraded from v{$from} to v{$to}",
        ],
        [ '%s', '%s', '%s', '%s', '%s' ]
    );
}

// ================================================================
// HOOK: Log khi update plugin/theme qua WP Admin
// ================================================================
add_action( 'upgrader_process_complete', 'cinema_on_upgrade', 10, 2 );

function cinema_on_upgrade( $upgrader, $hook_extra ) {
    if ( empty( $hook_extra['type'] ) ) return;

    $type   = $hook_extra['type']; // 'plugin' | 'theme'
    $action = $hook_extra['action'] ?? 'update';

    if ( $action !== 'update' ) return;

    // Xác định tên plugin/theme được update
    $name = '';
    if ( $type === 'plugin' && ! empty( $hook_extra['plugins'] ) ) {
        $name = implode( ', ', $hook_extra['plugins'] );
    } elseif ( $type === 'theme' && ! empty( $hook_extra['themes'] ) ) {
        $name = implode( ', ', $hook_extra['themes'] );
    }

    if ( $name ) {
        global $wpdb;
        $wpdb->insert(
            'cinema_changelog',
            [
                'version'    => CINEMA_PLUGIN_VERSION,
                'type'       => $type,
                'name'       => $name,
                'changed_at' => current_time( 'mysql' ),
                'notes'      => "WP Admin update: {$action}",
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }
}
