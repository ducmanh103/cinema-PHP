<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cinema_Seat {
    /**
     * Giữ ghế tạm thời (10 phút)
     */
    public static function hold_seats( $showtime_id, $seat_ids, $cinema_user_id ) {
        global $wpdb;

        $showtime_id = absint( $showtime_id );
        $cinema_user_id = absint( $cinema_user_id );
        $seat_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $seat_ids ) ) ) );

        if ( ! $showtime_id || ! $cinema_user_id || empty( $seat_ids ) ) {
            return new WP_Error( 'invalid_hold_data', 'Dữ liệu giữ ghế không hợp lệ.' );
        }

        $room_id = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT RoomId FROM cinema_showtimes WHERE ShowtimeId = %d AND StartTime > NOW()',
            $showtime_id
        ) );
        if ( ! $room_id ) {
            return new WP_Error( 'invalid_showtime', 'Suất chiếu không hợp lệ.' );
        }

        $placeholders = implode( ',', array_fill( 0, count( $seat_ids ), '%d' ) );
        $valid_args = array_merge( [ $room_id ], $seat_ids );
        $valid_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM cinema_seats WHERE RoomId = %d AND SeatId IN ($placeholders)",
            ...$valid_args
        ) );

        if ( $valid_count !== count( $seat_ids ) ) {
            return new WP_Error( 'seat_room_mismatch', 'Một hoặc nhiều ghế không thuộc phòng chiếu này.' );
        }

        // 1. Kiểm tra xem các ghế này đã bị người khác đặt hoặc hold chưa
        $args = array_merge( [ $showtime_id, $showtime_id ], $seat_ids );
        
        $query = "SELECT s.SeatId 
                  FROM cinema_seats s
                  LEFT JOIN cinema_tickets t ON t.SeatId = s.SeatId AND t.ShowtimeId = %d AND t.Status = 'Booked'
                  LEFT JOIN cinema_seat_holds sh ON sh.SeatId = s.SeatId AND sh.ShowtimeId = %d AND sh.Status = 'Active' AND sh.ExpiresAt > NOW()
                  WHERE s.SeatId IN ($placeholders) AND (t.TicketId IS NOT NULL OR sh.SeatHoldId IS NOT NULL)";
                  
        $unavailable = $wpdb->get_col( $wpdb->prepare( $query, ...$args ) );

        if ( ! empty( $unavailable ) ) {
            return new WP_Error( 'seat_unavailable', 'Một hoặc nhiều ghế đã bị đặt hoặc đang được giữ.' );
        }

        // 2. Bắt đầu Transaction
        $wpdb->query( "START TRANSACTION" );

        // Hủy các hold cũ của user này cho suất chiếu này (nếu có) để tránh spam
        $wpdb->update( 
            'cinema_seat_holds', 
            [ 'Status' => 'Released', 'ReleasedAt' => current_time('mysql') ], 
            [ 'ShowtimeId' => $showtime_id, 'UserId' => $cinema_user_id, 'Status' => 'Active' ]
        );

        // 3. Thêm Hold mới (10 phút), dùng giờ MySQL để khớp với điều kiện ExpiresAt > NOW().
        $expires = $wpdb->get_var( "SELECT DATE_ADD(NOW(), INTERVAL 10 MINUTE)" );
        
        foreach ( $seat_ids as $seat_id ) {
            $inserted = $wpdb->insert( 'cinema_seat_holds', [
                'ShowtimeId' => $showtime_id,
                'SeatId'     => $seat_id,
                'UserId'     => $cinema_user_id,
                'ExpiresAt'  => $expires,
                'Status'     => 'Active'
            ] );

            if ( ! $inserted ) {
                $wpdb->query( "ROLLBACK" );
                return new WP_Error( 'db_error', 'Lỗi khi giữ ghế.' );
            }
        }

        $wpdb->query( "COMMIT" );
        return $expires; // Trả về thời gian hết hạn
    }

    /**
     * Hủy giữ ghế (khi user hủy hoặc timeout)
     */
    public static function release_seats( $showtime_id, $seat_ids, $cinema_user_id ) {
        global $wpdb;
        $showtime_id = absint( $showtime_id );
        $cinema_user_id = absint( $cinema_user_id );
        $seat_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $seat_ids ) ) ) );
        if ( ! $showtime_id || ! $cinema_user_id || empty( $seat_ids ) ) {
            return true;
        }

        $placeholders = implode( ',', array_fill( 0, count( $seat_ids ), '%d' ) );
        
        $sql = "UPDATE cinema_seat_holds 
                SET Status = 'Released', ReleasedAt = NOW() 
                WHERE ShowtimeId = %d AND UserId = %d AND Status = 'Active' AND SeatId IN ($placeholders)";
                
        $args = array_merge( [ $showtime_id, $cinema_user_id ], $seat_ids );
        $wpdb->query( $wpdb->prepare( $sql, ...$args ) );
        return true;
    }
}
