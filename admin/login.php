<?php
/**
 * ==============================================
 * ĐĂNG NHẬP ADMIN
 * ==============================================
 */

require_once '../includes/config.php';

if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if (!$admin) {
            $error = 'Tài khoản không tồn tại!';
        } elseif ($admin['trang_thai'] != 1) {
            $error = 'Tài khoản đã bị khóa!';
        } elseif (!verifyPassword($password, $admin['password'])) {
            $error = 'Mật khẩu không chính xác!';
        } else {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['ho_ten'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_lop_id'] = $admin['lop_id'];

            logActivity('admin', $admin['id'], 'Đăng nhập Admin', 'Đăng nhập thành công - Role: ' . $admin['role']);

            redirect('admin/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập Admin - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">👨‍🏫</div>
                <h2 class="login-title">Đăng nhập Giáo viên</h2>
                <p class="login-subtitle">Quản lý hệ thống học tập</p>
            </div>

            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: #EF4444; padding: 12px 16px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: 600;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Tên đăng nhập</label>
                    <input type="text" name="username" class="form-input" placeholder="admin" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••" required>
                </div>

                <button type="submit" class="btn btn-lg" style="width: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i data-feather="log-in"></i>
                    Đăng nhập
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid #E5E7EB;">
                <a href="<?php echo BASE_URL; ?>" style="color: #6B7280; font-weight: 600;">
                    <i data-feather="arrow-left" style="width: 16px; height: 16px; vertical-align: middle;"></i>
                    Quay về trang chủ
                </a>
            </div>
        </div>
    </div>

    <script>feather.replace();</script>
</body>
</html>
