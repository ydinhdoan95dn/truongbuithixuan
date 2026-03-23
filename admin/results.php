<?php
/**
 * ==============================================
 * KẾT QUẢ THI
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

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

$message = '';
$messageType = '';

// Xử lý xóa kết quả thi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete') {
        $bailamId = intval($_POST['id']);

        // Kiểm tra quyền xóa
        $canDelete = true;
        $stmtCheck = $conn->prepare("
            SELECT bl.*, hs.lop_id, hs.ho_ten, dt.ten_de
            FROM bai_lam bl
            JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
            JOIN de_thi dt ON bl.de_thi_id = dt.id
            WHERE bl.id = ?
        ");
        $stmtCheck->execute(array($bailamId));
        $bailam = $stmtCheck->fetch();

        if (!$bailam) {
            $message = 'Không tìm thấy bài làm!';
            $messageType = 'error';
            $canDelete = false;
        } elseif (isGVCN() && $bailam['lop_id'] != $myLopId) {
            $message = 'Bạn không có quyền xóa kết quả này!';
            $messageType = 'error';
            $canDelete = false;
        }

        if ($canDelete) {
            // Xóa chi tiết bài làm trước
            $stmtDelDetail = $conn->prepare("DELETE FROM chi_tiet_bai_lam WHERE bai_lam_id = ?");
            $stmtDelDetail->execute(array($bailamId));

            // Xóa bài làm
            $stmtDel = $conn->prepare("DELETE FROM bai_lam WHERE id = ?");
            $stmtDel->execute(array($bailamId));

            // Cập nhật lại bảng ket_qua_tuan nếu có
            if ($bailam['tuan_id']) {
                $stmtUpdateKQ = $conn->prepare("
                    UPDATE ket_qua_tuan
                    SET so_lan_thi = so_lan_thi - 1
                    WHERE hoc_sinh_id = ? AND de_thi_id = ? AND tuan_id = ?
                ");
                $stmtUpdateKQ->execute(array($bailam['hoc_sinh_id'], $bailam['de_thi_id'], $bailam['tuan_id']));

                // Xóa record nếu so_lan_thi = 0
                $stmtCleanKQ = $conn->prepare("
                    DELETE FROM ket_qua_tuan WHERE hoc_sinh_id = ? AND de_thi_id = ? AND tuan_id = ? AND so_lan_thi <= 0
                ");
                $stmtCleanKQ->execute(array($bailam['hoc_sinh_id'], $bailam['de_thi_id'], $bailam['tuan_id']));
            }

            // Log hoạt động
            logActivity('admin', $admin['id'], 'Xóa kết quả thi',
                'Xóa kết quả: ' . $bailam['ho_ten'] . ' - ' . $bailam['ten_de'] . ' (Điểm: ' . $bailam['diem'] . ')');

            $message = 'Đã xóa kết quả thi của ' . $bailam['ho_ten'] . ' - ' . $bailam['ten_de'] . '. Học sinh có thể thi lại!';
            $messageType = 'success';
        }
    }
}

// Bộ lọc loại bài thi (chinh_thuc / luyen_thi / all)
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';

// Xây dựng điều kiện lọc loại bài thi
$typeFilter = '';
$typeFilterStats = '';
if ($filterType === 'chinh_thuc') {
    $typeFilter = ' AND bl.is_chinh_thuc = 1';
    $typeFilterStats = ' AND is_chinh_thuc = 1';
} elseif ($filterType === 'luyen_thi') {
    $typeFilter = ' AND (bl.is_chinh_thuc = 0 OR bl.is_chinh_thuc IS NULL)';
    $typeFilterStats = ' AND (is_chinh_thuc = 0 OR is_chinh_thuc IS NULL)';
}

// Lấy kết quả thi (theo quyền)
$classFilter = getClassFilterSQL('hs', false);
$stmtBL = $conn->query("
    SELECT bl.*, hs.ho_ten, hs.ma_hs, hs.lop_id, dt.ten_de, mh.ten_mon, lh.ten_lop
    FROM bai_lam bl
    JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
    JOIN de_thi dt ON bl.de_thi_id = dt.id
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    WHERE bl.trang_thai = 'hoan_thanh' AND {$classFilter}{$typeFilter}
    ORDER BY bl.thoi_gian_ket_thuc DESC
    LIMIT 100
");
$bailamList = $stmtBL->fetchAll();

// Thống kê (theo quyền và loại bài thi)
if (isAdmin()) {
    $stmtStats = $conn->query("
        SELECT
            COUNT(*) as tong_bai,
            AVG(diem) as diem_tb,
            MAX(diem) as diem_cao_nhat,
            MIN(diem) as diem_thap_nhat
        FROM bai_lam
        WHERE trang_thai = 'hoan_thanh'{$typeFilterStats}
    ");
} else {
    $stmtStats = $conn->prepare("
        SELECT
            COUNT(*) as tong_bai,
            AVG(bl.diem) as diem_tb,
            MAX(bl.diem) as diem_cao_nhat,
            MIN(bl.diem) as diem_thap_nhat
        FROM bai_lam bl
        JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
        WHERE bl.trang_thai = 'hoan_thanh' AND hs.lop_id = ?{$typeFilter}
    ");
    $stmtStats->execute(array($myLopId));
}
$stats = $stmtStats->fetch();

$pageTitle = isGVCN() ? 'Kết quả thi ' . $admin['ten_lop'] : 'Kết quả thi';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .stat-label { color: #6B7280; font-size: 0.875rem; margin-top: 4px; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #EF4444; }

        .btn-delete {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-delete:hover {
            background: #EF4444;
            color: white;
        }

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            background: white;
            padding: 8px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .filter-tab {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            color: #6B7280;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-tab:hover {
            background: #F3F4F6;
            color: #374151;
        }
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .filter-tab .count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        .filter-tab.active .count {
            background: rgba(255,255,255,0.2);
        }

        /* Badge loại bài thi */
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .type-badge.chinh-thuc {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            color: #B45309;
            border: 1px solid #FCD34D;
        }
        .type-badge.luyen-thi {
            background: #F3F4F6;
            color: #6B7280;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 24px;">📊 <?php echo $pageTitle; ?></h1>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo $messageType === 'success' ? '✅' : '❌'; ?> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?type=all" class="filter-tab <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                    Tất cả
                </a>
                <a href="?type=chinh_thuc" class="filter-tab <?php echo $filterType === 'chinh_thuc' ? 'active' : ''; ?>">
                    ⭐ Chính thức
                </a>
                <a href="?type=luyen_thi" class="filter-tab <?php echo $filterType === 'luyen_thi' ? 'active' : ''; ?>">
                    📝 Luyện thi
                </a>
            </div>

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-value" style="color: #667eea;"><?php echo $stats['tong_bai']; ?></div>
                    <div class="stat-label">Tổng bài thi</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #4ECDC4;"><?php echo number_format($stats['diem_tb'], 1); ?></div>
                    <div class="stat-label">Điểm trung bình</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #10B981;"><?php echo number_format($stats['diem_cao_nhat'], 1); ?></div>
                    <div class="stat-label">Điểm cao nhất</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #EF4444;"><?php echo number_format($stats['diem_thap_nhat'], 1); ?></div>
                    <div class="stat-label">Điểm thấp nhất</div>
                </div>
            </div>

            <!-- Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F9FAFB;">
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Học sinh</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Đề thi</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Loại</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Kết quả</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Điểm</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Thời gian</th>
                            <th style="padding: 16px; text-align: right; font-weight: 600; color: #6B7280;">Ngày thi</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bailamList as $bl): ?>
                            <?php $eval = evaluateResult($bl['diem']); ?>
                            <tr style="border-top: 1px solid #E5E7EB;">
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($bl['ho_ten']); ?></div>
                                    <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo $bl['ma_hs']; ?> - <?php echo $bl['ten_lop']; ?></div>
                                </td>
                                <td style="padding: 16px;">
                                    <div><?php echo htmlspecialchars($bl['ten_de']); ?></div>
                                    <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo $bl['ten_mon']; ?></div>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php if (isset($bl['is_chinh_thuc']) && $bl['is_chinh_thuc'] == 1): ?>
                                        <span class="type-badge chinh-thuc">⭐ Chính thức</span>
                                    <?php else: ?>
                                        <span class="type-badge luyen-thi">Luyện thi</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #F3F4F6;">
                                        <?php echo $eval['icon']; ?> <?php echo $bl['so_cau_dung']; ?>/<?php echo $bl['tong_cau']; ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center; font-weight: 700; font-size: 1.25rem; color: #FF6B6B;">
                                    <?php echo number_format($bl['diem'], 1); ?>
                                </td>
                                <td style="padding: 16px; text-align: center; color: #6B7280;">
                                    <?php echo formatTime($bl['tong_thoi_gian']); ?>
                                </td>
                                <td style="padding: 16px; text-align: right; color: #9CA3AF; font-size: 0.875rem;">
                                    <?php echo formatDateVN($bl['thoi_gian_ket_thuc'], 'd/m/Y H:i'); ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <button type="button" class="btn-delete" onclick="confirmDelete(<?php echo $bl['id']; ?>, '<?php echo addslashes($bl['ho_ten']); ?>', '<?php echo addslashes($bl['ten_de']); ?>', <?php echo $bl['diem']; ?>)">
                                        <i data-feather="trash-2" style="width: 14px; height: 14px;"></i> Xóa
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Hidden Delete Form -->
    <form id="deleteForm" method="POST" action="?type=<?php echo htmlspecialchars($filterType); ?>" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
    feather.replace();

    function confirmDelete(id, hoTen, tenDe, diem) {
        var message = 'Bạn có chắc muốn XÓA kết quả thi này?\n\n';
        message += '👤 Học sinh: ' + hoTen + '\n';
        message += '📝 Đề thi: ' + tenDe + '\n';
        message += '💯 Điểm: ' + diem + '\n\n';
        message += '⚠️ Sau khi xóa, học sinh có thể thi lại đề này!';

        if (confirm(message)) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }
    </script>
</body>
</html>
