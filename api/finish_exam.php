<?php
/**
 * API Kết thúc bài thi
 * Cập nhật: Tích hợp hệ thống tuần học, lưu điểm cao nhất
 */

require_once '../includes/config.php';
require_once '../includes/week_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionToken = isset($input['session_token']) ? $input['session_token'] : '';

if (empty($sessionToken)) {
    echo json_encode(array('success' => false, 'message' => 'Invalid parameters'));
    exit;
}

$conn = getDBConnection();

// Lấy bài làm
$stmtBL = $conn->prepare("
    SELECT bl.*, hs.id as hoc_sinh_id
    FROM bai_lam bl
    JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
    WHERE bl.session_token = ? AND bl.trang_thai = 'dang_lam'
");
$stmtBL->execute(array($sessionToken));
$baiLam = $stmtBL->fetch();

if (!$baiLam) {
    echo json_encode(array('success' => false, 'message' => 'Session not found'));
    exit;
}

// Tính điểm
$stmtCount = $conn->prepare("
    SELECT
        COUNT(*) as tong_cau,
        SUM(is_dung) as so_dung,
        SUM(thoi_gian_tra_loi) as tong_thoi_gian
    FROM chi_tiet_bai_lam
    WHERE bai_lam_id = ?
");
$stmtCount->execute(array($baiLam['id']));
$result = $stmtCount->fetch();

$tongCau = intval($result['tong_cau']);
$soDung = intval($result['so_dung']);
$tongThoiGian = intval($result['tong_thoi_gian']);
$diem = $tongCau > 0 ? round(($soDung / $tongCau) * 10, 2) : 0;

// Cập nhật bài làm
$stmtUpdate = $conn->prepare("
    UPDATE bai_lam
    SET thoi_gian_ket_thuc = NOW(),
        tong_thoi_gian = ?,
        so_cau_dung = ?,
        diem = ?,
        trang_thai = 'hoan_thanh'
    WHERE id = ?
");
$stmtUpdate->execute(array($tongThoiGian, $soDung, $diem, $baiLam['id']));

// Cập nhật điểm tích lũy
$stmtDTL = $conn->prepare("SELECT * FROM diem_tich_luy WHERE hoc_sinh_id = ?");
$stmtDTL->execute(array($baiLam['hoc_sinh_id']));
$diemTichLuy = $stmtDTL->fetch();

// Lấy chuỗi ngày học của học sinh
$stmtStreak = $conn->prepare("SELECT chuoi_ngay_hoc FROM hoc_sinh WHERE id = ?");
$stmtStreak->execute(array($baiLam['hoc_sinh_id']));
$hsStreak = $stmtStreak->fetch();
$chuoiNgayHoc = $hsStreak ? intval($hsStreak['chuoi_ngay_hoc']) : 0;

if ($diemTichLuy) {
    // Cập nhật
    $newTongDiem = $diemTichLuy['tong_diem'] + $diem;
    $newTongLanThi = $diemTichLuy['tong_lan_thi'] + 1;
    $newDiemTB = $newTongDiem / $newTongLanThi;
    $newTongThoiGian = $diemTichLuy['tong_thoi_gian'] + $tongThoiGian;
    $newTocDoTB = $tongCau > 0 ? $newTongThoiGian / ($newTongLanThi * $tongCau) : 0;

    // Tính điểm xếp hạng
    $diemXepHang = calculateRankScore($newDiemTB, $newTongLanThi, $newTocDoTB, $chuoiNgayHoc);

    $stmtUpdateDTL = $conn->prepare("
        UPDATE diem_tich_luy
        SET tong_diem = ?, tong_lan_thi = ?, diem_trung_binh = ?,
            tong_thoi_gian = ?, toc_do_tb = ?,
            diem_xep_hang = ?
        WHERE hoc_sinh_id = ?
    ");
    $stmtUpdateDTL->execute(array(
        $newTongDiem, $newTongLanThi, $newDiemTB,
        $newTongThoiGian, $newTocDoTB,
        $diemXepHang, $baiLam['hoc_sinh_id']
    ));
} else {
    // Tạo mới
    $tocDoTB = $tongCau > 0 ? $tongThoiGian / $tongCau : 0;

    // Tính điểm xếp hạng
    $diemXepHang = calculateRankScore($diem, 1, $tocDoTB, $chuoiNgayHoc);

    $stmtInsertDTL = $conn->prepare("
        INSERT INTO diem_tich_luy (hoc_sinh_id, tong_diem, tong_lan_thi, diem_trung_binh, tong_thoi_gian, toc_do_tb, diem_xep_hang)
        VALUES (?, ?, 1, ?, ?, ?, ?)
    ");
    $stmtInsertDTL->execute(array(
        $baiLam['hoc_sinh_id'], $diem, $diem,
        $tongThoiGian, $tocDoTB, $diemXepHang
    ));
}

// === CẬP NHẬT KẾT QUẢ TUẦN ===
// Lấy thông tin đề thi để xác định tuần
$stmtDeThi = $conn->prepare("SELECT tuan_id FROM de_thi WHERE id = ?");
$stmtDeThi->execute(array($baiLam['de_thi_id']));
$deThi = $stmtDeThi->fetch();

if ($deThi && $deThi['tuan_id']) {
    // Đề thi thuộc tuần học -> cập nhật kết quả tuần
    $soLanThi = updateWeekResult(
        $baiLam['hoc_sinh_id'],
        $deThi['tuan_id'],
        $baiLam['de_thi_id'],
        $diem,
        $baiLam['id'],
        $tongThoiGian
    );
} else {
    // Nếu đề không gắn tuần, kiểm tra tuần hiện tại
    $currentWeek = getCurrentWeek();
    if ($currentWeek) {
        $soLanThi = updateWeekResult(
            $baiLam['hoc_sinh_id'],
            $currentWeek['id'],
            $baiLam['de_thi_id'],
            $diem,
            $baiLam['id'],
            $tongThoiGian
        );
    }
}

// Log hoạt động
logActivity('hoc_sinh', $baiLam['hoc_sinh_id'], 'Hoàn thành thi', 'Điểm: ' . $diem);

// Lấy số lần thi trong tuần để trả về
$soLanThiTrongTuan = 0;
if (isset($soLanThi)) {
    $soLanThiTrongTuan = $soLanThi;
}

echo json_encode(array(
    'success' => true,
    'score' => $diem,
    'correct' => $soDung,
    'total' => $tongCau,
    'so_lan_thi_tuan' => $soLanThiTrongTuan
));
