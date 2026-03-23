<?php
/**
 * ==============================================
 * TRANG THI ONLINE - FULLSCREEN DESKTOP APP
 * Giao diện không scroll - phù hợp học sinh tiểu học
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/device.php';

// Redirect sang mobile nếu là thiết bị di động
$mobileUrl = BASE_URL . '/student/mobile/exam.php';
if (isset($_GET['id'])) $mobileUrl .= '?id=' . intval($_GET['id']);
if (isset($_GET['session'])) $mobileUrl .= (strpos($mobileUrl, '?') !== false ? '&' : '?') . 'session=' . $_GET['session'];
redirectIfMobile($mobileUrl);

if (!isStudentLoggedIn()) {
    redirect('login.php');
}

$student = getCurrentStudent();
if (!$student) {
    redirect('logout.php');
}

$deThiId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($deThiId <= 0) {
    redirect('student/dashboard.php');
}

$conn = getDBConnection();
require_once '../includes/week_helper.php';

// Lấy tuần hiện tại
$currentWeek = getCurrentWeek();

// Lấy thông tin đề thi
$stmtDT = $conn->prepare("
    SELECT dt.*, mh.ten_mon, mh.mau_sac
    FROM de_thi dt
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    WHERE dt.id = ? AND dt.lop_id = ? AND dt.trang_thai = 1
");
$stmtDT->execute([$deThiId, $student['lop_id']]);
$deThi = $stmtDT->fetch();

if (!$deThi) {
    redirect('student/dashboard.php');
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
        $cheDoMo = 'theo_lich'; // cuoi_tuan hoặc theo_gio đều dùng theo_lich
    }
}

// Mặc định nếu vẫn null
if ($cheDoMo === null) {
    $cheDoMo = 'theo_lich';
}

// Kiểm tra chế độ mở cho đề thi chính thức
if ($isChinhThuc && $cheDoMo == 'theo_lich' && !isset($_GET['session'])) {
    // Lấy danh sách ngày mở thi từ cài đặt (hoặc mặc định T7, CN)
    $stmtNgayMo = $conn->prepare("SELECT gia_tri FROM cau_hinh WHERE ma_cau_hinh = 'ngay_mo_thi'");
    $stmtNgayMo->execute();
    $ngayMoResult = $stmtNgayMo->fetch();
    $ngayMoThi = $ngayMoResult ? $ngayMoResult['gia_tri'] : 't7,cn';

    // Chuyển đổi ngày trong tuần
    $dayMap = array('cn' => 0, 't2' => 1, 't3' => 2, 't4' => 3, 't5' => 4, 't6' => 5, 't7' => 6);
    $allowedDays = array();
    foreach (explode(',', $ngayMoThi) as $day) {
        $day = trim(strtolower($day));
        if (isset($dayMap[$day])) {
            $allowedDays[] = $dayMap[$day];
        }
    }

    // Kiểm tra ngày hiện tại
    $dayOfWeek = (int)date('w'); // 0 = CN, 1-6 = T2-T7
    if (!in_array($dayOfWeek, $allowedDays)) {
        $dayNames = array('Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7');
        $allowedDayNames = array();
        foreach ($allowedDays as $d) {
            $allowedDayNames[] = $dayNames[$d];
        }
        $_SESSION['error_message'] = 'Đề thi chính thức này chỉ mở vào: ' . implode(', ', $allowedDayNames) . '!';
        redirect('student/dashboard.php');
    }
}

if ($isChinhThuc && $currentWeek) {
    // Đếm số lần thi chính thức trong tuần này
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
    $stmtCheck->execute([$sessionToken, $student['id']]);
    $existingSession = $stmtCheck->fetch();
}

// ============ TRANG CHUẨN BỊ THI ============
// Nếu hết lượt thi chính thức -> redirect về dashboard
if ($isChinhThuc && $hetLuotThi && !isset($_GET['session'])) {
    $_SESSION['error_message'] = 'Bạn đã hết lượt thi chính thức cho đề thi này trong tuần!';
    redirect('student/dashboard.php');
}

if (!$existingSession && !isset($_POST['start_exam'])):
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chuẩn bị thi - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --vh: 1vh;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            overflow: hidden;
            font-family: 'Quicksand', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .app-container {
            display: flex;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            width: 100vw;
        }

        /* Sidebar nhỏ gọn */
        .sidebar-mini {
            width: clamp(60px, 8vw, 80px);
            background: rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: clamp(12px, 2vh, 20px) 0;
            flex-shrink: 0;
        }

        .back-btn {
            width: clamp(40px, 5vw, 50px);
            height: clamp(40px, 5vw, 50px);
            border-radius: clamp(10px, 1.2vw, 14px);
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            color: white;
            text-decoration: none;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }

        /* Main content */
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 3vw, 40px);
            overflow: auto;
        }

        /* Layout 2 cột khi có bài thi chính thức */
        .prep-layout {
            display: flex;
            gap: clamp(20px, 3vw, 40px);
            max-width: 1100px;
            width: 100%;
            max-height: 100%;
            align-items: stretch;
        }

        .prep-layout.single-column {
            max-width: min(600px, 95%);
        }

        .prep-card {
            background: white;
            border-radius: clamp(20px, 3vw, 30px);
            padding: clamp(24px, 4vw, 50px);
            flex: 1;
            min-width: 0;
            max-height: 100%;
            overflow-y: auto;
            text-align: center;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
        }

        .prep-layout.single-column .prep-card {
            width: 100%;
        }

        /* Cột cảnh báo bên phải */
        .prep-sidebar {
            width: clamp(280px, 30%, 350px);
            display: flex;
            flex-direction: column;
            gap: clamp(16px, 2vh, 24px);
            flex-shrink: 0;
        }

        .official-badge-card {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 3px solid #FFD700;
            border-radius: clamp(16px, 2vw, 24px);
            padding: clamp(20px, 3vh, 32px);
            text-align: center;
            box-shadow: 0 10px 40px rgba(255, 215, 0, 0.3);
        }

        .official-badge-card .badge-icon {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            margin-bottom: clamp(8px, 1.5vh, 16px);
        }

        .official-badge-card .badge-title {
            font-size: clamp(1.1rem, 2vw, 1.4rem);
            font-weight: 700;
            color: #92400E;
            margin-bottom: clamp(12px, 2vh, 20px);
        }

        .official-badge-card .remaining-count {
            background: white;
            border-radius: clamp(12px, 1.5vw, 16px);
            padding: clamp(12px, 2vh, 20px);
            margin-bottom: clamp(12px, 2vh, 16px);
        }

        .remaining-count .label {
            font-size: clamp(0.8rem, 1.2vw, 0.95rem);
            color: #92400E;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .remaining-count .value {
            font-size: clamp(2rem, 4vw, 2.8rem);
            font-weight: 700;
        }

        .remaining-count .value.low {
            color: #EF4444;
        }

        .remaining-count .value.ok {
            color: #10B981;
        }

        .official-badge-card .badge-note {
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
            color: #B45309;
            line-height: 1.5;
        }

        .warning-card {
            background: white;
            border-radius: clamp(16px, 2vw, 24px);
            padding: clamp(16px, 2.5vh, 24px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .warning-card h4 {
            color: #DC2626;
            font-weight: 700;
            margin-bottom: clamp(10px, 1.5vh, 16px);
            font-size: clamp(0.9rem, 1.4vw, 1.1rem);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .warning-card ul {
            color: #7F1D1D;
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
            line-height: 1.7;
            padding-left: clamp(16px, 2vw, 20px);
            margin: 0;
        }

        .warning-card ul li {
            margin-bottom: 6px;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .prep-layout {
                flex-direction: column;
                max-width: min(600px, 95%);
            }

            .prep-sidebar {
                width: 100%;
                flex-direction: row;
                flex-wrap: wrap;
            }

            .official-badge-card,
            .warning-card {
                flex: 1;
                min-width: 250px;
            }
        }

        @media (max-width: 600px) {
            .prep-sidebar {
                flex-direction: column;
            }

            .official-badge-card,
            .warning-card {
                min-width: auto;
            }
        }

        .prep-icon {
            font-size: clamp(3rem, 6vw, 5rem);
            margin-bottom: clamp(12px, 2vh, 20px);
        }

        .prep-title {
            font-size: clamp(1.2rem, 2.5vw, 1.8rem);
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }

        .prep-subject {
            color: #6B7280;
            font-size: clamp(0.9rem, 1.5vw, 1.1rem);
            margin-bottom: clamp(16px, 2.5vh, 30px);
        }

        .prep-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: clamp(8px, 1.5vw, 16px);
            margin-bottom: clamp(16px, 2.5vh, 30px);
        }

        .prep-stat {
            background: #F9FAFB;
            border-radius: clamp(10px, 1.2vw, 16px);
            padding: clamp(12px, 2vh, 20px);
        }

        .prep-stat-value {
            font-size: clamp(1.4rem, 3vw, 2rem);
            font-weight: 700;
            color: #667eea;
        }

        .prep-stat-label {
            color: #6B7280;
            font-size: clamp(0.75rem, 1.2vw, 0.9rem);
            font-weight: 600;
        }

        .prep-warning {
            background: #FEF3C7;
            border-radius: clamp(10px, 1.2vw, 16px);
            padding: clamp(12px, 2vh, 20px);
            margin-bottom: clamp(16px, 2.5vh, 30px);
            text-align: left;
        }

        .prep-warning h4 {
            color: #92400E;
            font-weight: 700;
            margin-bottom: clamp(6px, 1vh, 10px);
            font-size: clamp(0.85rem, 1.3vw, 1rem);
        }

        .prep-warning ul {
            color: #92400E;
            font-size: clamp(0.8rem, 1.2vw, 0.9rem);
            line-height: 1.6;
            padding-left: clamp(16px, 2vw, 20px);
        }

        .start-btn {
            width: 100%;
            padding: clamp(14px, 2.5vh, 20px);
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            color: white;
            border: none;
            border-radius: clamp(10px, 1.2vw, 16px);
            font-size: clamp(1rem, 2vw, 1.3rem);
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(8px, 1vw, 12px);
            transition: all 0.3s;
            font-family: inherit;
        }

        .start-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
        }

        .back-link {
            display: block;
            margin-top: clamp(12px, 2vh, 20px);
            color: #6B7280;
            font-weight: 600;
            font-size: clamp(0.85rem, 1.2vw, 1rem);
            text-decoration: none;
        }

        .back-link:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <script>
        // Fix viewport height cho mobile
        function setVH() {
            var vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', vh + 'px');
        }
        setVH();
        window.addEventListener('resize', setVH);
    </script>
    <div class="app-container">
        <aside class="sidebar-mini">
            <a href="<?php echo BASE_URL; ?>/student/dashboard.php" class="back-btn" title="Quay lại">←</a>
        </aside>

        <main class="main-content">
            <div class="prep-layout <?php echo $isChinhThuc ? '' : 'single-column'; ?>">
                <!-- Card chính bên trái -->
                <div class="prep-card">
                    <div class="prep-icon">📝</div>
                    <h1 class="prep-title"><?php echo htmlspecialchars($deThi['ten_de']); ?></h1>
                    <p class="prep-subject"><?php echo htmlspecialchars($deThi['ten_mon']); ?></p>

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
                            <div class="prep-stat-label">Tổng thời gian</div>
                        </div>
                    </div>

                    <?php if (!$isChinhThuc): ?>
                    <div class="prep-warning">
                        <h4>⚠️ Lưu ý quan trọng:</h4>
                        <ul>
                            <li>Mỗi câu có thời gian giới hạn, hết giờ sẽ tự động chuyển</li>
                            <li>Không thể quay lại câu trước đó</li>
                            <li>Chọn đáp án bằng cách click vào ô trả lời</li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="start_exam" value="1">
                        <button type="submit" class="start-btn">
                            <span>▶️</span> Bắt đầu làm bài
                        </button>
                    </form>

                    <a href="<?php echo BASE_URL; ?>/student/dashboard.php" class="back-link">
                        ← Quay lại Dashboard
                    </a>
                </div>

                <?php if ($isChinhThuc): ?>
                <!-- Sidebar cảnh báo bên phải -->
                <div class="prep-sidebar">
                    <div class="official-badge-card">
                        <div class="badge-icon">⭐</div>
                        <div class="badge-title">BÀI THI CHÍNH THỨC</div>
                        <div class="remaining-count">
                            <div class="label">Số lượt thi còn lại</div>
                            <div class="value <?php echo ($soLanToiDa - $soLanDaThi) > 1 ? 'ok' : 'low'; ?>">
                                <?php echo $soLanToiDa - $soLanDaThi; ?>/<?php echo $soLanToiDa; ?>
                            </div>
                        </div>
                        <div class="badge-note">
                            Điểm bài thi này sẽ được tính vào bảng xếp hạng chính thức của tuần!
                        </div>
                    </div>

                    <div class="warning-card">
                        <h4>🚨 Lưu ý quan trọng</h4>
                        <ul>
                            <li>Mỗi câu có thời gian giới hạn</li>
                            <li>Hết giờ sẽ tự động chuyển câu</li>
                            <li>Không thể quay lại câu trước</li>
                            <li>Chọn đáp án bằng cách click</li>
                            <li><strong>Đây là bài thi chính thức!</strong></li>
                            <li><strong>Hãy tập trung làm bài!</strong></li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
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
            redirect('student/dashboard.php');
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
    $stmtCH->execute([$deThiId, $deThi['so_cau']]);
    $cauHoiIds = $stmtCH->fetchAll(PDO::FETCH_COLUMN);

    if (empty($cauHoiIds)) {
        redirect('student/dashboard.php');
    }

    // Tạo bài làm (thêm is_chinh_thuc và tuan_id)
    $tuanId = $currentWeek ? $currentWeek['id'] : null;
    $stmtBL = $conn->prepare("
        INSERT INTO bai_lam (hoc_sinh_id, de_thi_id, thoi_gian_bat_dau, tong_cau, trang_thai, session_token, is_chinh_thuc, tuan_id)
        VALUES (?, ?, NOW(), ?, 'dang_lam', ?, ?, ?)
    ");
    $stmtBL->execute(array($student['id'], $deThiId, count($cauHoiIds), $sessionToken, $isChinhThuc, $tuanId));
    $bailamId = $conn->lastInsertId();

    // Tạo chi tiết bài làm
    $thuTu = 1;
    foreach ($cauHoiIds as $chId) {
        $stmtCT = $conn->prepare("
            INSERT INTO chi_tiet_bai_lam (bai_lam_id, cau_hoi_id, thu_tu_cau)
            VALUES (?, ?, ?)
        ");
        $stmtCT->execute([$bailamId, $chId, $thuTu]);
        $thuTu++;
    }

    logActivity('hoc_sinh', $student['id'], 'Bắt đầu thi', 'Đề: ' . $deThi['ten_de']);
    redirect('student/exam.php?id=' . $deThiId . '&session=' . $sessionToken);
}

// ============ TRANG LÀM BÀI THI ============
$stmtBL = $conn->prepare("SELECT * FROM bai_lam WHERE session_token = ? AND hoc_sinh_id = ?");
$stmtBL->execute([$sessionToken, $student['id']]);
$baiLam = $stmtBL->fetch();

if (!$baiLam || $baiLam['trang_thai'] !== 'dang_lam') {
    redirect('student/dashboard.php');
}

// Lấy danh sách câu hỏi
$stmtCH = $conn->prepare("
    SELECT ch.*, ctbl.thu_tu_cau, ctbl.dap_an_chon, ctbl.id as chi_tiet_id
    FROM chi_tiet_bai_lam ctbl
    JOIN cau_hoi ch ON ctbl.cau_hoi_id = ch.id
    WHERE ctbl.bai_lam_id = ?
    ORDER BY ctbl.thu_tu_cau ASC
");
$stmtCH->execute([$baiLam['id']]);
$cauHoiList = $stmtCH->fetchAll();

$questionsJson = json_encode($cauHoiList, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Đang làm bài - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --vh: 1vh;
            --padding: clamp(12px, 2vw, 20px);
            --header-height: clamp(50px, 8vh, 70px);
            --progress-height: clamp(50px, 8vh, 70px);
            --font-title: clamp(1rem, 2.5vw, 1.4rem);
            --font-question: clamp(1rem, 2.2vw, 1.3rem);
            --font-answer: clamp(0.9rem, 1.8vw, 1.1rem);
            --font-timer: clamp(1rem, 2vw, 1.2rem);
            --answer-padding: clamp(12px, 2vh, 20px);
            --answer-letter-size: clamp(36px, 5vh, 50px);
            --card-padding: clamp(20px, 3vw, 40px);
            --card-radius: clamp(16px, 2.5vw, 30px);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            overflow: hidden;
            font-family: 'Quicksand', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .app-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            width: 100vw;
            padding: var(--padding);
            overflow: hidden;
        }

        /* Header - cố định chiều cao */
        .exam-header {
            text-align: center;
            color: white;
            padding-bottom: clamp(8px, 1.5vh, 16px);
            flex-shrink: 0;
            height: var(--header-height);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .exam-title {
            font-size: var(--font-title);
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .exam-meta {
            opacity: 0.9;
            font-size: clamp(0.8rem, 1.5vw, 0.95rem);
        }

        /* Progress Bar - cố định chiều cao */
        .progress-section {
            display: flex;
            align-items: center;
            gap: clamp(10px, 2vw, 20px);
            background: rgba(255,255,255,0.15);
            padding: clamp(10px, 1.5vh, 16px) clamp(14px, 2vw, 24px);
            border-radius: clamp(12px, 1.5vw, 20px);
            flex-shrink: 0;
            height: var(--progress-height);
        }

        .question-counter {
            font-size: clamp(0.9rem, 1.8vw, 1.1rem);
            font-weight: 700;
            color: white;
            white-space: nowrap;
        }

        .progress-bar-wrap {
            flex: 1;
            height: clamp(8px, 1.2vh, 12px);
            background: rgba(255,255,255,0.3);
            border-radius: 6px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ECDC4, #44A8B3);
            border-radius: 6px;
            transition: width 0.3s;
        }

        .timer {
            display: flex;
            align-items: center;
            gap: clamp(4px, 0.8vw, 8px);
            background: white;
            color: #1F2937;
            padding: clamp(8px, 1.2vh, 12px) clamp(12px, 1.5vw, 20px);
            border-radius: clamp(10px, 1.2vw, 14px);
            font-weight: 700;
            font-size: var(--font-timer);
            min-width: clamp(80px, 10vw, 100px);
            justify-content: center;
        }

        .timer.warning {
            background: #FF6B6B;
            color: white;
            animation: pulse 0.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        /* Question Area - chiếm phần còn lại */
        .question-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-top: clamp(10px, 1.5vh, 20px);
            min-height: 0; /* Quan trọng cho flex shrink */
        }

        .question-card {
            background: white;
            border-radius: var(--card-radius);
            padding: var(--card-padding);
            max-width: min(900px, 95%);
            width: 100%;
            max-height: 100%;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
        }

        .question-text {
            font-size: var(--font-question);
            font-weight: 700;
            color: #1F2937;
            line-height: 1.5;
            margin-bottom: clamp(16px, 2.5vh, 30px);
            text-align: center;
            flex-shrink: 0;
        }

        .question-image {
            max-width: 100%;
            max-height: clamp(100px, 20vh, 200px);
            border-radius: clamp(10px, 1.2vw, 16px);
            margin: 0 auto clamp(12px, 2vh, 24px);
            display: block;
            object-fit: contain;
            flex-shrink: 0;
        }

        .answers-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: clamp(10px, 1.5vh, 16px);
            flex: 1;
            min-height: 0;
        }

        .answer-btn {
            display: flex;
            align-items: center;
            gap: clamp(10px, 1.2vw, 16px);
            padding: var(--answer-padding);
            background: #F9FAFB;
            border: 3px solid #E5E7EB;
            border-radius: clamp(10px, 1.2vw, 16px);
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
            min-height: clamp(60px, 10vh, 90px);
        }

        .answer-btn:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .answer-btn.selected {
            border-color: #4ECDC4;
            background: rgba(78, 205, 196, 0.15);
        }

        .answer-letter {
            width: var(--answer-letter-size);
            height: var(--answer-letter-size);
            min-width: var(--answer-letter-size);
            border-radius: clamp(10px, 1vw, 14px);
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1rem, 2vw, 1.3rem);
            font-weight: 700;
            flex-shrink: 0;
        }

        .answer-text {
            font-size: var(--font-answer);
            font-weight: 600;
            color: #1F2937;
            line-height: 1.3;
            word-break: break-word;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            color: white;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 16px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .hidden { display: none !important; }

        /* Toast */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 10000;
        }

        .toast {
            background: #1F2937;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            margin-top: 10px;
            font-weight: 600;
            font-size: clamp(0.85rem, 1.5vw, 1rem);
            animation: slideIn 0.3s;
        }

        .toast.warning { background: #F59E0B; }
        .toast.error { background: #EF4444; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background: white;
            border-radius: clamp(20px, 3vw, 30px);
            padding: clamp(24px, 4vw, 40px);
            max-width: min(500px, 90vw);
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3);
            transform: scale(0.9);
            transition: transform 0.3s;
        }

        .modal-overlay.show .modal-content {
            transform: scale(1);
        }

        .modal-icon {
            font-size: clamp(3rem, 6vw, 5rem);
            margin-bottom: clamp(12px, 2vh, 20px);
        }

        .modal-title {
            font-size: clamp(1.3rem, 2.5vw, 1.8rem);
            font-weight: 700;
            color: #1F2937;
            margin-bottom: clamp(8px, 1.5vh, 12px);
        }

        .modal-desc {
            font-size: clamp(0.9rem, 1.5vw, 1.1rem);
            color: #6B7280;
            margin-bottom: clamp(16px, 3vh, 24px);
            line-height: 1.5;
        }

        .modal-stats {
            display: flex;
            justify-content: center;
            gap: clamp(16px, 3vw, 30px);
            margin-bottom: clamp(16px, 3vh, 24px);
        }

        .modal-stat {
            text-align: center;
        }

        .modal-stat-value {
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            font-weight: 700;
            color: #667eea;
        }

        .modal-stat-value.score {
            color: #10B981;
        }

        .modal-stat-label {
            font-size: clamp(0.75rem, 1.2vw, 0.9rem);
            color: #6B7280;
            font-weight: 600;
        }

        .modal-buttons {
            display: flex;
            gap: clamp(10px, 2vw, 16px);
            justify-content: center;
            flex-wrap: wrap;
        }

        .modal-btn {
            padding: clamp(12px, 2vh, 16px) clamp(20px, 3vw, 32px);
            border-radius: clamp(10px, 1.2vw, 14px);
            font-size: clamp(0.9rem, 1.5vw, 1.1rem);
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-btn.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .modal-btn.success {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }

        .modal-btn.secondary {
            background: #F3F4F6;
            color: #1F2937;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        /* Review Mode Styles */
        .review-mode .progress-section {
            background: rgba(16, 185, 129, 0.2);
        }

        .review-nav {
            display: none;
            position: fixed;
            bottom: clamp(16px, 3vh, 30px);
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: clamp(12px, 2vh, 16px) clamp(16px, 3vw, 24px);
            border-radius: clamp(14px, 2vw, 20px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            gap: clamp(10px, 2vw, 16px);
            align-items: center;
            z-index: 100;
        }

        .review-mode .review-nav {
            display: flex;
        }

        .review-mode .question-area {
            margin-bottom: clamp(80px, 12vh, 100px);
        }

        .review-btn {
            padding: clamp(10px, 1.5vh, 14px) clamp(16px, 2vw, 24px);
            border-radius: clamp(8px, 1vw, 12px);
            font-size: clamp(0.85rem, 1.5vw, 1rem);
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-family: inherit;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .review-btn.nav {
            background: #F3F4F6;
            color: #1F2937;
        }

        .review-btn.nav:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .review-btn.submit {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: white;
        }

        .review-btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }

        .review-timer {
            background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
            color: white;
            padding: clamp(8px, 1.2vh, 12px) clamp(12px, 2vw, 16px);
            border-radius: clamp(8px, 1vw, 12px);
            font-weight: 700;
            font-size: clamp(0.9rem, 1.5vw, 1.1rem);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .review-timer.critical {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            animation: pulse 0.5s infinite;
        }

        .question-dots-container {
            display: none;
            flex-wrap: wrap;
            justify-content: center;
            gap: clamp(6px, 1vw, 10px);
            margin: clamp(8px, 1.5vh, 12px) 0;
        }

        .review-mode .question-dots-container {
            display: flex;
        }

        .q-dot {
            width: clamp(28px, 4vw, 36px);
            height: clamp(28px, 4vw, 36px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(0.7rem, 1.2vw, 0.85rem);
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .q-dot.answered {
            background: #10B981;
            color: white;
        }

        .q-dot.unanswered {
            background: #FEE2E2;
            color: #DC2626;
        }

        .q-dot.current {
            border-color: #667eea;
            transform: scale(1.15);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Score Popup */
        .score-display {
            font-size: clamp(3rem, 8vw, 5rem);
            font-weight: 700;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: clamp(8px, 1.5vh, 12px);
        }

        .score-label {
            font-size: clamp(1rem, 1.8vw, 1.3rem);
            color: #6B7280;
            font-weight: 600;
        }

        .score-detail {
            display: flex;
            justify-content: center;
            gap: clamp(20px, 4vw, 40px);
            margin-top: clamp(16px, 3vh, 24px);
            padding-top: clamp(16px, 3vh, 24px);
            border-top: 2px solid #E5E7EB;
        }

        .score-item {
            text-align: center;
        }

        .score-item-value {
            font-size: clamp(1.3rem, 2.5vw, 1.8rem);
            font-weight: 700;
        }

        .score-item-value.correct {
            color: #10B981;
        }

        .score-item-value.wrong {
            color: #EF4444;
        }

        .score-item-label {
            font-size: clamp(0.75rem, 1.2vw, 0.9rem);
            color: #6B7280;
        }

        /* Responsive cho màn hình nhỏ */
        @media (max-height: 600px) {
            .answers-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }
            .answer-btn {
                min-height: 50px;
                padding: 10px 12px;
            }
            .question-text {
                margin-bottom: 12px;
            }
        }

        @media (max-width: 768px) {
            .answers-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Laptop màn hình nhỏ */
        @media (max-width: 1366px) {
            .exam-header {
                padding: clamp(12px, 2vh, 20px) clamp(16px, 3vw, 30px);
            }
            .exam-title {
                font-size: clamp(1.1rem, 2vw, 1.4rem);
            }
            .exam-meta {
                font-size: clamp(0.8rem, 1.3vw, 0.95rem);
            }
            .progress-section {
                padding: clamp(10px, 1.5vh, 16px) clamp(16px, 3vw, 30px);
            }
            .question-counter {
                font-size: clamp(0.85rem, 1.4vw, 1rem);
            }
            .timer {
                font-size: clamp(1rem, 1.6vw, 1.2rem);
                padding: clamp(8px, 1.2vh, 12px) clamp(14px, 2vw, 20px);
            }
            .question-card {
                padding: clamp(16px, 2.5vh, 30px);
            }
            .question-text {
                font-size: clamp(1rem, 1.6vw, 1.3rem);
            }
            .answer-btn {
                padding: clamp(12px, 1.8vh, 18px) clamp(14px, 2vw, 20px);
                font-size: clamp(0.9rem, 1.4vw, 1.1rem);
            }
            .answer-label {
                width: clamp(32px, 4vw, 42px);
                height: clamp(32px, 4vw, 42px);
                font-size: clamp(0.9rem, 1.4vw, 1.1rem);
            }
        }

        /* Màn hình chiều cao thấp (laptop) */
        @media (max-height: 768px) {
            .exam-header {
                padding: 10px 20px;
            }
            .exam-title {
                font-size: 1.1rem;
            }
            .progress-section {
                padding: 8px 20px;
            }
            .question-counter {
                font-size: 0.85rem;
            }
            .timer {
                font-size: 1rem;
                padding: 8px 14px;
            }
            .question-area {
                padding: 12px 16px;
            }
            .question-card {
                padding: 16px;
            }
            .question-text {
                font-size: 1rem;
                margin-bottom: 14px;
            }
            .answers-grid {
                gap: 10px;
            }
            .answer-btn {
                min-height: 50px;
                padding: 10px 14px;
                font-size: 0.9rem;
            }
            .answer-label {
                width: 32px;
                height: 32px;
                font-size: 0.9rem;
            }
            .review-nav {
                padding: 10px 16px;
            }
            .review-btn {
                padding: 8px 14px;
                font-size: 0.85rem;
            }
            .modal-content {
                padding: clamp(20px, 3vh, 30px);
            }
            .modal-title {
                font-size: 1.2rem;
            }
            .score-display {
                font-size: 2.5rem;
            }
        }

        /* Màn hình chiều cao rất thấp */
        @media (max-height: 680px) {
            .exam-header {
                padding: 8px 16px;
            }
            .exam-title {
                font-size: 1rem;
            }
            .exam-meta {
                font-size: 0.75rem;
            }
            .progress-section {
                padding: 6px 16px;
            }
            .timer {
                padding: 6px 12px;
                font-size: 0.9rem;
            }
            .question-area {
                padding: 10px 14px;
            }
            .question-card {
                padding: 12px;
            }
            .question-text {
                font-size: 0.95rem;
            }
            .answer-btn {
                min-height: 45px;
                padding: 8px 12px;
                font-size: 0.85rem;
            }
            .answer-label {
                width: 28px;
                height: 28px;
                font-size: 0.85rem;
            }
            .q-dot {
                width: 26px;
                height: 26px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="loading-overlay hidden" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div>Đang xử lý...</div>
    </div>

    <div class="app-container">
        <div class="exam-header">
            <h1 class="exam-title"><?php echo htmlspecialchars($deThi['ten_de']); ?></h1>
            <p class="exam-meta"><?php echo htmlspecialchars($deThi['ten_mon']); ?> • <?php echo isset($student['ten_lop']) ? $student['ten_lop'] : ''; ?></p>
        </div>

        <div class="progress-section">
            <div class="question-counter">
                Câu <span id="currentQ">1</span>/<?php echo count($cauHoiList); ?>
            </div>
            <div class="progress-bar-wrap">
                <div id="progressFill" class="progress-fill" style="width: <?php echo (1/count($cauHoiList)*100); ?>%"></div>
            </div>
            <div id="timer" class="timer">
                <span>⏱️</span>
                <span id="timerDisplay"><?php echo formatTime($deThi['thoi_gian_cau']); ?></span>
            </div>
        </div>

        <div class="question-area">
            <div class="question-card" id="questionCard">
                <!-- Rendered by JS -->
            </div>
        </div>
    </div>

    <div id="toastContainer" class="toast-container"></div>

    <!-- Review Modal - Hiển thị khi làm xong câu cuối -->
    <div class="modal-overlay" id="reviewModal">
        <div class="modal-content">
            <div class="modal-icon">📋</div>
            <div class="modal-title">Hoàn thành bài thi!</div>
            <div class="modal-desc">Bạn đã trả lời hết các câu hỏi. Bạn có muốn xem lại bài trước khi nộp không?</div>
            <div class="modal-stats">
                <div class="modal-stat">
                    <div class="modal-stat-value" id="answeredCount">0</div>
                    <div class="modal-stat-label">Đã trả lời</div>
                </div>
                <div class="modal-stat">
                    <div class="modal-stat-value" id="unansweredCount">0</div>
                    <div class="modal-stat-label">Chưa trả lời</div>
                </div>
            </div>
            <div class="modal-buttons">
                <button class="modal-btn primary" onclick="enterReviewMode()">
                    <span>👁️</span> Xem lại bài
                </button>
                <button class="modal-btn success" onclick="submitExamNow()">
                    <span>✓</span> Nộp bài ngay
                </button>
            </div>
        </div>
    </div>

    <!-- Score Modal - Hiển thị điểm sau khi nộp -->
    <div class="modal-overlay" id="scoreModal">
        <div class="modal-content">
            <div class="modal-icon" id="scoreEmoji">🌟</div>
            <div class="modal-title" id="scoreTitle">Xuất sắc!</div>
            <div class="score-display" id="scoreValue">10</div>
            <div class="score-label">điểm</div>
            <div class="score-detail">
                <div class="score-item">
                    <div class="score-item-value correct" id="correctCount">0</div>
                    <div class="score-item-label">Đúng ✓</div>
                </div>
                <div class="score-item">
                    <div class="score-item-value wrong" id="wrongCount">0</div>
                    <div class="score-item-label">Sai ✗</div>
                </div>
            </div>
            <div class="modal-buttons" style="margin-top: 24px;">
                <button class="modal-btn success" onclick="goToResult()">
                    <span>📊</span> Xem chi tiết
                </button>
            </div>
        </div>
    </div>

    <!-- Review Navigation Bar -->
    <div class="review-nav" id="reviewNav">
        <button class="review-btn nav" id="btnPrevReview" onclick="reviewPrev()">
            <span>←</span> Trước
        </button>
        <div class="review-timer" id="reviewTimer">
            <span>⏱️</span>
            <span id="reviewTimerDisplay">00:00</span>
        </div>
        <button class="review-btn nav" id="btnNextReview" onclick="reviewNext()">
            Sau <span>→</span>
        </button>
        <button class="review-btn submit" onclick="showSubmitConfirm()">
            <span>✓</span> Nộp bài
        </button>
    </div>

    <!-- Question Dots for Review Mode -->
    <div class="question-dots-container" id="questionDots"></div>

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
        var totalTimeUsed = 0; // Tổng thời gian đã sử dụng
        var reviewTimer = null;
        var examStartTime = new Date();

        function formatTime(sec) {
            var m = Math.floor(sec / 60);
            var s = sec % 60;
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        function showToast(msg, type) {
            var container = document.getElementById('toastContainer');
            var toast = document.createElement('div');
            toast.className = 'toast ' + (type || '');
            toast.textContent = msg;
            container.appendChild(toast);
            setTimeout(function() { toast.remove(); }, 3000);
        }

        function ajax(url, method, data) {
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try { resolve(JSON.parse(xhr.responseText)); }
                        catch(e) { resolve(xhr.responseText); }
                    } else { reject({ status: xhr.status }); }
                };
                xhr.onerror = function() { reject({ status: xhr.status }); };
                xhr.send(data ? JSON.stringify(data) : null);
            });
        }

        function renderQuestion() {
            var q = EXAM.questions[currentIndex];
            qStartTime = new Date();

            document.getElementById('currentQ').textContent = currentIndex + 1;
            document.getElementById('progressFill').style.width = ((currentIndex + 1) / EXAM.questions.length * 100) + '%';

            var opts = ['A', 'B', 'C', 'D'];
            var keys = ['dap_an_a', 'dap_an_b', 'dap_an_c', 'dap_an_d'];
            var answersHtml = '';

            for (var i = 0; i < 4; i++) {
                var sel = answers[q.id] === opts[i] ? ' selected' : '';
                answersHtml += '<div class="answer-btn' + sel + '" onclick="selectAnswer(\'' + opts[i] + '\')">' +
                    '<div class="answer-letter">' + opts[i] + '</div>' +
                    '<div class="answer-text">' + escapeHtml(q[keys[i]]) + '</div>' +
                '</div>';
            }

            var imgHtml = q.hinh_anh ? '<img src="' + q.hinh_anh + '" class="question-image" alt="">' : '';

            document.getElementById('questionCard').innerHTML =
                '<div class="question-text">' + escapeHtml(q.noi_dung) + '</div>' +
                imgHtml +
                '<div class="answers-grid">' + answersHtml + '</div>';

            // Update question dots in review mode
            if (isReviewMode) {
                updateQuestionDots();
                updateReviewNavButtons();
            }
        }

        function selectAnswer(ans) {
            var q = EXAM.questions[currentIndex];
            var timeSpent = Math.round((new Date() - qStartTime) / 1000);
            answers[q.id] = ans;

            ajax(BASE_URL + '/api/submit_answer.php', 'POST', {
                session_token: EXAM.session,
                question_id: q.id,
                answer: ans,
                time_spent: timeSpent
            }).catch(function(e) { console.error(e); });

            var btns = document.querySelectorAll('.answer-btn');
            for (var i = 0; i < btns.length; i++) {
                btns[i].classList.remove('selected');
            }
            event.currentTarget.classList.add('selected');

            // Trong review mode không tự động chuyển câu
            if (!isReviewMode) {
                setTimeout(nextQuestion, 400);
            } else {
                updateQuestionDots();
            }
        }

        function startTimer() {
            timeLeft = EXAM.timePerQ;
            updateTimer();

            timer = setInterval(function() {
                timeLeft--;
                totalTimeUsed++; // Cộng thời gian đã sử dụng
                updateTimer();

                if (timeLeft <= 5) {
                    document.getElementById('timer').classList.add('warning');
                }
                if (timeLeft <= 0) {
                    handleTimeout();
                }
            }, 1000);
        }

        function updateTimer() {
            document.getElementById('timerDisplay').textContent = formatTime(timeLeft);
        }

        function handleTimeout() {
            var q = EXAM.questions[currentIndex];
            if (!answers[q.id]) {
                ajax(BASE_URL + '/api/submit_answer.php', 'POST', {
                    session_token: EXAM.session,
                    question_id: q.id,
                    answer: null,
                    time_spent: EXAM.timePerQ
                }).catch(function(e) { console.error(e); });
            }
            nextQuestion();
        }

        function nextQuestion() {
            clearInterval(timer);
            document.getElementById('timer').classList.remove('warning');

            if (currentIndex < EXAM.questions.length - 1) {
                currentIndex++;
                renderQuestion();
                startTimer();
            } else {
                // Thay vì finishExam(), hiển thị modal review
                showReviewModal();
            }
        }

        // ============ REVIEW MODE FUNCTIONS ============

        function showReviewModal() {
            clearInterval(timer);

            // Đếm số câu đã trả lời
            var answered = 0;
            for (var i = 0; i < EXAM.questions.length; i++) {
                if (answers[EXAM.questions[i].id]) {
                    answered++;
                }
            }
            var unanswered = EXAM.questions.length - answered;

            document.getElementById('answeredCount').textContent = answered;
            document.getElementById('unansweredCount').textContent = unanswered;
            document.getElementById('reviewModal').classList.add('show');
        }

        function hideReviewModal() {
            document.getElementById('reviewModal').classList.remove('show');
        }

        function enterReviewMode() {
            hideReviewModal();
            isReviewMode = true;
            currentIndex = 0;

            // Add review-mode class to container
            document.querySelector('.app-container').classList.add('review-mode');

            // Render question dots
            renderQuestionDots();

            // Ẩn timer per-question, hiển thị timer tổng
            document.getElementById('timer').style.display = 'none';

            // Start review timer (đếm ngược tổng thời gian còn lại)
            startReviewTimer();

            // Render câu hỏi đầu tiên
            renderQuestion();
        }

        function renderQuestionDots() {
            var dotsHtml = '';
            for (var i = 0; i < EXAM.questions.length; i++) {
                var q = EXAM.questions[i];
                var answered = answers[q.id] ? 'answered' : 'unanswered';
                var current = i === currentIndex ? 'current' : '';
                dotsHtml += '<div class="q-dot ' + answered + ' ' + current + '" onclick="goToQuestion(' + i + ')">' + (i + 1) + '</div>';
            }
            document.getElementById('questionDots').innerHTML = dotsHtml;
        }

        function updateQuestionDots() {
            var dots = document.querySelectorAll('.q-dot');
            for (var i = 0; i < dots.length; i++) {
                var q = EXAM.questions[i];
                dots[i].className = 'q-dot';
                dots[i].classList.add(answers[q.id] ? 'answered' : 'unanswered');
                if (i === currentIndex) {
                    dots[i].classList.add('current');
                }
            }
        }

        function goToQuestion(index) {
            if (index >= 0 && index < EXAM.questions.length) {
                currentIndex = index;
                renderQuestion();
            }
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

        function updateReviewNavButtons() {
            document.getElementById('btnPrevReview').disabled = currentIndex === 0;
            document.getElementById('btnNextReview').disabled = currentIndex === EXAM.questions.length - 1;
        }

        function startReviewTimer() {
            // Tính thời gian còn lại = Tổng thời gian - Thời gian đã dùng
            var totalTime = EXAM.questions.length * EXAM.timePerQ;
            var reviewTimeLeft = totalTime - totalTimeUsed;
            if (reviewTimeLeft < 0) reviewTimeLeft = 0;

            updateReviewTimerDisplay(reviewTimeLeft);

            reviewTimer = setInterval(function() {
                reviewTimeLeft--;
                updateReviewTimerDisplay(reviewTimeLeft);

                if (reviewTimeLeft <= 30) {
                    document.getElementById('reviewTimer').classList.add('critical');
                }

                if (reviewTimeLeft <= 0) {
                    // Hết giờ - tự động nộp bài
                    clearInterval(reviewTimer);
                    showToast('Hết thời gian! Bài thi đang được nộp...', 'warning');
                    submitExamNow();
                }
            }, 1000);
        }

        function updateReviewTimerDisplay(timeLeft) {
            document.getElementById('reviewTimerDisplay').textContent = formatTime(timeLeft);
        }

        function showSubmitConfirm() {
            // Đếm lại số câu đã trả lời
            var answered = 0;
            for (var i = 0; i < EXAM.questions.length; i++) {
                if (answers[EXAM.questions[i].id]) {
                    answered++;
                }
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
            showLoading();
            examFinished = true;

            ajax(BASE_URL + '/api/finish_exam.php', 'POST', {
                session_token: EXAM.session
            }).then(function(response) {
                hideLoading();
                // Hiển thị popup điểm
                showScoreModal(response);
            }).catch(function() {
                examFinished = false;
                hideLoading();
                showToast('Có lỗi xảy ra!', 'error');
            });
        }

        function showScoreModal(response) {
            var score = response.score || 0;
            var correct = response.correct || 0;
            var total = response.total || EXAM.questions.length;
            var wrong = total - correct;

            // Xác định emoji và title dựa trên điểm
            var emoji, title;
            if (score >= 9) {
                emoji = '🌟';
                title = 'Xuất sắc!';
            } else if (score >= 7) {
                emoji = '👏';
                title = 'Tốt lắm!';
            } else if (score >= 5) {
                emoji = '👍';
                title = 'Khá tốt!';
            } else {
                emoji = '💪';
                title = 'Cố gắng hơn nhé!';
            }

            document.getElementById('scoreEmoji').textContent = emoji;
            document.getElementById('scoreTitle').textContent = title;
            document.getElementById('scoreValue').textContent = score;
            document.getElementById('correctCount').textContent = correct;
            document.getElementById('wrongCount').textContent = wrong;

            document.getElementById('scoreModal').classList.add('show');
        }

        function goToResult() {
            window.location.href = BASE_URL + '/student/result.php?session=' + EXAM.session;
        }

        // Legacy function for compatibility
        function finishExam() {
            showReviewModal();
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Prevent reload (chỉ khi chưa hoàn thành bài thi)
        window.addEventListener('beforeunload', function(e) {
            if (!examFinished) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                showToast('Không thể tải lại trang!', 'warning');
            }

            // Arrow keys for review mode navigation
            if (isReviewMode) {
                if (e.key === 'ArrowLeft') {
                    reviewPrev();
                } else if (e.key === 'ArrowRight') {
                    reviewNext();
                }
            }
        });

        // ========== VIEWPORT HEIGHT FIX ==========
        function setViewportHeight() {
            var vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', vh + 'px');
        }

        setViewportHeight();
        window.addEventListener('resize', setViewportHeight);
        window.addEventListener('orientationchange', function() {
            setTimeout(setViewportHeight, 100);
        });

        // Init
        document.addEventListener('DOMContentLoaded', function() {
            setViewportHeight();
            renderQuestion();
            startTimer();
        });
    </script>
</body>
</html>
