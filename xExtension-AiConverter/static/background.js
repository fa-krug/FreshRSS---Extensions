/**
 * AiConverter Extension - Background Processing Auto-Trigger
 *
 * This script automatically triggers AI processing on page load.
 * It runs on all FreshRSS pages to ensure pending articles are processed
 * as soon as possible after feed updates.
 */
(function () {
    'use strict';

    let isProcessing = false;
    let processAttempted = false;

    /**
     * Trigger background processing
     */
    function triggerBackgroundProcessing() {
        // Only attempt once per page load
        if (processAttempted || isProcessing) {
            return;
        }

        processAttempted = true;
        isProcessing = true;

        // Build the processing URL
        const baseUrl = window.location.pathname.split('?')[0];
        const processingUrl = baseUrl + '?c=aiconverter&a=process&batch_size=5';

        fetch(processingUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'batch_size=5'
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                isProcessing = false;
                if (data.success && data.processed > 0) {
                    console.log('AiConverter: Processed ' + data.processed + ' articles in background');

                    // If there are more remaining, trigger another batch after a delay
                    if (data.remaining > 0) {
                        setTimeout(function () {
                            processAttempted = false;
                            triggerBackgroundProcessing();
                        }, 3000); // Wait 3 seconds between batches
                    }
                }
            })
            .catch(function (err) {
                isProcessing = false;
                // Silently fail - this runs in background
                console.debug('AiConverter: Background processing skipped or failed');
            });
    }

    // Trigger on page load after a short delay
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(triggerBackgroundProcessing, 1000);
        });
    } else {
        setTimeout(triggerBackgroundProcessing, 1000);
    }
})();
