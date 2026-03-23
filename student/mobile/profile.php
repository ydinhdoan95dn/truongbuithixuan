<?php
/**
 * ==============================================
 * MOBILE - TRANG CÁ NHÂN
 * ==============================================
 */

require_once '../../includes/config.php';
require_once '../../includes/device.php';

redirectIfDesktop(BASE_URL . '/student/dashboard.php');

if (!isStudentLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$student = getCurrentStudent();
$conn = getDBConnection();

// Lấy thông tin lớp
$stmtLop = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ?");
$stmtLop->execute(array($student['lop_id']));
$lop = $stmtLop->fetch();

// Lấy điểm tích lũy
$stmtDiem = $conn->prepare("SELECT * FROM diem_tich_luy WHERE hoc_sinh_id = ?");
$stmtDiem->execute(array($student['id']));
$diemTichLuy = $stmtDiem->fetch();

// Thống kê
$stmtStats = $conn->prepare("
    SELECT
        COUNT(*) as tong_bai,
        AVG(diem) as diem_tb,
        MAX(diem) as diem_cao_nhat,
        SUM(so_cau_dung) as tong_cau_dung
    FROM bai_lam
    WHERE hoc_sinh_id = ? AND trang_thai = 'hoan_thanh'
");
$stmtStats->execute(array($student['id']));
$stats = $stmtStats->fetch();

$pageTitle = 'Trang cá nhân';
$currentTab = 'profile';
include 'header.php';
?>

<!-- Header -->
<div class="header">
    <div class="header-content">
        <div class="page-header">
            <h1>👤 Trang cá nhân</h1>
        </div>
        <a href="<?php echo BASE_URL; ?>/logout.php" style="color: white; text-decoration: none; font-size: 14px;">
            Đăng xuất
        </a>
    </div>
</div>

<main class="main">
    <!-- Profile Card -->
    <div class="card">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="font-size: 56px; width: 80px; height: 80px; background: var(--bg); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <?php echo getStudentAvatar($student); ?>
            </div>
            <div style="flex: 1;">
                <div style="font-size: 20px; font-weight: 700;"><?php echo htmlspecialchars($student['ho_ten']); ?></div>
                <div style="color: var(--text-light); margin-top: 4px;"><?php echo htmlspecialchars($lop['ten_lop']); ?></div>
                <div style="margin-top: 8px;">
                    <span style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 600;">
                        ⭐ <?php echo number_format(isset($diemTichLuy['diem_xep_hang']) ? $diemTichLuy['diem_xep_hang'] : 0); ?> điểm
                    </span>
                </div>
            </div>
        </div>
    </div>
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="value"><?php echo $stats['tong_bai'] ?: 0; ?></div>
            <div class="label">Bài đã thi</div>
        </div>
        <div class="stat-item success">
            <div class="value"><?php echo round($stats['diem_tb'] ?: 0, 1); ?></div>
            <div class="label">Điểm TB</div>
        </div>
        <div class="stat-item warning">
            <div class="value"><?php echo $stats['diem_cao_nhat'] ?: 0; ?></div>
            <div class="label">Cao nhất</div>
        </div>
    </div>

    <!-- Menu -->
    <div class="card">
        <a href="history.php" class="list-item">
            <div class="icon">📊</div>
            <div class="content">
                <div class="title">Lịch sử thi</div>
                <div class="subtitle">Xem lại các bài thi đã làm</div>
            </div>
            <div class="arrow">›</div>
        </a>
        <a href="ranking.php" class="list-item">
            <div class="icon">🏆</div>
            <div class="content">
                <div class="title">Bảng xếp hạng</div>
                <div class="subtitle">Xem thứ hạng của bạn</div>
            </div>
            <div class="arrow">›</div>
        </a>
        <a href="<?php echo BASE_URL; ?>/student/dashboard.php?view=desktop" class="list-item">
            <div class="icon">💻</div>
            <div class="content">
                <div class="title">Phiên bản Desktop</div>
                <div class="subtitle">Chuyển sang giao diện máy tính</div>
            </div>
            <div class="arrow">›</div>
        </a>
    </div>

    <!-- Info -->
    <div class="card">
        <div class="card-title">Thông tin tài khoản</div>
        <div style="font-size: 14px; line-height: 2;">
            <div><strong>Họ tên:</strong> <?php echo htmlspecialchars($student['ho_ten']); ?></div>
            <div><strong>Lớp:</strong> <?php echo htmlspecialchars($lop['ten_lop']); ?></div>
            <div><strong>Mã học sinh:</strong> <?php echo htmlspecialchars($student['ma_hs']); ?></div>
        </div>
    </div>

    <!-- Logout -->
    <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-danger btn-block" style="margin-bottom: 24px;">
        🚪 Đăng xuất
    </a>

    <!-- Author Credit -->
    <div class="author-credit">
        <strong>Tác giả:</strong> GV Hồ Thị Thanh Hằng - GV Đinh Thị Chi
    </div>
</main>

<!-- Bottom Tab Bar -->
<nav class="tab-bar">
    <a href="index.php" class="tab-item">
        <span class="icon">🏠</span>
        <span class="label">Trang chủ</span>
    </a>
    <a href="exams.php" class="tab-item">
        <span class="icon">📝</span>
        <span class="label">Làm bài</span>
    </a>
    <a href="documents.php" class="tab-item">
        <span class="icon">📖</span>
        <span class="label">Tài liệu</span>
    </a>
    <a href="profile.php" class="tab-item active">
        <span class="icon">👤</span>
        <span class="label">Tôi</span>
    </a>
</nav>

<?php include 'footer.php'; ?>
