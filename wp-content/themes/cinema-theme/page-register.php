<?php
/**
 * page-register.php — Đăng ký tài khoản
 */
if ( is_user_logged_in() ) {
    wp_redirect( home_url( '/' ) );
    exit;
}

global $wpdb;
$errors = [];

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( ! isset( $_POST['cinema_register_nonce'] ) || ! wp_verify_nonce( $_POST['cinema_register_nonce'], 'cinema_register' ) ) {
        $errors[] = 'Phiên đăng ký không hợp lệ. Vui lòng thử lại.';
    } else {
        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $full_name = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password = (string) ( $_POST['password'] ?? '' );
        $confirm = (string) ( $_POST['confirm_password'] ?? '' );

        if ( strlen( $username ) < 3 ) $errors[] = 'Tên đăng nhập tối thiểu 3 ký tự.';
        if ( strlen( $password ) < 6 ) $errors[] = 'Mật khẩu tối thiểu 6 ký tự.';
        if ( $password !== $confirm ) $errors[] = 'Xác nhận mật khẩu không khớp.';
        if ( $email && ! is_email( $email ) ) $errors[] = 'Email không hợp lệ.';
        if ( username_exists( $username ) || $wpdb->get_var( $wpdb->prepare( 'SELECT UserId FROM cinema_users WHERE Username = %s', $username ) ) ) $errors[] = 'Tên đăng nhập đã tồn tại.';
        if ( $email && ( email_exists( $email ) || $wpdb->get_var( $wpdb->prepare( 'SELECT UserId FROM cinema_users WHERE Email = %s', $email ) ) ) ) $errors[] = 'Email đã được sử dụng.';

        if ( empty( $errors ) ) {
            $wp_user_id = wp_create_user( $username, $password, $email );
            if ( is_wp_error( $wp_user_id ) ) {
                $errors[] = $wp_user_id->get_error_message();
            } else {
                wp_update_user( [ 'ID' => $wp_user_id, 'display_name' => $full_name ?: $username ] );
                $cinema_data = [
                    'WpUserId' => $wp_user_id,
                    'Username' => $username,
                    'PasswordHash' => password_hash( $password, PASSWORD_BCRYPT ),
                    'FullName' => $full_name,
                    'Email' => $email,
                    'RoleId' => 3,
                    'Status' => 'Active',
                    'CreatedAt' => current_time( 'mysql' ),
                ];
                $existing_cinema_id = (int) $wpdb->get_var( $wpdb->prepare(
                    'SELECT UserId FROM cinema_users WHERE WpUserId = %d OR Username = %s LIMIT 1',
                    $wp_user_id,
                    $username
                ) );
                if ( $existing_cinema_id ) {
                    unset( $cinema_data['CreatedAt'] );
                    $wpdb->update( 'cinema_users', $cinema_data, [ 'UserId' => $existing_cinema_id ] );
                } else {
                    $wpdb->insert( 'cinema_users', $cinema_data );
                }
                wp_redirect( add_query_arg( 'registered', '1', home_url( '/dang-nhap/' ) ) );
                exit;
            }
        }
    }
}
get_header();
?>
<section class="section auth-inline-page">
    <div class="container">
        <div class="auth-card auth-card-inline">
            <div class="auth-brand">
                <div class="auth-icon">👤</div>
                <h1>Tạo tài khoản</h1>
                <p>Đăng ký để đặt vé và theo dõi lịch sử vé.</p>
            </div>
            <?php if ( $errors ) : ?><div class="auth-errors"><?php foreach ( $errors as $error ) : ?><p><?php echo esc_html( $error ); ?></p><?php endforeach; ?></div><?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'cinema_register', 'cinema_register_nonce' ); ?>
                <label>Tên đăng nhập *</label>
                <input type="text" name="username" required>
                <label>Họ và tên</label>
                <input type="text" name="full_name">
                <label>Email</label>
                <input type="email" name="email">
                <label>Mật khẩu *</label>
                <input type="password" name="password" required>
                <label>Xác nhận mật khẩu *</label>
                <input type="password" name="confirm_password" required>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Đăng Ký</button>
            </form>
            <p class="auth-switch">Đã có tài khoản? <a href="<?php echo esc_url( home_url( '/dang-nhap/' ) ); ?>">Đăng nhập</a></p>
        </div>
    </div>
</section>
<?php get_footer(); ?>
