<?php
/**
 * ==============================================
 * TRANG CHỦ - CHƯA ĐĂNG NHẬP
 * Web App Học tập & Thi trực tuyến Tiểu học
 * Trường Bùi Thị Xuân
 * Style: Fullscreen Desktop App
 * ==============================================
 */

require_once 'includes/config.php';
require_once 'includes/device.php';

// Redirect mobile users sang trang mobile
if (isMobile() || isTablet()) {
    header('Location: ' . BASE_URL . '/mobile/index.php');
    exit;
}

// Nếu đã đăng nhập thì chuyển hướng
if (isStudentLoggedIn()) {
    redirect('student/dashboard.php');
}

if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
}

// Lấy danh sách lớp
$conn = getDBConnection();
$stmtLop = $conn->query("SELECT * FROM lop_hoc ORDER BY thu_tu ASC");
$danhSachLop = $stmtLop->fetchAll();

// Lấy danh sách môn học
$stmtMon = $conn->query("SELECT * FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu ASC");
$danhSachMon = $stmtMon->fetchAll();

// Lấy tài liệu công khai (lấy tất cả để phân trang JS)
$stmtTL = $conn->prepare("
    SELECT tl.*, mh.ten_mon, mh.icon, mh.mau_sac, lh.ten_lop
    FROM tai_lieu tl
    JOIN mon_hoc mh ON tl.mon_hoc_id = mh.id
    LEFT JOIN lop_hoc lh ON tl.lop_id = lh.id
    WHERE tl.is_public = 1 AND tl.trang_thai = 1
    ORDER BY tl.created_at DESC
");
$stmtTL->execute();
$taiLieuList = $stmtTL->fetchAll();

// Lấy top học sinh theo điểm xếp hạng
$stmtXH = $conn->prepare("
    SELECT hs.ho_ten, hs.avatar, lh.ten_lop, lh.khoi, dtl.diem_xep_hang
    FROM hoc_sinh hs
    JOIN lop_hoc lh ON hs.lop_id = lh.id
    LEFT JOIN diem_tich_luy dtl ON hs.id = dtl.hoc_sinh_id
    WHERE lh.trang_thai = 1 AND hs.trang_thai = 1
    ORDER BY dtl.diem_xep_hang DESC
    LIMIT 50
");
$stmtXH->execute();
$topHocSinh = $stmtXH->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang chủ - <?php echo SITE_NAME; ?></title>
    <?php
    require_once 'includes/seo.php';
    echo getSeoMetaTags('Trang chủ');
    echo getSeoJsonLd();
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .app-container {
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 280px;
            min-width: 280px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            display: flex;
            flex-direction: column;
            color: white;
            position: relative;
        }

        .sidebar-header {
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .school-logo {
            font-size: 50px;
            margin-bottom: 10px;
            animation: bounce 2s ease-in-out infinite;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .school-name {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            line-height: 1.3;
        }

        .school-desc {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            margin-top: 5px;
        }

        /* Menu */
        .sidebar-menu {
            flex: 1;
            padding: 20px 15px;
            overflow-y: auto;
        }

        .menu-section-title {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            padding-left: 10px;
        }

        .menu-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 8px 18px;
            margin-bottom: 8px;
            border: none;
            border-radius: 12px;
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.8);
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }

        .menu-btn.active {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        .menu-btn-icon {
            font-size: 22px;
            width: 30px;
            text-align: center;
        }

        .menu-btn-count {
            margin-left: auto;
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        .menu-btn.active .menu-btn-count {
            background: rgba(255,255,255,0.3);
        }

        /* Login button */
        .sidebar-footer {
            padding: 12px 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .login-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 12px 16px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .login-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.5);
        }

        .author-credit {
            padding: 12px 15px;
            text-align: center;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.5);
            line-height: 1.5;
        }

        .author-credit strong {
            color: rgba(255,255,255,0.7);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f0f4f8;
            overflow: hidden;
        }

        /* Header */
        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            min-height: 80px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-title-icon {
            font-size: 32px;
        }

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            background: white;
            color: #666;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-tab:hover {
            border-color: #FF6B6B;
            color: #FF6B6B;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-color: transparent;
            color: white;
        }

        /* Content Body */
        .content-body {
            flex: 1;
            padding: 25px 30px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Documents Grid */
        .docs-grid {
            flex: 1;
            display: grid;
            gap: 20px;
            overflow: hidden;
        }

        .doc-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .doc-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #FF6B6B;
        }

        .doc-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .doc-title {
            font-size: 15px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .doc-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .doc-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Rankings Grid */
        .rankings-grid {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow: hidden;
        }

        .podium-section {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 20px;
            padding: 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            min-height: 200px;
        }

        .podium-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: white;
        }

        .podium-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 8px;
            border: 3px solid;
        }

        .podium-item.rank-1 .podium-avatar {
            width: 80px;
            height: 80px;
            font-size: 32px;
            border-color: #FFD700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
        }

        .podium-item.rank-2 .podium-avatar {
            border-color: #C0C0C0;
        }

        .podium-item.rank-3 .podium-avatar {
            border-color: #CD7F32;
        }

        .podium-medal {
            font-size: 32px;
            margin-bottom: 5px;
        }

        .podium-item.rank-1 .podium-medal {
            font-size: 42px;
        }

        .podium-name {
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            max-width: 100px;
        }

        .podium-class {
            font-size: 11px;
            opacity: 0.8;
        }

        .podium-score {
            font-size: 16px;
            font-weight: 700;
            margin-top: 5px;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 15px;
        }

        .podium-stand {
            width: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            padding-bottom: 10px;
            border-radius: 8px 8px 0 0;
        }

        .podium-item.rank-1 .podium-stand {
            height: 100px;
            background: linear-gradient(180deg, #FFD700 0%, #FFA500 100%);
        }

        .podium-item.rank-2 .podium-stand {
            height: 70px;
            background: linear-gradient(180deg, #C0C0C0 0%, #A0A0A0 100%);
        }

        .podium-item.rank-3 .podium-stand {
            height: 50px;
            background: linear-gradient(180deg, #CD7F32 0%, #8B4513 100%);
        }

        .rank-number {
            font-size: 24px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        /* Rank list */
        .rank-list {
            flex: 1;
            display: grid;
            gap: 12px;
            overflow: hidden;
        }

        .rank-item {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 15px 20px;
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .rank-position {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
        }

        .rank-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
        }

        .rank-info {
            flex: 1;
        }

        .rank-name {
            font-size: 15px;
            font-weight: 700;
            color: #333;
        }

        .rank-class {
            font-size: 12px;
            color: #888;
        }

        .rank-score {
            font-size: 18px;
            font-weight: 700;
            color: #FF6B6B;
        }

        /* Pagination Bar */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            padding: 20px;
            background: white;
            border-radius: 16px;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .pagination-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .pagination-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .pagination-info {
            font-size: 15px;
            font-weight: 600;
            color: #666;
        }

        .pagination-info span {
            color: #FF6B6B;
            font-weight: 700;
        }

        /* Empty state */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #888;
        }

        .empty-icon {
            font-size: 60px;
            margin-bottom: 15px;
        }

        .empty-text {
            font-size: 18px;
            font-weight: 600;
        }

        /* Class filter buttons in sidebar */
        .class-filter-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 10px;
        }

        .class-filter-btn {
            padding: 10px;
            border: 2px solid rgba(255,255,255,0.2);
            border-radius: 10px;
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.7);
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .class-filter-btn:hover {
            background: rgba(255,255,255,0.15);
        }

        .class-filter-btn.active {
            background: rgba(255,255,255,0.2);
            border-color: #4CAF50;
            color: #4CAF50;
        }

        .class-filter-btn.locked {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========== DOCUMENT VIEWER MODAL ========== */
        .doc-viewer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 10000;
            display: none;
            flex-direction: column;
        }

        .doc-viewer-overlay.show {
            display: flex;
        }

        .doc-viewer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
            background: #1a1a2e;
            color: white;
            flex-shrink: 0;
        }

        .doc-viewer-title {
            font-size: 1.1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .doc-viewer-actions {
            display: flex;
            gap: 10px;
        }

        .doc-viewer-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .doc-viewer-btn.login-prompt-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            text-decoration: none;
        }

        .doc-viewer-btn.login-prompt-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .doc-viewer-btn.close-btn {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .doc-viewer-btn.close-btn:hover {
            background: rgba(239, 68, 68, 0.8);
        }

        .doc-viewer-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 20px;
        }

        .doc-viewer-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 12px;
            background: white;
        }

        .doc-viewer-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            color: white;
        }

        .doc-viewer-loading .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .doc-viewer-error {
            text-align: center;
            color: white;
            padding: 40px;
        }

        .doc-viewer-error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }

        .doc-viewer-error-text {
            font-size: 1.2rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Document Viewer Modal -->
    <div class="doc-viewer-overlay" id="docViewerOverlay">
        <div class="doc-viewer-header">
            <div class="doc-viewer-title">
                <span id="docViewerIcon">📄</span>
                <span id="docViewerTitle">Tài liệu</span>
            </div>
            <div class="doc-viewer-actions">
                <a href="<?php echo BASE_URL; ?>/login.php" class="doc-viewer-btn login-prompt-btn">
                    <span>🔑</span> Đăng nhập để tải xuống
                </a>
                <button class="doc-viewer-btn close-btn" onclick="closeDocViewer()">
                    <span>✕</span> Đóng
                </button>
            </div>
        </div>
        <div class="doc-viewer-body" id="docViewerBody">
            <div class="doc-viewer-loading" id="docViewerLoading">
                <div class="loading-spinner"></div>
                <div>Đang tải tài liệu...</div>
            </div>
            <iframe class="doc-viewer-iframe" id="docViewerIframe" style="display: none;"></iframe>
        </div>
    </div>

    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="school-logo">📚</div>
                <div class="school-name"><?php echo SITE_NAME; ?></div>
                <div class="school-desc"><?php echo SITE_DESCRIPTION; ?></div>
            </div>

            <nav class="sidebar-menu">
                <div class="menu-section-title">Khám phá</div>

                <button class="menu-btn active" onclick="navigateTo('documents')">
                    <span class="menu-btn-icon">📖</span>
                    <span>Kho tài liệu</span>
                    <span class="menu-btn-count"><?php echo count($taiLieuList); ?></span>
                </button>

                <button class="menu-btn" onclick="navigateTo('rankings')">
                    <span class="menu-btn-icon">🏆</span>
                    <span>Bảng xếp hạng</span>
                </button>

                <div class="menu-section-title" style="margin-top: 25px;">Lọc theo lớp</div>

                <div class="class-filter-group">
                    <button class="class-filter-btn active" onclick="filterClass('all', this)">Tất cả</button>
                    <?php foreach ($danhSachLop as $lop): ?>
                        <?php if ($lop['trang_thai'] == 1): ?>
                            <button class="class-filter-btn" onclick="filterClass(<?php echo $lop['id']; ?>, this)">
                                <?php echo htmlspecialchars($lop['ten_lop']); ?>
                            </button>
                        <?php else: ?>
                            <button class="class-filter-btn locked" disabled>
                                <?php echo htmlspecialchars($lop['ten_lop']); ?> 🔒
                            </button>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </nav>

            <div class="sidebar-footer">
                <a href="<?php echo BASE_URL; ?>/login.php" class="login-btn">
                    <span>🔑</span>
                    <span>Đăng nhập</span>
                </a>
            </div>

            <div class="author-credit">
                <strong>Tác giả:</strong><br>
                GV Hồ Thị Thanh Hằng<br>
                GV Đinh Thị Chi
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="content-header">
                <h1 class="page-title">
                    <span class="page-title-icon" id="page-icon">📖</span>
                    <span id="page-title-text">Kho tài liệu học tập</span>
                </h1>

                <div class="filter-tabs" id="filter-tabs">
                    <button class="filter-tab active" onclick="filterSubject('all', this)">Tất cả</button>
                    <?php foreach ($danhSachMon as $mon): ?>
                        <button class="filter-tab" onclick="filterSubject(<?php echo $mon['id']; ?>, this)">
                            <?php echo htmlspecialchars($mon['ten_mon']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </header>

            <div class="content-body">
                <div id="content-area"></div>
            </div>
        </main>
    </div>

    <script>
    // Data from PHP
    var DATA = {
        documents: <?php echo json_encode($taiLieuList); ?>,
        rankings: <?php echo json_encode($topHocSinh); ?>,
        subjects: <?php echo json_encode($danhSachMon); ?>,
        classes: <?php echo json_encode($danhSachLop); ?>
    };

    var SCREEN = {
        width: 0,
        height: 0,
        contentHeight: 0,
        contentWidth: 0,
        columns: 3,
        rows: 2,
        itemsPerPage: 6
    };

    var STATE = {
        currentPage: 'documents',
        documents: { page: 1, total: 1, filterSubject: 'all', filterClass: 'all' },
        rankings: { page: 1, total: 1, filterClass: 'all' }
    };

    // Calculate screen dimensions
    function calculateScreen() {
        SCREEN.width = window.innerWidth;
        SCREEN.height = window.innerHeight;
        SCREEN.contentWidth = SCREEN.width - 280 - 60; // sidebar + padding
        SCREEN.contentHeight = SCREEN.height - 80 - 50 - 100; // header + padding + pagination

        if (STATE.currentPage === 'documents') {
            var cardWidth = 200;
            var cardHeight = 160;
            var gap = 20;

            SCREEN.columns = Math.max(2, Math.floor((SCREEN.contentWidth + gap) / (cardWidth + gap)));
            SCREEN.rows = Math.max(1, Math.floor((SCREEN.contentHeight + gap) / (cardHeight + gap)));
            SCREEN.itemsPerPage = SCREEN.columns * SCREEN.rows;
        } else {
            // Rankings - items in a list
            var itemHeight = 70;
            var podiumHeight = 220;
            var availableHeight = SCREEN.contentHeight - podiumHeight - 40;
            SCREEN.itemsPerPage = Math.max(2, Math.floor(availableHeight / itemHeight));
        }
    }

    // Navigate to page
    function navigateTo(page) {
        STATE.currentPage = page;

        // Update menu buttons
        var menuBtns = document.querySelectorAll('.menu-btn');
        for (var i = 0; i < menuBtns.length; i++) {
            menuBtns[i].className = 'menu-btn';
        }

        if (page === 'documents') {
            menuBtns[0].className = 'menu-btn active';
            document.getElementById('page-icon').textContent = '📖';
            document.getElementById('page-title-text').textContent = 'Kho tài liệu học tập';
            document.getElementById('filter-tabs').style.display = 'flex';
        } else {
            menuBtns[1].className = 'menu-btn active';
            document.getElementById('page-icon').textContent = '🏆';
            document.getElementById('page-title-text').textContent = 'Bảng xếp hạng';
            document.getElementById('filter-tabs').style.display = 'none';
        }

        calculateScreen();
        render();
    }

    // Filter by subject
    function filterSubject(subjectId, btn) {
        STATE.documents.filterSubject = subjectId;
        STATE.documents.page = 1;

        var tabs = document.querySelectorAll('.filter-tab');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].className = 'filter-tab';
        }
        if (btn) btn.className = 'filter-tab active';

        render();
    }

    // Filter by class
    function filterClass(classId, btn) {
        STATE.documents.filterClass = classId;
        STATE.rankings.filterClass = classId;
        STATE.documents.page = 1;
        STATE.rankings.page = 1;

        var btns = document.querySelectorAll('.class-filter-btn');
        for (var i = 0; i < btns.length; i++) {
            if (!btns[i].disabled) {
                btns[i].className = 'class-filter-btn';
            }
        }
        if (btn) btn.className = 'class-filter-btn active';

        render();
    }

    // Get filtered data
    function getFilteredDocuments() {
        var docs = DATA.documents;
        var filtered = [];

        for (var i = 0; i < docs.length; i++) {
            var doc = docs[i];
            var matchSubject = STATE.documents.filterSubject === 'all' || doc.mon_hoc_id == STATE.documents.filterSubject;
            var matchClass = STATE.documents.filterClass === 'all' || doc.lop_id == STATE.documents.filterClass || doc.lop_id === null;

            if (matchSubject && matchClass) {
                filtered.push(doc);
            }
        }

        return filtered;
    }

    function getFilteredRankings() {
        var rankings = DATA.rankings;

        if (STATE.rankings.filterClass === 'all') {
            return rankings;
        }

        var filtered = [];
        for (var i = 0; i < rankings.length; i++) {
            // Filter by khoi (grade level)
            var classInfo = null;
            for (var j = 0; j < DATA.classes.length; j++) {
                if (DATA.classes[j].id == STATE.rankings.filterClass) {
                    classInfo = DATA.classes[j];
                    break;
                }
            }

            if (classInfo && rankings[i].khoi == classInfo.khoi) {
                filtered.push(rankings[i]);
            }
        }

        return filtered;
    }

    // Render content
    function render() {
        calculateScreen();

        if (STATE.currentPage === 'documents') {
            renderDocuments();
        } else {
            renderRankings();
        }
    }

    function renderDocuments() {
        var docs = getFilteredDocuments();
        var total = docs.length;
        var totalPages = Math.max(1, Math.ceil(total / SCREEN.itemsPerPage));

        STATE.documents.total = totalPages;
        if (STATE.documents.page > totalPages) STATE.documents.page = totalPages;

        var start = (STATE.documents.page - 1) * SCREEN.itemsPerPage;
        var end = Math.min(start + SCREEN.itemsPerPage, total);
        var pageDocs = docs.slice(start, end);

        var html = '<div class="docs-grid" style="grid-template-columns: repeat(' + SCREEN.columns + ', 1fr); grid-template-rows: repeat(' + SCREEN.rows + ', 1fr);">';

        if (pageDocs.length === 0) {
            html += '<div class="empty-state" style="grid-column: 1 / -1; grid-row: 1 / -1;">';
            html += '<div class="empty-icon">📚</div>';
            html += '<div class="empty-text">Chưa có tài liệu nào</div>';
            html += '</div>';
        } else {
            var icons = { pdf: '📄', word: '📝', ppt: '📊', video: '🎬', image: '🖼️' };

            for (var i = 0; i < pageDocs.length; i++) {
                var doc = pageDocs[i];
                var icon = icons[doc.loai_file] || '📁';

                html += '<div class="doc-card" onclick="viewDocument(' + doc.id + ')">';
                html += '<div class="doc-icon">' + icon + '</div>';
                html += '<div class="doc-title">' + escapeHtml(doc.tieu_de) + '</div>';
                html += '<div class="doc-meta">';
                html += '<span class="doc-badge" style="background: ' + doc.mau_sac + '20; color: ' + doc.mau_sac + '">' + escapeHtml(doc.ten_mon) + '</span>';
                html += '<span class="doc-badge" style="background: #66666620; color: #666">' + (doc.ten_lop || 'Chung') + '</span>';
                html += '</div>';
                html += '</div>';
            }
        }

        html += '</div>';

        // Pagination
        html += renderPagination('documents', STATE.documents.page, totalPages, total);

        document.getElementById('content-area').innerHTML = html;
    }

    function renderRankings() {
        var rankings = getFilteredRankings();
        var total = rankings.length;

        var html = '<div class="rankings-grid">';

        // Podium for top 3
        if (rankings.length >= 3) {
            html += '<div class="podium-section">';

            // Rank 2
            var r2 = rankings[1];
            var initial2 = r2.ho_ten.charAt(0).toUpperCase();
            html += '<div class="podium-item rank-2">';
            html += '<div class="podium-medal">🥈</div>';
            html += '<div class="podium-avatar">' + initial2 + '</div>';
            html += '<div class="podium-name">' + escapeHtml(r2.ho_ten) + '</div>';
            html += '<div class="podium-class">' + escapeHtml(r2.ten_lop) + '</div>';
            html += '<div class="podium-stand"><span class="rank-number">2</span></div>';
            html += '</div>';

            // Rank 1
            var r1 = rankings[0];
            var initial1 = r1.ho_ten.charAt(0).toUpperCase();
            html += '<div class="podium-item rank-1">';
            html += '<div class="podium-medal">🥇</div>';
            html += '<div class="podium-avatar">' + initial1 + '</div>';
            html += '<div class="podium-name">' + escapeHtml(r1.ho_ten) + '</div>';
            html += '<div class="podium-class">' + escapeHtml(r1.ten_lop) + '</div>';
            html += '<div class="podium-score">' + Math.round(r1.diem_xep_hang || 0) + ' điểm</div>';
            html += '<div class="podium-stand"><span class="rank-number">1</span></div>';
            html += '</div>';

            // Rank 3
            var r3 = rankings[2];
            var initial3 = r3.ho_ten.charAt(0).toUpperCase();
            html += '<div class="podium-item rank-3">';
            html += '<div class="podium-medal">🥉</div>';
            html += '<div class="podium-avatar">' + initial3 + '</div>';
            html += '<div class="podium-name">' + escapeHtml(r3.ho_ten) + '</div>';
            html += '<div class="podium-class">' + escapeHtml(r3.ten_lop) + '</div>';
            html += '<div class="podium-stand"><span class="rank-number">3</span></div>';
            html += '</div>';

            html += '</div>';
        }

        // List from rank 4
        var listRankings = rankings.slice(3);
        var listTotal = listRankings.length;
        var totalPages = Math.max(1, Math.ceil(listTotal / SCREEN.itemsPerPage));

        STATE.rankings.total = totalPages;
        if (STATE.rankings.page > totalPages) STATE.rankings.page = totalPages;

        var start = (STATE.rankings.page - 1) * SCREEN.itemsPerPage;
        var end = Math.min(start + SCREEN.itemsPerPage, listTotal);
        var pageRankings = listRankings.slice(start, end);

        if (pageRankings.length > 0) {
            html += '<div class="rank-list" style="grid-template-rows: repeat(' + SCREEN.itemsPerPage + ', 1fr);">';

            for (var i = 0; i < pageRankings.length; i++) {
                var r = pageRankings[i];
                var rank = start + i + 4; // +4 because we skipped top 3
                var initial = r.ho_ten.charAt(0).toUpperCase();

                html += '<div class="rank-item">';
                html += '<div class="rank-position">' + rank + '</div>';
                html += '<div class="rank-avatar">' + initial + '</div>';
                html += '<div class="rank-info">';
                html += '<div class="rank-name">' + escapeHtml(r.ho_ten) + '</div>';
                html += '<div class="rank-class">' + escapeHtml(r.ten_lop) + '</div>';
                html += '</div>';
                html += '<div class="rank-score">' + Math.round(r.diem_xep_hang || 0) + '</div>';
                html += '</div>';
            }

            html += '</div>';
        } else if (rankings.length < 3) {
            html += '<div class="empty-state">';
            html += '<div class="empty-icon">🏆</div>';
            html += '<div class="empty-text">Chưa có đủ dữ liệu xếp hạng</div>';
            html += '</div>';
        }

        html += '</div>';

        // Pagination (only if there are items beyond top 3)
        if (listTotal > 0) {
            html += renderPagination('rankings', STATE.rankings.page, totalPages, listTotal);
        }

        document.getElementById('content-area').innerHTML = html;
    }

    function renderPagination(type, current, total, totalItems) {
        var html = '<div class="pagination-bar">';

        html += '<button class="pagination-btn" onclick="goPage(\'' + type + '\', -1)" ' + (current <= 1 ? 'disabled' : '') + '>';
        html += '◀ Trước';
        html += '</button>';

        html += '<div class="pagination-info">';
        html += 'Trang <span>' + current + '</span> / <span>' + total + '</span>';
        html += ' (' + totalItems + ' mục)';
        html += '</div>';

        html += '<button class="pagination-btn" onclick="goPage(\'' + type + '\', 1)" ' + (current >= total ? 'disabled' : '') + '>';
        html += 'Sau ▶';
        html += '</button>';

        html += '</div>';
        return html;
    }

    function goPage(type, delta) {
        STATE[type].page += delta;
        render();
    }

    // ========== FILE ICONS ==========
    var FILE_ICONS = { pdf: '📄', word: '📝', ppt: '📊', video: '🎬', image: '🖼️' };
    var BASE_URL = '<?php echo BASE_URL; ?>';

    // ========== DOCUMENT VIEWER ==========
    var CURRENT_DOC = null;

    function viewDocument(docId) {
        // Tìm document
        var doc = null;
        for (var i = 0; i < DATA.documents.length; i++) {
            if (DATA.documents[i].id == docId) {
                doc = DATA.documents[i];
                break;
            }
        }

        if (!doc) return;

        CURRENT_DOC = doc;
        var gdriveId = doc.google_drive_id || '';
        var localFile = doc.local_file || '';
        var fileType = doc.loai_file || '';

        if (!localFile && !gdriveId) {
            alert('Tài liệu chưa có file đính kèm');
            return;
        }

        // Hiển thị modal
        var overlay = document.getElementById('docViewerOverlay');
        var iframe = document.getElementById('docViewerIframe');
        var loading = document.getElementById('docViewerLoading');

        overlay.classList.add('show');
        iframe.style.display = 'none';
        loading.style.display = 'block';

        // Set title và icon
        var icon = FILE_ICONS[fileType] || '📄';
        document.getElementById('docViewerIcon').textContent = icon;
        document.getElementById('docViewerTitle').textContent = doc.tieu_de;

        // Xác định URL để xem
        var viewUrl = '';

        if (localFile) {
            // File local
            var fileUrl = BASE_URL + '/uploads/documents/' + localFile;

            if (fileType === 'pdf') {
                viewUrl = fileUrl;
            } else if (fileType === 'word' || fileType === 'ppt') {
                viewUrl = 'https://docs.google.com/gview?url=' + encodeURIComponent(fileUrl) + '&embedded=true';
            } else if (fileType === 'image') {
                showImageViewer(fileUrl);
                return;
            } else if (fileType === 'video') {
                showVideoViewer(fileUrl);
                return;
            } else {
                viewUrl = fileUrl;
            }
        } else if (gdriveId) {
            // File từ Google Drive
            if (fileType === 'image') {
                var imgUrl = 'https://drive.google.com/uc?export=view&id=' + gdriveId;
                showImageViewer(imgUrl);
                return;
            } else {
                viewUrl = 'https://drive.google.com/file/d/' + gdriveId + '/preview';
            }
        }

        // Load iframe
        iframe.onload = function() {
            loading.style.display = 'none';
            iframe.style.display = 'block';
        };

        iframe.onerror = function() {
            showViewerError();
        };

        iframe.src = viewUrl;

        // Timeout fallback
        setTimeout(function() {
            if (loading.style.display !== 'none') {
                loading.style.display = 'none';
                iframe.style.display = 'block';
            }
        }, 5000);
    }

    function showImageViewer(imageUrl) {
        var body = document.getElementById('docViewerBody');
        document.getElementById('docViewerLoading').style.display = 'none';
        document.getElementById('docViewerIframe').style.display = 'none';

        body.innerHTML = '<img src="' + imageUrl + '" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 12px;" onerror="showViewerError()">';
    }

    function showVideoViewer(videoUrl) {
        var body = document.getElementById('docViewerBody');
        document.getElementById('docViewerLoading').style.display = 'none';
        document.getElementById('docViewerIframe').style.display = 'none';

        body.innerHTML = '<video controls autoplay style="max-width: 100%; max-height: 100%; border-radius: 12px;"><source src="' + videoUrl + '">Trình duyệt không hỗ trợ video.</video>';
    }

    function showViewerError() {
        var body = document.getElementById('docViewerBody');
        body.innerHTML = '<div class="doc-viewer-error">' +
            '<div class="doc-viewer-error-icon">😕</div>' +
            '<div class="doc-viewer-error-text">Không thể xem trực tiếp tài liệu này.<br>Vui lòng đăng nhập để tải xuống.</div>' +
        '</div>';
    }

    function closeDocViewer() {
        var overlay = document.getElementById('docViewerOverlay');
        var iframe = document.getElementById('docViewerIframe');

        overlay.classList.remove('show');
        iframe.src = '';

        // Reset body content
        document.getElementById('docViewerBody').innerHTML =
            '<div class="doc-viewer-loading" id="docViewerLoading">' +
                '<div class="loading-spinner"></div>' +
                '<div>Đang tải tài liệu...</div>' +
            '</div>' +
            '<iframe class="doc-viewer-iframe" id="docViewerIframe" style="display: none;"></iframe>';
    }

    // Đóng modal khi nhấn ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDocViewer();
        }
    });

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Init
    window.onload = function() {
        calculateScreen();
        render();
    };

    window.onresize = function() {
        calculateScreen();
        render();
    };
    </script>
</body>
</html>
