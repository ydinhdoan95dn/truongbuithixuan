<?php
/**
 * ==============================================
 * MOBILE - XEM TÀI LIỆU (Google Drive Embed)
 * ==============================================
 */

require_once '../../includes/config.php';
require_once '../../includes/device.php';

if (!isStudentLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$student = getCurrentStudent();
$conn = getDBConnection();

$docId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($docId <= 0) {
    header('Location: ' . BASE_URL . '/student/mobile/documents.php');
    exit;
}

// Lấy thông tin tài liệu
$stmt = $conn->prepare("
    SELECT tl.*, mh.ten_mon, mh.icon
    FROM tai_lieu tl
    JOIN mon_hoc mh ON tl.mon_hoc_id = mh.id
    WHERE tl.id = ? AND tl.is_public = 1 AND tl.trang_thai = 1
");
$stmt->execute(array($docId));
$taiLieu = $stmt->fetch();

if (!$taiLieu) {
    header('Location: ' . BASE_URL . '/student/mobile/documents.php');
    exit;
}

$pageTitle = $taiLieu['tieu_de'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Quicksand', sans-serif;
            height: 100%;
            overflow: hidden;
        }
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --safe-top: env(safe-area-inset-top, 0px);
            --safe-bottom: env(safe-area-inset-bottom, 0px);
        }
        .viewer-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .viewer-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 12px 16px;
            padding-top: calc(12px + var(--safe-top));
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .back-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
        }
        .viewer-title {
            flex: 1;
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .viewer-frame {
            flex: 1;
            border: none;
            width: 100%;
        }
        .viewer-footer {
            background: white;
            padding: 12px 16px;
            padding-bottom: calc(12px + var(--safe-bottom));
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            gap: 12px;
        }
        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 12px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        .btn-outline {
            background: #F3F4F6;
            color: #1F2937;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="viewer-container">
        <div class="viewer-header">
            <a href="documents.php" class="back-btn">‹</a>
            <div class="viewer-title"><?php echo getSubjectIcon($taiLieu['icon']); ?> <?php echo htmlspecialchars($taiLieu['tieu_de']); ?></div>
        </div>

        <?php if (!empty($taiLieu['youtube_id'])): ?>
            <!-- YouTube Video -->
            <iframe
                class="viewer-frame"
                src="https://www.youtube.com/embed/<?php echo htmlspecialchars($taiLieu['youtube_id']); ?>?rel=0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen>
            </iframe>
        <?php elseif (!empty($taiLieu['google_drive_id'])): ?>
            <!-- Google Drive -->
            <iframe
                class="viewer-frame"
                src="https://drive.google.com/file/d/<?php echo htmlspecialchars($taiLieu['google_drive_id']); ?>/preview"
                allow="autoplay"
                allowfullscreen>
            </iframe>
        <?php elseif ($taiLieu['loai_file'] === 'editor' && !empty($taiLieu['noi_dung'])): ?>
            <!-- Editor Content -->
            <div style="flex: 1; overflow-y: auto; padding: 16px; background: white;">
                <div style="max-width: 800px; margin: 0 auto; line-height: 1.8; font-size: 16px;">
                    <?php echo $taiLieu['noi_dung']; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; padding: 20px;">
                <div>
                    <div style="font-size: 64px; margin-bottom: 16px;">📄</div>
                    <div style="font-weight: 700; margin-bottom: 8px;">Không thể xem trước</div>
                    <div style="color: #6B7280; font-size: 14px;">Tài liệu này chưa có nội dung</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="viewer-footer">
            <a href="documents.php" class="btn btn-outline">← Quay lại</a>
            <?php if (!empty($taiLieu['youtube_id'])): ?>
            <a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($taiLieu['youtube_id']); ?>" class="btn btn-primary" target="_blank">
                🎬 Xem trên YouTube
            </a>
            <?php elseif (!empty($taiLieu['google_drive_id'])): ?>
            <a href="https://drive.google.com/uc?export=download&id=<?php echo htmlspecialchars($taiLieu['google_drive_id']); ?>" class="btn btn-primary" target="_blank">
                ⬇️ Tải xuống
            </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
