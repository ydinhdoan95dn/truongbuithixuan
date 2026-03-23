<?php
/**
 * ==============================================
 * ADMIN DASHBOARD
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    redirect('admin/login.php');
}

$admin = getCurrentAdminFull();
define('PAGE_TITLE', 'Dashboard');

$conn = getDBConnection();
$role = getAdminRole();
$lopId = getAdminLopId();

// Thống kê theo quyền
$classFilter = getClassFilterSQL('hs');

// Tổng học sinh
if (isAdmin()) {
    $stmtHS = $conn->query("SELECT COUNT(*) FROM hoc_sinh WHERE trang_thai = 1");
} else {
    $stmtHS = $conn->prepare("SELECT COUNT(*) FROM hoc_sinh hs WHERE hs.trang_thai = 1 AND {$classFilter}");
    $stmtHS->execute();
}
$tongHS = $stmtHS->fetchColumn();

// Tổng đề thi
$classFilterDT = getClassFilterSQL('dt');
$stmtDT = $conn->query("SELECT COUNT(*) FROM de_thi dt WHERE dt.trang_thai = 1 AND {$classFilterDT}");
$tongDT = $stmtDT->fetchColumn();

// Tổng bài thi hoàn thành
if (isAdmin()) {
    $stmtBL = $conn->query("SELECT COUNT(*) FROM bai_lam WHERE trang_thai = 'hoan_thanh'");
} else {
    $stmtBL = $conn->prepare("
        SELECT COUNT(*) FROM bai_lam bl
        JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
        WHERE bl.trang_thai = 'hoan_thanh' AND hs.lop_id = ?
    ");
    $stmtBL->execute(array($lopId));
}
$tongBL = $stmtBL->fetchColumn();

// Tổng tài liệu
$classFilterTL = getClassFilterSQL('tl', true);
$stmtTL = $conn->query("SELECT COUNT(*) FROM tai_lieu tl WHERE tl.trang_thai = 1 AND {$classFilterTL}");
$tongTL = $stmtTL->fetchColumn();

// Bài thi gần đây
if (isAdmin()) {
    $stmtRecent = $conn->query("
        SELECT bl.*, hs.ho_ten, hs.ma_hs, dt.ten_de, mh.ten_mon, lh.ten_lop
        FROM bai_lam bl
        JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
        JOIN de_thi dt ON bl.de_thi_id = dt.id
        JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        WHERE bl.trang_thai = 'hoan_thanh'
        ORDER BY bl.thoi_gian_ket_thuc DESC
        LIMIT 10
    ");
} else {
    $stmtRecent = $conn->prepare("
        SELECT bl.*, hs.ho_ten, hs.ma_hs, dt.ten_de, mh.ten_mon, lh.ten_lop
        FROM bai_lam bl
        JOIN hoc_sinh hs ON bl.hoc_sinh_id = hs.id
        JOIN de_thi dt ON bl.de_thi_id = dt.id
        JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        WHERE bl.trang_thai = 'hoan_thanh' AND hs.lop_id = ?
        ORDER BY bl.thoi_gian_ket_thuc DESC
        LIMIT 10
    ");
    $stmtRecent->execute(array($lopId));
}
$recentExams = $stmtRecent->fetchAll();

// Top học sinh
if (isAdmin()) {
    $stmtTop = $conn->query("
        SELECT hs.ho_ten, hs.gioi_tinh, lh.ten_lop, dtl.diem_xep_hang, dtl.diem_trung_binh, dtl.tong_lan_thi
        FROM hoc_sinh hs
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        LEFT JOIN diem_tich_luy dtl ON hs.id = dtl.hoc_sinh_id
        WHERE hs.trang_thai = 1 AND lh.trang_thai = 1
        ORDER BY dtl.diem_xep_hang DESC
        LIMIT 5
    ");
} else {
    $stmtTop = $conn->prepare("
        SELECT hs.ho_ten, hs.gioi_tinh, lh.ten_lop, dtl.diem_xep_hang, dtl.diem_trung_binh, dtl.tong_lan_thi
        FROM hoc_sinh hs
        JOIN lop_hoc lh ON hs.lop_id = lh.id
        LEFT JOIN diem_tich_luy dtl ON hs.id = dtl.hoc_sinh_id
        WHERE hs.trang_thai = 1 AND hs.lop_id = ?
        ORDER BY dtl.diem_xep_hang DESC
        LIMIT 5
    ");
    $stmtTop->execute(array($lopId));
}
$topStudents = $stmtTop->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo PAGE_TITLE; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .stat-card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1F2937;
        }
        .stat-card-label {
            color: #6B7280;
            font-weight: 500;
        }
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .welcome-card h1 {
            font-size: 1.75rem;
            margin-bottom: 8px;
        }
        .welcome-card p {
            opacity: 0.9;
        }
        .welcome-emoji {
            font-size: 4rem;
        }
        @media (max-width: 768px) {
            .welcome-card { flex-direction: column; text-align: center; }
            .welcome-emoji { margin-top: 16px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div>
                    <h1>Xin chào, <?php echo htmlspecialchars($admin['ho_ten']); ?>!</h1>
                    <p>
                        <?php if (isAdmin()): ?>
                            Chào mừng quản trị viên quay trở lại hệ thống.
                        <?php elseif (isGVCN()): ?>
                            Chào mừng giáo viên chủ nhiệm <?php echo htmlspecialchars($admin['ten_lop']); ?>.
                        <?php else: ?>
                            Chào mừng giáo viên bộ môn quay trở lại.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="welcome-emoji">
                    <?php echo isAdmin() ? '👨‍💼' : '👨‍🏫'; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-grid">
                <?php if (isAdmin() || isGVCN()): ?>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">
                            <?php echo isGVCN() ? 'Học sinh lớp' : 'Tổng học sinh'; ?>
                        </span>
                        <div class="stat-card-icon" style="background: rgba(255, 107, 107, 0.15); color: #FF6B6B;">
                            <i data-feather="users"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $tongHS; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Đề thi</span>
                        <div class="stat-card-icon" style="background: rgba(78, 205, 196, 0.15); color: #4ECDC4;">
                            <i data-feather="file-text"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $tongDT; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Bài thi hoàn thành</span>
                        <div class="stat-card-icon" style="background: rgba(167, 139, 250, 0.15); color: #A78BFA;">
                            <i data-feather="check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $tongBL; ?></div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-label">Tài liệu</span>
                        <div class="stat-card-icon" style="background: rgba(96, 165, 250, 0.15); color: #60A5FA;">
                            <i data-feather="folder"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $tongTL; ?></div>
                </div>
            </div>

            <?php if (isAdmin() || isGVCN()): ?>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <!-- Recent Exams -->
                <div class="card" style="padding: 0; overflow: hidden; background: white; border-radius: 16px;">
                    <div style="padding: 20px 24px; border-bottom: 1px solid #E5E7EB;">
                        <h3 style="font-weight: 700; color: #1F2937;">
                            📝 Bài thi gần đây <?php echo isGVCN() ? '(' . htmlspecialchars($admin['ten_lop']) . ')' : ''; ?>
                        </h3>
                    </div>
                    <?php if (count($recentExams) > 0): ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #F9FAFB;">
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6B7280;">Học sinh</th>
                                <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #6B7280;">Đề thi</th>
                                <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #6B7280;">Điểm</th>
                                <th style="padding: 12px 16px; text-align: right; font-weight: 600; color: #6B7280;">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentExams as $exam): ?>
                                <tr style="border-top: 1px solid #E5E7EB;">
                                    <td style="padding: 12px 16px;">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($exam['ho_ten']); ?></div>
                                        <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo $exam['ten_lop']; ?></div>
                                    </td>
                                    <td style="padding: 12px 16px; color: #6B7280; font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($exam['ten_de']); ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: center; font-weight: 700; color: <?php echo $exam['diem'] >= 8 ? '#10B981' : ($exam['diem'] >= 5 ? '#F59E0B' : '#EF4444'); ?>;">
                                        <?php echo number_format($exam['diem'], 1); ?>
                                    </td>
                                    <td style="padding: 12px 16px; text-align: right; color: #9CA3AF; font-size: 0.75rem;">
                                        <?php echo formatDateVN($exam['thoi_gian_ket_thuc'], 'd/m H:i'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="padding: 40px; text-align: center; color: #9CA3AF;">
                        Chưa có bài thi nào.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Top Students -->
                <div class="card" style="padding: 0; overflow: hidden; background: white; border-radius: 16px;">
                    <div style="padding: 20px 24px; border-bottom: 1px solid #E5E7EB;">
                        <h3 style="font-weight: 700; color: #1F2937;">🏆 Top học sinh</h3>
                    </div>
                    <div style="padding: 16px;">
                        <?php if (count($topStudents) > 0): ?>
                        <?php foreach ($topStudents as $index => $hs): ?>
                            <?php
                            $rank = $index + 1;
                            $medal = '';
                            if ($rank === 1) $medal = '🥇';
                            elseif ($rank === 2) $medal = '🥈';
                            elseif ($rank === 3) $medal = '🥉';
                            $avatar = (isset($hs['gioi_tinh']) && $hs['gioi_tinh'] == 1) ? '👦' : '👧';
                            ?>
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px 0; <?php echo $index > 0 ? 'border-top: 1px solid #E5E7EB;' : ''; ?>">
                                <div style="width: 32px; text-align: center; font-size: 1.25rem;">
                                    <?php echo $medal ? $medal : $avatar; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($hs['ho_ten']); ?></div>
                                    <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo $hs['ten_lop']; ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 700; color: #667eea;"><?php echo round($hs['diem_xep_hang'] ? $hs['diem_xep_hang'] : 0); ?></div>
                                    <div style="font-size: 0.75rem; color: #9CA3AF;">điểm</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: #9CA3AF;">
                            Chưa có dữ liệu.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- GVBM - Chỉ xem tài liệu -->
            <div class="card" style="background: white; border-radius: 16px; padding: 32px; text-align: center;">
                <div style="font-size: 4rem; margin-bottom: 16px;">📚</div>
                <h3 style="color: #1F2937; margin-bottom: 8px;">Chào mừng giáo viên bộ môn!</h3>
                <p style="color: #6B7280; margin-bottom: 24px;">
                    Bạn có thể xem và quản lý tài liệu dùng chung cho tất cả các lớp.
                </p>
                <a href="<?php echo BASE_URL; ?>/admin/documents.php" class="btn btn-primary">
                    <i data-feather="folder"></i> Xem tài liệu
                </a>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>feather.replace();</script>
</body>
</html>
