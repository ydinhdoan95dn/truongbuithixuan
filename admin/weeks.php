<?php
/**
 * ==============================================
 * QUẢN LÝ TUẦN HỌC
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/week_helper.php';

if (!isAdminLoggedIn()) {
    redirect('admin/login.php');
}

// Chỉ Admin mới có quyền quản lý tuần
if (!isAdmin()) {
    $_SESSION['error_message'] = 'Bạn không có quyền truy cập chức năng này!';
    redirect('admin/dashboard.php');
}

$conn = getDBConnection();
$message = '';
$messageType = '';

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add_semester') {
        $tenHocKy = sanitize($_POST['ten_hoc_ky']);
        $namHoc = sanitize($_POST['nam_hoc']);
        $ngayBatDau = $_POST['ngay_bat_dau'];
        $ngayKetThuc = $_POST['ngay_ket_thuc'];

        $stmt = $conn->prepare("INSERT INTO hoc_ky (ten_hoc_ky, nam_hoc, ngay_bat_dau, ngay_ket_thuc, trang_thai) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute(array($tenHocKy, $namHoc, $ngayBatDau, $ngayKetThuc));

        $message = 'Thêm học kỳ thành công!';
        $messageType = 'success';

    } elseif ($action === 'activate_semester') {
        $semesterId = intval($_POST['semester_id']);

        // Đặt tất cả về 0
        $conn->query("UPDATE hoc_ky SET trang_thai = 0");
        // Kích hoạt học kỳ được chọn
        $stmt = $conn->prepare("UPDATE hoc_ky SET trang_thai = 1 WHERE id = ?");
        $stmt->execute(array($semesterId));

        $message = 'Đã kích hoạt học kỳ!';
        $messageType = 'success';

    } elseif ($action === 'add_week') {
        $hocKyId = intval($_POST['hoc_ky_id']);
        $soTuan = intval($_POST['so_tuan']);
        $tenTuan = sanitize($_POST['ten_tuan']);
        $ngayBatDau = $_POST['ngay_bat_dau'];
        $ngayKetThuc = $_POST['ngay_ket_thuc'];

        $stmt = $conn->prepare("INSERT INTO tuan_hoc (hoc_ky_id, so_tuan, ten_tuan, ngay_bat_dau, ngay_ket_thuc, trang_thai) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute(array($hocKyId, $soTuan, $tenTuan, $ngayBatDau, $ngayKetThuc));

        $message = 'Thêm tuần học thành công!';
        $messageType = 'success';

    } elseif ($action === 'auto_generate_weeks') {
        $hocKyId = intval($_POST['hoc_ky_id']);

        // Lấy thông tin học kỳ
        $stmtHK = $conn->prepare("SELECT * FROM hoc_ky WHERE id = ?");
        $stmtHK->execute(array($hocKyId));
        $hocKy = $stmtHK->fetch();

        if ($hocKy) {
            // Xóa tuần cũ của học kỳ này
            $stmtDel = $conn->prepare("DELETE FROM tuan_hoc WHERE hoc_ky_id = ?");
            $stmtDel->execute(array($hocKyId));

            // Tạo tuần mới
            $startDate = new DateTime($hocKy['ngay_bat_dau']);
            $endDate = new DateTime($hocKy['ngay_ket_thuc']);

            // Tìm thứ 2 đầu tiên
            $dayOfWeek = $startDate->format('N'); // 1=Mon, 7=Sun
            if ($dayOfWeek != 1) {
                $startDate->modify('next monday');
            }

            // Xác định số tuần bắt đầu dựa vào tên học kỳ
            // Học kỳ 2 bắt đầu từ tuần 19
            $isHK2 = (strpos($hocKy['ten_hoc_ky'], '2') !== false);
            $weekNum = $isHK2 ? 19 : 1;
            $startWeekNum = $weekNum;

            while ($startDate < $endDate) {
                $weekStart = clone $startDate;
                $weekEnd = clone $startDate;
                $weekEnd->modify('+6 days'); // Chủ nhật

                if ($weekEnd > $endDate) {
                    $weekEnd = clone $endDate;
                }

                $tenTuan = 'Tuần ' . $weekNum;
                $stmt = $conn->prepare("INSERT INTO tuan_hoc (hoc_ky_id, so_tuan, ten_tuan, ngay_bat_dau, ngay_ket_thuc, trang_thai) VALUES (?, ?, ?, ?, ?, 0)");
                $stmt->execute(array($hocKyId, $weekNum, $tenTuan, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')));

                $startDate->modify('+7 days');
                $weekNum++;
            }

            $totalWeeks = $weekNum - $startWeekNum;
            $message = 'Đã tạo ' . $totalWeeks . ' tuần học (Tuần ' . $startWeekNum . ' - Tuần ' . ($weekNum - 1) . ')!';
            $messageType = 'success';
        }

    } elseif ($action === 'update_week_status') {
        $weekId = intval($_POST['week_id']);
        $trangThai = intval($_POST['trang_thai']);

        $stmt = $conn->prepare("UPDATE tuan_hoc SET trang_thai = ? WHERE id = ?");
        $stmt->execute(array($trangThai, $weekId));

        $message = 'Cập nhật trạng thái tuần thành công!';
        $messageType = 'success';

    } elseif ($action === 'delete_week') {
        $weekId = intval($_POST['week_id']);

        $stmt = $conn->prepare("DELETE FROM tuan_hoc WHERE id = ?");
        $stmt->execute(array($weekId));

        $message = 'Xóa tuần học thành công!';
        $messageType = 'success';

    } elseif ($action === 'delete_semester') {
        $semesterId = intval($_POST['semester_id']);

        // Xóa tuần của học kỳ trước
        $stmtDel = $conn->prepare("DELETE FROM tuan_hoc WHERE hoc_ky_id = ?");
        $stmtDel->execute(array($semesterId));

        // Xóa học kỳ
        $stmt = $conn->prepare("DELETE FROM hoc_ky WHERE id = ?");
        $stmt->execute(array($semesterId));

        $message = 'Xóa học kỳ thành công!';
        $messageType = 'success';
    }
}

// Lấy danh sách học kỳ
$stmtHK = $conn->query("SELECT * FROM hoc_ky ORDER BY nam_hoc DESC, id DESC");
$hocKyList = $stmtHK->fetchAll();

// Lấy học kỳ hiện tại hoặc học kỳ đầu tiên
$selectedSemester = isset($_GET['hk']) ? intval($_GET['hk']) : 0;
if ($selectedSemester == 0 && !empty($hocKyList)) {
    // Ưu tiên học kỳ đang active
    foreach ($hocKyList as $hk) {
        if ($hk['trang_thai'] == 1) {
            $selectedSemester = $hk['id'];
            break;
        }
    }
    if ($selectedSemester == 0) {
        $selectedSemester = $hocKyList[0]['id'];
    }
}

// Lấy tuần của học kỳ được chọn
$tuanList = array();
if ($selectedSemester > 0) {
    $stmtTuan = $conn->prepare("SELECT * FROM tuan_hoc WHERE hoc_ky_id = ? ORDER BY so_tuan ASC");
    $stmtTuan->execute(array($selectedSemester));
    $tuanList = $stmtTuan->fetchAll();
}

// Tìm tuần hiện tại
$today = date('Y-m-d');
$currentWeekId = 0;
foreach ($tuanList as $tuan) {
    if ($today >= $tuan['ngay_bat_dau'] && $today <= $tuan['ngay_ket_thuc']) {
        $currentWeekId = $tuan['id'];
        break;
    }
}

$pageTitle = 'Quản lý tuần học';
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

        .semester-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .semester-tab {
            padding: 10px 20px;
            border-radius: 12px;
            background: white;
            border: 2px solid #E5E7EB;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .semester-tab:hover {
            border-color: #667eea;
        }
        .semester-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        .semester-tab.is-active-semester {
            position: relative;
        }
        .semester-tab.is-active-semester::after {
            content: '✓';
            position: absolute;
            top: -5px;
            right: -5px;
            background: #10B981;
            color: white;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .week-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .week-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .week-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .week-card.current {
            border-color: #10B981;
            background: linear-gradient(to bottom right, rgba(16,185,129,0.05), white);
        }
        .week-card.past {
            opacity: 0.7;
        }
        .week-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .week-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1F2937;
        }
        .week-number {
            font-size: 0.75rem;
            color: #9CA3AF;
        }
        .week-date {
            font-size: 0.85rem;
            color: #6B7280;
            margin-bottom: 12px;
        }
        .week-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-0 { background: #F3F4F6; color: #6B7280; }
        .status-1 { background: rgba(16,185,129,0.1); color: #10B981; }
        .status-2 { background: rgba(107,114,128,0.1); color: #6B7280; }

        .week-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #9CA3AF;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">📅 <?php echo $pageTitle; ?></h1>
                <button class="btn btn-primary" onclick="showAddSemesterModal()">
                    <i data-feather="plus"></i> Thêm học kỳ
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Semester Tabs -->
            <div class="semester-tabs">
                <?php foreach ($hocKyList as $hk): ?>
                    <a href="?hk=<?php echo $hk['id']; ?>"
                       class="semester-tab <?php echo $selectedSemester == $hk['id'] ? 'active' : ''; ?> <?php echo $hk['trang_thai'] == 1 ? 'is-active-semester' : ''; ?>">
                        <?php echo htmlspecialchars($hk['ten_hoc_ky']); ?> - <?php echo htmlspecialchars($hk['nam_hoc']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($selectedSemester > 0): ?>
                <?php
                $selectedHK = null;
                foreach ($hocKyList as $hk) {
                    if ($hk['id'] == $selectedSemester) {
                        $selectedHK = $hk;
                        break;
                    }
                }
                ?>

                <!-- Stats -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($tuanList); ?></div>
                        <div class="stat-label">Tổng số tuần</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #10B981;">
                            <?php
                            $activeTuans = 0;
                            foreach ($tuanList as $t) {
                                if ($t['trang_thai'] == 1) $activeTuans++;
                            }
                            echo $activeTuans;
                            ?>
                        </div>
                        <div class="stat-label">Đang diễn ra</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" style="color: #6B7280;">
                            <?php
                            $pastTuans = 0;
                            foreach ($tuanList as $t) {
                                if ($t['trang_thai'] == 2) $pastTuans++;
                            }
                            echo $pastTuans;
                            ?>
                        </div>
                        <div class="stat-label">Đã kết thúc</div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <button class="btn btn-secondary" onclick="showAddWeekModal()">
                        <i data-feather="plus"></i> Thêm tuần
                    </button>
                    <button class="btn btn-secondary" onclick="autoGenerateWeeks()">
                        <i data-feather="zap"></i> Tự động tạo tuần
                    </button>
                    <?php if ($selectedHK && $selectedHK['trang_thai'] != 1): ?>
                        <button class="btn btn-success" onclick="activateSemester(<?php echo $selectedSemester; ?>)">
                            <i data-feather="check-circle"></i> Kích hoạt học kỳ này
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-ghost" style="color: #EF4444;" onclick="deleteSemester(<?php echo $selectedSemester; ?>)">
                        <i data-feather="trash-2"></i> Xóa học kỳ
                    </button>
                </div>

                <!-- Week Grid -->
                <?php if (empty($tuanList)): ?>
                    <div class="card" style="text-align: center; padding: 48px;">
                        <div style="font-size: 4rem; margin-bottom: 16px;">📅</div>
                        <p style="color: #6B7280; margin-bottom: 16px;">Chưa có tuần học nào trong học kỳ này</p>
                        <button class="btn btn-primary" onclick="autoGenerateWeeks()">
                            <i data-feather="zap"></i> Tự động tạo tuần
                        </button>
                    </div>
                <?php else: ?>
                    <div class="week-grid">
                        <?php foreach ($tuanList as $tuan): ?>
                            <?php
                            $isCurrent = $tuan['id'] == $currentWeekId;
                            $isPast = strtotime($tuan['ngay_ket_thuc']) < strtotime($today);
                            $cardClass = $isCurrent ? 'current' : ($isPast ? 'past' : '');

                            $statusText = array(0 => 'Chưa bắt đầu', 1 => 'Đang diễn ra', 2 => 'Đã kết thúc');
                            ?>
                            <div class="week-card <?php echo $cardClass; ?>">
                                <div class="week-header">
                                    <div>
                                        <div class="week-title">
                                            <?php if ($isCurrent): ?>
                                                <span style="color: #10B981;">●</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($tuan['ten_tuan']); ?>
                                        </div>
                                        <div class="week-number">Tuần thứ <?php echo $tuan['so_tuan']; ?></div>
                                    </div>
                                    <span class="week-status status-<?php echo $tuan['trang_thai']; ?>">
                                        <?php echo $statusText[$tuan['trang_thai']]; ?>
                                    </span>
                                </div>

                                <div class="week-date">
                                    📆 <?php echo date('d/m', strtotime($tuan['ngay_bat_dau'])); ?> - <?php echo date('d/m/Y', strtotime($tuan['ngay_ket_thuc'])); ?>
                                </div>

                                <div class="week-actions">
                                    <select class="form-input" style="flex: 1; padding: 8px 12px; font-size: 0.85rem;"
                                            onchange="updateWeekStatus(<?php echo $tuan['id']; ?>, this.value)">
                                        <option value="0" <?php echo $tuan['trang_thai'] == 0 ? 'selected' : ''; ?>>Chưa bắt đầu</option>
                                        <option value="1" <?php echo $tuan['trang_thai'] == 1 ? 'selected' : ''; ?>>Đang diễn ra</option>
                                        <option value="2" <?php echo $tuan['trang_thai'] == 2 ? 'selected' : ''; ?>>Đã kết thúc</option>
                                    </select>
                                    <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteWeek(<?php echo $tuan['id']; ?>)">
                                        <i data-feather="trash-2"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: 48px;">
                    <div style="font-size: 4rem; margin-bottom: 16px;">📚</div>
                    <p style="color: #6B7280; margin-bottom: 16px;">Chưa có học kỳ nào</p>
                    <button class="btn btn-primary" onclick="showAddSemesterModal()">
                        <i data-feather="plus"></i> Thêm học kỳ đầu tiên
                    </button>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Semester Modal -->
    <div id="addSemesterModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeAddSemesterModal()">&times;</button>
            <h3 class="modal-title">Thêm học kỳ mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_semester">
                <div class="form-group">
                    <label class="form-label">Tên học kỳ</label>
                    <select name="ten_hoc_ky" class="form-input" required>
                        <option value="Học kỳ 1">Học kỳ 1</option>
                        <option value="Học kỳ 2">Học kỳ 2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Năm học</label>
                    <input type="text" name="nam_hoc" id="semester_nam_hoc" class="form-input" required placeholder="VD: 2024-2025"
                           value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Ngày bắt đầu</label>
                        <input type="date" name="ngay_bat_dau" id="semester_ngay_bat_dau" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ngày kết thúc</label>
                        <input type="date" name="ngay_ket_thuc" id="semester_ngay_ket_thuc" class="form-input" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Thêm học kỳ</button>
            </form>
        </div>
    </div>

    <!-- Add Week Modal -->
    <?php
    // Tính số tuần tiếp theo dựa vào học kỳ
    $nextWeekNum = count($tuanList) + 1;
    if ($selectedHK && strpos($selectedHK['ten_hoc_ky'], '2') !== false) {
        // Học kỳ 2: nếu chưa có tuần nào thì bắt đầu từ 19, còn không thì lấy số tuần lớn nhất + 1
        if (count($tuanList) == 0) {
            $nextWeekNum = 19;
        } else {
            $maxWeek = 0;
            foreach ($tuanList as $t) {
                if ($t['so_tuan'] > $maxWeek) $maxWeek = $t['so_tuan'];
            }
            $nextWeekNum = $maxWeek + 1;
        }
    }
    ?>
    <div id="addWeekModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeAddWeekModal()">&times;</button>
            <h3 class="modal-title">Thêm tuần học</h3>
            <?php if ($selectedHK && strpos($selectedHK['ten_hoc_ky'], '2') !== false): ?>
            <div style="background: #FEF3C7; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 0.9rem; color: #92400E;">
                ℹ️ Học kỳ 2 bắt đầu từ tuần 19 (tiếp nối từ học kỳ 1)
            </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="add_week">
                <input type="hidden" name="hoc_ky_id" value="<?php echo $selectedSemester; ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Số tuần</label>
                        <input type="number" name="so_tuan" id="add_so_tuan" class="form-input" required min="1"
                               value="<?php echo $nextWeekNum; ?>" onchange="updateTenTuan()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tên tuần</label>
                        <input type="text" name="ten_tuan" id="add_ten_tuan" class="form-input" required
                               value="Tuần <?php echo $nextWeekNum; ?>">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Ngày bắt đầu (Thứ 2)</label>
                        <input type="date" name="ngay_bat_dau" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ngày kết thúc (Chủ nhật)</label>
                        <input type="date" name="ngay_ket_thuc" class="form-input" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Thêm tuần</button>
            </form>
        </div>
    </div>

    <!-- Hidden Forms -->
    <form id="autoGenerateForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="auto_generate_weeks">
        <input type="hidden" name="hoc_ky_id" value="<?php echo $selectedSemester; ?>">
    </form>

    <form id="updateWeekStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_week_status">
        <input type="hidden" name="week_id" id="update_week_id">
        <input type="hidden" name="trang_thai" id="update_trang_thai">
    </form>

    <form id="deleteWeekForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_week">
        <input type="hidden" name="week_id" id="delete_week_id">
    </form>

    <form id="deleteSemesterForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_semester">
        <input type="hidden" name="semester_id" id="delete_semester_id">
    </form>

    <form id="activateSemesterForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="activate_semester">
        <input type="hidden" name="semester_id" id="activate_semester_id">
    </form>

    <script>
        feather.replace();

        function showAddSemesterModal() { document.getElementById('addSemesterModal').classList.add('active'); }
        function closeAddSemesterModal() { document.getElementById('addSemesterModal').classList.remove('active'); }

        function showAddWeekModal() { document.getElementById('addWeekModal').classList.add('active'); }
        function closeAddWeekModal() { document.getElementById('addWeekModal').classList.remove('active'); }

        function autoGenerateWeeks() {
            if (confirm('Thao tác này sẽ xóa tất cả tuần hiện tại và tạo lại từ đầu. Bạn có chắc chắn?')) {
                document.getElementById('autoGenerateForm').submit();
            }
        }

        function updateWeekStatus(weekId, status) {
            document.getElementById('update_week_id').value = weekId;
            document.getElementById('update_trang_thai').value = status;
            document.getElementById('updateWeekStatusForm').submit();
        }

        function deleteWeek(weekId) {
            if (confirm('Bạn có chắc muốn xóa tuần học này?')) {
                document.getElementById('delete_week_id').value = weekId;
                document.getElementById('deleteWeekForm').submit();
            }
        }

        function deleteSemester(semesterId) {
            if (confirm('Bạn có chắc muốn xóa học kỳ này? Tất cả tuần học liên quan sẽ bị xóa!')) {
                document.getElementById('delete_semester_id').value = semesterId;
                document.getElementById('deleteSemesterForm').submit();
            }
        }

        function activateSemester(semesterId) {
            if (confirm('Kích hoạt học kỳ này? Các học kỳ khác sẽ bị vô hiệu hóa.')) {
                document.getElementById('activate_semester_id').value = semesterId;
                document.getElementById('activateSemesterForm').submit();
            }
        }

        function updateTenTuan() {
            var soTuan = document.getElementById('add_so_tuan').value;
            document.getElementById('add_ten_tuan').value = 'Tuần ' + soTuan;
        }

        // Auto-fill dates when selecting semester type
        function updateSemesterDates() {
            var tenHK = document.querySelector('select[name="ten_hoc_ky"]').value;
            var namHoc = document.getElementById('semester_nam_hoc').value;
            var years = namHoc.split('-');
            var startYear = parseInt(years[0]) || new Date().getFullYear();
            var endYear = parseInt(years[1]) || startYear + 1;

            if (tenHK === 'Học kỳ 1') {
                // HK1: Tháng 9 năm đầu - Tháng 1 năm sau
                document.getElementById('semester_ngay_bat_dau').value = startYear + '-09-01';
                document.getElementById('semester_ngay_ket_thuc').value = endYear + '-01-15';
            } else {
                // HK2: Tháng 1 - Tháng 5 năm sau
                document.getElementById('semester_ngay_bat_dau').value = endYear + '-01-20';
                document.getElementById('semester_ngay_ket_thuc').value = endYear + '-05-31';
            }
        }

        // Attach events
        document.querySelector('select[name="ten_hoc_ky"]').addEventListener('change', updateSemesterDates);
        document.getElementById('semester_nam_hoc').addEventListener('change', updateSemesterDates);

        // Initial call to set default dates
        updateSemesterDates();
    </script>
</body>
</html>
