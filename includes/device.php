<?php
/**
 * ==============================================
 * DEVICE DETECTION & REDIRECT
 * Phát hiện thiết bị mobile và điều hướng
 * ==============================================
 */

/**
 * Kiểm tra thiết bị có phải mobile không
 * @return bool
 */
function isMobile() {
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

    // Danh sách các mobile user agents
    $mobile_agents = array(
        'mobile',
        'android',
        'iphone',
        'ipod',
        'ipad',
        'blackberry',
        'windows phone',
        'opera mini',
        'opera mobi',
        'iemobile',
        'webos',
        'palm',
        'symbian',
        'nokia',
        'samsung',
        'lg',
        'htc',
        'mot',
        'sony'
    );

    foreach ($mobile_agents as $agent) {
        if (strpos($ua, $agent) !== false) {
            // iPad có thể dùng desktop mode
            if (strpos($ua, 'ipad') !== false) {
                return false; // iPad dùng desktop
            }
            return true;
        }
    }

    // Kiểm tra thêm màn hình nhỏ qua cookie (set bởi JS)
    if (isset($_COOKIE['is_mobile']) && $_COOKIE['is_mobile'] === '1') {
        return true;
    }

    return false;
}

/**
 * Kiểm tra có phải tablet không
 * @return bool
 */
function isTablet() {
    if (!isset($_SERVER['HTTP_USER_AGENT'])) {
        return false;
    }

    $ua = strtolower($_SERVER['HTTP_USER_AGENT']);

    $tablet_agents = array('ipad', 'tablet', 'kindle', 'playbook');

    foreach ($tablet_agents as $agent) {
        if (strpos($ua, $agent) !== false) {
            return true;
        }
    }

    // Android tablet thường không có "mobile" trong UA
    if (strpos($ua, 'android') !== false && strpos($ua, 'mobile') === false) {
        return true;
    }

    return false;
}

/**
 * Redirect sang mobile nếu đang dùng mobile
 * @param string $mobilePath Đường dẫn đến trang mobile
 */
function redirectIfMobile($mobilePath) {
    // Cho phép user chọn xem desktop: ?view=desktop
    if (isset($_GET['view']) && $_GET['view'] === 'desktop') {
        setcookie('force_desktop', '1', time() + 86400, '/'); // 1 ngày
        return;
    }

    // Đã chọn xem desktop trước đó
    if (isset($_COOKIE['force_desktop']) && $_COOKIE['force_desktop'] === '1') {
        return;
    }

    // Redirect nếu là mobile
    if (isMobile()) {
        header('Location: ' . $mobilePath);
        exit;
    }
}

/**
 * Redirect sang desktop nếu không phải mobile
 * @param string $desktopPath Đường dẫn đến trang desktop
 */
function redirectIfDesktop($desktopPath) {
    // Cho phép user chọn xem mobile: ?view=mobile
    if (isset($_GET['view']) && $_GET['view'] === 'mobile') {
        setcookie('force_mobile', '1', time() + 86400, '/');
        return;
    }

    // Đã chọn xem mobile trước đó
    if (isset($_COOKIE['force_mobile']) && $_COOKIE['force_mobile'] === '1') {
        return;
    }

    // Redirect nếu không phải mobile (hoặc là tablet)
    if (!isMobile() || isTablet()) {
        header('Location: ' . $desktopPath);
        exit;
    }
}

/**
 * Reset preference về mặc định
 */
function resetViewPreference() {
    setcookie('force_desktop', '', time() - 3600, '/');
    setcookie('force_mobile', '', time() - 3600, '/');
}

/**
 * JavaScript để detect screen size và set cookie
 * Gọi hàm này trong <head> của trang
 */
function getScreenDetectJS() {
    return '<script>
    (function() {
        var w = window.innerWidth || document.documentElement.clientWidth;
        if (w < 768) {
            document.cookie = "is_mobile=1;path=/;max-age=3600";
        } else {
            document.cookie = "is_mobile=0;path=/;max-age=3600";
        }
    })();
    </script>';
}
?>
