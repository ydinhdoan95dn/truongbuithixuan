<?php
/**
 * ==============================================
 * TRANG ĐĂNG NHẬP HỌC SINH
 * Web App Học tập & Thi trực tuyến Tiểu học
 * Trường Bùi Thị Xuân
 * ==============================================
 */

require_once 'includes/config.php';
require_once 'includes/device.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isStudentLoggedIn()) {
    if (isMobile()) {
        redirect('student/mobile/index.php');
    } else {
        redirect('student/dashboard.php');
    }
}

$error = '';
$maHS = '';

// Xử lý đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $maHS = isset($_POST['ma_hs']) ? sanitize($_POST['ma_hs']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($maHS) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ thông tin!';
    } else {
        $conn = getDBConnection();

        // Tìm học sinh
        $stmt = $conn->prepare("
            SELECT hs.*, lh.trang_thai as lop_trang_thai
            FROM hoc_sinh hs
            JOIN lop_hoc lh ON hs.lop_id = lh.id
            WHERE hs.ma_hs = ?
        ");
        $stmt->execute([$maHS]);
        $student = $stmt->fetch();

        if (!$student) {
            $error = 'Mã học sinh không tồn tại!';
        } elseif ($student['trang_thai'] != 1) {
            $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ giáo viên!';
        } elseif ($student['lop_trang_thai'] != 1) {
            $error = 'Lớp học chưa được kích hoạt!';
        } elseif (!verifyPassword($password, $student['password'])) {
            $error = 'Mật khẩu không chính xác!';
        } else {
            // Đăng nhập thành công
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_name'] = $student['ho_ten'];
            $_SESSION['student_class'] = $student['lop_id'];

            // Cập nhật chuỗi ngày học
            updateStudyStreak($student['id']);

            // Log hoạt động
            logActivity('hoc_sinh', $student['id'], 'Đăng nhập', 'Đăng nhập thành công');

            // Redirect based on device
            if (isMobile()) {
                redirect('student/mobile/index.php');
            } else {
                redirect('student/dashboard.php');
            }
        }
    }
}

define('PAGE_TITLE', 'Đăng nhập');
$bodyClass = 'login-page';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, viewport-fit=cover">
    <meta name="theme-color" content="#FF6B6B">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo PAGE_TITLE . ' - ' . SITE_NAME; ?></title>
    <?php
    require_once 'includes/seo.php';
    echo getSeoMetaTags(PAGE_TITLE);
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">📚</div>
                <h2 class="login-title">Xin chào!</h2>
                <p class="login-subtitle">Đăng nhập để bắt đầu học tập</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error" style="text-align: center;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" onsubmit="return validateLoginForm(this)">
                <div class="form-group">
                    <label class="form-label">Mã học sinh</label>
                    <input type="text"
                           name="ma_hs"
                           class="form-input"
                           placeholder="Ví dụ: HS3001"
                           value="<?php echo htmlspecialchars($maHS); ?>"
                           autocomplete="username"
                           autocapitalize="characters"
                           required>
                </div>

                <div class="form-group">
                    <label class="form-label">Mật khẩu</label>
                    <div style="position: relative;">
                        <input type="password"
                               name="password"
                               id="password-input"
                               class="form-input"
                               placeholder="Nhập mật khẩu"
                               autocomplete="current-password"
                               required>
                        <button type="button"
                                onclick="togglePassword()"
                                style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9CA3AF; cursor: pointer; padding: 8px;">
                            <i data-feather="eye" id="toggle-icon" style="width: 20px; height: 20px;"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top: 16px;">
                    <i data-feather="log-in"></i>
                    Đăng nhập
                </button>
            </form>

            <div style="text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid #E5E7EB;">
                <a href="<?php echo BASE_URL; ?>" class="btn btn-ghost">
                    <i data-feather="arrow-left"></i>
                    Quay về trang chủ
                </a>
            </div>

            <div style="text-align: center; margin-top: 16px;">
                <a href="<?php echo BASE_URL; ?>/admin/login.php" style="color: #9CA3AF; font-size: 14px;">
                    Đăng nhập Giáo viên
                </a>
            </div>
        </div>
    </div>

    <script>
        feather.replace();

        function validateLoginForm(form) {
            const maHS = form.querySelector('[name="ma_hs"]').value.trim();
            const password = form.querySelector('[name="password"]').value;

            if (!maHS) {
                alert('Vui lòng nhập mã học sinh!');
                return false;
            }

            if (!password) {
                alert('Vui lòng nhập mật khẩu!');
                return false;
            }

            return true;
        }

        function togglePassword() {
            const input = document.getElementById('password-input');
            const icon = document.getElementById('toggle-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-feather', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-feather', 'eye');
            }
            feather.replace();
        }
    </script>
</body>
</html>
