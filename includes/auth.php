<?php
/**
 * ==============================================
 * HỆ THỐNG PHÂN QUYỀN
 * Web App Học tập & Thi trực tuyến Tiểu học
 * ==============================================
 *
 * ROLES:
 * - admin: Quản trị viên - toàn quyền
 * - gvcn: Giáo viên chủ nhiệm - quản lý lớp được phân công
 * - gvbm: Giáo viên bộ môn - xem tài liệu chung, quản lý câu hỏi môn học
 */

// Các quyền theo role
$ROLE_PERMISSIONS = array(
    'admin' => array(
        'manage_teachers',      // Quản lý tài khoản giáo viên
        'manage_all_students',  // Quản lý tất cả học sinh
        'manage_all_exams',     // Quản lý tất cả đề thi
        'manage_all_questions', // Quản lý tất cả câu hỏi
        'manage_all_documents', // Quản lý tất cả tài liệu
        'view_all_results',     // Xem tất cả kết quả
        'manage_classes',       // Quản lý lớp học (tạm khóa)
        'manage_subjects',      // Quản lý môn học (tạm khóa)
        'view_logs',            // Xem log hoạt động
        'system_settings'       // Cài đặt hệ thống
    ),
    'gvcn' => array(
        'manage_class_students', // Quản lý học sinh lớp mình
        'manage_class_exams',    // Quản lý đề thi cho lớp mình
        'manage_class_questions',// Quản lý câu hỏi cho lớp mình
        'manage_class_documents',// Quản lý tài liệu cho lớp mình
        'view_class_results',    // Xem kết quả lớp mình
        'view_public_documents'  // Xem tài liệu chung
    ),
    'gvbm' => array(
        'view_public_documents', // Xem tài liệu chung
        'manage_subject_questions' // Quản lý câu hỏi bộ môn (tạm khóa)
    )
);

/**
 * Lấy thông tin admin đang đăng nhập (bao gồm role và lop_id)
 */
function getCurrentAdminFull() {
    if (!isset($_SESSION['admin_id'])) return null;

    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT a.*, lh.ten_lop, lh.khoi
        FROM admins a
        LEFT JOIN lop_hoc lh ON a.lop_id = lh.id
        WHERE a.id = ?
    ");
    $stmt->execute(array($_SESSION['admin_id']));
    return $stmt->fetch();
}

/**
 * Kiểm tra role của admin hiện tại
 */
function getAdminRole() {
    return isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'gvbm';
}

/**
 * Kiểm tra lớp phụ trách của admin hiện tại
 */
function getAdminLopId() {
    return isset($_SESSION['admin_lop_id']) ? $_SESSION['admin_lop_id'] : null;
}

/**
 * Kiểm tra xem admin có quyền không
 */
function hasPermission($permission) {
    global $ROLE_PERMISSIONS;
    $role = getAdminRole();

    if (!isset($ROLE_PERMISSIONS[$role])) {
        return false;
    }

    return in_array($permission, $ROLE_PERMISSIONS[$role]);
}

/**
 * Kiểm tra xem có phải admin không
 */
function isAdmin() {
    return getAdminRole() === 'admin';
}

/**
 * Kiểm tra xem có phải GVCN không
 */
function isGVCN() {
    return getAdminRole() === 'gvcn';
}

/**
 * Kiểm tra xem có phải GVBM không
 */
function isGVBM() {
    return getAdminRole() === 'gvbm';
}

/**
 * Lấy tên hiển thị của role
 */
function getRoleName($role = null) {
    if ($role === null) {
        $role = getAdminRole();
    }

    $names = array(
        'admin' => 'Quản trị viên',
        'gvcn' => 'GV Chủ nhiệm',
        'gvbm' => 'GV Bộ môn'
    );

    return isset($names[$role]) ? $names[$role] : 'Không xác định';
}

/**
 * Lấy màu badge của role
 */
function getRoleBadgeColor($role = null) {
    if ($role === null) {
        $role = getAdminRole();
    }

    $colors = array(
        'admin' => '#EF4444',  // Đỏ
        'gvcn' => '#3B82F6',   // Xanh dương
        'gvbm' => '#10B981'    // Xanh lá
    );

    return isset($colors[$role]) ? $colors[$role] : '#6B7280';
}

/**
 * Lấy icon của role
 */
function getRoleIcon($role = null) {
    if ($role === null) {
        $role = getAdminRole();
    }

    $icons = array(
        'admin' => 'shield',
        'gvcn' => 'users',
        'gvbm' => 'book'
    );

    return isset($icons[$role]) ? $icons[$role] : 'user';
}

/**
 * Kiểm tra quyền truy cập và chuyển hướng nếu không có quyền
 */
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        $_SESSION['error_message'] = 'Bạn không có quyền truy cập chức năng này!';
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

/**
 * Kiểm tra quyền truy cập trang (chỉ admin)
 */
function requireAdmin() {
    if (!isAdmin()) {
        $_SESSION['error_message'] = 'Chỉ quản trị viên mới có quyền truy cập!';
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

/**
 * Lấy điều kiện WHERE cho lọc theo lớp (dùng trong SQL)
 * @param string $tableAlias - Alias của bảng có cột lop_id
 * @param bool $includeNull - Có bao gồm record không có lop_id (tài liệu chung)
 */
function getClassFilterSQL($tableAlias = '', $includeNull = true) {
    $role = getAdminRole();
    $lopId = getAdminLopId();

    if ($role === 'admin') {
        return '1=1'; // Không lọc
    }

    $prefix = $tableAlias ? $tableAlias . '.' : '';

    if ($role === 'gvcn' && $lopId) {
        if ($includeNull) {
            return "({$prefix}lop_id = {$lopId} OR {$prefix}lop_id IS NULL)";
        }
        return "{$prefix}lop_id = {$lopId}";
    }

    // GVBM chỉ xem tài liệu chung
    if ($includeNull) {
        return "{$prefix}lop_id IS NULL";
    }

    return '1=0'; // Không có quyền
}

/**
 * Lấy danh sách lớp mà admin có quyền xem
 */
function getAccessibleClasses() {
    $role = getAdminRole();
    $lopId = getAdminLopId();
    $conn = getDBConnection();

    if ($role === 'admin') {
        $stmt = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
        return $stmt->fetchAll();
    }

    if ($role === 'gvcn' && $lopId) {
        $stmt = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ?");
        $stmt->execute(array($lopId));
        return $stmt->fetchAll();
    }

    return array();
}

/**
 * Kiểm tra xem admin có quyền với lớp cụ thể không
 */
function canAccessClass($classId) {
    $role = getAdminRole();
    $lopId = getAdminLopId();

    if ($role === 'admin') {
        return true;
    }

    if ($role === 'gvcn') {
        return $lopId == $classId;
    }

    return false;
}

/**
 * Hiển thị thông báo chức năng tạm khóa
 */
function showLockedFeature($featureName = 'Chức năng này') {
    echo '<div style="background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); border: 2px solid #F59E0B; border-radius: 16px; padding: 32px; text-align: center; margin: 20px 0;">';
    echo '<div style="font-size: 48px; margin-bottom: 16px;">🔒</div>';
    echo '<h3 style="color: #92400E; margin-bottom: 8px;">' . $featureName . ' đang phát triển</h3>';
    echo '<p style="color: #B45309;">Chức năng này sẽ sớm được cập nhật trong phiên bản tiếp theo.</p>';
    echo '</div>';
}
