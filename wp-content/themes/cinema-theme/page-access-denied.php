<?php get_header(); ?>
<section class="section auth-inline-page">
    <div class="container">
        <div class="empty-panel">
            <h1>Truy cập bị từ chối</h1>
            <p>Bạn không có quyền truy cập trang này.</p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-outline">Về trang chủ</a>
        </div>
    </div>
</section>
<?php get_footer(); ?>
