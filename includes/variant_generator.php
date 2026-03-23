<?php
/**
 * ==============================================
 * ENGINE SINH ĐỀ BIẾN THỂ - MÔN TOÁN
 * ==============================================
 * Từ 1 đề gốc, sinh ra nhiều đề biến thể với các số khác nhau
 * nhưng cùng cấu trúc và độ khó
 */

/**
 * Sinh đề biến thể từ đề gốc
 * @param int $deGocId - ID đề gốc
 * @param int $soLuongBienThe - Số lượng đề biến thể cần tạo
 * @return array - Danh sách ID các đề biến thể đã tạo
 */
function generateVariantExams($deGocId, $soLuongBienThe = 5) {
    $conn = getDBConnection();

    // Lấy thông tin đề gốc
    $stmtDe = $conn->prepare("SELECT * FROM de_thi WHERE id = ?");
    $stmtDe->execute(array($deGocId));
    $deGoc = $stmtDe->fetch();

    if (!$deGoc) {
        return array('success' => false, 'message' => 'Không tìm thấy đề gốc');
    }

    // Lấy câu hỏi gốc
    $stmtCH = $conn->prepare("SELECT * FROM cau_hoi WHERE de_thi_id = ? ORDER BY id ASC");
    $stmtCH->execute(array($deGocId));
    $cauHoiGocList = $stmtCH->fetchAll();

    if (empty($cauHoiGocList)) {
        return array('success' => false, 'message' => 'Đề gốc không có câu hỏi');
    }

    $createdIds = array();

    for ($i = 1; $i <= $soLuongBienThe; $i++) {
        // Tạo đề biến thể
        $tenDeBienThe = $deGoc['ten_de'] . ' - Biến thể ' . $i;

        $stmtInsertDe = $conn->prepare("
            INSERT INTO de_thi (ten_de, mo_ta, mon_hoc_id, lop_id, so_cau, thoi_gian_cau, random_cau_hoi, admin_id, trang_thai, tuan_id, de_goc_id, is_bien_the, che_do_mo, ngay_mo_thi, gio_bat_dau, gio_ket_thuc)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)
        ");
        $stmtInsertDe->execute(array(
            $tenDeBienThe,
            $deGoc['mo_ta'] . ' (Biến thể ' . $i . ')',
            $deGoc['mon_hoc_id'],
            $deGoc['lop_id'],
            $deGoc['so_cau'],
            $deGoc['thoi_gian_cau'],
            $deGoc['random_cau_hoi'],
            $deGoc['admin_id'],
            $deGoc['trang_thai'],
            $deGoc['tuan_id'],
            $deGocId,
            isset($deGoc['che_do_mo']) ? $deGoc['che_do_mo'] : 'theo_lich',
            isset($deGoc['ngay_mo_thi']) ? $deGoc['ngay_mo_thi'] : 't7,cn',
            isset($deGoc['gio_bat_dau']) ? $deGoc['gio_bat_dau'] : '00:00:00',
            isset($deGoc['gio_ket_thuc']) ? $deGoc['gio_ket_thuc'] : '23:59:59'
        ));

        $deBienTheId = $conn->lastInsertId();
        $createdIds[] = $deBienTheId;

        // Tạo câu hỏi biến thể
        foreach ($cauHoiGocList as $cauHoiGoc) {
            $cauHoiBienThe = generateVariantQuestion($cauHoiGoc);

            $stmtInsertCH = $conn->prepare("
                INSERT INTO cau_hoi (de_thi_id, noi_dung, dap_an_a, dap_an_b, dap_an_c, dap_an_d, dap_an_dung)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsertCH->execute(array(
                $deBienTheId,
                $cauHoiBienThe['noi_dung'],
                $cauHoiBienThe['dap_an_a'],
                $cauHoiBienThe['dap_an_b'],
                $cauHoiBienThe['dap_an_c'],
                $cauHoiBienThe['dap_an_d'],
                $cauHoiBienThe['dap_an_dung']
            ));
        }
    }

    return array(
        'success' => true,
        'message' => 'Đã tạo ' . $soLuongBienThe . ' đề biến thể',
        'de_ids' => $createdIds
    );
}

/**
 * Sinh câu hỏi biến thể từ câu hỏi gốc
 * Phân tích cấu trúc và thay đổi số liệu
 */
function generateVariantQuestion($cauHoiGoc) {
    $noiDung = $cauHoiGoc['noi_dung'];
    $dapAnDung = $cauHoiGoc['dap_an_dung'];

    // Phát hiện loại phép tính
    $loaiPhepTinh = detectOperationType($noiDung);

    if ($loaiPhepTinh) {
        // Sinh câu hỏi mới theo loại phép tính
        return generateMathQuestion($loaiPhepTinh, $cauHoiGoc);
    } else {
        // Không phát hiện được pattern, giữ nguyên câu hỏi gốc
        // nhưng shuffle đáp án
        return shuffleAnswers($cauHoiGoc);
    }
}

/**
 * Phát hiện loại phép tính trong câu hỏi
 */
function detectOperationType($noiDung) {
    // Pattern cho phép cộng: "5 + 3 = ?" hoặc "5 cộng 3 bằng ?"
    if (preg_match('/(\d+)\s*[\+\+]\s*(\d+)/u', $noiDung) ||
        preg_match('/(\d+)\s*cộng\s*(\d+)/ui', $noiDung)) {
        return 'cong';
    }

    // Pattern cho phép trừ
    if (preg_match('/(\d+)\s*[\-\−]\s*(\d+)/u', $noiDung) ||
        preg_match('/(\d+)\s*trừ\s*(\d+)/ui', $noiDung)) {
        return 'tru';
    }

    // Pattern cho phép nhân
    if (preg_match('/(\d+)\s*[×xX\*]\s*(\d+)/u', $noiDung) ||
        preg_match('/(\d+)\s*nhân\s*(\d+)/ui', $noiDung)) {
        return 'nhan';
    }

    // Pattern cho phép chia
    if (preg_match('/(\d+)\s*[\:÷\/]\s*(\d+)/u', $noiDung) ||
        preg_match('/(\d+)\s*chia\s*(\d+)/ui', $noiDung)) {
        return 'chia';
    }

    // Pattern so sánh: "5 ... 3" (điền dấu)
    if (preg_match('/(\d+)\s*\.\.\.\s*(\d+)/u', $noiDung) ||
        preg_match('/so\s*sánh/ui', $noiDung)) {
        return 'so_sanh';
    }

    return null;
}

/**
 * Sinh câu hỏi Toán mới theo loại phép tính
 */
function generateMathQuestion($loaiPhepTinh, $cauHoiGoc) {
    // Xác định phạm vi số dựa vào câu hỏi gốc
    preg_match_all('/\d+/', $cauHoiGoc['noi_dung'], $matches);
    $numbers = array_map('intval', $matches[0]);

    if (empty($numbers)) {
        return shuffleAnswers($cauHoiGoc);
    }

    $maxNum = max($numbers);
    $minNum = max(1, min($numbers));

    // Xác định phạm vi phù hợp theo độ lớn số
    if ($maxNum <= 10) {
        $range = array('min' => 1, 'max' => 10);
    } elseif ($maxNum <= 100) {
        $range = array('min' => 1, 'max' => 100);
    } elseif ($maxNum <= 1000) {
        $range = array('min' => 10, 'max' => 1000);
    } else {
        $range = array('min' => 100, 'max' => 10000);
    }

    switch ($loaiPhepTinh) {
        case 'cong':
            return generateAdditionQuestion($range);
        case 'tru':
            return generateSubtractionQuestion($range);
        case 'nhan':
            return generateMultiplicationQuestion($range);
        case 'chia':
            return generateDivisionQuestion($range);
        case 'so_sanh':
            return generateComparisonQuestion($range);
        default:
            return shuffleAnswers($cauHoiGoc);
    }
}

/**
 * Sinh câu hỏi phép cộng
 */
function generateAdditionQuestion($range) {
    $a = rand($range['min'], $range['max']);
    $b = rand($range['min'], $range['max']);
    $ketQua = $a + $b;

    $noiDung = "{$a} + {$b} = ?";

    // Tạo các đáp án nhiễu
    $dapAnSai = generateWrongAnswers($ketQua, 3, $range);

    // Shuffle và gán đáp án
    return assignAnswers($ketQua, $dapAnSai, $noiDung);
}

/**
 * Sinh câu hỏi phép trừ
 */
function generateSubtractionQuestion($range) {
    // Đảm bảo a >= b để kết quả không âm
    $a = rand($range['min'], $range['max']);
    $b = rand($range['min'], $a);
    $ketQua = $a - $b;

    $noiDung = "{$a} - {$b} = ?";

    $dapAnSai = generateWrongAnswers($ketQua, 3, $range);
    return assignAnswers($ketQua, $dapAnSai, $noiDung);
}

/**
 * Sinh câu hỏi phép nhân
 */
function generateMultiplicationQuestion($range) {
    // Giới hạn phạm vi cho phép nhân
    $maxA = min(99, $range['max']);
    $maxB = min(12, $range['max']);

    $a = rand(2, $maxA);
    $b = rand(2, $maxB);
    $ketQua = $a * $b;

    $noiDung = "{$a} × {$b} = ?";

    $dapAnSai = generateWrongAnswers($ketQua, 3, array('min' => 1, 'max' => $ketQua * 2));
    return assignAnswers($ketQua, $dapAnSai, $noiDung);
}

/**
 * Sinh câu hỏi phép chia
 */
function generateDivisionQuestion($range) {
    // Tạo phép chia chia hết
    $b = rand(2, min(12, $range['max']));
    $ketQua = rand(1, min(100, intval($range['max'] / $b)));
    $a = $b * $ketQua;

    $noiDung = "{$a} : {$b} = ?";

    $dapAnSai = generateWrongAnswers($ketQua, 3, array('min' => 1, 'max' => $ketQua * 3));
    return assignAnswers($ketQua, $dapAnSai, $noiDung);
}

/**
 * Sinh câu hỏi so sánh
 */
function generateComparisonQuestion($range) {
    $a = rand($range['min'], $range['max']);
    $b = rand($range['min'], $range['max']);

    $noiDung = "Điền dấu thích hợp vào chỗ trống: {$a} ... {$b}";

    if ($a > $b) {
        $ketQua = '>';
        $dapAnSai = array('<', '=', '≤');
    } elseif ($a < $b) {
        $ketQua = '<';
        $dapAnSai = array('>', '=', '≥');
    } else {
        $ketQua = '=';
        $dapAnSai = array('>', '<', '≠');
    }

    return assignAnswers($ketQua, $dapAnSai, $noiDung);
}

/**
 * Tạo các đáp án sai
 */
function generateWrongAnswers($ketQuaDung, $soLuong, $range) {
    $dapAnSai = array();
    $attempts = 0;
    $maxAttempts = 50;

    while (count($dapAnSai) < $soLuong && $attempts < $maxAttempts) {
        $attempts++;

        // Tạo đáp án sai gần với đáp án đúng
        $variation = rand(1, max(5, intval($ketQuaDung * 0.3)));
        $wrongAnswer = rand(0, 1) ? $ketQuaDung + $variation : max(0, $ketQuaDung - $variation);

        // Đảm bảo đáp án sai khác đáp án đúng và không trùng
        if ($wrongAnswer != $ketQuaDung && !in_array($wrongAnswer, $dapAnSai) && $wrongAnswer >= 0) {
            $dapAnSai[] = $wrongAnswer;
        }
    }

    // Nếu không đủ đáp án sai, thêm ngẫu nhiên
    while (count($dapAnSai) < $soLuong) {
        $wrongAnswer = rand(max(0, $ketQuaDung - 10), $ketQuaDung + 10);
        if ($wrongAnswer != $ketQuaDung && !in_array($wrongAnswer, $dapAnSai)) {
            $dapAnSai[] = $wrongAnswer;
        }
    }

    return $dapAnSai;
}

/**
 * Gán đáp án vào các vị trí A, B, C, D ngẫu nhiên
 */
function assignAnswers($ketQuaDung, $dapAnSai, $noiDung) {
    $allAnswers = array_merge(array($ketQuaDung), $dapAnSai);
    shuffle($allAnswers);

    // Tìm vị trí đáp án đúng
    $viTriDung = array_search($ketQuaDung, $allAnswers);
    $kyHieuDung = array('A', 'B', 'C', 'D');

    return array(
        'noi_dung' => $noiDung,
        'dap_an_a' => strval($allAnswers[0]),
        'dap_an_b' => strval($allAnswers[1]),
        'dap_an_c' => strval($allAnswers[2]),
        'dap_an_d' => strval($allAnswers[3]),
        'dap_an_dung' => $kyHieuDung[$viTriDung]
    );
}

/**
 * Shuffle đáp án của câu hỏi gốc (khi không detect được pattern)
 */
function shuffleAnswers($cauHoiGoc) {
    $answers = array(
        'A' => $cauHoiGoc['dap_an_a'],
        'B' => $cauHoiGoc['dap_an_b'],
        'C' => $cauHoiGoc['dap_an_c'],
        'D' => $cauHoiGoc['dap_an_d']
    );

    $correctValue = $answers[$cauHoiGoc['dap_an_dung']];

    // Shuffle đáp án
    $values = array_values($answers);
    shuffle($values);

    // Tìm vị trí mới của đáp án đúng
    $newPosition = array_search($correctValue, $values);
    $kyHieu = array('A', 'B', 'C', 'D');

    return array(
        'noi_dung' => $cauHoiGoc['noi_dung'],
        'dap_an_a' => $values[0],
        'dap_an_b' => $values[1],
        'dap_an_c' => $values[2],
        'dap_an_d' => $values[3],
        'dap_an_dung' => $kyHieu[$newPosition]
    );
}

/**
 * Sinh đề biến thể từ mẫu câu hỏi
 * Sử dụng bảng cau_hoi_mau
 */
function generateExamFromTemplate($lopId, $soCau = 10, $tuanId = null, $tenDe = null) {
    $conn = getDBConnection();

    // Lấy mẫu câu hỏi của lớp
    $stmtMau = $conn->prepare("SELECT * FROM cau_hoi_mau WHERE lop_id = ? AND trang_thai = 1");
    $stmtMau->execute(array($lopId));
    $mauList = $stmtMau->fetchAll();

    if (empty($mauList)) {
        return array('success' => false, 'message' => 'Không có mẫu câu hỏi cho lớp này');
    }

    // Lấy môn Toán
    $stmtMon = $conn->query("SELECT id FROM mon_hoc WHERE ten_mon LIKE '%Toán%' LIMIT 1");
    $monToan = $stmtMon->fetch();
    $monHocId = $monToan ? $monToan['id'] : 1;

    // Tạo tên đề
    if (!$tenDe) {
        $tenDe = 'Đề Toán tự động - ' . date('d/m/Y H:i');
    }

    // Tạo đề thi
    $stmtDe = $conn->prepare("
        INSERT INTO de_thi (ten_de, mo_ta, mon_hoc_id, lop_id, so_cau, thoi_gian_cau, random_cau_hoi, trang_thai, tuan_id, che_do_mo, ngay_mo_thi)
        VALUES (?, ?, ?, ?, ?, 15, 1, 1, ?, 'theo_lich', 't7,cn')
    ");
    $stmtDe->execute(array($tenDe, 'Đề thi tự động từ mẫu', $monHocId, $lopId, $soCau, $tuanId));
    $deThiId = $conn->lastInsertId();

    // Sinh câu hỏi từ mẫu
    for ($i = 0; $i < $soCau; $i++) {
        // Chọn ngẫu nhiên mẫu
        $mau = $mauList[array_rand($mauList)];

        // Sinh câu hỏi từ mẫu
        $cauHoi = generateQuestionFromTemplate($mau);

        $stmtCH = $conn->prepare("
            INSERT INTO cau_hoi (de_thi_id, noi_dung, dap_an_a, dap_an_b, dap_an_c, dap_an_d, dap_an_dung)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtCH->execute(array(
            $deThiId,
            $cauHoi['noi_dung'],
            $cauHoi['dap_an_a'],
            $cauHoi['dap_an_b'],
            $cauHoi['dap_an_c'],
            $cauHoi['dap_an_d'],
            $cauHoi['dap_an_dung']
        ));
    }

    return array(
        'success' => true,
        'message' => 'Đã tạo đề thi với ' . $soCau . ' câu hỏi',
        'de_thi_id' => $deThiId
    );
}

/**
 * Sinh câu hỏi từ mẫu
 */
function generateQuestionFromTemplate($mau) {
    // Sinh số ngẫu nhiên trong phạm vi
    $a = rand($mau['bien_a_min'], $mau['bien_a_max']);
    $b = rand($mau['bien_b_min'], $mau['bien_b_max']);

    // Đảm bảo a > b cho phép trừ
    if ($mau['loai_phep_tinh'] == 'tru' && $a < $b) {
        $temp = $a;
        $a = $b;
        $b = $temp;
    }

    // Đảm bảo chia hết cho phép chia
    if ($mau['loai_phep_tinh'] == 'chia') {
        $b = rand(max(2, $mau['bien_b_min']), min(12, $mau['bien_b_max']));
        $ketQua = rand(1, 100);
        $a = $b * $ketQua;
    }

    // Thay thế biến trong mẫu
    $noiDung = str_replace('{a}', $a, $mau['mau_cau_hoi']);
    $noiDung = str_replace('{b}', $b, $noiDung);

    // Tính kết quả
    $ketQua = 0;
    switch ($mau['loai_phep_tinh']) {
        case 'cong':
            $ketQua = $a + $b;
            break;
        case 'tru':
            $ketQua = $a - $b;
            break;
        case 'nhan':
            $ketQua = $a * $b;
            break;
        case 'chia':
            $ketQua = intval($a / $b);
            break;
    }

    // Sinh đáp án sai
    $dapAnSai = generateWrongAnswers($ketQua, 3, array('min' => 0, 'max' => $ketQua * 2 + 10));

    return assignAnswers($ketQua, $dapAnSai, $noiDung);
}

/**
 * Xóa tất cả đề biến thể của đề gốc
 */
function deleteVariantExams($deGocId) {
    $conn = getDBConnection();

    // Lấy danh sách đề biến thể
    $stmt = $conn->prepare("SELECT id FROM de_thi WHERE de_goc_id = ?");
    $stmt->execute(array($deGocId));
    $bienTheList = $stmt->fetchAll();

    foreach ($bienTheList as $bt) {
        // Xóa câu hỏi
        $stmtDelCH = $conn->prepare("DELETE FROM cau_hoi WHERE de_thi_id = ?");
        $stmtDelCH->execute(array($bt['id']));

        // Xóa đề
        $stmtDelDe = $conn->prepare("DELETE FROM de_thi WHERE id = ?");
        $stmtDelDe->execute(array($bt['id']));
    }

    return array(
        'success' => true,
        'message' => 'Đã xóa ' . count($bienTheList) . ' đề biến thể'
    );
}
