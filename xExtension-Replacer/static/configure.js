/**
 * Replacer Extension - Configuration UI Script
 *
 * This script manages the dynamic UI for configuring replacement rules in the Replacer extension.
 * Features:
 * - Add/remove replacement rules dynamically
 * - Automatic rule numbering
 * - AJAX-based feed reload functionality
 * - Event delegation for dynamic elements
 */
(function () {
    'use strict';

    /**
     * Add a new replacement rule to a feed's rules list
     *
     * @param {string|number} feedId - The ID of the feed to add a rule for
     */
    function addRule(feedId) {
        var container = document.getElementById('rules_container_' + feedId);
        var ruleItems = container.querySelectorAll('.rule-item');
        var ruleNumber = ruleItems.length + 1;

        // Create new rule element with input fields
        var newRule = document.createElement('div');
        newRule.className = 'rule-item';
        newRule.innerHTML = `
           <div class="header">
             <span class="item rule-number">Rule <?php echo $index + 1; ?></span>
             <span class="item"></span>
             <span class="item"></span>
             <div class="item">
                <button type="button" class="btn btn-attention confirm btn-remove-rule">Remove</button>
             </div>
          </div>
          <div class="post">
            <div class="form-group">
              <label class="group-name">Search Regex Pattern</label>
              <div class="group-controls">
                <input type="text" name="replacer_search_regex_${feedId}[]" value="" placeholder="e.g., #pattern#i">
              </div>
            </div>

            <div class="form-group">
              <label class="group-name">Replace String</label>
              <div class="group-controls">
                <textarea name="replacer_replace_string_${feedId}[]" placeholder="e.g., replacement text"></textarea>
              </div>
            </div>
          </div>
        `;

        // Append the new rule to the container
        container.appendChild(newRule);

        // Update rule numbers to reflect the new rule
        updateRuleNumbers(feedId);
    }

    /**
     * Remove a replacement rule from the DOM
     *
     * @param {HTMLElement} button - The remove button that was clicked
     */
    function removeRule(button) {
        // Find the rule item element and its container
        var ruleItem = button.closest('.rule-item');
        var container = ruleItem.closest('.rules-container');
        var feedId = container.closest('.feed-rules').getAttribute('data-feed-id');

        // Remove the rule from the DOM
        ruleItem.remove();

        // Renumber remaining rules
        updateRuleNumbers(feedId);
    }

    /**
     * Update rule numbers for all rules in a feed
     *
     * This ensures rules are numbered sequentially (1, 2, 3, etc.)
     * even after rules are added or removed.
     *
     * @param {string|number} feedId - The ID of the feed to update rule numbers for
     */
    function updateRuleNumbers(feedId) {
        var container = document.getElementById('rules_container_' + feedId);
        var ruleItems = container.querySelectorAll('.rule-item');

        // Renumber all rules sequentially
        ruleItems.forEach(function (item, index) {
            var ruleNumber = item.querySelector('.rule-number');
            ruleNumber.textContent = 'Rule ' + (index + 1);
        });
    }

    /**
     * Reload a feed from the backend
     *
     * This function makes an AJAX call to truncate all feed entries and trigger a fresh reload.
     * Useful when replacement rules have been modified and need to be applied to existing entries.
     *
     * @param {string|number} feedId - The ID of the feed to reload
     * @param {HTMLElement} button - The reload button element (for disabling during request)
     */
    function reloadFeedBackend(feedId, button) {
        // Confirm with user before destructive operation
        if (!confirm('Are you sure you want to delete all entries and reload this feed?')) return;

        // Disable button during request
        button.disabled = true;

        // Get CSRF token from form
        const csrf = document.querySelector('input[name="_csrf"]').value;

        // Make AJAX request to reload endpoint
        fetch(window.location.pathname + '?c=replacer&a=reload&feed_id=' + encodeURIComponent(feedId), {
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
                alert('Error: ' + err);
            });
    }

      // Event delegation: Handle "Add Rule" and "Reload Feed" button clicks
    // Using event delegation allows handlers to work with dynamically added elements
    document.addEventListener('click', function (e) {
        // Handle "Add Rule" button clicks
        if (e.target.classList.contains('btn-add-rule')) {
            var feedId = e.target.getAttribute('data-feed-id');
            addRule(feedId);
        }

        // Handle "Reload Feed" button clicks
        if (e.target.classList.contains('btn-reload-feed-replacer')) {
            var feedId = e.target.getAttribute('data-feed-id');
            reloadFeedBackend(feedId, e.target);
        }

        // Handle "Remove Rule" button clicks
        if (e.target.classList.contains('btn-remove-rule')) {
            removeRule(e.target);
        }
    });
})();
