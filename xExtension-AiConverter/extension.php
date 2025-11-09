<?php

/**
 * AiConverterExtension - Convert RSS feed content using OpenAI-compatible APIs
 *
 * This extension allows users to process RSS feed content through an AI API
 * to extract and reformat article content. Users can configure a global API
 * endpoint and access token, set a default prompt, and enable/override settings
 * on a per-feed basis.
 */
class AiConverterExtension extends Minz_Extension {
    /** @var bool Track if background processing has been triggered this request */
    private static $processingTriggered = false;

    /**
     * Initialize the extension
     * Registers hooks and controllers, loads assets for configuration page
     *
     * @return void
     */
    public function init() {
        // Register hook to process entries before they are inserted into the database
        $this->registerHook('entry_before_insert', array('AiConverterExtension', 'processEntryWithAi'));

        // Register hook to auto-process pending articles after feed update
        $this->registerHook('freshrss_user_maintenance', array('AiConverterExtension', 'autoProcessPending'));

        // Register our custom controller for AJAX operations (feed reload)
        $this->registerController('aiconverter');

        // Load JavaScript for configuration page UI
        if (Minz_Request::controllerName() === 'extension') {
            Minz_View::appendScript($this->getFileUrl('configure.js'));
        }

        Minz_Log::notice('AiConverter extension initialized');
    }

    /**
     * Handle configuration form submission
     * Processes and saves global settings and per-feed configurations
     *
     * @return void
     */
    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            $data = array();

            // Save global settings
            $data['api_endpoint'] = Minz_Request::param('api_endpoint', 'https://api.openai.com/v1/chat/completions');
            $data['api_token'] = Minz_Request::param('api_token', '');
            $data['model'] = Minz_Request::param('model', 'gpt-4o-mini');
            $data['default_prompt'] = Minz_Request::param('default_prompt', '');
            $data['processing_mode'] = Minz_Request::param('processing_mode', 'background');

            Minz_Log::notice('AiConverter: Saving global settings');

            // Save per-feed settings
            $data['feed_configs'] = array();
            $feedIds = Minz_Request::param('feed_ids', array());

            foreach ($feedIds as $feedId) {
                $enabled = Minz_Request::param('aiconverter_enabled_' . $feedId, false);
                $customPrompt = Minz_Request::param('aiconverter_custom_prompt_' . $feedId, '');

                // Decode HTML entities
                $customPrompt = html_entity_decode($customPrompt, ENT_QUOTES, 'UTF-8');

                if ($enabled === 'on' || $enabled === '1') {
                    $data['feed_configs'][$feedId] = array(
                        'enabled' => true,
                        'custom_prompt' => trim($customPrompt)
                    );
                    Minz_Log::notice('AiConverter: Feed ' . $feedId . ' enabled with custom prompt: ' . (empty($customPrompt) ? 'No' : 'Yes'));
                }
            }

            FreshRSS_Context::$user_conf->aiconverter = $data;
            FreshRSS_Context::$user_conf->save();

            Minz_Log::notice('AiConverter: Configuration saved successfully with ' . count($data['feed_configs']) . ' enabled feeds');
        }
    }

    /**
     * Hook callback: Mark or process entry for AI conversion before database insertion
     *
     * This method is called for every new entry before it's saved to the database.
     * Depending on processing_mode setting:
     * - 'background': Adds a marker to content for later processing (fast, non-blocking)
     * - 'immediate': Processes entry with AI right away (slow, blocking)
     *
     * @param FreshRSS_Entry $entry The entry object to process
     * @return FreshRSS_Entry The modified entry object
     */
    public static function processEntryWithAi($entry) {
        try {
            if (!is_object($entry)) {
                return $entry;
            }

            // Get feed ID
            $feedId = null;
            if (method_exists($entry, 'feed') && is_object($entry->feed())) {
                $feedId = $entry->feed()->id();
            }

            if (!$feedId) {
                return $entry;
            }

            // Get configuration
            $config = FreshRSS_Context::$user_conf->aiconverter ?? array();
            $feedConfigs = $config['feed_configs'] ?? array();

            // Check if AI processing is enabled for this feed
            if (!isset($feedConfigs[$feedId]) || !($feedConfigs[$feedId]['enabled'] ?? false)) {
                return $entry;
            }

            $processingMode = $config['processing_mode'] ?? 'background';

            // Background mode: just mark for later processing
            if ($processingMode === 'background') {
                $content = method_exists($entry, 'content') ? $entry->content() : '';
                if (!empty($content) && strpos($content, '<!--AI_PENDING-->') === false) {
                    // Add invisible marker at the start of content
                    $entry->_content('<!--AI_PENDING-->' . $content);
                    Minz_Log::notice('AiConverter: Marked entry from feed ' . $feedId . ' for background processing');

                    // Trigger background processing asynchronously
                    self::triggerBackgroundProcessing();
                }
                return $entry;
            }

            // Immediate mode: process now (original behavior)
            Minz_Log::notice('AiConverter: Processing entry from feed ' . $feedId . ' immediately');

            // Get API settings
            $apiEndpoint = $config['api_endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
            $apiToken = $config['api_token'] ?? '';

            if (empty($apiToken)) {
                Minz_Log::warning('AiConverter: No API token configured, skipping processing');
                return $entry;
            }

            // Determine which prompt to use (custom or default)
            $prompt = $feedConfigs[$feedId]['custom_prompt'] ?? '';
            if (empty($prompt)) {
                $prompt = $config['default_prompt'] ?? '';
            }

            if (empty($prompt)) {
                Minz_Log::warning('AiConverter: No prompt configured for feed ' . $feedId);
                return $entry;
            }

            // Get entry content
            $content = method_exists($entry, 'content') ? $entry->content() : '';
            $entryUrl = method_exists($entry, 'link') ? $entry->link() : '';
            $entryTitle = method_exists($entry, 'title') ? $entry->title() : '';

            if (empty($content)) {
                Minz_Log::warning('AiConverter: Entry has no content, skipping');
                return $entry;
            }

            // Prepare the message for the AI
            $userMessage = $prompt . "\n\n" . "Article URL: " . $entryUrl . "\n" . "Article Title: " . $entryTitle . "\n\n" . "Content:\n" . $content;

            // Get model configuration
            $model = $config['model'] ?? 'gpt-4o-mini';

            // Call the AI API
            $aiResponse = self::callAiApi($apiEndpoint, $apiToken, $model, $userMessage);

            if ($aiResponse !== null) {
                // Replace entry content with AI response
                $entry->_content($aiResponse);
                Minz_Log::notice('AiConverter: Successfully processed entry from feed ' . $feedId);
            } else {
                Minz_Log::error('AiConverter: Failed to get response from AI API for feed ' . $feedId);
            }

            return $entry;
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Unexpected error in processEntryWithAi - ' . $e->getMessage());
            return $entry;
        }
    }

    /**
     * Call the OpenAI-compatible API with the provided message
     *
     * @param string $endpoint The API endpoint URL
     * @param string $token The API access token
     * @param string $model The model to use (e.g., 'gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo')
     * @param string $message The message to send to the AI
     * @return string|null The AI response content, or null on failure
     */
    private static function callAiApi($endpoint, $token, $model, $message) {
        try {
            // Prepare the request payload
            $payload = array(
                'model' => $model,
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $message
                    )
                ),
                'temperature' => 0.7
            );

            $jsonPayload = json_encode($payload);

            // Initialize cURL
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ));
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout

            Minz_Log::notice('AiConverter: Sending request to AI API');

            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Minz_Log::error('AiConverter: cURL error - ' . $error);
                return null;
            }

            if ($httpCode !== 200) {
                Minz_Log::error('AiConverter: API returned HTTP ' . $httpCode . ' - ' . substr($response, 0, 500));
                return null;
            }

            // Parse response
            $responseData = json_decode($response, true);

            if (!isset($responseData['choices'][0]['message']['content'])) {
                Minz_Log::error('AiConverter: Invalid API response format');
                return null;
            }

            $content = $responseData['choices'][0]['message']['content'];
            Minz_Log::notice('AiConverter: Received response from AI API (' . strlen($content) . ' characters)');

            // Strip markdown code block wrappers if present
            $content = self::stripCodeBlockWrapper($content);

            return $content;
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Error calling AI API - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Hook callback: Auto-process pending articles
     * This is called periodically by FreshRSS maintenance
     *
     * @return void
     */
    public static function autoProcessPending() {
        try {
            $config = FreshRSS_Context::$user_conf->aiconverter ?? array();
            $processingMode = $config['processing_mode'] ?? 'background';

            // Only auto-process in background mode
            if ($processingMode !== 'background') {
                return;
            }

            $apiEndpoint = $config['api_endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
            $apiToken = $config['api_token'] ?? '';
            $model = $config['model'] ?? 'gpt-4o-mini';
            $defaultPrompt = $config['default_prompt'] ?? '';
            $feedConfigs = $config['feed_configs'] ?? array();

            if (empty($apiToken)) {
                return;
            }

            // Find and process a few pending entries
            $entryDAO = FreshRSS_Factory::createEntryDAO();
            $entries = $entryDAO->listWhere('e', '', FreshRSS_Entry::STATE_ALL, 'DESC', 20);

            $processed = 0;
            $maxBatch = 3;

            foreach ($entries as $entry) {
                if ($processed >= $maxBatch) {
                    break;
                }

                $content = $entry->content();
                if (strpos($content, '<!--AI_PENDING-->') === false) {
                    continue;
                }

                $feedId = $entry->feed(false);

                // Check if feed is still enabled
                if (!isset($feedConfigs[$feedId]) || !($feedConfigs[$feedId]['enabled'] ?? false)) {
                    $cleanContent = str_replace('<!--AI_PENDING-->', '', $content);
                    $entryDAO->updateContent($entry->id(), $cleanContent);
                    continue;
                }

                // Get prompt
                $prompt = $feedConfigs[$feedId]['custom_prompt'] ?? '';
                if (empty($prompt)) {
                    $prompt = $defaultPrompt;
                }

                if (empty($prompt)) {
                    continue;
                }

                // Remove marker
                $cleanContent = str_replace('<!--AI_PENDING-->', '', $content);

                // Prepare message
                $userMessage = $prompt . "\n\n" . "Article URL: " . $entry->link() . "\n" . "Article Title: " . $entry->title() . "\n\n" . "Content:\n" . $cleanContent;

                // Process with AI
                $aiResponse = self::callAiApi($apiEndpoint, $apiToken, $model, $userMessage);

                if ($aiResponse !== null) {
                    $entryDAO->updateContent($entry->id(), $aiResponse);
                    $processed++;
                    Minz_Log::notice('AiConverter: Auto-processed entry ID ' . $entry->id());
                } else {
                    $entryDAO->updateContent($entry->id(), $cleanContent);
                    Minz_Log::error('AiConverter: Failed to auto-process entry ID ' . $entry->id());
                }
            }

            if ($processed > 0) {
                Minz_Log::notice('AiConverter: Auto-processed ' . $processed . ' pending articles');
            }
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Auto-processing error - ' . $e->getMessage());
        }
    }

    /**
     * Trigger background processing asynchronously
     * Processes pending articles after the response is sent to the client
     *
     * @return void
     */
    private static function triggerBackgroundProcessing() {
        // Only trigger once per request to avoid multiple processing
        if (self::$processingTriggered) {
            return;
        }

        self::$processingTriggered = true;

        try {
            // Register shutdown function to process in background
            register_shutdown_function(array('AiConverterExtension', 'processInBackground'));

            Minz_Log::notice('AiConverter: Scheduled background processing');
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Failed to schedule background processing - ' . $e->getMessage());
        }
    }

    /**
     * Process pending articles in background after response is sent
     * Called via register_shutdown_function
     *
     * @return void
     */
    public static function processInBackground() {
        try {
            // If fastcgi_finish_request is available, finish the response first
            // This allows the main request to complete while we continue processing
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // Ignore user abort so processing continues even if client disconnects
            @ignore_user_abort(true);
            @set_time_limit(300); // 5 minutes max for background processing

            // Get configuration
            $config = FreshRSS_Context::$user_conf->aiconverter ?? array();
            $apiEndpoint = $config['api_endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
            $apiToken = $config['api_token'] ?? '';
            $model = $config['model'] ?? 'gpt-4o-mini';
            $defaultPrompt = $config['default_prompt'] ?? '';
            $feedConfigs = $config['feed_configs'] ?? array();

            if (empty($apiToken)) {
                return;
            }

            // Find and process pending entries
            $entryDAO = FreshRSS_Factory::createEntryDAO();
            $entries = $entryDAO->listWhere('e', '', FreshRSS_Entry::STATE_ALL, 'DESC', 20);

            $processed = 0;
            $maxBatch = 5; // Process up to 5 articles per background task

            foreach ($entries as $entry) {
                if ($processed >= $maxBatch) {
                    break;
                }

                $content = $entry->content();
                if (strpos($content, '<!--AI_PENDING-->') === false) {
                    continue;
                }

                $feedId = $entry->feed(false);

                // Check if feed is still enabled
                if (!isset($feedConfigs[$feedId]) || !($feedConfigs[$feedId]['enabled'] ?? false)) {
                    $cleanContent = str_replace('<!--AI_PENDING-->', '', $content);
                    $entryDAO->updateContent($entry->id(), $cleanContent);
                    continue;
                }

                // Get prompt
                $prompt = $feedConfigs[$feedId]['custom_prompt'] ?? '';
                if (empty($prompt)) {
                    $prompt = $defaultPrompt;
                }

                if (empty($prompt)) {
                    continue;
                }

                // Remove marker
                $cleanContent = str_replace('<!--AI_PENDING-->', '', $content);

                // Prepare message
                $userMessage = $prompt . "\n\n" . "Article URL: " . $entry->link() . "\n" . "Article Title: " . $entry->title() . "\n\n" . "Content:\n" . $cleanContent;

                // Process with AI
                $aiResponse = self::callAiApi($apiEndpoint, $apiToken, $model, $userMessage);

                if ($aiResponse !== null) {
                    $entryDAO->updateContent($entry->id(), $aiResponse);
                    $processed++;
                    Minz_Log::notice('AiConverter: Background processed entry ID ' . $entry->id());
                } else {
                    // Remove marker but keep original content on error
                    $entryDAO->updateContent($entry->id(), $cleanContent);
                    Minz_Log::error('AiConverter: Failed to background process entry ID ' . $entry->id());
                }
            }

            if ($processed > 0) {
                Minz_Log::notice('AiConverter: Background task processed ' . $processed . ' articles');
            }
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Background processing error - ' . $e->getMessage());
        }
    }

    /**
     * Public wrapper for callAiApi to allow controller access
     *
     * @param string $endpoint The API endpoint URL
     * @param string $token The API access token
     * @param string $model The model to use
     * @param string $message The message to send to the AI
     * @return string|null The AI response content, or null on failure
     */
    public static function callAiApiPublic($endpoint, $token, $model, $message) {
        return self::callAiApi($endpoint, $token, $model, $message);
    }

    /**
     * Strip markdown code block wrappers from AI response
     *
     * AI models often return HTML content wrapped in markdown code blocks like:
     * ```html
     * <div>content</div>
     * ```
     *
     * This function removes these wrappers to get the raw HTML.
     *
     * @param string $content The content to clean
     * @return string The cleaned content
     */
    private static function stripCodeBlockWrapper($content) {
        $content = trim($content);

        // Check if content starts with markdown code block
        // Matches: ```html, ```HTML, ``` followed by optional language identifier
        if (preg_match('/^```(?:html|HTML)?\s*\n/s', $content)) {
            // Remove opening ```[lang] and closing ```
            $content = preg_replace('/^```(?:html|HTML)?\s*\n/s', '', $content);
            $content = preg_replace('/\n```\s*$/s', '', $content);
            $content = trim($content);
            Minz_Log::notice('AiConverter: Stripped markdown code block wrapper from response');
        }

        return $content;
    }
}
