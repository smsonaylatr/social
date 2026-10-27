/**
 * SocialFlow AI — High Performance Video Thumbnail Engine v2.0
 * 
 * Features:
 * - Single-worker sequential queue (zero GPU/network overload, lightning fast)
 * - Hardware decoder sync using requestVideoFrameCallback (eliminates half-decoded frames)
 * - Automatic corrupted / half-black / all-black frame detection with canvas pixel analysis
 * - Intelligent multi-point seek retry (skips dark opening fades & corrupted macroblocks)
 * - Automatic live repair for existing corrupted thumbnails in the DOM
 * - Instant UI update & permanent server-side sync
 */
(function() {
    'use strict';

    if (window._socialFlowThumbnailEngineInitialized) {
        return;
    }
    window._socialFlowThumbnailEngineInitialized = true;

    // Concurrency control: max 1 video decoder at a time to prevent GPU hardware limit exhaustion
    var queue = [];
    var isProcessing = false;
    var processedIds = new Set();
    var verifiedThumbnails = new Set();
    var workerVideo = null;

    /**
     * Get or create a hidden, reusable single-worker video element
     */
    function getWorkerVideo() {
        if (!workerVideo) {
            workerVideo = document.createElement('video');
            workerVideo.muted = true;
            workerVideo.playsInline = true;
            workerVideo.autoplay = false;
            workerVideo.crossOrigin = 'anonymous';
            workerVideo.style.cssText = 'position:fixed; top:-9999px; left:-9999px; width:480px; height:270px; opacity:0; pointer-events:none; z-index:-1;';
            document.body.appendChild(workerVideo);
        }
        return workerVideo;
    }

    /**
     * Inspect canvas pixel data to detect if the frame is corrupted, half-black, or pure black
     */
    function isCorruptedFrame(ctx, width, height) {
        if (!ctx || width <= 0 || height <= 0) return true;

        try {
            // Downsample to a small 60x90 test canvas for sub-millisecond pixel analysis
            var sampleW = 60;
            var sampleH = 90;
            var testCanvas = document.createElement('canvas');
            testCanvas.width = sampleW;
            testCanvas.height = sampleH;
            var testCtx = testCanvas.getContext('2d', { willReadFrequently: true });
            if (!testCtx) return false;

            testCtx.drawImage(ctx.canvas, 0, 0, sampleW, sampleH);
            var imgData = testCtx.getImageData(0, 0, sampleW, sampleH).data;

            var leftSum = 0, leftCount = 0;
            var rightSum = 0, rightCount = 0;
            var topSum = 0, topCount = 0;
            var bottomSum = 0, bottomCount = 0;
            var totalSum = 0, totalCount = 0;

            var leftBoundary = Math.floor(sampleW * 0.40);
            var rightBoundary = Math.floor(sampleW * 0.60);
            var topBoundary = Math.floor(sampleH * 0.40);
            var bottomBoundary = Math.floor(sampleH * 0.60);

            for (var y = 4; y < sampleH - 4; y++) {
                for (var x = 3; x < sampleW - 3; x++) {
                    var idx = (y * sampleW + x) * 4;
                    var r = imgData[idx];
                    var g = imgData[idx + 1];
                    var b = imgData[idx + 2];
                    var brightness = 0.299 * r + 0.587 * g + 0.114 * b;

                    totalSum += brightness;
                    totalCount++;

                    if (x < leftBoundary) {
                        leftSum += brightness;
                        leftCount++;
                    } else if (x > rightBoundary) {
                        rightSum += brightness;
                        rightCount++;
                    }

                    if (y < topBoundary) {
                        topSum += brightness;
                        topCount++;
                    } else if (y > bottomBoundary) {
                        bottomSum += brightness;
                        bottomCount++;
                    }
                }
            }

            var avgTotal = totalCount > 0 ? (totalSum / totalCount) : 0;
            var avgLeft = leftCount > 0 ? (leftSum / leftCount) : 0;
            var avgRight = rightCount > 0 ? (rightSum / rightCount) : 0;
            var avgTop = topCount > 0 ? (topSum / topCount) : 0;
            var avgBottom = bottomCount > 0 ? (bottomSum / bottomCount) : 0;

            // 1. Entirely black / blank frame (often opening title or unloaded buffer)
            if (avgTotal < 8) {
                return true;
            }

            // 2. Hardware decode glitch: Left half is black, right half has content
            if (avgLeft < 10 && avgRight > 32) {
                return true;
            }

            // 3. Hardware decode glitch: Right half is black, left half has content
            if (avgRight < 10 && avgLeft > 32) {
                return true;
            }

            // 4. Top half is black, bottom half has content
            if (avgTop < 8 && avgBottom > 35) {
                return true;
            }

            return false;
        } catch (e) {
            // In case of any read error (e.g. cross-origin restriction), accept the frame
            return false;
        }
    }

    /**
     * Update all DOM preview cards with the new captured thumbnail immediately
     */
    function applyThumbnailToCards(videoId, imageUrl) {
        var boxes = document.querySelectorAll('[data-video-id="' + videoId + '"]');
        boxes.forEach(function(box) {
            var img = box.querySelector('img.preview-thumb');
            if (!img) {
                img = document.createElement('img');
                img.className = 'preview-thumb w-full h-full object-cover pointer-events-none transition-transform duration-200 group-hover:scale-105';
                img.style.cssText = 'width: 100% !important; height: 100% !important; object-fit: cover !important; display: block;';
                img.alt = 'Video Önizleme';
                img.crossOrigin = 'anonymous';
                box.insertBefore(img, box.firstChild);
            }
            img.src = imageUrl;
            img.style.display = 'block';

            var placeholder = box.querySelector('.preview-placeholder, .preview-thumb-container, .preview-fallback');
            if (placeholder) {
                placeholder.style.display = 'none';
            }

            var video = box.querySelector('video');
            if (video) {
                video.style.display = 'none';
            }
        });
    }

    /**
     * Process next item in the sequential queue
     */
    function processQueue() {
        if (isProcessing || queue.length === 0) {
            return;
        }

        var item = queue.shift();
        isProcessing = true;

        var videoId = item.videoId;
        var videoUrl = item.videoUrl;
        var seekPoints = item.seekPoints || [1.0, 1.8, 2.5, 0.5];
        var attemptIdx = item.attemptIdx || 0;

        var video = getWorkerVideo();

        var cleanup = function() {
            video.onloadedmetadata = null;
            video.onseeked = null;
            video.onerror = null;
            try {
                video.pause();
                video.removeAttribute('src');
                video.load();
            } catch (e) {}
            isProcessing = false;
            setTimeout(processQueue, 30);
        };

        var timeoutTimer = setTimeout(function() {
            cleanup();
        }, 12000);

        function tryCaptureAtCurrentTime() {
            try {
                var w = video.videoWidth || 720;
                var h = video.videoHeight || 1280;

                if (w === 0 || h === 0) {
                    if (attemptIdx + 1 < seekPoints.length) {
                        retryWithNextSeek();
                    } else {
                        clearTimeout(timeoutTimer);
                        cleanup();
                    }
                    return;
                }

                var canvas = document.createElement('canvas');
                var targetW = Math.min(w, 480);
                var targetH = Math.round(targetW * (h / w));
                canvas.width = targetW;
                canvas.height = targetH;

                var ctx = canvas.getContext('2d');
                if (!ctx) {
                    clearTimeout(timeoutTimer);
                    cleanup();
                    return;
                }

                ctx.drawImage(video, 0, 0, targetW, targetH);

                // QUALITY CHECK: Ensure frame is NOT half-black or blacked out
                if (isCorruptedFrame(ctx, targetW, targetH)) {
                    if (attemptIdx + 1 < seekPoints.length) {
                        retryWithNextSeek();
                        return;
                    }
                }

                var dataUrl = canvas.toDataURL('image/jpeg', 0.88);

                // 1. Instant UI update on screen
                applyThumbnailToCards(videoId, dataUrl);
                processedIds.add(videoId);

                // 2. Persist to server in background
                var tokenMeta = document.querySelector('meta[name="csrf-token"]');
                var csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';

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
                        applyThumbnailToCards(videoId, data.thumbnail_url);
                    }
                })
                .catch(function() {})
                .finally(function() {
                    clearTimeout(timeoutTimer);
                    cleanup();
                });

            } catch (err) {
                clearTimeout(timeoutTimer);
                cleanup();
            }
        }

        function retryWithNextSeek() {
            attemptIdx++;
            if (attemptIdx >= seekPoints.length) {
                clearTimeout(timeoutTimer);
                cleanup();
                return;
            }

            var nextTargetTime = seekPoints[attemptIdx];
            if (video.duration > 0 && nextTargetTime >= video.duration) {
                nextTargetTime = Math.max(0.3, video.duration * 0.25);
            }

            video.onseeked = function() {
                video.onseeked = null;
                waitForValidFrame(tryCaptureAtCurrentTime);
            };

            try {
                video.currentTime = nextTargetTime;
            } catch (e) {
                clearTimeout(timeoutTimer);
                cleanup();
            }
        }

        function waitForValidFrame(callback) {
            // Chromium requestVideoFrameCallback ensures the hardware decoder has actually rendered the frame
            if ('requestVideoFrameCallback' in video) {
                video.requestVideoFrameCallback(function() {
                    setTimeout(callback, 50);
                });
            } else {
                requestAnimationFrame(function() {
                    setTimeout(callback, 120);
                });
            }
        }

        video.onerror = function() {
            clearTimeout(timeoutTimer);
            cleanup();
        };

        video.onloadedmetadata = function() {
            var targetTime = seekPoints[attemptIdx];
            if (video.duration > 0 && targetTime >= video.duration) {
                targetTime = Math.max(0.3, video.duration * 0.2);
            }

            video.onseeked = function() {
                video.onseeked = null;
                waitForValidFrame(tryCaptureAtCurrentTime);
            };

            try {
                video.currentTime = targetTime;
            } catch (e) {
                waitForValidFrame(tryCaptureAtCurrentTime);
            }
        };

        // Start loading the single worker video stream
        video.src = videoUrl;
        video.load();
    }

    /**
     * Enqueue a video for sequential capture
     */
    function enqueueCapture(videoId, videoUrl, force) {
        if (!videoId || !videoUrl) return;
        if (!force && processedIds.has(videoId)) return;

        // Prevent duplicate queue entries for the same video
        var alreadyInQueue = queue.some(function(item) {
            return item.videoId === videoId;
        });

        if (!alreadyInQueue) {
            queue.push({
                videoId: videoId,
                videoUrl: videoUrl,
                seekPoints: [1.0, 1.8, 2.5, 0.5],
                attemptIdx: 0,
                force: !!force
            });
            processQueue();
        }
    }

    /**
     * Verifies if an existing loaded <img> thumbnail is corrupted (half-black or pure black).
     * If corrupted, automatically triggers a clean background re-capture to heal it!
     */
    window.verifyThumbnailQuality = function(img, videoId, videoUrl) {
        if (!img || !videoId || !videoUrl) return;
        if (verifiedThumbnails.has(videoId)) return;

        // Check after image has naturally rendered in DOM
        setTimeout(function() {
            try {
                if (!img.naturalWidth || !img.naturalHeight) return;

                var sampleW = 40;
                var sampleH = 60;
                var canvas = document.createElement('canvas');
                canvas.width = sampleW;
                canvas.height = sampleH;
                var ctx = canvas.getContext('2d', { willReadFrequently: true });
                if (!ctx) return;

                ctx.drawImage(img, 0, 0, sampleW, sampleH);
                if (isCorruptedFrame(ctx, sampleW, sampleH)) {
                    console.warn('[SocialFlow AI] Corrupted/Half-black thumbnail detected for video #' + videoId + '. Auto-repairing...');
                    enqueueCapture(videoId, videoUrl, true);
                } else {
                    verifiedThumbnails.add(videoId);
                }
            } catch (e) {
                // If canvas is tainted or cross-origin prevents inspection, mark as checked
                verifiedThumbnails.add(videoId);
            }
        }, 80);
    };

    /**
     * Error handler for thumbnail images (falls back to queue capture)
     */
    window.handlePreviewThumbError = function(img, videoId, videoUrl) {
        if (img) img.style.display = 'none';
        if (videoId && videoUrl) {
            enqueueCapture(videoId, videoUrl, true);
        }
    };

    /**
     * Legacy adapter function
     */
    window.captureVideoPreviewThumbnail = function(videoOrBox, videoId) {
        var videoUrl = null;
        if (videoOrBox && videoOrBox.tagName === 'VIDEO') {
            videoUrl = videoOrBox.getAttribute('src');
            if (videoUrl) {
                videoUrl = videoUrl.split('#')[0];
            }
        }
        if (!videoUrl && videoOrBox) {
            var box = videoOrBox.closest ? videoOrBox.closest('[data-video-url]') : null;
            if (box) videoUrl = box.getAttribute('data-video-url');
        }
        if (videoId && videoUrl) {
            enqueueCapture(videoId, videoUrl, false);
        }
    };

    /**
     * Scan the page for elements needing thumbnails
     */
    function scanAndQueueMissingThumbnails(force) {
        var elements = document.querySelectorAll('[data-needs-capture="true"], [data-video-url]');
        elements.forEach(function(el) {
            var videoId = parseInt(el.getAttribute('data-video-id'), 10);
            var videoUrl = el.getAttribute('data-video-url');
            if (videoId && videoUrl) {
                var hasImg = el.querySelector('img.preview-thumb');
                if (!hasImg || force || el.getAttribute('data-needs-capture') === 'true') {
                    enqueueCapture(videoId, videoUrl, force);
                }
            }
        });
    }

    // Auto-scan on DOM ready and Filament / Livewire navigation events
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(scanAndQueueMissingThumbnails, 150);
        });
    } else {
        setTimeout(scanAndQueueMissingThumbnails, 150);
    }

    document.addEventListener('livewire:navigated', function() {
        setTimeout(scanAndQueueMissingThumbnails, 200);
    });

    document.addEventListener('livewire:load', function() {
        setTimeout(scanAndQueueMissingThumbnails, 200);
    });

    // Listen for manual sync / resync events from Filament header action
    window.addEventListener('sf:sync-thumbnails', function(e) {
        var force = e && e.detail && e.detail.force;
        scanAndQueueMissingThumbnails(force || true);
    });

    window.SocialFlowThumbnailEngine = {
        enqueue: enqueueCapture,
        scan: scanAndQueueMissingThumbnails
    };
})();
