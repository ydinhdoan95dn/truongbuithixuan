<?php
/**
 * Xử lý đăng xuất
 */
require_once 'includes/config.php';

// Log hoạt động trước khi xóa session
if (isStudentLoggedIn()) {
    logActivity('hoc_sinh', $_SESSION['student_id'], 'Đăng xuất', 'Đăng xuất thành công');
} elseif (isAdminLoggedIn()) {
    logActivity('admin', $_SESSION['admin_id'], 'Đăng xuất', 'Đăng xuất thành công');
}

// Xóa toàn bộ session
session_unset();
session_destroy();

// Chuyển về trang chủ
redirect('');
