(function () {
    'use strict';

    function addRule(feedId) {
        var container = document.getElementById('rules_container_' + feedId);
        var ruleItems = container.querySelectorAll('.rule-item');
        var ruleNumber = ruleItems.length + 1;

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

        container.appendChild(newRule);
        updateRuleNumbers(feedId);
    }

    function removeRule(button) {
        var ruleItem = button.closest('.rule-item');
        var container = ruleItem.closest('.rules-container');
        var feedId = container.closest('.feed-rules').getAttribute('data-feed-id');

        ruleItem.remove();
        updateRuleNumbers(feedId);
    }

    function updateRuleNumbers(feedId) {
        var container = document.getElementById('rules_container_' + feedId);
        var ruleItems = container.querySelectorAll('.rule-item');

        ruleItems.forEach(function (item, index) {
            var ruleNumber = item.querySelector('.rule-number');
            ruleNumber.textContent = 'Rule ' + (index + 1);
        });
    }


  // Handle "Add Rule" button clicks
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-add-rule')) {
      var feedId = e.target.getAttribute('data-feed-id');
      addRule(feedId);
    }
    // Handle "Reload Feed" button clicks
    if (e.target.classList.contains('btn-reload-feed')) {
      var feedId = e.target.getAttribute('data-feed-id');
      reloadFeedBackend(feedId, e.target);
    }
  });

  // Reload Feed: call backend to truncate and reload feed
  function reloadFeedBackend(feedId, button) {
    if (!confirm('Are you sure you want to delete all entries and reload this feed?')) return;
    button.disabled = true;
    const csrf = document.querySelector('input[name="_csrf"]').value;
    fetch(window.location.pathname + '?c=replacer&a=reload&feed_id=' + encodeURIComponent(feedId), {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'feed_id=' + encodeURIComponent(feedId) + '&_csrf=' + encodeURIComponent(csrf)
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        button.disabled = false;
        if (data.success) {
          window.location.replace('/');
        } else {
          alert('Error: ' + (data.error || 'Failed to reload feed.'));
        }
      })
      .catch(function (err) {
        button.disabled = false;
        alert('Error: ' + err);
      });
  }

    // Handle "Remove" button clicks
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove-rule')) {
            removeRule(e.target);
        }
    });
})();
