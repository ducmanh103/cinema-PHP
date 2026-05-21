<?php
/**
 * Cinema_VNPay — Tích hợp VNPay Sandbox (mirror barbercut LOPAS_VNPay_Gateway).
 *
 * Tài liệu: https://sandbox.vnpayment.vn/apis/docs/
 *
 * Flow:
 *   1. FE gọi `cinema_vnpay_create` → BE build vnp_* params + ký HMAC-SHA512(vnp_HashSecret)
 *      trên query string đã sort theo key, trả về URL `vpcpay.html?...&vnp_SecureHash=...`
 *   2. FE redirect user sang URL đó. VNPay sandbox tự host trang QR + chọn ngân hàng + countdown.
 *   3. Sau khi user thanh toán, VNPay redirect về `vnp_ReturnUrl` kèm vnp_* + vnp_SecureHash
 *   4. BE verify hash, rồi book vé từ data đã lưu trong transient (key = vnp_TxnRef).
 *
 * Sandbox credentials (chia sẻ public bởi VNPay docs):
 *   - tmn_code:    5652YMTY
 *   - hash_secret: 2AT2HZOW2D58PYMT5BJ6B24JHK98OGEN
 *   - pay_url:     https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
 *
 * Trên production: define CINEMA_VNPAY_TMN_CODE / HASH_SECRET / PAY_URL trong wp-config.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_VNPay {

    // ----- Sandbox defaults (override trên production qua wp-config.php) -----
    const SANDBOX_TMN_CODE    = '5652YMTY';
    const SANDBOX_HASH_SECRET = '2AT2HZOW2D58PYMT5BJ6B24JHK98OGEN';
    const SANDBOX_PAY_URL     = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    const VERSION             = '2.1.0';
    const CURRENCY            = 'VND';
    const LOCALE              = 'vn';

    // ----- Runtime config (constant > sandbox fallback) -----
    public static function tmn_code() {
        return defined( 'CINEMA_VNPAY_TMN_CODE' ) ? CINEMA_VNPAY_TMN_CODE : self::SANDBOX_TMN_CODE;
    }
    public static function hash_secret() {
        return defined( 'CINEMA_VNPAY_HASH_SECRET' ) ? CINEMA_VNPAY_HASH_SECRET : self::SANDBOX_HASH_SECRET;
    }
    public static function pay_url() {
        return defined( 'CINEMA_VNPAY_PAY_URL' ) ? CINEMA_VNPAY_PAY_URL : self::SANDBOX_PAY_URL;
    }
    public static function return_url() {
        // Cho phép override để dùng IPN khác (vd: với reverse proxy)
        if ( defined( 'CINEMA_VNPAY_RETURN_URL' ) ) return CINEMA_VNPAY_RETURN_URL;
        return admin_url( 'admin-ajax.php?action=cinema_vnpay_return' );
    }

    public static function is_enabled() {
        return self::tmn_code() && self::hash_secret();
    }

    /**
     * Tạo payment URL VNPay → trả về { order_url, order_code, amount }
     *
     * @param int    $amount      Tổng tiền (VND, integer ≥ 1)
     * @param string $order_code  Mã giao dịch unique (vd: CIN20260520153012345)
     * @param string $order_info  Mô tả ngắn (ASCII, không dấu — VNPay khuyến nghị)
     * @return array|WP_Error
     */
    public static function create_payment_url( $amount, $order_code, $order_info ) {
        $amount = (int) $amount;
        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', 'Số tiền không hợp lệ.' );
        }
        if ( ! self::is_enabled() ) {
            return new WP_Error( 'vnpay_disabled', 'VNPay chưa được cấu hình.' );
        }

        $vnp_data = [
            'vnp_Version'    => self::VERSION,
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => self::tmn_code(),
            // VNPay yêu cầu amount * 100 (đơn vị: x VND, không dấu phẩy)
            'vnp_Amount'     => $amount * 100,
            'vnp_CurrCode'   => self::CURRENCY,
            'vnp_TxnRef'     => $order_code,
            'vnp_OrderInfo'  => $order_info,
            'vnp_OrderType'  => 'billpayment',
            'vnp_Locale'     => self::LOCALE,
            'vnp_ReturnUrl'  => self::return_url(),
            'vnp_IpAddr'     => self::get_client_ip(),
            'vnp_CreateDate' => date( 'YmdHis' ),
        ];

        // Sort theo key
        ksort( $vnp_data );

        // Build hashData (RFC3986 — `&` chỉ thêm từ phần tử thứ 2)
        $hash_data = '';
        $query     = '';
        $i = 0;
        foreach ( $vnp_data as $key => $value ) {
            if ( 1 === $i ) {
                $hash_data .= '&' . urlencode( $key ) . '=' . urlencode( $value );
            } else {
                $hash_data .= urlencode( $key ) . '=' . urlencode( $value );
                $i = 1;
            }
            $query .= urlencode( $key ) . '=' . urlencode( $value ) . '&';
        }

        $secure_hash = hash_hmac( 'sha512', $hash_data, self::hash_secret() );
        $url         = self::pay_url() . '?' . $query . 'vnp_SecureHash=' . $secure_hash;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Cinema VNPay] Request URL: ' . $url );
        }

        return [
            'order_url'  => $url,
            'order_code' => $order_code,
            'amount'     => $amount,
        ];
    }

    /**
     * Verify chữ ký từ VNPay (return URL hoặc IPN).
     *
     * @param array $response GET/POST từ VNPay
     * @return bool
     */
    public static function verify_response( array $response ) {
        if ( empty( $response['vnp_SecureHash'] ) ) {
            return false;
        }

        $vnp_secure_hash = $response['vnp_SecureHash'];
        unset( $response['vnp_SecureHash'], $response['vnp_SecureHashType'] );

        // Loại bỏ key non-vnp_
        $response = array_filter( $response, function ( $k ) {
            return is_string( $k ) && 0 === strpos( $k, 'vnp_' );
        }, ARRAY_FILTER_USE_KEY );

        ksort( $response );

        $hash_data = '';
        $i = 0;
        foreach ( $response as $key => $value ) {
            if ( 1 === $i ) {
                $hash_data .= '&' . urlencode( $key ) . '=' . urlencode( $value );
            } else {
                $hash_data .= urlencode( $key ) . '=' . urlencode( $value );
                $i = 1;
            }
        }

        $secure_hash = hash_hmac( 'sha512', $hash_data, self::hash_secret() );

        if ( hash_equals( $secure_hash, $vnp_secure_hash ) ) {
            return true;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Cinema VNPay] Hash mismatch — calc=' . $secure_hash . ' got=' . $vnp_secure_hash );
        }
        return false;
    }

    /**
     * Parse response → mảng standard.
     */
    public static function parse_response( array $response ) {
        return [
            'transaction_code'   => $response['vnp_TransactionNo']     ?? '',
            'order_code'         => $response['vnp_TxnRef']            ?? '',
            'amount'             => isset( $response['vnp_Amount'] ) ? intval( $response['vnp_Amount'] ) / 100 : 0,
            'response_code'      => $response['vnp_ResponseCode']      ?? '',
            'transaction_status' => $response['vnp_TransactionStatus'] ?? '',
            'bank_code'          => $response['vnp_BankCode']          ?? '',
            'bank_tran_no'       => $response['vnp_BankTranNo']        ?? '',
            'pay_date'           => $response['vnp_PayDate']           ?? '',
            'raw_response'       => $response,
        ];
    }

    /**
     * VNPay code 00 = thành công.
     */
    public static function is_payment_success( $response_code ) {
        return '00' === (string) $response_code;
    }

    /**
     * Sinh mã giao dịch unique (≤ 100 ký tự, ASCII).
     */
    public static function generate_order_code() {
        return 'CIN' . date( 'YmdHis' ) . wp_rand( 1000, 9999 );
    }

    /**
     * Loại bỏ dấu tiếng Việt cho vnp_OrderInfo (VNPay khuyến nghị ASCII).
     */
    public static function ascii( $str ) {
        $str = remove_accents( $str );
        // Chỉ giữ chữ cái, số, khoảng trắng, dấu phẩy, hai chấm
        return trim( preg_replace( '/[^A-Za-z0-9 ,:_-]/', ' ', $str ) );
    }

    private static function get_client_ip() {
        $candidates = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
        foreach ( $candidates as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', $_SERVER[ $key ] )[0];
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }
}
