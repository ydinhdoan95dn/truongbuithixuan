<?php
/**
 * ==============================================
 * IMPORT ĐỀ THI - SMART EDITOR
 * Hỗ trợ TinyMCE + MathJax + Auto-detect câu hỏi
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/week_helper.php';
require_once '../includes/word_import.php';

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

// Xử lý import từ editor
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'import_editor') {
        $tenDe = sanitize($_POST['ten_de']);
        $monHocId = intval($_POST['mon_hoc_id']);
        $lopId = intval($_POST['lop_id']);
        $tuanId = !empty($_POST['tuan_id']) ? intval($_POST['tuan_id']) : null;
        $questionsJson = isset($_POST['questions']) ? $_POST['questions'] : '[]';

        $questions = json_decode($questionsJson, true);

        // Kiểm tra quyền GVCN
        if (isGVCN() && $lopId != $myLopId) {
            $message = 'Bạn chỉ có thể tạo đề cho lớp mình!';
            $messageType = 'error';
        } elseif (empty($tenDe)) {
            $message = 'Vui lòng nhập tên đề thi!';
            $messageType = 'error';
        } elseif (empty($questions) || !is_array($questions)) {
            $message = 'Không có câu hỏi nào để import!';
            $messageType = 'error';
        } else {
            // Tạo đề thi
            try {
                $conn->beginTransaction();

                // Thêm đề thi
                $stmt = $conn->prepare("INSERT INTO de_thi (ten_de, mon_hoc_id, lop_id, admin_id, tuan_id, thoi_gian_cau, so_cau, created_at) VALUES (?, ?, ?, ?, ?, 180, ?, NOW())");
                $stmt->execute(array($tenDe, $monHocId, $lopId, $admin['id'], $tuanId, count($questions)));
                $deThiId = $conn->lastInsertId();

                // Thêm từng câu hỏi
                $stmtCau = $conn->prepare("INSERT INTO cau_hoi (de_thi_id, noi_dung, dap_an_a, dap_an_b, dap_an_c, dap_an_d, dap_an_dung, giai_thich, thu_tu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $thuTu = 1;
                $importedCount = 0;
                $errors = array();

                foreach ($questions as $q) {
                    if (empty($q['noi_dung'])) {
                        $errors[] = "Câu $thuTu: Thiếu nội dung câu hỏi";
                        continue;
                    }
                    if (empty($q['dap_an_a']) || empty($q['dap_an_b']) || empty($q['dap_an_c']) || empty($q['dap_an_d'])) {
                        $errors[] = "Câu $thuTu: Thiếu đáp án";
                        continue;
                    }
                    if (empty($q['dap_an_dung']) || !in_array($q['dap_an_dung'], array('A', 'B', 'C', 'D'))) {
                        $errors[] = "Câu $thuTu: Đáp án đúng không hợp lệ";
                        continue;
                    }

                    $giaiThich = isset($q['giai_thich']) ? $q['giai_thich'] : '';
                    $stmtCau->execute(array(
                        $deThiId,
                        $q['noi_dung'],
                        $q['dap_an_a'],
                        $q['dap_an_b'],
                        $q['dap_an_c'],
                        $q['dap_an_d'],
                        $q['dap_an_dung'],
                        $giaiThich,
                        $thuTu
                    ));
                    $importedCount++;
                    $thuTu++;
                }

                // Cập nhật số câu thực tế
                $stmtUpdate = $conn->prepare("UPDATE de_thi SET so_cau = ? WHERE id = ?");
                $stmtUpdate->execute(array($importedCount, $deThiId));

                $conn->commit();

                $message = "Import thành công $importedCount câu hỏi!";
                $messageType = 'success';
                $importResult = array(
                    'success' => true,
                    'de_thi_id' => $deThiId,
                    'imported' => $importedCount,
                    'errors' => $errors
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

// Lấy danh sách môn học
$stmtMon = $conn->query("SELECT * FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$monList = $stmtMon->fetchAll();

// Lấy danh sách tuần
$semester = getCurrentSemester();
$tuanList = array();
if ($semester) {
    $tuanList = getWeeksBySemester($semester['id']);
}

$pageTitle = 'Smart Editor - Import Đề Thi';
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
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/0mynsf76992hnllxsipvvkuoe4qcfdesqs0gpza59jmvi223/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <!-- MathJax for rendering math formulas -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
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
            min-height: 600px;
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

        #editorContent {
            width: 100%;
            height: 100%;
            min-height: 400px;
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
            color: #4F46E5;
        }

        .question-card {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            border-left: 4px solid #4F46E5;
        }

        .question-card.has-error {
            border-left-color: #EF4444;
            background: rgba(239, 68, 68, 0.05);
        }

        .question-number {
            font-weight: 700;
            color: #4F46E5;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .question-content {
            color: #1F2937;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .answers-list {
            display: grid;
            gap: 8px;
        }

        .answer-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 8px;
            color: #6B7280;
            font-size: 0.95rem;
        }

        .answer-item.correct {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            font-weight: 600;
        }

        .answer-letter {
            font-weight: 700;
            min-width: 24px;
        }

        .answer-item.correct .answer-letter::after {
            content: ' ✓';
            color: #10B981;
        }

        .error-badge {
            background: #EF4444;
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
        }

        .no-questions {
            text-align: center;
            padding: 60px 20px;
            color: #9CA3AF;
        }

        .no-questions-icon {
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
            grid-template-columns: repeat(4, 1fr);
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
            margin-top: 12px;
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

        .explanation-box {
            margin-top: 10px;
            padding: 8px 12px;
            background: rgba(79,70,229,0.06);
            border-radius: 8px;
            font-size: 0.88rem;
            color: #4338CA;
        }
        .explanation-box span { font-weight: 600; }

        /* Typing indicator */
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #4F46E5;
        }

        .typing-indicator.show {
            display: flex;
        }

        .typing-dots {
            display: flex;
            gap: 3px;
        }

        .typing-dots span {
            width: 6px;
            height: 6px;
            background: #4F46E5;
            border-radius: 50%;
            animation: typing 1s infinite;
        }

        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1); }
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
                ✨ Smart Editor - Import Đề Thi
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
                            <a href="<?php echo BASE_URL; ?>/admin/questions.php?exam_id=<?php echo $importResult['de_thi_id']; ?>" class="btn btn-primary btn-sm">
                                Xem câu hỏi đã import
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Split screen: Editor | Preview -->
            <div class="smart-editor-container">
                <!-- Left: Editor -->
                <div class="editor-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            📝 Nhập nội dung đề thi
                        </div>
                        <button class="instructions-toggle" onclick="toggleInstructions()">
                            <i data-feather="help-circle" style="width: 16px; height: 16px;"></i>
                            Hướng dẫn format
                        </button>
                    </div>
                    <div class="panel-body">
                        <div class="instructions-box" id="instructionsBox">
                            <h4>📋 Các định dạng được hỗ trợ tự động:</h4>
                            <pre style="font-size:0.8rem;">─── Format 1: Câu + số ───
Câu 1: Nội dung câu hỏi?
A. Đáp án A
B. Đáp án B
C. Đáp án C
D. Đáp án D
Đáp án: B

─── Format 2: Số đơn thuần ───
1. Nội dung câu hỏi?
A) Đáp án A
B) Đáp án B
C) Đáp án C
D) Đáp án D
ĐA: C

─── Format 3: Đáp án đúng tô đậm (từ Word) ───
Câu 3: Nội dung câu hỏi?
A. Đáp án A
**B. Đáp án đúng** ← in đậm → tự nhận ra
C. Đáp án C
D. Đáp án D

─── Format 4: Đánh dấu sao (*) ───
Câu 4: Nội dung?
A. Đáp án A
*B. Đáp án đúng
C. Đáp án C
D. Đáp án D

─── Format 5: Có giải thích ───
Câu 5: Nội dung câu hỏi?
A. Đáp án A
B. Đáp án B
C. Đáp án C
D. Đáp án D
Đáp án: A
Giải thích: Vì...</pre>
                            <div style="margin-top: 10px; font-size: 0.83rem; color: #92400E;">
                                <strong>Mẹo:</strong> Copy thẳng từ Word — đáp án in đậm tự động được nhận diện!
                            </div>
                        </div>
                        <div class="editor-wrapper">
                            <textarea id="editorContent"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right: Preview -->
                <div class="preview-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            👁️ Preview câu hỏi
                            <span class="typing-indicator" id="typingIndicator">
                                <span class="typing-dots">
                                    <span></span><span></span><span></span>
                                </span>
                                Đang phân tích...
                            </span>
                        </div>
                        <div class="preview-stats" id="previewStats" style="display: none;">
                            <div class="stat-item">
                                <span>📊 Tổng:</span>
                                <span class="stat-value" id="totalQuestions">0</span> câu
                            </div>
                            <div class="stat-item">
                                <span>✅ Hợp lệ:</span>
                                <span class="stat-value" id="validQuestions">0</span>
                            </div>
                            <div class="stat-item">
                                <span>⚠️ Lỗi:</span>
                                <span class="stat-value" id="errorQuestions" style="color: #EF4444;">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="preview-body" id="previewBody">
                        <div class="no-questions">
                            <div class="no-questions-icon">📄</div>
                            <div>Nhập hoặc dán nội dung đề thi vào bên trái</div>
                            <div style="font-size: 0.85rem; margin-top: 8px;">Preview sẽ tự động hiển thị</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Form -->
            <div class="import-form-section">
                <form method="POST" id="importForm">
                    <input type="hidden" name="action" value="import_editor">
                    <input type="hidden" name="questions" id="questionsInput" value="[]">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tên đề thi *</label>
                            <input type="text" name="ten_de" class="form-input" required placeholder="VD: Đề Toán tuần 16">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Môn học *</label>
                            <select name="mon_hoc_id" class="form-input" required>
                                <?php foreach ($monList as $mon): ?>
                                    <option value="<?php echo $mon['id']; ?>"><?php echo htmlspecialchars($mon['ten_mon']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lớp *</label>
                            <select name="lop_id" class="form-input" required>
                                <?php foreach ($lopList as $lop): ?>
                                    <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tuần học</label>
                            <select name="tuan_id" class="form-input">
                                <option value="">-- Không gắn tuần --</option>
                                <?php foreach ($tuanList as $tuan): ?>
                                    <option value="<?php echo $tuan['id']; ?>">
                                        <?php echo htmlspecialchars($tuan['ten_tuan']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="clearEditor()">
                            <i data-feather="trash-2"></i> Xóa tất cả
                        </button>
                        <button type="submit" class="btn btn-primary" id="importBtn" disabled>
                            <i data-feather="upload"></i> Import đề thi (<span id="importCount">0</span> câu)
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        feather.replace();

        var parseTimeout = null;
        var parsedQuestions = [];
        var tinyMCEInstance = null;

        // Init TinyMCE
        tinymce.init({
            selector: '#editorContent',
            height: '100%',
            min_height: 400,
            plugins: 'lists advlist autolink link charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime table help wordcount paste',
            toolbar: 'undo redo | formatselect | bold italic underline | bullist numlist | charmap | removeformat | help',
            menubar: false,
            statusbar: true,
            paste_as_text: false,
            paste_word_valid_elements: 'p,b,strong,i,em,u,br',
            content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; line-height: 1.6; }',
            setup: function(editor) {
                tinyMCEInstance = editor;

                // Monitor changes
                editor.on('input', function() {
                    scheduleParseContent();
                });

                editor.on('change', function() {
                    scheduleParseContent();
                });

                editor.on('paste', function() {
                    // Wait for paste to complete
                    setTimeout(function() {
                        scheduleParseContent();
                    }, 100);
                });

                editor.on('keyup', function() {
                    scheduleParseContent();
                });
            }
        });

        // Debounce: Auto-parse after user stops typing for 1.5 seconds
        function scheduleParseContent() {
            // Show typing indicator
            document.getElementById('typingIndicator').classList.add('show');

            // Clear previous timeout
            if (parseTimeout) {
                clearTimeout(parseTimeout);
            }

            // Schedule new parse
            parseTimeout = setTimeout(function() {
                parseContent();
                document.getElementById('typingIndicator').classList.remove('show');
            }, 1500);
        }

        // Parse content and extract questions
        function parseContent() {
            if (!tinyMCEInstance) return;
            var text = tinyMCEInstance.getContent({ format: 'text' });
            var html = tinyMCEInstance.getContent({ format: 'html' });
            if (!text.trim()) { showNoQuestions(); return; }
            parsedQuestions = parseQuestionsSmartV2(text, html);
            updatePreview();
        }

        // ─── Smart parser v2: HTML-aware + state machine ───────────────────────
        function parseQuestionsSmartV2(text, html) {
            var boldSet = extractBoldTextSet(html);
            var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            var questions = [];
            var cur = null;
            var state = 'IDLE'; // IDLE | IN_QUESTION | IN_ANSWERS

            for (var i = 0; i < lines.length; i++) {
                var line = lines[i].trim();
                if (!line) continue;

                // 1. Question start?
                var qText = matchQuestionStart(line);
                if (qText !== null) {
                    if (cur) { finalizeQ(cur, boldSet); questions.push(cur); }
                    cur = { noi_dung: qText, dap_an_a: '', dap_an_b: '', dap_an_c: '', dap_an_d: '', dap_an_dung: '', giai_thich: '', errors: [] };
                    state = 'IN_QUESTION';
                    continue;
                }

                if (!cur) continue;

                // 2. Answer option?
                var am = matchAnswerLine(line);
                if (am !== null) {
                    state = 'IN_ANSWERS';
                    cur['dap_an_' + am.letter.toLowerCase()] = am.text;
                    if (am.markedCorrect) {
                        cur.dap_an_dung = am.letter;
                    } else if (!cur.dap_an_dung && boldSet[am.text]) {
                        // Bold answer from Word = correct
                        cur.dap_an_dung = am.letter;
                    }
                    continue;
                }

                // 3. Explicit correct answer line?
                var dm = matchCorrectAnswerLine(line);
                if (dm !== null) { cur.dap_an_dung = dm; continue; }

                // 4. Explanation?
                var em = matchExplanationLine(line);
                if (em !== null) { cur.giai_thich = em; continue; }

                // 5. Continuation of current section
                if (state === 'IN_QUESTION') {
                    cur.noi_dung += '\n' + line;
                } else if (state === 'IN_ANSWERS') {
                    var lastL = getLastFilledAnswer(cur);
                    if (lastL) cur['dap_an_' + lastL.toLowerCase()] += ' ' + line;
                }
            }

            if (cur) { finalizeQ(cur, boldSet); questions.push(cur); }

            // Validate
            questions.forEach(function(q) {
                q.errors = [];
                if (!q.noi_dung)    q.errors.push('Thiếu nội dung');
                if (!q.dap_an_a)    q.errors.push('Thiếu đáp án A');
                if (!q.dap_an_b)    q.errors.push('Thiếu đáp án B');
                if (!q.dap_an_c)    q.errors.push('Thiếu đáp án C');
                if (!q.dap_an_d)    q.errors.push('Thiếu đáp án D');
                if (!q.dap_an_dung) q.errors.push('Thiếu đáp án đúng');
            });

            return questions;
        }

        // Returns question text (stripped prefix) or null
        function matchQuestionStart(line) {
            // "Câu 1:", "Câu 1.", "1.", "1:", "1)", "1/"
            var m = line.match(/^(?:(?:Câu|Question|Q)\s+)?(\d{1,3})\s*[.:)\s\/\-]+\s*(.+)/i);
            if (!m) return null;
            var n = parseInt(m[1]);
            if (n < 1 || n > 500) return null;
            // Avoid matching answer lines like "A. something" (letters only)
            // and lines where the "number" is part of a formula "1+1"
            var after = m[2].trim();
            if (!after) return null;
            return after;
        }

        // Returns {letter, text, markedCorrect} or null
        function matchAnswerLine(line) {
            // Asterisk prefix marks correct: *A. Text
            var starM = line.match(/^\*([A-Da-d])[.):\s\-]+(.+)/);
            if (starM) return { letter: starM[1].toUpperCase(), text: starM[2].trim(), markedCorrect: true };

            // Normal: A. / A) / A: / A -
            var m = line.match(/^([A-Da-d])[.):\s\-]+(.+)/);
            if (!m) return null;

            var letter = m[1].toUpperCase();
            var text = m[2].trim();
            var marked = false;

            // Trailing asterisk: "Answer text *"
            if (/\s\*$/.test(text)) {
                marked = true;
                text = text.replace(/\s\*$/, '').trim();
            }
            // Trailing (X) where X matches this letter: "Answer text (B)"
            var tailBracket = text.match(/\(([A-D])\)$/i);
            if (tailBracket && tailBracket[1].toUpperCase() === letter) {
                marked = true;
                text = text.replace(/\([A-D]\)$/i, '').trim();
            }

            return { letter: letter, text: text, markedCorrect: marked };
        }

        // Returns letter A-D or null
        function matchCorrectAnswerLine(line) {
            var m = line.match(/^(?:Đáp\s*án(?:\s*(?:đúng|là|:))?|ĐA|Answer(?:\s*key)?|Key|Chọn)\s*[:\s]\s*([A-Da-d])/i);
            return m ? m[1].toUpperCase() : null;
        }

        // Returns explanation text or null
        function matchExplanationLine(line) {
            var m = line.match(/^(?:Giải\s*thích|Giải|Hướng\s*dẫn|Note|Explanation?|Lý\s*do)\s*[:\s]+(.+)/i);
            return m ? m[1].trim() : null;
        }

        function getLastFilledAnswer(q) {
            if (q.dap_an_d) return 'D';
            if (q.dap_an_c) return 'C';
            if (q.dap_an_b) return 'B';
            if (q.dap_an_a) return 'A';
            return null;
        }

        function finalizeQ(q, boldSet) {
            q.noi_dung = q.noi_dung.trim();
            // Last-resort bold detection: match against full answer strings
            if (!q.dap_an_dung) {
                ['A','B','C','D'].forEach(function(L) {
                    var txt = q['dap_an_' + L.toLowerCase()];
                    if (txt && boldSet[txt.trim()]) q.dap_an_dung = L;
                });
            }
        }

        // Build set of bold text content from TinyMCE HTML
        function extractBoldTextSet(html) {
            var set = {};
            if (!html) return set;
            try {
                var div = document.createElement('div');
                div.innerHTML = html;
                var bolds = div.querySelectorAll('strong, b');
                for (var i = 0; i < bolds.length; i++) {
                    var t = bolds[i].textContent.trim();
                    // Strip "A. " prefix if bold wraps the whole answer line
                    var cleaned = t.replace(/^[A-D][.):\s\-]+/i, '').trim();
                    if (cleaned) set[cleaned] = true;
                    if (t) set[t] = true;
                }
            } catch(e) {}
            return set;
        }

        // Update preview panel
        function updatePreview() {
            var previewBody = document.getElementById('previewBody');
            var previewStats = document.getElementById('previewStats');

            if (parsedQuestions.length === 0) {
                showNoQuestions();
                return;
            }

            // Count stats
            var total = parsedQuestions.length;
            var valid = parsedQuestions.filter(function(q) { return q.errors.length === 0; }).length;
            var errors = total - valid;

            // Update stats
            document.getElementById('totalQuestions').textContent = total;
            document.getElementById('validQuestions').textContent = valid;
            document.getElementById('errorQuestions').textContent = errors;
            previewStats.style.display = 'flex';

            // Update import button
            document.getElementById('importCount').textContent = valid;
            document.getElementById('importBtn').disabled = (valid === 0);

            // Store valid questions for form submission
            var validQuestions = parsedQuestions.filter(function(q) { return q.errors.length === 0; });
            document.getElementById('questionsInput').value = JSON.stringify(validQuestions);

            // Render questions
            var html = '';
            parsedQuestions.forEach(function(q, index) {
                var hasError = q.errors.length > 0;
                html += '<div class="question-card' + (hasError ? ' has-error' : '') + '">';
                html += '<div class="question-number">';
                html += 'Câu ' + (index + 1);
                if (hasError) {
                    html += ' <span class="error-badge">' + q.errors.join(', ') + '</span>';
                }
                html += '</div>';
                html += '<div class="question-content">' + escapeHtml(q.noi_dung) + '</div>';
                html += '<div class="answers-list">';
                ['A','B','C','D'].forEach(function(L) {
                    html += renderAnswer(L, q['dap_an_' + L.toLowerCase()], q.dap_an_dung === L);
                });
                html += '</div>';
                if (q.giai_thich) {
                    html += '<div class="explanation-box"><span>💡 Giải thích:</span> ' + escapeHtml(q.giai_thich) + '</div>';
                }
                html += '</div>';
            });

            previewBody.innerHTML = html;

            // Re-render MathJax if present
            if (window.MathJax) {
                MathJax.typesetPromise([previewBody]).catch(function(err) {
                    console.log('MathJax error:', err);
                });
            }
        }

        function renderAnswer(letter, text, isCorrect) {
            if (!text) text = '(chưa có)';
            return '<div class="answer-item' + (isCorrect ? ' correct' : '') + '">' +
                   '<span class="answer-letter">' + letter + '.</span>' +
                   '<span>' + escapeHtml(text) + '</span>' +
                   '</div>';
        }

        function showNoQuestions() {
            document.getElementById('previewBody').innerHTML =
                '<div class="no-questions">' +
                '<div class="no-questions-icon">📄</div>' +
                '<div>Nhập hoặc dán nội dung đề thi vào bên trái</div>' +
                '<div style="font-size: 0.85rem; margin-top: 8px;">Preview sẽ tự động hiển thị</div>' +
                '</div>';
            document.getElementById('previewStats').style.display = 'none';
            document.getElementById('importBtn').disabled = true;
            document.getElementById('importCount').textContent = '0';
            document.getElementById('questionsInput').value = '[]';
            parsedQuestions = [];
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

        function clearEditor() {
            if (confirm('Bạn có chắc muốn xóa tất cả nội dung?')) {
                if (tinyMCEInstance) {
                    tinyMCEInstance.setContent('');
                }
                showNoQuestions();
            }
        }

        // Validate form before submit
        document.getElementById('importForm').addEventListener('submit', function(e) {
            var validQuestions = parsedQuestions.filter(function(q) { return q.errors.length === 0; });

            if (validQuestions.length === 0) {
                e.preventDefault();
                alert('Không có câu hỏi hợp lệ để import!');
                return false;
            }

            if (!confirm('Bạn có chắc muốn import ' + validQuestions.length + ' câu hỏi?')) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
