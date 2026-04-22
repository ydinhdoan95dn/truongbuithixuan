<?php
/**
 * ==============================================
 * TRANG KẾT QUẢ - FULLSCREEN DESKTOP APP
 * Giao diện không scroll - phân trang thông minh
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/device.php';

// Redirect sang mobile nếu là thiết bị di động
$mobileUrl = BASE_URL . '/student/mobile/result.php';
if (isset($_GET['session'])) $mobileUrl .= '?session=' . $_GET['session'];
redirectIfMobile($mobileUrl);

if (!isStudentLoggedIn()) {
    redirect('login.php');
}

$student = getCurrentStudent();
$sessionToken = isset($_GET['session']) ? $_GET['session'] : '';

if (empty($sessionToken)) {
    redirect('student/dashboard.php');
}

$conn = getDBConnection();

// Lấy kết quả bài làm
$stmtBL = $conn->prepare("
    SELECT bl.*, dt.ten_de, mh.ten_mon
    FROM bai_lam bl
    JOIN de_thi dt ON bl.de_thi_id = dt.id
    JOIN mon_hoc mh ON dt.mon_hoc_id = mh.id
    WHERE bl.session_token = ? AND bl.hoc_sinh_id = ? AND bl.trang_thai = 'hoan_thanh'
");
$stmtBL->execute([$sessionToken, $student['id']]);
$baiLam = $stmtBL->fetch();

if (!$baiLam) {
    redirect('student/dashboard.php');
}

$evaluation = evaluateResult($baiLam['diem']);

// Lấy chi tiết bài làm
$stmtCT = $conn->prepare("
    SELECT ctbl.*, ch.noi_dung, ch.dap_an_a, ch.dap_an_b, ch.dap_an_c, ch.dap_an_d, ch.dap_an_dung, ch.giai_thich
    FROM chi_tiet_bai_lam ctbl
    JOIN cau_hoi ch ON ctbl.cau_hoi_id = ch.id
    WHERE ctbl.bai_lam_id = ?
    ORDER BY ctbl.thu_tu_cau ASC
");
$stmtCT->execute([$baiLam['id']]);
$chiTietList = $stmtCT->fetchAll();

$jsData = array(
    'baiLam' => $baiLam,
    'evaluation' => $evaluation,
    'chiTiet' => $chiTietList,
    'baseUrl' => BASE_URL
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; font-family: 'Inter', sans-serif; background: #F0F2F5; }
        .app-container { display: flex; height: 100vh; width: 100vw; }
        .sidebar { width: 280px; min-width: 280px; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); display: flex; flex-direction: column; color: white; padding: 20px; }
        .sidebar-header { text-align: center; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 20px; }
        .result-icon { font-size: 4rem; margin-bottom: 10px; }
        .result-text { font-size: 1.5rem; font-weight: 700; }
        .result-score-big { font-size: 3.5rem; font-weight: 700; margin: 16px 0; }
        .result-exam { font-size: 0.9rem; opacity: 0.9; }
        .result-stats { display: grid; gap: 12px; margin-bottom: 20px; }
        .result-stat { background: rgba(255,255,255,0.15); border-radius: 12px; padding: 16px; display: flex; align-items: center; gap: 12px; }
        .result-stat-icon { font-size: 1.5rem; }
        .result-stat-value { font-size: 1.3rem; font-weight: 700; }
        .result-stat-label { font-size: 0.8rem; opacity: 0.8; }
        .sidebar-actions { margin-top: auto; display: flex; flex-direction: column; gap: 10px; }
        .action-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px; border-radius: 12px; text-decoration: none; font-weight: 700; transition: all 0.2s; font-size: 1rem; }
        .action-btn.primary { background: white; color: #667eea; }
        .action-btn.secondary { background: rgba(255,255,255,0.2); color: white; }
        .action-btn:hover { transform: translateY(-2px); }
        .main-content { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .content-header { background: white; padding: 16px 24px; display: flex; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); flex-shrink: 0; }
        .page-title { font-size: 1.4rem; font-weight: 700; color: #1F2937; display: flex; align-items: center; gap: 10px; }
        .content-body { flex: 1; display: flex; flex-direction: column; padding: 20px 24px; overflow: hidden; }
        .pagination-bar { display: flex; align-items: center; justify-content: space-between; background: white; padding: 12px 20px; border-radius: 14px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); flex-shrink: 0; }
        .page-info { font-weight: 600; color: #4B5563; }
        .page-info strong { color: #667eea; }
        .pagination-btns { display: flex; gap: 10px; }
        .page-btn { display: flex; align-items: center; gap: 8px; padding: 12px 24px; border: none; border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: inherit; }
        .page-btn.prev { background: #E5E7EB; color: #374151; }
        .page-btn.next { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .review-grid { flex: 1; display: grid; gap: 16px; overflow: hidden; }
        .review-card { background: white; border-radius: 16px; padding: 20px; border-left: 5px solid #E5E7EB; }
        .review-card.correct { border-left-color: #10B981; }
        .review-card.incorrect { border-left-color: #EF4444; }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .review-q-num { font-weight: 700; color: #6B7280; }
        .review-status { display: flex; align-items: center; gap: 6px; font-weight: 700; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; }
        .review-status.correct { background: rgba(16, 185, 129, 0.15); color: #10B981; }
        .review-status.incorrect { background: rgba(239, 68, 68, 0.15); color: #EF4444; }
        .review-question { font-size: 1rem; font-weight: 600; color: #1F2937; margin-bottom: 12px; line-height: 1.5; }
        .review-answers { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .review-answer { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; font-size: 0.9rem; font-weight: 500; }
        .review-answer.correct-answer { background: rgba(16, 185, 129, 0.1); border: 2px solid #10B981; }
        .review-answer.wrong-chosen { background: rgba(239, 68, 68, 0.1); border: 2px solid #EF4444; }
        .review-answer.normal { background: #F9FAFB; border: 2px solid #E5E7EB; }
        .answer-letter { width: 28px; height: 28px; border-radius: 8px; background: #E5E7EB; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; flex-shrink: 0; }
        .confetti-container { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 9999; }
        .confetti-piece { position: absolute; width: 10px; height: 10px; border-radius: 50%; animation: confettiFall 4s ease-out forwards; }
        @keyframes confettiFall { 0% { transform: translateY(-100px) rotate(0deg); opacity: 1; } 100% { transform: translateY(100vh) rotate(720deg); opacity: 0; } }

        /* ========== RESPONSIVE CHO MÀN HÌNH NHỎ ========== */
        @media (max-width: 1366px) {
            .sidebar { width: 280px; min-width: 280px; padding: 20px; }
            .result-icon { font-size: 3rem; margin-bottom: 12px; }
            .result-score { font-size: 2.5rem; }
            .result-label { font-size: 1.1rem; }
            .stats-grid { gap: 10px; }
            .stat-item { padding: 12px; }
            .stat-value { font-size: 1.3rem; }
            .stat-label { font-size: 0.7rem; }
            .main-content { padding: 16px; }
            .pagination-bar { padding: 10px 14px; }
            .page-btn { padding: 10px 18px; font-size: 0.9rem; }
            .review-card { padding: 16px; }
            .review-question { font-size: 0.95rem; }
            .review-answer { padding: 8px 12px; font-size: 0.85rem; }
        }

        @media (max-height: 768px) {
            .sidebar { padding: 16px; }
            .result-icon { font-size: 2.5rem; margin-bottom: 10px; }
            .result-score { font-size: 2.2rem; }
            .result-label { font-size: 1rem; }
            .stars { font-size: 1.3rem; margin-top: 8px; }
            .stats-grid { gap: 8px; margin-bottom: 16px; }
            .stat-item { padding: 10px; }
            .stat-value { font-size: 1.2rem; }
            .sidebar-actions { gap: 8px; }
            .action-btn { padding: 10px; font-size: 0.85rem; }
            .main-content { padding: 12px; }
            .pagination-bar { padding: 8px 12px; margin-bottom: 10px; }
            .page-btn { padding: 8px 14px; font-size: 0.85rem; }
            .review-grid { gap: 10px; }
            .review-card { padding: 12px; }
            .review-header { margin-bottom: 8px; }
            .review-question { font-size: 0.9rem; margin-bottom: 10px; }
            .review-answers { gap: 6px; }
            .review-answer { padding: 6px 10px; font-size: 0.8rem; }
            .answer-letter { width: 24px; height: 24px; font-size: 0.75rem; }
        }

        @media (max-height: 680px) {
            .sidebar { padding: 12px; }
            .result-icon { font-size: 2rem; }
            .result-score { font-size: 1.8rem; }
            .stat-item { padding: 8px; }
            .stat-value { font-size: 1.1rem; }
            .action-btn { padding: 8px; font-size: 0.8rem; }
            .review-card { padding: 10px; }
            .review-question { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="result-icon"><?php echo $evaluation['icon']; ?></div>
                <div class="result-text"><?php echo $evaluation['text']; ?></div>
                <div class="result-score-big"><?php echo number_format($baiLam['diem'], 1); ?></div>
                <div class="result-exam"><?php echo htmlspecialchars($baiLam['ten_de']); ?><br><?php echo htmlspecialchars($baiLam['ten_mon']); ?></div>
            </div>
            <div class="result-stats">
                <div class="result-stat"><div class="result-stat-icon">✅</div><div><div class="result-stat-value"><?php echo $baiLam['so_cau_dung']; ?></div><div class="result-stat-label">Câu đúng</div></div></div>
                <div class="result-stat"><div class="result-stat-icon">❌</div><div><div class="result-stat-value"><?php echo $baiLam['tong_cau'] - $baiLam['so_cau_dung']; ?></div><div class="result-stat-label">Câu sai</div></div></div>
                <div class="result-stat"><div class="result-stat-icon">⏱️</div><div><div class="result-stat-value"><?php echo formatTime($baiLam['tong_thoi_gian']); ?></div><div class="result-stat-label">Thời gian</div></div></div>
            </div>
            <div class="sidebar-actions">
                <a href="<?php echo BASE_URL; ?>/student/dashboard.php" class="action-btn primary"><span>🏠</span> Về trang chủ</a>
                <a href="<?php echo BASE_URL; ?>/student/ranking.php" class="action-btn secondary"><span>🏆</span> Xem xếp hạng</a>
            </div>
        </aside>
        <main class="main-content">
            <header class="content-header"><h1 class="page-title"><span>📋</span><span>Chi tiết bài làm</span></h1></header>
            <div class="content-body" id="contentBody"></div>
        </main>
    </div>
    <div class="confetti-container" id="confettiContainer"></div>
    <script>
        var DATA = <?php echo json_encode($jsData, JSON_UNESCAPED_UNICODE); ?>;
        var SCREEN = { itemsPerPage: 2, currentPage: 1, totalPages: 1 };

        document.addEventListener('DOMContentLoaded', function() {
            calculateScreen(); renderReview();
            if (DATA.baiLam.diem >= 8) createConfetti();
            window.addEventListener('resize', function() { calculateScreen(); renderReview(); });
        });

        function calculateScreen() {
            var contentH = window.innerHeight - 130;
            SCREEN.itemsPerPage = Math.max(1, Math.min(3, Math.floor(contentH / 220)));
            SCREEN.totalPages = Math.ceil(DATA.chiTiet.length / SCREEN.itemsPerPage) || 1;
            if (SCREEN.currentPage > SCREEN.totalPages) SCREEN.currentPage = 1;
        }

        function renderReview() {
            var start = (SCREEN.currentPage - 1) * SCREEN.itemsPerPage;
            var items = DATA.chiTiet.slice(start, start + SCREEN.itemsPerPage);
            var html = '<div class="pagination-bar"><div class="page-info">Trang <strong>' + SCREEN.currentPage + '</strong> / ' + SCREEN.totalPages + ' • <strong>' + DATA.chiTiet.length + '</strong> câu</div><div class="pagination-btns"><button class="page-btn prev"' + (SCREEN.currentPage <= 1 ? ' disabled' : '') + ' onclick="goPage(' + (SCREEN.currentPage - 1) + ')">◀️ Trước</button><button class="page-btn next"' + (SCREEN.currentPage >= SCREEN.totalPages ? ' disabled' : '') + ' onclick="goPage(' + (SCREEN.currentPage + 1) + ')">Tiếp ▶️</button></div></div>';
            html += '<div class="review-grid">';
            for (var i = 0; i < items.length; i++) {
                var ct = items[i], isCorrect = ct.is_dung == 1, cls = isCorrect ? 'correct' : 'incorrect';
                html += '<div class="review-card ' + cls + '"><div class="review-header"><span class="review-q-num">Câu ' + (start + i + 1) + '</span><span class="review-status ' + cls + '">' + (isCorrect ? '✓ Đúng' : '✗ Sai') + '</span></div><div class="review-question">' + esc(ct.noi_dung) + '</div><div class="review-answers">';
                var opts = [{k:'A',v:ct.dap_an_a},{k:'B',v:ct.dap_an_b},{k:'C',v:ct.dap_an_c},{k:'D',v:ct.dap_an_d}];
                for (var j = 0; j < 4; j++) {
                    var o = opts[j], chosen = ct.dap_an_chon && ct.dap_an_chon.toUpperCase() === o.k, correct = ct.dap_an_dung.toUpperCase() === o.k;
                    var ac = correct ? 'correct-answer' : (chosen && !correct ? 'wrong-chosen' : 'normal');
                    html += '<div class="review-answer ' + ac + '"><span class="answer-letter">' + o.k + '</span><span>' + esc(o.v) + '</span></div>';
                }
                html += '</div></div>';
            }
            html += '</div>';
            document.getElementById('contentBody').innerHTML = html;
        }

        function goPage(p) { SCREEN.currentPage = Math.max(1, Math.min(p, SCREEN.totalPages)); renderReview(); }
        function esc(t) { if (!t) return ''; var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
        function createConfetti() {
            var c = document.getElementById('confettiContainer'), colors = ['#FF6B6B','#4ECDC4','#FFE66D','#A78BFA','#60A5FA'];
            for (var i = 0; i < 50; i++) { var p = document.createElement('div'); p.className = 'confetti-piece'; p.style.left = Math.random()*100+'%'; p.style.backgroundColor = colors[Math.floor(Math.random()*colors.length)]; p.style.animationDelay = Math.random()*2+'s'; c.appendChild(p); }
            setTimeout(function() { c.innerHTML = ''; }, 5000);
        }
    </script>
</body>
</html>
