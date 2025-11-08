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

        // Register our controller
        $this->registerController('replacer');

        // Load CSS and JS for configuration page
        if (Minz_Request::controllerName() === 'extension') {
            Minz_View::appendScript($this->getFileUrl('configure.js'));
        }
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $data = array();
            $data['replacements'] = array();

            // Get all feed IDs from POST data
            $feedIds = Minz_Request::param('feed_ids', array());

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

                        // Only add rules that have at least a pattern
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

                $rules = $replacements[self::$feedId];

                // If old format (single rule), convert to array
                if (isset($rules["search_regex"])) {
                    $rules = array($rules);
                }

                if (!is_array($rules) || empty($rules)) {
                    return $entry;
                }

                // Process ONLY entry content, NOT title
                if (method_exists($entry, 'content')) {
                    $newContent = $entry->content();

                    // Apply each rule in order
                    foreach ($rules as $rule) {
                        $searchRegex = $rule["search_regex"] ?? '';
                        $replaceString = $rule["replace_string"] ?? '';

                        if ($searchRegex === '') {
                            continue;
                        }

                        // Validate regex pattern
                        if (@preg_match($searchRegex, '') === false) {
                            Minz_Log::error('Replacer: Invalid regex pattern for feed ' . self::$feedId . ': ' . $searchRegex);
                            continue;
                        }

                        // Decode HTML entities from stored string
                        $replaceString = html_entity_decode($replaceString, ENT_QUOTES, 'UTF-8');

                        // Replace placeholders
                        $replaceString = str_replace('{url}', self::$entryUrl, $replaceString);
                        $replaceString = str_replace('{feed_url}', self::$feedUrl, $replaceString);
                        $replaceString = str_replace('{title}', self::$feedTitle, $replaceString);

                        // Apply the replacement
                        FreshRSS_Log::info('Replacing');
                        FreshRSS_Log::info('Content: ' . $newContent);
                        FreshRSS_Log::info('Search: ' . $searchRegex);
                        FreshRSS_Log::info('Replace: ' . $replaceString);
                        $newContent = preg_replace($searchRegex, $replaceString, $newContent);
                    }
                    
                    $entry->_content($newContent);
                }
            }
            return $entry;
        } catch (Exception $e) {
            FreshRSS_Log::error('Replacer: ' . $e->getMessage());
            return $entry;
        }
    }

}
