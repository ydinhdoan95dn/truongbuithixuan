<?php
/**
 * ==============================================
 * HELPER FUNCTIONS CHO HỆ THỐNG TUẦN HỌC
 * ==============================================
 */

/**
 * Lấy tuần hiện tại
 */
function getCurrentWeek() {
    $conn = getDBConnection();
    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT th.*, hk.ten_hoc_ky, hk.nam_hoc
        FROM tuan_hoc th
        LEFT JOIN hoc_ky hk ON th.hoc_ky_id = hk.id
        WHERE ? BETWEEN th.ngay_bat_dau AND th.ngay_ket_thuc
        LIMIT 1
    ");
    $stmt->execute(array($today));
    return $stmt->fetch();
}

/**
 * Lấy tuần trước (đã có kết quả)
 */
function getLastWeek() {
    $conn = getDBConnection();
    $today = date('Y-m-d');

    $stmt = $conn->prepare("
        SELECT th.*, hk.ten_hoc_ky, hk.nam_hoc
        FROM tuan_hoc th
        LEFT JOIN hoc_ky hk ON th.hoc_ky_id = hk.id
        WHERE th.ngay_ket_thuc < ?
        ORDER BY th.ngay_ket_thuc DESC
        LIMIT 1
    ");
    $stmt->execute(array($today));
    return $stmt->fetch();
}

/**
 * Lấy tuần theo ID
 */
function getWeekById($weekId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT th.*, hk.ten_hoc_ky, hk.nam_hoc
        FROM tuan_hoc th
        LEFT JOIN hoc_ky hk ON th.hoc_ky_id = hk.id
        WHERE th.id = ?
    ");
    $stmt->execute(array($weekId));
    return $stmt->fetch();
}

/**
 * Lấy học kỳ hiện tại
 */
function getCurrentSemester() {
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT * FROM hoc_ky WHERE trang_thai = 1 LIMIT 1");
    return $stmt->fetch();
}

/**
 * Kiểm tra đề thi có mở không (theo thời gian)
 * CHỈ áp dụng cho đề thi CHÍNH THỨC (is_chinh_thuc = 1)
 * Đề thi luyện tập LUÔN MỞ
 */
function isExamOpen($exam) {
    // Đề thi luyện tập (không phải chính thức) -> LUÔN MỞ
    $isChinhThuc = isset($exam['is_chinh_thuc']) ? (int)$exam['is_chinh_thuc'] : 0;
    if ($isChinhThuc != 1) {
        return true; // Luyện tập luôn mở
    }

    // === CHỈ KIỂM TRA CHO ĐỀ THI CHÍNH THỨC ===

    // Lấy chế độ mở từ đề thi (ưu tiên từ exam-schedule)
    $cheDoMo = isset($exam['che_do_mo']) ? $exam['che_do_mo'] : '';

    // Nếu chế độ "Mở ngay" hoặc "luôn mở" -> luôn return true
    if ($cheDoMo == 'mo_ngay' || $cheDoMo == 'luon_mo') {
        return true;
    }

    // Chế độ "Theo lịch" - kiểm tra ngày trong tuần
    $dayOfWeek = strtolower(date('D')); // mon, tue, wed, thu, fri, sat, sun
    $dayMap = array(
        'mon' => 't2',
        'tue' => 't3',
        'wed' => 't4',
        'thu' => 't5',
        'fri' => 't6',
        'sat' => 't7',
        'sun' => 'cn'
    );

    $currentDay = $dayMap[$dayOfWeek];

    // Lấy ngày mở thi từ exam hoặc từ cài đặt hệ thống
    $ngayMoThi = isset($exam['ngay_mo_thi']) && !empty($exam['ngay_mo_thi']) ? $exam['ngay_mo_thi'] : 't7,cn';
    $allowedDays = explode(',', $ngayMoThi);
    // Trim spaces
    $allowedDays = array_map('trim', $allowedDays);

    if (!in_array($currentDay, $allowedDays)) {
        return false;
    }

    // Kiểm tra giờ (nếu có cài đặt)
    $currentTime = date('H:i:s');
    $startTime = isset($exam['gio_bat_dau']) ? $exam['gio_bat_dau'] : null;
    $endTime = isset($exam['gio_ket_thuc']) ? $exam['gio_ket_thuc'] : null;

    if ($startTime && $endTime) {
        if ($currentTime < $startTime || $currentTime > $endTime) {
            return false;
        }
    }

    return true;
}

/**
 * Lấy kết quả tuần của học sinh
 */
function getStudentWeekResult($studentId, $weekId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM ket_qua_tuan
        WHERE hoc_sinh_id = ? AND tuan_id = ?
    ");
    $stmt->execute(array($studentId, $weekId));
    return $stmt->fetch();
}

/**
 * Cập nhật kết quả tuần (lưu điểm cao nhất)
 */
function updateWeekResult($studentId, $weekId, $examId, $score, $bailamId, $timeSpent) {
    $conn = getDBConnection();

    // Kiểm tra đã có kết quả chưa
    $existing = getStudentWeekResult($studentId, $weekId);

    if ($existing) {
        // Tăng số lần thi
        $soLanThi = $existing['so_lan_thi'] + 1;

        // Chỉ cập nhật nếu điểm mới cao hơn
        if ($score > $existing['diem_cao_nhat']) {
            $stmt = $conn->prepare("
                UPDATE ket_qua_tuan
                SET diem_cao_nhat = ?, so_lan_thi = ?, bai_lam_id = ?, thoi_gian_nhanh_nhat = ?
                WHERE hoc_sinh_id = ? AND tuan_id = ?
            ");
            $stmt->execute(array($score, $soLanThi, $bailamId, $timeSpent, $studentId, $weekId));
        } else {
            // Chỉ tăng số lần thi
            $stmt = $conn->prepare("
                UPDATE ket_qua_tuan SET so_lan_thi = ? WHERE hoc_sinh_id = ? AND tuan_id = ?
            ");
            $stmt->execute(array($soLanThi, $studentId, $weekId));
        }

        return $soLanThi;
    } else {
        // Tạo mới
        $stmt = $conn->prepare("
            INSERT INTO ket_qua_tuan (hoc_sinh_id, tuan_id, de_thi_id, diem_cao_nhat, so_lan_thi, bai_lam_id, thoi_gian_nhanh_nhat)
            VALUES (?, ?, ?, ?, 1, ?, ?)
        ");
        $stmt->execute(array($studentId, $weekId, $examId, $score, $bailamId, $timeSpent));

        return 1;
    }
}

/**
 * Lấy xếp hạng tuần của lớp
 */
function getWeekRankingByClass($weekId, $classId, $limit = 50) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT
            kt.*,
            hs.ho_ten,
            hs.ma_hs,
            hs.gioi_tinh,
            lh.ten_lop,
            @rank := @rank + 1 as thu_hang
        FROM ket_qua_tuan kt
        JOIN hoc_sinh hs ON kt.hoc_sinh_id = hs.id
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        CROSS JOIN (SELECT @rank := 0) r
        WHERE kt.tuan_id = ? AND hs.lop_id = ?
        ORDER BY kt.diem_cao_nhat DESC, kt.thoi_gian_nhanh_nhat ASC
        LIMIT ?
    ");
    $stmt->execute(array($weekId, $classId, $limit));
    return $stmt->fetchAll();
}

/**
 * Lấy xếp hạng tuần của khối
 */
function getWeekRankingByGrade($weekId, $grade, $limit = 50) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT
            kt.*,
            hs.ho_ten,
            hs.ma_hs,
            hs.gioi_tinh,
            lh.ten_lop,
            lh.khoi,
            @rank := @rank + 1 as thu_hang
        FROM ket_qua_tuan kt
        JOIN hoc_sinh hs ON kt.hoc_sinh_id = hs.id
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        CROSS JOIN (SELECT @rank := 0) r
        WHERE kt.tuan_id = ? AND lh.khoi = ?
        ORDER BY kt.diem_cao_nhat DESC, kt.thoi_gian_nhanh_nhat ASC
        LIMIT ?
    ");
    $stmt->execute(array($weekId, $grade, $limit));
    return $stmt->fetchAll();
}

/**
 * Lấy xếp hạng tháng
 */
function getMonthRanking($month, $year, $classId = null, $limit = 50) {
    $conn = getDBConnection();

    // Lấy các tuần trong tháng
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    $classFilter = $classId ? "AND hs.lop_id = " . intval($classId) : "";

    $stmt = $conn->query("
        SELECT
            hs.id as hoc_sinh_id,
            hs.ho_ten,
            hs.ma_hs,
            hs.gioi_tinh,
            lh.ten_lop,
            lh.khoi,
            SUM(kt.diem_cao_nhat) as tong_diem,
            COUNT(kt.id) as so_tuan_thi,
            AVG(kt.diem_cao_nhat) as diem_trung_binh,
            @rank := @rank + 1 as thu_hang
        FROM hoc_sinh hs
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        LEFT JOIN ket_qua_tuan kt ON hs.id = kt.hoc_sinh_id
        LEFT JOIN tuan_hoc th ON kt.tuan_id = th.id
        CROSS JOIN (SELECT @rank := 0) r
        WHERE th.ngay_bat_dau >= '{$startDate}'
            AND th.ngay_ket_thuc <= '{$endDate}'
            {$classFilter}
        GROUP BY hs.id
        ORDER BY tong_diem DESC, diem_trung_binh DESC
        LIMIT {$limit}
    ");
    return $stmt->fetchAll();
}

/**
 * Lấy xếp hạng học kỳ
 */
function getSemesterRanking($semesterId, $classId = null, $limit = 50) {
    $conn = getDBConnection();

    $classFilter = $classId ? "AND hs.lop_id = " . intval($classId) : "";

    $stmt = $conn->query("
        SELECT
            hs.id as hoc_sinh_id,
            hs.ho_ten,
            hs.ma_hs,
            hs.gioi_tinh,
            lh.ten_lop,
            lh.khoi,
            SUM(kt.diem_cao_nhat) as tong_diem,
            COUNT(kt.id) as so_tuan_thi,
            AVG(kt.diem_cao_nhat) as diem_trung_binh,
            @rank := @rank + 1 as thu_hang
        FROM hoc_sinh hs
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        LEFT JOIN ket_qua_tuan kt ON hs.id = kt.hoc_sinh_id
        LEFT JOIN tuan_hoc th ON kt.tuan_id = th.id
        CROSS JOIN (SELECT @rank := 0) r
        WHERE th.hoc_ky_id = " . intval($semesterId) . "
            {$classFilter}
        GROUP BY hs.id
        ORDER BY tong_diem DESC, diem_trung_binh DESC
        LIMIT {$limit}
    ");
    return $stmt->fetchAll();
}

/**
 * Lấy danh sách tuần của học kỳ
 */
function getWeeksBySemester($semesterId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT * FROM tuan_hoc
        WHERE hoc_ky_id = ?
        ORDER BY so_tuan ASC
    ");
    $stmt->execute(array($semesterId));
    return $stmt->fetchAll();
}

/**
 * Lấy đề thi của tuần
 */
function getExamsByWeek($weekId, $classId = null) {
    $conn = getDBConnection();

    $classFilter = $classId ? "AND dt.lop_id = " . intval($classId) : "";

    $stmt = $conn->query("
        SELECT dt.*, mh.ten_mon, lh.ten_lop,
               (SELECT COUNT(*) FROM cau_hoi ch WHERE ch.de_thi_id = dt.id) as so_cau_hoi
        FROM de_thi dt
        JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
        JOIN lop_hoc lh ON dt.lop_id = lh.id
        WHERE dt.tuan_id = " . intval($weekId) . " {$classFilter}
        ORDER BY dt.lop_id, dt.id
    ");
    return $stmt->fetchAll();
}

/**
 * Tính số ngày còn lại trong tuần
 */
function getDaysRemainingInWeek($week) {
    if (!$week) return 0;

    $endDate = new DateTime($week['ngay_ket_thuc']);
    $today = new DateTime();

    if ($today > $endDate) return 0;

    $diff = $today->diff($endDate);
    return $diff->days;
}

/**
 * Format ngày tuần
 */
function formatWeekDate($week) {
    if (!$week) return '';

    $start = date('d/m', strtotime($week['ngay_bat_dau']));
    $end = date('d/m/Y', strtotime($week['ngay_ket_thuc']));

    return "{$start} - {$end}";
}

/**
 * Lấy tên tháng tiếng Việt
 */
function getVietnameseMonth($month) {
    $months = array(
        1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3',
        4 => 'Tháng 4', 5 => 'Tháng 5', 6 => 'Tháng 6',
        7 => 'Tháng 7', 8 => 'Tháng 8', 9 => 'Tháng 9',
        10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
    );
    return isset($months[$month]) ? $months[$month] : '';
}

/**
 * Kiểm tra tuần có phải là tuần hiện tại không
 */
function isCurrentWeek($week) {
    if (!$week) return false;

    $today = date('Y-m-d');
    return ($today >= $week['ngay_bat_dau'] && $today <= $week['ngay_ket_thuc']);
}

/**
 * Lấy thống kê tổng quan tuần
 */
function getWeekStatistics($weekId, $classId = null) {
    $conn = getDBConnection();

    $classFilter = $classId ? "AND hs.lop_id = " . intval($classId) : "";

    $stmt = $conn->query("
        SELECT
            COUNT(DISTINCT kt.hoc_sinh_id) as so_hs_da_thi,
            AVG(kt.diem_cao_nhat) as diem_trung_binh,
            MAX(kt.diem_cao_nhat) as diem_cao_nhat,
            MIN(kt.diem_cao_nhat) as diem_thap_nhat,
            SUM(kt.so_lan_thi) as tong_luot_thi
        FROM ket_qua_tuan kt
        JOIN hoc_sinh hs ON kt.hoc_sinh_id = hs.id
        WHERE kt.tuan_id = " . intval($weekId) . " {$classFilter}
    ");
    return $stmt->fetch();
}
