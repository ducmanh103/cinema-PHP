<div class="cinema-form-grid">
    <div>
        <label>Tên phim <span class="required">*</span></label>
        <input type="text" name="title" value="<?php echo esc_attr( $movie->Title ); ?>" required>
    </div>
    <div>
        <label>Slug</label>
        <input type="text" name="slug" value="<?php echo esc_attr( $movie->Slug ); ?>" placeholder="Tự tạo nếu để trống">
    </div>
    <div>
        <label>Thời lượng (phút) <span class="required">*</span></label>
        <input type="number" name="duration" min="1" value="<?php echo esc_attr( $movie->Duration ); ?>" required>
    </div>
    <div>
        <label>Khởi chiếu</label>
        <input type="date" name="release_date" value="<?php echo esc_attr( $movie->ReleaseDate ); ?>">
    </div>
    <div>
        <label>Trạng thái</label>
        <select name="status">
            <?php foreach ( $statuses as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $movie->Status, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label>Thể loại</label>
        <select name="genre_ids[]" multiple size="4">
            <?php foreach ( $genres as $genre ) : ?>
                <option value="<?php echo intval( $genre->GenreId ); ?>" <?php selected( in_array( (int) $genre->GenreId, $movie_genre_ids, true ) ); ?>>
                    <?php echo esc_html( $genre->GenreName ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="full">
        <label>Poster URL</label>
        <input type="text" name="poster_url" value="<?php echo esc_attr( $movie->PosterUrl ); ?>" placeholder="/wp-content/uploads/movies/poster.jpg">
    </div>
    <div class="full">
        <label>Banner URL</label>
        <input type="text" name="banner_url" value="<?php echo esc_attr( $movie->BannerUrl ); ?>" placeholder="/wp-content/uploads/movies/banner.jpg">
    </div>
    <div class="full">
        <label>Mô tả</label>
        <textarea name="description" rows="5"><?php echo esc_textarea( $movie->Description ); ?></textarea>
    </div>
</div>
