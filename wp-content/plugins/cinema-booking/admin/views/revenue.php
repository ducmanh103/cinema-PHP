<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $wpdb;

$paid_statuses = Cinema_Admin::PAID_STATUSES;
$current_year = (int) date( 'Y' );
$selected_year = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : $current_year;
$selected_year = $selected_year ?: $current_year;

$available_years = $wpdb->get_col( "SELECT DISTINCT YEAR(PaidAt) FROM cinema_payments WHERE Status IN ({$paid_statuses}) ORDER BY YEAR(PaidAt) DESC" );
if ( ! $available_years ) {
    $available_years = [ $current_year ];
}

$total_revenue = (float) $wpdb->get_var( "SELECT COALESCE(SUM(Amount),0) FROM cinema_payments WHERE Status IN ({$paid_statuses})" );
$year_revenue = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(Amount),0) FROM cinema_payments WHERE Status IN ({$paid_statuses}) AND YEAR(PaidAt) = %d",
    $selected_year
) );
$year_tickets = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM cinema_payments WHERE Status IN ({$paid_statuses}) AND YEAR(PaidAt) = %d",
    $selected_year
) );
$total_paid_tickets = (int) $wpdb->get_var( "SELECT COUNT(*) FROM cinema_payments WHERE Status IN ({$paid_statuses})" );

$monthly_rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT MONTH(PaidAt) AS MonthNo, COALESCE(SUM(Amount),0) AS Revenue, COUNT(*) AS Tickets
     FROM cinema_payments
     WHERE Status IN ({$paid_statuses}) AND YEAR(PaidAt) = %d
     GROUP BY MONTH(PaidAt)",
    $selected_year
) );
$monthly = array_fill( 1, 12, [ 'revenue' => 0, 'tickets' => 0 ] );
foreach ( $monthly_rows as $row ) {
    $monthly[ (int) $row->MonthNo ] = [
        'revenue' => (float) $row->Revenue,
        'tickets' => (int) $row->Tickets,
    ];
}
$max_month_revenue = max( 1, max( array_column( $monthly, 'revenue' ) ) );

$top_movies = $wpdb->get_results(
    "SELECT m.Title, m.PosterUrl, COUNT(p.PaymentId) AS TicketCount, COALESCE(SUM(p.Amount),0) AS TotalRevenue
     FROM cinema_payments p
     JOIN cinema_tickets tk ON tk.TicketId = p.TicketId
     JOIN cinema_showtimes st ON st.ShowtimeId = tk.ShowtimeId
     JOIN cinema_movies m ON m.MovieId = st.MovieId
     WHERE p.Status IN ({$paid_statuses})
     GROUP BY m.MovieId
     ORDER BY TotalRevenue DESC
     LIMIT 10"
);

$payment_methods = $wpdb->get_results(
    "SELECT Method, COUNT(*) AS CountNo, COALESCE(SUM(Amount),0) AS Total
     FROM cinema_payments
     WHERE Status IN ({$paid_statuses})
     GROUP BY Method
     ORDER BY Total DESC"
);

Cinema_Admin::admin_header( 'Thống kê doanh thu', 'Báo cáo tài chính hệ thống' );
?>

<div class="cinema-box">
    <div class="cinema-box-body">
        <form method="get" class="cinema-actions" style="margin:0">
            <input type="hidden" name="page" value="cinema-revenue">
            <label for="cinema-year"><strong>📅 Xem năm:</strong></label>
            <select id="cinema-year" name="year" onchange="this.form.submit()">
                <?php foreach ( $available_years as $year ) : ?>
                    <option value="<?php echo intval( $year ); ?>" <?php selected( $selected_year, (int) $year ); ?>><?php echo intval( $year ); ?></option>
                <?php endforeach; ?>
            </select>
            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=cinema-revenue' ) ); ?>">Năm hiện tại</a>
        </form>
    </div>
</div>

<div class="cinema-stats">
    <div class="cinema-small-box cinema-bg-green">
        <div class="inner"><h3><?php echo esc_html( Cinema_Admin::format_price( $total_revenue ) ); ?></h3><p>Tổng doanh thu</p></div>
        <div class="icon">💰</div>
    </div>
    <div class="cinema-small-box cinema-bg-aqua">
        <div class="inner"><h3><?php echo esc_html( Cinema_Admin::format_price( $year_revenue ) ); ?></h3><p>Doanh thu năm <?php echo intval( $selected_year ); ?></p></div>
        <div class="icon">📊</div>
    </div>
    <div class="cinema-small-box cinema-bg-yellow">
        <div class="inner"><h3><?php echo esc_html( number_format( $year_tickets, 0, ',', '.' ) ); ?></h3><p>Vé bán năm <?php echo intval( $selected_year ); ?></p></div>
        <div class="icon">🎟</div>
    </div>
    <div class="cinema-small-box cinema-bg-red">
        <div class="inner"><h3><?php echo esc_html( number_format( $total_paid_tickets, 0, ',', '.' ) ); ?></h3><p>Giao dịch thành công</p></div>
        <div class="icon">✅</div>
    </div>
</div>

<div class="cinema-grid-2">
    <div class="cinema-box">
        <div class="cinema-box-header"><h2 class="cinema-box-title">📈 Doanh thu theo tháng - <?php echo intval( $selected_year ); ?></h2></div>
        <div class="cinema-box-body">
            <div class="cinema-chart">
                <?php for ( $month = 1; $month <= 12; $month++ ) :
                    $revenue = $monthly[ $month ]['revenue'];
                    $width = max( 2, round( $revenue / $max_month_revenue * 100 ) );
                    ?>
                    <div class="cinema-chart-row">
                        <span>Tháng <?php echo $month; ?></span>
                        <div class="cinema-chart-bar"><span style="width:<?php echo esc_attr( $width ); ?>%"></span></div>
                        <strong><?php echo esc_html( Cinema_Admin::format_price( $revenue ) ); ?></strong>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <div class="cinema-box">
        <div class="cinema-box-header"><h2 class="cinema-box-title">💳 Phương thức thanh toán</h2></div>
        <div class="cinema-box-body">
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Phương thức</th><th>Số GD</th><th>Tổng thu</th></tr></thead>
                <tbody>
                    <?php if ( $payment_methods ) : foreach ( $payment_methods as $method ) : ?>
                        <tr>
                            <td><?php echo esc_html( $method->Method ); ?></td>
                            <td><?php echo intval( $method->CountNo ); ?></td>
                            <td><strong><?php echo esc_html( Cinema_Admin::format_price( $method->Total ) ); ?></strong></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="3">Chưa có dữ liệu.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="cinema-box">
    <div class="cinema-box-header"><h2 class="cinema-box-title">🏆 Top phim doanh thu cao nhất</h2></div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th style="width:60px">Poster</th>
                    <th>Tên phim</th>
                    <th style="width:120px">Số vé</th>
                    <th style="width:160px">Doanh thu</th>
                    <th style="width:120px">% Tổng</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $top_movies ) : foreach ( $top_movies as $index => $movie ) :
                    $percent = $total_revenue > 0 ? ( (float) $movie->TotalRevenue / $total_revenue * 100 ) : 0;
                    ?>
                    <tr>
                        <td><span class="cinema-label cinema-label-primary"><?php echo $index + 1; ?></span></td>
                        <td>
                            <?php if ( $movie->PosterUrl ) : ?>
                                <img class="cinema-table-thumb" src="<?php echo esc_url( Cinema_Admin::asset_url( $movie->PosterUrl ) ); ?>" alt="<?php echo esc_attr( $movie->Title ); ?>">
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo esc_html( $movie->Title ); ?></strong></td>
                        <td><?php echo intval( $movie->TicketCount ); ?></td>
                        <td><strong><?php echo esc_html( Cinema_Admin::format_price( $movie->TotalRevenue ) ); ?></strong></td>
                        <td><?php echo esc_html( number_format( $percent, 1, ',', '.' ) ); ?>%</td>
                    </tr>
                <?php endforeach; else : ?>
                    <tr><td colspan="6">Chưa có dữ liệu doanh thu.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="cinema-box">
    <div class="cinema-box-header"><h2 class="cinema-box-title">🎟 Số vé bán theo tháng - <?php echo intval( $selected_year ); ?></h2></div>
    <div class="cinema-box-body">
        <table class="wp-list-table widefat fixed striped">
            <thead><tr><th>Tháng</th><th>Số vé</th><th>Doanh thu</th></tr></thead>
            <tbody>
                <?php for ( $month = 1; $month <= 12; $month++ ) : ?>
                    <tr>
                        <td>Tháng <?php echo $month; ?></td>
                        <td><?php echo intval( $monthly[ $month ]['tickets'] ); ?></td>
                        <td><?php echo esc_html( Cinema_Admin::format_price( $monthly[ $month ]['revenue'] ) ); ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>

<?php Cinema_Admin::admin_footer(); ?>
