<?php
/**
 * ==============================================
 * ADMIN INDEX - Redirect to Dashboard/Login
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Nếu đã đăng nhập admin -> chuyển về dashboard
if (isAdminLoggedIn()) {
    redirect('admin/dashboard.php');
} else {
    // Chưa đăng nhập -> chuyển về login
    redirect('admin/login.php');
}
