<?php
/**
 * ==============================================
 * QUẢN LÝ CÂU HỎI
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
$examId = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Lấy thông tin đề thi nếu có
$currentExam = null;
if ($examId > 0) {
    $stmtExam = $conn->prepare("SELECT dt.*, mh.ten_mon, lh.ten_lop FROM de_thi dt JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id JOIN lop_hoc lh ON dt.lop_id = lh.id WHERE dt.id = ?");
    $stmtExam->execute(array($examId));
    $currentExam = $stmtExam->fetch();

    // GVCN chỉ xem được câu hỏi của lớp mình
    if (isGVCN() && $currentExam && $currentExam['lop_id'] != $myLopId) {
        $_SESSION['error_message'] = 'Bạn không có quyền xem câu hỏi của lớp khác!';
        redirect('admin/questions.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $deThiId = intval($_POST['de_thi_id']);
        $noiDung = sanitize($_POST['noi_dung']);
        $dapAnA = sanitize($_POST['dap_an_a']);
        $dapAnB = sanitize($_POST['dap_an_b']);
        $dapAnC = sanitize($_POST['dap_an_c']);
        $dapAnD = sanitize($_POST['dap_an_d']);
        $dapAnDung = strtoupper(sanitize($_POST['dap_an_dung']));
        $giaiThich = sanitize($_POST['giai_thich']);

        // Kiểm tra quyền thêm
        $canAdd = true;
        if (isGVCN()) {
            $stmtCheck = $conn->prepare("SELECT lop_id FROM de_thi WHERE id = ?");
            $stmtCheck->execute(array($deThiId));
            $exam = $stmtCheck->fetch();
            if ($exam['lop_id'] != $myLopId) {
                $canAdd = false;
                $message = 'Bạn không có quyền thêm câu hỏi cho đề thi này!';
                $messageType = 'error';
            }
        }

        if ($canAdd) {
            $stmt = $conn->prepare("INSERT INTO cau_hoi (de_thi_id, noi_dung, dap_an_a, dap_an_b, dap_an_c, dap_an_d, dap_an_dung, giai_thich) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($deThiId, $noiDung, $dapAnA, $dapAnB, $dapAnC, $dapAnD, $dapAnDung, $giaiThich));

            $message = 'Thêm câu hỏi thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $noiDung = sanitize($_POST['noi_dung']);
        $dapAnA = sanitize($_POST['dap_an_a']);
        $dapAnB = sanitize($_POST['dap_an_b']);
        $dapAnC = sanitize($_POST['dap_an_c']);
        $dapAnD = sanitize($_POST['dap_an_d']);
        $dapAnDung = strtoupper(sanitize($_POST['dap_an_dung']));
        $giaiThich = sanitize($_POST['giai_thich']);

        // Kiểm tra quyền sửa
        $canEdit = true;
        if (isGVCN()) {
            $stmtCheck = $conn->prepare("SELECT dt.lop_id FROM cau_hoi ch JOIN de_thi dt ON ch.de_thi_id = dt.id WHERE ch.id = ?");
            $stmtCheck->execute(array($id));
            $q = $stmtCheck->fetch();
            if ($q['lop_id'] != $myLopId) {
                $canEdit = false;
                $message = 'Bạn không có quyền sửa câu hỏi này!';
                $messageType = 'error';
            }
        }

        if ($canEdit) {
            $stmt = $conn->prepare("UPDATE cau_hoi SET noi_dung = ?, dap_an_a = ?, dap_an_b = ?, dap_an_c = ?, dap_an_d = ?, dap_an_dung = ?, giai_thich = ? WHERE id = ?");
            $stmt->execute(array($noiDung, $dapAnA, $dapAnB, $dapAnC, $dapAnD, $dapAnDung, $giaiThich, $id));

            $message = 'Cập nhật câu hỏi thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);

        // Kiểm tra quyền xóa
        $canDelete = true;
        $deleteError = '';

        // Lấy thông tin câu hỏi và đề thi
        $stmtCheck = $conn->prepare("
            SELECT ch.*, dt.lop_id, dt.ten_de, dt.is_chinh_thuc
            FROM cau_hoi ch
            JOIN de_thi dt ON ch.de_thi_id = dt.id
            WHERE ch.id = ?
        ");
        $stmtCheck->execute(array($id));
        $q = $stmtCheck->fetch();

        if (!$q) {
            $canDelete = false;
            $deleteError = 'Câu hỏi không tồn tại!';
        }

        // Kiểm tra quyền GVCN
        if ($canDelete && isGVCN() && $q['lop_id'] != $myLopId) {
            $canDelete = false;
            $deleteError = 'Bạn không có quyền xóa câu hỏi này!';
        }

        // Kiểm tra câu hỏi có trong bài thi chính thức đã hoàn thành
        if ($canDelete) {
            $stmtUsed = $conn->prepare("
                SELECT COUNT(*) as cnt
                FROM chi_tiet_bai_lam ctbl
                JOIN bai_lam bl ON ctbl.bai_lam_id = bl.id
                WHERE ctbl.cau_hoi_id = ? AND bl.is_chinh_thuc = 1 AND bl.trang_thai = 'hoan_thanh'
            ");
            $stmtUsed->execute(array($id));
            $usedResult = $stmtUsed->fetch();
            if ($usedResult['cnt'] > 0) {
                $canDelete = false;
                $deleteError = 'Không thể xóa! Câu hỏi này đã được sử dụng trong ' . $usedResult['cnt'] . ' bài thi chính thức. Dữ liệu này ảnh hưởng đến kết quả học sinh.';
            }
        }

        if ($canDelete) {
            try {
                // Xóa chi tiết bài làm luyện tập có câu hỏi này
                $conn->prepare("
                    DELETE ctbl FROM chi_tiet_bai_lam ctbl
                    JOIN bai_lam bl ON ctbl.bai_lam_id = bl.id
                    WHERE ctbl.cau_hoi_id = ? AND bl.is_chinh_thuc = 0
                ")->execute(array($id));

                // Xóa câu hỏi
                $stmt = $conn->prepare("DELETE FROM cau_hoi WHERE id = ?");
                $stmt->execute(array($id));

                $message = 'Xóa câu hỏi thành công!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Lỗi khi xóa câu hỏi: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = $deleteError;
            $messageType = 'error';
        }
    }
}

// Lấy danh sách đề thi (theo quyền)
$classFilter = getClassFilterSQL('dt', false);
$stmtDT = $conn->query("SELECT dt.id, dt.ten_de, dt.lop_id, mh.ten_mon, lh.ten_lop FROM de_thi dt JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id JOIN lop_hoc lh ON dt.lop_id = lh.id WHERE {$classFilter} ORDER BY dt.created_at DESC");
$deThiList = $stmtDT->fetchAll();

// Query câu hỏi (theo quyền)
$whereExam = $examId > 0 ? "AND ch.de_thi_id = " . intval($examId) : "";
$stmtCH = $conn->query("
    SELECT ch.*, dt.ten_de, dt.lop_id, mh.ten_mon
    FROM cau_hoi ch
    JOIN de_thi dt ON ch.de_thi_id = dt.id
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    WHERE {$classFilter} {$whereExam}
    ORDER BY ch.de_thi_id, ch.thu_tu, ch.id
");
$cauHoiList = $stmtCH->fetchAll();

$pageTitle = isGVCN() ? 'Câu hỏi ' . $admin['ten_lop'] : 'Ngân hàng câu hỏi';
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
        .question-card { background: white; border-radius: 16px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .question-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <div>
                    <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">❓ <?php echo $pageTitle; ?></h1>
                    <?php if ($currentExam): ?>
                        <p style="color: #6B7280; margin-top: 4px;">
                            Đề: <?php echo htmlspecialchars($currentExam['ten_de']); ?> - <?php echo $currentExam['ten_mon']; ?> (<?php echo $currentExam['ten_lop']; ?>)
                        </p>
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i data-feather="plus"></i> Thêm câu hỏi
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card" style="margin-bottom: 24px; padding: 16px;">
                <form method="GET" style="display: flex; gap: 16px; align-items: center;">
                    <select name="exam_id" class="form-input" style="width: auto;" onchange="this.form.submit()">
                        <option value="">Tất cả đề thi</option>
                        <?php foreach ($deThiList as $dt): ?>
                            <option value="<?php echo $dt['id']; ?>" <?php echo $examId == $dt['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dt['ten_de'] . ' - ' . $dt['ten_mon']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="color: #6B7280;">Tổng: <?php echo count($cauHoiList); ?> câu hỏi</span>
                </form>
            </div>

            <!-- Questions List -->
            <?php foreach ($cauHoiList as $index => $ch): ?>
                <div class="question-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <div>
                            <span style="display: inline-block; padding: 4px 12px; background: #F3F4F6; border-radius: 20px; font-size: 0.75rem; font-weight: 600; color: #6B7280; margin-right: 8px;">
                                Câu <?php echo $index + 1; ?>
                            </span>
                            <span style="font-size: 0.75rem; color: #9CA3AF;"><?php echo htmlspecialchars($ch['ten_de']); ?></span>
                        </div>
                        <div>
                            <button class="btn btn-ghost btn-sm" onclick='showEditModal(<?php echo json_encode($ch); ?>)'>
                                <i data-feather="edit-2"></i>
                            </button>
                            <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteQuestion(<?php echo $ch['id']; ?>)">
                                <i data-feather="trash-2"></i>
                            </button>
                        </div>
                    </div>

                    <p style="font-size: 1.1rem; font-weight: 600; color: #1F2937; margin-bottom: 16px;">
                        <?php echo htmlspecialchars($ch['noi_dung']); ?>
                    </p>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                        <?php
                        $options = array('A' => $ch['dap_an_a'], 'B' => $ch['dap_an_b'], 'C' => $ch['dap_an_c'], 'D' => $ch['dap_an_d']);
                        foreach ($options as $key => $value):
                            $isCorrect = $ch['dap_an_dung'] === $key;
                        ?>
                            <div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; <?php echo $isCorrect ? 'background: rgba(16, 185, 129, 0.1); border: 1px solid #10B981;' : 'background: #F9FAFB;'; ?>">
                                <span style="font-weight: 700; color: <?php echo $isCorrect ? '#10B981' : '#6B7280'; ?>;"><?php echo $key; ?>.</span>
                                <span style="<?php echo $isCorrect ? 'color: #10B981; font-weight: 600;' : ''; ?>"><?php echo htmlspecialchars($value); ?></span>
                                <?php if ($isCorrect): ?>
                                    <span style="margin-left: auto;">✓</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($ch['giai_thich'])): ?>
                        <div style="margin-top: 12px; padding: 12px; background: #FEF3C7; border-radius: 8px;">
                            <strong style="color: #92400E;">💡 Giải thích:</strong>
                            <span style="color: #92400E;"><?php echo htmlspecialchars($ch['giai_thich']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (empty($cauHoiList)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">❓</div>
                    <p class="empty-state-text">Chưa có câu hỏi nào</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
            <h3 class="modal-title">Thêm câu hỏi mới</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label class="form-label">Đề thi</label>
                    <select name="de_thi_id" class="form-input" required>
                        <?php foreach ($deThiList as $dt): ?>
                            <option value="<?php echo $dt['id']; ?>" <?php echo $examId == $dt['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dt['ten_de'] . ' - ' . $dt['ten_mon'] . ' (' . $dt['ten_lop'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nội dung câu hỏi</label>
                    <textarea name="noi_dung" class="form-input" rows="3" required placeholder="Nhập nội dung câu hỏi..."></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Đáp án A</label>
                        <input type="text" name="dap_an_a" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đáp án B</label>
                        <input type="text" name="dap_an_b" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đáp án C</label>
                        <input type="text" name="dap_an_c" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đáp án D</label>
                        <input type="text" name="dap_an_d" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Đáp án đúng</label>
                    <select name="dap_an_dung" class="form-input" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Giải thích (không bắt buộc)</label>
                    <textarea name="giai_thich" class="form-input" rows="2" placeholder="Giải thích tại sao đáp án đúng..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Thêm câu hỏi</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 600px;">
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
            <h3 class="modal-title">Sửa câu hỏi</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label class="form-label">Nội dung câu hỏi</label>
                    <textarea name="noi_dung" id="edit_noi_dung" class="form-input" rows="3" required></textarea>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Đáp án A</label>
                        <input type="text" name="dap_an_a" id="edit_dap_an_a" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đáp án B</label>
                        <input type="text" name="dap_an_b" id="edit_dap_an_b" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đáp án C</label>
                        <input type="text" name="dap_an_c" id="edit_dap_an_c" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Đáp án D</label>
                        <input type="text" name="dap_an_d" id="edit_dap_an_d" class="form-input" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Đáp án đúng</label>
                    <select name="dap_an_dung" id="edit_dap_an_dung" class="form-input" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Giải thích</label>
                    <textarea name="giai_thich" id="edit_giai_thich" class="form-input" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Lưu thay đổi</button>
            </form>
        </div>
    </div>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        feather.replace();

        function showAddModal() { document.getElementById('addModal').classList.add('active'); }
        function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }

        function showEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_noi_dung').value = data.noi_dung;
            document.getElementById('edit_dap_an_a').value = data.dap_an_a;
            document.getElementById('edit_dap_an_b').value = data.dap_an_b;
            document.getElementById('edit_dap_an_c').value = data.dap_an_c;
            document.getElementById('edit_dap_an_d').value = data.dap_an_d;
            document.getElementById('edit_dap_an_dung').value = data.dap_an_dung;
            document.getElementById('edit_giai_thich').value = data.giai_thich || '';
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }

        function deleteQuestion(id) {
            if (confirm('Bạn có chắc muốn xóa câu hỏi này?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
