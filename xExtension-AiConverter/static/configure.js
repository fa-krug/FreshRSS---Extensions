/**
 * AiConverter Extension - Configuration UI Script
 *
 * This script manages the feed reload functionality and automatic background processing
 * for the AiConverter extension.
 * Features:
 * - AJAX-based feed reload functionality
 * - Automatic background processing of pending articles
 * - Progress tracking and status updates
 * - Event delegation for dynamic elements
 */
(function () {
    'use strict';

    let processingInterval = null;
    let isProcessing = false;

    /**
     * Reload a feed from the backend
     *
     * This function makes an AJAX call to truncate all feed entries and trigger a fresh reload.
     * Useful when AI conversion settings have been modified and need to be applied to existing entries.
     *
     * @param {string|number} feedId - The ID of the feed to reload
     * @param {HTMLElement} button - The reload button element (for disabling during request)
     */
    function reloadFeedBackend(feedId, button) {
        // Confirm with user before destructive operation
        if (!confirm('Are you sure you want to delete all entries and reload this feed? The AI will process all new articles.')) return;

        // Disable button during request
        button.disabled = true;
        button.textContent = 'Reloading...';

        // Get CSRF token from form
        const csrf = document.querySelector('input[name="_csrf"]').value;

        // Make AJAX request to reload endpoint
        fetch(window.location.pathname + '?c=aiconverter&a=reload&feed_id=' + encodeURIComponent(feedId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'feed_id=' + encodeURIComponent(feedId) + '&_csrf=' + encodeURIComponent(csrf)
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                button.disabled = false;
                button.textContent = 'Reload Feed';
                if (data.success) {
                    alert('Feed reloaded successfully! Redirecting to home page...');
                    // Redirect to home page after successful reload
                    window.location.replace('/');
                } else {
                    alert('Error: ' + (data.error || 'Failed to reload feed.'));
                }
            })
            .catch(function (err) {
                button.disabled = false;
                button.textContent = 'Reload Feed';
                alert('Error: ' + err);
            });
    }

    /**
     * Update pending articles count
     */
    function updatePendingCount() {
        const countElement = document.getElementById('pending-count');
        const statusElement = document.getElementById('pending-status');

        if (!countElement || !statusElement) return;

        fetch(window.location.pathname + '?c=aiconverter&a=countPending', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_csrf=' + encodeURIComponent(document.querySelector('input[name="_csrf"]').value)
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.count !== undefined) {
                    countElement.textContent = data.count;
                    if (data.count > 0) {
                        statusElement.style.display = 'block';
                    } else {
                        statusElement.style.display = 'none';
                    }
                }
            })
            .catch(function (err) {
                console.error('Failed to update pending count:', err);
            });
    }

    /**
     * Process pending articles in background
     */
    function processPendingArticles(batchSize) {
        if (isProcessing) {
            console.log('Already processing...');
            return;
        }

        isProcessing = true;
        const statusElement = document.getElementById('processing-status');
        const processBtn = document.getElementById('process-pending-btn');

        if (statusElement) {
            statusElement.textContent = 'Processing...';
            statusElement.style.color = '#0066cc';
        }
        if (processBtn) {
            processBtn.disabled = true;
        }

        const csrf = document.querySelector('input[name="_csrf"]').value;

        fetch(window.location.pathname + '?c=aiconverter&a=process&batch_size=' + batchSize, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_csrf=' + encodeURIComponent(csrf) + '&batch_size=' + batchSize
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                isProcessing = false;
                if (processBtn) {
                    processBtn.disabled = false;
                }

                if (data.success) {
                    if (statusElement) {
                        statusElement.textContent = 'Processed: ' + data.processed + ' | Errors: ' + data.errors + (data.remaining > 0 ? ' | Remaining: ' + data.remaining : '');
                        statusElement.style.color = '#008800';
                    }
                    // Update count after processing
                    setTimeout(updatePendingCount, 1000);

                    // Continue processing if there are more items
                    if (data.remaining > 0) {
                        setTimeout(function () {
                            processPendingArticles(batchSize);
                        }, 2000); // Wait 2 seconds between batches
                    }
                } else {
                    if (statusElement) {
                        statusElement.textContent = 'Error: ' + (data.error || 'Unknown error');
                        statusElement.style.color = '#cc0000';
                    }
                }
            })
            .catch(function (err) {
                isProcessing = false;
                if (processBtn) {
                    processBtn.disabled = false;
                }
                if (statusElement) {
                    statusElement.textContent = 'Error: ' + err;
                    statusElement.style.color = '#cc0000';
                }
                console.error('Processing error:', err);
            });
    }

    /**
     * Check and process pending articles
     */
    function checkAndProcess() {
        const processingMode = document.getElementById('processing_mode');

        if (processingMode && processingMode.value === 'background') {
            updatePendingCount();

            // Wait a bit for count to update, then auto-process if needed
            setTimeout(function() {
                const countElement = document.getElementById('pending-count');
                if (countElement && parseInt(countElement.textContent) > 0 && !isProcessing) {
                    processPendingArticles(5); // Process 5 at a time automatically
                }
            }, 500);
        }
    }

    // Event delegation: Handle button clicks
    document.addEventListener('click', function (e) {
        // Handle "Reload Feed" button clicks
        if (e.target.classList.contains('btn-reload-feed')) {
            var feedId = e.target.getAttribute('data-feed-id');
            reloadFeedBackend(feedId, e.target);
        }

        // Handle "Process Pending Articles" button clicks
        if (e.target.id === 'process-pending-btn') {
            processPendingArticles(10); // Process 10 at a time when manually triggered
        }
    });

    // Handle processing mode changes
    document.addEventListener('change', function (e) {
        if (e.target.id === 'processing_mode') {
            checkAndProcess();
        }
    });

    // Initialize when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            checkAndProcess();
        });
    } else {
        checkAndProcess();
    }
})();
