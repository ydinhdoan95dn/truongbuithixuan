<?php
/**
 * API Lấy bảng xếp hạng
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : 'tong';
$classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

$conn = getDBConnection();

// Build WHERE clause
$where = "WHERE hs.trang_thai = 1 AND lh.trang_thai = 1";
if ($classId > 0) {
    $where .= " AND lh.khoi = " . $classId;
}

$query = "
    SELECT hs.ho_ten, hs.avatar, lh.ten_lop, lh.khoi,
           COALESCE(dtl.diem_xep_hang, 0) as diem_xep_hang
    FROM hoc_sinh hs
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    LEFT JOIN diem_tich_luy dtl ON hs.id = dtl.hoc_sinh_id
    {$where}
    ORDER BY dtl.diem_xep_hang DESC
    LIMIT {$limit}
";

$stmt = $conn->query($query);
$rankings = $stmt->fetchAll();

echo json_encode(array(
    'success' => true,
    'rankings' => $rankings
));
