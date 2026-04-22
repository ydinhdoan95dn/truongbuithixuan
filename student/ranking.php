<?php
/**
 * ==============================================
 * BẢNG XẾP HẠNG & VINH DANH - GAME STYLE
 * Fullscreen Desktop App - 2 Column Layout
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/device.php';

// Redirect mobile
redirectIfMobile(BASE_URL . '/student/mobile/ranking.php');

if (!isStudentLoggedIn()) {
    redirect('login.php');
}

$student = getCurrentStudent();
$conn = getDBConnection();

// Lấy danh sách lớp
$stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$lopList = $stmtLop->fetchAll();

// Filter parameters
$filterLop = isset($_GET['lop']) ? intval($_GET['lop']) : 0;
$filterPeriod = isset($_GET['period']) ? $_GET['period'] : 'week';
$filterType = isset($_GET['type']) ? $_GET['type'] : 'chinh_thuc';

// Lấy tuần hiện tại
$today = date('Y-m-d');
$stmtTuan = $conn->prepare("SELECT * FROM tuan_hoc WHERE ? BETWEEN ngay_bat_dau AND ngay_ket_thuc LIMIT 1");
$stmtTuan->execute(array($today));
$currentWeek = $stmtTuan->fetch();

// Xác định khoảng thời gian
$dateFilter = '';
$periodLabel = 'Tất cả thời gian';
if ($filterPeriod === 'week' && $currentWeek) {
    $dateFilter = " AND bl.thoi_gian_ket_thuc >= '{$currentWeek['ngay_bat_dau']}' AND bl.thoi_gian_ket_thuc <= '{$currentWeek['ngay_ket_thuc']} 23:59:59'";
    $periodLabel = $currentWeek['ten_tuan'];
} elseif ($filterPeriod === 'month') {
    $firstDayOfMonth = date('Y-m-01');
    $lastDayOfMonth = date('Y-m-t');
    $dateFilter = " AND bl.thoi_gian_ket_thuc >= '{$firstDayOfMonth}' AND bl.thoi_gian_ket_thuc <= '{$lastDayOfMonth} 23:59:59'";
    $periodLabel = 'Tháng ' . date('m/Y');
}

// Xác định loại bài thi
$typeFilter = '';
if ($filterType === 'chinh_thuc') {
    $typeFilter = ' AND bl.is_chinh_thuc = 1';
} elseif ($filterType === 'luyen_thi') {
    $typeFilter = ' AND (bl.is_chinh_thuc = 0 OR bl.is_chinh_thuc IS NULL)';
}

// Xác định lọc lớp
$classFilter = '';
if ($filterLop > 0) {
    $classFilter = ' AND hs.lop_id = ' . $filterLop;
}

// Query xếp hạng
$stmtXH = $conn->query("
    SELECT
        hs.id, hs.ho_ten, hs.chuoi_ngay_hoc, hs.gioi_tinh,
        lh.ten_lop, lh.khoi,
        COUNT(bl.id) as so_bai_thi,
        COALESCE(AVG(bl.diem), 0) as diem_trung_binh,
        COALESCE(SUM(bl.diem), 0) as tong_diem,
        COALESCE(MAX(bl.diem), 0) as diem_cao_nhat
    FROM hoc_sinh hs
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    LEFT JOIN bai_lam bl ON hs.id = bl.hoc_sinh_id
        AND bl.trang_thai = 'hoan_thanh'{$dateFilter}{$typeFilter}
    WHERE hs.trang_thai = 1 AND lh.trang_thai = 1{$classFilter}
    GROUP BY hs.id
    HAVING so_bai_thi > 0
    ORDER BY diem_trung_binh DESC, so_bai_thi DESC, tong_diem DESC
    LIMIT 100
");
$xepHangList = $stmtXH->fetchAll();

// Tìm vị trí học sinh hiện tại
$myRank = 0;
$myStats = null;
foreach ($xepHangList as $index => $xh) {
    if ($xh['id'] == $student['id']) {
        $myRank = $index + 1;
        $myStats = $xh;
        break;
    }
}

$totalStudents = count($xepHangList);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏆 Bảng xếp hạng - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        }

        .app { display: flex; height: 100vh; }

        /* ===== LEFT PANEL - VINH DANH ===== */
        .left-panel {
            width: 420px;
            min-width: 420px;
            background: linear-gradient(180deg, rgba(102,126,234,0.3) 0%, rgba(118,75,162,0.3) 100%);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        /* Animated background */
        .left-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,215,0,0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .left-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 24px;
        }

        /* Header */
        .header-title {
            text-align: center;
            color: white;
            margin-bottom: 20px;
        }
        .header-title h1 {
            font-size: 1.8rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .header-title .period {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 4px;
        }

        /* My Rank */
        .my-rank-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0.05) 100%);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
        }
        .my-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 4px 20px rgba(255,107,107,0.4);
        }
        .my-info { flex: 1; color: white; }
        .my-name { font-weight: 700; font-size: 1rem; }
        .my-class { opacity: 0.7; font-size: 0.85rem; }
        .my-rank-badge {
            text-align: center;
            color: white;
        }
        .my-rank-badge .label { font-size: 0.7rem; opacity: 0.7; }
        .my-rank-badge .value {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* PODIUM - Top 3 */
        .podium-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .podium-title {
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 24px;
        }
        .podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 16px;
        }
        .podium-item {
            text-align: center;
            transition: transform 0.3s;
        }
        .podium-item:hover { transform: scale(1.05); }

        .podium-avatar {
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .podium-1 .podium-avatar {
            width: 110px;
            height: 110px;
            font-size: 3.5rem;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            border: 4px solid #FFD700;
            animation: glow-gold 2s ease-in-out infinite;
        }
        .podium-2 .podium-avatar {
            width: 90px;
            height: 90px;
            font-size: 2.8rem;
            background: linear-gradient(135deg, #E8E8E8 0%, #B8B8B8 100%);
            border: 3px solid #C0C0C0;
        }
        .podium-3 .podium-avatar {
            width: 80px;
            height: 80px;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #CD7F32 0%, #8B4513 100%);
            border: 3px solid #CD7F32;
        }

        @keyframes glow-gold {
            0%, 100% { box-shadow: 0 0 20px rgba(255,215,0,0.5), 0 10px 40px rgba(0,0,0,0.3); }
            50% { box-shadow: 0 0 40px rgba(255,215,0,0.8), 0 10px 40px rgba(0,0,0,0.3); }
        }

        .podium-crown {
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 2rem;
            animation: bounce 1s ease-in-out infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(-5px); }
        }

        .podium-medal {
            font-size: 1.8rem;
            margin: 8px 0 4px;
        }
        .podium-1 .podium-medal { font-size: 2.2rem; }

        .podium-name {
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .podium-1 .podium-name { font-size: 1rem; max-width: 120px; }

        .podium-class {
            color: rgba(255,255,255,0.6);
            font-size: 0.75rem;
        }
        .podium-score {
            margin-top: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 1rem;
        }
        .podium-1 .podium-score {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: #7B4F00;
            font-size: 1.1rem;
        }
        .podium-2 .podium-score {
            background: linear-gradient(135deg, #E8E8E8, #C0C0C0);
            color: #555;
        }
        .podium-3 .podium-score {
            background: linear-gradient(135deg, #CD7F32, #A0522D);
            color: white;
        }
        .podium-exams {
            color: rgba(255,255,255,0.5);
            font-size: 0.7rem;
            margin-top: 4px;
        }

        /* Stand */
        .podium-stand {
            margin-top: 16px;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding-bottom: 8px;
        }
        .podium-1 .podium-stand {
            width: 100px;
            height: 80px;
            background: linear-gradient(180deg, #FFD700 0%, #B8860B 100%);
        }
        .podium-2 .podium-stand {
            width: 90px;
            height: 55px;
            background: linear-gradient(180deg, #C0C0C0 0%, #808080 100%);
        }
        .podium-3 .podium-stand {
            width: 85px;
            height: 40px;
            background: linear-gradient(180deg, #CD7F32 0%, #8B4513 100%);
        }
        .stand-number {
            color: rgba(255,255,255,0.9);
            font-size: 1.5rem;
            font-weight: 800;
        }

        /* Back button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 30px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            margin-top: auto;
            align-self: center;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        /* ===== RIGHT PANEL - DANH SÁCH ===== */
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8fafc;
            overflow: hidden;
        }

        /* Compact Filters */
        .filters-bar {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 24px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-group .label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
        }
        .filter-pills {
            display: flex;
            gap: 4px;
        }
        .filter-pill {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            background: #f1f5f9;
            color: #64748b;
        }
        .filter-pill:hover { background: #e2e8f0; }
        .filter-pill.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .filter-pill.gold.active {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: #7B4F00;
        }

        /* Class dropdown */
        .class-select {
            padding: 6px 12px;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            background: white;
        }
        .class-select:focus { outline: none; border-color: #667eea; }

        /* Stats bar */
        .stats-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stats-bar .title { font-weight: 700; font-size: 1rem; }
        .stats-bar .count { opacity: 0.9; font-size: 0.85rem; }

        /* Ranking List */
        .ranking-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 24px;
        }
        .rank-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 18px;
            background: white;
            border-radius: 16px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.2s;
            cursor: default;
        }
        .rank-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .rank-item.me {
            background: linear-gradient(135deg, rgba(255,107,107,0.1) 0%, rgba(255,142,83,0.1) 100%);
            border: 2px solid #FF6B6B;
        }

        .rank-pos {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .rank-pos.top-10 {
            background: linear-gradient(135deg, #4ECDC4 0%, #44A08D 100%);
            color: white;
        }
        .rank-pos.normal {
            background: #f1f5f9;
            color: #64748b;
        }

        .rank-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .rank-info { flex: 1; min-width: 0; }
        .rank-name {
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .rank-name .me-tag {
            background: #FF6B6B;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            font-weight: 700;
        }
        .rank-class { color: #94a3b8; font-size: 0.85rem; }

        .rank-stats {
            display: flex;
            gap: 20px;
            flex-shrink: 0;
        }
        .rank-stat { text-align: center; }
        .rank-stat .value {
            font-weight: 800;
            color: #4ECDC4;
            font-size: 0.95rem;
        }
        .rank-stat .label {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .rank-score {
            font-size: 1.5rem;
            font-weight: 800;
            color: #FF6B6B;
            min-width: 60px;
            text-align: right;
        }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
        }
        .empty-state .icon { font-size: 4rem; margin-bottom: 16px; }
        .empty-state .text { font-weight: 600; font-size: 1.1rem; }

        /* Scrollbar */
        .ranking-list::-webkit-scrollbar { width: 6px; }
        .ranking-list::-webkit-scrollbar-track { background: transparent; }
        .ranking-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        /* ========== RESPONSIVE CHO MÀN HÌNH NHỎ ========== */
        @media (max-width: 1366px) {
            .left-panel {
                min-width: 280px;
            }
            .trophy-icon { font-size: 3rem; }
            .top-item { padding: 14px; gap: 12px; }
            .top-avatar { width: 50px; height: 50px; font-size: 1.4rem; }
            .top-name { font-size: 0.95rem; }
            .top-score { font-size: 1.5rem; }
            .right-panel { padding: 16px; }
            .filter-group select, .filter-group button {
                padding: 10px 14px;
                font-size: 0.85rem;
            }
            .ranking-row { padding: 12px 14px; }
            .rank-badge { width: 36px; height: 36px; font-size: 0.9rem; }
            .rank-avatar { width: 40px; height: 40px; font-size: 1.1rem; }
            .rank-name { font-size: 0.9rem; }
            .rank-stats { font-size: 0.75rem; }
            .score-value { font-size: 1.3rem; }
        }

        @media (max-width: 1280px) {
            .left-panel { min-width: 250px; }
            .top-item { padding: 12px; }
            .top-avatar { width: 45px; height: 45px; font-size: 1.2rem; }
            .top-name { font-size: 0.9rem; }
            .top-score { font-size: 1.3rem; }
        }

        @media (max-height: 768px) {
            .left-panel { padding: 16px; }
            .left-header { margin-bottom: 16px; }
            .trophy-icon { font-size: 2.5rem; }
            .left-title { font-size: 1.3rem; }
            .top-item { padding: 10px; gap: 10px; }
            .top-avatar { width: 42px; height: 42px; font-size: 1.1rem; }
            .top-name { font-size: 0.85rem; }
            .top-score { font-size: 1.2rem; }
            .right-panel { padding: 12px; }
            .filters { gap: 10px; margin-bottom: 12px; padding: 10px; }
            .filter-group select, .filter-group button {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
            .my-rank-card { padding: 12px 14px; margin-bottom: 12px; }
            .my-rank-badge { width: 40px; height: 40px; font-size: 1.1rem; }
            .my-name { font-size: 1rem; }
            .my-score { font-size: 1.1rem; }
            .ranking-header { padding: 10px 14px; }
            .ranking-row { padding: 10px 12px; }
            .rank-badge { width: 32px; height: 32px; font-size: 0.85rem; }
            .rank-avatar { width: 36px; height: 36px; font-size: 1rem; }
            .rank-name { font-size: 0.85rem; }
            .rank-stats { font-size: 0.7rem; }
            .score-value { font-size: 1.1rem; }
        }

        @media (max-height: 680px) {
            .left-panel { padding: 12px; }
            .trophy-icon { font-size: 2rem; }
            .left-title { font-size: 1.1rem; }
            .top-item { padding: 8px; }
            .top-avatar { width: 38px; height: 38px; font-size: 1rem; }
            .top-name { font-size: 0.8rem; }
            .top-class { font-size: 0.65rem; }
            .top-score { font-size: 1.1rem; }
            .right-panel { padding: 10px; }
            .filters { padding: 8px; margin-bottom: 10px; }
            .my-rank-card { padding: 10px 12px; }
            .ranking-row { padding: 8px 10px; }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- LEFT PANEL - VINH DANH -->
        <aside class="left-panel">
            <div class="left-content">
                <!-- Header -->
                <div class="header-title">
                    <h1><span>🏆</span> Vinh Danh</h1>
                    <div class="period"><?php echo $periodLabel; ?></div>
                </div>

                <!-- My Rank Card -->
                <?php if ($myRank > 0 && $myStats): ?>
                <div class="my-rank-card">
                    <div class="my-avatar"><?php echo $student['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                    <div class="my-info">
                        <div class="my-name"><?php echo htmlspecialchars($student['ho_ten']); ?></div>
                        <div class="my-class"><?php echo $student['ten_lop']; ?> • ĐTB: <?php echo number_format($myStats['diem_trung_binh'], 1); ?></div>
                    </div>
                    <div class="my-rank-badge">
                        <div class="label">Hạng</div>
                        <div class="value">#<?php echo $myRank; ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Top 3 Podium -->
                <div class="podium-section">
                    <?php if (count($xepHangList) >= 3): ?>
                    <div class="podium-title">✨ Top 3 Xuất Sắc ✨</div>
                    <div class="podium">
                        <!-- Rank 2 -->
                        <div class="podium-item podium-2">
                            <div class="podium-avatar"><?php echo $xepHangList[1]['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                            <div class="podium-medal">🥈</div>
                            <div class="podium-name"><?php echo htmlspecialchars($xepHangList[1]['ho_ten']); ?></div>
                            <div class="podium-class"><?php echo $xepHangList[1]['ten_lop']; ?></div>
                            <div class="podium-score"><?php echo number_format($xepHangList[1]['diem_trung_binh'], 1); ?></div>
                            <div class="podium-exams"><?php echo $xepHangList[1]['so_bai_thi']; ?> bài thi</div>
                            <div class="podium-stand"><span class="stand-number">2</span></div>
                        </div>

                        <!-- Rank 1 -->
                        <div class="podium-item podium-1">
                            <div class="podium-avatar">
                                <span class="podium-crown">👑</span>
                                <?php echo $xepHangList[0]['gioi_tinh'] == 1 ? '👦' : '👧'; ?>
                            </div>
                            <div class="podium-medal">🥇</div>
                            <div class="podium-name"><?php echo htmlspecialchars($xepHangList[0]['ho_ten']); ?></div>
                            <div class="podium-class"><?php echo $xepHangList[0]['ten_lop']; ?></div>
                            <div class="podium-score"><?php echo number_format($xepHangList[0]['diem_trung_binh'], 1); ?></div>
                            <div class="podium-exams"><?php echo $xepHangList[0]['so_bai_thi']; ?> bài thi</div>
                            <div class="podium-stand"><span class="stand-number">1</span></div>
                        </div>

                        <!-- Rank 3 -->
                        <div class="podium-item podium-3">
                            <div class="podium-avatar"><?php echo $xepHangList[2]['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                            <div class="podium-medal">🥉</div>
                            <div class="podium-name"><?php echo htmlspecialchars($xepHangList[2]['ho_ten']); ?></div>
                            <div class="podium-class"><?php echo $xepHangList[2]['ten_lop']; ?></div>
                            <div class="podium-score"><?php echo number_format($xepHangList[2]['diem_trung_binh'], 1); ?></div>
                            <div class="podium-exams"><?php echo $xepHangList[2]['so_bai_thi']; ?> bài thi</div>
                            <div class="podium-stand"><span class="stand-number">3</span></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="icon">🏆</div>
                        <div class="text">Chưa đủ dữ liệu Top 3</div>
                    </div>
                    <?php endif; ?>
                </div>

                <a href="<?php echo BASE_URL; ?>/student/dashboard.php" class="back-btn">
                    <span>🏠</span> Về trang chủ
                </a>
            </div>
        </aside>

        <!-- RIGHT PANEL - DANH SÁCH -->
        <main class="right-panel">
            <!-- Compact Filters -->
            <div class="filters-bar">
                <div class="filter-group">
                    <span class="label">⏰</span>
                    <div class="filter-pills">
                        <a href="?period=week&type=<?php echo $filterType; ?>&lop=<?php echo $filterLop; ?>" class="filter-pill <?php echo $filterPeriod === 'week' ? 'active' : ''; ?>">Tuần</a>
                        <a href="?period=month&type=<?php echo $filterType; ?>&lop=<?php echo $filterLop; ?>" class="filter-pill <?php echo $filterPeriod === 'month' ? 'active' : ''; ?>">Tháng</a>
                        <a href="?period=all&type=<?php echo $filterType; ?>&lop=<?php echo $filterLop; ?>" class="filter-pill <?php echo $filterPeriod === 'all' ? 'active' : ''; ?>">Tổng</a>
                    </div>
                </div>

                <div class="filter-group">
                    <span class="label">📝</span>
                    <div class="filter-pills">
                        <a href="?period=<?php echo $filterPeriod; ?>&type=chinh_thuc&lop=<?php echo $filterLop; ?>" class="filter-pill gold <?php echo $filterType === 'chinh_thuc' ? 'active' : ''; ?>">⭐ Chính thức</a>
                        <a href="?period=<?php echo $filterPeriod; ?>&type=luyen_thi&lop=<?php echo $filterLop; ?>" class="filter-pill <?php echo $filterType === 'luyen_thi' ? 'active' : ''; ?>">Luyện thi</a>
                        <a href="?period=<?php echo $filterPeriod; ?>&type=all&lop=<?php echo $filterLop; ?>" class="filter-pill <?php echo $filterType === 'all' ? 'active' : ''; ?>">Tất cả</a>
                    </div>
                </div>

                <div class="filter-group">
                    <span class="label">🏫</span>
                    <select class="class-select" onchange="window.location.href='?period=<?php echo $filterPeriod; ?>&type=<?php echo $filterType; ?>&lop=' + this.value">
                        <option value="0" <?php echo $filterLop == 0 ? 'selected' : ''; ?>>Toàn trường</option>
                        <?php foreach ($lopList as $lop): ?>
                        <option value="<?php echo $lop['id']; ?>" <?php echo $filterLop == $lop['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="stats-bar">
                <span class="title">📊 Bảng xếp hạng</span>
                <span class="count"><?php echo $totalStudents; ?> học sinh</span>
            </div>

            <!-- Ranking List -->
            <div class="ranking-list">
                <?php if (count($xepHangList) > 0): ?>
                    <?php foreach ($xepHangList as $index => $xh): ?>
                    <?php
                    $rank = $index + 1;
                    $isMe = ($xh['id'] == $student['id']);
                    if ($rank <= 3) continue; // Skip top 3
                    ?>
                    <div class="rank-item <?php echo $isMe ? 'me' : ''; ?>">
                        <div class="rank-pos <?php echo $rank <= 10 ? 'top-10' : 'normal'; ?>">
                            <?php echo $rank; ?>
                        </div>
                        <div class="rank-avatar"><?php echo $xh['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                        <div class="rank-info">
                            <div class="rank-name">
                                <?php echo htmlspecialchars($xh['ho_ten']); ?>
                                <?php if ($isMe): ?><span class="me-tag">Bạn</span><?php endif; ?>
                            </div>
                            <div class="rank-class"><?php echo $xh['ten_lop']; ?></div>
                        </div>
                        <div class="rank-stats">
                            <div class="rank-stat">
                                <div class="value"><?php echo $xh['so_bai_thi']; ?></div>
                                <div class="label">Bài thi</div>
                            </div>
                            <div class="rank-stat">
                                <div class="value">🔥<?php echo $xh['chuoi_ngay_hoc']; ?></div>
                                <div class="label">Chuỗi</div>
                            </div>
                        </div>
                        <div class="rank-score"><?php echo number_format($xh['diem_trung_binh'], 1); ?></div>
                    </div>
                    <?php endforeach; ?>

                    <?php if (count($xepHangList) <= 3): ?>
                    <div class="empty-state">
                        <div class="icon">🎉</div>
                        <div class="text">Chỉ có Top 3 trong bảng xếp hạng!</div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <div class="text">Chưa có dữ liệu xếp hạng</div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
