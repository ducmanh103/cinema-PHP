<?php
/**
 * Plugin Name:       Cinema Booking
 * Plugin URI:        https://cinemahub.example/
 * Description:       Core functionality for the Cinema Management System. Handles database, movies, showtimes, seats, and bookings.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Nguyễn Đức Mạnh
 * Text Domain:       cinema-booking
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Định nghĩa version & migration
require_once plugin_dir_path( __FILE__ ) . 'includes/version.php';

class Cinema_Booking_Plugin {
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        // Core Classes
        require_once CINEMA_PLUGIN_DIR . 'includes/class-cinema-auth.php';
        require_once CINEMA_PLUGIN_DIR . 'includes/class-cinema-booking.php';
        require_once CINEMA_PLUGIN_DIR . 'includes/class-cinema-seat.php';
        require_once CINEMA_PLUGIN_DIR . 'includes/class-cinema-rest-api.php';
        require_once CINEMA_PLUGIN_DIR . 'includes/class-cinema-vnpay.php';

        // Public / AJAX
        require_once CINEMA_PLUGIN_DIR . 'public/class-cinema-ajax.php';


        // Admin
        if ( is_admin() ) {
            require_once CINEMA_PLUGIN_DIR . 'admin/class-cinema-admin.php';
        }
    }

    private function init_hooks() {
        add_action( 'init', [ $this, 'register_session' ], 1 );
    }

    public function register_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }
}

// Khởi tạo Plugin
function run_cinema_booking() {
    new Cinema_Booking_Plugin();
}
add_action( 'plugins_loaded', 'run_cinema_booking' );
