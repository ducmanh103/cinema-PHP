<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_Booking {
    /**
     * Thực hiện đặt vé (chuyển từ Hold -> Booked)
     */
    public static function book_tickets( $showtime_id, $seat_ids, $cinema_user_id, $method = 'VNPay', $transaction_id = null ) {
        global $wpdb;

        $showtime_id = absint( $showtime_id );
        $cinema_user_id = absint( $cinema_user_id );
        $seat_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $seat_ids ) ) ) );
        $method = in_array( $method, [ 'Cash', 'VNPay' ], true ) ? $method : 'VNPay';

        if ( ! $showtime_id || ! $cinema_user_id || empty( $seat_ids ) ) {
            return new WP_Error( 'invalid_booking_data', 'Dữ liệu đặt vé không hợp lệ.' );
        }

        $role_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT r.RoleName FROM cinema_users u JOIN cinema_roles r ON r.RoleId = u.RoleId WHERE u.UserId = %d",
            $cinema_user_id
        ) );
        if ( 'Cash' === $method && ! in_array( $role_name, [ 'Admin', 'Staff' ], true ) ) {
            return new WP_Error( 'cash_not_allowed', 'Khách hàng mua vé online bắt buộc thanh toán qua VNPay.' );
        }

        // Bắt đầu Transaction
        $wpdb->query( "START TRANSACTION" );

        $showtime = $wpdb->get_row( $wpdb->prepare( "SELECT Price, RoomId FROM cinema_showtimes WHERE ShowtimeId = %d AND StartTime > NOW()", $showtime_id ) );
        if ( ! $showtime ) {
            $wpdb->query( "ROLLBACK" );
            return new WP_Error( 'invalid_showtime', 'Suất chiếu không hợp lệ.' );
        }

        $placeholders = implode( ',', array_fill( 0, count( $seat_ids ), '%d' ) );
        $seat_room_args = array_merge( [ (int) $showtime->RoomId ], $seat_ids );
        $valid_seat_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM cinema_seats
             WHERE RoomId = %d
               AND SeatId IN ($placeholders)",
            ...$seat_room_args
        ) );

        if ( $valid_seat_count !== count( $seat_ids ) ) {
            $wpdb->query( "ROLLBACK" );
            return new WP_Error( 'seat_room_mismatch', 'Một hoặc nhiều ghế không thuộc phòng chiếu này.' );
        }

        $held_args = array_merge( [ $showtime_id, $cinema_user_id ], $seat_ids );
        $held_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT SeatId)
             FROM cinema_seat_holds
             WHERE ShowtimeId = %d
               AND UserId = %d
               AND Status = 'Active'
               AND ExpiresAt > NOW()
               AND SeatId IN ($placeholders)",
            ...$held_args
        ) );

        if ( $held_count !== count( $seat_ids ) ) {
            $wpdb->query( "ROLLBACK" );
            return new WP_Error( 'seat_hold_required', 'Vui lòng giữ ghế trước khi thanh toán.' );
        }

        $booked_args = array_merge( [ $showtime_id ], $seat_ids );
        $booked_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
             FROM cinema_tickets
             WHERE ShowtimeId = %d
               AND Status = 'Booked'
               AND SeatId IN ($placeholders)",
            ...$booked_args
        ) );

        if ( $booked_count > 0 ) {
            $wpdb->query( "ROLLBACK" );
            return new WP_Error( 'seat_already_booked', 'Một hoặc nhiều ghế đã được đặt.' );
        }

        if ( empty( $transaction_id ) ) {
            $prefix = ( 'VNPay' === $method ) ? 'VN' : ( 'Cash' === $method ? 'CS' : 'TX' );
            $transaction_id = $prefix . date('YmdHis') . rand(100, 999);
        }
        $total_amount = 0;

        foreach ( $seat_ids as $seat_id ) {
            // Lấy loại ghế để tính giá
            $seat = $wpdb->get_row( $wpdb->prepare("SELECT SeatType FROM cinema_seats WHERE SeatId = %d", $seat_id) );
            $price = $showtime->Price;
            if ( $seat && $seat->SeatType === 'VIP' ) {
                $price = $price + 10000;
            }
            $total_amount += $price;

            // 1. Tạo Ticket
            $ticket_inserted = $wpdb->insert( 'cinema_tickets', [
                'ShowtimeId'  => $showtime_id,
                'SeatId'      => $seat_id,
                'UserId'      => $cinema_user_id,
                'Status'      => 'Booked',
                'BookingTime' => current_time('mysql')
            ] );

            if ( ! $ticket_inserted ) {
                $wpdb->query( "ROLLBACK" );
                return new WP_Error( 'db_error', 'Lỗi tạo vé.' );
            }

            $ticket_id = $wpdb->insert_id;

            // 2. Tạo Payment
            $payment_inserted = $wpdb->insert( 'cinema_payments', [
                'TicketId'      => $ticket_id,
                'Amount'        => $price,
                'Method'        => $method,
                'Status'        => 'Completed',
                'TransactionId' => $transaction_id,
                'PaidAt'        => current_time('mysql')
            ] );

            if ( ! $payment_inserted ) {
                $wpdb->query( "ROLLBACK" );
                return new WP_Error( 'db_error', 'Lỗi tạo thanh toán.' );
            }
        }

        // 3. Giải phóng các ghế đang hold của user này
        Cinema_Seat::release_seats( $showtime_id, $seat_ids, $cinema_user_id );

        $wpdb->query( "COMMIT" );
        return $transaction_id;
    }

    /**
     * Hủy vé
     */
    public static function cancel_ticket( $ticket_id, $cinema_user_id ) {
        global $wpdb;

        $ticket = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM cinema_tickets WHERE TicketId = %d AND UserId = %d", 
            $ticket_id, $cinema_user_id 
        ) );

        if ( ! $ticket ) return false;
        if ( $ticket->Status !== 'Booked' ) return false;

        $wpdb->query( "START TRANSACTION" );

        $wpdb->update( 'cinema_tickets', ['Status' => 'Cancelled'], ['TicketId' => $ticket_id] );
        $wpdb->update( 'cinema_payments', ['Status' => 'Refunded'], ['TicketId' => $ticket_id] );

        $wpdb->query( "COMMIT" );
        return true;
    }
}
