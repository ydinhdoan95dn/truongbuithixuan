    </div><!-- End #app -->

    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Popup Modal Container -->
    <div id="modal-overlay" class="modal-overlay">
        <div id="modal-content" class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <div id="modal-body"></div>
        </div>
    </div>

    <!-- Document Viewer Fullscreen -->
    <div id="doc-viewer-overlay" class="doc-viewer-overlay">
        <div class="doc-viewer-header">
            <span id="doc-viewer-title" class="doc-viewer-title"></span>
            <button class="doc-viewer-close" onclick="closeDocViewer()">&times;</button>
        </div>
        <div class="doc-viewer-content">
            <iframe id="doc-viewer-iframe" src="" allowfullscreen></iframe>
        </div>
    </div>

    <!-- Main JS -->
    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>

    <?php if (isset($extraJS)): ?>
        <?php foreach ($extraJS as $js): ?>
            <script src="<?php echo BASE_URL; ?>/assets/js/<?php echo $js; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <script>
        // Initialize Feather Icons
        feather.replace();

        // Hide loading overlay
        window.addEventListener('load', function() {
            document.getElementById('loading-overlay').classList.add('hidden');
        });

        // Base URL for JS
        const BASE_URL = '<?php echo BASE_URL; ?>';

        // Document Viewer Functions
        function viewDocument(docId, googleDriveId, fileType, title, localFile) {
            const overlay = document.getElementById('doc-viewer-overlay');
            const iframe = document.getElementById('doc-viewer-iframe');
            const titleEl = document.getElementById('doc-viewer-title');

            titleEl.textContent = title || 'Xem tài liệu';

            let embedUrl = '';

            if (localFile) {
                // Local file - mở trực tiếp
                const fileUrl = BASE_URL + '/uploads/documents/' + localFile;

                if (fileType === 'pdf') {
                    // PDF có thể embed trực tiếp
                    embedUrl = fileUrl;
                } else if (fileType === 'image') {
                    // Hình ảnh - hiển thị trong iframe đặc biệt
                    iframe.src = '';
                    document.querySelector('.doc-viewer-content').innerHTML = `
                        <img src="${fileUrl}" alt="${title}" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;">
                    `;
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    return;
                } else if (fileType === 'video') {
                    // Video local
                    iframe.src = '';
                    document.querySelector('.doc-viewer-content').innerHTML = `
                        <video controls autoplay style="max-width: 100%; max-height: 100%; border-radius: 8px;">
                            <source src="${fileUrl}" type="video/mp4">
                            Trình duyệt không hỗ trợ video.
                        </video>
                    `;
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    return;
                } else {
                    // Word, PPT - mở tab mới để download
                    window.open(fileUrl, '_blank');
                    return;
                }
            } else if (googleDriveId) {
                // Google Drive embed URL
                embedUrl = 'https://drive.google.com/file/d/' + googleDriveId + '/preview';
            } else {
                showToast('Tài liệu chưa có file đính kèm', 'error');
                return;
            }

            // Reset content nếu trước đó hiển thị image/video
            document.querySelector('.doc-viewer-content').innerHTML = '<iframe id="doc-viewer-iframe" src="" allowfullscreen></iframe>';
            document.getElementById('doc-viewer-iframe').src = embedUrl;

            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDocViewer() {
            const overlay = document.getElementById('doc-viewer-overlay');

            overlay.classList.remove('active');
            // Reset content về iframe mặc định
            document.querySelector('.doc-viewer-content').innerHTML = '<iframe id="doc-viewer-iframe" src="" allowfullscreen></iframe>';
            document.body.style.overflow = '';
        }

        // Close viewer on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDocViewer();
                closeModal();
            }
        });

        // Coming soon popup
        function showComingSoonPopup() {
            showModal({
                icon: '🔒',
                title: 'Sắp ra mắt!',
                text: 'Nội dung lớp này đang được xây dựng. Hãy quay lại sau nhé!'
            });
        }

        // Switch class tab for leaderboard
        function switchClassTab(btn, khoi) {
            // Update active state
            document.querySelectorAll('.class-tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');

            // Fetch leaderboard data
            fetch(BASE_URL + '/api/leaderboard.php?khoi=' + khoi)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateLeaderboard(data.data);
                    }
                })
                .catch(err => {
                    console.error('Error loading leaderboard:', err);
                });
        }

        function updateLeaderboard(students) {
            const container = document.getElementById('leaderboard-content');
            if (!students || students.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <p class="empty-state-text">Chưa có dữ liệu</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="rank-list">';
            students.forEach((hs, index) => {
                const rank = index + 1;
                const initial = hs.ho_ten.charAt(0);
                let badges = '';
                if (rank === 1) badges = '<span class="badge">🥇</span>';
                else if (rank === 2) badges = '<span class="badge">🥈</span>';
                else if (rank === 3) badges = '<span class="badge">🥉</span>';

                html += `
                    <div class="rank-item">
                        <div class="rank-position">${rank <= 3 ? '' : rank}</div>
                        <div class="rank-avatar">${initial}</div>
                        <div class="rank-info">
                            <div class="rank-name">${hs.ho_ten}</div>
                            <div class="rank-class">${hs.ten_lop}</div>
                        </div>
                        <div class="rank-badges">${badges}</div>
                        <div class="rank-score">${Math.round(hs.diem_xep_hang || 0)}</div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }
    </script>
</body>
</html>
