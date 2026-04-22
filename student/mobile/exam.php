<?php
/**
 * ==============================================
 * MOBILE - LÀM BÀI THI
 * Giao diện 1 câu hỏi/màn hình, tối ưu cho mobile
 * Logic đồng bộ với Desktop exam.php
 * ==============================================
 */

require_once '../../includes/config.php';
require_once '../../includes/device.php';

// Redirect sang desktop nếu là thiết bị desktop
$desktopUrl = BASE_URL . '/student/exam.php';
if (isset($_GET['id'])) $desktopUrl .= '?id=' . intval($_GET['id']);
if (isset($_GET['session'])) $desktopUrl .= (strpos($desktopUrl, '?') !== false ? '&' : '?') . 'session=' . $_GET['session'];
redirectIfDesktop($desktopUrl);

if (!isStudentLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$student = getCurrentStudent();
if (!$student) {
    header('Location: ' . BASE_URL . '/logout.php');
    exit;
}

$deThiId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($deThiId <= 0) {
    header('Location: ' . BASE_URL . '/student/mobile/index.php');
    exit;
}

$conn = getDBConnection();
require_once '../../includes/week_helper.php';

// Lấy tuần hiện tại
$currentWeek = getCurrentWeek();

// Lấy thông tin đề thi
$stmtDT = $conn->prepare("
    SELECT dt.*, mh.ten_mon, mh.icon, mh.mau_sac
    FROM de_thi dt
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    WHERE dt.id = ? AND (dt.lop_id = ? OR dt.lop_id IS NULL) AND dt.trang_thai = 1
");
$stmtDT->execute(array($deThiId, $student['lop_id']));
$deThi = $stmtDT->fetch();

if (!$deThi) {
    header('Location: ' . BASE_URL . '/student/mobile/index.php');
    exit;
}

// Kiểm tra nếu là đề thi chính thức - cần kiểm tra số lần thi còn lại và chế độ mở
$isChinhThuc = isset($deThi['is_chinh_thuc']) ? (int)$deThi['is_chinh_thuc'] : 0;
$soLanToiDa = isset($deThi['so_lan_thi_toi_da_tuan']) ? (int)$deThi['so_lan_thi_toi_da_tuan'] : 3;
$soLanDaThi = 0;
$hetLuotThi = false;

// Ưu tiên 1: Lấy chế độ mở từ lịch thi (exam-schedule)
// Ưu tiên 2: Nếu không có, lấy từ cài đặt hệ thống
$cheDoMo = isset($deThi['che_do_mo']) && !empty($deThi['che_do_mo']) ? $deThi['che_do_mo'] : null;

// Nếu không có chế độ mở từ lịch thi, lấy từ cài đặt hệ thống
if ($cheDoMo === null && $isChinhThuc) {
    $stmtSetting = $conn->prepare("SELECT gia_tri FROM cau_hinh WHERE ma_cau_hinh = 'che_do_mo_mac_dinh'");
    $stmtSetting->execute();
    $settingResult = $stmtSetting->fetch();
    $cheDoMoMacDinh = $settingResult ? $settingResult['gia_tri'] : 'cuoi_tuan';

    // Chuyển đổi từ cài đặt hệ thống sang chế độ mở
    if ($cheDoMoMacDinh == 'luon_mo') {
        $cheDoMo = 'mo_ngay';
    } else {
        $cheDoMo = 'theo_lich';
    }
}

// Mặc định nếu vẫn null
if ($cheDoMo === null) {
    $cheDoMo = 'theo_lich';
}

// Kiểm tra chế độ mở cho đề thi chính thức
if ($isChinhThuc && $cheDoMo == 'theo_lich' && !isset($_GET['session'])) {
    $stmtNgayMo = $conn->prepare("SELECT gia_tri FROM cau_hinh WHERE ma_cau_hinh = 'ngay_mo_thi'");
    $stmtNgayMo->execute();
    $ngayMoResult = $stmtNgayMo->fetch();
    $ngayMoThi = $ngayMoResult ? $ngayMoResult['gia_tri'] : 't7,cn';

    $dayMap = array('cn' => 0, 't2' => 1, 't3' => 2, 't4' => 3, 't5' => 4, 't6' => 5, 't7' => 6);
    $allowedDays = array();
    foreach (explode(',', $ngayMoThi) as $day) {
        $day = trim(strtolower($day));
        if (isset($dayMap[$day])) {
            $allowedDays[] = $dayMap[$day];
        }
    }

    $dayOfWeek = (int)date('w');
    if (!in_array($dayOfWeek, $allowedDays)) {
        $dayNames = array('Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7');
        $allowedDayNames = array();
        foreach ($allowedDays as $d) {
            $allowedDayNames[] = $dayNames[$d];
        }
        $_SESSION['error_message'] = 'Đề thi chính thức này chỉ mở vào: ' . implode(', ', $allowedDayNames) . '!';
        header('Location: ' . BASE_URL . '/student/mobile/index.php');
        exit;
    }
}

if ($isChinhThuc && $currentWeek) {
    $stmtCount = $conn->prepare("
        SELECT COUNT(*) as count
        FROM bai_lam
        WHERE hoc_sinh_id = ?
        AND de_thi_id = ?
        AND tuan_id = ?
        AND is_chinh_thuc = 1
        AND trang_thai = 'hoan_thanh'
    ");
    $stmtCount->execute(array($student['id'], $deThiId, $currentWeek['id']));
    $countResult = $stmtCount->fetch();
    $soLanDaThi = $countResult ? (int)$countResult['count'] : 0;

    if ($soLanDaThi >= $soLanToiDa) {
        $hetLuotThi = true;
    }
}

// Kiểm tra session bài thi
$existingSession = null;
if (isset($_GET['session'])) {
    $sessionToken = $_GET['session'];
    $stmtCheck = $conn->prepare("
        SELECT * FROM bai_lam
        WHERE session_token = ? AND hoc_sinh_id = ? AND trang_thai = 'dang_lam'
    ");
    $stmtCheck->execute(array($sessionToken, $student['id']));
    $existingSession = $stmtCheck->fetch();
}

// ============ TRANG CHUẨN BỊ THI ============
if ($isChinhThuc && $hetLuotThi && !isset($_GET['session'])) {
    $_SESSION['error_message'] = 'Bạn đã hết lượt thi chính thức cho đề thi này trong tuần!';
    header('Location: ' . BASE_URL . '/student/mobile/index.php');
    exit;
}

if (!$existingSession && !isset($_POST['start_exam'])):
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Chuẩn bị thi - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        :root {
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        .prep-container {
            min-height: 100vh;
            padding: 20px;
            padding-top: calc(20px + var(--safe-top));
            padding-bottom: calc(20px + var(--safe-bottom));
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .back-btn {
            position: absolute;
            top: calc(16px + var(--safe-top));
            left: 16px;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
            text-decoration: none;
        }

        .prep-card {
            background: white;
            border-radius: 24px;
            padding: 32px 24px;
            width: 100%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .prep-icon { font-size: 4rem; margin-bottom: 16px; }
        .prep-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .prep-subject {
            color: #6B7280;
            font-size: 1rem;
            margin-bottom: 24px;
        }

        .prep-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .prep-stat {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 16px 8px;
        }

        .prep-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }

        .prep-stat-label {
            color: #6B7280;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .prep-warning {
            background: #FEF3C7;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }

        .prep-warning h4 {
            color: #92400E;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .prep-warning ul {
            color: #92400E;
            font-size: 0.85rem;
            line-height: 1.6;
            padding-left: 20px;
            margin: 0;
        }

        .start-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }

        .back-link {
            display: block;
            margin-top: 16px;
            color: #6B7280;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
        }

        <?php if ($isChinhThuc): ?>
        .official-badge {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 2px solid #FFD700;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .official-badge .badge-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: #92400E;
            margin-bottom: 8px;
        }

        .official-badge .remaining {
            font-size: 1.8rem;
            font-weight: 700;
            color: <?php echo ($soLanToiDa - $soLanDaThi) > 1 ? '#10B981' : '#EF4444'; ?>;
        }

        .official-badge .remaining-label {
            font-size: 0.8rem;
            color: #B45309;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="prep-container">
        <a href="<?php echo BASE_URL; ?>/student/mobile/index.php" class="back-btn">←</a>

        <div class="prep-card">
            <div class="prep-icon">📝</div>
            <h1 class="prep-title"><?php echo htmlspecialchars($deThi['ten_de']); ?></h1>
            <p class="prep-subject"><?php echo htmlspecialchars($deThi['ten_mon']); ?></p>

            <?php if ($isChinhThuc): ?>
            <div class="official-badge">
                <div class="badge-title">⭐ BÀI THI CHÍNH THỨC</div>
                <div class="remaining"><?php echo $soLanToiDa - $soLanDaThi; ?>/<?php echo $soLanToiDa; ?></div>
                <div class="remaining-label">lượt thi còn lại</div>
            </div>
            <?php endif; ?>

            <div class="prep-stats">
                <div class="prep-stat">
                    <div class="prep-stat-value"><?php echo $deThi['so_cau']; ?></div>
                    <div class="prep-stat-label">Câu hỏi</div>
                </div>
                <div class="prep-stat">
                    <div class="prep-stat-value"><?php echo $deThi['thoi_gian_cau']; ?>s</div>
                    <div class="prep-stat-label">Mỗi câu</div>
                </div>
                <div class="prep-stat">
                    <div class="prep-stat-value"><?php echo formatTime($deThi['so_cau'] * $deThi['thoi_gian_cau']); ?></div>
                    <div class="prep-stat-label">Tổng</div>
                </div>
            </div>

            <div class="prep-warning">
                <h4>⚠️ Lưu ý:</h4>
                <ul>
                    <li>Mỗi câu có thời gian giới hạn</li>
                    <li>Hết giờ sẽ tự động chuyển câu</li>
                    <li>Chọn đáp án bằng cách chạm vào</li>
                </ul>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="start_exam" value="1">
                <button type="submit" class="start-btn">
                    ▶️ Bắt đầu làm bài
                </button>
            </form>

            <a href="<?php echo BASE_URL; ?>/student/mobile/index.php" class="back-link">
                ← Quay lại
            </a>
        </div>
    </div>
</body>
</html>
<?php
exit;
endif;

// ============ XỬ LÝ BẮT ĐẦU THI ============
if (isset($_POST['start_exam'])) {
    // Kiểm tra lại số lượt thi chính thức trước khi bắt đầu
    if ($isChinhThuc && $currentWeek) {
        $stmtRecheck = $conn->prepare("
            SELECT COUNT(*) as count
            FROM bai_lam
            WHERE hoc_sinh_id = ?
            AND de_thi_id = ?
            AND tuan_id = ?
            AND is_chinh_thuc = 1
            AND trang_thai = 'hoan_thanh'
        ");
        $stmtRecheck->execute(array($student['id'], $deThiId, $currentWeek['id']));
        $recheckResult = $stmtRecheck->fetch();
        if ($recheckResult && (int)$recheckResult['count'] >= $soLanToiDa) {
            $_SESSION['error_message'] = 'Bạn đã hết lượt thi chính thức cho đề thi này trong tuần!';
            header('Location: ' . BASE_URL . '/student/mobile/index.php');
            exit;
        }
    }

    $sessionToken = generateExamToken();

    // Lấy câu hỏi
    $stmtCH = $conn->prepare("
        SELECT id FROM cau_hoi
        WHERE de_thi_id = ? AND trang_thai = 1
        ORDER BY " . ($deThi['random_cau_hoi'] ? "RAND()" : "thu_tu ASC") . "
        LIMIT ?
    ");
    $stmtCH->execute(array($deThiId, $deThi['so_cau']));
    $cauHoiIds = $stmtCH->fetchAll(PDO::FETCH_COLUMN);

    if (empty($cauHoiIds)) {
        $_SESSION['error_message'] = 'Đề thi chưa có câu hỏi!';
        header('Location: ' . BASE_URL . '/student/mobile/index.php');
        exit;
    }

    // Tạo bài làm
    $tuanId = $currentWeek ? $currentWeek['id'] : null;
    $stmtBL = $conn->prepare("
        INSERT INTO bai_lam (hoc_sinh_id, de_thi_id, thoi_gian_bat_dau, tong_cau, trang_thai, session_token, is_chinh_thuc, tuan_id)
        VALUES (?, ?, NOW(), ?, 'dang_lam', ?, ?, ?)
    ");
    $stmtBL->execute(array($student['id'], $deThiId, count($cauHoiIds), $sessionToken, $isChinhThuc, $tuanId));
    $bailamId = $conn->lastInsertId();

    // Tạo chi tiết bài làm cho từng câu hỏi
    $thuTu = 1;
    foreach ($cauHoiIds as $chId) {
        $stmtCT = $conn->prepare("
            INSERT INTO chi_tiet_bai_lam (bai_lam_id, cau_hoi_id, thu_tu_cau)
            VALUES (?, ?, ?)
        ");
        $stmtCT->execute(array($bailamId, $chId, $thuTu));
        $thuTu++;
    }

    logActivity('hoc_sinh', $student['id'], 'Bắt đầu thi (Mobile)', 'Đề: ' . $deThi['ten_de']);
    header('Location: ' . BASE_URL . '/student/mobile/exam.php?id=' . $deThiId . '&session=' . $sessionToken);
    exit;
}

// ============ TRANG LÀM BÀI THI ============
// Kiểm tra session token
if (!isset($_GET['session']) || empty($_GET['session'])) {
    header('Location: ' . BASE_URL . '/student/mobile/index.php');
    exit;
}
$sessionToken = $_GET['session'];

$stmtBL = $conn->prepare("SELECT * FROM bai_lam WHERE session_token = ? AND hoc_sinh_id = ?");
$stmtBL->execute(array($sessionToken, $student['id']));
$baiLam = $stmtBL->fetch();

if (!$baiLam || $baiLam['trang_thai'] !== 'dang_lam') {
    header('Location: ' . BASE_URL . '/student/mobile/index.php');
    exit;
}

// Lấy danh sách câu hỏi từ chi_tiet_bai_lam (giống desktop)
$stmtCH = $conn->prepare("
    SELECT ch.*, ctbl.thu_tu_cau, ctbl.dap_an_chon, ctbl.id as chi_tiet_id
    FROM chi_tiet_bai_lam ctbl
    JOIN cau_hoi ch ON ctbl.cau_hoi_id = ch.id
    WHERE ctbl.bai_lam_id = ?
    ORDER BY ctbl.thu_tu_cau ASC
");
$stmtCH->execute(array($baiLam['id']));
$cauHoiList = $stmtCH->fetchAll();

$questionsJson = json_encode($cauHoiList, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Đang làm bài - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body {
            font-family: 'Inter', sans-serif;
            background: #F3F4F6;
            min-height: 100vh;
            overflow-x: hidden;
        }
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --text: #1F2937;
            --text-light: #6B7280;
            --bg: #F3F4F6;
            --border: #E5E7EB;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        /* Header */
        .exam-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 16px 20px;
            padding-top: calc(16px + var(--safe-top));
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .exam-header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .exam-title {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.95;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 60%;
        }

        .timer {
            background: white;
            color: var(--text);
            padding: 8px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .timer.warning {
            background: var(--warning);
            color: white;
        }

        .timer.danger {
            background: var(--danger);
            color: white;
            animation: pulse 0.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .exam-progress {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .question-num {
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
        }

        .progress-bar {
            flex: 1;
            height: 6px;
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar .fill {
            height: 100%;
            background: white;
            border-radius: 3px;
            transition: width 0.3s;
        }

        /* Question Dots */
        .question-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
            padding: 10px 16px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            margin-top: 10px;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            transition: all 0.2s;
        }

        .dot.current {
            background: white;
            transform: scale(1.4);
        }

        .dot.answered {
            background: var(--success);
        }

        /* Body */
        .exam-body {
            padding-top: calc(140px + var(--safe-top));
            padding-bottom: calc(90px + var(--safe-bottom));
            min-height: 100vh;
        }

        .question-container {
            padding: 20px;
        }

        .question-text {
            font-size: 1.2rem;
            font-weight: 700;
            line-height: 1.5;
            margin-bottom: 16px;
            text-align: center;
            color: var(--text);
        }

        .question-image {
            max-width: 100%;
            border-radius: 12px;
            margin: 16px auto;
            display: block;
        }

        .answer-list {
            margin-top: 20px;
        }

        .answer-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: white;
            border: 2px solid var(--border);
            border-radius: 14px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .answer-option:active {
            transform: scale(0.98);
        }

        .answer-option.selected {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.1);
        }

        .answer-option .letter {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .answer-option.selected .letter {
            background: var(--success);
        }

        .answer-option .text {
            font-weight: 600;
            color: var(--text);
            line-height: 1.4;
            font-size: 1rem;
        }

        /* Footer */
        .exam-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 20px;
            padding-bottom: calc(12px + var(--safe-bottom));
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 12px;
            z-index: 100;
        }

        .nav-btn {
            flex: 1;
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: inherit;
        }

        .nav-btn.prev {
            background: var(--bg);
            color: var(--text);
        }

        .nav-btn.next {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .nav-btn:disabled {
            opacity: 0.5;
        }

        .nav-btn.submit {
            background: var(--success);
        }

        /* Review Mode */
        .review-mode .exam-header {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
        }

        .review-nav-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 16px;
            padding-bottom: calc(12px + var(--safe-bottom));
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: none;
            gap: 10px;
            z-index: 100;
        }

        .review-mode .review-nav-bar {
            display: flex;
        }

        .review-mode .exam-footer {
            display: none;
        }

        .review-btn {
            flex: 1;
            padding: 14px 12px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
        }

        .review-btn.nav {
            background: var(--bg);
            color: var(--text);
        }

        .review-btn.nav:disabled {
            opacity: 0.5;
        }

        .review-btn.submit {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }

        .review-timer-total {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .review-timer-total.critical {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            animation: pulse 0.5s infinite;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            padding: 20px;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 28px 24px;
            max-width: 340px;
            width: 100%;
            text-align: center;
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        .modal-icon {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .modal-desc {
            color: var(--text-light);
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .modal-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
        }

        .modal-stat-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .modal-stat-value.answered { color: var(--success); }
        .modal-stat-value.unanswered { color: var(--danger); }

        .modal-stat-label {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
        }

        /* Score Modal */
        .score-display {
            font-size: 4rem;
            font-weight: 700;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 4px;
        }

        .score-label {
            font-size: 1.1rem;
            color: var(--text-light);
            font-weight: 600;
        }

        .score-detail {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }

        .score-item-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .score-item-value.correct { color: var(--success); }
        .score-item-value.wrong { color: var(--danger); }

        .score-item-label {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<!-- Header -->
<div class="exam-header">
    <div class="exam-header-top">
        <div class="exam-title"><?php echo getSubjectIcon($deThi['icon']); ?> <?php echo htmlspecialchars($deThi['ten_de']); ?></div>
        <div class="timer" id="timer">⏱️ <span id="timerText"><?php echo formatTime($deThi['thoi_gian_cau']); ?></span></div>
    </div>
    <div class="exam-progress">
        <span class="question-num" id="questionNum">1/<?php echo count($cauHoiList); ?></span>
        <div class="progress-bar">
            <div class="fill" id="progressFill" style="width: <?php echo (1/count($cauHoiList)*100); ?>%"></div>
        </div>
    </div>
    <div class="question-dots" id="questionDots">
        <?php for ($i = 0; $i < count($cauHoiList); $i++): ?>
        <div class="dot<?php echo $i === 0 ? ' current' : ''; ?>" data-index="<?php echo $i; ?>"></div>
        <?php endfor; ?>
    </div>
</div>

<!-- Body -->
<div class="exam-body">
    <div class="question-container" id="questionContainer">
        <!-- Rendered by JS -->
    </div>
</div>

<!-- Footer - Chỉ có nút "Tiếp" trong normal mode (không được quay lại) -->
<div class="exam-footer">
    <button class="nav-btn next" id="btnNext" onclick="nextQuestion()" style="flex:1;">Tiếp ›</button>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-content">
        <div class="modal-icon">📋</div>
        <div class="modal-title">Hoàn thành bài thi!</div>
        <div class="modal-desc">Bạn có muốn xem lại bài trước khi nộp không?</div>
        <div class="modal-stats">
            <div>
                <div class="modal-stat-value answered" id="answeredCount">0</div>
                <div class="modal-stat-label">Đã trả lời</div>
            </div>
            <div>
                <div class="modal-stat-value unanswered" id="unansweredCount">0</div>
                <div class="modal-stat-label">Chưa trả lời</div>
            </div>
        </div>
        <div class="modal-buttons">
            <button class="btn btn-primary" onclick="enterReviewMode()">👁️ Xem lại</button>
            <button class="btn btn-success" onclick="submitExamNow()">✓ Nộp bài</button>
        </div>
    </div>
</div>

<!-- Score Modal -->
<div class="modal-overlay" id="scoreModal">
    <div class="modal-content">
        <div class="modal-icon" id="scoreEmoji">🌟</div>
        <div class="modal-title" id="scoreTitle">Xuất sắc!</div>
        <div class="score-display" id="scoreValue">10</div>
        <div class="score-label">điểm</div>
        <div class="score-detail">
            <div>
                <div class="score-item-value correct" id="correctCount">0</div>
                <div class="score-item-label">Đúng ✓</div>
            </div>
            <div>
                <div class="score-item-value wrong" id="wrongCount">0</div>
                <div class="score-item-label">Sai ✗</div>
            </div>
        </div>
        <div class="modal-buttons" style="margin-top: 20px;">
            <button class="btn btn-success" onclick="goToResult()">📊 Xem chi tiết</button>
        </div>
    </div>
</div>

<!-- Review Navigation Bar -->
<div class="review-nav-bar" id="reviewNavBar">
    <button class="review-btn nav" id="btnPrevReview" onclick="reviewPrev()">← Trước</button>
    <div class="review-timer-total" id="reviewTimerTotal">⏱️ <span id="reviewTimerDisplay">00:00</span></div>
    <button class="review-btn nav" id="btnNextReview" onclick="reviewNext()">Sau →</button>
    <button class="review-btn submit" onclick="showSubmitConfirm()">✓ Nộp</button>
</div>

<!-- Loading -->
<div id="loading" class="loading-overlay">
    <div class="spinner"></div>
    <div style="margin-top:16px;font-weight:600;color:#6B7280;">Đang nộp bài...</div>
</div>

<script>
var BASE_URL = '<?php echo BASE_URL; ?>';

var EXAM = {
    id: <?php echo $deThiId; ?>,
    session: '<?php echo $sessionToken; ?>',
    questions: <?php echo $questionsJson; ?>,
    timePerQ: <?php echo $deThi['thoi_gian_cau']; ?>
};

var currentIndex = 0;
var answers = {};
var timer = null;
var timeLeft = EXAM.timePerQ;
var qStartTime = null;
var examFinished = false;

// Review mode variables
var isReviewMode = false;
var totalTimeUsed = 0;
var reviewTimer = null;

function formatTime(sec) {
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderQuestion() {
    var q = EXAM.questions[currentIndex];
    qStartTime = new Date();

    var html = '<div class="question-text">' + escapeHtml(q.noi_dung) + '</div>';

    if (q.hinh_anh) {
        html += '<img src="' + q.hinh_anh + '" class="question-image" alt="">';
    }

    html += '<div class="answer-list">';
    var opts = ['A', 'B', 'C', 'D'];
    var keys = ['dap_an_a', 'dap_an_b', 'dap_an_c', 'dap_an_d'];

    for (var i = 0; i < 4; i++) {
        var sel = answers[q.id] === opts[i] ? ' selected' : '';
        html += '<div class="answer-option' + sel + '" onclick="selectAnswer(\'' + opts[i] + '\')">';
        html += '<div class="letter">' + opts[i] + '</div>';
        html += '<div class="text">' + escapeHtml(q[keys[i]]) + '</div>';
        html += '</div>';
    }
    html += '</div>';

    document.getElementById('questionContainer').innerHTML = html;

    // Update progress
    document.getElementById('questionNum').textContent = (currentIndex + 1) + '/' + EXAM.questions.length;
    document.getElementById('progressFill').style.width = ((currentIndex + 1) / EXAM.questions.length * 100) + '%';

    // Update dots
    var dots = document.querySelectorAll('.dot');
    dots.forEach(function(dot, idx) {
        dot.classList.remove('current');
        if (idx === currentIndex) dot.classList.add('current');
        if (answers[EXAM.questions[idx].id]) {
            dot.classList.add('answered');
        }
    });

    // Update nav buttons
    if (!isReviewMode) {
        var btnNext = document.getElementById('btnNext');
        if (currentIndex === EXAM.questions.length - 1) {
            btnNext.innerHTML = 'Hoàn thành ✓';
            btnNext.classList.add('submit');
            btnNext.classList.remove('next');
        } else {
            btnNext.innerHTML = 'Tiếp ›';
            btnNext.classList.remove('submit');
            btnNext.classList.add('next');
        }
    } else {
        document.getElementById('btnPrevReview').disabled = currentIndex === 0;
        document.getElementById('btnNextReview').disabled = currentIndex === EXAM.questions.length - 1;
    }
}

function selectAnswer(ans) {
    var q = EXAM.questions[currentIndex];
    var timeSpent = Math.round((new Date() - qStartTime) / 1000);
    answers[q.id] = ans;

    // Update UI
    document.querySelectorAll('.answer-option').forEach(function(el) {
        el.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');

    // Update dot
    var dotEl = document.querySelector('.dot[data-index="' + currentIndex + '"]');
    if (dotEl) dotEl.classList.add('answered');

    // Save to server
    var xhr = new XMLHttpRequest();
    xhr.open('POST', BASE_URL + '/api/submit_answer.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(JSON.stringify({
        session_token: EXAM.session,
        question_id: q.id,
        answer: ans,
        time_spent: timeSpent
    }));

    // KHÔNG tự động chuyển câu - để học sinh tự bấm "Tiếp" (giống desktop)
    // Điều này giúp học sinh có thời gian xem lại đáp án đã chọn
}

function startTimer() {
    timeLeft = EXAM.timePerQ;
    updateTimerDisplay();

    timer = setInterval(function() {
        timeLeft--;
        totalTimeUsed++;
        updateTimerDisplay();

        if (timeLeft <= 0) {
            clearInterval(timer);
            handleTimeout();
        }
    }, 1000);
}

function updateTimerDisplay() {
    var timerEl = document.getElementById('timer');
    document.getElementById('timerText').textContent = formatTime(timeLeft);

    timerEl.classList.remove('warning', 'danger');
    if (timeLeft <= 5) {
        timerEl.classList.add('danger');
    } else if (timeLeft <= 10) {
        timerEl.classList.add('warning');
    }
}

function handleTimeout() {
    var q = EXAM.questions[currentIndex];
    if (!answers[q.id]) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', BASE_URL + '/api/submit_answer.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send(JSON.stringify({
            session_token: EXAM.session,
            question_id: q.id,
            answer: null,
            time_spent: EXAM.timePerQ
        }));
    }
    nextQuestion();
}

function prevQuestion() {
    // KHÔNG cho phép quay lại câu trước trong normal mode (theo yêu cầu đề thi)
    // Chỉ được quay lại trong review mode
    if (!isReviewMode) return;

    if (currentIndex > 0) {
        currentIndex--;
        renderQuestion();
    }
}

function nextQuestion() {
    clearInterval(timer);
    document.getElementById('timer').classList.remove('warning', 'danger');

    if (currentIndex < EXAM.questions.length - 1) {
        currentIndex++;
        renderQuestion();
        startTimer();
    } else {
        showReviewModal();
    }
}

// ============ REVIEW MODE ============

function showReviewModal() {
    clearInterval(timer);

    var answered = 0;
    for (var i = 0; i < EXAM.questions.length; i++) {
        if (answers[EXAM.questions[i].id]) answered++;
    }

    document.getElementById('answeredCount').textContent = answered;
    document.getElementById('unansweredCount').textContent = EXAM.questions.length - answered;
    document.getElementById('reviewModal').classList.add('show');
}

function hideReviewModal() {
    document.getElementById('reviewModal').classList.remove('show');
}

function enterReviewMode() {
    hideReviewModal();
    isReviewMode = true;
    currentIndex = 0;

    document.body.classList.add('review-mode');
    document.getElementById('timer').style.display = 'none';

    startReviewTimer();
    renderQuestion();
}

function startReviewTimer() {
    var totalTime = EXAM.questions.length * EXAM.timePerQ;
    var reviewTimeLeft = totalTime - totalTimeUsed;
    if (reviewTimeLeft < 0) reviewTimeLeft = 0;

    updateReviewTimerDisplay(reviewTimeLeft);

    reviewTimer = setInterval(function() {
        reviewTimeLeft--;
        updateReviewTimerDisplay(reviewTimeLeft);

        if (reviewTimeLeft <= 30) {
            document.getElementById('reviewTimerTotal').classList.add('critical');
        }

        if (reviewTimeLeft <= 0) {
            clearInterval(reviewTimer);
            alert('Hết thời gian! Bài thi đang được nộp...');
            submitExamNow();
        }
    }, 1000);
}

function updateReviewTimerDisplay(t) {
    document.getElementById('reviewTimerDisplay').textContent = formatTime(t);
}

function reviewPrev() {
    if (currentIndex > 0) {
        currentIndex--;
        renderQuestion();
    }
}

function reviewNext() {
    if (currentIndex < EXAM.questions.length - 1) {
        currentIndex++;
        renderQuestion();
    }
}

function showSubmitConfirm() {
    var answered = 0;
    for (var i = 0; i < EXAM.questions.length; i++) {
        if (answers[EXAM.questions[i].id]) answered++;
    }
    var unanswered = EXAM.questions.length - answered;

    if (unanswered > 0) {
        if (!confirm('Bạn còn ' + unanswered + ' câu chưa trả lời. Bạn có chắc muốn nộp bài?')) {
            return;
        }
    }
    submitExamNow();
}

function submitExamNow() {
    hideReviewModal();
    clearInterval(reviewTimer);
    clearInterval(timer);
    document.getElementById('loading').classList.add('show');
    examFinished = true;

    var xhr = new XMLHttpRequest();
    xhr.open('POST', BASE_URL + '/api/finish_exam.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            document.getElementById('loading').classList.remove('show');
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    showScoreModal(response);
                } catch (e) {
                    window.location.href = BASE_URL + '/student/mobile/result.php?session=' + EXAM.session;
                }
            } else {
                examFinished = false;
                alert('Có lỗi xảy ra!');
            }
        }
    };
    xhr.send(JSON.stringify({session_token: EXAM.session}));
}

function showScoreModal(response) {
    var score = response.score || 0;
    var correct = response.correct || 0;
    var total = response.total || EXAM.questions.length;
    var wrong = total - correct;

    var emoji, title;
    if (score >= 9) { emoji = '🌟'; title = 'Xuất sắc!'; }
    else if (score >= 7) { emoji = '👏'; title = 'Tốt lắm!'; }
    else if (score >= 5) { emoji = '👍'; title = 'Khá tốt!'; }
    else { emoji = '💪'; title = 'Cố gắng hơn nhé!'; }

    document.getElementById('scoreEmoji').textContent = emoji;
    document.getElementById('scoreTitle').textContent = title;
    document.getElementById('scoreValue').textContent = score;
    document.getElementById('correctCount').textContent = correct;
    document.getElementById('wrongCount').textContent = wrong;

    document.getElementById('scoreModal').classList.add('show');
}

function goToResult() {
    window.location.href = BASE_URL + '/student/mobile/result.php?session=' + EXAM.session;
}

// Prevent reload
window.addEventListener('beforeunload', function(e) {
    if (!examFinished) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// Swipe gestures - CHỈ hoạt động trong REVIEW MODE để tránh nộp bài nhầm
var touchStartX = 0;
document.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
}, false);

document.addEventListener('touchend', function(e) {
    // Chỉ cho phép swipe trong review mode để tránh học sinh vô tình nộp bài
    if (!isReviewMode) return;

    var diff = e.changedTouches[0].screenX - touchStartX;
    if (Math.abs(diff) > 50) {
        if (diff > 0) {
            reviewPrev();
        } else {
            reviewNext();
        }
    }
}, false);

// Init
document.addEventListener('DOMContentLoaded', function() {
    renderQuestion();
    startTimer();
});
</script>

</body>
</html>
