/**
 * seat-picker.js — Xử lý chọn ghế, đếm giờ, và đặt vé
 */
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('seat-grid');
    if (!grid) return;

    const showtimeId   = grid.dataset.showtime;
    const priceStd     = parseInt(grid.dataset.priceStandard, 10);
    const priceVip     = parseInt(grid.dataset.priceVip, 10);
    const currentUserId= document.getElementById('current-user-id')?.value;

    const seats        = grid.querySelectorAll('.seat:not(:disabled)');
    const selectedList = document.getElementById('seats-list');
    const priceDetail  = document.getElementById('price-detail');
    const priceTotal   = document.getElementById('price-total');
    const summaryBlock = document.getElementById('price-summary');
    const methodBlock  = document.getElementById('payment-method');

    const btnHold      = document.getElementById('btn-hold');
    const btnConfirm   = document.getElementById('btn-confirm');
    const btnCancel    = document.getElementById('btn-cancel');

    const holdTimer    = document.getElementById('hold-timer');
    const timerDisplay = document.getElementById('timer-display');

    let selectedSeats  = [];
    let isHolding      = false;
    let holdInterval   = null;
    let currentExpiresAt = null;

    // ================================================================
    // 1. CHỌN GHẾ (Frontend)
    // ================================================================
    seats.forEach(seat => {
        seat.addEventListener('click', () => {
            if (isHolding) return; // Không cho đổi khi đang hold

            const id     = seat.dataset.seatId;
            const number = seat.dataset.seatNumber;
            const type   = seat.dataset.seatType;
            const price  = type === 'VIP' ? priceVip : priceStd;

            const index = selectedSeats.findIndex(s => s.id === id);

            if (index > -1) {
                // Bỏ chọn
                selectedSeats.splice(index, 1);
                seat.classList.remove('seat-selected');
            } else {
                // Chỉ cho chọn max 8 ghế
                if (selectedSeats.length >= 8) {
                    alert('Bạn chỉ được chọn tối đa 8 ghế!');
                    return;
                }
                selectedSeats.push({ id, number, type, price });
                seat.classList.add('seat-selected');
            }

            updateSummary();
        });
    });

    function updateSummary() {
        if (selectedSeats.length === 0) {
            selectedList.innerHTML = '<p class="empty-state">Chưa chọn ghế nào</p>';
            summaryBlock.style.display = 'none';
            btnHold.style.display = 'none';
            btnCancel.style.display = 'none';
            return;
        }

        // Render danh sách ghế
        selectedList.innerHTML = selectedSeats.map(s => 
            `<span class="seat-tag">${s.number} (${s.type})</span>`
        ).join('');

        // Tính tiền
        let total = 0;
        let details = [];
        let stdCount = 0, vipCount = 0;

        selectedSeats.forEach(s => {
            total += s.price;
            if (s.type === 'VIP') vipCount++; else stdCount++;
        });

        if (stdCount > 0) details.push(`${stdCount} Thường x ${formatPrice(priceStd)}`);
        if (vipCount > 0) details.push(`${vipCount} VIP x ${formatPrice(priceVip)}`);

        priceDetail.innerHTML = details.join('<br>');
        priceTotal.innerText  = formatPrice(total);

        summaryBlock.style.display = 'block';
        btnHold.style.display = 'block';
        btnHold.disabled = false;
        btnCancel.style.display = 'block';
    }

    function formatPrice(num) {
        return num.toLocaleString('vi-VN') + ' ₫';
    }

    function resetBookingState() {
        clearInterval(holdInterval);
        selectedSeats.forEach(seat => {
            const el = document.getElementById('seat-' + seat.id);
            if (el) el.classList.remove('seat-held', 'seat-selected');
        });

        selectedSeats = [];
        isHolding = false;
        currentExpiresAt = null;

        holdTimer.style.display = 'none';
        timerDisplay.innerText = '10:00';
        methodBlock.style.display = 'none';

        btnHold.innerText = 'Đặt Vé';
        btnConfirm.innerText = 'Thanh Toán';
        btnConfirm.disabled = true;
        btnConfirm.style.display = 'none';
        btnCancel.disabled = false;
        btnCancel.innerText = 'Hủy Chọn';

        updateSummary();
    }

    // Nút Hủy Chọn
    btnCancel.addEventListener('click', () => {
        if (isHolding) {
            btnCancel.disabled = true;
            btnCancel.innerText = 'Đang hủy...';
            btnConfirm.disabled = true;

            releaseSeats()
                .then(resetBookingState)
                .catch(err => {
                    console.error(err);
                    alert('Không thể hủy giữ ghế. Vui lòng thử lại.');
                    btnCancel.disabled = false;
                    btnCancel.innerText = 'Hủy Chọn';
                    btnConfirm.disabled = false;
                });
        } else {
            selectedSeats = [];
            seats.forEach(s => s.classList.remove('seat-selected'));
            updateSummary();
        }
    });

    // ================================================================
    // 2. GIỮ GHẾ (AJAX)
    // ================================================================
    btnHold.addEventListener('click', () => {
        if (selectedSeats.length === 0) return;

        btnHold.disabled = true;
        btnHold.innerText = 'Đang xử lý...';

        const seatIds = selectedSeats.map(s => s.id);

        fetch(cinemaAjax.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'cinema_hold_seats',
                nonce: cinemaAjax.nonce,
                showtime_id: showtimeId,
                seat_ids: JSON.stringify(seatIds)
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Hold thành công
                isHolding = true;
                btnHold.style.display = 'none';
                methodBlock.style.display = 'block';
                btnConfirm.style.display = 'block';
                btnConfirm.disabled = false;
                
                // Cập nhật UI ghế
                seatIds.forEach(id => {
                    const el = document.getElementById('seat-' + id);
                    el.classList.remove('seat-selected');
                    el.classList.add('seat-held');
                });

                startTimer(data.data.expires_at);
            } else {
                alert(data.data.message || 'Ghế đã bị người khác chọn. Vui lòng chọn ghế khác!');
                location.reload(); // Refresh để lấy trạng thái mới nhất
            }
        })
        .catch(err => {
            console.error(err);
            alert('Có lỗi xảy ra, vui lòng thử lại.');
            btnHold.disabled = false;
            btnHold.innerText = 'Đặt Vé';
        });
    });

    // ================================================================
    // 3. ĐẾM NGƯỢC THỜI GIAN GIỮ GHẾ
    // ================================================================
    function startTimer(expiresAtIso) {
        if (expiresAtIso) currentExpiresAt = expiresAtIso;
        if (!currentExpiresAt) return;
        holdTimer.style.display = 'block';
        const expiresAt = new Date(currentExpiresAt).getTime();

        holdInterval = setInterval(() => {
            const now = new Date().getTime();
            const diff = expiresAt - now;

            if (diff <= 0) {
                clearInterval(holdInterval);
                alert('Đã hết thời gian giữ ghế. Vui lòng chọn lại.');
                location.reload();
                return;
            }

            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            timerDisplay.innerText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
    }

    function releaseSeats() {
        if (!isHolding) return Promise.resolve();
        const seatIds = selectedSeats.map(s => s.id);
        
        // Gửi AJAX hủy hold
        return fetch(cinemaAjax.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'cinema_release_seats',
                nonce: cinemaAjax.nonce,
                showtime_id: showtimeId,
                seat_ids: JSON.stringify(seatIds)
            })
        });
    }

    // ================================================================
    // 4. XÁC NHẬN ĐẶT VÉ
    //    - Cash  → gọi book_tickets ngay
    //    - VNPay → tạo đơn rồi window.location = order_url
    //              (VNPay tự host trang chọn QR/ATM/Visa, sau khi paid
    //               redirect về handler `cinema_vnpay_return`).
    //    Pattern này y hệt project barbercut redirect sang VNPay.
    // ================================================================
    btnConfirm.addEventListener('click', () => {
        const method  = document.querySelector('input[name="payment"]:checked').value;
        const seatIds = selectedSeats.map(s => s.id);

        btnConfirm.disabled  = true;
        btnConfirm.innerText = 'Đang xử lý...';

        if (method === 'VNPay') {
            redirectToVNPay(seatIds);
        } else {
            // Cash → book luôn (không qua cổng thanh toán)
            clearInterval(holdInterval);
            doBookTickets(seatIds, method, null);
        }
    });

    function doBookTickets(seatIds, method, transactionId) {
        const payload = {
            action: 'cinema_book_tickets',
            nonce: cinemaAjax.nonce,
            showtime_id: showtimeId,
            seat_ids: JSON.stringify(seatIds),
            method: method
        };
        if (transactionId) payload.transaction_id = transactionId;

        fetch(cinemaAjax.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('modal-booking-info').innerHTML =
                    `Bạn đã đặt thành công <strong>${seatIds.length} vé</strong>.<br>` +
                    `Mã giao dịch: <strong>${data.data.transaction_id || 'Tiền mặt'}</strong>.<br>` +
                    `Vui lòng kiểm tra email hoặc lịch sử vé.`;

                document.getElementById('success-modal').style.display = 'flex';
                isHolding = false;
            } else {
                alert(data.data.message || 'Không thể hoàn tất đặt vé.');
                btnConfirm.disabled  = false;
                btnConfirm.innerText = 'Thanh Toán';
                if (method !== 'VNPay') startTimer(currentExpiresAt);
            }
        })
        .catch(err => {
            console.error(err);
            alert('Lỗi kết nối. Vui lòng thử lại.');
            btnConfirm.disabled  = false;
            btnConfirm.innerText = 'Thanh Toán';
        });
    }

    function redirectToVNPay(seatIds) {
        fetch(cinemaAjax.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'cinema_vnpay_create',
                nonce: cinemaAjax.nonce,
                showtime_id: showtimeId,
                seat_ids: JSON.stringify(seatIds)
            })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.data || !data.data.order_url) {
                alert((data.data && data.data.message) || 'Không tạo được đơn VNPay.');
                btnConfirm.disabled  = false;
                btnConfirm.innerText = 'Thanh Toán';
                return;
            }
            // Stop hold-timer trước khi rời trang để tránh popup "Đã hết thời gian"
            // hiện lên khi user quay lại bằng nút Back
            clearInterval(holdInterval);
            isHolding = false;
            window.location.href = data.data.order_url;
        })
        .catch(err => {
            console.error(err);
            alert('Lỗi kết nối VNPay. Vui lòng thử lại.');
            btnConfirm.disabled  = false;
            btnConfirm.innerText = 'Thanh Toán';
        });
    }

    // Cảnh báo khi rời trang nếu đang hold ghế (best-effort release qua sendBeacon)
    window.addEventListener('beforeunload', () => {
        if (!isHolding) return;
        const seatIds = selectedSeats.map(s => s.id);
        const data = new URLSearchParams({
            action: 'cinema_release_seats',
            nonce: cinemaAjax.nonce,
            showtime_id: showtimeId,
            seat_ids: JSON.stringify(seatIds)
        });
        navigator.sendBeacon(cinemaAjax.ajaxUrl, data);
    });
});
