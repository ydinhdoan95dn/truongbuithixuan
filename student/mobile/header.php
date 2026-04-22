<?php
/**
 * ==============================================
 * MOBILE HEADER COMPONENT
 * ==============================================
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#667eea">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>

    <?php
    // SEO Meta Tags - Sử dụng cách tương thích PHP 5.x
    $seoPath = realpath(__DIR__ . '/../../includes/seo.php');
    if ($seoPath && file_exists($seoPath)) {
        require_once $seoPath;
        echo getSeoMetaTags(isset($pageTitle) ? $pageTitle : '');
    }
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            background: #F3F4F6;
            color: #1F2937;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ========== VARIABLES ========== */
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd6;
            --secondary: #764ba2;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
            --text: #1F2937;
            --text-light: #6B7280;
            --bg: #F3F4F6;
            --card: #FFFFFF;
            --border: #E5E7EB;
            --radius: 16px;
            --radius-sm: 12px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
            --header-height: 60px;
            --tab-bar-height: 60px;
        }

        /* ========== APP CONTAINER ========== */
        .app {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ========== TOP HEADER ========== */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 14px 16px;
            padding-top: calc(14px + var(--safe-top));
            position: sticky;
            top: 0;
            z-index: 100;
            flex-shrink: 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .header-info h1 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .header-info p {
            font-size: 12px;
            opacity: 0.9;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-points {
            background: rgba(255,255,255,0.2);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 {
            font-size: 18px;
            font-weight: 700;
        }

        /* Page không có tab bar */
        .app.no-tab-bar .main {
            padding-bottom: 16px;
        }

        /* Main không có padding bottom (cho trang không có tab bar) */
        .main.no-padding-bottom {
            padding-bottom: 16px;
        }

        /* Page với header compact */
        .header.compact {
            padding: 10px 16px;
            padding-top: calc(10px + var(--safe-top));
        }

        .header.compact .back-btn {
            width: 36px;
            height: 36px;
            font-size: 18px;
        }

        .header.compact h1 {
            font-size: 17px;
        }

        /* Back button link style */
        a.back-btn {
            text-decoration: none;
            color: white;
        }

        /* ========== MAIN CONTENT ========== */
        .main {
            flex: 1;
            padding: 16px;
            padding-bottom: calc(16px + var(--tab-bar-height) + var(--safe-bottom));
        }

        /* ========== CARDS ========== */
        .card {
            background: var(--card);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ========== GRID MENU ========== */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .menu-item {
            background: var(--card);
            border-radius: var(--radius);
            padding: 24px 16px;
            text-align: center;
            box-shadow: var(--shadow);
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
            border: 2px solid transparent;
        }

        .menu-item:active {
            transform: scale(0.96);
        }

        .menu-item .icon {
            font-size: 40px;
            margin-bottom: 12px;
            display: block;
        }

        .menu-item .label {
            font-size: 15px;
            font-weight: 700;
        }

        .menu-item.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .menu-item.success {
            border-color: var(--success);
        }

        .menu-item.warning {
            border-color: var(--warning);
        }

        /* ========== BUTTONS ========== */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn:active {
            transform: scale(0.96);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
        }

        .btn-block {
            width: 100%;
        }

        .btn-lg {
            padding: 18px 32px;
            font-size: 18px;
            min-height: 60px;
        }

        /* ========== BOTTOM TAB BAR ========== */
        .tab-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: calc(60px + var(--safe-bottom));
            padding-bottom: var(--safe-bottom);
            background: var(--card);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 100;
        }

        .tab-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--text-light);
            padding: 8px 16px;
            border-radius: 12px;
            transition: all 0.2s;
            min-width: 64px;
        }

        .tab-item .icon {
            font-size: 24px;
            margin-bottom: 4px;
        }

        .tab-item .label {
            font-size: 11px;
            font-weight: 600;
        }

        .tab-item.active {
            color: var(--primary);
        }

        .tab-item.active .icon {
            transform: scale(1.1);
        }

        /* ========== LISTS ========== */
        .list-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--card);
            border-radius: var(--radius-sm);
            margin-bottom: 10px;
            box-shadow: var(--shadow);
            text-decoration: none;
            color: var(--text);
        }

        .list-item:active {
            background: #F9FAFB;
        }

        .list-item .icon {
            font-size: 32px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            border-radius: 12px;
        }

        .list-item .content {
            flex: 1;
        }

        .list-item .title {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 2px;
        }

        .list-item .subtitle {
            font-size: 13px;
            color: var(--text-light);
        }

        .list-item .arrow {
            color: var(--text-light);
            font-size: 20px;
        }

        /* ========== ANSWER OPTIONS (Exam) ========== */
        .answer-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .answer-option {
            padding: 20px;
            background: var(--card);
            border: 3px solid var(--border);
            border-radius: var(--radius);
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .answer-option:active {
            transform: scale(0.98);
        }

        .answer-option .letter {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            flex-shrink: 0;
        }

        .answer-option.selected {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.08);
        }

        .answer-option.selected .letter {
            background: var(--primary);
            color: white;
        }

        .answer-option.correct {
            border-color: var(--success);
            background: rgba(16, 185, 129, 0.08);
        }

        .answer-option.correct .letter {
            background: var(--success);
            color: white;
        }

        .answer-option.wrong {
            border-color: var(--danger);
            background: rgba(239, 68, 68, 0.08);
        }

        .answer-option.wrong .letter {
            background: var(--danger);
            color: white;
        }

        /* ========== PROGRESS BAR ========== */
        .progress-bar {
            height: 8px;
            background: var(--border);
            border-radius: 4px;
            overflow: hidden;
            margin: 12px 0;
        }

        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border-radius: 4px;
            transition: width 0.3s;
        }

        /* ========== TIMER ========== */
        .timer {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 18px;
            font-weight: 700;
        }

        .timer.warning {
            background: var(--warning);
            animation: pulse 1s infinite;
        }

        .timer.danger {
            background: var(--danger);
            animation: pulse 0.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* ========== RESULT SCORE ========== */
        .result-score {
            text-align: center;
            padding: 32px 20px;
        }

        .result-score .emoji {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .result-score .score {
            font-size: 48px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .result-score .label {
            font-size: 16px;
            color: var(--text-light);
        }

        .result-score .stars {
            font-size: 32px;
            margin-top: 12px;
        }

        /* ========== STATS GRID ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 16px 0;
        }

        .stat-item {
            text-align: center;
            padding: 16px 8px;
            background: var(--bg);
            border-radius: var(--radius-sm);
        }

        .stat-item .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-item .label {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }

        .stat-item.success .value { color: var(--success); }
        .stat-item.danger .value { color: var(--danger); }
        .stat-item.warning .value { color: var(--warning); }

        /* ========== TOAST ========== */
        .toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: var(--text);
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 600;
            z-index: 1000;
            opacity: 0;
            transition: all 0.3s;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }
        .toast.warning { background: var(--warning); }

        /* ========== LOADING ========== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            margin-top: 16px;
            font-weight: 600;
            color: var(--text-light);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
        }

        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state .title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-state .desc {
            font-size: 14px;
            color: var(--text-light);
        }

        /* ========== UTILITIES ========== */
        .text-center { text-align: center; }
        .text-primary { color: var(--primary); }
        .text-success { color: var(--success); }
        .text-danger { color: var(--danger); }
        .text-muted { color: var(--text-light); }
        .font-bold { font-weight: 700; }
        .mt-16 { margin-top: 16px; }
        .mb-16 { margin-bottom: 16px; }
        .p-16 { padding: 16px; }

        /* ========== AUTHOR CREDIT ========== */
        .author-credit {
            text-align: center;
            padding: 16px;
            font-size: 11px;
            color: var(--text-light);
        }
    </style>
    <?php if (isset($extraStyles)) echo $extraStyles; ?>
</head>
<body>
    <div class="app">
