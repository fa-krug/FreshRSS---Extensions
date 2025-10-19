<?php
$replacements = FreshRSS_Context::$user_conf->replacer["replacements"] ?? array();

// Get all feeds
$feedDAO = new FreshRSS_FeedDAO();
$feeds = $feedDAO->listFeeds();
?>

    <form action="<?php echo _url('extension', 'configure', 'e', urlencode($this->getName())); ?>" method="post">
        <input type="hidden" name="_csrf" value="<?php echo FreshRSS_Auth::csrfToken(); ?>" />

        <div class="form-group">
            <div class="group-controls">
                <h3>Available Placeholders</h3>
                <ul>
                    <li><strong>{url}:</strong> The URL of the article (entry link)</li>
                    <li><strong>{feed_url}:</strong> The URL of the feed</li>
                    <li><strong>{title}:</strong> The title of the feed</li>
                </ul>
            </div>
        </div>

        <div class="form-group">
            <div class="group-controls">
                <h3>Regex Pattern Examples</h3>
                <ul>
                    <li><code>#pattern#</code> - Basic pattern with # delimiter</li>
                    <li><code>#pattern#i</code> - Case-insensitive pattern (i flag)</li>
                    <li><code>/pattern/</code> - Pattern with / delimiter</li>
                </ul>
            </div>
        </div>

        <div class="form-group">
            <div class="group-controls">
                <h3>Important Note</h3>
                <p>Replacements are applied ONLY to entry content, not to titles or subtitles</p>
            </div>
        </div>

        <?php if (empty($feeds)): ?>
            <div class="form-group">
                <div class="group-controls">
                    <p>No feeds configured. Please add a feed first.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($feeds as $feed): ?>
                <?php
                $feedId = $feed->id();
                $config = $replacements[$feedId] ?? array();
                $searchRegex = $config["search_regex"] ?? '';
                $replaceString = $config["replace_string"] ?? '';
                // Decode HTML entities for display
                $replaceStringDisplay = html_entity_decode($replaceString, ENT_QUOTES, 'UTF-8');
                ?>

                <fieldset class="form-group">
                    <legend><?php echo htmlspecialchars($feed->name()); ?></legend>

                    <input type="hidden" name="feed_ids[]" value="<?php echo $feedId; ?>">

                    <div class="form-group">
                        <label class="group-name" for="replacer_search_regex_<?php echo $feedId; ?>">Search Regex Pattern</label>
                        <div class="group-controls">
                            <input type="text" name="replacer_search_regex_<?php echo $feedId; ?>" id="replacer_search_regex_<?php echo $feedId; ?>" value="<?php echo $searchRegex; ?>" placeholder="e.g., #pattern#i">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="group-name" for="replacer_replace_string_<?php echo $feedId; ?>">Replace String</label>
                        <div class="group-controls">
                            <textarea name="replacer_replace_string_<?php echo $feedId; ?>" id="replacer_replace_string_<?php echo $feedId; ?>" placeholder="e.g., replacement text"><?php echo $replaceStringDisplay; ?></textarea>
                        </div>
                    </div>
                </fieldset>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="form-group form-actions">
            <div class="group-controls">
                <button type="submit" class="btn btn-important">Submit</button>
                <button type="reset" class="btn">Cancel</button>
            </div>
        </div>
    </form>

    <style>
        fieldset.form-group {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        fieldset.form-group legend {
            padding: 0 10px;
            font-weight: bold;
            margin-left: -10px;
        }

        textarea {
            width: 100%;
            min-height: 60px;
            font-family: monospace;
        }
    </style>

    root@ec0bbfa0f587:/var/www/FreshRSS/extensions/xExtension-Replacer# cat extension.php
<?php
class ReplacerExtension extends Minz_Extension {
    public static $feedUrl = '';
    public static $feedTitle = '';
    public static $entryUrl = '';
    public static $feedId = '';

    public function init() {
        self::$feedUrl = '';
        self::$feedTitle = '';
        self::$entryUrl = '';
        self::$feedId = '';
        $this->registerHook('entry_before_insert', array('ReplacerExtension', 'applyReplacementsToEntry'));
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $data = array();
            $data['replacements'] = array();

            // Get all feed IDs from POST data
            $feedIds = Minz_Request::param('feed_ids', array());

            foreach ($feedIds as $feedId) {
                $searchRegex = Minz_Request::param('replacer_search_regex_' . $feedId, '');
                $replaceString = Minz_Request::param('replacer_replace_string_' . $feedId, '');

                if ($searchRegex !== '' || $replaceString !== '') {
                    $data['replacements'][$feedId] = array(
                        'search_regex' => $searchRegex,
                        'replace_string' => $replaceString
                    );
                }
            }

            FreshRSS_Context::$user_conf->replacer = $data;
            FreshRSS_Context::$user_conf->save();
        }
    }

    public static function applyReplacementsToEntry($entry) {
        try {
            if (is_object($entry) === true) {
                // Get feed ID from entry
                if (method_exists($entry, 'feed') && is_object($entry->feed())) {
                    self::$feedId = $entry->feed()->id();
                    self::$feedUrl = $entry->feed()->url();
                    self::$feedTitle = $entry->feed()->name();
                }

                // Get entry URL (article link)
                if (method_exists($entry, 'link')) {
                    self::$entryUrl = $entry->link();
                }

                // Get replacement config for this feed
                $replacements = FreshRSS_Context::$user_conf->replacer["replacements"] ?? array();

                if (!isset($replacements[self::$feedId])) {
                    return $entry;
                }

                $config = $replacements[self::$feedId];
                $searchRegex = $config["search_regex"] ?? '';
                $replaceString = $config["replace_string"] ?? '';

                if ($searchRegex == '' || $replaceString == '') {
                    return $entry;
                }

                // Validate regex pattern
                if (@preg_match($searchRegex, '') === false) {
                    Minz_Log::error('Replacer: Invalid regex pattern for feed ' . self::$feedId . ': ' . $searchRegex);
                    return $entry;
                }

                // Process ONLY entry content, NOT title
                if (method_exists($entry, 'content')) {
                    $newContent = $entry->content();
                    // Get the first match
                    if (preg_match($searchRegex, $newContent, $matches)) {
                        // Only replace the first match
                        $replacement = self::performReplacementEntry($matches);
                        $newContent = preg_replace($searchRegex, $replacement, $newContent, 1); // Limit to 1 replacement
                        $entry->_content($newContent);
                    }
                }
            }
            return $entry;
        } catch (Exception $e) {
            Minz_Log::error('Replacer: ' . $e->getMessage());
            return $entry;
        }
    }

    private static function performReplacementEntry(&$matches) {
        $replacements = FreshRSS_Context::$user_conf->replacer["replacements"] ?? array();
        $config = $replacements[self::$feedId] ?? array();
        $replaceString = $config["replace_string"] ?? '';

        // Decode HTML entities from stored string
        $replaceString = html_entity_decode($replaceString, ENT_QUOTES, 'UTF-8');

        // Replace placeholders
        $result = str_replace('{url}', self::$entryUrl, $replaceString);
        $result = str_replace('{feed_url}', self::$feedUrl, $result);
        $result = str_replace('{title}', self::$feedTitle, $result);

        return $result;
    }
}