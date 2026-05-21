<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$roles = $wpdb->get_results( 'SELECT * FROM cinema_roles ORDER BY RoleId ASC' );
$users = $wpdb->get_results(
    "SELECT u.*, r.RoleName
     FROM cinema_users u
     JOIN cinema_roles r ON r.RoleId = u.RoleId
     ORDER BY u.UserId DESC"
);

Cinema_Admin::admin_header( 'Quản lý người dùng', 'Danh sách tài khoản và phân quyền' );
?>

<div class="cinema-box">
    <div class="cinema-box-header">
        <h2 class="cinema-box-title">👥 Danh sách người dùng (<?php echo count( $users ); ?>)</h2>
    </div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:70px">ID</th>
                    <th>Username</th>
                    <th>Họ tên</th>
                    <th>Email</th>
                    <th style="width:100px">Vai trò</th>
                    <th style="width:110px">Trạng thái</th>
                    <th style="width:190px">Đổi vai trò</th>
                    <th style="width:110px">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $users ) : foreach ( $users as $user ) : ?>
                    <tr>
                        <td><?php echo intval( $user->UserId ); ?></td>
                        <td><strong><?php echo esc_html( $user->Username ); ?></strong></td>
                        <td><?php echo esc_html( $user->FullName ); ?></td>
                        <td><?php echo esc_html( $user->Email ); ?></td>
                        <td>
                            <?php
                            $role_class = 'Admin' === $user->RoleName ? 'danger' : ( 'Staff' === $user->RoleName ? 'warning' : 'primary' );
                            echo '<span class="cinema-label cinema-label-' . esc_attr( $role_class ) . '">' . esc_html( $user->RoleName ) . '</span>';
                            ?>
                        </td>
                        <td>
                            <?php if ( 'Active' === $user->Status ) : ?>
                                <span class="cinema-label cinema-label-success">Hoạt động</span>
                            <?php else : ?>
                                <span class="cinema-label cinema-label-default">Bị khoá</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="cinema-actions" style="margin:0">
                                <?php Cinema_Admin::nonce_fields( 'change_user_role' ); ?>
                                <input type="hidden" name="user_id" value="<?php echo intval( $user->UserId ); ?>">
                                <select name="role_id">
                                    <?php foreach ( $roles as $role ) : ?>
                                        <option value="<?php echo intval( $role->RoleId ); ?>" <?php selected( (int) $user->RoleId, (int) $role->RoleId ); ?>>
                                            <?php echo esc_html( $role->RoleName ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="button button-small">Lưu</button>
                            </form>
                        </td>
                        <td>
                            <form method="post" class="cinema-inline-form" onsubmit="return confirm('<?php echo 'Active' === $user->Status ? 'Khoá' : 'Mở khoá'; ?> người dùng <?php echo esc_js( $user->Username ); ?>?');">
                                <?php Cinema_Admin::nonce_fields( 'toggle_user_status' ); ?>
                                <input type="hidden" name="user_id" value="<?php echo intval( $user->UserId ); ?>">
                                <button type="submit" class="button button-small">
                                    <?php echo 'Active' === $user->Status ? 'Khoá' : 'Mở khoá'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="8">Chưa có người dùng.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php Cinema_Admin::admin_footer(); ?>
