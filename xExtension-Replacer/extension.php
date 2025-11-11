<?php

/**
 * ReplacerExtension - Replace content in RSS feeds using regex patterns
 *
 * This extension allows users to define regex-based find-and-replace rules
 * on a per-feed basis. Rules are applied sequentially to entry content before
 * insertion into the database. Supports dynamic placeholders like {url}, {feed_url}, and {title}.
 */
class ReplacerExtension extends Minz_Extension {
    /** @var string The URL of the feed being processed */
    public static $feedUrl = '';

    /** @var string The title/name of the feed being processed */
    public static $feedTitle = '';

    /** @var string The URL of the entry/article being processed */
    public static $entryUrl = '';

    /** @var string The ID of the feed being processed */
    public static $feedId = '';

    /**
     * Initialize the extension
     * Registers hooks and controllers, loads assets for configuration page
     *
     * @return void
     */
    public function init() {
        // Initialize static properties
        self::$feedUrl = '';
        self::$feedTitle = '';
        self::$entryUrl = '';
        self::$feedId = '';

        // Register hook to process entries before they are inserted into the database
        $this->registerHook('entry_before_insert', array('ReplacerExtension', 'applyReplacementsToEntry'));

        // Register our custom controller for AJAX operations (feed reload)
        $this->registerController('replacer');

        // Load JavaScript for configuration page UI (add/remove rules, feed reload)
        if (Minz_Request::controllerName() === 'extension') {
            Minz_View::appendScript($this->getFileUrl('configure.js'));
        }

        Minz_Log::notice('Replacer extension initialized');
    }

    /**
     * Handle configuration form submission
     * Processes and saves replacement rules for all feeds
     *
     * @return void
     */
    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $data = array();
            $data['replacements'] = array();

            // Get all feed IDs from POST data (submitted as hidden inputs)
            $feedIds = Minz_Request::param('feed_ids', array());
            Minz_Log::notice('Replacer: Processing configuration for ' . count($feedIds) . ' feeds');

            foreach ($feedIds as $feedId) {
                $regexPatterns = Minz_Request::param('replacer_search_regex_' . $feedId, array());
                $replaceStrings = Minz_Request::param('replacer_replace_string_' . $feedId, array());

                $rules = array();
                if (is_array($regexPatterns) && is_array($replaceStrings)) {
                    foreach ($regexPatterns as $index => $pattern) {
                        $replacement = $replaceStrings[$index] ?? '';

                        // Decode URL-encoded values coming from the POST (some clients encode them)
                        $pattern = urldecode($pattern);
                        $replacement = urldecode($replacement);

                        // Decode HTML entities (e.g. &lt; &gt;) so patterns like `<header>` are stored correctly
                        $pattern = html_entity_decode($pattern, ENT_QUOTES, 'UTF-8');
                        $replacement = html_entity_decode($replacement, ENT_QUOTES, 'UTF-8');

                        // Only add rules that have at least a pattern (replacement can be empty for deletion)
                        if (trim($pattern) !== '') {
                            $rules[] = array(
                                'search_regex' => $pattern,
                                'replace_string' => $replacement
                            );
                        }
                    }
                }

                if (!empty($rules)) {
                    $data['replacements'][$feedId] = $rules;
                    Minz_Log::notice('Replacer: Saved ' . count($rules) . ' rule(s) for feed ID ' . $feedId);
                }
            }

            FreshRSS_Context::$user_conf->replacer = $data;
            FreshRSS_Context::$user_conf->save();

            $totalRules = array_sum(array_map('count', $data['replacements']));
            Minz_Log::notice('Replacer: Configuration saved successfully with ' . $totalRules . ' total rules across ' . count($data['replacements']) . ' feeds');
        }
    }

    /**
     * Hook callback: Apply replacement rules to entry content before database insertion
     *
     * This method is called for every new entry before it's saved to the database.
     * It applies all configured replacement rules for the entry's feed in sequential order.
     * Supports dynamic placeholders: {url}, {feed_url}, {title}
     *
     * @param FreshRSS_Entry $entry The entry object to process
     * @return FreshRSS_Entry The modified entry object
     */
    public static function applyReplacementsToEntry($entry) {
        try {
            if (is_object($entry) === true) {
                // Extract feed metadata from entry object
                if (method_exists($entry, 'feed') && is_object($entry->feed())) {
                    self::$feedId = $entry->feed()->id();
                    self::$feedUrl = $entry->feed()->url();
                    self::$feedTitle = $entry->feed()->name();
                }

                // Get entry URL (article link) for placeholder replacement
                if (method_exists($entry, 'link')) {
                    self::$entryUrl = $entry->link();
                }

                // Get replacement configuration for this feed
                $replacements = FreshRSS_Context::$user_conf->replacer["replacements"] ?? array();

                // Skip processing if no rules configured for this feed
                if (!isset($replacements[self::$feedId])) {
                    return $entry;
                }

                $rules = $replacements[self::$feedId];

                // Backward compatibility: convert old single-rule format to array
                if (isset($rules["search_regex"])) {
                    $rules = array($rules);
                }

                if (!is_array($rules) || empty($rules)) {
                    return $entry;
                }

                // Process ONLY entry content, NOT title (to preserve original titles in UI)
                if (method_exists($entry, 'content')) {
                    $newContent = $entry->content();
                    $rulesApplied = 0;

                    // Apply each rule in sequential order (order matters for chained replacements)
                    foreach ($rules as $ruleIndex => $rule) {
                        $searchRegex = $rule["search_regex"] ?? '';
                        $replaceString = $rule["replace_string"] ?? '';

                        if ($searchRegex === '') {
                            continue;
                        }

                        // Validate regex pattern syntax before applying
                        if (@preg_match($searchRegex, '') === false) {
                            Minz_Log::error('Replacer: Invalid regex pattern for feed ' . self::$feedId . ' (rule #' . ($ruleIndex + 1) . '): ' . $searchRegex);
                            continue;
                        }

                        // Decode HTML entities from stored replacement string
                        $replaceString = html_entity_decode($replaceString, ENT_QUOTES, 'UTF-8');

                        // Replace dynamic placeholders with actual values
                        $replaceString = str_replace('{url}', self::$entryUrl, $replaceString);
                        $replaceString = str_replace('{feed_url}', self::$feedUrl, $replaceString);
                        $replaceString = str_replace('{title}', self::$feedTitle, $replaceString);

                        // Log the rule being applied
                        Minz_Log::debug('Replacer: Applying rule #' . ($ruleIndex + 1) . ' to feed ' . self::$feedId);
                        Minz_Log::debug('Replacer: Search Regex: ' . $searchRegex);
                        Minz_Log::debug('Replacer: Replace String: ' . $replaceString);

                        // Apply the regex replacement to content
                        // Note: For multiline matching, users should include the 's' modifier in their pattern
                        // e.g., /pattern/s or #pattern#s to make . match newlines
                        $contentBefore = $newContent;
                        $newContent = @preg_replace($searchRegex, $replaceString, $newContent);

                        // If preg_replace fails, log error and keep original content
                        if ($newContent === null) {
                            Minz_Log::error('Replacer: preg_replace failed for feed ' . self::$feedId . ' (rule #' . ($ruleIndex + 1) . ')');
                            $newContent = $contentBefore;
                        }

                        // Track if this rule actually changed the content
                        if ($contentBefore !== $newContent) {
                            $rulesApplied++;
                        }
                    }

                    // Update entry content if any rules were applied
                    if ($rulesApplied > 0) {
                        $entry->_content($newContent);
                        Minz_Log::notice('Replacer: Applied ' . $rulesApplied . ' rule(s) to entry from feed ' . self::$feedId);
                    }
                }
            }
            return $entry;
        } catch (Exception $e) {
            Minz_Log::error('Replacer: Unexpected error in applyReplacementsToEntry - ' . $e->getMessage());
            return $entry;
        }
    }


}
