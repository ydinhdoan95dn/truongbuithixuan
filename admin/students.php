<?php
/**
 * ==============================================
 * QUẢN LÝ HỌC SINH
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
$lopId = getAdminLopId();
$conn = getDBConnection();

// Xử lý thêm/sửa học sinh
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $maHS = sanitize($_POST['ma_hs']);
        $hoTen = sanitize($_POST['ho_ten']);
        $newLopId = intval($_POST['lop_id']);
        $password = $_POST['password'];
        $gioiTinh = intval($_POST['gioi_tinh']);

        // GVCN chỉ được thêm học sinh vào lớp mình
        if (isGVCN() && $newLopId != $lopId) {
            $message = 'Bạn chỉ có thể thêm học sinh vào lớp mình phụ trách!';
            $messageType = 'error';
        } else {
            // Kiểm tra mã HS trùng
            $stmtCheck = $conn->prepare("SELECT id FROM hoc_sinh WHERE ma_hs = ?");
            $stmtCheck->execute(array($maHS));
            if ($stmtCheck->fetch()) {
                $message = 'Mã học sinh đã tồn tại!';
                $messageType = 'error';
            } else {
                $hashedPassword = hashPassword($password);
                $stmt = $conn->prepare("INSERT INTO hoc_sinh (ma_hs, password, ho_ten, lop_id, gioi_tinh) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute(array($maHS, $hashedPassword, $hoTen, $newLopId, $gioiTinh));

                $message = 'Thêm học sinh thành công!';
                $messageType = 'success';
            }
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $hoTen = sanitize($_POST['ho_ten']);
        $newLopId = intval($_POST['lop_id']);
        $gioiTinh = intval($_POST['gioi_tinh']);
        $trangThai = intval($_POST['trang_thai']);

        // Kiểm tra quyền sửa
        if (isGVCN()) {
            $stmtCheck = $conn->prepare("SELECT lop_id FROM hoc_sinh WHERE id = ?");
            $stmtCheck->execute(array($id));
            $oldStudent = $stmtCheck->fetch();
            if ($oldStudent['lop_id'] != $lopId) {
                $message = 'Bạn không có quyền sửa học sinh này!';
                $messageType = 'error';
            }
        }

        if (empty($message)) {
            // GVCN không được chuyển học sinh sang lớp khác
            if (isGVCN() && $newLopId != $lopId) {
                $newLopId = $lopId;
            }
            $stmt = $conn->prepare("UPDATE hoc_sinh SET ho_ten = ?, lop_id = ?, gioi_tinh = ?, trang_thai = ? WHERE id = ?");
            $stmt->execute(array($hoTen, $newLopId, $gioiTinh, $trangThai, $id));

            // Reset password nếu có
            if (!empty($_POST['new_password'])) {
                $hashedPassword = hashPassword($_POST['new_password']);
                $stmt = $conn->prepare("UPDATE hoc_sinh SET password = ? WHERE id = ?");
                $stmt->execute(array($hashedPassword, $id));
            }

            $message = 'Cập nhật thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        // Kiểm tra quyền xóa
        $canDelete = true;
        if (isGVCN()) {
            $stmtCheck = $conn->prepare("SELECT lop_id FROM hoc_sinh WHERE id = ?");
            $stmtCheck->execute(array($id));
            $student = $stmtCheck->fetch();
            if ($student['lop_id'] != $lopId) {
                $canDelete = false;
                $message = 'Bạn không có quyền xóa học sinh này!';
                $messageType = 'error';
            }
        }

        if ($canDelete) {
            $stmt = $conn->prepare("DELETE FROM hoc_sinh WHERE id = ?");
            $stmt->execute(array($id));

            $message = 'Xóa học sinh thành công!';
            $messageType = 'success';
        }
    }
}

// Lấy danh sách lớp (theo quyền)
if (isAdmin()) {
    $stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
    $lopList = $stmtLop->fetchAll();
} else {
    // GVCN chỉ thấy lớp mình
    $stmtLop = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ?");
    $stmtLop->execute(array($lopId));
    $lopList = $stmtLop->fetchAll();
}

// Filter
$filterLop = isset($_GET['lop']) ? intval($_GET['lop']) : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// GVCN bắt buộc lọc theo lớp mình
if (isGVCN()) {
    $filterLop = $lopId;
}

// Query học sinh
$where = "WHERE 1=1";
if ($filterLop > 0) {
    $where .= " AND hs.lop_id = " . intval($filterLop);
} elseif (isGVCN()) {
    $where .= " AND hs.lop_id = " . intval($lopId);
}
if ($search) {
    $where .= " AND (hs.ma_hs LIKE '%" . $conn->quote($search) . "%' OR hs.ho_ten LIKE '%" . $conn->quote($search) . "%')";
}

$stmtHS = $conn->query("
    SELECT hs.*, lh.ten_lop
    FROM hoc_sinh hs
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    {$where}
    ORDER BY lh.thu_tu, hs.ho_ten
");
$hocSinhList = $stmtHS->fetchAll();
?>
<?php $pageTitle = isGVCN() ? 'Học sinh ' . $admin['ten_lop'] : 'Quản lý học sinh'; ?>
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
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">👨‍🎓 <?php echo $pageTitle; ?></h1>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i data-feather="plus"></i> Thêm học sinh
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card" style="margin-bottom: 24px; padding: 16px;">
                <form method="GET" style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <?php if (isAdmin()): ?>
                    <select name="lop" class="form-input" style="width: auto;">
                        <option value="">Tất cả lớp</option>
                        <?php foreach ($lopList as $lop): ?>
                            <option value="<?php echo $lop['id']; ?>" <?php echo $filterLop == $lop['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lop['ten_lop']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <input type="text" name="search" class="form-input" placeholder="Tìm kiếm..." value="<?php echo htmlspecialchars($search); ?>" style="width: auto; flex: 1; min-width: 200px;">
                    <button type="submit" class="btn btn-secondary">Tìm</button>
                </form>
            </div>

            <!-- Table -->
            <div class="card" style="padding: 0; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F9FAFB;">
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Mã HS</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Họ tên</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Lớp</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Giới tính</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Trạng thái</th>
                            <th style="padding: 16px; text-align: right; font-weight: 600; color: #6B7280;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hocSinhList as $hs): ?>
                            <tr style="border-top: 1px solid #E5E7EB;">
                                <td style="padding: 16px; font-weight: 600;"><?php echo htmlspecialchars($hs['ma_hs']); ?></td>
                                <td style="padding: 16px;"><?php echo htmlspecialchars($hs['ho_ten']); ?></td>
                                <td style="padding: 16px; text-align: center;"><?php echo htmlspecialchars($hs['ten_lop']); ?></td>
                                <td style="padding: 16px; text-align: center;"><?php echo $hs['gioi_tinh'] ? 'Nam' : 'Nữ'; ?></td>
                                <td style="padding: 16px; text-align: center;">
                                    <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; <?php echo $hs['trang_thai'] ? 'background: rgba(16,185,129,0.1); color: #10B981;' : 'background: rgba(239,68,68,0.1); color: #EF4444;'; ?>">
                                        <?php echo $hs['trang_thai'] ? 'Hoạt động' : 'Khóa'; ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: right;">
                                    <button class="btn btn-ghost btn-sm" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($hs)); ?>)">
                                        <i data-feather="edit-2"></i>
                                    </button>
                                    <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteStudent(<?php echo $hs['id']; ?>, '<?php echo htmlspecialchars($hs['ho_ten']); ?>')">
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
            <h3 class="modal-title">Thêm học sinh mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Mã học sinh</label>
                    <input type="text" name="ma_hs" class="form-input" required placeholder="VD: HS3006">
                </div>
                <div class="form-group">
                    <label class="form-label">Họ tên</label>
                    <input type="text" name="ho_ten" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Lớp</label>
                    <select name="lop_id" class="form-input" required>
                        <?php foreach ($lopList as $lop): ?>
                            <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Giới tính</label>
                    <select name="gioi_tinh" class="form-input">
                        <option value="1">Nam</option>
                        <option value="0">Nữ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Thêm học sinh</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
            <h3 class="modal-title">Sửa thông tin học sinh</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label class="form-label">Mã học sinh</label>
                    <input type="text" id="edit_ma_hs" class="form-input" disabled>
                </div>
                <div class="form-group">
                    <label class="form-label">Họ tên</label>
                    <input type="text" name="ho_ten" id="edit_ho_ten" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Lớp</label>
                    <select name="lop_id" id="edit_lop_id" class="form-input" required>
                        <?php foreach ($lopList as $lop): ?>
                            <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Giới tính</label>
                    <select name="gioi_tinh" id="edit_gioi_tinh" class="form-input">
                        <option value="1">Nam</option>
                        <option value="0">Nữ</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Trạng thái</label>
                    <select name="trang_thai" id="edit_trang_thai" class="form-input">
                        <option value="1">Hoạt động</option>
                        <option value="0">Khóa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                    <input type="password" name="new_password" class="form-input">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu thay đổi</button>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        feather.replace();

        function showAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }
        function showEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_ma_hs').value = data.ma_hs;
            document.getElementById('edit_ho_ten').value = data.ho_ten;
            document.getElementById('edit_lop_id').value = data.lop_id;
            document.getElementById('edit_gioi_tinh').value = data.gioi_tinh;
            document.getElementById('edit_trang_thai').value = data.trang_thai;
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        function deleteStudent(id, name) {
            if (confirm('Bạn có chắc muốn xóa học sinh "' + name + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
