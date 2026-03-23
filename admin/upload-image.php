<?php
/**
 * ==============================================
 * UPLOAD HÌNH ẢNH CHO TINYMCE EDITOR
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isAdminLoggedIn()) {
    echo json_encode(array('error' => 'Unauthorized'));
    exit;
}

$uploadDir = '../uploads/editor/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$allowedTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
$maxSize = 5 * 1024 * 1024; // 5MB

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // Validate
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(array('error' => 'Upload error'));
        exit;
    }

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(array('error' => 'Invalid file type'));
        exit;
    }

    if ($file['size'] > $maxSize) {
        echo json_encode(array('error' => 'File too large'));
        exit;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = date('Ymd_His') . '_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(array(
            'location' => BASE_URL . '/uploads/editor/' . $newFileName
        ));
    } else {
        echo json_encode(array('error' => 'Failed to move file'));
    }
} else {
    echo json_encode(array('error' => 'No file uploaded'));
}
