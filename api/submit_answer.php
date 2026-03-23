<?php
/**
 * API Gửi đáp án
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

// Lấy dữ liệu
$input = json_decode(file_get_contents('php://input'), true);
$sessionToken = isset($input['session_token']) ? $input['session_token'] : '';
$questionId = isset($input['question_id']) ? intval($input['question_id']) : 0;
$answer = isset($input['answer']) ? $input['answer'] : null;
$timeSpent = isset($input['time_spent']) ? intval($input['time_spent']) : 0;

if (empty($sessionToken) || $questionId <= 0) {
    echo json_encode(array('success' => false, 'message' => 'Invalid parameters'));
    exit;
}

$conn = getDBConnection();

// Lấy bài làm
$stmtBL = $conn->prepare("
    SELECT * FROM bai_lam
    WHERE session_token = ? AND trang_thai = 'dang_lam'
");
$stmtBL->execute(array($sessionToken));
$baiLam = $stmtBL->fetch();

if (!$baiLam) {
    echo json_encode(array('success' => false, 'message' => 'Session not found'));
    exit;
}

// Lấy đáp án đúng
$stmtCH = $conn->prepare("SELECT dap_an_dung FROM cau_hoi WHERE id = ?");
$stmtCH->execute(array($questionId));
$cauHoi = $stmtCH->fetch();

if (!$cauHoi) {
    echo json_encode(array('success' => false, 'message' => 'Question not found'));
    exit;
}

$isDung = ($answer && strtoupper($answer) === strtoupper($cauHoi['dap_an_dung'])) ? 1 : 0;

// Cập nhật chi tiết bài làm
$stmtUpdate = $conn->prepare("
    UPDATE chi_tiet_bai_lam
    SET dap_an_chon = ?, is_dung = ?, thoi_gian_tra_loi = ?
    WHERE bai_lam_id = ? AND cau_hoi_id = ?
");
$stmtUpdate->execute(array($answer, $isDung, $timeSpent, $baiLam['id'], $questionId));

echo json_encode(array(
    'success' => true,
    'is_correct' => $isDung == 1
));
