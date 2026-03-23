<?php
/**
 * ==============================================
 * BẢNG XẾP HẠNG
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

// Lấy bảng xếp hạng (theo quyền)
$classFilter = getClassFilterSQL('hs', false);
$stmtRanking = $conn->query("
    SELECT hs.id, hs.ho_ten, hs.ma_hs, hs.gioi_tinh, lh.ten_lop,
           COALESCE(dtl.diem_xep_hang, 0) as diem_xep_hang,
           COALESCE(dtl.diem_trung_binh, 0) as diem_trung_binh,
           COALESCE(dtl.tong_lan_thi, 0) as tong_lan_thi
    FROM hoc_sinh hs
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    LEFT JOIN diem_tich_luy dtl ON hs.id = dtl.hoc_sinh_id
    WHERE hs.trang_thai = 1 AND {$classFilter}
    ORDER BY dtl.diem_xep_hang DESC, dtl.diem_trung_binh DESC
    LIMIT 50
");
$rankingList = $stmtRanking->fetchAll();

$pageTitle = isGVCN() ? 'Xếp hạng ' . $admin['ten_lop'] : 'Bảng xếp hạng';
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
        .ranking-row {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            background: white;
            border-radius: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .ranking-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .ranking-row.top-1 { border-left: 4px solid #FFD700; }
        .ranking-row.top-2 { border-left: 4px solid #C0C0C0; }
        .ranking-row.top-3 { border-left: 4px solid #CD7F32; }
        .rank-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
        }
        .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); color: white; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #8B4513); color: white; }
        .rank-other { background: #F3F4F6; color: #6B7280; }
        .student-info { flex: 1; }
        .student-name { font-weight: 600; color: #1F2937; }
        .student-meta { font-size: 0.75rem; color: #9CA3AF; margin-top: 2px; }
        .score-badge {
            text-align: center;
            padding: 8px 16px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .score-value { font-size: 1.25rem; font-weight: 700; }
        .score-label { font-size: 0.7rem; opacity: 0.9; }
        .stats-row {
            display: flex;
            gap: 12px;
        }
        .stat-mini {
            text-align: center;
            padding: 8px 12px;
            background: #F9FAFB;
            border-radius: 8px;
        }
        .stat-mini-value { font-weight: 700; color: #1F2937; }
        .stat-mini-label { font-size: 0.7rem; color: #9CA3AF; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 24px;">
                🏆 <?php echo $pageTitle; ?>
            </h1>

            <?php if (empty($rankingList)): ?>
                <div class="empty-state" style="background: white; border-radius: 16px; padding: 48px; text-align: center;">
                    <div style="font-size: 4rem; margin-bottom: 16px;">🏆</div>
                    <p style="color: #6B7280;">Chưa có dữ liệu xếp hạng</p>
                </div>
            <?php else: ?>
                <?php foreach ($rankingList as $index => $hs): ?>
                    <?php
                    $rank = $index + 1;
                    $rankClass = '';
                    $rowClass = '';
                    if ($rank === 1) { $rankClass = 'rank-1'; $rowClass = 'top-1'; }
                    elseif ($rank === 2) { $rankClass = 'rank-2'; $rowClass = 'top-2'; }
                    elseif ($rank === 3) { $rankClass = 'rank-3'; $rowClass = 'top-3'; }
                    else { $rankClass = 'rank-other'; }

                    $avatar = ($hs['gioi_tinh'] == 1) ? '👦' : '👧';
                    $medal = '';
                    if ($rank === 1) $medal = '🥇';
                    elseif ($rank === 2) $medal = '🥈';
                    elseif ($rank === 3) $medal = '🥉';
                    ?>
                    <div class="ranking-row <?php echo $rowClass; ?>">
                        <div class="rank-number <?php echo $rankClass; ?>">
                            <?php echo $medal ? $medal : $rank; ?>
                        </div>

                        <div style="font-size: 2rem;"><?php echo $avatar; ?></div>

                        <div class="student-info">
                            <div class="student-name"><?php echo htmlspecialchars($hs['ho_ten']); ?></div>
                            <div class="student-meta"><?php echo $hs['ma_hs']; ?> - <?php echo $hs['ten_lop']; ?></div>
                        </div>

                        <div class="stats-row">
                            <div class="stat-mini">
                                <div class="stat-mini-value"><?php echo number_format($hs['diem_trung_binh'], 1); ?></div>
                                <div class="stat-mini-label">TB</div>
                            </div>
                            <div class="stat-mini">
                                <div class="stat-mini-value"><?php echo $hs['tong_lan_thi']; ?></div>
                                <div class="stat-mini-label">Bài thi</div>
                            </div>
                        </div>

                        <div class="score-badge">
                            <div class="score-value"><?php echo round($hs['diem_xep_hang']); ?></div>
                            <div class="score-label">Tổng điểm</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>

    <script>feather.replace();</script>
</body>
</html>
