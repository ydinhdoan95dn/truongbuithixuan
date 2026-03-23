<?php
/**
 * ==============================================
 * STUDENT INDEX - Redirect to Dashboard/Login
 * ==============================================
 */

require_once '../includes/config.php';

// Nếu đã đăng nhập học sinh -> chuyển về dashboard
if (isStudentLoggedIn()) {
    redirect('student/dashboard.php');
} else {
    // Chưa đăng nhập -> chuyển về trang chủ
    redirect('login.php');
}
