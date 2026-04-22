<?php
/**
 * ==============================================
 * QUẢN LÝ LỊCH THI - EXAM SCHEDULE
 * - Xem lịch theo tuần/tháng
 * - Gán đề thi chính thức cho tuần
 * - Bật/tắt chế độ thi chính thức
 * - Reset kết quả thi
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/week_helper.php';

if (!isAdminLoggedIn()) {
    redirect('admin/login.php');
}

// Chỉ Admin và GVCN mới có quyền
if (isGVBM()) {
    $_SESSION['error_message'] = 'Bạn không có quyền truy cập chức năng này!';
    redirect('admin/dashboard.php');
}

$admin = getCurrentAdminFull();
$role = getAdminRole();
$myLopId = getAdminLopId();
$conn = getDBConnection();

// Tự động thêm các cột cần thiết nếu chưa có
// Hàm kiểm tra cột tồn tại
function columnExists($conn, $table, $column) {
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute(array($column));
    return $stmt->fetch() !== false;
}

try {
    // Thêm cột vào de_thi
    if (!columnExists($conn, 'de_thi', 'is_chinh_thuc')) {
        $conn->exec("ALTER TABLE de_thi ADD COLUMN is_chinh_thuc TINYINT(1) DEFAULT 0");
    }
    if (!columnExists($conn, 'de_thi', 'so_lan_thi_toi_da_tuan')) {
        $conn->exec("ALTER TABLE de_thi ADD COLUMN so_lan_thi_toi_da_tuan INT DEFAULT 3");
    }
    // Thêm cột chế độ mở: 'mo_ngay' = mở ngay, 'theo_lich' = theo lịch thứ 7-CN
    if (!columnExists($conn, 'de_thi', 'che_do_mo')) {
        $conn->exec("ALTER TABLE de_thi ADD COLUMN che_do_mo VARCHAR(20) DEFAULT 'theo_lich'");
    }

    // Thêm cột vào bai_lam
    if (!columnExists($conn, 'bai_lam', 'is_chinh_thuc')) {
        $conn->exec("ALTER TABLE bai_lam ADD COLUMN is_chinh_thuc TINYINT(1) DEFAULT 0");
    }
    if (!columnExists($conn, 'bai_lam', 'tuan_id')) {
        $conn->exec("ALTER TABLE bai_lam ADD COLUMN tuan_id INT NULL");
    }

    // Thêm cột vào ket_qua_tuan
    if (!columnExists($conn, 'ket_qua_tuan', 'is_chinh_thuc')) {
        $conn->exec("ALTER TABLE ket_qua_tuan ADD COLUMN is_chinh_thuc TINYINT(1) DEFAULT 0");
    }
} catch (Exception $e) {
    // Bỏ qua lỗi
}

$message = '';
$messageType = '';

// Xử lý actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'set_chinh_thuc') {
        // Gán đề thi chính thức cho tuần
        $deThiId = intval($_POST['de_thi_id']);
        $tuanId = intval($_POST['tuan_id']);
        $isChinhThuc = isset($_POST['is_chinh_thuc']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE de_thi SET is_chinh_thuc = ?, tuan_id = ? WHERE id = ?");
        $stmt->execute(array($isChinhThuc, $tuanId, $deThiId));

        $message = $isChinhThuc ? 'Đã đặt làm đề thi chính thức!' : 'Đã bỏ đề thi chính thức!';
        $messageType = 'success';

    } elseif ($action === 'batch_set') {
        // Gán nhiều đề cùng lúc với số lần thi tối đa và chế độ mở
        $tuanId = intval($_POST['tuan_id']);
        $lopId = intval($_POST['lop_id']);
        $deThiIds = isset($_POST['de_thi_ids']) ? $_POST['de_thi_ids'] : array();
        $soLanToiDa = isset($_POST['so_lan_toi_da']) ? $_POST['so_lan_toi_da'] : array();
        $cheDoMo = isset($_POST['che_do_mo']) ? $_POST['che_do_mo'] : array();

        // Reset tất cả đề của lớp này trong tuần này trước
        $stmtReset = $conn->prepare("UPDATE de_thi SET is_chinh_thuc = 0 WHERE tuan_id = ? AND lop_id = ?");
        $stmtReset->execute(array($tuanId, $lopId));

        // Gán các đề được chọn
        if (!empty($deThiIds)) {
            foreach ($deThiIds as $deThiId) {
                $maxAttempts = isset($soLanToiDa[$deThiId]) ? intval($soLanToiDa[$deThiId]) : 3;
                if ($maxAttempts < 1) $maxAttempts = 1;
                if ($maxAttempts > 10) $maxAttempts = 10;

                $mode = isset($cheDoMo[$deThiId]) ? $cheDoMo[$deThiId] : 'theo_lich';
                if (!in_array($mode, array('mo_ngay', 'theo_lich'))) $mode = 'theo_lich';

                $stmtSet = $conn->prepare("UPDATE de_thi SET is_chinh_thuc = 1, tuan_id = ?, so_lan_thi_toi_da_tuan = ?, che_do_mo = ? WHERE id = ?");
                $stmtSet->execute(array($tuanId, $maxAttempts, $mode, $deThiId));
            }
        }

        $message = 'Đã cập nhật danh sách đề thi chính thức!';
        $messageType = 'success';

    } elseif ($action === 'reset_result') {
        // Reset kết quả thi của học sinh
        $hocSinhId = intval($_POST['hoc_sinh_id']);
        $deThiId = intval($_POST['de_thi_id']);
        $tuanId = intval($_POST['tuan_id']);

        // Xóa kết quả tuần
        $stmt = $conn->prepare("DELETE FROM ket_qua_tuan WHERE hoc_sinh_id = ? AND de_thi_id = ? AND tuan_id = ?");
        $stmt->execute(array($hocSinhId, $deThiId, $tuanId));

        $message = 'Đã reset kết quả thi!';
        $messageType = 'success';

    } elseif ($action === 'reset_all_week') {
        // Reset tất cả kết quả của tuần
        $tuanId = intval($_POST['tuan_id']);
        $deThiId = !empty($_POST['de_thi_id']) ? intval($_POST['de_thi_id']) : null;

        if ($deThiId) {
            $stmt = $conn->prepare("DELETE FROM ket_qua_tuan WHERE de_thi_id = ? AND tuan_id = ?");
            $stmt->execute(array($deThiId, $tuanId));
        } else {
            $stmt = $conn->prepare("DELETE FROM ket_qua_tuan WHERE tuan_id = ?");
            $stmt->execute(array($tuanId));
        }

        $message = 'Đã reset tất cả kết quả!';
        $messageType = 'success';

    } elseif ($action === 'remove_from_schedule') {
        // Xóa đề thi khỏi lịch (bỏ is_chinh_thuc)
        $deThiId = intval($_POST['de_thi_id']);

        $stmt = $conn->prepare("UPDATE de_thi SET is_chinh_thuc = 0, tuan_id = NULL WHERE id = ?");
        $stmt->execute(array($deThiId));

        $message = 'Đã xóa đề thi khỏi lịch thi chính thức!';
        $messageType = 'success';
    }
}

// Lấy tuần hiện tại và tuần được chọn
// Mặc định auto-activate tuần hiện tại khi giáo viên vào trang
$currentWeek = getCurrentWeek();
$selectedWeekId = isset($_GET['week_id']) ? intval($_GET['week_id']) : ($currentWeek ? $currentWeek['id'] : 0);

// Nếu không có tuần hiện tại trong hệ thống, lấy tuần gần nhất
if ($selectedWeekId == 0) {
    $lastWeek = getLastWeek();
    if ($lastWeek) {
        $selectedWeekId = $lastWeek['id'];
    }
}

// Lấy học kỳ hiện tại
$semester = getCurrentSemester();
$tuanList = array();
if ($semester) {
    $tuanList = getWeeksBySemester($semester['id']);
}

// Lấy thông tin tuần được chọn
$selectedWeek = null;
foreach ($tuanList as $t) {
    if ($t['id'] == $selectedWeekId) {
        $selectedWeek = $t;
        break;
    }
}

// Lấy danh sách lớp
if (isAdmin()) {
    $stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
    $lopList = $stmtLop->fetchAll();
} else {
    $stmtLop = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ?");
    $stmtLop->execute(array($myLopId));
    $lopList = $stmtLop->fetchAll();
}

// Lớp được chọn
$selectedLopId = isset($_GET['lop_id']) ? intval($_GET['lop_id']) : (count($lopList) > 0 ? $lopList[0]['id'] : 0);

// Lấy danh sách đề thi của lớp
$classFilter = isAdmin() ? "dt.lop_id = ?" : "dt.lop_id = ?";
$stmtDeThi = $conn->prepare("
    SELECT dt.*, mh.ten_mon, mh.mau_sac,
           (SELECT COUNT(*) FROM cau_hoi WHERE de_thi_id = dt.id) as so_cau_hoi
    FROM de_thi dt
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    WHERE dt.lop_id = ? AND dt.trang_thai = 1
    ORDER BY dt.is_chinh_thuc DESC, mh.thu_tu, dt.ten_de
");
$stmtDeThi->execute(array($selectedLopId));
$deThiList = $stmtDeThi->fetchAll();

// Đảm bảo mỗi đề thi có che_do_mo
foreach ($deThiList as &$dt) {
    if (!isset($dt['che_do_mo']) || empty($dt['che_do_mo'])) {
        $dt['che_do_mo'] = 'theo_lich';
    }
}
unset($dt);

// Lấy đề thi chính thức của tuần được chọn
$deThiChinhThuc = array();
if ($selectedWeekId) {
    $stmtCT = $conn->prepare("
        SELECT dt.*, mh.ten_mon, mh.mau_sac
        FROM de_thi dt
        JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
        WHERE dt.tuan_id = ? AND dt.is_chinh_thuc = 1 AND dt.lop_id = ?
    ");
    $stmtCT->execute(array($selectedWeekId, $selectedLopId));
    $deThiChinhThuc = $stmtCT->fetchAll();
}

// Lấy kết quả thi của tuần
$ketQuaTuan = array();
if ($selectedWeekId && !empty($deThiChinhThuc)) {
    $deIds = array_column($deThiChinhThuc, 'id');
    if (!empty($deIds)) {
        $placeholders = implode(',', array_fill(0, count($deIds), '?'));
        $stmtKQ = $conn->prepare("
            SELECT kqt.*, hs.ho_ten as ten_hoc_sinh, dt.ten_de
            FROM ket_qua_tuan kqt
            JOIN hoc_sinh hs ON kqt.hoc_sinh_id = hs.id
            JOIN de_thi dt ON kqt.de_thi_id = dt.id
            WHERE kqt.tuan_id = ? AND kqt.de_thi_id IN ($placeholders)
            ORDER BY kqt.diem_cao_nhat DESC
        ");
        $params = array_merge(array($selectedWeekId), $deIds);
        $stmtKQ->execute($params);
        $ketQuaTuan = $stmtKQ->fetchAll();
    }
}

// Lấy danh sách học sinh của lớp (để reset)
$stmtHS = $conn->prepare("SELECT id, ho_ten FROM hoc_sinh WHERE lop_id = ? AND trang_thai = 1 ORDER BY ho_ten");
$stmtHS->execute(array($selectedLopId));
$hocSinhList = $stmtHS->fetchAll();

// ====== TỔNG QUAN LỊCH THI ĐÃ CÀI ĐẶT ======
// Lấy tất cả đề thi chính thức đã được gán cho các tuần
$allScheduleSQL = "
    SELECT dt.id, dt.ten_de, dt.is_chinh_thuc, dt.so_lan_thi_toi_da_tuan,
           dt.tuan_id, dt.lop_id, dt.created_at, dt.che_do_mo,
           mh.ten_mon, mh.mau_sac,
           lh.ten_lop, lh.khoi,
           th.ten_tuan, th.ngay_bat_dau, th.ngay_ket_thuc,
           hk.ten_hoc_ky,
           (SELECT COUNT(*) FROM cau_hoi WHERE de_thi_id = dt.id) as so_cau_hoi,
           (SELECT COUNT(DISTINCT hoc_sinh_id) FROM bai_lam WHERE de_thi_id = dt.id AND is_chinh_thuc = 1 AND trang_thai = 'hoan_thanh') as so_luot_thi
    FROM de_thi dt
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    JOIN lop_hoc lh ON dt.lop_id = lh.id
    LEFT JOIN tuan_hoc th ON dt.tuan_id = th.id
    LEFT JOIN hoc_ky hk ON th.hoc_ky_id = hk.id
    WHERE dt.is_chinh_thuc = 1 AND dt.tuan_id IS NOT NULL
";

// Nếu là GVCN thì chỉ xem lớp của mình
if (!isAdmin()) {
    $allScheduleSQL .= " AND dt.lop_id = " . intval($myLopId);
}
$allScheduleSQL .= " ORDER BY th.ngay_bat_dau DESC, lh.thu_tu, dt.ten_de";

$stmtAllSchedule = $conn->query($allScheduleSQL);
$allScheduleList = $stmtAllSchedule->fetchAll();

// Lấy danh sách môn học để filter
$stmtMonHoc = $conn->query("SELECT id, ten_mon FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$monHocList = $stmtMonHoc->fetchAll();

// Lấy danh sách học kỳ để filter
$stmtHocKy = $conn->query("SELECT id, ten_hoc_ky FROM hoc_ky ORDER BY id DESC");
$hocKyList = $stmtHocKy->fetchAll();

$pageTitle = 'Quản lý lịch thi';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #EF4444; }

        .schedule-header {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-weight: 600;
            color: #6B7280;
            font-size: 0.9rem;
        }

        .week-selector {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .week-btn {
            padding: 8px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .week-btn:hover {
            border-color: #667eea;
        }

        .week-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .week-btn.current {
            border-color: #10B981;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .exam-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 500px;
            overflow-y: auto;
        }

        .exam-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            background: #F9FAFB;
            transition: all 0.2s;
        }

        .exam-item:hover {
            background: #F3F4F6;
        }

        .exam-item.official {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(102, 126, 234, 0.1) 100%);
            border: 2px solid #10B981;
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0%, 100% { border-color: #10B981; }
            50% { border-color: #667eea; }
        }

        .exam-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .exam-info {
            flex: 1;
        }

        .exam-name {
            font-weight: 600;
            color: #1F2937;
        }

        .exam-meta {
            font-size: 0.85rem;
            color: #6B7280;
            display: flex;
            gap: 12px;
            margin-top: 4px;
        }

        .exam-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .badge-official {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }

        .badge-practice {
            background: #E5E7EB;
            color: #6B7280;
        }

        /* Week info card */
        .week-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }

        .week-info-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .week-info-dates {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .week-stats {
            display: flex;
            gap: 24px;
            margin-top: 16px;
        }

        .week-stat {
            text-align: center;
        }

        .week-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .week-stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Results table */
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }

        .results-table th,
        .results-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }

        .results-table th {
            background: #F9FAFB;
            font-weight: 700;
            color: #374151;
            font-size: 0.85rem;
        }

        .results-table tr:hover {
            background: #F9FAFB;
        }

        .score-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .score-high { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .score-medium { background: rgba(245, 158, 11, 0.1); color: #F59E0B; }
        .score-low { background: rgba(239, 68, 68, 0.1); color: #EF4444; }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7280;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Tab navigation */
        .tab-nav {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 0;
        }
        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            color: #667eea;
        }
        .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-btn.active, .tab-btn:hover {
            background: #F44336;
            color: #ffffff;

        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Search & Filter Bar */
        .search-filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 12px;
        }
        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
        }
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9CA3AF;
        }
        .filter-select {
            min-width: 150px;
        }
        .filter-select select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }
        .filter-select select:focus {
            border-color: #667eea;
            outline: none;
        }

        /* Schedule Overview Table */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .schedule-table th,
        .schedule-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #E5E7EB;
        }
        .schedule-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        .schedule-table tbody tr:hover {
            background: #F3F4F6;
        }
        .schedule-table tbody tr.highlight {
            background: #FEF3C7;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-active {
            background: #D1FAE5;
            color: #059669;
        }
        .status-past {
            background: #E5E7EB;
            color: #6B7280;
        }
        .status-future {
            background: #DBEAFE;
            color: #2563EB;
        }

        .action-btns {
            display: flex;
            gap: 4px;
        }
        .action-btns button {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .action-btns .btn-edit {
            background: #DBEAFE;
            color: #2563EB;
        }
        .action-btns .btn-edit:hover {
            background: #BFDBFE;
        }
        .action-btns .btn-delete {
            background: #FEE2E2;
            color: #DC2626;
        }
        .action-btns .btn-delete:hover {
            background: #FECACA;
        }
        .action-btns .btn-view {
            background: #D1FAE5;
            color: #059669;
        }
        .action-btns .btn-view:hover {
            background: #A7F3D0;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #9CA3AF;
        }
        .no-data i {
            font-size: 4rem;
            margin-bottom: 16px;
            display: block;
        }

        .table-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 0.85rem;
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 24px;">
                📅 <?php echo $pageTitle; ?>
            </h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('schedule')">
                    <i data-feather="calendar" style="width: 16px; height: 16px; margin-right: 6px;"></i>
                    Cài đặt lịch thi
                </button>
                <button class="tab-btn" onclick="switchTab('overview')">
                    <i data-feather="list" style="width: 16px; height: 16px; margin-right: 6px;"></i>
                    Tổng quan (<?php echo count($allScheduleList); ?>)
                </button>
            </div>

            <!-- Tab 1: Cài đặt lịch thi -->
            <div id="tab-schedule" class="tab-content active">
            <!-- Filters -->
            <div class="schedule-header">
                <div class="filter-group">
                    <span class="filter-label">Lớp:</span>
                    <select class="form-input" style="width: auto;" onchange="changeFilter();" id="lopSelect">
                        <?php foreach ($lopList as $lop): ?>
                            <option value="<?php echo $lop['id']; ?>" <?php echo $lop['id'] == $selectedLopId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lop['ten_lop']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" style="flex: 1;">
                    <span class="filter-label">Tuần:</span>
                    <div class="week-selector">
                        <?php foreach ($tuanList as $tuan): ?>
                            <button type="button" class="week-btn <?php echo $tuan['id'] == $selectedWeekId ? 'active' : ''; ?> <?php echo ($currentWeek && $tuan['id'] == $currentWeek['id']) ? 'current' : ''; ?>"
                                    onclick="selectWeek(<?php echo $tuan['id']; ?>)">
                                <?php echo htmlspecialchars($tuan['ten_tuan']); ?>
                                <?php if ($currentWeek && $tuan['id'] == $currentWeek['id']): ?>
                                    <span style="font-size: 0.7rem;">📍</span>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($selectedWeek): ?>
            <!-- Week Info -->
            <div class="week-info-card">
                <div class="week-info-title">
                    <?php echo htmlspecialchars($selectedWeek['ten_tuan']); ?>
                    <?php if ($currentWeek && $selectedWeek['id'] == $currentWeek['id']): ?>
                        <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; margin-left: 8px;">Tuần hiện tại</span>
                    <?php endif; ?>
                </div>
                <div class="week-info-dates">
                    📆 <?php echo date('d/m/Y', strtotime($selectedWeek['ngay_bat_dau'])); ?> - <?php echo date('d/m/Y', strtotime($selectedWeek['ngay_ket_thuc'])); ?>
                </div>
                <div class="week-stats">
                    <div class="week-stat">
                        <div class="week-stat-value"><?php echo count($deThiChinhThuc); ?></div>
                        <div class="week-stat-label">Đề thi chính thức</div>
                    </div>
                    <div class="week-stat">
                        <div class="week-stat-value"><?php echo count($ketQuaTuan); ?></div>
                        <div class="week-stat-label">Lượt thi</div>
                    </div>
                    <div class="week-stat">
                        <div class="week-stat-value">3</div>
                        <div class="week-stat-label">Lần thi tối đa</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-grid">
                <!-- Danh sách đề thi -->
                <div class="card">
                    <div class="card-title">
                        <i data-feather="file-text"></i>
                        Danh sách đề thi (click để chọn)
                    </div>

                    <div class="exam-list">
                        <?php if (empty($deThiList)): ?>
                            <div style="text-align: center; padding: 40px; color: #9CA3AF;">
                                Chưa có đề thi nào cho lớp này
                            </div>
                        <?php else: ?>
                            <?php foreach ($deThiList as $dt): ?>
                                <?php
                                $isOfficial = ($dt['is_chinh_thuc'] == 1 && $dt['tuan_id'] == $selectedWeekId);
                                $soLanToiDa = isset($dt['so_lan_thi_toi_da_tuan']) ? $dt['so_lan_thi_toi_da_tuan'] : 3;
                                $cheDoMo = isset($dt['che_do_mo']) ? $dt['che_do_mo'] : 'theo_lich';
                                ?>
                                <div class="exam-item <?php echo $isOfficial ? 'official' : ''; ?>"
                                     onclick="toggleExamSelection(this, <?php echo $dt['id']; ?>, '<?php echo addslashes($dt['ten_de']); ?>', '<?php echo addslashes($dt['ten_mon']); ?>', <?php echo $dt['so_cau_hoi']; ?>, <?php echo $soLanToiDa; ?>, '<?php echo $cheDoMo; ?>')"
                                     data-id="<?php echo $dt['id']; ?>"
                                     data-selected="<?php echo $isOfficial ? '1' : '0'; ?>"
                                     data-max-attempts="<?php echo $soLanToiDa; ?>"
                                     data-che-do-mo="<?php echo $cheDoMo; ?>"
                                     style="cursor: pointer;">
                                    <div class="exam-select-icon" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; background: <?php echo $isOfficial ? 'linear-gradient(135deg, #10B981 0%, #059669 100%)' : '#E5E7EB'; ?>; color: <?php echo $isOfficial ? 'white' : '#9CA3AF'; ?>;">
                                        <?php echo $isOfficial ? '✓' : '○'; ?>
                                    </div>
                                    <div class="exam-info">
                                        <div class="exam-name"><?php echo htmlspecialchars($dt['ten_de']); ?></div>
                                        <div class="exam-meta">
                                            <span style="color: <?php echo $dt['mau_sac']; ?>;">📚 <?php echo htmlspecialchars($dt['ten_mon']); ?></span>
                                            <span>❓ <?php echo $dt['so_cau_hoi']; ?> câu</span>
                                            <span>⏱️ <?php echo $dt['thoi_gian_cau']; ?>s/câu</span>
                                        </div>
                                    </div>
                                    <?php if ($isOfficial): ?>
                                        <div style="text-align: right;">
                                            <span class="exam-badge badge-official">✓ Chính thức</span>
                                            <div style="font-size: 0.75rem; color: #059669; margin-top: 4px;">
                                                <?php echo $cheDoMo == 'mo_ngay' ? '⚡ Mở ngay' : '📅 T7-CN'; ?> • <?php echo $soLanToiDa; ?> lần
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="exam-badge badge-practice">Luyện tập</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Nút xem danh sách đã chọn -->
                    <div id="selectedExamsPanel" style="margin-top: 16px; padding: 16px; background: #F0FDF4; border-radius: 12px; display: none;">
                        <div style="font-weight: 700; color: #059669; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span>✅</span> Đề thi chính thức đã chọn: <span id="selectedCount">0</span>
                        </div>
                        <div id="selectedExamsList" style="margin-bottom: 12px;"></div>
                        <button type="button" class="btn btn-primary" onclick="showConfirmModal()">
                            <i data-feather="check-circle"></i> Xác nhận & Lưu
                        </button>
                    </div>
                </div>

                <!-- Kết quả thi chính thức -->
                <div class="card">
                    <div class="card-title">
                        <i data-feather="award"></i>
                        Kết quả thi chính thức
                        <?php if (!empty($ketQuaTuan)): ?>
                        <button class="btn btn-secondary btn-sm" style="margin-left: auto;" onclick="showResetModal()">
                            <i data-feather="refresh-cw"></i> Reset
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($deThiChinhThuc)): ?>
                        <div style="text-align: center; padding: 40px; color: #9CA3AF;">
                            <div style="font-size: 3rem; margin-bottom: 12px;">📋</div>
                            <div>Chưa có đề thi chính thức cho tuần này</div>
                            <div style="font-size: 0.85rem; margin-top: 8px;">Tick chọn đề thi bên trái và nhấn "Lưu thay đổi"</div>
                        </div>
                    <?php elseif (empty($ketQuaTuan)): ?>
                        <div style="text-align: center; padding: 40px; color: #9CA3AF;">
                            <div style="font-size: 3rem; margin-bottom: 12px;">📊</div>
                            <div>Chưa có học sinh nào làm bài</div>
                        </div>
                    <?php else: ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <table class="results-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Học sinh</th>
                                        <th>Đề thi</th>
                                        <th>Điểm cao nhất</th>
                                        <th>Số lần</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($ketQuaTuan as $kq): ?>
                                        <tr>
                                            <td><?php echo $rank++; ?></td>
                                            <td><?php echo htmlspecialchars($kq['ten_hoc_sinh']); ?></td>
                                            <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($kq['ten_de']); ?></td>
                                            <td>
                                                <?php
                                                $scoreClass = 'score-low';
                                                if ($kq['diem_cao_nhat'] >= 8) $scoreClass = 'score-high';
                                                elseif ($kq['diem_cao_nhat'] >= 5) $scoreClass = 'score-medium';
                                                ?>
                                                <span class="score-badge <?php echo $scoreClass; ?>">
                                                    <?php echo number_format($kq['diem_cao_nhat'], 1); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: <?php echo $kq['so_lan_thi'] >= 3 ? '#EF4444' : '#6B7280'; ?>;">
                                                    <?php echo $kq['so_lan_thi']; ?>/3
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-ghost btn-sm" style="color: #EF4444;"
                                                        onclick="resetStudent(<?php echo $kq['hoc_sinh_id']; ?>, <?php echo $kq['de_thi_id']; ?>, '<?php echo addslashes($kq['ten_hoc_sinh']); ?>')">
                                                    <i data-feather="trash-2"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Hướng dẫn -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-title">
                    <i data-feather="info"></i>
                    Hướng dẫn sử dụng
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                    <div style="padding: 16px; background: #F0FDF4; border-radius: 12px;">
                        <div style="font-weight: 700; color: #059669; margin-bottom: 8px;">✅ Đề thi chính thức</div>
                        <div style="font-size: 0.9rem; color: #374151;">
                            Học sinh chỉ được làm tối đa 3 lần/tuần. Kết quả dùng để xếp hạng chính thức.
                        </div>
                    </div>
                    <div style="padding: 16px; background: #F9FAFB; border-radius: 12px;">
                        <div style="font-weight: 700; color: #6B7280; margin-bottom: 8px;">📝 Đề thi luyện tập</div>
                        <div style="font-size: 0.9rem; color: #374151;">
                            Học sinh làm không giới hạn. Dùng để tính điểm chuyên cần.
                        </div>
                    </div>
                    <div style="padding: 16px; background: #FEF3C7; border-radius: 12px;">
                        <div style="font-weight: 700; color: #92400E; margin-bottom: 8px;">⏰ Chế độ mở thi</div>
                        <div style="font-size: 0.9rem; color: #374151;">
                            <strong>⚡ Mở ngay:</strong> Học sinh thi được ngay khi đề được gán.<br>
                            <strong>📅 T7-CN:</strong> Chỉ mở vào Thứ 7 & Chủ nhật trong tuần.
                        </div>
                    </div>
                    <div style="padding: 16px; background: #FEE2E2; border-radius: 12px;">
                        <div style="font-weight: 700; color: #B91C1C; margin-bottom: 8px;">🔄 Reset kết quả</div>
                        <div style="font-size: 0.9rem; color: #374151;">
                            Dùng khi cần cho học sinh thi lại do lỗi hoặc trường hợp đặc biệt.
                        </div>
                    </div>
                </div>
            </div>
            </div><!-- End Tab 1 -->

            <!-- Tab 2: Tổng quan lịch thi đã cài đặt -->
            <div id="tab-overview" class="tab-content">
                <div class="card">
                    <div class="card-title">
                        <i data-feather="list"></i>
                        Danh sách lịch thi chính thức đã cài đặt
                    </div>

                    <!-- Search & Filter Bar -->
                    <div class="search-filter-bar">
                        <div class="search-box">
                            <i data-feather="search"></i>
                            <input type="text" id="searchInput" placeholder="Tìm kiếm đề thi, môn học..." oninput="filterTable()">
                        </div>
                        <div class="filter-select">
                            <select id="filterLop" onchange="filterTable()">
                                <option value="">Tất cả lớp</option>
                                <?php foreach ($lopList as $lop): ?>
                                    <option value="<?php echo htmlspecialchars($lop['ten_lop']); ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-select">
                            <select id="filterMon" onchange="filterTable()">
                                <option value="">Tất cả môn</option>
                                <?php foreach ($monHocList as $mon): ?>
                                    <option value="<?php echo htmlspecialchars($mon['ten_mon']); ?>"><?php echo htmlspecialchars($mon['ten_mon']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-select">
                            <select id="filterTuan" onchange="filterTable()">
                                <option value="">Tất cả tuần</option>
                                <?php foreach ($tuanList as $tuan): ?>
                                    <option value="<?php echo htmlspecialchars($tuan['ten_tuan']); ?>"><?php echo htmlspecialchars($tuan['ten_tuan']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-select">
                            <select id="filterStatus" onchange="filterTable()">
                                <option value="">Tất cả trạng thái</option>
                                <option value="active">Đang diễn ra</option>
                                <option value="past">Đã qua</option>
                                <option value="future">Sắp tới</option>
                            </select>
                        </div>
                    </div>

                    <!-- Table Info -->
                    <div class="table-info">
                        <span>Hiển thị <strong id="visibleCount"><?php echo count($allScheduleList); ?></strong> / <?php echo count($allScheduleList); ?> bản ghi</span>
                        <button class="btn btn-secondary btn-sm" onclick="exportToExcel()">
                            <i data-feather="download"></i> Xuất Excel
                        </button>
                    </div>

                    <?php if (empty($allScheduleList)): ?>
                        <div class="no-data">
                            <span style="font-size: 4rem;">📋</span>
                            <p>Chưa có lịch thi chính thức nào được cài đặt</p>
                            <p style="font-size: 0.85rem;">Chuyển sang tab "Cài đặt lịch thi" để bắt đầu</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto; max-height: 600px; overflow-y: auto;">
                            <table class="schedule-table" id="scheduleTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th>Đề thi</th>
                                        <th>Môn học</th>
                                        <th>Lớp</th>
                                        <th>Tuần</th>
                                        <th>Thời gian</th>
                                        <th style="text-align: center;">Số lần</th>
                                        <th style="text-align: center;">Lượt thi</th>
                                        <th>Trạng thái</th>
                                        <th style="width: 120px;">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $today = date('Y-m-d');
                                    $stt = 1;
                                    foreach ($allScheduleList as $schedule):
                                        // Xác định trạng thái
                                        $cheDoMoSchedule = isset($schedule['che_do_mo']) ? $schedule['che_do_mo'] : '';

                                        // Nếu chế độ "Mở ngay" thì luôn hiển thị "Đang mở"
                                        if ($cheDoMoSchedule === 'mo_ngay') {
                                            $status = 'active';
                                            $statusText = 'Đang mở';
                                            $statusClass = 'status-active';
                                        } else {
                                            // Chế độ "Theo lịch" - kiểm tra theo ngày
                                            $status = 'past';
                                            $statusText = 'Đã qua';
                                            $statusClass = 'status-past';
                                            if ($schedule['ngay_bat_dau'] && $schedule['ngay_ket_thuc']) {
                                                if ($today >= $schedule['ngay_bat_dau'] && $today <= $schedule['ngay_ket_thuc']) {
                                                    $status = 'active';
                                                    $statusText = 'Đang diễn ra';
                                                    $statusClass = 'status-active';
                                                } elseif ($today < $schedule['ngay_bat_dau']) {
                                                    $status = 'future';
                                                    $statusText = 'Sắp tới';
                                                    $statusClass = 'status-future';
                                                }
                                            }
                                        }
                                        $isCurrentWeek = ($currentWeek && $schedule['tuan_id'] == $currentWeek['id']);
                                    ?>
                                        <tr class="schedule-row <?php echo $isCurrentWeek ? 'highlight' : ''; ?>"
                                            data-ten-de="<?php echo htmlspecialchars(strtolower($schedule['ten_de'])); ?>"
                                            data-ten-mon="<?php echo htmlspecialchars(strtolower($schedule['ten_mon'])); ?>"
                                            data-ten-lop="<?php echo htmlspecialchars($schedule['ten_lop']); ?>"
                                            data-ten-tuan="<?php echo htmlspecialchars($schedule['ten_tuan']); ?>"
                                            data-status="<?php echo $status; ?>">
                                            <td><?php echo $stt++; ?></td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($schedule['ten_de']); ?></div>
                                                <div style="font-size: 0.8rem; color: #6B7280;"><?php echo $schedule['so_cau_hoi']; ?> câu hỏi</div>
                                            </td>
                                            <td>
                                                <span style="display: inline-flex; align-items: center; gap: 6px;">
                                                    <span style="width: 10px; height: 10px; border-radius: 50%; background: <?php echo $schedule['mau_sac']; ?>;"></span>
                                                    <?php echo htmlspecialchars($schedule['ten_mon']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600;"><?php echo htmlspecialchars($schedule['ten_lop']); ?></span>
                                                <span style="font-size: 0.8rem; color: #6B7280;">(K<?php echo $schedule['khoi']; ?>)</span>
                                            </td>
                                            <td>
                                                <span style="font-weight: 600; color: #667eea;">
                                                    <?php echo htmlspecialchars($schedule['ten_tuan']); ?>
                                                </span>
                                                <?php if ($isCurrentWeek): ?>
                                                    <span style="font-size: 0.7rem;">📍</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 0.85rem;">
                                                <?php if ($schedule['ngay_bat_dau']): ?>
                                                    <?php echo date('d/m', strtotime($schedule['ngay_bat_dau'])); ?> - <?php echo date('d/m/Y', strtotime($schedule['ngay_ket_thuc'])); ?>
                                                <?php else: ?>
                                                    <span style="color: #9CA3AF;">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="background: #E5E7EB; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">
                                                    <?php echo $schedule['so_lan_thi_toi_da_tuan'] ?: 3; ?> lần
                                                </span>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-weight: 700; color: <?php echo $schedule['so_luot_thi'] > 0 ? '#10B981' : '#9CA3AF'; ?>;">
                                                    <?php echo $schedule['so_luot_thi']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusText; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-btns">
                                                    <button class="btn-view" title="Xem chi tiết" onclick="viewSchedule(<?php echo $schedule['lop_id']; ?>, <?php echo $schedule['tuan_id']; ?>)">
                                                        <i data-feather="eye"></i>
                                                    </button>
                                                    <button class="btn-edit" title="Chỉnh sửa" onclick="editSchedule(<?php echo $schedule['lop_id']; ?>, <?php echo $schedule['tuan_id']; ?>)">
                                                        <i data-feather="edit-2"></i>
                                                    </button>
                                                    <button class="btn-delete" title="Xóa khỏi lịch" onclick="removeFromSchedule(<?php echo $schedule['id']; ?>, '<?php echo addslashes($schedule['ten_de']); ?>')">
                                                        <i data-feather="trash-2"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!-- End Tab 2 -->
        </main>
    </div>

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content" style="max-width: 600px;">
            <h3 class="modal-title">✅ Xác nhận đề thi chính thức</h3>

            <form method="POST" id="officialExamForm">
                <input type="hidden" name="action" value="batch_set">
                <input type="hidden" name="tuan_id" value="<?php echo $selectedWeekId; ?>">
                <input type="hidden" name="lop_id" value="<?php echo $selectedLopId; ?>">

                <div id="confirmExamsList" style="max-height: 300px; overflow-y: auto; margin-bottom: 16px;"></div>

                <div style="background: #FEF3C7; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
                    <div style="font-weight: 700; color: #92400E; margin-bottom: 8px;">⚠️ Lưu ý quan trọng:</div>
                    <ul style="margin: 0; padding-left: 20px; color: #92400E; font-size: 0.9rem;">
                        <li>Học sinh chỉ được làm bài thi chính thức theo số lần tối đa đã cài đặt</li>
                        <li>Kết quả bài thi chính thức sẽ dùng để xếp hạng</li>
                        <li>Bạn có thể thay đổi số lần thi tối đa cho từng đề</li>
                    </ul>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeConfirmModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i data-feather="save"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Modal -->
    <div class="modal-overlay" id="resetModal">
        <div class="modal-content">
            <h3 class="modal-title">🔄 Reset kết quả thi</h3>
            <form method="POST" id="resetForm">
                <input type="hidden" name="action" value="reset_result">
                <input type="hidden" name="tuan_id" value="<?php echo $selectedWeekId; ?>">
                <input type="hidden" name="de_thi_id" id="reset_de_thi_id">
                <input type="hidden" name="hoc_sinh_id" id="reset_hoc_sinh_id">

                <div id="resetSingleStudent" style="display: none;">
                    <p>Bạn có chắc muốn reset kết quả thi của <strong id="resetStudentName"></strong>?</p>
                </div>

                <div id="resetAllOption">
                    <div class="form-group">
                        <label class="form-label">Chọn đề thi</label>
                        <select name="de_thi_id" class="form-input" id="resetExamSelect">
                            <option value="">-- Tất cả đề --</option>
                            <?php foreach ($deThiChinhThuc as $dt): ?>
                                <option value="<?php echo $dt['id']; ?>"><?php echo htmlspecialchars($dt['ten_de']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p style="color: #EF4444; font-weight: 600;">⚠️ Thao tác này sẽ xóa tất cả kết quả thi!</p>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeResetModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1; background: #EF4444;">Xác nhận Reset</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        feather.replace();

        // Lưu trữ danh sách đề thi đã chọn
        var selectedExams = {};

        // Khởi tạo từ các đề đã được chọn sẵn
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.exam-item[data-selected="1"]').forEach(function(item) {
                var id = item.getAttribute('data-id');
                var maxAttempts = item.getAttribute('data-max-attempts') || 3;
                var cheDoMo = item.getAttribute('data-che-do-mo') || 'theo_lich';
                var name = item.querySelector('.exam-name').textContent;
                var subject = item.querySelector('.exam-meta span').textContent;
                selectedExams[id] = {
                    name: name,
                    subject: subject,
                    maxAttempts: parseInt(maxAttempts),
                    cheDoMo: cheDoMo
                };
            });
            updateSelectedPanel();
        });

        function changeFilter() {
            var lopId = document.getElementById('lopSelect').value;
            var weekId = <?php echo $selectedWeekId; ?>;
            window.location.href = '?lop_id=' + lopId + '&week_id=' + weekId;
        }

        function selectWeek(weekId) {
            var lopId = document.getElementById('lopSelect').value;
            window.location.href = '?lop_id=' + lopId + '&week_id=' + weekId;
        }

        function toggleExamSelection(element, id, name, subject, soCau, maxAttempts, cheDoMo) {
            id = String(id); // Đảm bảo là string để khớp với keys
            var isSelected = element.getAttribute('data-selected') === '1';
            cheDoMo = cheDoMo || 'theo_lich';

            if (isSelected) {
                // Bỏ chọn
                delete selectedExams[id];
                element.setAttribute('data-selected', '0');
                element.classList.remove('official');

                // Cập nhật icon
                var icon = element.querySelector('.exam-select-icon');
                icon.style.background = '#E5E7EB';
                icon.style.color = '#9CA3AF';
                icon.textContent = '○';

                // Cập nhật badge
                var badgeContainer = element.querySelector('.exam-badge').parentElement;
                if (badgeContainer.querySelector('.exam-badge')) {
                    badgeContainer.innerHTML = '<span class="exam-badge badge-practice">Luyện tập</span>';
                }
            } else {
                // Chọn
                selectedExams[id] = {
                    name: name,
                    subject: subject,
                    maxAttempts: maxAttempts || 3,
                    cheDoMo: cheDoMo
                };
                element.setAttribute('data-selected', '1');
                element.classList.add('official');

                // Cập nhật icon
                var icon = element.querySelector('.exam-select-icon');
                icon.style.background = 'linear-gradient(135deg, #10B981 0%, #059669 100%)';
                icon.style.color = 'white';
                icon.textContent = '✓';

                // Cập nhật badge
                var modeText = cheDoMo == 'mo_ngay' ? '⚡ Mở ngay' : '📅 T7-CN';
                var badgeContainer = element.querySelector('.exam-badge').parentElement;
                badgeContainer.innerHTML = '<div style="text-align: right;"><span class="exam-badge badge-official">✓ Chính thức</span><div style="font-size: 0.75rem; color: #059669; margin-top: 4px;">' + modeText + ' • ' + maxAttempts + ' lần</div></div>';
            }

            updateSelectedPanel();
        }

        function updateSelectedPanel() {
            var panel = document.getElementById('selectedExamsPanel');
            var countSpan = document.getElementById('selectedCount');
            var listDiv = document.getElementById('selectedExamsList');

            var count = Object.keys(selectedExams).length;
            countSpan.textContent = count;

            if (count > 0) {
                panel.style.display = 'block';

                var html = '';
                for (var id in selectedExams) {
                    var exam = selectedExams[id];
                    var currentMode = exam.cheDoMo || 'theo_lich';
                    html += '<div class="selected-exam-row" style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: white; border-radius: 10px; margin-bottom: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">';
                    html += '<span style="color: #10B981; font-size: 1.1rem;">✓</span>';
                    html += '<div style="flex: 1;">';
                    html += '<div style="font-weight: 600; color: #1F2937;">' + exam.name + '</div>';
                    html += '<div style="font-size: 0.8rem; color: #6B7280;">' + exam.subject + '</div>';
                    html += '</div>';
                    html += '<div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">';
                    // Chế độ mở
                    html += '<select class="form-input" style="width: auto; padding: 4px 6px; font-size: 0.8rem; border-radius: 6px;" onchange="updateExamMode(' + id + ', this.value)">';
                    html += '<option value="mo_ngay"' + (currentMode == 'mo_ngay' ? ' selected' : '') + '>⚡ Mở ngay</option>';
                    html += '<option value="theo_lich"' + (currentMode == 'theo_lich' ? ' selected' : '') + '>📅 T7-CN</option>';
                    html += '</select>';
                    // Số lần
                    html += '<select class="form-input" style="width: 65px; padding: 4px 6px; font-size: 0.85rem; border-radius: 6px;" onchange="updateExamMaxAttempts(' + id + ', this.value)">';
                    for (var i = 1; i <= 10; i++) {
                        var selected = i == exam.maxAttempts ? ' selected' : '';
                        html += '<option value="' + i + '"' + selected + '>' + i + ' lần</option>';
                    }
                    html += '</select>';
                    html += '<button type="button" onclick="removeExamFromSelection(' + id + ')" style="width: 28px; height: 28px; border: none; background: #FEE2E2; color: #EF4444; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; transition: all 0.2s;" onmouseover="this.style.background=\'#FCA5A5\'" onmouseout="this.style.background=\'#FEE2E2\'">&times;</button>';
                    html += '</div>';
                    html += '</div>';
                }
                listDiv.innerHTML = html;
            } else {
                panel.style.display = 'none';
            }
        }

        function updateExamMode(examId, newMode) {
            examId = String(examId); // Đảm bảo là string để khớp với keys
            if (selectedExams[examId]) {
                selectedExams[examId].cheDoMo = newMode;
                // Cập nhật trong danh sách đề thi
                var examItem = document.querySelector('.exam-item[data-id="' + examId + '"]');
                if (examItem) {
                    examItem.setAttribute('data-che-do-mo', newMode);
                    var badgeContainer = examItem.querySelector('.exam-badge');
                    if (badgeContainer && badgeContainer.parentElement) {
                        var parent = badgeContainer.parentElement;
                        if (parent.querySelector('.exam-badge.badge-official')) {
                            var modeText = newMode == 'mo_ngay' ? '⚡ Mở ngay' : '📅 T7-CN';
                            parent.innerHTML = '<span class="exam-badge badge-official">✓ Chính thức</span><div style="font-size: 0.75rem; color: #059669; margin-top: 4px;">' + modeText + ' • ' + selectedExams[examId].maxAttempts + ' lần</div>';
                        }
                    }
                }
            }
        }

        function updateExamMaxAttempts(examId, newValue) {
            examId = String(examId); // Đảm bảo là string để khớp với keys
            if (selectedExams[examId]) {
                selectedExams[examId].maxAttempts = parseInt(newValue);
                // Cập nhật badge trong danh sách đề thi
                var examItem = document.querySelector('.exam-item[data-id="' + examId + '"]');
                if (examItem) {
                    examItem.setAttribute('data-max-attempts', newValue);
                    var badgeContainer = examItem.querySelector('.exam-badge');
                    if (badgeContainer && badgeContainer.parentElement) {
                        var parent = badgeContainer.parentElement;
                        if (parent.querySelector('.exam-badge.badge-official')) {
                            parent.innerHTML = '<span class="exam-badge badge-official">✓ Chính thức</span><div style="font-size: 0.75rem; color: #059669; margin-top: 4px;">Tối đa ' + newValue + ' lần</div>';
                        }
                    }
                }
            }
        }

        function removeExamFromSelection(examId) {
            examId = String(examId); // Đảm bảo là string để khớp với keys
            // Xóa khỏi selectedExams
            delete selectedExams[examId];

            // Cập nhật UI của exam item trong danh sách
            var examItem = document.querySelector('.exam-item[data-id="' + examId + '"]');
            if (examItem) {
                examItem.setAttribute('data-selected', '0');
                examItem.classList.remove('official');

                // Cập nhật icon
                var icon = examItem.querySelector('.exam-select-icon');
                if (icon) {
                    icon.style.background = '#E5E7EB';
                    icon.style.color = '#9CA3AF';
                    icon.textContent = '○';
                }

                // Cập nhật badge
                var badgeContainer = examItem.querySelector('.exam-badge');
                if (badgeContainer && badgeContainer.parentElement) {
                    badgeContainer.parentElement.innerHTML = '<span class="exam-badge badge-practice">Luyện tập</span>';
                }
            }

            // Cập nhật panel
            updateSelectedPanel();
        }

        function showConfirmModal() {
            var confirmList = document.getElementById('confirmExamsList');
            var html = '';

            for (var id in selectedExams) {
                var exam = selectedExams[id];
                var currentMode = exam.cheDoMo || 'theo_lich';
                html += '<div style="display: flex; align-items: center; gap: 12px; padding: 12px; background: #F9FAFB; border-radius: 10px; margin-bottom: 8px;">';
                html += '<input type="hidden" name="de_thi_ids[]" value="' + id + '">';
                html += '<div style="flex: 1;">';
                html += '<div style="font-weight: 700; color: #1F2937;">' + exam.name + '</div>';
                html += '<div style="font-size: 0.85rem; color: #6B7280;">' + exam.subject + '</div>';
                html += '</div>';
                html += '<div style="display: flex; flex-direction: column; gap: 6px;">';
                // Chế độ mở
                html += '<div style="display: flex; align-items: center; gap: 8px;">';
                html += '<label style="font-size: 0.8rem; color: #374151; width: 70px;">Chế độ:</label>';
                html += '<select name="che_do_mo[' + id + ']" class="form-input" style="width: 110px; padding: 4px 6px; font-size: 0.85rem;">';
                html += '<option value="mo_ngay"' + (currentMode == 'mo_ngay' ? ' selected' : '') + '>⚡ Mở ngay</option>';
                html += '<option value="theo_lich"' + (currentMode == 'theo_lich' ? ' selected' : '') + '>📅 T7-CN</option>';
                html += '</select>';
                html += '</div>';
                // Số lần
                html += '<div style="display: flex; align-items: center; gap: 8px;">';
                html += '<label style="font-size: 0.8rem; color: #374151; width: 70px;">Số lần:</label>';
                html += '<select name="so_lan_toi_da[' + id + ']" class="form-input" style="width: 70px; padding: 4px 6px; font-size: 0.85rem;">';
                for (var i = 1; i <= 10; i++) {
                    var selected = i == exam.maxAttempts ? ' selected' : '';
                    html += '<option value="' + i + '"' + selected + '>' + i + '</option>';
                }
                html += '</select>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }

            if (Object.keys(selectedExams).length === 0) {
                html = '<div style="text-align: center; padding: 20px; color: #9CA3AF;">';
                html += '<div style="font-size: 2rem; margin-bottom: 8px;">📋</div>';
                html += '<div>Chưa chọn đề thi nào</div>';
                html += '<div style="font-size: 0.85rem; margin-top: 4px;">Click vào đề thi bên trái để chọn</div>';
                html += '</div>';
            }

            confirmList.innerHTML = html;
            document.getElementById('confirmModal').classList.add('active');
            feather.replace();
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        function showResetModal() {
            document.getElementById('resetSingleStudent').style.display = 'none';
            document.getElementById('resetAllOption').style.display = 'block';
            document.getElementById('resetForm').querySelector('[name="action"]').value = 'reset_all_week';
            document.getElementById('resetModal').classList.add('active');
        }

        function closeResetModal() {
            document.getElementById('resetModal').classList.remove('active');
        }

        function resetStudent(hocSinhId, deThiId, hoTen) {
            if (confirm('Bạn có chắc muốn reset kết quả thi của ' + hoTen + '?')) {
                document.getElementById('reset_hoc_sinh_id').value = hocSinhId;
                document.getElementById('reset_de_thi_id').value = deThiId;
                document.getElementById('resetForm').querySelector('[name="action"]').value = 'reset_result';
                document.getElementById('resetForm').submit();
            }
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // ============ TAB FUNCTIONS ============
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(function(tab) {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.closest('.tab-btn').classList.add('active');

            // Re-render feather icons
            feather.replace();
        }

        // ============ FILTER & SEARCH FUNCTIONS ============
        function filterTable() {
            var searchText = document.getElementById('searchInput').value.toLowerCase();
            var filterLop = document.getElementById('filterLop').value;
            var filterMon = document.getElementById('filterMon').value;
            var filterTuan = document.getElementById('filterTuan').value;
            var filterStatus = document.getElementById('filterStatus').value;

            var rows = document.querySelectorAll('.schedule-row');
            var visibleCount = 0;

            rows.forEach(function(row) {
                var tenDe = row.getAttribute('data-ten-de') || '';
                var tenMon = row.getAttribute('data-ten-mon') || '';
                var tenLop = row.getAttribute('data-ten-lop') || '';
                var tenTuan = row.getAttribute('data-ten-tuan') || '';
                var status = row.getAttribute('data-status') || '';

                var matchSearch = (searchText === '' ||
                    tenDe.indexOf(searchText) !== -1 ||
                    tenMon.indexOf(searchText) !== -1);
                var matchLop = (filterLop === '' || tenLop === filterLop);
                var matchMon = (filterMon === '' || tenMon.toLowerCase().indexOf(filterMon.toLowerCase()) !== -1);
                var matchTuan = (filterTuan === '' || tenTuan === filterTuan);
                var matchStatus = (filterStatus === '' || status === filterStatus);

                if (matchSearch && matchLop && matchMon && matchTuan && matchStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('visibleCount').textContent = visibleCount;
        }

        // ============ ACTION FUNCTIONS ============
        function viewSchedule(lopId, tuanId) {
            switchTab('schedule');
            document.getElementById('lopSelect').value = lopId;
            window.location.href = '?lop_id=' + lopId + '&week_id=' + tuanId;
        }

        function editSchedule(lopId, tuanId) {
            window.location.href = '?lop_id=' + lopId + '&week_id=' + tuanId;
        }

        function removeFromSchedule(deThiId, tenDe) {
            if (confirm('Bạn có chắc muốn xóa "' + tenDe + '" khỏi lịch thi chính thức?\n\nLưu ý: Đề thi sẽ chuyển về chế độ luyện tập.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="remove_from_schedule">' +
                                 '<input type="hidden" name="de_thi_id" value="' + deThiId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportToExcel() {
            var table = document.getElementById('scheduleTable');
            if (!table) {
                alert('Không có dữ liệu để xuất!');
                return;
            }

            var csv = [];
            var rows = table.querySelectorAll('tr');

            for (var i = 0; i < rows.length; i++) {
                var row = [];
                var cols = rows[i].querySelectorAll('td, th');

                for (var j = 0; j < cols.length - 1; j++) { // Bỏ cột hành động
                    var text = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }

                csv.push(row.join(','));
            }

            var csvContent = '\uFEFF' + csv.join('\n'); // UTF-8 BOM
            var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'lich_thi_chinh_thuc_' + new Date().toISOString().slice(0,10) + '.csv';
            link.click();
        }

        // Check for tab parameter in URL
        document.addEventListener('DOMContentLoaded', function() {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('tab') === 'overview') {
                document.querySelectorAll('.tab-btn')[1].click();
            }
        });
    </script>

    <!-- Form xóa khỏi lịch -->
    <form id="removeScheduleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="remove_from_schedule">
        <input type="hidden" name="de_thi_id" id="remove_de_thi_id">
    </form>
</body>
</html>
