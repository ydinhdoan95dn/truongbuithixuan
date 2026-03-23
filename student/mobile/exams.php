<?php
/**
 * ==============================================
 * MOBILE - DANH SÁCH ĐỀ THI
 * ==============================================
 */

require_once '../../includes/config.php';
require_once '../../includes/device.php';
require_once '../../includes/week_helper.php';

redirectIfDesktop(BASE_URL . '/student/dashboard.php');

if (!isStudentLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$student = getCurrentStudent();
$conn = getDBConnection();

// Lấy tuần hiện tại
$currentWeek = getCurrentWeek();

// Lấy cài đặt ngày mở thi
$stmtNgayMo = $conn->prepare("SELECT gia_tri FROM cau_hinh WHERE ma_cau_hinh = 'ngay_mo_thi'");
$stmtNgayMo->execute();
$ngayMoResult = $stmtNgayMo->fetch();
$ngayMoThi = $ngayMoResult ? $ngayMoResult['gia_tri'] : 't7,cn';

// Lấy chế độ mở mặc định
$stmtCheDoMo = $conn->prepare("SELECT gia_tri FROM cau_hinh WHERE ma_cau_hinh = 'che_do_mo_mac_dinh'");
$stmtCheDoMo->execute();
$cheDoMoResult = $stmtCheDoMo->fetch();
$cheDoMoMacDinh = $cheDoMoResult ? $cheDoMoResult['gia_tri'] : 'cuoi_tuan';

// Parse ngày được phép
$dayMap = array('cn' => 0, 't2' => 1, 't3' => 2, 't4' => 3, 't5' => 4, 't6' => 5, 't7' => 6);
$dayNames = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');
$dayNamesFull = array('Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7');
$allowedDays = array();
foreach (explode(',', $ngayMoThi) as $day) {
    $day = trim(strtolower($day));
    if (isset($dayMap[$day])) {
        $allowedDays[] = $dayMap[$day];
    }
}
$todayDayOfWeek = (int)date('w');
$isTodayAllowed = in_array($todayDayOfWeek, $allowedDays);

// Tạo text ngày mở
$allowedDayText = array();
foreach ($allowedDays as $d) {
    $allowedDayText[] = $dayNamesFull[$d];
}
$ngayMoText = implode(', ', $allowedDayText);

// Lấy danh sách môn học
$stmtMon = $conn->query("SELECT * FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$monList = $stmtMon->fetchAll();

// Lọc theo môn
$monFilter = isset($_GET['mon']) ? intval($_GET['mon']) : 0;

// Lấy danh sách đề thi (ưu tiên chính thức lên đầu)
$sql = "
    SELECT dt.*, mh.ten_mon, mh.icon, mh.mau_sac,
           (SELECT COUNT(*) FROM bai_lam bl WHERE bl.de_thi_id = dt.id AND bl.hoc_sinh_id = ? AND bl.trang_thai = 'hoan_thanh') as da_lam
    FROM de_thi dt
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    WHERE dt.trang_thai = 1
    AND (dt.lop_id = ? OR dt.lop_id IS NULL)
";

if ($monFilter > 0) {
    $sql .= " AND dt.mon_hoc_id = " . $monFilter;
}

$sql .= " ORDER BY dt.is_chinh_thuc DESC, dt.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute(array($student['id'], $student['lop_id']));
$deThiList = $stmt->fetchAll();

// Xử lý thêm thông tin cho từng đề thi
foreach ($deThiList as &$deThi) {
    $isChinhThuc = isset($deThi['is_chinh_thuc']) && $deThi['is_chinh_thuc'] == 1;

    // Xác định chế độ mở
    $cheDoMo = isset($deThi['che_do_mo']) && !empty($deThi['che_do_mo']) ? $deThi['che_do_mo'] : null;
    if ($cheDoMo === null && $isChinhThuc) {
        $cheDoMo = ($cheDoMoMacDinh == 'luon_mo') ? 'mo_ngay' : 'theo_lich';
    }
    if ($cheDoMo === null) {
        $cheDoMo = 'theo_lich';
    }

    $deThi['_cheDoMo'] = $cheDoMo;
    $deThi['_canCheckNgay'] = $isChinhThuc && $cheDoMo == 'theo_lich';
    $deThi['_duocThiHomNay'] = !$deThi['_canCheckNgay'] || $isTodayAllowed;

    // Kiểm tra số lượt thi còn lại
    $soLanToiDa = isset($deThi['so_lan_thi_toi_da_tuan']) ? (int)$deThi['so_lan_thi_toi_da_tuan'] : 3;
    $soLanDaThi = 0;

    if ($isChinhThuc && $currentWeek) {
        $stmtCount = $conn->prepare("SELECT COUNT(*) as count FROM bai_lam WHERE hoc_sinh_id = ? AND de_thi_id = ? AND tuan_id = ? AND is_chinh_thuc = 1 AND trang_thai = 'hoan_thanh'");
        $stmtCount->execute(array($student['id'], $deThi['id'], $currentWeek['id']));
        $countResult = $stmtCount->fetch();
        $soLanDaThi = $countResult ? (int)$countResult['count'] : 0;
    }

    $deThi['_soLanToiDa'] = $soLanToiDa;
    $deThi['_soLanDaThi'] = $soLanDaThi;
    $deThi['_conLuotThi'] = $soLanToiDa - $soLanDaThi;
    $deThi['_hetLuot'] = $isChinhThuc && ($soLanDaThi >= $soLanToiDa);
}
unset($deThi);

$pageTitle = 'Làm bài thi';
$currentTab = 'exams';
include 'header.php';
?>

<!-- Header -->
<div class="header">
    <div class="header-content">
        <div class="page-header">
            <a href="index.php" class="back-btn">‹</a>
            <h1>📝 Làm bài thi</h1>
        </div>
    </div>
</div>

<main class="main">
    <!-- Filter Tabs -->
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 8px;">
        <a href="exams.php" class="btn <?php echo $monFilter == 0 ? 'btn-primary' : 'btn-outline'; ?>" style="white-space: nowrap; padding: 10px 16px; font-size: 14px;">
            Tất cả
        </a>
        <?php foreach ($monList as $mon): ?>
        <a href="exams.php?mon=<?php echo $mon['id']; ?>"
           class="btn <?php echo $monFilter == $mon['id'] ? 'btn-primary' : 'btn-outline'; ?>"
           style="white-space: nowrap; padding: 10px 16px; font-size: 14px;">
            <?php echo getSubjectIcon($mon['icon']); ?> <?php echo htmlspecialchars($mon['ten_mon']); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Danh sách đề thi -->
    <?php if (count($deThiList) > 0): ?>
        <?php foreach ($deThiList as $deThi):
            $isChinhThuc = isset($deThi['is_chinh_thuc']) && $deThi['is_chinh_thuc'] == 1;
            $canThi = $deThi['_duocThiHomNay'] && !$deThi['_hetLuot'];
            $lockReason = '';
            if (!$deThi['_duocThiHomNay']) {
                $lockReason = 'schedule';
            } elseif ($deThi['_hetLuot']) {
                $lockReason = 'limit';
            }
        ?>
        <?php if ($canThi): ?>
        <a href="exam.php?id=<?php echo $deThi['id']; ?>" class="list-item" style="position: relative;">
        <?php else: ?>
        <div class="list-item exam-locked" style="position: relative; opacity: 0.85; cursor: pointer;"
             onclick="showLockModal('<?php echo $lockReason; ?>', '<?php echo htmlspecialchars($deThi['ten_de'], ENT_QUOTES); ?>', '<?php echo $ngayMoText; ?>', <?php echo $deThi['_conLuotThi']; ?>)">
        <?php endif; ?>

            <?php if ($isChinhThuc): ?>
                <?php if (!$canThi): ?>
                <div style="position: absolute; top: 4px; right: 4px; background: linear-gradient(135deg, #EF4444, #DC2626); color: white; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">
                    🔒 <?php echo $lockReason == 'limit' ? 'HẾT LƯỢT' : 'CHƯA MỞ'; ?>
                </div>
                <?php else: ?>
                <div style="position: absolute; top: 4px; right: 4px; background: linear-gradient(135deg, #FFD700, #FFA500); color: #7B4F00; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">
                    ⭐ CHÍNH THỨC
                </div>
                <?php endif; ?>
            <?php else: ?>
            <div style="position: absolute; top: 4px; right: 4px; background: #667eea; color: white; padding: 2px 8px; border-radius: 8px; font-size: 10px; font-weight: 700;">
                📝 LUYỆN THI
            </div>
            <?php endif; ?>

            <div class="icon" style="background: <?php echo $deThi['mau_sac'] ?: '#667eea'; ?>20;">
                <?php echo getSubjectIcon($deThi['icon']); ?>
            </div>
            <div class="content">
                <div class="title"><?php echo htmlspecialchars($deThi['ten_de']); ?></div>
                <div class="subtitle">
                    <?php echo htmlspecialchars($deThi['ten_mon']); ?>
                    • <?php echo $deThi['so_cau']; ?> câu
                    • <?php echo $deThi['thoi_gian_cau']; ?>s/câu
                    <?php if ($isChinhThuc): ?>
                        <br>
                        <?php if ($deThi['_canCheckNgay'] && !$deThi['_duocThiHomNay']): ?>
                            <span style="color: #EF4444; font-weight: 600;">⏰ Mở vào: <?php echo $ngayMoText; ?></span>
                        <?php elseif ($deThi['_hetLuot']): ?>
                            <span style="color: #EF4444; font-weight: 600;">❌ Đã hết lượt tuần này</span>
                        <?php else: ?>
                            <span style="color: #10B981; font-weight: 600;">✓ Còn <?php echo $deThi['_conLuotThi']; ?>/<?php echo $deThi['_soLanToiDa']; ?> lượt</span>
                        <?php endif; ?>
                    <?php elseif ($deThi['da_lam'] > 0): ?>
                        <span class="text-success">• Đã làm <?php echo $deThi['da_lam']; ?> lần</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="arrow"><?php echo $canThi ? '›' : '🔒'; ?></div>

        <?php if ($canThi): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="icon">📭</div>
            <div class="title">Chưa có đề thi</div>
            <div class="desc">Hiện tại chưa có đề thi nào cho bạn</div>
        </div>
    <?php endif; ?>
</main>

<!-- Modal cảnh báo -->
<div id="lockModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: white; border-radius: 20px; padding: 24px; max-width: 320px; width: 100%; text-align: center; animation: modalIn 0.3s ease;">
        <div id="modalIcon" style="font-size: 3rem; margin-bottom: 12px;">🔒</div>
        <div id="modalTitle" style="font-size: 1.2rem; font-weight: 700; color: #1F2937; margin-bottom: 8px;">Chưa thể thi</div>
        <div id="modalDesc" style="color: #6B7280; font-size: 0.95rem; line-height: 1.5; margin-bottom: 20px;"></div>
        <button onclick="closeLockModal()" style="width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; font-family: inherit;">
            Đã hiểu
        </button>
    </div>
</div>

<style>
@keyframes modalIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.exam-locked {
    background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%) !important;
    border: 2px solid #F59E0B !important;
}

.exam-locked .arrow {
    color: #92400E !important;
}
</style>

<script>
function showLockModal(reason, examName, allowedDays, remaining) {
    var modal = document.getElementById('lockModal');
    var icon = document.getElementById('modalIcon');
    var title = document.getElementById('modalTitle');
    var desc = document.getElementById('modalDesc');

    if (reason === 'schedule') {
        icon.textContent = '📅';
        title.textContent = 'Chưa đến ngày thi!';
        desc.innerHTML = 'Đề thi <strong>"' + examName + '"</strong> chỉ mở vào:<br><br><span style="color: #667eea; font-weight: 700; font-size: 1.1rem;">' + allowedDays + '</span><br><br>Hãy quay lại vào những ngày này nhé!';
    } else if (reason === 'limit') {
        icon.textContent = '❌';
        title.textContent = 'Đã hết lượt thi!';
        desc.innerHTML = 'Bạn đã sử dụng hết lượt thi cho đề <strong>"' + examName + '"</strong> trong tuần này.<br><br>Hãy chờ đến tuần sau để thi lại nhé!';
    }

    modal.style.display = 'flex';
}

function closeLockModal() {
    document.getElementById('lockModal').style.display = 'none';
}

// Đóng modal khi click bên ngoài
document.getElementById('lockModal').addEventListener('click', function(e) {
    if (e.target === this) closeLockModal();
});
</script>

<!-- Bottom Tab Bar -->
<nav class="tab-bar">
    <a href="index.php" class="tab-item">
        <span class="icon">🏠</span>
        <span class="label">Trang chủ</span>
    </a>
    <a href="exams.php" class="tab-item active">
        <span class="icon">📝</span>
        <span class="label">Làm bài</span>
    </a>
    <a href="documents.php" class="tab-item">
        <span class="icon">📖</span>
        <span class="label">Tài liệu</span>
    </a>
    <a href="profile.php" class="tab-item">
        <span class="icon">👤</span>
        <span class="label">Tôi</span>
    </a>
</nav>

<?php include 'footer.php'; ?>
