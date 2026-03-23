<?php
/**
 * ==============================================
 * IMPORT DANH SÁCH HỌC SINH
 * Copy/Paste từ Excel - Preview và Import
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

$message = '';
$messageType = '';
$importResult = null;

// Xử lý import
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'import_students') {
        $lopId = intval($_POST['lop_id']);
        $studentsJson = isset($_POST['students']) ? $_POST['students'] : '[]';
        $defaultPassword = isset($_POST['default_password']) ? trim($_POST['default_password']) : '123456';

        $students = json_decode($studentsJson, true);

        // Kiểm tra quyền GVCN
        if (isGVCN() && $lopId != $myLopId) {
            $message = 'Bạn chỉ có thể nhập học sinh cho lớp mình!';
            $messageType = 'error';
        } elseif (empty($students) || !is_array($students)) {
            $message = 'Không có học sinh nào để import!';
            $messageType = 'error';
        } else {
            try {
                $conn->beginTransaction();

                // Hash default password
                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

                // Lấy mã lớp để tạo mã học sinh
                $stmtLop = $conn->prepare("SELECT ten_lop FROM lop_hoc WHERE id = ?");
                $stmtLop->execute(array($lopId));
                $lopInfo = $stmtLop->fetch();
                $lopPrefix = preg_replace('/[^0-9A-Za-z]/', '', $lopInfo['ten_lop']);

                // Lấy số thứ tự lớn nhất hiện tại của lớp
                $stmtMax = $conn->prepare("SELECT MAX(CAST(SUBSTRING(ma_hs, -3) AS UNSIGNED)) as max_num FROM hoc_sinh WHERE lop_id = ? AND ma_hs LIKE ?");
                $stmtMax->execute(array($lopId, $lopPrefix . '%'));
                $maxResult = $stmtMax->fetch();
                $nextNum = ($maxResult['max_num'] ? intval($maxResult['max_num']) : 0) + 1;

                // Thêm từng học sinh
                $stmtInsert = $conn->prepare("INSERT INTO hoc_sinh (ma_hs, password, ho_ten, lop_id, ngay_sinh, gioi_tinh, trang_thai, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");

                $importedCount = 0;
                $errors = array();

                foreach ($students as $s) {
                    if (empty($s['ho_ten'])) {
                        $errors[] = "Dòng " . ($importedCount + 1) . ": Thiếu họ tên";
                        continue;
                    }

                    // Tạo mã học sinh: LOP + 3 số
                    $maHs = $lopPrefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

                    // Kiểm tra mã đã tồn tại chưa
                    $stmtCheck = $conn->prepare("SELECT id FROM hoc_sinh WHERE ma_hs = ?");
                    $stmtCheck->execute(array($maHs));
                    if ($stmtCheck->fetch()) {
                        $nextNum++;
                        $maHs = $lopPrefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
                    }

                    // Chuyển ngày sinh sang định dạng MySQL
                    $ngaySinh = null;
                    if (!empty($s['ngay_sinh'])) {
                        // Hỗ trợ định dạng dd/mm/yyyy
                        $parts = preg_split('/[\/\-]/', $s['ngay_sinh']);
                        if (count($parts) == 3) {
                            $ngaySinh = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                        }
                    }

                    $gioiTinh = 1; // Default: Nam = 1
                    if (!empty($s['gioi_tinh'])) {
                        $gt = mb_strtolower(trim($s['gioi_tinh']), 'UTF-8');
                        if ($gt === 'nữ' || $gt === 'nu' || $gt === 'female' || $gt === 'f' || $gt === '0') {
                            $gioiTinh = 0; // Nữ = 0
                        }
                    }

                    try {
                        $stmtInsert->execute(array(
                            $maHs,
                            $hashedPassword,
                            trim($s['ho_ten']),
                            $lopId,
                            $ngaySinh,
                            $gioiTinh
                        ));
                        $importedCount++;
                        $nextNum++;
                    } catch (Exception $e) {
                        $errors[] = "Lỗi khi thêm: " . $s['ho_ten'] . " - " . $e->getMessage();
                    }
                }

                $conn->commit();

                $message = "Import thành công $importedCount học sinh!";
                $messageType = 'success';
                $importResult = array(
                    'success' => true,
                    'imported' => $importedCount,
                    'errors' => $errors,
                    'lop_id' => $lopId
                );

            } catch (Exception $e) {
                $conn->rollBack();
                $message = 'Lỗi: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Lấy danh sách lớp
if (isAdmin()) {
    $stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
    $lopList = $stmtLop->fetchAll();
} else {
    $stmtLop = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ?");
    $stmtLop->execute(array($myLopId));
    $lopList = $stmtLop->fetchAll();
}

$pageTitle = 'Import Danh Sách Học Sinh';
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

        /* Split screen layout */
        .smart-editor-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: calc(100vh - 200px);
            min-height: 500px;
        }

        .editor-panel, .preview-panel {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            padding: 16px 20px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-body {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .editor-wrapper {
            flex: 1;
            padding: 16px;
            overflow: hidden;
        }

        #pasteArea {
            width: 100%;
            height: 100%;
            min-height: 350px;
            border: 2px dashed #E5E7EB;
            border-radius: 12px;
            padding: 16px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 13px;
            line-height: 1.6;
            resize: none;
            transition: border-color 0.3s;
        }

        #pasteArea:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.02);
        }

        #pasteArea::placeholder {
            color: #9CA3AF;
        }

        /* Preview panel */
        .preview-body {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
        }

        .preview-stats {
            display: flex;
            gap: 16px;
            padding: 12px 16px;
            background: #F9FAFB;
            border-radius: 8px;
            margin-bottom: 16px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
        }

        .stat-value {
            font-weight: 700;
            color: #667eea;
        }

        /* Student table */
        .student-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .student-table th {
            background: #F9FAFB;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #E5E7EB;
        }

        .student-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #F3F4F6;
            color: #4B5563;
        }

        .student-table tr:hover {
            background: #F9FAFB;
        }

        .student-table tr.has-error {
            background: rgba(239, 68, 68, 0.05);
        }

        .student-table tr.has-error td {
            color: #EF4444;
        }

        .stt-col {
            width: 50px;
            text-align: center;
            font-weight: 600;
            color: #667eea !important;
        }

        .gender-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .gender-nam {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }

        .gender-nu {
            background: rgba(236, 72, 153, 0.1);
            color: #EC4899;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #9CA3AF;
        }

        .no-data-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        /* Form section */
        .import-form-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            align-items: end;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        /* Instructions */
        .instructions-toggle {
            background: none;
            border: 1px solid #E5E7EB;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #6B7280;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .instructions-toggle:hover {
            background: #F9FAFB;
        }

        .instructions-box {
            display: none;
            background: #FEF3C7;
            border-radius: 12px;
            padding: 16px;
            margin: 12px 16px 0 16px;
        }

        .instructions-box.show {
            display: block;
        }

        .instructions-box h4 {
            color: #92400E;
            margin-bottom: 12px;
            font-size: 0.95rem;
        }

        .instructions-box pre {
            background: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            line-height: 1.5;
            overflow-x: auto;
        }

        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .sample-table th, .sample-table td {
            padding: 8px 12px;
            border: 1px solid #E5E7EB;
            font-size: 0.85rem;
        }

        .sample-table th {
            background: #F3F4F6;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .smart-editor-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            .editor-panel, .preview-panel {
                min-height: 400px;
            }
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 24px;">
                Import Danh Sách Học Sinh
            </h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                    <?php if ($importResult && !empty($importResult['errors'])): ?>
                        <ul style="margin-top: 10px; font-weight: normal;">
                            <?php foreach ($importResult['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($importResult && $importResult['success']): ?>
                        <div style="margin-top: 12px;">
                            <a href="<?php echo BASE_URL; ?>/admin/students.php?lop_id=<?php echo $importResult['lop_id']; ?>" class="btn btn-primary btn-sm">
                                Xem danh sách học sinh
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Split screen: Paste Area | Preview -->
            <div class="smart-editor-container">
                <!-- Left: Paste Area -->
                <div class="editor-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            Dán dữ liệu từ Excel
                        </div>
                        <button class="instructions-toggle" onclick="toggleInstructions()">
                            <i data-feather="help-circle" style="width: 16px; height: 16px;"></i>
                            Hướng dẫn
                        </button>
                    </div>
                    <div class="instructions-box" id="instructionsBox">
                        <h4>Định dạng dữ liệu:</h4>
                        <p style="font-size: 0.9rem; color: #92400E; margin-bottom: 12px;">
                            Copy từ Excel và dán trực tiếp vào ô bên dưới. Dữ liệu cần có các cột:
                        </p>
                        <table class="sample-table">
                            <tr>
                                <th>STT</th>
                                <th>Họ và tên</th>
                                <th>Ngày sinh</th>
                                <th>Giới tính</th>
                            </tr>
                            <tr>
                                <td>1</td>
                                <td>Nguyễn Phúc An</td>
                                <td>20/12/2016</td>
                                <td>Nam</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Phan Phương Hoài An</td>
                                <td>06/10/2016</td>
                                <td>Nữ</td>
                            </tr>
                        </table>
                        <div style="margin-top: 12px; font-size: 0.85rem; color: #92400E;">
                            <strong>Lưu ý:</strong> Cột STT sẽ được bỏ qua. Mã học sinh sẽ được tạo tự động theo lớp.
                        </div>
                    </div>
                    <div class="panel-body">
                        <div class="editor-wrapper">
                            <textarea id="pasteArea" placeholder="Dán dữ liệu từ Excel vào đây...

Ví dụ:
1	Nguyễn Văn A	01/01/2016	Nam
2	Trần Thị B	15/05/2016	Nữ
3	Lê Văn C	20/09/2016	Nam"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right: Preview -->
                <div class="preview-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            Preview danh sách
                        </div>
                        <div class="preview-stats" id="previewStats" style="display: none;">
                            <div class="stat-item">
                                <span>Tổng:</span>
                                <span class="stat-value" id="totalStudents">0</span> học sinh
                            </div>
                            <div class="stat-item">
                                <span>Hợp lệ:</span>
                                <span class="stat-value" id="validStudents">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-body" id="previewBody">
                        <div class="no-data">
                            <div class="no-data-icon">📋</div>
                            <div>Dán dữ liệu từ Excel vào bên trái</div>
                            <div style="font-size: 0.85rem; margin-top: 8px;">Preview sẽ tự động hiển thị</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Form -->
            <div class="import-form-section">
                <form method="POST" id="importForm">
                    <input type="hidden" name="action" value="import_students">
                    <input type="hidden" name="students" id="studentsInput" value="[]">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Lớp *</label>
                            <select name="lop_id" class="form-input" required>
                                <?php foreach ($lopList as $lop): ?>
                                    <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mật khẩu mặc định</label>
                            <input type="text" name="default_password" class="form-input" value="123456" placeholder="Mật khẩu cho tất cả học sinh">
                        </div>
                        <div class="form-group" style="display: flex; gap: 12px;">
                            <button type="button" class="btn btn-secondary" onclick="clearAll()" style="flex: 1;">
                                <i data-feather="trash-2"></i> Xóa
                            </button>
                            <button type="submit" class="btn btn-primary" id="importBtn" disabled style="flex: 2;">
                                <i data-feather="upload"></i> Import (<span id="importCount">0</span> học sinh)
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        feather.replace();

        var parseTimeout = null;
        var parsedStudents = [];

        // Monitor paste area changes
        document.getElementById('pasteArea').addEventListener('input', function() {
            scheduleParseContent();
        });

        document.getElementById('pasteArea').addEventListener('paste', function() {
            setTimeout(function() {
                scheduleParseContent();
            }, 100);
        });

        // Debounce: Auto-parse after user stops typing
        function scheduleParseContent() {
            if (parseTimeout) {
                clearTimeout(parseTimeout);
            }
            parseTimeout = setTimeout(function() {
                parseContent();
            }, 500);
        }

        // Parse pasted content
        function parseContent() {
            var content = document.getElementById('pasteArea').value;

            if (!content.trim()) {
                showNoData();
                return;
            }

            parsedStudents = parseStudentsFromText(content);
            updatePreview();
        }

        // Smart parser for Excel data
        function parseStudentsFromText(text) {
            var students = [];
            var lines = text.split(/\n/);

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i].trim();
                if (!line) continue;

                // Split by tab (Excel default) or multiple spaces
                var parts = line.split(/\t/);
                if (parts.length < 2) {
                    // Try splitting by multiple spaces
                    parts = line.split(/\s{2,}/);
                }

                if (parts.length < 2) continue;

                // Check if first column is STT (number only)
                var startIndex = 0;
                if (/^\d+$/.test(parts[0].trim())) {
                    startIndex = 1; // Skip STT column
                }

                var student = {
                    ho_ten: '',
                    ngay_sinh: '',
                    gioi_tinh: 'Nam',
                    errors: []
                };

                // Họ tên
                if (parts[startIndex]) {
                    student.ho_ten = parts[startIndex].trim();
                }

                // Ngày sinh
                if (parts[startIndex + 1]) {
                    var dateStr = parts[startIndex + 1].trim();
                    // Validate date format dd/mm/yyyy or dd-mm-yyyy
                    if (/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4}$/.test(dateStr)) {
                        student.ngay_sinh = dateStr;
                    }
                }

                // Giới tính
                if (parts[startIndex + 2]) {
                    var gt = parts[startIndex + 2].trim().toLowerCase();
                    if (gt === 'nữ' || gt === 'nu' || gt === 'female' || gt === 'f') {
                        student.gioi_tinh = 'Nữ';
                    } else {
                        student.gioi_tinh = 'Nam';
                    }
                }

                // Validate
                if (!student.ho_ten) {
                    student.errors.push('Thiếu họ tên');
                }

                students.push(student);
            }

            return students;
        }

        // Update preview panel
        function updatePreview() {
            var previewBody = document.getElementById('previewBody');
            var previewStats = document.getElementById('previewStats');

            if (parsedStudents.length === 0) {
                showNoData();
                return;
            }

            // Count stats
            var total = parsedStudents.length;
            var valid = parsedStudents.filter(function(s) { return s.errors.length === 0; }).length;

            // Update stats
            document.getElementById('totalStudents').textContent = total;
            document.getElementById('validStudents').textContent = valid;
            previewStats.style.display = 'flex';

            // Update import button
            document.getElementById('importCount').textContent = valid;
            document.getElementById('importBtn').disabled = (valid === 0);

            // Store valid students for form submission
            var validStudents = parsedStudents.filter(function(s) { return s.errors.length === 0; });
            document.getElementById('studentsInput').value = JSON.stringify(validStudents);

            // Render table
            var html = '<table class="student-table">';
            html += '<thead><tr>';
            html += '<th class="stt-col">STT</th>';
            html += '<th>Họ và tên</th>';
            html += '<th>Ngày sinh</th>';
            html += '<th>Giới tính</th>';
            html += '</tr></thead><tbody>';

            parsedStudents.forEach(function(s, index) {
                var hasError = s.errors.length > 0;
                html += '<tr class="' + (hasError ? 'has-error' : '') + '">';
                html += '<td class="stt-col">' + (index + 1) + '</td>';
                html += '<td>' + escapeHtml(s.ho_ten || '(trống)') + '</td>';
                html += '<td>' + escapeHtml(s.ngay_sinh || '-') + '</td>';
                html += '<td>';
                if (s.gioi_tinh === 'Nữ') {
                    html += '<span class="gender-badge gender-nu">Nữ</span>';
                } else {
                    html += '<span class="gender-badge gender-nam">Nam</span>';
                }
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            previewBody.innerHTML = html;
        }

        function showNoData() {
            document.getElementById('previewBody').innerHTML =
                '<div class="no-data">' +
                '<div class="no-data-icon">📋</div>' +
                '<div>Dán dữ liệu từ Excel vào bên trái</div>' +
                '<div style="font-size: 0.85rem; margin-top: 8px;">Preview sẽ tự động hiển thị</div>' +
                '</div>';
            document.getElementById('previewStats').style.display = 'none';
            document.getElementById('importBtn').disabled = true;
            document.getElementById('importCount').textContent = '0';
            document.getElementById('studentsInput').value = '[]';
            parsedStudents = [];
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function toggleInstructions() {
            document.getElementById('instructionsBox').classList.toggle('show');
        }

        function clearAll() {
            if (confirm('Bạn có chắc muốn xóa tất cả dữ liệu?')) {
                document.getElementById('pasteArea').value = '';
                showNoData();
            }
        }

        // Validate form before submit
        document.getElementById('importForm').addEventListener('submit', function(e) {
            var validStudents = parsedStudents.filter(function(s) { return s.errors.length === 0; });

            if (validStudents.length === 0) {
                e.preventDefault();
                alert('Không có học sinh hợp lệ để import!');
                return false;
            }

            if (!confirm('Bạn có chắc muốn import ' + validStudents.length + ' học sinh?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
