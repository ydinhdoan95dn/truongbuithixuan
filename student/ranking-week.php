<?php
/**
 * ==============================================
 * BẢNG XẾP HẠNG TUẦN / THÁNG / HỌC KỲ
 * Mặc định hiển thị tuần trước (đã có kết quả)
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/week_helper.php';

if (!isStudentLoggedIn()) {
    redirect('login.php');
}

$student = getCurrentStudent();
if (!$student) {
    redirect('logout.php');
}

$conn = getDBConnection();

// Xác định tab hiện tại
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'week';
$viewType = isset($_GET['view']) ? $_GET['view'] : 'class'; // class hoặc grade

// Lấy tuần trước (mặc định)
$lastWeek = getLastWeek();
$currentWeek = getCurrentWeek();
$currentSemester = getCurrentSemester();

// Xử lý theo tab
$rankings = array();
$pageTitle = '';
$periodInfo = '';

if ($tab == 'week') {
    // Mặc định hiển thị tuần trước
    $selectedWeek = isset($_GET['week_id']) ? getWeekById(intval($_GET['week_id'])) : $lastWeek;

    if ($selectedWeek) {
        $pageTitle = $selectedWeek['ten_tuan'];
        $periodInfo = formatWeekDate($selectedWeek);

        if ($viewType == 'class') {
            $rankings = getWeekRankingByClass($selectedWeek['id'], $student['lop_id']);
        } else {
            $rankings = getWeekRankingByGrade($selectedWeek['id'], $student['khoi']);
        }
    }

    // Lấy danh sách tuần để chọn
    $weekList = array();
    if ($currentSemester) {
        $weekList = getWeeksBySemester($currentSemester['id']);
    }
} elseif ($tab == 'month') {
    // Xếp hạng tháng
    $month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
    $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

    // Nếu là tháng hiện tại, lùi lại 1 tháng
    if ($month == intval(date('m')) && $year == intval(date('Y'))) {
        $month--;
        if ($month < 1) {
            $month = 12;
            $year--;
        }
    }

    $pageTitle = getVietnameseMonth($month) . ' ' . $year;
    $periodInfo = 'Tổng hợp các tuần trong tháng';

    $classId = ($viewType == 'class') ? $student['lop_id'] : null;
    $rankings = getMonthRanking($month, $year, $classId);
} elseif ($tab == 'semester') {
    // Xếp hạng học kỳ
    $semesterId = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : ($currentSemester ? $currentSemester['id'] : 0);
    $semester = null;

    if ($semesterId) {
        $stmtSem = $conn->prepare("SELECT * FROM hoc_ky WHERE id = ?");
        $stmtSem->execute(array($semesterId));
        $semester = $stmtSem->fetch();
    }

    if ($semester) {
        $pageTitle = $semester['ten_hoc_ky'] . ' - ' . $semester['nam_hoc'];
        $periodInfo = 'Tổng hợp toàn bộ học kỳ';

        $classId = ($viewType == 'class') ? $student['lop_id'] : null;
        $rankings = getSemesterRanking($semesterId, $classId);
    }
}

// Tìm vị trí của học sinh hiện tại
$myRank = 0;
$myScore = 0;
foreach ($rankings as $index => $r) {
    if ($r['hoc_sinh_id'] == $student['id']) {
        $myRank = $index + 1;
        $myScore = isset($r['diem_cao_nhat']) ? $r['diem_cao_nhat'] : $r['tong_diem'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng xếp hạng - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .ranking-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #6B7280;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            color: #667eea;
        }

        .ranking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 24px;
            color: white;
            text-align: center;
            margin-bottom: 24px;
        }

        .ranking-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .ranking-period {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .my-rank-box {
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            display: inline-block;
        }

        .my-rank-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .my-rank-value {
            font-size: 2rem;
            font-weight: 700;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: #F3F4F6;
            border-radius: 10px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            background: #E5E7EB;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* View toggle */
        .view-toggle {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .view-btn {
            padding: 8px 16px;
            border: 2px solid #E5E7EB;
            background: white;
            border-radius: 8px;
            font-weight: 600;
            color: #6B7280;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .view-btn.active {
            border-color: #667eea;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        /* Ranking list */
        .ranking-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #F3F4F6;
            transition: background 0.2s;
        }

        .ranking-item:last-child {
            border-bottom: none;
        }

        .ranking-item:hover {
            background: #F9FAFB;
        }

        .ranking-item.is-me {
            background: rgba(102, 126, 234, 0.1);
        }

        .rank-number {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #A0A0A0); color: white; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #A0522D); color: white; }
        .rank-other { background: #F3F4F6; color: #6B7280; }

        .rank-info {
            flex: 1;
        }

        .rank-name {
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 2px;
        }

        .rank-class {
            font-size: 0.8rem;
            color: #9CA3AF;
        }

        .rank-score {
            text-align: right;
        }

        .score-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #FF6B6B;
        }

        .score-label {
            font-size: 0.75rem;
            color: #9CA3AF;
        }

        .attempts-badge {
            background: #E5E7EB;
            color: #6B7280;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 8px;
        }

        /* Week selector */
        .week-selector {
            background: white;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .week-select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #E5E7EB;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            color: #1F2937;
            background: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9CA3AF;
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        @media (max-width: 600px) {
            .ranking-container {
                padding: 16px;
            }

            .tabs {
                justify-content: center;
            }

            .tab-btn {
                flex: 1;
                text-align: center;
                padding: 10px 12px;
            }
        }
    </style>
</head>
<body style="background: #F9FAFB; min-height: 100vh;">
    <div class="ranking-container">
        <a href="<?php echo BASE_URL; ?>/student/dashboard.php" class="back-btn">
            ← Quay lại Dashboard
        </a>

        <!-- Header -->
        <div class="ranking-header">
            <div class="ranking-title">🏆 <?php echo htmlspecialchars($pageTitle); ?></div>
            <div class="ranking-period"><?php echo $periodInfo; ?></div>

            <?php if ($myRank > 0): ?>
            <div class="my-rank-box">
                <div class="my-rank-label">Hạng của bạn</div>
                <div class="my-rank-value">#<?php echo $myRank; ?></div>
                <div class="my-rank-label">Điểm: <?php echo number_format($myScore, 1); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=week&view=<?php echo $viewType; ?>" class="tab-btn <?php echo $tab == 'week' ? 'active' : ''; ?>">
                📅 Tuần
            </a>
            <a href="?tab=month&view=<?php echo $viewType; ?>" class="tab-btn <?php echo $tab == 'month' ? 'active' : ''; ?>">
                📆 Tháng
            </a>
            <a href="?tab=semester&view=<?php echo $viewType; ?>" class="tab-btn <?php echo $tab == 'semester' ? 'active' : ''; ?>">
                🎓 Học kỳ
            </a>
        </div>

        <!-- View Toggle -->
        <div class="view-toggle">
            <a href="?tab=<?php echo $tab; ?>&view=class<?php echo isset($_GET['week_id']) ? '&week_id='.$_GET['week_id'] : ''; ?>"
               class="view-btn <?php echo $viewType == 'class' ? 'active' : ''; ?>">
                Lớp <?php echo $student['ten_lop']; ?>
            </a>
            <a href="?tab=<?php echo $tab; ?>&view=grade<?php echo isset($_GET['week_id']) ? '&week_id='.$_GET['week_id'] : ''; ?>"
               class="view-btn <?php echo $viewType == 'grade' ? 'active' : ''; ?>">
                Khối <?php echo $student['khoi']; ?>
            </a>
        </div>

        <!-- Week Selector (chỉ hiện khi tab = week) -->
        <?php if ($tab == 'week' && !empty($weekList)): ?>
        <div class="week-selector">
            <select class="week-select" onchange="location.href='?tab=week&view=<?php echo $viewType; ?>&week_id='+this.value">
                <?php foreach ($weekList as $w): ?>
                    <?php
                    $isSelected = ($selectedWeek && $selectedWeek['id'] == $w['id']);
                    $weekStatus = '';
                    if ($w['trang_thai'] == 1) $weekStatus = ' (Đang diễn ra)';
                    elseif ($w['trang_thai'] == 0) $weekStatus = ' (Chưa bắt đầu)';
                    ?>
                    <option value="<?php echo $w['id']; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                        <?php echo $w['ten_tuan']; ?> (<?php echo formatWeekDate($w); ?>)<?php echo $weekStatus; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Ranking List -->
        <div class="ranking-card">
            <?php if (empty($rankings)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <div>Chưa có dữ liệu xếp hạng</div>
                </div>
            <?php else: ?>
                <?php foreach ($rankings as $index => $r): ?>
                    <?php
                    $rank = $index + 1;
                    $rankClass = 'rank-other';
                    if ($rank == 1) $rankClass = 'rank-1';
                    elseif ($rank == 2) $rankClass = 'rank-2';
                    elseif ($rank == 3) $rankClass = 'rank-3';

                    $isMe = ($r['hoc_sinh_id'] == $student['id']);
                    $avatar = ($r['gioi_tinh'] == 1) ? '👦' : '👧';
                    $score = isset($r['diem_cao_nhat']) ? $r['diem_cao_nhat'] : $r['tong_diem'];
                    $attempts = isset($r['so_lan_thi']) ? $r['so_lan_thi'] : 0;
                    ?>
                    <div class="ranking-item <?php echo $isMe ? 'is-me' : ''; ?>">
                        <div class="rank-number <?php echo $rankClass; ?>">
                            <?php if ($rank <= 3): ?>
                                <?php echo $rank == 1 ? '🥇' : ($rank == 2 ? '🥈' : '🥉'); ?>
                            <?php else: ?>
                                <?php echo $rank; ?>
                            <?php endif; ?>
                        </div>

                        <div class="rank-info">
                            <div class="rank-name">
                                <?php echo $avatar; ?> <?php echo htmlspecialchars($r['ho_ten']); ?>
                                <?php if ($isMe): ?>
                                    <span style="color: #667eea;">(Bạn)</span>
                                <?php endif; ?>
                            </div>
                            <div class="rank-class">
                                <?php echo $r['ten_lop']; ?>
                                <?php if ($tab != 'week' && isset($r['so_tuan_thi'])): ?>
                                    • <?php echo $r['so_tuan_thi']; ?> tuần
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rank-score">
                            <div class="score-value"><?php echo number_format($score, 1); ?></div>
                            <div class="score-label">
                                <?php if ($tab == 'week'): ?>
                                    điểm
                                    <?php if ($attempts > 0): ?>
                                        <span class="attempts-badge"><?php echo $attempts; ?> lần</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    tổng điểm
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
