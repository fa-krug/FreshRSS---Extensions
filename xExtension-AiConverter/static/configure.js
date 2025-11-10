/**
 * AiConverter Extension - Configuration UI Script
 *
 * This script manages the feed reload functionality for the AiConverter extension.
 * Features:
 * - AJAX-based feed reload functionality
 * - Event delegation for dynamic elements
 */
(function () {
    'use strict';

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
                if (data.success) {
                    // Redirect to home page after successful reload
                    window.location.replace('/i/?get=f_' + feedId);
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

    // Event delegation: Handle button clicks
    document.addEventListener('click', function (e) {
        // Handle "Reload Feed" button clicks
        if (e.target.classList.contains('btn-reload-feed')) {
            var feedId = e.target.getAttribute('data-feed-id');
            reloadFeedBackend(feedId, e.target);
        }
    });
})();
