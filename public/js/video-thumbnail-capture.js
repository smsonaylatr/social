/**
 * SocialFlow AI — Universal Video Thumbnail Auto-Capture & Real-time Sync Engine
 */
(function() {
    'use strict';

    if (window.captureVideoPreviewThumbnail) {
        return;
    }

    window.captureVideoPreviewThumbnail = function(video, videoId) {
        if (!video || !videoId) return;

        // Skip if currently processing or already captured
        if (video.dataset.capturing === 'true' || video.dataset.captured === 'true') {
            return;
        }

        video.dataset.capturing = 'true';

        function doCapture() {
            try {
                const w = video.videoWidth || 720;
                const h = video.videoHeight || 1280;

                if (w === 0 || h === 0) {
                    video.dataset.capturing = 'false';
                    return;
                }

                const canvas = document.createElement('canvas');
                const targetW = Math.min(w, 480);
                const targetH = Math.round(targetW * (h / w));
                canvas.width = targetW;
                canvas.height = targetH;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    video.dataset.capturing = 'false';
                    return;
                }

                ctx.drawImage(video, 0, 0, targetW, targetH);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

                video.dataset.captured = 'true';
                video.dataset.capturing = 'false';

                // 1. INSTANT UI UPDATE: Replace video with the captured image in all matching cards on this page immediately!
                const boxes = document.querySelectorAll('[data-video-id="' + videoId + '"]');
                boxes.forEach(function(box) {
                    let img = box.querySelector('img.preview-thumb');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'preview-thumb w-full h-full object-cover pointer-events-none transition-transform duration-200 group-hover:scale-105';
                        img.style.cssText = 'width: 100% !important; height: 100% !important; object-fit: cover !important; display: block;';
                        img.alt = 'Video Önizleme';
                        box.insertBefore(img, box.firstChild);
                    } else {
                        img.src = dataUrl;
                        img.style.display = 'block';
                    }

                    const v = box.querySelector('video');
                    if (v && v !== video) {
                        v.style.display = 'none';
                    } else if (v) {
                        v.style.display = 'none';
                    }

                    const fallback = box.querySelector('.preview-fallback');
                    if (fallback) {
                        fallback.style.display = 'none';
                    }
                });

                // 2. Persist to server in background
                const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

                fetch('/admin/videos/' + videoId + '/save-thumbnail', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        thumbnail: dataUrl,
                        duration: Math.round(video.duration || 0),
                        width: video.videoWidth || 0,
                        height: video.videoHeight || 0
                    })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.success && data.thumbnail_url) {
                        // After server saves it, update img src to the permanent static URL
                        boxes.forEach(function(box) {
                            const img = box.querySelector('img.preview-thumb');
                            if (img) {
                                img.src = data.thumbnail_url;
                            }
                        });
                    }
                })
                .catch(function(e) {
                    // Fail silently in background
                });

            } catch (err) {
                video.dataset.capturing = 'false';
            }
        }

        function seekAndCapture() {
            if (video.duration > 0.6 && video.currentTime < 0.2) {
                const targetTime = Math.min(1.0, Math.max(0.4, (video.duration || 2) / 3));
                video.currentTime = targetTime;
                video.addEventListener('seeked', function() {
                    setTimeout(doCapture, 60);
                }, { once: true });
            } else {
                setTimeout(doCapture, 60);
            }
        }

        if (video.readyState >= 2) {
            seekAndCapture();
        } else {
            video.addEventListener('loadeddata', function() {
                seekAndCapture();
            }, { once: true });
        }
    };
})();
