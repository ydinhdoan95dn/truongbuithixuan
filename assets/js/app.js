/**
 * ==============================================
 * MAIN JAVASCRIPT
 * Web App Học tập & Thi trực tuyến Tiểu học
 * Trường Bùi Thị Xuân
 * ==============================================
 */

// ==============================================
// UTILITY FUNCTIONS
// ==============================================

/**
 * Hiển thị Toast Notification
 */
function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icons = {
        success: '<i data-feather="check-circle"></i>',
        error: '<i data-feather="x-circle"></i>',
        warning: '<i data-feather="alert-triangle"></i>',
        info: '<i data-feather="info"></i>'
    };

    toast.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <span class="toast-message">${message}</span>
        <span class="toast-close" onclick="this.parentElement.remove()">
            <i data-feather="x"></i>
        </span>
    `;

    container.appendChild(toast);
    feather.replace();

    // Auto remove
    setTimeout(() => {
        toast.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Hiển thị Modal
 */
function showModal(content, options = {}) {
    const overlay = document.getElementById('modal-overlay');
    const body = document.getElementById('modal-body');

    body.innerHTML = content;
    overlay.classList.add('active');

    // Close on overlay click
    if (options.closeOnOverlay !== false) {
        overlay.onclick = function(e) {
            if (e.target === overlay) {
                closeModal();
            }
        };
    }

    // Close on ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
}

/**
 * Đóng Modal
 */
function closeModal() {
    const overlay = document.getElementById('modal-overlay');
    overlay.classList.remove('active');
}

/**
 * Hiển thị popup "Đang cập nhật"
 */
function showComingSoonPopup() {
    const content = `
        <div class="text-center">
            <div class="modal-icon">🚧</div>
            <h3 class="modal-title">Nội dung đang được cập nhật</h3>
            <p class="modal-text">Chúng mình đang chuẩn bị nội dung thật hay cho các bạn. Hãy quay lại sau nhé!</p>
            <button class="btn btn-primary" onclick="closeModal()">
                <i data-feather="thumbs-up"></i>
                Đã hiểu
            </button>
        </div>
    `;
    showModal(content);
    feather.replace();
}

/**
 * Loading overlay
 */
function showLoading() {
    document.getElementById('loading-overlay').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loading-overlay').classList.add('hidden');
}

/**
 * AJAX Request Helper
 */
function ajaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject({
                    status: xhr.status,
                    message: xhr.statusText
                });
            }
        };

        xhr.onerror = function() {
            reject({
                status: xhr.status,
                message: 'Network error'
            });
        };

        if (data) {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

/**
 * Format thời gian
 */
function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Tạo confetti effect
 */
function createConfetti() {
    const container = document.createElement('div');
    container.className = 'confetti';
    document.body.appendChild(container);

    const colors = ['#FF6B6B', '#4ECDC4', '#FFE66D', '#A78BFA', '#60A5FA', '#F472B6'];

    for (let i = 0; i < 50; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + '%';
        piece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        piece.style.animationDelay = Math.random() * 2 + 's';
        piece.style.animationDuration = (2 + Math.random() * 2) + 's';
        container.appendChild(piece);
    }

    // Remove after animation
    setTimeout(() => container.remove(), 5000);
}

// ==============================================
// EXAM SYSTEM
// ==============================================

class ExamSystem {
    constructor(config) {
        this.examId = config.examId;
        this.sessionToken = config.sessionToken;
        this.questions = config.questions || [];
        this.currentIndex = 0;
        this.timePerQuestion = config.timePerQuestion || 15;
        this.answers = {};
        this.timer = null;
        this.timeLeft = this.timePerQuestion;
        this.startTime = null;
        this.questionStartTime = null;

        this.init();
    }

    init() {
        this.startTime = new Date();
        this.renderQuestion();
        this.startTimer();

        // Chống refresh/thoát trang
        window.addEventListener('beforeunload', (e) => {
            e.preventDefault();
            e.returnValue = 'Bạn có chắc muốn thoát? Bài làm sẽ không được lưu!';
        });

        // Chống phím tắt
        document.addEventListener('keydown', (e) => {
            // Chặn F5, Ctrl+R
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                showToast('Không thể tải lại trang trong khi làm bài!', 'warning');
            }
        });
    }

    renderQuestion() {
        const question = this.questions[this.currentIndex];
        const container = document.getElementById('question-container');

        this.questionStartTime = new Date();

        // Update progress
        document.getElementById('current-question').textContent = this.currentIndex + 1;
        document.getElementById('progress-fill').style.width =
            ((this.currentIndex + 1) / this.questions.length * 100) + '%';

        // Render question
        let optionsHtml = '';
        const options = ['A', 'B', 'C', 'D'];
        const optionKeys = ['dap_an_a', 'dap_an_b', 'dap_an_c', 'dap_an_d'];

        options.forEach((opt, idx) => {
            const isSelected = this.answers[question.id] === opt;
            optionsHtml += `
                <div class="answer-option ${isSelected ? 'selected' : ''}"
                     onclick="exam.selectAnswer('${opt}')">
                    <span class="option-letter">${opt}</span>
                    <span class="option-text">${question[optionKeys[idx]]}</span>
                </div>
            `;
        });

        container.innerHTML = `
            <div class="question-card">
                <div class="question-text">${question.noi_dung}</div>
                ${question.hinh_anh ? `<img src="${question.hinh_anh}" class="question-image" alt="Hình minh họa">` : ''}
                <div class="answer-options">
                    ${optionsHtml}
                </div>
            </div>
        `;
    }

    selectAnswer(answer) {
        const question = this.questions[this.currentIndex];
        const timeSpent = Math.round((new Date() - this.questionStartTime) / 1000);

        this.answers[question.id] = answer;

        // Gửi đáp án ngay lập tức
        this.submitAnswer(question.id, answer, timeSpent);

        // Update UI
        document.querySelectorAll('.answer-option').forEach(opt => {
            opt.classList.remove('selected');
        });
        event.currentTarget.classList.add('selected');

        // Chuyển câu sau 0.5s
        setTimeout(() => this.nextQuestion(), 500);
    }

    submitAnswer(questionId, answer, timeSpent) {
        ajaxRequest(BASE_URL + '/api/submit_answer.php', 'POST', {
            session_token: this.sessionToken,
            question_id: questionId,
            answer: answer,
            time_spent: timeSpent
        }).catch(err => console.error('Error submitting answer:', err));
    }

    startTimer() {
        this.timeLeft = this.timePerQuestion;
        this.updateTimerDisplay();

        this.timer = setInterval(() => {
            this.timeLeft--;
            this.updateTimerDisplay();

            if (this.timeLeft <= 5) {
                document.getElementById('timer').classList.add('warning');
            }

            if (this.timeLeft <= 0) {
                this.handleTimeout();
            }
        }, 1000);
    }

    updateTimerDisplay() {
        document.getElementById('timer-display').textContent = formatTime(this.timeLeft);
    }

    handleTimeout() {
        const question = this.questions[this.currentIndex];

        // Nếu chưa chọn đáp án, gửi null
        if (!this.answers[question.id]) {
            this.submitAnswer(question.id, null, this.timePerQuestion);
        }

        this.nextQuestion();
    }

    nextQuestion() {
        clearInterval(this.timer);
        document.getElementById('timer').classList.remove('warning');

        if (this.currentIndex < this.questions.length - 1) {
            this.currentIndex++;
            this.renderQuestion();
            this.startTimer();
        } else {
            this.finishExam();
        }
    }

    finishExam() {
        window.removeEventListener('beforeunload', () => {});

        showLoading();

        ajaxRequest(BASE_URL + '/api/finish_exam.php', 'POST', {
            session_token: this.sessionToken
        }).then(result => {
            hideLoading();
            window.location.href = BASE_URL + '/student/result.php?session=' + this.sessionToken;
        }).catch(err => {
            hideLoading();
            showToast('Có lỗi xảy ra. Vui lòng thử lại!', 'error');
        });
    }
}

// Global exam instance
let exam = null;

// ==============================================
// DOCUMENT VIEWER
// ==============================================

function viewDocument(docId, driveId, type) {
    let embedUrl = '';

    if (driveId) {
        // Google Drive embed
        if (type === 'pdf') {
            embedUrl = `https://drive.google.com/file/d/${driveId}/preview`;
        } else if (type === 'word' || type === 'ppt') {
            embedUrl = `https://docs.google.com/viewer?srcid=${driveId}&pid=explorer&efh=false&a=v&chrome=false&embedded=true`;
        }
    }

    const content = `
        <div style="width: 100%; height: 70vh;">
            <iframe src="${embedUrl}"
                    style="width: 100%; height: 100%; border: none; border-radius: 8px;"
                    allow="autoplay">
            </iframe>
        </div>
    `;

    showModal(content);

    // Log view
    ajaxRequest(BASE_URL + '/api/log_view.php', 'POST', {
        document_id: docId
    }).catch(() => {});
}

// ==============================================
// LEADERBOARD
// ==============================================

function loadLeaderboard(type = 'tong', classId = null) {
    const container = document.getElementById('leaderboard-content');
    if (!container) return;

    container.innerHTML = '<div class="text-center"><div class="loader-icon" style="margin: 20px auto;"></div></div>';

    let url = BASE_URL + '/api/leaderboard.php?type=' + type;
    if (classId) url += '&class_id=' + classId;

    ajaxRequest(url).then(data => {
        if (data.success && data.rankings.length > 0) {
            let html = '<div class="rank-list">';

            data.rankings.forEach((student, index) => {
                const rank = index + 1;
                let badges = '';

                if (rank === 1) badges = '<span class="badge">🥇</span>';
                else if (rank === 2) badges = '<span class="badge">🥈</span>';
                else if (rank === 3) badges = '<span class="badge">🥉</span>';

                const initial = student.ho_ten.charAt(0).toUpperCase();

                html += `
                    <div class="rank-item">
                        <div class="rank-position">${rank <= 3 ? '' : rank}</div>
                        <div class="rank-avatar">
                            ${student.avatar ? `<img src="${BASE_URL}/uploads/avatars/${student.avatar}">` : initial}
                        </div>
                        <div class="rank-info">
                            <div class="rank-name">${student.ho_ten}</div>
                            <div class="rank-class">${student.ten_lop}</div>
                        </div>
                        <div class="rank-badges">${badges}</div>
                        <div class="rank-score">${Math.round(student.diem_xep_hang)}</div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📊</div><p class="empty-state-text">Chưa có dữ liệu xếp hạng</p></div>';
        }
    }).catch(() => {
        container.innerHTML = '<div class="empty-state text-center">Không thể tải dữ liệu</div>';
    });
}

function switchLeaderboardTab(btn, type) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadLeaderboard(type);
}

function switchClassTab(btn, classId) {
    document.querySelectorAll('.class-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    loadLeaderboard('tong', classId);
}

// ==============================================
// FORM VALIDATION
// ==============================================

function validateLoginForm(form) {
    const maHS = form.querySelector('[name="ma_hs"]').value.trim();
    const password = form.querySelector('[name="password"]').value;

    if (!maHS) {
        showToast('Vui lòng nhập mã học sinh!', 'warning');
        return false;
    }

    if (!password) {
        showToast('Vui lòng nhập mật khẩu!', 'warning');
        return false;
    }

    return true;
}

// ==============================================
// INITIALIZATION
// ==============================================

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Feather Icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Hide loading
    hideLoading();

    // Load leaderboard if exists
    if (document.getElementById('leaderboard-content')) {
        loadLeaderboard();
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});
