<?php
/**
 * ==============================================
 * ADMIN SIDEBAR - PHÂN QUYỀN THEO ROLE
 * Menu accordion với nhóm chức năng
 * ==============================================
 */

require_once dirname(__DIR__) . '/../includes/auth.php';

$currentAdmin = getCurrentAdminFull();
$role = getAdminRole();
$roleName = getRoleName();
$roleColor = getRoleBadgeColor();
$roleIcon = getRoleIcon();

// Lấy trang hiện tại
$currentPage = basename($_SERVER['PHP_SELF']);

// Xác định nhóm menu đang active
$activeGroup = '';
$examPages = array('exams.php', 'questions.php', 'generate-variants.php', 'import-word.php');
$schedulePages = array('exam-schedule.php', 'weeks.php', 'results.php', 'ranking.php');
$systemPages = array('teachers.php', 'students.php', 'import-students.php', 'settings.php');
$documentPages = array('documents.php');

if (in_array($currentPage, $examPages)) $activeGroup = 'exam';
elseif (in_array($currentPage, $schedulePages)) $activeGroup = 'schedule';
elseif (in_array($currentPage, $systemPages)) $activeGroup = 'system';
elseif (in_array($currentPage, $documentPages)) $activeGroup = 'document';
?>

<style>
.admin-layout {
    display: flex;
    min-height: 100vh;
}
.admin-sidebar {
    width: 280px;
    background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,0.2) transparent;
}
.admin-sidebar::-webkit-scrollbar {
    width: 6px;
}
.admin-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}
.admin-main {
    flex: 1;
    margin-left: 280px;
    padding: 24px;
    background: #F3F4F6;
    min-height: 100vh;
}

/* Header Section */
.sidebar-header {
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    margin-bottom: 8px;
}
.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 12px;
}
.sidebar-logo-icon {
    width: 46px;
    height: 46px;
    background: rgba(255,255,255,0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}
.sidebar-logo h2 {
    font-size: 1rem;
    font-weight: 700;
    margin: 0;
}
.sidebar-logo span {
    font-size: 0.8rem;
    opacity: 0.9;
}
.sidebar-role {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    margin-top: 6px;
    background: rgba(255,255,255,0.25);
}

/* Class Info */
.sidebar-class-info {
    background: rgba(102, 126, 234, 0.15);
    border-left: 3px solid #667eea;
    padding: 10px 16px;
    margin: 0 12px 12px;
    border-radius: 0 8px 8px 0;
    font-size: 0.8rem;
}
.sidebar-class-info strong {
    color: #a5b4fc;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.sidebar-class-info .class-name {
    font-weight: 600;
    font-size: 0.9rem;
    margin-top: 2px;
}

/* Menu Container */
.sidebar-nav {
    padding: 0 12px;
}

/* Single Menu Item (Dashboard, Logout) */
.menu-item {
    margin-bottom: 4px;
}
.menu-item > a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    color: rgba(255,255,255,0.7);
    transition: all 0.2s;
    font-weight: 500;
    font-size: 0.9rem;
    text-decoration: none;
}
.menu-item > a:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}
.menu-item > a.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.menu-item > a i {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

/* Menu Group (Accordion) */
.menu-group {
    margin-bottom: 6px;
}
.menu-group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    color: rgba(255,255,255,0.7);
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 600;
    font-size: 0.85rem;
    user-select: none;
}
.menu-group-header:hover {
    background: rgba(255,255,255,0.08);
    color: white;
}
.menu-group.open .menu-group-header {
    background: rgba(255,255,255,0.1);
    color: white;
}
.menu-group-header .group-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.menu-group-header .group-icon i {
    width: 18px;
    height: 18px;
}
.menu-group-header .group-title {
    flex: 1;
}
.menu-group-header .group-arrow {
    width: 20px;
    height: 20px;
    transition: transform 0.3s;
    opacity: 0.5;
}
.menu-group.open .menu-group-header .group-arrow {
    transform: rotate(180deg);
    opacity: 1;
}
.menu-group-header .group-count {
    background: rgba(255,255,255,0.15);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Submenu */
.menu-submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    padding-left: 20px;
}
.menu-group.open .menu-submenu {
    max-height: 500px;
}
.menu-submenu a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-radius: 8px;
    color: rgba(255,255,255,0.6);
    transition: all 0.2s;
    font-size: 0.85rem;
    text-decoration: none;
    margin: 2px 0;
    border-left: 2px solid transparent;
}
.menu-submenu a:hover {
    background: rgba(255,255,255,0.08);
    color: white;
    border-left-color: rgba(255,255,255,0.3);
}
.menu-submenu a.active {
    background: rgba(102, 126, 234, 0.2);
    color: #a5b4fc;
    border-left-color: #667eea;
    font-weight: 600;
}
.menu-submenu a i {
    width: 16px;
    height: 16px;
    opacity: 0.7;
}
.menu-submenu a.active i {
    opacity: 1;
}

/* Group Icon Colors */
.group-icon.exam { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.group-icon.schedule { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.group-icon.document { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
.group-icon.system { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }

/* Divider */
.menu-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 16px 16px;
}

/* Logout */
.menu-logout {
    margin-top: 8px;
    padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.menu-logout a {
    color: rgba(255,255,255,0.5);
}
.menu-logout a:hover {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
}

/* Locked items */
.menu-submenu a.locked {
    opacity: 0.4;
    cursor: not-allowed;
}
.menu-submenu a.locked:hover {
    background: transparent;
    border-left-color: transparent;
}
.lock-badge {
    margin-left: auto;
    font-size: 0.7rem;
}

/* Mobile */
@media (max-width: 768px) {
    .admin-sidebar {
        display: none;
    }
    .admin-main {
        margin-left: 0;
        padding: 16px;
    }
}
</style>

<aside class="admin-sidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon">🎓</div>
            <div>
                <h2>Admin Panel</h2>
                <span><?php echo htmlspecialchars($currentAdmin['ho_ten']); ?></span>
                <div class="sidebar-role">
                    <i data-feather="<?php echo $roleIcon; ?>" style="width: 12px; height: 12px;"></i>
                    <?php echo $roleName; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($role === 'gvcn' && !empty($currentAdmin['ten_lop'])): ?>
    <div class="sidebar-class-info">
        <strong>Lớp phụ trách</strong>
        <div class="class-name"><?php echo htmlspecialchars($currentAdmin['ten_lop']); ?> (Khối <?php echo $currentAdmin['khoi']; ?>)</div>
    </div>
    <?php endif; ?>

    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="menu-item">
            <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <i data-feather="home"></i>
                Dashboard
            </a>
        </div>

        <?php if (isAdmin() || isGVCN()): ?>
        <!-- NHÓM: Quản lý đề thi -->
        <div class="menu-group <?php echo $activeGroup === 'exam' ? 'open' : ''; ?>" data-group="exam">
            <div class="menu-group-header" onclick="toggleMenuGroup(this)">
                <div class="group-icon exam">
                    <i data-feather="file-text"></i>
                </div>
                <span class="group-title">Quản lý đề thi</span>
                <span class="group-count">4</span>
                <i data-feather="chevron-down" class="group-arrow"></i>
            </div>
            <div class="menu-submenu">
                <a href="<?php echo BASE_URL; ?>/admin/exams.php" class="<?php echo $currentPage === 'exams.php' ? 'active' : ''; ?>">
                    <i data-feather="file-text"></i>
                    Danh sách đề thi
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/questions.php" class="<?php echo $currentPage === 'questions.php' ? 'active' : ''; ?>">
                    <i data-feather="help-circle"></i>
                    Ngân hàng câu hỏi
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/import-word.php" class="<?php echo $currentPage === 'import-word.php' ? 'active' : ''; ?>">
                    <i data-feather="upload"></i>
                    Nhập đề từ Word
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/generate-variants.php" class="<?php echo $currentPage === 'generate-variants.php' ? 'active' : ''; ?>">
                    <i data-feather="shuffle"></i>
                    Sinh đề biến thể
                </a>
            </div>
        </div>

        <!-- NHÓM: Lịch thi & Kết quả -->
        <div class="menu-group <?php echo $activeGroup === 'schedule' ? 'open' : ''; ?>" data-group="schedule">
            <div class="menu-group-header" onclick="toggleMenuGroup(this)">
                <div class="group-icon schedule">
                    <i data-feather="calendar"></i>
                </div>
                <span class="group-title">Lịch thi & Kết quả</span>
                <span class="group-count"><?php echo isAdmin() ? '4' : '3'; ?></span>
                <i data-feather="chevron-down" class="group-arrow"></i>
            </div>
            <div class="menu-submenu">
                <a href="<?php echo BASE_URL; ?>/admin/exam-schedule.php" class="<?php echo $currentPage === 'exam-schedule.php' ? 'active' : ''; ?>">
                    <i data-feather="clock"></i>
                    Quản lý lịch thi
                </a>
                <?php if (isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>/admin/weeks.php" class="<?php echo $currentPage === 'weeks.php' ? 'active' : ''; ?>">
                    <i data-feather="calendar"></i>
                    Quản lý tuần học
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/admin/results.php" class="<?php echo $currentPage === 'results.php' ? 'active' : ''; ?>">
                    <i data-feather="bar-chart-2"></i>
                    Kết quả thi
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/ranking.php" class="<?php echo $currentPage === 'ranking.php' ? 'active' : ''; ?>">
                    <i data-feather="award"></i>
                    Bảng xếp hạng
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- NHÓM: Tài liệu -->
        <div class="menu-group <?php echo $activeGroup === 'document' ? 'open' : ''; ?>" data-group="document">
            <div class="menu-group-header" onclick="toggleMenuGroup(this)">
                <div class="group-icon document">
                    <i data-feather="folder"></i>
                </div>
                <span class="group-title">Tài liệu học tập</span>
                <span class="group-count">1</span>
                <i data-feather="chevron-down" class="group-arrow"></i>
            </div>
            <div class="menu-submenu">
                <a href="<?php echo BASE_URL; ?>/admin/documents.php" class="<?php echo $currentPage === 'documents.php' ? 'active' : ''; ?>">
                    <i data-feather="folder"></i>
                    Quản lý tài liệu
                </a>
            </div>
        </div>

        <?php if (isAdmin() || isGVCN()): ?>
        <!-- NHÓM: Hệ thống -->
        <div class="menu-group <?php echo $activeGroup === 'system' ? 'open' : ''; ?>" data-group="system">
            <div class="menu-group-header" onclick="toggleMenuGroup(this)">
                <div class="group-icon system">
                    <i data-feather="settings"></i>
                </div>
                <span class="group-title">Hệ thống</span>
                <span class="group-count"><?php echo isAdmin() ? '4' : '3'; ?></span>
                <i data-feather="chevron-down" class="group-arrow"></i>
            </div>
            <div class="menu-submenu">
                <?php if (isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>/admin/teachers.php" class="<?php echo $currentPage === 'teachers.php' ? 'active' : ''; ?>">
                    <i data-feather="user-check"></i>
                    Quản lý giáo viên
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/admin/students.php" class="<?php echo $currentPage === 'students.php' ? 'active' : ''; ?>">
                    <i data-feather="users"></i>
                    Quản lý học sinh
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/import-students.php" class="<?php echo $currentPage === 'import-students.php' ? 'active' : ''; ?>">
                    <i data-feather="upload-cloud"></i>
                    Import học sinh
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                    <i data-feather="sliders"></i>
                    Cài đặt hệ thống
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Logout -->
        <div class="menu-item menu-logout">
            <a href="<?php echo BASE_URL; ?>/logout.php">
                <i data-feather="log-out"></i>
                Đăng xuất
            </a>
        </div>
    </nav>
</aside>

<script>
function toggleMenuGroup(header) {
    var group = header.parentElement;
    var isOpen = group.classList.contains('open');

    // Đóng tất cả các nhóm khác (tuỳ chọn - có thể bỏ để cho phép mở nhiều nhóm)
    // document.querySelectorAll('.menu-group.open').forEach(function(g) {
    //     if (g !== group) g.classList.remove('open');
    // });

    // Toggle nhóm hiện tại
    group.classList.toggle('open');

    // Lưu trạng thái vào localStorage
    saveMenuState();
}

function saveMenuState() {
    var openGroups = [];
    document.querySelectorAll('.menu-group.open').forEach(function(g) {
        openGroups.push(g.dataset.group);
    });
    localStorage.setItem('adminMenuState', JSON.stringify(openGroups));
}

function loadMenuState() {
    var saved = localStorage.getItem('adminMenuState');
    if (saved) {
        try {
            var openGroups = JSON.parse(saved);
            openGroups.forEach(function(groupName) {
                var group = document.querySelector('.menu-group[data-group="' + groupName + '"]');
                if (group) group.classList.add('open');
            });
        } catch(e) {}
    }
}

// Load trạng thái khi trang tải xong
document.addEventListener('DOMContentLoaded', function() {
    // Nếu có trang active trong một nhóm, đảm bảo nhóm đó mở
    var activeSubmenuItem = document.querySelector('.menu-submenu a.active');
    if (activeSubmenuItem) {
        var parentGroup = activeSubmenuItem.closest('.menu-group');
        if (parentGroup) parentGroup.classList.add('open');
    }
});
</script>
