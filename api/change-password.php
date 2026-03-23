<?php
/**
 * API Đổi mật khẩu học sinh
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

// Kiểm tra đăng nhập
if (!isStudentLoggedIn()) {
    echo json_encode(array('success' => false, 'message' => 'Bạn chưa đăng nhập'));
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = isset($input['current_password']) ? $input['current_password'] : '';
$newPassword = isset($input['new_password']) ? $input['new_password'] : '';

// Validate
if (empty($currentPassword) || empty($newPassword)) {
    echo json_encode(array('success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'));
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(array('success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 6 ký tự'));
    exit;
}

$studentId = $_SESSION['student_id'];
$conn = getDBConnection();

// Lấy mật khẩu hiện tại
$stmt = $conn->prepare("SELECT password FROM hoc_sinh WHERE id = ?");
$stmt->execute(array($studentId));
$student = $stmt->fetch();

if (!$student) {
    echo json_encode(array('success' => false, 'message' => 'Không tìm thấy tài khoản'));
    exit;
}

// Verify current password
if (!password_verify($currentPassword, $student['password'])) {
    echo json_encode(array('success' => false, 'message' => 'Mật khẩu hiện tại không đúng'));
    exit;
}

// Hash new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update
$stmtUpdate = $conn->prepare("UPDATE hoc_sinh SET password = ? WHERE id = ?");
$result = $stmtUpdate->execute(array($hashedPassword, $studentId));

if ($result) {
    echo json_encode(array('success' => true, 'message' => 'Đổi mật khẩu thành công'));
} else {
    echo json_encode(array('success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại'));
}
