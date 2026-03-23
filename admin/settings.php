<?php
/**
 * ==============================================
 * CÀI ĐẶT THÔNG SỐ MẶC ĐỊNH
 * - Cấu hình thi mặc định
 * - Cấu hình thời gian mở thi
 * - Các thông số hệ thống
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
$conn = getDBConnection();

// Tạo bảng settings nếu chưa có
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS cau_hinh (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ma_cau_hinh VARCHAR(100) NOT NULL UNIQUE,
            gia_tri TEXT,
            mo_ta VARCHAR(255),
            nhom VARCHAR(50) DEFAULT 'general',
            loai_du_lieu VARCHAR(20) DEFAULT 'text',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Thêm cột nhom và loai_du_lieu nếu chưa có (cho bảng cũ)
    $stmtCheck = $conn->query("SHOW COLUMNS FROM cau_hinh LIKE 'nhom'");
    if ($stmtCheck->fetch() === false) {
        $conn->exec("ALTER TABLE cau_hinh ADD COLUMN nhom VARCHAR(50) DEFAULT 'general'");
    }
    $stmtCheck = $conn->query("SHOW COLUMNS FROM cau_hinh LIKE 'loai_du_lieu'");
    if ($stmtCheck->fetch() === false) {
        $conn->exec("ALTER TABLE cau_hinh ADD COLUMN loai_du_lieu VARCHAR(20) DEFAULT 'text'");
    }
    $stmtCheck = $conn->query("SHOW COLUMNS FROM cau_hinh LIKE 'ma_cau_hinh'");
    if ($stmtCheck->fetch() === false) {
        $conn->exec("ALTER TABLE cau_hinh ADD COLUMN ma_cau_hinh VARCHAR(100) NOT NULL DEFAULT ''");
    }

    // Xóa các bản ghi cũ không có ma_cau_hinh
    $conn->exec("DELETE FROM cau_hinh WHERE ma_cau_hinh IS NULL OR ma_cau_hinh = ''");

    // Thêm các cấu hình mặc định nếu chưa có
    $defaultSettings = array(
        // Cấu hình thi
        array('thoi_gian_cau_mac_dinh', '30', 'Thời gian làm mỗi câu (giây)', 'exam', 'number'),
        array('so_cau_mac_dinh', '10', 'Số câu hỏi mặc định cho đề thi mới', 'exam', 'number'),
        array('che_do_mo_mac_dinh', 'cuoi_tuan', 'Chế độ mở mặc định (khi lịch thi không chỉ định)', 'exam', 'select'),

        // Cấu hình thời gian mở thi (áp dụng khi chế độ = theo_lich)
        array('ngay_mo_thi', 't7,cn', 'Ngày mở thi chính thức (dùng cho chế độ T7-CN)', 'schedule', 'text'),
        array('gio_bat_dau', '00:00:00', 'Giờ bắt đầu cho phép thi', 'schedule', 'time'),
        array('gio_ket_thuc', '23:59:59', 'Giờ kết thúc cho phép thi', 'schedule', 'time'),

        // Cấu hình điểm
        array('diem_toi_da', '10', 'Điểm tối đa cho bài thi', 'score', 'number'),
        array('diem_dat', '5', 'Điểm đạt yêu cầu', 'score', 'number'),
        array('hien_thi_diem_ngay', '1', 'Hiển thị điểm ngay sau khi nộp bài', 'score', 'boolean'),

        // Cấu hình xếp hạng
        array('so_hoc_sinh_top', '50', 'Số học sinh hiển thị trong bảng xếp hạng', 'ranking', 'number'),
        array('cap_nhat_xep_hang', 'realtime', 'Cách cập nhật xếp hạng (realtime, daily)', 'ranking', 'select'),

        // Cấu hình giao diện
        array('ten_truong', 'Trường Tiểu học Bùi Thị Xuân', 'Tên trường hiển thị', 'display', 'text'),
        array('slogan', 'Học tập - Sáng tạo - Phát triển', 'Slogan của trường', 'display', 'text'),
    );

    foreach ($defaultSettings as $setting) {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO cau_hinh (ma_cau_hinh, gia_tri, mo_ta, nhom, loai_du_lieu)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute($setting);
    }
} catch (Exception $e) {
    // Bỏ qua lỗi
}

$message = '';
$messageType = '';

// Xử lý lưu cấu hình
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_settings') {
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();

        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("UPDATE cau_hinh SET gia_tri = ? WHERE ma_cau_hinh = ?");
            $stmt->execute(array($value, $key));
        }

        $message = 'Đã lưu cấu hình thành công!';
        $messageType = 'success';
    }
}

// Lấy tất cả cấu hình
$stmt = $conn->query("SELECT * FROM cau_hinh ORDER BY nhom, id");
$allSettings = $stmt->fetchAll();

// Nhóm theo category
$settingsByGroup = array();
foreach ($allSettings as $setting) {
    // Bỏ qua các bản ghi không có ma_cau_hinh
    if (empty($setting['ma_cau_hinh'])) continue;
    $group = !empty($setting['nhom']) ? $setting['nhom'] : 'general';
    $settingsByGroup[$group][] = $setting;
}

// Tên nhóm
$groupNames = array(
    'exam' => array('name' => 'Cấu hình bài thi', 'icon' => 'file-text', 'color' => '#667eea'),
    'schedule' => array('name' => 'Lịch mở thi', 'icon' => 'clock', 'color' => '#10B981'),
    'score' => array('name' => 'Cấu hình điểm', 'icon' => 'award', 'color' => '#F59E0B'),
    'ranking' => array('name' => 'Xếp hạng', 'icon' => 'trending-up', 'color' => '#8B5CF6'),
    'display' => array('name' => 'Giao diện', 'icon' => 'layout', 'color' => '#EC4899'),
    'general' => array('name' => 'Chung', 'icon' => 'settings', 'color' => '#6B7280'),
);

$pageTitle = 'Cài đặt hệ thống';
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
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #EF4444; }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .settings-card-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #E5E7EB;
        }

        .settings-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .settings-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1F2937;
        }

        .settings-card-body {
            padding: 20px;
        }

        .setting-item {
            margin-bottom: 20px;
        }

        .setting-item:last-child {
            margin-bottom: 0;
        }

        .setting-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }

        .setting-desc {
            font-size: 0.8rem;
            color: #9CA3AF;
            margin-bottom: 8px;
        }

        .setting-input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s;
        }

        .setting-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .setting-input[type="number"] {
            max-width: 150px;
        }

        .setting-input[type="time"] {
            max-width: 150px;
        }

        .setting-toggle {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #E5E7EB;
            border-radius: 26px;
            transition: 0.3s;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .toggle-label {
            font-size: 0.9rem;
            color: #6B7280;
        }

        .save-btn-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 100;
        }

        .save-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s;
        }

        .save-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(102, 126, 234, 0.5);
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #F3F4F6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .checkbox-item:hover {
            background: #E5E7EB;
        }

        .checkbox-item.checked {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
            border: 2px solid #667eea;
        }

        .checkbox-item input {
            display: none;
        }

        .checkbox-item span {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
        }

        .info-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .info-banner-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .info-banner-content h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-banner-content p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .settings-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <div class="page-header">
                <h1 class="page-title">
                    <i data-feather="settings"></i>
                    <?php echo $pageTitle; ?>
                </h1>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="info-banner">
                <div class="info-banner-icon">⚙️</div>
                <div class="info-banner-content">
                    <h3>Cấu hình thông số mặc định</h3>
                    <p>Các thông số này sẽ được áp dụng làm giá trị mặc định khi tạo đề thi mới hoặc cài đặt lịch thi.</p>
                </div>
            </div>

            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="save_settings">

                <div class="settings-container">
                    <?php foreach ($settingsByGroup as $group => $settings): ?>
                        <?php
                        $groupInfo = isset($groupNames[$group]) ? $groupNames[$group] : $groupNames['general'];
                        ?>
                        <div class="settings-card">
                            <div class="settings-card-header">
                                <div class="settings-card-icon" style="background: <?php echo $groupInfo['color']; ?>;">
                                    <i data-feather="<?php echo $groupInfo['icon']; ?>"></i>
                                </div>
                                <div class="settings-card-title"><?php echo $groupInfo['name']; ?></div>
                            </div>
                            <div class="settings-card-body">
                                <?php foreach ($settings as $setting): ?>
                                    <div class="setting-item">
                                        <label class="setting-label"><?php echo htmlspecialchars($setting['mo_ta']); ?></label>

                                        <?php if ($setting['loai_du_lieu'] === 'boolean'): ?>
                                            <div class="setting-toggle">
                                                <label class="toggle-switch">
                                                    <input type="hidden" name="settings[<?php echo $setting['ma_cau_hinh']; ?>]" value="0">
                                                    <input type="checkbox" name="settings[<?php echo $setting['ma_cau_hinh']; ?>]" value="1" <?php echo $setting['gia_tri'] == '1' ? 'checked' : ''; ?>>
                                                    <span class="toggle-slider"></span>
                                                </label>
                                                <span class="toggle-label"><?php echo $setting['gia_tri'] == '1' ? 'Bật' : 'Tắt'; ?></span>
                                            </div>

                                        <?php elseif ($setting['loai_du_lieu'] === 'time'): ?>
                                            <input type="time" class="setting-input"
                                                   name="settings[<?php echo $setting['ma_cau_hinh']; ?>]"
                                                   value="<?php echo htmlspecialchars(substr($setting['gia_tri'], 0, 5)); ?>">

                                        <?php elseif ($setting['loai_du_lieu'] === 'number'): ?>
                                            <input type="number" class="setting-input"
                                                   name="settings[<?php echo $setting['ma_cau_hinh']; ?>]"
                                                   value="<?php echo htmlspecialchars($setting['gia_tri']); ?>"
                                                   min="1" max="1000">

                                        <?php elseif ($setting['ma_cau_hinh'] === 'ngay_mo_thi'): ?>
                                            <?php
                                            $selectedDays = explode(',', $setting['gia_tri']);
                                            $allDays = array(
                                                't2' => 'Thứ 2',
                                                't3' => 'Thứ 3',
                                                't4' => 'Thứ 4',
                                                't5' => 'Thứ 5',
                                                't6' => 'Thứ 6',
                                                't7' => 'Thứ 7',
                                                'cn' => 'Chủ nhật'
                                            );
                                            ?>
                                            <div class="checkbox-group" id="daySelector">
                                                <?php foreach ($allDays as $dayKey => $dayName): ?>
                                                    <label class="checkbox-item <?php echo in_array($dayKey, $selectedDays) ? 'checked' : ''; ?>">
                                                        <input type="checkbox" value="<?php echo $dayKey; ?>" <?php echo in_array($dayKey, $selectedDays) ? 'checked' : ''; ?>>
                                                        <span><?php echo $dayName; ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <input type="hidden" name="settings[ngay_mo_thi]" id="ngay_mo_thi_value" value="<?php echo htmlspecialchars($setting['gia_tri']); ?>">

                                        <?php elseif ($setting['ma_cau_hinh'] === 'che_do_mo_mac_dinh'): ?>
                                            <select class="setting-input" name="settings[<?php echo $setting['ma_cau_hinh']; ?>]">
                                                <option value="luon_mo" <?php echo $setting['gia_tri'] === 'luon_mo' ? 'selected' : ''; ?>>Luôn mở</option>
                                                <option value="cuoi_tuan" <?php echo $setting['gia_tri'] === 'cuoi_tuan' ? 'selected' : ''; ?>>Cuối tuần (T7 & CN)</option>
                                                <option value="theo_gio" <?php echo $setting['gia_tri'] === 'theo_gio' ? 'selected' : ''; ?>>Theo giờ cài đặt</option>
                                            </select>

                                        <?php elseif ($setting['ma_cau_hinh'] === 'cap_nhat_xep_hang'): ?>
                                            <select class="setting-input" name="settings[<?php echo $setting['ma_cau_hinh']; ?>]">
                                                <option value="realtime" <?php echo $setting['gia_tri'] === 'realtime' ? 'selected' : ''; ?>>Cập nhật ngay</option>
                                                <option value="daily" <?php echo $setting['gia_tri'] === 'daily' ? 'selected' : ''; ?>>Cập nhật hàng ngày</option>
                                            </select>

                                        <?php else: ?>
                                            <input type="text" class="setting-input"
                                                   name="settings[<?php echo $setting['ma_cau_hinh']; ?>]"
                                                   value="<?php echo htmlspecialchars($setting['gia_tri']); ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="save-btn-container">
                    <button type="submit" class="save-btn">
                        <i data-feather="save"></i>
                        Lưu cấu hình
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        feather.replace();

        // Xử lý checkbox ngày
        document.querySelectorAll('#daySelector .checkbox-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT') {
                    var checkbox = this.querySelector('input');
                    checkbox.checked = !checkbox.checked;
                }
                this.classList.toggle('checked', this.querySelector('input').checked);
                updateDayValue();
            });
        });

        function updateDayValue() {
            var selectedDays = [];
            document.querySelectorAll('#daySelector input:checked').forEach(function(cb) {
                selectedDays.push(cb.value);
            });
            document.getElementById('ngay_mo_thi_value').value = selectedDays.join(',');
        }

        // Cập nhật label toggle khi thay đổi
        document.querySelectorAll('.toggle-switch input').forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                var label = this.closest('.setting-toggle').querySelector('.toggle-label');
                label.textContent = this.checked ? 'Bật' : 'Tắt';
            });
        });
    </script>
</body>
</html>
