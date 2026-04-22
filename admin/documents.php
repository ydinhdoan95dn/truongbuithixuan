<?php
/**
 * ==============================================
 * QUẢN LÝ TÀI LIỆU
 * + Google Drive, YouTube, Editor (TinyMCE)
 * ==============================================
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!isAdminLoggedIn()) {
    redirect('admin/login.php');
}

$admin = getCurrentAdminFull();
$conn = getDBConnection();

$message = '';
$messageType = '';

// Thư mục upload cho editor images
$editorUploadDir = '../uploads/editor/';
if (!file_exists($editorUploadDir)) {
    mkdir($editorUploadDir, 0777, true);
}

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add') {
        $tieuDe = sanitize($_POST['tieu_de']);
        $moTa = sanitize($_POST['mo_ta']);
        $monHocId = intval($_POST['mon_hoc_id']);
        $lopId = !empty($_POST['lop_id']) ? intval($_POST['lop_id']) : null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        $uploadType = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'google_drive';

        $loaiFile = 'pdf';
        $googleDriveId = '';
        $youtubeId = '';
        $noiDung = '';

        if ($uploadType === 'google_drive') {
            $loaiFile = sanitize($_POST['loai_file']);
            $googleDriveId = sanitize($_POST['google_drive_id']);
            $duongDan = sanitize($_POST['duong_dan']);

            // Extract Google Drive ID từ URL
            if (empty($googleDriveId) && !empty($duongDan)) {
                if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $duongDan, $matches)) {
                    $googleDriveId = $matches[1];
                } elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $duongDan, $matches)) {
                    $googleDriveId = $matches[1];
                }
            }

            if (empty($googleDriveId)) {
                $message = 'Vui lòng nhập link hoặc ID Google Drive!';
                $messageType = 'error';
            }
        } elseif ($uploadType === 'youtube') {
            $loaiFile = 'youtube';
            $youtubeUrl = sanitize($_POST['youtube_url']);

            // Extract YouTube ID từ các định dạng URL khác nhau
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $youtubeUrl, $matches)) {
                $youtubeId = $matches[1];
            } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtubeUrl)) {
                $youtubeId = $youtubeUrl;
            }

            if (empty($youtubeId)) {
                $message = 'Link YouTube không hợp lệ!';
                $messageType = 'error';
            }
        } elseif ($uploadType === 'editor') {
            $loaiFile = 'editor';
            $noiDung = $_POST['noi_dung']; // Không sanitize vì là HTML từ editor
        }

        // Lưu vào database
        if (empty($message)) {
            $stmt = $conn->prepare("
                INSERT INTO tai_lieu (tieu_de, mo_ta, mon_hoc_id, lop_id, loai_file, google_drive_id, youtube_id, noi_dung, is_public, admin_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute(array($tieuDe, $moTa, $monHocId, $lopId, $loaiFile, $googleDriveId, $youtubeId, $noiDung, $isPublic, $admin['id']));

            $message = 'Thêm tài liệu thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $tieuDe = sanitize($_POST['tieu_de']);
        $moTa = sanitize($_POST['mo_ta']);
        $monHocId = intval($_POST['mon_hoc_id']);
        $lopId = !empty($_POST['lop_id']) ? intval($_POST['lop_id']) : null;
        $isPublic = isset($_POST['is_public']) ? 1 : 0;
        $uploadType = isset($_POST['upload_type']) ? $_POST['upload_type'] : 'google_drive';

        $loaiFile = 'pdf';
        $googleDriveId = '';
        $youtubeId = '';
        $noiDung = '';

        if ($uploadType === 'google_drive') {
            $loaiFile = sanitize($_POST['loai_file']);
            $googleDriveId = sanitize($_POST['google_drive_id']);
            $duongDan = isset($_POST['duong_dan']) ? sanitize($_POST['duong_dan']) : '';

            if (empty($googleDriveId) && !empty($duongDan)) {
                if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $duongDan, $matches)) {
                    $googleDriveId = $matches[1];
                } elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $duongDan, $matches)) {
                    $googleDriveId = $matches[1];
                }
            }

            if (empty($googleDriveId)) {
                $message = 'Vui lòng nhập link hoặc ID Google Drive!';
                $messageType = 'error';
            }
        } elseif ($uploadType === 'youtube') {
            $loaiFile = 'youtube';
            $youtubeUrl = sanitize($_POST['youtube_url']);

            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $youtubeUrl, $matches)) {
                $youtubeId = $matches[1];
            } elseif (preg_match('/^[a-zA-Z0-9_-]{11}$/', $youtubeUrl)) {
                $youtubeId = $youtubeUrl;
            }

            if (empty($youtubeId)) {
                $message = 'Link YouTube không hợp lệ!';
                $messageType = 'error';
            }
        } elseif ($uploadType === 'editor') {
            $loaiFile = 'editor';
            $noiDung = $_POST['noi_dung'];
        }

        if (empty($message)) {
            $stmt = $conn->prepare("
                UPDATE tai_lieu
                SET tieu_de = ?, mo_ta = ?, mon_hoc_id = ?, lop_id = ?, loai_file = ?,
                    google_drive_id = ?, youtube_id = ?, noi_dung = ?, is_public = ?
                WHERE id = ?
            ");
            $stmt->execute(array($tieuDe, $moTa, $monHocId, $lopId, $loaiFile, $googleDriveId, $youtubeId, $noiDung, $isPublic, $id));

            $message = 'Cập nhật tài liệu thành công!';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM tai_lieu WHERE id = ?");
        $stmt->execute(array($id));
        $message = 'Xóa tài liệu thành công!';
        $messageType = 'success';
    }
}

// Lấy danh sách lớp (chỉ lớp được phép theo role)
if (isAdmin()) {
    $stmtLop = $conn->query("SELECT * FROM lop_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
} else {
    $lopId = getAdminLopId();
    $stmtLop = $conn->prepare("SELECT * FROM lop_hoc WHERE id = ? OR trang_thai = 1");
    $stmtLop->execute(array($lopId));
}
$lopList = $stmtLop->fetchAll();

$stmtMon = $conn->query("SELECT * FROM mon_hoc WHERE trang_thai = 1 ORDER BY thu_tu");
$monList = $stmtMon->fetchAll();

// Lấy danh sách tài liệu theo quyền
$classFilter = getClassFilterSQL('tl', true);
$stmtTL = $conn->query("
    SELECT tl.*, mh.ten_mon, lh.ten_lop
    FROM tai_lieu tl
    JOIN mon_hoc mh ON tl.mon_hoc_id = mh.id
    LEFT JOIN lop_hoc lh ON tl.lop_id = lh.id
    WHERE {$classFilter}
    ORDER BY tl.created_at DESC
");
$taiLieuList = $stmtTL->fetchAll();

// Thống kê theo loại
$countGDrive = $countYoutube = $countEditor = 0;
foreach ($taiLieuList as $tl) {
    if (!empty($tl['google_drive_id'])) $countGDrive++;
    if (!empty($tl['youtube_id'])) $countYoutube++;
    if ($tl['loai_file'] === 'editor') $countEditor++;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài liệu - <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- TinyMCE Editor -->
    <script src="https://cdn.tiny.cloud/1/0mynsf76992hnllxsipvvkuoe4qcfdesqs0gpza59jmvi223/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .upload-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .upload-tab {
            padding: 12px 20px;
            border-radius: 12px;
            background: #F3F4F6;
            color: #6B7280;
            cursor: pointer;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
            font-size: 0.9rem;
        }
        .upload-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .upload-tab:hover:not(.active) {
            background: #E5E7EB;
        }
        .upload-panel {
            display: none;
        }
        .upload-panel.active {
            display: block;
        }
        .source-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .source-badge.gdrive { background: rgba(59, 130, 246, 0.1); color: #3B82F6; }
        .source-badge.youtube { background: rgba(239, 68, 68, 0.1); color: #EF4444; }
        .source-badge.editor { background: rgba(16, 185, 129, 0.1); color: #10B981; }
        .hint-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }
        .hint-box h4 { margin-bottom: 8px; font-size: 0.9rem; }
        .hint-box ol { padding-left: 20px; margin: 0; line-height: 1.8; }
        .youtube-preview {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 12px;
            margin-top: 12px;
            background: #000;
        }
        .youtube-preview iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .stat-grid-4 {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .stat-grid-4 { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="admin-main">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: #1F2937;">📁 Quản lý tài liệu</h1>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i data-feather="plus"></i> Thêm tài liệu
                </button>
            </div>

            <?php if ($message): ?>
                <div style="padding: 16px; border-radius: 12px; margin-bottom: 20px; background: <?php echo $messageType === 'success' ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'; ?>; color: <?php echo $messageType === 'success' ? '#10B981' : '#EF4444'; ?>;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stat-grid-4">
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #667eea;"><?php echo count($taiLieuList); ?></div>
                    <div style="color: #6B7280;">Tổng tài liệu</div>
                </div>
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #3B82F6;"><?php echo $countGDrive; ?></div>
                    <div style="color: #6B7280;">Google Drive</div>
                </div>
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #EF4444;"><?php echo $countYoutube; ?></div>
                    <div style="color: #6B7280;">YouTube</div>
                </div>
                <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 2rem; font-weight: 700; color: #10B981;"><?php echo $countEditor; ?></div>
                    <div style="color: #6B7280;">Bài soạn</div>
                </div>
            </div>

            <!-- Table -->
            <div class="card" style="padding: 0; overflow: hidden; background: white; border-radius: 16px;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 700px;">
                        <thead>
                            <tr style="background: #F9FAFB;">
                                <th style="padding: 16px; text-align: left; font-weight: 600; color: #6B7280;">Tài liệu</th>
                                <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Môn</th>
                                <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Lớp</th>
                                <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Loại</th>
                                <th style="padding: 16px; text-align: center; font-weight: 600; color: #6B7280;">Công khai</th>
                                <th style="padding: 16px; text-align: right; font-weight: 600; color: #6B7280;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($taiLieuList) === 0): ?>
                                <tr>
                                    <td colspan="6" style="padding: 40px; text-align: center; color: #9CA3AF;">
                                        <div style="font-size: 3rem; margin-bottom: 12px;">📂</div>
                                        Chưa có tài liệu nào. Nhấn "Thêm tài liệu" để bắt đầu!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($taiLieuList as $tl): ?>
                                    <?php
                                    $icon = '📁';
                                    $badgeClass = '';
                                    $badgeText = '';
                                    if (!empty($tl['youtube_id'])) {
                                        $icon = '🎬'; $badgeClass = 'youtube'; $badgeText = 'YouTube';
                                    } elseif (!empty($tl['google_drive_id'])) {
                                        $icons = array('pdf' => '📄', 'word' => '📝', 'ppt' => '📊', 'video' => '🎬', 'image' => '🖼️');
                                        $icon = isset($icons[$tl['loai_file']]) ? $icons[$tl['loai_file']] : '📁';
                                        $badgeClass = 'gdrive'; $badgeText = 'G-Drive';
                                    } elseif ($tl['loai_file'] === 'editor') {
                                        $icon = '📝'; $badgeClass = 'editor'; $badgeText = 'Bài soạn';
                                    }
                                    ?>
                                    <tr style="border-top: 1px solid #E5E7EB;">
                                        <td style="padding: 16px;">
                                            <div style="display: flex; align-items: center; gap: 12px;">
                                                <span style="font-size: 1.5rem;"><?php echo $icon; ?></span>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($tl['tieu_de']); ?></div>
                                                    <div style="font-size: 0.75rem; color: #9CA3AF;"><?php echo htmlspecialchars($tl['mo_ta']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 16px; text-align: center;"><?php echo htmlspecialchars($tl['ten_mon']); ?></td>
                                        <td style="padding: 16px; text-align: center;"><?php echo $tl['ten_lop'] ? htmlspecialchars($tl['ten_lop']) : 'Chung'; ?></td>
                                        <td style="padding: 16px; text-align: center;">
                                            <span class="source-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                        </td>
                                        <td style="padding: 16px; text-align: center;">
                                            <?php echo $tl['is_public'] ? '✅' : '🔒'; ?>
                                        </td>
                                        <td style="padding: 16px; text-align: right;">
                                            <?php if (!empty($tl['youtube_id'])): ?>
                                                <a href="https://www.youtube.com/watch?v=<?php echo $tl['youtube_id']; ?>" target="_blank" class="btn btn-ghost btn-sm" title="Xem video">
                                                    <i data-feather="play-circle"></i>
                                                </a>
                                            <?php elseif (!empty($tl['google_drive_id'])): ?>
                                                <a href="https://drive.google.com/file/d/<?php echo $tl['google_drive_id']; ?>/view" target="_blank" class="btn btn-ghost btn-sm" title="Xem trên Google Drive">
                                                    <i data-feather="external-link"></i>
                                                </a>
                                            <?php elseif ($tl['loai_file'] === 'editor'): ?>
                                                <button class="btn btn-ghost btn-sm" onclick='previewContent(<?php echo $tl["id"]; ?>)' title="Xem nội dung">
                                                    <i data-feather="eye"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-ghost btn-sm" style="color: #3B82F6;" onclick='editDoc(<?php echo json_encode($tl, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' title="Sửa">
                                                <i data-feather="edit-2"></i>
                                            </button>
                                            <button class="btn btn-ghost btn-sm" style="color: #EF4444;" onclick="deleteDoc(<?php echo $tl['id']; ?>, '<?php echo addslashes($tl['tieu_de']); ?>')" title="Xóa">
                                                <i data-feather="trash-2"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" onclick="closeAddModal()">&times;</button>
            <h3 class="modal-title">Thêm tài liệu mới</h3>

            <form method="POST" id="addDocForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="upload_type" id="upload_type" value="google_drive">

                <div class="form-group">
                    <label class="form-label">Tiêu đề *</label>
                    <input type="text" name="tieu_de" class="form-input" required placeholder="VD: Bài học Toán lớp 3 - Phép cộng">
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả ngắn</label>
                    <input type="text" name="mo_ta" class="form-input" placeholder="Mô tả ngắn về tài liệu">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Môn học *</label>
                        <select name="mon_hoc_id" class="form-input" required>
                            <?php foreach ($monList as $mon): ?>
                                <option value="<?php echo $mon['id']; ?>"><?php echo htmlspecialchars($mon['ten_mon']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lớp</label>
                        <select name="lop_id" class="form-input">
                            <option value="">Tài liệu chung (tất cả lớp)</option>
                            <?php foreach ($lopList as $lop): ?>
                                <?php if ($lop['trang_thai'] == 1): ?>
                                <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Upload Type Tabs -->
                <div class="upload-tabs">
                    <button type="button" class="upload-tab active" onclick="switchUploadTab('google_drive')">
                        ☁️ Google Drive
                    </button>
                    <button type="button" class="upload-tab" onclick="switchUploadTab('youtube')">
                        🎬 YouTube
                    </button>
                    <button type="button" class="upload-tab" onclick="switchUploadTab('editor')">
                        ✏️ Soạn bài
                    </button>
                </div>

                <!-- Panel: Google Drive -->
                <div id="panel-google_drive" class="upload-panel active">
                    <div class="hint-box">
                        <h4>Hướng dẫn lấy link Google Drive</h4>
                        <ol>
                            <li>Upload file lên Google Drive</li>
                            <li>Chuột phải > Chia sẻ > "Bất kỳ ai có đường liên kết"</li>
                            <li>Copy link và dán vào ô bên dưới</li>
                        </ol>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link Google Drive *</label>
                        <input type="text" name="duong_dan" class="form-input" placeholder="https://drive.google.com/file/d/.../view" onpaste="extractGoogleDriveId(this)" oninput="extractGoogleDriveId(this)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hoặc nhập trực tiếp ID</label>
                        <input type="text" name="google_drive_id" class="form-input" placeholder="1abc123xyz...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Loại file</label>
                        <select name="loai_file" class="form-input">
                            <option value="pdf">PDF</option>
                            <option value="word">Word</option>
                            <option value="ppt">PowerPoint</option>
                            <option value="video">Video</option>
                            <option value="image">Hình ảnh</option>
                        </select>
                    </div>
                </div>

                <!-- Panel: YouTube -->
                <div id="panel-youtube" class="upload-panel">
                    <div class="hint-box">
                        <h4>Hướng dẫn thêm video YouTube</h4>
                        <ol>
                            <li>Mở video YouTube muốn thêm</li>
                            <li>Copy link từ thanh địa chỉ (VD: https://www.youtube.com/watch?v=xxxxx)</li>
                            <li>Dán vào ô bên dưới - hệ thống sẽ tự động lấy ID</li>
                        </ol>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link YouTube *</label>
                        <input type="text" name="youtube_url" id="youtube_url" class="form-input" placeholder="https://www.youtube.com/watch?v=xxxxx" oninput="previewYoutube(this.value)">
                    </div>
                    <div id="youtube_preview" style="display: none;">
                        <label class="form-label">Xem trước:</label>
                        <div class="youtube-preview">
                            <iframe id="youtube_iframe" src="" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>

                <!-- Panel: Editor -->
                <div id="panel-editor" class="upload-panel">
                    <div class="hint-box">
                        <h4>Soạn nội dung bài học</h4>
                        <p style="margin: 0;">Sử dụng trình soạn thảo bên dưới để tạo nội dung bài học với hình ảnh, định dạng văn bản...</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nội dung bài học *</label>
                        <textarea name="noi_dung" id="editor_content" style="min-height: 300px;"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_public" checked style="width: 20px; height: 20px;">
                        <span>Công khai (học sinh có thể xem)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i data-feather="save"></i> Lưu tài liệu
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-content" style="max-width: 700px; max-height: 90vh; overflow-y: auto;">
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
            <h3 class="modal-title">Sửa tài liệu</h3>

            <form method="POST" id="editDocForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="upload_type" id="edit_upload_type" value="google_drive">

                <div class="form-group">
                    <label class="form-label">Tiêu đề *</label>
                    <input type="text" name="tieu_de" id="edit_tieu_de" class="form-input" required placeholder="VD: Bài học Toán lớp 3 - Phép cộng">
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả ngắn</label>
                    <input type="text" name="mo_ta" id="edit_mo_ta" class="form-input" placeholder="Mô tả ngắn về tài liệu">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label">Môn học *</label>
                        <select name="mon_hoc_id" id="edit_mon_hoc_id" class="form-input" required>
                            <?php foreach ($monList as $mon): ?>
                                <option value="<?php echo $mon['id']; ?>"><?php echo htmlspecialchars($mon['ten_mon']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lớp</label>
                        <select name="lop_id" id="edit_lop_id" class="form-input">
                            <option value="">Tài liệu chung (tất cả lớp)</option>
                            <?php foreach ($lopList as $lop): ?>
                                <?php if ($lop['trang_thai'] == 1): ?>
                                <option value="<?php echo $lop['id']; ?>"><?php echo htmlspecialchars($lop['ten_lop']); ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Upload Type Tabs -->
                <div class="upload-tabs" id="edit_upload_tabs">
                    <button type="button" class="upload-tab active" onclick="switchEditUploadTab('google_drive')">
                        ☁️ Google Drive
                    </button>
                    <button type="button" class="upload-tab" onclick="switchEditUploadTab('youtube')">
                        🎬 YouTube
                    </button>
                    <button type="button" class="upload-tab" onclick="switchEditUploadTab('editor')">
                        ✏️ Soạn bài
                    </button>
                </div>

                <!-- Panel: Google Drive -->
                <div id="edit_panel-google_drive" class="upload-panel active">
                    <div class="form-group">
                        <label class="form-label">Link Google Drive</label>
                        <input type="text" name="duong_dan" id="edit_duong_dan" class="form-input" placeholder="https://drive.google.com/file/d/.../view" onpaste="extractEditGoogleDriveId(this)" oninput="extractEditGoogleDriveId(this)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hoặc nhập trực tiếp ID *</label>
                        <input type="text" name="google_drive_id" id="edit_google_drive_id" class="form-input" placeholder="1abc123xyz...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Loại file</label>
                        <select name="loai_file" id="edit_loai_file" class="form-input">
                            <option value="pdf">PDF</option>
                            <option value="word">Word</option>
                            <option value="ppt">PowerPoint</option>
                            <option value="video">Video</option>
                            <option value="image">Hình ảnh</option>
                        </select>
                    </div>
                </div>

                <!-- Panel: YouTube -->
                <div id="edit_panel-youtube" class="upload-panel">
                    <div class="form-group">
                        <label class="form-label">Link YouTube *</label>
                        <input type="text" name="youtube_url" id="edit_youtube_url" class="form-input" placeholder="https://www.youtube.com/watch?v=xxxxx" oninput="previewEditYoutube(this.value)">
                    </div>
                    <div id="edit_youtube_preview" style="display: none;">
                        <label class="form-label">Xem trước:</label>
                        <div class="youtube-preview">
                            <iframe id="edit_youtube_iframe" src="" frameborder="0" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>

                <!-- Panel: Editor -->
                <div id="edit_panel-editor" class="upload-panel">
                    <div class="form-group">
                        <label class="form-label">Nội dung bài học *</label>
                        <textarea name="noi_dung" id="edit_editor_content" style="min-height: 300px;"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_public" id="edit_is_public" style="width: 20px; height: 20px;">
                        <span>Công khai (học sinh có thể xem)</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <i data-feather="save"></i> Cập nhật tài liệu
                </button>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        feather.replace();

        var tinymceInitialized = false;

        function showAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.getElementById('addDocForm').reset();
            document.getElementById('youtube_preview').style.display = 'none';
            if (tinymce.get('editor_content')) {
                tinymce.get('editor_content').setContent('');
            }
        }

        function switchUploadTab(type) {
            document.querySelectorAll('.upload-tab').forEach(function(t) { t.classList.remove('active'); });
            document.querySelectorAll('.upload-panel').forEach(function(p) { p.classList.remove('active'); });

            var tabIndex = type === 'google_drive' ? 0 : (type === 'youtube' ? 1 : 2);
            document.querySelectorAll('.upload-tab')[tabIndex].classList.add('active');
            document.getElementById('panel-' + type).classList.add('active');
            document.getElementById('upload_type').value = type;

            // Init TinyMCE khi chọn tab editor
            if (type === 'editor' && !tinymceInitialized) {
                initTinyMCE();
                tinymceInitialized = true;
            }
        }

        function initTinyMCE() {
            tinymce.init({
                selector: '#editor_content',
                height: 400,
                language: 'vi',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                images_upload_url: '<?php echo BASE_URL; ?>/admin/upload-image.php',
                automatic_uploads: true,
                images_reuse_filename: true,
                file_picker_types: 'image',
                content_style: 'body { font-family: Inter, sans-serif; font-size: 16px; line-height: 1.6; }',
                branding: false,
                promotion: false
            });
        }

        function extractGoogleDriveId(input) {
            var url = input.value.trim();
            var fileId = null;

            var match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
            if (match) fileId = match[1];

            if (!fileId) {
                match = url.match(/[?&]id=([a-zA-Z0-9_-]+)/);
                if (match) fileId = match[1];
            }

            if (fileId) {
                document.querySelector('[name="google_drive_id"]').value = fileId;
                input.style.borderColor = '#10B981';
                setTimeout(function() { input.style.borderColor = ''; }, 2000);
            }
        }

        function previewYoutube(url) {
            var videoId = null;
            var match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/);
            if (match) {
                videoId = match[1];
            } else if (/^[a-zA-Z0-9_-]{11}$/.test(url.trim())) {
                videoId = url.trim();
            }

            if (videoId) {
                document.getElementById('youtube_iframe').src = 'https://www.youtube.com/embed/' + videoId;
                document.getElementById('youtube_preview').style.display = 'block';
            } else {
                document.getElementById('youtube_preview').style.display = 'none';
            }
        }

        function deleteDoc(id, title) {
            if (confirm('Bạn có chắc muốn xóa tài liệu "' + title + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function previewContent(id) {
            // Có thể mở modal xem nội dung hoặc redirect
            window.open('<?php echo BASE_URL; ?>/student/mobile/document-view.php?id=' + id, '_blank');
        }

        // ========== EDIT FUNCTIONS ==========
        var editTinymceInitialized = false;

        function editDoc(doc) {
            // Fill basic info
            document.getElementById('edit_id').value = doc.id;
            document.getElementById('edit_tieu_de').value = doc.tieu_de || '';
            document.getElementById('edit_mo_ta').value = doc.mo_ta || '';
            document.getElementById('edit_mon_hoc_id').value = doc.mon_hoc_id;
            document.getElementById('edit_lop_id').value = doc.lop_id || '';
            document.getElementById('edit_is_public').checked = doc.is_public == 1;

            // Determine upload type and fill data
            var uploadType = 'google_drive';
            if (doc.youtube_id && doc.youtube_id !== '') {
                uploadType = 'youtube';
                document.getElementById('edit_youtube_url').value = 'https://www.youtube.com/watch?v=' + doc.youtube_id;
                previewEditYoutube(document.getElementById('edit_youtube_url').value);
            } else if (doc.loai_file === 'editor') {
                uploadType = 'editor';
            } else if (doc.google_drive_id && doc.google_drive_id !== '') {
                uploadType = 'google_drive';
                document.getElementById('edit_google_drive_id').value = doc.google_drive_id;
                document.getElementById('edit_loai_file').value = doc.loai_file || 'pdf';
            }

            // Switch to correct tab
            switchEditUploadTab(uploadType);

            // If editor type, set content after TinyMCE is ready
            if (uploadType === 'editor') {
                setTimeout(function() {
                    if (tinymce.get('edit_editor_content')) {
                        tinymce.get('edit_editor_content').setContent(doc.noi_dung || '');
                    }
                }, 500);
            }

            // Show modal
            document.getElementById('editModal').classList.add('active');
            feather.replace();
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.getElementById('editDocForm').reset();
            document.getElementById('edit_youtube_preview').style.display = 'none';
            if (tinymce.get('edit_editor_content')) {
                tinymce.get('edit_editor_content').setContent('');
            }
        }

        function switchEditUploadTab(type) {
            var tabs = document.querySelectorAll('#edit_upload_tabs .upload-tab');
            var panels = document.querySelectorAll('#editModal .upload-panel');

            tabs.forEach(function(t) { t.classList.remove('active'); });
            panels.forEach(function(p) { p.classList.remove('active'); });

            var tabIndex = type === 'google_drive' ? 0 : (type === 'youtube' ? 1 : 2);
            tabs[tabIndex].classList.add('active');
            document.getElementById('edit_panel-' + type).classList.add('active');
            document.getElementById('edit_upload_type').value = type;

            // Init TinyMCE for edit modal
            if (type === 'editor' && !editTinymceInitialized) {
                initEditTinyMCE();
                editTinymceInitialized = true;
            }
        }

        function initEditTinyMCE() {
            tinymce.init({
                selector: '#edit_editor_content',
                height: 400,
                language: 'vi',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                images_upload_url: '<?php echo BASE_URL; ?>/admin/upload-image.php',
                automatic_uploads: true,
                images_reuse_filename: true,
                file_picker_types: 'image',
                content_style: 'body { font-family: Inter, sans-serif; font-size: 16px; line-height: 1.6; }',
                branding: false,
                promotion: false
            });
        }

        function extractEditGoogleDriveId(input) {
            var url = input.value.trim();
            var fileId = null;

            var match = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
            if (match) fileId = match[1];

            if (!fileId) {
                match = url.match(/[?&]id=([a-zA-Z0-9_-]+)/);
                if (match) fileId = match[1];
            }

            if (fileId) {
                document.getElementById('edit_google_drive_id').value = fileId;
                input.style.borderColor = '#10B981';
                setTimeout(function() { input.style.borderColor = ''; }, 2000);
            }
        }

        function previewEditYoutube(url) {
            var videoId = null;
            var match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/);
            if (match) {
                videoId = match[1];
            } else if (/^[a-zA-Z0-9_-]{11}$/.test(url.trim())) {
                videoId = url.trim();
            }

            if (videoId) {
                document.getElementById('edit_youtube_iframe').src = 'https://www.youtube.com/embed/' + videoId;
                document.getElementById('edit_youtube_preview').style.display = 'block';
            } else {
                document.getElementById('edit_youtube_preview').style.display = 'none';
            }
        }
    </script>
</body>
</html>
