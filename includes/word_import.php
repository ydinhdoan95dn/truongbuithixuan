<?php
/**
 * ==============================================
 * IMPORT ĐỀ THI TỪ FILE WORD
 * ==============================================
 * Hỗ trợ định dạng:
 * - .docx (Office Open XML)
 * - .doc (Legacy Word - cần thêm thư viện)
 *
 * Format mẫu trong file Word:
 * Câu 1: 5 + 3 = ?
 * A. 7
 * B. 8
 * C. 9
 * D. 10
 * Đáp án: B
 */

/**
 * Đọc nội dung text từ file .docx
 */
function readDocxContent($filePath) {
    if (!file_exists($filePath)) {
        return array('success' => false, 'message' => 'File không tồn tại');
    }

    // Mở file docx như một zip archive
    $zip = new ZipArchive();
    $result = $zip->open($filePath);

    if ($result !== true) {
        return array('success' => false, 'message' => 'Không thể mở file Word');
    }

    // Đọc document.xml
    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xmlContent === false) {
        return array('success' => false, 'message' => 'Không thể đọc nội dung file');
    }

    // Parse XML và lấy text
    $content = extractTextFromDocx($xmlContent);

    return array('success' => true, 'content' => $content);
}

/**
 * Trích xuất text từ document.xml
 */
function extractTextFromDocx($xmlContent) {
    // Loại bỏ namespace để dễ xử lý
    $xmlContent = str_replace('w:', '', $xmlContent);

    // Tạo DOM Document
    $dom = new DOMDocument();
    $dom->loadXML($xmlContent, LIBXML_NOERROR | LIBXML_NOWARNING);

    $text = '';
    $paragraphs = $dom->getElementsByTagName('p');

    foreach ($paragraphs as $p) {
        $paraText = '';
        $textNodes = $p->getElementsByTagName('t');

        foreach ($textNodes as $t) {
            $paraText .= $t->nodeValue;
        }

        if (trim($paraText) !== '') {
            $text .= $paraText . "\n";
        }
    }

    return $text;
}

/**
 * Parse nội dung thành danh sách câu hỏi
 */
function parseQuestionsFromText($text) {
    $questions = array();
    $lines = explode("\n", $text);
    $currentQuestion = null;

    for ($i = 0; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;

        // Kiểm tra dòng câu hỏi: "Câu 1:", "Câu 1.", "1.", "1:" etc
        if (preg_match('/^(?:Câu\s*)?(\d+)[\.:]\s*(.+)/ui', $line, $matches)) {
            // Lưu câu hỏi trước nếu có
            if ($currentQuestion && isQuestionComplete($currentQuestion)) {
                $questions[] = $currentQuestion;
            }

            // Bắt đầu câu hỏi mới
            $currentQuestion = array(
                'noi_dung' => trim($matches[2]),
                'dap_an_a' => '',
                'dap_an_b' => '',
                'dap_an_c' => '',
                'dap_an_d' => '',
                'dap_an_dung' => ''
            );
            continue;
        }

        // Kiểm tra đáp án A, B, C, D
        if ($currentQuestion) {
            // Pattern: "A. ...", "A) ...", "A: ..."
            if (preg_match('/^A[\.\)\:]\s*(.+)/i', $line, $matches)) {
                $currentQuestion['dap_an_a'] = trim($matches[1]);
            } elseif (preg_match('/^B[\.\)\:]\s*(.+)/i', $line, $matches)) {
                $currentQuestion['dap_an_b'] = trim($matches[1]);
            } elseif (preg_match('/^C[\.\)\:]\s*(.+)/i', $line, $matches)) {
                $currentQuestion['dap_an_c'] = trim($matches[1]);
            } elseif (preg_match('/^D[\.\)\:]\s*(.+)/i', $line, $matches)) {
                $currentQuestion['dap_an_d'] = trim($matches[1]);
            }
            // Pattern đáp án đúng: "Đáp án: B", "ĐA: B", "Đ/a: B"
            elseif (preg_match('/^(?:Đáp\s*án|ĐA|Đ\/a)[\.\:\s]*([ABCD])/ui', $line, $matches)) {
                $currentQuestion['dap_an_dung'] = strtoupper($matches[1]);
            }
        }
    }

    // Lưu câu hỏi cuối cùng
    if ($currentQuestion && isQuestionComplete($currentQuestion)) {
        $questions[] = $currentQuestion;
    }

    return $questions;
}

/**
 * Kiểm tra câu hỏi đã đủ thông tin chưa
 */
function isQuestionComplete($question) {
    return !empty($question['noi_dung'])
        && !empty($question['dap_an_a'])
        && !empty($question['dap_an_b'])
        && !empty($question['dap_an_c'])
        && !empty($question['dap_an_d'])
        && !empty($question['dap_an_dung']);
}

/**
 * Import câu hỏi vào database
 */
function importQuestionsToExam($deThiId, $questions) {
    if (empty($questions)) {
        return array('success' => false, 'message' => 'Không có câu hỏi nào để import');
    }

    $conn = getDBConnection();
    $imported = 0;
    $errors = array();

    foreach ($questions as $index => $q) {
        if (!isQuestionComplete($q)) {
            $errors[] = 'Câu ' . ($index + 1) . ' thiếu thông tin';
            continue;
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO cau_hoi (de_thi_id, noi_dung, dap_an_a, dap_an_b, dap_an_c, dap_an_d, dap_an_dung)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array(
                $deThiId,
                $q['noi_dung'],
                $q['dap_an_a'],
                $q['dap_an_b'],
                $q['dap_an_c'],
                $q['dap_an_d'],
                $q['dap_an_dung']
            ));
            $imported++;
        } catch (Exception $e) {
            $errors[] = 'Câu ' . ($index + 1) . ': ' . $e->getMessage();
        }
    }

    return array(
        'success' => true,
        'imported' => $imported,
        'total' => count($questions),
        'errors' => $errors
    );
}

/**
 * Import đề thi từ file Word
 */
function importExamFromWord($filePath, $tenDe, $monHocId, $lopId, $adminId, $tuanId = null) {
    // Đọc file Word
    $result = readDocxContent($filePath);
    if (!$result['success']) {
        return $result;
    }

    // Parse câu hỏi
    $questions = parseQuestionsFromText($result['content']);
    if (empty($questions)) {
        return array('success' => false, 'message' => 'Không tìm thấy câu hỏi nào trong file');
    }

    $conn = getDBConnection();

    // Tạo đề thi
    $soCau = count($questions);
    $stmt = $conn->prepare("
        INSERT INTO de_thi (ten_de, mo_ta, mon_hoc_id, lop_id, so_cau, thoi_gian_cau, random_cau_hoi, admin_id, trang_thai, tuan_id, che_do_mo, ngay_mo_thi)
        VALUES (?, ?, ?, ?, ?, 15, 1, ?, 1, ?, 'theo_lich', 't7,cn')
    ");
    $stmt->execute(array($tenDe, 'Import từ Word', $monHocId, $lopId, $soCau, $adminId, $tuanId));
    $deThiId = $conn->lastInsertId();

    // Import câu hỏi
    $importResult = importQuestionsToExam($deThiId, $questions);

    return array(
        'success' => true,
        'de_thi_id' => $deThiId,
        'imported' => $importResult['imported'],
        'total' => $importResult['total'],
        'errors' => $importResult['errors'],
        'message' => 'Đã import ' . $importResult['imported'] . '/' . $importResult['total'] . ' câu hỏi'
    );
}

/**
 * Tạo file Word mẫu
 */
function generateSampleWordTemplate() {
    $content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
<w:p><w:r><w:t>HƯỚNG DẪN FORMAT ĐỀ THI</w:t></w:r></w:p>
<w:p><w:r><w:t></w:t></w:r></w:p>
<w:p><w:r><w:t>Câu 1: 5 + 3 = ?</w:t></w:r></w:p>
<w:p><w:r><w:t>A. 7</w:t></w:r></w:p>
<w:p><w:r><w:t>B. 8</w:t></w:r></w:p>
<w:p><w:r><w:t>C. 9</w:t></w:r></w:p>
<w:p><w:r><w:t>D. 10</w:t></w:r></w:p>
<w:p><w:r><w:t>Đáp án: B</w:t></w:r></w:p>
<w:p><w:r><w:t></w:t></w:r></w:p>
<w:p><w:r><w:t>Câu 2: 12 - 5 = ?</w:t></w:r></w:p>
<w:p><w:r><w:t>A. 6</w:t></w:r></w:p>
<w:p><w:r><w:t>B. 7</w:t></w:r></w:p>
<w:p><w:r><w:t>C. 8</w:t></w:r></w:p>
<w:p><w:r><w:t>D. 9</w:t></w:r></w:p>
<w:p><w:r><w:t>Đáp án: B</w:t></w:r></w:p>
<w:p><w:r><w:t></w:t></w:r></w:p>
<w:p><w:r><w:t>Câu 3: 4 x 5 = ?</w:t></w:r></w:p>
<w:p><w:r><w:t>A. 18</w:t></w:r></w:p>
<w:p><w:r><w:t>B. 20</w:t></w:r></w:p>
<w:p><w:r><w:t>C. 22</w:t></w:r></w:p>
<w:p><w:r><w:t>D. 25</w:t></w:r></w:p>
<w:p><w:r><w:t>Đáp án: B</w:t></w:r></w:p>
</w:body>
</w:document>';

    return $content;
}
