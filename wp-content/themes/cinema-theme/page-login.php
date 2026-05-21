<?php
/**
 * page-login.php — Đăng nhập tài khoản Cinema
 */
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );

if ( is_user_logged_in() ) {
    wp_safe_redirect( $redirect_to ?: home_url( '/' ) );
    exit;
}

global $wpdb;
$errors = [];

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    if ( ! isset( $_POST['cinema_login_nonce'] ) || ! wp_verify_nonce( $_POST['cinema_login_nonce'], 'cinema_login' ) ) {
        $errors[] = 'Phiên đăng nhập không hợp lệ. Vui lòng thử lại.';
    } else {
        $username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
        $password = (string) ( $_POST['password'] ?? '' );
        $remember = ! empty( $_POST['remember'] );
        $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );

        $cinema_user = $wpdb->get_row( $wpdb->prepare(
            'SELECT cu.*, cr.RoleName FROM cinema_users cu JOIN cinema_roles cr ON cr.RoleId = cu.RoleId WHERE cu.Username = %s OR cu.Email = %s LIMIT 1',
            $username,
            $username
        ) );

        if ( $cinema_user ) {
            if ( 'Active' !== $cinema_user->Status ) {
                $errors[] = 'Tài khoản đã bị khoá.';
            } elseif ( $cinema_user->LockoutEnd && strtotime( $cinema_user->LockoutEnd ) > time() ) {
                $errors[] = 'Tài khoản đang bị khoá tạm thời. Vui lòng thử lại sau ' . date( 'H:i', strtotime( $cinema_user->LockoutEnd ) ) . '.';
            } elseif ( ! password_verify( $password, $cinema_user->PasswordHash ) ) {
                $attempts = (int) $cinema_user->FailedLoginAttempts + 1;
                $data = [ 'FailedLoginAttempts' => $attempts ];
                if ( $attempts >= 5 ) {
                    $data['LockoutEnd'] = date( 'Y-m-d H:i:s', strtotime( '+15 minutes' ) );
                    $errors[] = 'Bạn đã nhập sai mật khẩu 5 lần. Tài khoản bị khoá 15 phút.';
                } else {
                    $errors[] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
                }
                $wpdb->update( 'cinema_users', $data, [ 'UserId' => $cinema_user->UserId ] );
            } else {
                $wp_user_id = (int) $cinema_user->WpUserId;
                if ( ! $wp_user_id || ! get_user_by( 'id', $wp_user_id ) ) {
                    $wp_user_id = username_exists( $cinema_user->Username );
                    if ( ! $wp_user_id ) {
                        $wp_user_id = wp_create_user( $cinema_user->Username, $password, $cinema_user->Email );
                    }
                    if ( is_wp_error( $wp_user_id ) ) {
                        $errors[] = $wp_user_id->get_error_message();
                    } else {
                        wp_update_user( [
                            'ID' => $wp_user_id,
                            'display_name' => $cinema_user->FullName ?: $cinema_user->Username,
                            'role' => 'Admin' === $cinema_user->RoleName ? 'administrator' : 'subscriber',
                        ] );
                        $wpdb->update( 'cinema_users', [ 'WpUserId' => $wp_user_id ], [ 'UserId' => $cinema_user->UserId ] );
                    }
                }

                if ( empty( $errors ) ) {
                    $wpdb->update( 'cinema_users', [ 'FailedLoginAttempts' => 0, 'LockoutEnd' => null ], [ 'UserId' => $cinema_user->UserId ] );
                    wp_set_current_user( $wp_user_id );
                    wp_set_auth_cookie( $wp_user_id, $remember );
                    wp_safe_redirect( 'Admin' === $cinema_user->RoleName ? admin_url( 'admin.php?page=cinema-dashboard' ) : $redirect_to );
                    exit;
                }
            }
        } else {
            $signon = wp_signon( [
                'user_login' => $username,
                'user_password' => $password,
                'remember' => $remember,
            ], false );

            if ( is_wp_error( $signon ) ) {
                $errors[] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            } else {
                wp_safe_redirect( $redirect_to );
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập | <?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="cinema-auth-screen">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="auth-back">←</a>
    <main class="auth-wrapper">
        <section class="auth-card">
            <div class="auth-brand">
                <div class="auth-icon">🎬</div>
                <h1>CinemaHub</h1>
                <p>Mở ra kỷ nguyên điện ảnh mới</p>
            </div>
            <?php if ( $errors ) : ?>
                <div class="auth-errors"><?php foreach ( $errors as $error ) : ?><p><?php echo esc_html( $error ); ?></p><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ( isset( $_GET['registered'] ) ) : ?>
                <div class="auth-errors" style="border-left-color:#2e7d32;background:rgba(46,125,50,.16);color:#c8e6c9"><p>Đăng ký thành công. Vui lòng đăng nhập.</p></div>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'cinema_login', 'cinema_login_nonce' ); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>">
                <label>Tài khoản / Email</label>
                <input type="text" name="username" autocomplete="username" required>
                <label>Mật khẩu</label>
                <input type="password" name="password" autocomplete="current-password" required>
                <label class="auth-check"><input type="checkbox" name="remember" value="1"> Ghi nhớ tôi</label>
                <button type="submit" class="btn btn-primary btn-block btn-lg">Đăng Nhập</button>
            </form>
            <p class="auth-switch">Lần đầu đến CinemaHub? <a href="<?php echo esc_url( home_url( '/dang-ky/' ) ); ?>">Đăng ký ngay</a></p>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>
