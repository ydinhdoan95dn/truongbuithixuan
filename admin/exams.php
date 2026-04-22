<?php
/**
 * ==============================================
 * QUẢN LÝ ĐỀ THI
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $tenDe = sanitize($_POST['ten_de']);
        $moTa = sanitize($_POST['mo_ta']);
        $monHocId = intval($_POST['mon_hoc_id']);
        $lopId = intval($_POST['lop_id']);
        $soCau = intval($_POST['so_cau']);
        $thoiGianCau = intval($_POST['thoi_gian_cau']);
        $randomCauHoi = isset($_POST['random_cau_hoi']) ? 1 : 0;
        $isChinhThuc = isset($_POST['is_chinh_thuc']) ? 1 : 0;

        // GVCN chỉ được tạo đề cho lớp mình
        if (isGVCN() && $lopId != $myLopId) {
            $message = 'Bạn chỉ có thể tạo đề thi cho lớp mình!';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO de_thi (ten_de, mo_ta, mon_hoc_id, lop_id, so_cau, thoi_gian_cau, random_cau_hoi, is_chinh_thuc, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($tenDe, $moTa, $monHocId, $lopId, $soCau, $thoiGianCau, $randomCauHoi, $isChinhThuc, $admin['id']));

            $message = 'Thêm đề thi thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $tenDe = sanitize($_POST['ten_de']);
        $moTa = sanitize($_POST['mo_ta']);
        $monHocId = intval($_POST['mon_hoc_id']);
        $lopId = intval($_POST['lop_id']);
        $soCau = intval($_POST['so_cau']);
        $thoiGianCau = intval($_POST['thoi_gian_cau']);
        $trangThai = intval($_POST['trang_thai']);
        $randomCauHoi = isset($_POST['random_cau_hoi']) ? 1 : 0;
        $isChinhThuc = isset($_POST['is_chinh_thuc']) ? 1 : 0;

        // Kiểm tra quyền sửa
        $canEdit = true;
        if (isGVCN()) {
            $stmtCheck = $conn->prepare("SELECT lop_id FROM de_thi WHERE id = ?");
            $stmtCheck->execute(array($id));
            $exam = $stmtCheck->fetch();
            if ($exam['lop_id'] != $myLopId) {
                $canEdit = false;
                $message = 'Bạn không có quyền sửa đề thi này!';
                $messageType = 'error';
            }
            // GVCN không được chuyển lớp
            $lopId = $myLopId;
        }

        if ($canEdit) {
            $stmt = $conn->prepare("UPDATE de_thi SET ten_de = ?, mo_ta = ?, mon_hoc_id = ?, lop_id = ?, so_cau = ?, thoi_gian_cau = ?, random_cau_hoi = ?, is_chinh_thuc = ?, trang_thai = ? WHERE id = ?");
            $stmt->execute(array($tenDe, $moTa, $monHocId, $lopId, $soCau, $thoiGianCau, $randomCauHoi, $isChinhThuc, $trangThai, $id));

            $message = 'Cập nhật đề thi thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        // Kiểm tra quyền xóa
        $canDelete = true;
        $deleteError = '';

        // Lấy thông tin đề thi
        $stmtCheck = $conn->prepare("SELECT * FROM de_thi WHERE id = ?");
        $stmtCheck->execute(array($id));
        $exam = $stmtCheck->fetch();

        if (!$exam) {
            $canDelete = false;
            $deleteError = 'Đề thi không tồn tại!';
        }

        // Kiểm tra quyền GVCN
        if ($canDelete && isGVCN() && $exam['lop_id'] != $myLopId) {
            $canDelete = false;
            $deleteError = 'Bạn không có quyền xóa đề thi này!';
        }

        // Kiểm tra đề thi chính thức đang có lịch
        if ($canDelete && $exam['is_chinh_thuc'] == 1 && !empty($exam['tuan_id'])) {
            $canDelete = false;
            $deleteError = 'Không thể xóa! Đề thi đang được gán làm bài thi chính thức. Vui lòng xóa khỏi lịch thi trước (vào Quản lý lịch thi → Xóa khỏi lịch).';
        }

        // Kiểm tra có bài làm nào chưa hoàn thành
        if ($canDelete) {
            $stmtBaiLam = $conn->prepare("SELECT COUNT(*) as cnt FROM bai_lam WHERE de_thi_id = ? AND trang_thai = 'dang_lam'");
            $stmtBaiLam->execute(array($id));
            $bailamResult = $stmtBaiLam->fetch();
            if ($bailamResult['cnt'] > 0) {
                $canDelete = false;
                $deleteError = 'Không thể xóa! Có ' . $bailamResult['cnt'] . ' học sinh đang làm bài thi này. Vui lòng đợi họ hoàn thành.';
            }
        }

        // Kiểm tra có kết quả thi chính thức
        if ($canDelete) {
            $stmtKetQua = $conn->prepare("SELECT COUNT(*) as cnt FROM bai_lam WHERE de_thi_id = ? AND is_chinh_thuc = 1 AND trang_thai = 'hoan_thanh'");
            $stmtKetQua->execute(array($id));
            $kqResult = $stmtKetQua->fetch();
            if ($kqResult['cnt'] > 0) {
                $canDelete = false;
                $deleteError = 'Không thể xóa! Đề thi có ' . $kqResult['cnt'] . ' kết quả thi chính thức. Dữ liệu này ảnh hưởng đến xếp hạng học sinh. Nếu muốn xóa, vui lòng reset kết quả thi trước (vào Quản lý lịch thi → Reset kết quả).';
            }
        }

        if ($canDelete) {
            try {
                // Xóa các bài làm luyện tập liên quan
                $conn->prepare("DELETE FROM bai_lam WHERE de_thi_id = ? AND is_chinh_thuc = 0")->execute(array($id));

                // Xóa đề thi (câu hỏi sẽ cascade delete)
                $stmt = $conn->prepare("DELETE FROM de_thi WHERE id = ?");
                $stmt->execute(array($id));

                $message = 'Xóa đề thi thành công!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Lỗi khi xóa đề thi: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = $deleteError;
            $messageType = 'error';
        }
    }
}

// Lấy danh sách lớp (theo quyền)
if (isAdmin()) {
    $stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
    $lopList = $stmtLop->fetchAll();
} else {
    $stmtLop = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ?");
    $stmtLop->execute(array($myLopId));
    $lopList = $stmtLop->fetchAll();
}

$stmtMon = $conn->query("SELECT * FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$monList = $stmtMon->fetchAll();

// Bộ lọc loại đề thi
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';
$typeFilter = '';
if ($filterType === 'chinh_thuc') {
    $typeFilter = ' AND dt.is_chinh_thuc = 1';
} elseif ($filterType === 'luyen_thi') {
    $typeFilter = ' AND (dt.is_chinh_thuc = 0 OR dt.is_chinh_thuc IS NULL)';
}

// Query đề thi (theo quyền)
$classFilter = getClassFilterSQL('dt', false);
$stmtDT = $conn->query("
    SELECT dt.*, mh.ten_mon, lh.ten_lop,
           (SELECT COUNT(*) FROM cau_hoi ch WHERE ch.de_thi_id = dt.id) as so_cau_hoi
    FROM de_thi dt
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    JOIN lop_hoc lh ON dt.lop_id = lh.id
    WHERE {$classFilter}{$typeFilter}
    ORDER BY dt.is_chinh_thuc DESC, dt.created_at DESC
");
$deThiList = $stmtDT->fetchAll();

$pageTitle = isGVCN() ? 'Đề thi ' . $admin['ten_lop'] : 'Quản lý đề thi';
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

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
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
        .filter-tab.gold.active {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #7B4F00;
        }

        /* Type badge */
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

        <!-- Main Content -->
        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">📝 <?php echo $pageTitle; ?></h1>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i data-feather="plus"></i> Thêm đề thi
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?type=all" class="filter-tab <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                    Tất cả
                </a>
                <a href="?type=chinh_thuc" class="filter-tab gold <?php echo $filterType === 'chinh_thuc' ? 'active' : ''; ?>">
                    ⭐ Chính thức
                </a>
                <a href="?type=luyen_thi" class="filter-tab <?php echo $filterType === 'luyen_thi' ? 'active' : ''; ?>">
                    📝 Luyện thi
                </a>
            </div>

            <!-- Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F9FAFB;">
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Tên đề</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Loại</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Môn</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Lớp</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Câu hỏi</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Thời gian</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Trạng thái</th>
                            <th style="padding: 16px; text-align: right; font-weight: 600; color: #6B7280;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deThiList as $dt): ?>
                            <tr style="border-top: 1px solid #E5E7EB;">
                                <td style="padding: 16px;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($dt['ten_de']); ?></div>
                                    <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo htmlspecialchars($dt['mo_ta']); ?></div>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php if (isset($dt['is_chinh_thuc']) && $dt['is_chinh_thuc'] == 1): ?>
                                        <span class="type-badge chinh-thuc">⭐ Chính thức</span>
                                    <?php else: ?>
                                        <span class="type-badge luyen-thi">Luyện thi</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; text-align: center;"><?php echo htmlspecialchars($dt['ten_mon']); ?></td>
                                <td style="padding: 16px; text-align: center;"><?php echo htmlspecialchars($dt['ten_lop']); ?></td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="font-weight: 700; color: <?php echo $dt['so_cau_hoi'] >= $dt['so_cau'] ? '#10B981' : '#EF4444'; ?>">
                                        <?php echo $dt['so_cau_hoi']; ?>/<?php echo $dt['so_cau']; ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center;"><?php echo $dt['thoi_gian_cau']; ?>s</td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; <?php echo $dt['trang_thai'] ? 'background: rgba(16,185,129,0.1); color: #10B981;' : 'background: rgba(239,68,68,0.1); color: #EF4444;'; ?>">
                                        <?php echo $dt['trang_thai'] ? 'Mở' : 'Đóng'; ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: right;">
                                    <a href="<?php echo BASE_URL; ?>/admin/questions.php?exam_id=<?php echo $dt['id']; ?>" class="btn btn-ghost btn-sm">
                                        <i data-feather="list"></i>
                                    </a>
                                    <button class="btn btn-ghost btn-sm" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($dt)); ?>)">
                                        <i data-feather="edit-2"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteExam(<?php echo $dt['id']; ?>)">
                                        <i data-feather="trash-2"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
            <h3 class="modal-title">Thêm đề thi mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Tên đề thi</label>
                    <input type="text" name="ten_de" class="form-input" required placeholder="VD: Đề 1 - Toán lớp 3">
                </div>
                <div class="form-group">
                    <label class="form-label">Mô tả</label>
                    <input type="text" name="mo_ta" class="form-input" placeholder="Mô tả ngắn về đề thi">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Môn học</label>
                        <select name="mon_hoc_id" class="form-input" required>
                            <?php foreach ($monList as $mon): ?>
                                <option value="<?php echo $mon['id']; ?>"><?php echo htmlspecialchars($mon['ten_mon']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lớp</label>
                        <select name="lop_id" class="form-input" required>
                            <?php foreach ($lopList as $lop): ?>
                                <?php if ($lop['trang_thai']): ?>
                                    <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Số câu hỏi</label>
                        <input type="number" name="so_cau" class="form-input" value="10" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Thời gian/câu (giây)</label>
                        <input type="number" name="thoi_gian_cau" class="form-input" value="15" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="random_cau_hoi" checked>
                        <span>Random thứ tự câu hỏi</span>
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px; background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-radius: 12px; border: 2px solid #FCD34D;">
                        <input type="checkbox" name="is_chinh_thuc">
                        <span style="font-weight: 700; color: #B45309;">⭐ Đề thi CHÍNH THỨC (giới hạn số lần thi)</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Thêm đề thi</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
            <h3 class="modal-title">Sửa đề thi</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label class="form-label">Tên đề thi</label>
                    <input type="text" name="ten_de" id="edit_ten_de" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mô tả</label>
                    <input type="text" name="mo_ta" id="edit_mo_ta" class="form-input">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Môn học</label>
                        <select name="mon_hoc_id" id="edit_mon_hoc_id" class="form-input" required>
                            <?php foreach ($monList as $mon): ?>
                                <option value="<?php echo $mon['id']; ?>"><?php echo htmlspecialchars($mon['ten_mon']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lớp</label>
                        <select name="lop_id" id="edit_lop_id" class="form-input" required>
                            <?php foreach ($lopList as $lop): ?>
                                <?php if ($lop['trang_thai']): ?>
                                    <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Số câu hỏi</label>
                        <input type="number" name="so_cau" id="edit_so_cau" class="form-input" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Thời gian/câu (giây)</label>
                        <input type="number" name="thoi_gian_cau" id="edit_thoi_gian_cau" class="form-input" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select name="trang_thai" id="edit_trang_thai" class="form-input">
                        <option value="1">Mở</option>
                        <option value="0">Đóng</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="random_cau_hoi" id="edit_random_cau_hoi">
                        <span>Random thứ tự câu hỏi</span>
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 12px; background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%); border-radius: 12px; border: 2px solid #FCD34D;">
                        <input type="checkbox" name="is_chinh_thuc" id="edit_is_chinh_thuc">
                        <span style="font-weight: 700; color: #B45309;">⭐ Đề thi CHÍNH THỨC (giới hạn số lần thi)</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu thay đổi</button>
            </form>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        feather.replace();

        function showAddModal() { document.getElementById('addModal').classList.add('active'); }
        function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }

        function showEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_ten_de').value = data.ten_de;
            document.getElementById('edit_mo_ta').value = data.mo_ta;
            document.getElementById('edit_mon_hoc_id').value = data.mon_hoc_id;
            document.getElementById('edit_lop_id').value = data.lop_id;
            document.getElementById('edit_so_cau').value = data.so_cau;
            document.getElementById('edit_thoi_gian_cau').value = data.thoi_gian_cau;
            document.getElementById('edit_trang_thai').value = data.trang_thai;
            document.getElementById('edit_random_cau_hoi').checked = data.random_cau_hoi == 1;
            document.getElementById('edit_is_chinh_thuc').checked = data.is_chinh_thuc == 1;
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }

        function deleteExam(id) {
            if (confirm('Bạn có chắc muốn xóa đề thi này? Tất cả câu hỏi liên quan sẽ bị xóa!')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
