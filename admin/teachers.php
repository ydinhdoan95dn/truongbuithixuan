<?php
/**
 * ==============================================
 * QUẢN LÝ GIÁO VIÊN - CHỈ ADMIN
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    redirect('admin/login.php');
}

// Chỉ admin mới được truy cập
requireAdmin();

$admin = getCurrentAdminFull();
$conn = getDBConnection();

$message = '';
$messageType = '';

// Lấy danh sách lớp
$stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$lopList = $stmtLop->fetchAll();

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $hoTen = sanitize($_POST['ho_ten']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $lopId = !empty($_POST['lop_id']) ? intval($_POST['lop_id']) : null;

        // Kiểm tra username tồn tại
        $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $stmt->execute(array($username));
        if ($stmt->fetch()) {
            $message = 'Tên đăng nhập đã tồn tại!';
            $messageType = 'error';
        } else {
            $hashedPassword = hashPassword($password);
            $stmt = $conn->prepare("INSERT INTO admins (username, password, ho_ten, email, role, lop_id, trang_thai) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute(array($username, $hashedPassword, $hoTen, $email, $role, $lopId));

            $message = 'Thêm giáo viên thành công!';
            $messageType = 'success';
            logActivity('admin', $admin['id'], 'Thêm giáo viên', 'Thêm: ' . $hoTen);
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $hoTen = sanitize($_POST['ho_ten']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $lopId = !empty($_POST['lop_id']) ? intval($_POST['lop_id']) : null;
        $trangThai = isset($_POST['trang_thai']) ? 1 : 0;

        // Cập nhật
        $sql = "UPDATE admins SET ho_ten = ?, email = ?, role = ?, lop_id = ?, trang_thai = ?";
        $params = array($hoTen, $email, $role, $lopId, $trangThai);

        // Nếu có nhập password mới
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = hashPassword($_POST['password']);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $message = 'Cập nhật thành công!';
        $messageType = 'success';
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        // Không cho xóa admin chính
        if ($id == 1) {
            $message = 'Không thể xóa tài khoản Admin chính!';
            $messageType = 'error';
        } else {
            $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute(array($id));
            $message = 'Xóa giáo viên thành công!';
            $messageType = 'success';
        }
    }
}

// Lấy danh sách giáo viên
$stmtGV = $conn->query("
    SELECT a.*, lh.ten_lop
    FROM admins a
    LEFT JOIN lop_hoc lh ON a.lop_id = lh.id
    ORDER BY a.role, a.ho_ten
");
$giaoVienList = $stmtGV->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý giáo viên - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .role-admin { background: rgba(239,68,68,0.1); color: #EF4444; }
        .role-gvcn { background: rgba(59,130,246,0.1); color: #3B82F6; }
        .role-gvbm { background: rgba(16,185,129,0.1); color: #10B981; }
        .status-active { color: #10B981; }
        .status-inactive { color: #EF4444; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">👨‍🏫 Quản lý giáo viên</h1>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i data-feather="plus"></i> Thêm giáo viên
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: 20px; padding: 16px; border-radius: 12px; background: <?php echo $messageType === 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; color: <?php echo $messageType === 'success' ? '#10B981' : '#EF4444'; ?>;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #EF4444;">
                        <?php echo count(array_filter($giaoVienList, function($g) { return $g['role'] === 'admin'; })); ?>
                    </div>
                    <div style="color: #6B7280;">Quản trị viên</div>
                </div>
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #3B82F6;">
                        <?php echo count(array_filter($giaoVienList, function($g) { return $g['role'] === 'gvcn'; })); ?>
                    </div>
                    <div style="color: #6B7280;">GV Chủ nhiệm</div>
                </div>
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #10B981;">
                        <?php echo count(array_filter($giaoVienList, function($g) { return $g['role'] === 'gvbm'; })); ?>
                    </div>
                    <div style="color: #6B7280;">GV Bộ môn</div>
                </div>
            </div>

            <!-- Table -->
            <div class="card" style="padding: 0; overflow: hidden; background: white; border-radius: 16px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #F9FAFB;">
                            <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Giáo viên</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Vai trò</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Lớp phụ trách</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Trạng thái</th>
                            <th style="padding: 16px; text-align: right; font-weight: 600; color: #6B7280;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($giaoVienList as $gv): ?>
                            <tr style="border-top: 1px solid #E5E7EB;">
                                <td style="padding: 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700;">
                                            <?php echo mb_substr($gv['ho_ten'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($gv['ho_ten']); ?></div>
                                            <div style="font-size: 0.75rem; color: #9CA3AF;">@<?php echo htmlspecialchars($gv['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php
                                    $roleClass = 'role-' . $gv['role'];
                                    $roleName = getRoleName($gv['role']);
                                    ?>
                                    <span class="role-badge <?php echo $roleClass; ?>"><?php echo $roleName; ?></span>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php echo $gv['ten_lop'] ? htmlspecialchars($gv['ten_lop']) : '<span style="color: #9CA3AF;">-</span>'; ?>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <?php if ($gv['trang_thai'] == 1): ?>
                                        <span class="status-active">✓ Hoạt động</span>
                                    <?php else: ?>
                                        <span class="status-inactive">✗ Khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px; text-align: right;">
                                    <button class="btn btn-ghost btn-sm" onclick='editTeacher(<?php echo json_encode($gv); ?>)' title="Sửa">
                                        <i data-feather="edit-2"></i>
                                    </button>
                                    <?php if ($gv['id'] != 1): ?>
                                    <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteTeacher(<?php echo $gv['id']; ?>, '<?php echo addslashes($gv['ho_ten']); ?>')" title="Xóa">
                                        <i data-feather="trash-2"></i>
                                    </button>
                                    <?php endif; ?>
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
        <div class="modal-content" style="max-width: 500px;">
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
            <h3 class="modal-title">Thêm giáo viên mới</h3>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">Tên đăng nhập *</label>
                    <input type="text" name="username" class="form-input" required placeholder="vd: gvcn_lop3">
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu *</label>
                    <input type="password" name="password" class="form-input" required placeholder="Tối thiểu 6 ký tự">
                </div>

                <div class="form-group">
                    <label class="form-label">Họ và tên *</label>
                    <input type="text" name="ho_ten" class="form-input" required placeholder="Nguyễn Văn A">
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="email@truongbuithixuan.edu.vn">
                </div>

                <div class="form-group">
                    <label class="form-label">Vai trò *</label>
                    <select name="role" class="form-input" id="addRole" onchange="toggleClassSelect('add')">
                        <option value="gvcn">GV Chủ nhiệm</option>
                        <option value="gvbm">GV Bộ môn</option>
                        <option value="admin">Quản trị viên</option>
                    </select>
                </div>

                <div class="form-group" id="addClassGroup">
                    <label class="form-label">Lớp phụ trách</label>
                    <select name="lop_id" class="form-input">
                        <option value="">-- Không chọn --</option>
                        <?php foreach ($lopList as $lop): ?>
                            <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i data-feather="plus"></i> Thêm giáo viên
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 500px;">
            <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            <h3 class="modal-title">Chỉnh sửa giáo viên</h3>

            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label class="form-label">Họ và tên *</label>
                    <input type="text" name="ho_ten" id="edit_ho_ten" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu mới (để trống nếu không đổi)</label>
                    <input type="password" name="password" class="form-input" placeholder="Nhập mật khẩu mới">
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Vai trò *</label>
                    <select name="role" id="edit_role" class="form-input" onchange="toggleClassSelect('edit')">
                        <option value="gvcn">GV Chủ nhiệm</option>
                        <option value="gvbm">GV Bộ môn</option>
                        <option value="admin">Quản trị viên</option>
                    </select>
                </div>

                <div class="form-group" id="editClassGroup">
                    <label class="form-label">Lớp phụ trách</label>
                    <select name="lop_id" id="edit_lop_id" class="form-input">
                        <option value="">-- Không chọn --</option>
                        <?php foreach ($lopList as $lop): ?>
                            <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="trang_thai" id="edit_trang_thai" style="width: 20px; height: 20px;">
                        <span>Tài khoản hoạt động</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i data-feather="save"></i> Lưu thay đổi
                </button>
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

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function toggleClassSelect(prefix) {
            var role = document.getElementById(prefix + 'Role') ? document.getElementById(prefix + 'Role').value : document.getElementById(prefix + '_role').value;
            var classGroup = document.getElementById(prefix + 'ClassGroup') || document.getElementById(prefix + 'ClassGroup');
            if (role === 'gvcn') {
                classGroup.style.display = 'block';
            } else {
                classGroup.style.display = 'none';
            }
        }

        function editTeacher(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_ho_ten').value = data.ho_ten;
            document.getElementById('edit_email').value = data.email || '';
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_lop_id').value = data.lop_id || '';
            document.getElementById('edit_trang_thai').checked = data.trang_thai == 1;

            toggleClassSelect('edit');
            document.getElementById('editModal').classList.add('active');
        }

        function deleteTeacher(id, name) {
            if (confirm('Bạn có chắc muốn xóa giáo viên "' + name + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
