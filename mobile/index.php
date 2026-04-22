<?php
/**
 * ==============================================
 * MOBILE LANDING PAGE - TRANG CHỦ CHƯA ĐĂNG NHẬP
 * Giao diện tối ưu cho điện thoại
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/device.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isStudentLoggedIn()) {
    redirect('student/mobile/index.php');
}

if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

// Lấy dữ liệu
$conn = getDBConnection();

// Lấy danh sách môn học
$stmtMon = $conn->query("SELECT * FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu ASC");
$danhSachMon = $stmtMon->fetchAll();

// Lấy tài liệu công khai (giới hạn 20)
$stmtTL = $conn->prepare("
    SELECT tl.*, mh.ten_mon, mh.icon, mh.mau_sac
    FROM tai_lieu tl
    JOIN mon_hoc mh ON tl.mon_hoc_id = mh.id
    WHERE tl.is_public = 1 AND tl.trang_thai = 1
    ORDER BY tl.created_at DESC
    LIMIT 20
");
$stmtTL->execute();
$taiLieuList = $stmtTL->fetchAll();

// Lấy top 10 học sinh
$stmtXH = $conn->prepare("
    SELECT hs.ho_ten, hs.gioi_tinh, lh.ten_lop, dtl.diem_xep_hang
    FROM hoc_sinh hs
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    LEFT JOIN diem_tich_luy dtl ON hs.id = dtl.hoc_sinh_id
    WHERE lh.trang_thai = 1 AND hs.trang_thai = 1
    ORDER BY dtl.diem_xep_hang DESC
    LIMIT 10
");
$stmtXH->execute();
$topHocSinh = $stmtXH->fetchAll();

// File icons
$fileIcons = array('pdf' => '📄', 'word' => '📝', 'ppt' => '📊', 'video' => '🎬', 'image' => '🖼️');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#667eea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo SITE_NAME; ?></title>
    <?php
    require_once '../includes/seo.php';
    echo getSeoMetaTags('Trang chủ');
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10B981;
            --warning: #F59E0B;
            --danger: #EF4444;
            --text: #1F2937;
            --text-light: #6B7280;
            --bg: #F3F4F6;
            --white: #FFFFFF;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }

        html, body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 60px 20px 40px;
            padding-top: calc(60px + var(--safe-top));
            text-align: center;
            color: white;
            position: relative;
        }

        .hero-logo {
            font-size: 4rem;
            margin-bottom: 12px;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .hero-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .hero-desc {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .login-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 14px 32px;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }

        .login-btn:active {
            transform: scale(0.96);
        }

        /* Tab Navigation */
        .tab-nav {
            display: flex;
            background: white;
            padding: 8px;
            margin: -20px 16px 16px;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            z-index: 10;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        /* Content */
        .content {
            padding: 0 16px 100px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Section Title */
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Subject Filter */
        .subject-filter {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 12px;
            margin-bottom: 12px;
            -webkit-overflow-scrolling: touch;
        }

        .subject-filter::-webkit-scrollbar {
            display: none;
        }

        .filter-chip {
            flex-shrink: 0;
            padding: 8px 16px;
            border: 2px solid var(--bg);
            border-radius: 20px;
            background: white;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-chip.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-color: transparent;
            color: white;
        }

        /* Document Card */
        .doc-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .doc-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .doc-card:active {
            transform: scale(0.98);
        }

        .doc-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .doc-info {
            flex: 1;
            min-width: 0;
        }

        .doc-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .doc-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .doc-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 8px;
        }

        /* Rankings */
        .podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 12px;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 20px;
            margin-bottom: 16px;
        }

        .podium-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
        }

        .podium-medal {
            font-size: 1.8rem;
            margin-bottom: 4px;
        }

        .podium-item.rank-1 .podium-medal {
            font-size: 2.2rem;
        }

        .podium-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            border: 3px solid;
            margin-bottom: 6px;
        }

        .podium-item.rank-1 .podium-avatar {
            width: 55px;
            height: 55px;
            font-size: 1.5rem;
            border-color: #FFD700;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.5);
        }

        .podium-item.rank-2 .podium-avatar {
            border-color: #C0C0C0;
        }

        .podium-item.rank-3 .podium-avatar {
            border-color: #CD7F32;
        }

        .podium-name {
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
            max-width: 80px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .podium-class {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        .podium-stand {
            width: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 0;
            border-radius: 8px 8px 0 0;
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .podium-item.rank-1 .podium-stand {
            height: 60px;
            background: linear-gradient(180deg, #FFD700 0%, #FFA500 100%);
        }

        .podium-item.rank-2 .podium-stand {
            height: 45px;
            background: linear-gradient(180deg, #C0C0C0 0%, #A0A0A0 100%);
        }

        .podium-item.rank-3 .podium-stand {
            height: 35px;
            background: linear-gradient(180deg, #CD7F32 0%, #8B4513 100%);
        }

        /* Rank List */
        .rank-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rank-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: white;
            padding: 12px 16px;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .rank-pos {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .rank-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .rank-info {
            flex: 1;
            min-width: 0;
        }

        .rank-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
        }

        .rank-class {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .rank-score {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 20px;
            padding-bottom: calc(12px + var(--safe-bottom));
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 100;
        }

        .footer-text {
            font-size: 0.75rem;
            color: var(--text-light);
            line-height: 1.4;
        }

        .footer-login {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Document Viewer Modal */
        .doc-viewer {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            display: none;
            flex-direction: column;
        }

        .doc-viewer.show {
            display: flex;
        }

        .doc-viewer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            padding-top: calc(12px + var(--safe-top));
            background: #1a1a2e;
            color: white;
        }

        .doc-viewer-title {
            font-size: 0.95rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .doc-viewer-close {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 10px;
            background: rgba(255,255,255,0.1);
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            flex-shrink: 0;
        }

        .doc-viewer-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            overflow: hidden;
        }

        .doc-viewer-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 12px;
            background: white;
        }

        .doc-viewer-loading {
            text-align: center;
            color: white;
        }

        .doc-viewer-loading .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .doc-viewer-prompt {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            padding: 40px 20px 30px;
            padding-bottom: calc(30px + var(--safe-bottom));
            text-align: center;
        }

        .doc-viewer-prompt a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero">
        <div class="hero-logo">📚</div>
        <h1 class="hero-title"><?php echo SITE_NAME; ?></h1>
        <p class="hero-desc"><?php echo SITE_DESCRIPTION; ?></p>
        <a href="<?php echo BASE_URL; ?>/login.php" class="login-btn">
            🔑 Đăng nhập
        </a>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="showTab('docs')">📖 Tài liệu</button>
        <button class="tab-btn" onclick="showTab('rank')">🏆 Xếp hạng</button>
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Documents Tab -->
        <div id="tab-docs" class="tab-content active">
            <div class="subject-filter">
                <button class="filter-chip active" onclick="filterDocs('all', this)">Tất cả</button>
                <?php foreach ($danhSachMon as $mon): ?>
                <button class="filter-chip" onclick="filterDocs(<?php echo $mon['id']; ?>, this)">
                    <?php echo htmlspecialchars($mon['ten_mon']); ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="doc-list" id="docList">
                <?php if (count($taiLieuList) > 0): ?>
                    <?php foreach ($taiLieuList as $tl): ?>
                    <div class="doc-card" data-mon="<?php echo $tl['mon_hoc_id']; ?>" onclick="viewDoc(<?php echo $tl['id']; ?>)">
                        <div class="doc-icon"><?php echo isset($fileIcons[$tl['loai_file']]) ? $fileIcons[$tl['loai_file']] : '📁'; ?></div>
                        <div class="doc-info">
                            <div class="doc-title"><?php echo htmlspecialchars($tl['tieu_de']); ?></div>
                            <div class="doc-meta">
                                <span class="doc-badge" style="background: <?php echo $tl['mau_sac']; ?>20; color: <?php echo $tl['mau_sac']; ?>">
                                    <?php echo htmlspecialchars($tl['ten_mon']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <div>Chưa có tài liệu nào</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rankings Tab -->
        <div id="tab-rank" class="tab-content">
            <?php if (count($topHocSinh) >= 3): ?>
            <!-- Podium -->
            <div class="podium">
                <!-- Rank 2 -->
                <div class="podium-item rank-2">
                    <div class="podium-medal">🥈</div>
                    <div class="podium-avatar"><?php echo $topHocSinh[1]['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                    <div class="podium-name"><?php echo htmlspecialchars($topHocSinh[1]['ho_ten']); ?></div>
                    <div class="podium-class"><?php echo htmlspecialchars($topHocSinh[1]['ten_lop']); ?></div>
                    <div class="podium-stand">2</div>
                </div>

                <!-- Rank 1 -->
                <div class="podium-item rank-1">
                    <div class="podium-medal">🥇</div>
                    <div class="podium-avatar"><?php echo $topHocSinh[0]['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                    <div class="podium-name"><?php echo htmlspecialchars($topHocSinh[0]['ho_ten']); ?></div>
                    <div class="podium-class"><?php echo htmlspecialchars($topHocSinh[0]['ten_lop']); ?></div>
                    <div class="podium-stand">1</div>
                </div>

                <!-- Rank 3 -->
                <div class="podium-item rank-3">
                    <div class="podium-medal">🥉</div>
                    <div class="podium-avatar"><?php echo $topHocSinh[2]['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                    <div class="podium-name"><?php echo htmlspecialchars($topHocSinh[2]['ho_ten']); ?></div>
                    <div class="podium-class"><?php echo htmlspecialchars($topHocSinh[2]['ten_lop']); ?></div>
                    <div class="podium-stand">3</div>
                </div>
            </div>

            <!-- Rank List (4-10) -->
            <?php if (count($topHocSinh) > 3): ?>
            <div class="section-title">📊 Xếp hạng tiếp theo</div>
            <div class="rank-list">
                <?php for ($i = 3; $i < count($topHocSinh); $i++): ?>
                <div class="rank-item">
                    <div class="rank-pos"><?php echo $i + 1; ?></div>
                    <div class="rank-avatar"><?php echo $topHocSinh[$i]['gioi_tinh'] == 1 ? '👦' : '👧'; ?></div>
                    <div class="rank-info">
                        <div class="rank-name"><?php echo htmlspecialchars($topHocSinh[$i]['ho_ten']); ?></div>
                        <div class="rank-class"><?php echo htmlspecialchars($topHocSinh[$i]['ten_lop']); ?></div>
                    </div>
                    <div class="rank-score"><?php echo number_format($topHocSinh[$i]['diem_xep_hang'] ?: 0); ?></div>
                </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🏆</div>
                <div>Chưa có dữ liệu xếp hạng</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-text">
            <strong>Tác giả:</strong><br>
            GV Hồ Thị Thanh Hằng - GV Đinh Thị Chi
        </div>
        <a href="<?php echo BASE_URL; ?>/login.php" class="footer-login">
            🔑 Đăng nhập
        </a>
    </div>

    <!-- Document Viewer Modal -->
    <div class="doc-viewer" id="docViewer">
        <div class="doc-viewer-header">
            <div class="doc-viewer-title">
                <span id="viewerIcon">📄</span>
                <span id="viewerTitle">Tài liệu</span>
            </div>
            <button class="doc-viewer-close" onclick="closeViewer()">✕</button>
        </div>
        <div class="doc-viewer-body">
            <div class="doc-viewer-loading" id="viewerLoading">
                <div class="spinner"></div>
                <div>Đang tải...</div>
            </div>
            <iframe class="doc-viewer-iframe" id="viewerIframe" style="display: none;"></iframe>
        </div>
        <div class="doc-viewer-prompt">
            <a href="<?php echo BASE_URL; ?>/login.php">🔑 Đăng nhập để tải xuống</a>
        </div>
    </div>

    <script>
    var BASE_URL = '<?php echo BASE_URL; ?>';
    var DOCS = <?php echo json_encode($taiLieuList); ?>;
    var FILE_ICONS = {pdf: '📄', word: '📝', ppt: '📊', video: '🎬', image: '🖼️'};

    function showTab(tab) {
        // Update buttons
        var btns = document.querySelectorAll('.tab-btn');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.remove('active');
        }
        event.target.classList.add('active');

        // Update content
        document.getElementById('tab-docs').classList.remove('active');
        document.getElementById('tab-rank').classList.remove('active');
        document.getElementById('tab-' + tab).classList.add('active');
    }

    function filterDocs(monId, btn) {
        // Update chips
        var chips = document.querySelectorAll('.filter-chip');
        for (var i = 0; i < chips.length; i++) {
            chips[i].classList.remove('active');
        }
        btn.classList.add('active');

        // Filter cards
        var cards = document.querySelectorAll('.doc-card');
        for (var i = 0; i < cards.length; i++) {
            if (monId === 'all' || cards[i].dataset.mon == monId) {
                cards[i].style.display = 'flex';
            } else {
                cards[i].style.display = 'none';
            }
        }
    }

    function viewDoc(docId) {
        var doc = null;
        for (var i = 0; i < DOCS.length; i++) {
            if (DOCS[i].id == docId) {
                doc = DOCS[i];
                break;
            }
        }

        if (!doc) return;

        var gdriveId = doc.google_drive_id || '';
        var localFile = doc.local_file || '';
        var fileType = doc.loai_file || '';

        if (!localFile && !gdriveId) {
            alert('Tài liệu chưa có file đính kèm');
            return;
        }

        // Show modal
        document.getElementById('docViewer').classList.add('show');
        document.getElementById('viewerLoading').style.display = 'block';
        document.getElementById('viewerIframe').style.display = 'none';

        // Set title
        document.getElementById('viewerIcon').textContent = FILE_ICONS[fileType] || '📄';
        document.getElementById('viewerTitle').textContent = doc.tieu_de;

        // Get view URL
        var viewUrl = '';

        if (localFile) {
            var fileUrl = BASE_URL + '/uploads/documents/' + localFile;
            if (fileType === 'pdf') {
                viewUrl = fileUrl;
            } else if (fileType === 'word' || fileType === 'ppt') {
                viewUrl = 'https://docs.google.com/gview?url=' + encodeURIComponent(fileUrl) + '&embedded=true';
            } else {
                viewUrl = fileUrl;
            }
        } else if (gdriveId) {
            viewUrl = 'https://drive.google.com/file/d/' + gdriveId + '/preview';
        }

        // Load iframe
        var iframe = document.getElementById('viewerIframe');
        iframe.onload = function() {
            document.getElementById('viewerLoading').style.display = 'none';
            iframe.style.display = 'block';
        };
        iframe.src = viewUrl;

        // Timeout fallback
        setTimeout(function() {
            document.getElementById('viewerLoading').style.display = 'none';
            iframe.style.display = 'block';
        }, 5000);
    }

    function closeViewer() {
        document.getElementById('docViewer').classList.remove('show');
        document.getElementById('viewerIframe').src = '';
    }

    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeViewer();
    });
    </script>
</body>
</html>
