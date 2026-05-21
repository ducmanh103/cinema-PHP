<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_Auth {
    public function __construct() {
        // Đồng bộ khi WP user đăng ký
        add_action( 'user_register', [ $this, 'sync_wp_user_to_cinema' ], 10, 1 );
        
        // Đồng bộ khi WP user cập nhật
        add_action( 'profile_update', [ $this, 'sync_wp_user_to_cinema' ], 10, 1 );

        // Đồng bộ khi đăng nhập bằng tài khoản WordPress sẵn có
        add_action( 'wp_login', [ $this, 'sync_logged_in_wp_user' ], 10, 2 );
    }

    public function sync_logged_in_wp_user( $user_login, $user ) {
        if ( $user && ! empty( $user->ID ) ) {
            $this->sync_wp_user_to_cinema( $user->ID );
        }
    }

    public function sync_wp_user_to_cinema( $user_id ) {
        global $wpdb;
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT UserId FROM cinema_users WHERE WpUserId = %d", $user_id
        ) );

        $data = [
            'Username' => $user->user_login,
            'FullName' => $user->display_name,
            'Email'    => $user->user_email,
        ];

        if ( $exists ) {
            $wpdb->update( 'cinema_users', $data, [ 'WpUserId' => $user_id ] );
        } else {
            $by_identity = $wpdb->get_var( $wpdb->prepare(
                "SELECT UserId FROM cinema_users WHERE Username = %s OR Email = %s LIMIT 1",
                $user->user_login,
                $user->user_email
            ) );

            if ( $by_identity ) {
                $data['WpUserId'] = $user_id;
                $wpdb->update( 'cinema_users', $data, [ 'UserId' => $by_identity ] );
                return;
            }

            $data['WpUserId'] = $user_id;
            $data['PasswordHash'] = wp_hash_password( wp_generate_password() ); // Không dùng pass này để login, login qua WP
            $data['RoleId'] = 3; // 3 = Customer
            $wpdb->insert( 'cinema_users', $data );
        }
    }

    // Helper: Lấy Cinema UserId từ WpUserId hiện tại
    public static function get_current_cinema_user_id() {
        global $wpdb;
        $wp_id = get_current_user_id();
        if ( ! $wp_id ) return 0;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT UserId FROM cinema_users WHERE WpUserId = %d", $wp_id
        ) );
    }
}
new Cinema_Auth();
