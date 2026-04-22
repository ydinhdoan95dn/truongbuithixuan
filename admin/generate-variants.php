<?php
/**
 * ==============================================
 * SINH ĐỀ BIẾN THỂ
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/week_helper.php';
require_once '../includes/variant_generator.php';

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

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'generate_from_exam') {
        $deGocId = intval($_POST['de_goc_id']);
        $soLuong = intval($_POST['so_luong']);

        if ($soLuong < 1) $soLuong = 5;
        if ($soLuong > 20) $soLuong = 20;

        // Kiểm tra quyền
        if (isGVCN()) {
            $stmtCheck = $conn->prepare("SELECT lop_id FROM de_thi WHERE id = ?");
            $stmtCheck->execute(array($deGocId));
            $exam = $stmtCheck->fetch();
            if ($exam['lop_id'] != $myLopId) {
                $message = 'Bạn không có quyền sinh biến thể cho đề này!';
                $messageType = 'error';
            }
        }

        if (!$message) {
            $result = generateVariantExams($deGocId, $soLuong);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }

    } elseif ($action === 'generate_from_template') {
        $lopId = intval($_POST['lop_id']);
        $soCau = intval($_POST['so_cau']);
        $tenDe = sanitize($_POST['ten_de']);
        $tuanId = !empty($_POST['tuan_id']) ? intval($_POST['tuan_id']) : null;

        // Kiểm tra quyền GVCN
        if (isGVCN() && $lopId != $myLopId) {
            $message = 'Bạn chỉ có thể tạo đề cho lớp mình!';
            $messageType = 'error';
        } else {
            $result = generateExamFromTemplate($lopId, $soCau, $tuanId, $tenDe);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
        }

    } elseif ($action === 'delete_variants') {
        $deGocId = intval($_POST['de_goc_id']);

        $result = deleteVariantExams($deGocId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
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

// Lấy danh sách đề gốc (không phải biến thể)
// Kiểm tra xem cột de_goc_id đã tồn tại chưa
$hasDeGocId = false;
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM de_thi LIKE 'de_goc_id'");
    $hasDeGocId = $checkCol->rowCount() > 0;
} catch (Exception $e) {
    $hasDeGocId = false;
}

$classFilter = getClassFilterSQL('dt', false);

if ($hasDeGocId) {
    $stmtDe = $conn->query("
        SELECT dt.*, mh.ten_mon, lh.ten_lop,
               (SELECT COUNT(*) FROM cau_hoi ch WHERE ch.de_thi_id = dt.id) as so_cau_hoi,
               (SELECT COUNT(*) FROM de_thi bt WHERE bt.de_goc_id = dt.id) as so_bien_the
        FROM de_thi dt
        JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
        JOIN lop_hoc lh ON dt.lop_id = lh.id
        WHERE (dt.is_bien_the = 0 OR dt.is_bien_the IS NULL) AND {$classFilter}
        ORDER BY dt.created_at DESC
    ");
} else {
    // Cột chưa tồn tại, query đơn giản hơn
    $stmtDe = $conn->query("
        SELECT dt.*, mh.ten_mon, lh.ten_lop,
               (SELECT COUNT(*) FROM cau_hoi ch WHERE ch.de_thi_id = dt.id) as so_cau_hoi,
               0 as so_bien_the
        FROM de_thi dt
        JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
        JOIN lop_hoc lh ON dt.lop_id = lh.id
        WHERE {$classFilter}
        ORDER BY dt.created_at DESC
    ");
}
$deGocList = $stmtDe->fetchAll();

// Lấy danh sách tuần
$semester = getCurrentSemester();
$tuanList = array();
if ($semester) {
    $tuanList = getWeeksBySemester($semester['id']);
}

$pageTitle = 'Sinh đề biến thể';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #EF4444; }

        .generator-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
        }
        .gen-tab {
            padding: 12px 24px;
            background: white;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .gen-tab:hover { border-color: #667eea; }
        .gen-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .exam-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .exam-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        .exam-info { flex: 1; }
        .exam-title { font-weight: 700; color: #1F2937; }
        .exam-meta { font-size: 0.85rem; color: #6B7280; margin-top: 4px; }
        .exam-stats {
            display: flex;
            gap: 16px;
        }
        .exam-stat {
            text-align: center;
            padding: 8px 12px;
            background: #F9FAFB;
            border-radius: 8px;
        }
        .exam-stat-value { font-weight: 700; color: #1F2937; }
        .exam-stat-label { font-size: 0.7rem; color: #9CA3AF; }
        .exam-actions {
            display: flex;
            gap: 8px;
        }

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .form-card h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937; margin-bottom: 24px;">
                🧮 <?php echo $pageTitle; ?>
            </h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="generator-tabs">
                <button class="gen-tab active" onclick="showTab('from-exam')">
                    📝 Từ đề có sẵn
                </button>
                <button class="gen-tab" onclick="showTab('from-template')">
                    🎲 Từ mẫu câu hỏi
                </button>
            </div>

            <!-- Tab 1: Sinh từ đề có sẵn -->
            <div id="tab-from-exam" class="tab-content active">
                <p style="color: #6B7280; margin-bottom: 20px;">
                    Chọn đề gốc để sinh các phiên bản biến thể với số liệu khác nhau nhưng cùng cấu trúc.
                </p>

                <?php if (empty($deGocList)): ?>
                    <div class="form-card" style="text-align: center;">
                        <div style="font-size: 4rem; margin-bottom: 16px;">📝</div>
                        <p style="color: #6B7280;">Chưa có đề thi nào</p>
                        <a href="<?php echo BASE_URL; ?>/admin/exams.php" class="btn btn-primary" style="margin-top: 16px;">
                            Tạo đề thi mới
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($deGocList as $de): ?>
                        <div class="exam-card">
                            <div class="exam-icon">📄</div>
                            <div class="exam-info">
                                <div class="exam-title"><?php echo htmlspecialchars($de['ten_de']); ?></div>
                                <div class="exam-meta">
                                    <?php echo $de['ten_mon']; ?> - <?php echo $de['ten_lop']; ?>
                                    <?php if ($de['mo_ta']): ?>
                                        | <?php echo htmlspecialchars($de['mo_ta']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="exam-stats">
                                <div class="exam-stat">
                                    <div class="exam-stat-value"><?php echo $de['so_cau_hoi']; ?>/<?php echo $de['so_cau']; ?></div>
                                    <div class="exam-stat-label">Câu hỏi</div>
                                </div>
                                <div class="exam-stat">
                                    <div class="exam-stat-value" style="color: #667eea;"><?php echo $de['so_bien_the']; ?></div>
                                    <div class="exam-stat-label">Biến thể</div>
                                </div>
                            </div>

                            <div class="exam-actions">
                                <button class="btn btn-primary btn-sm" onclick="showGenerateModal(<?php echo $de['id']; ?>, '<?php echo htmlspecialchars($de['ten_de'], ENT_QUOTES); ?>')">
                                    <i data-feather="copy"></i> Sinh biến thể
                                </button>
                                <?php if ($de['so_bien_the'] > 0): ?>
                                    <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteVariants(<?php echo $de['id']; ?>)">
                                        <i data-feather="trash-2"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Tab 2: Sinh từ mẫu -->
            <div id="tab-from-template" class="tab-content">
                <div class="form-card">
                    <h3>🎲 Sinh đề tự động từ mẫu câu hỏi</h3>
                    <p style="color: #6B7280; margin-bottom: 20px;">
                        Hệ thống sẽ tự động sinh câu hỏi Toán với các số ngẫu nhiên dựa trên mẫu đã cấu hình.
                    </p>

                    <form method="POST">
                        <input type="hidden" name="action" value="generate_from_template">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Tên đề thi</label>
                                <input type="text" name="ten_de" class="form-input" required
                                       placeholder="VD: Đề Toán tuần 16">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Lớp</label>
                                <select name="lop_id" class="form-input" required>
                                    <?php foreach ($lopList as $lop): ?>
                                        <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                            <div class="form-group">
                                <label class="form-label">Số câu hỏi</label>
                                <input type="number" name="so_cau" class="form-input" value="10" min="5" max="50">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tuần học (không bắt buộc)</label>
                                <select name="tuan_id" class="form-input">
                                    <option value="">-- Không gắn tuần --</option>
                                    <?php foreach ($tuanList as $tuan): ?>
                                        <option value="<?php echo $tuan['id']; ?>">
                                            <?php echo htmlspecialchars($tuan['ten_tuan']); ?>
                                            (<?php echo date('d/m', strtotime($tuan['ngay_bat_dau'])); ?> - <?php echo date('d/m', strtotime($tuan['ngay_ket_thuc'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i data-feather="zap"></i> Sinh đề tự động
                        </button>
                    </form>
                </div>

                <div class="form-card" style="margin-top: 24px;">
                    <h3>📋 Các loại câu hỏi được hỗ trợ</h3>
                    <ul style="color: #6B7280; line-height: 2;">
                        <li>✅ <strong>Phép cộng:</strong> a + b = ?</li>
                        <li>✅ <strong>Phép trừ:</strong> a - b = ? (a > b để không ra số âm)</li>
                        <li>✅ <strong>Phép nhân:</strong> a × b = ?</li>
                        <li>✅ <strong>Phép chia:</strong> a : b = ? (chia hết)</li>
                        <li>✅ <strong>So sánh:</strong> a ... b (điền dấu &gt;, &lt;, =)</li>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <!-- Generate Modal -->
    <div id="generateModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close" onclick="closeGenerateModal()">&times;</button>
            <h3 class="modal-title">Sinh đề biến thể</h3>
            <form method="POST">
                <input type="hidden" name="action" value="generate_from_exam">
                <input type="hidden" name="de_goc_id" id="gen_de_goc_id">

                <p style="color: #6B7280; margin-bottom: 16px;">
                    Đề gốc: <strong id="gen_de_ten"></strong>
                </p>

                <div class="form-group">
                    <label class="form-label">Số lượng biến thể (1-20)</label>
                    <input type="number" name="so_luong" class="form-input" value="5" min="1" max="20">
                </div>

                <p style="font-size: 0.85rem; color: #9CA3AF; margin-bottom: 16px;">
                    Mỗi biến thể sẽ có cùng cấu trúc câu hỏi nhưng với các số liệu khác nhau.
                </p>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i data-feather="copy"></i> Sinh biến thể
                </button>
            </form>
        </div>
    </div>

    <!-- Delete Variants Form -->
    <form id="deleteVariantsForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_variants">
        <input type="hidden" name="de_goc_id" id="delete_de_goc_id">
    </form>

    <script>
        feather.replace();

        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(function(el) {
                el.classList.remove('active');
            });
            document.querySelectorAll('.gen-tab').forEach(function(el) {
                el.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');
        }

        function showGenerateModal(deId, tenDe) {
            document.getElementById('gen_de_goc_id').value = deId;
            document.getElementById('gen_de_ten').textContent = tenDe;
            document.getElementById('generateModal').classList.add('active');
        }

        function closeGenerateModal() {
            document.getElementById('generateModal').classList.remove('active');
        }

        function deleteVariants(deId) {
            if (confirm('Bạn có chắc muốn xóa tất cả đề biến thể của đề này?')) {
                document.getElementById('delete_de_goc_id').value = deId;
                document.getElementById('deleteVariantsForm').submit();
            }
        }
    </script>
</body>
</html>
