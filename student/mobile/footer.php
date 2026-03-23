    </div><!-- .app -->

    <!-- Toast Container -->
    <div id="toast" class="toast"></div>

    <!-- Loading Overlay -->
    <div id="loading" class="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Đang tải...</div>
    </div>

    <script>
        var BASE_URL = '<?php echo BASE_URL; ?>';

        // Toast notification
        function showToast(message, type) {
            var toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + (type || '');
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.remove('show');
            }, 3000);
        }

        // Loading
        function showLoading() {
            document.getElementById('loading').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('loading').classList.remove('show');
        }

        // AJAX helper
        function ajax(url, method, data) {
            return new Promise(function(resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open(method || 'GET', url, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                resolve(JSON.parse(xhr.responseText));
                            } catch (e) {
                                resolve(xhr.responseText);
                            }
                        } else {
                            reject(xhr.statusText);
                        }
                    }
                };
                if (data) {
                    var params = [];
                    for (var key in data) {
                        params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
                    }
                    xhr.send(params.join('&'));
                } else {
                    xhr.send();
                }
            });
        }

        // Format time
        function formatTime(seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }
    </script>
    <?php if (isset($extraScripts)) echo $extraScripts; ?>
</body>
</html>
