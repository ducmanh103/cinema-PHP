<input type="hidden" name="showtime_id" value="<?php echo intval( $showtime->ShowtimeId ); ?>">
<div class="cinema-form-grid">
    <div>
        <label>Phim <span class="required">*</span></label>
        <select name="movie_id" required>
            <option value="">-- Chọn phim --</option>
            <?php foreach ( $movies as $movie_option ) : ?>
                <option value="<?php echo intval( $movie_option->MovieId ); ?>" <?php selected( (int) $showtime->MovieId, (int) $movie_option->MovieId ); ?>>
                    <?php echo esc_html( $movie_option->Title . ' (' . $movie_option->Duration . ' phút)' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Phòng chiếu <span class="required">*</span></label>
        <select name="room_id" required>
            <option value="">-- Chọn phòng --</option>
            <?php foreach ( $rooms as $room ) : ?>
                <option value="<?php echo intval( $room->RoomId ); ?>" <?php selected( (int) $showtime->RoomId, (int) $room->RoomId ); ?>>
                    <?php echo esc_html( $room->TheaterName . ' - ' . $room->RoomName . ' (' . $room->SeatCount . ' ghế)' ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Giờ chiếu <span class="required">*</span></label>
        <input type="datetime-local" name="start_time" value="<?php echo $showtime->StartTime ? esc_attr( date( 'Y-m-d\TH:i', strtotime( $showtime->StartTime ) ) ) : ''; ?>" required>
    </div>
    <div>
        <label>Giá vé (₫) <span class="required">*</span></label>
        <input type="number" name="price" min="0" step="1000" value="<?php echo esc_attr( $showtime->Price ); ?>" required>
    </div>
</div>
