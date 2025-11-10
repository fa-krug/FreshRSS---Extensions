<?php

/**
 * AiConverter Controller
 *
 * Handles AJAX operations for the AiConverter extension, specifically feed reloading.
 * This controller provides an endpoint to truncate feed entries and trigger a refresh.
 */
class FreshExtension_aiconverter_Controller extends Minz_ActionController {
    /**
     * AJAX action to reload a feed
     *
     * This action deletes all entries from a feed and triggers a fresh reload.
     * Useful when AI conversion settings have been modified and need to be applied to existing entries.
     *
     * Expected POST parameters:
     * - feed_id: The ID of the feed to reload
     * - _csrf: CSRF token for security
     *
     * @return void Outputs JSON response and exits
     */
    public function reloadAction() {
        // Disable view rendering for AJAX requests
        Minz_View::_param('layout', false);
        header('Content-Type: application/json');

        // Validate request method
        if (!Minz_Request::isPost()) {
            Minz_Log::warning('AiConverter: Feed reload attempted with non-POST request');
            echo json_encode(['error' => 'POST method required']);
            exit();
        }

        // Validate feed ID parameter
        $feedId = Minz_Request::paramInt('feed_id');
        if (!$feedId) {
            Minz_Log::warning('AiConverter: Feed reload attempted without valid feed_id');
            echo json_encode(['error' => 'Missing or invalid feed_id']);
            exit();
        }

        Minz_Log::notice('AiConverter: Starting feed reload for feed ID ' . $feedId);

        try {
            // Verify user is authenticated
            $username = Minz_User::name();
            if (!$username) {
                Minz_Log::warning('AiConverter: Feed reload attempted by unauthenticated user');
                echo json_encode(['error' => 'Not logged in']);
                exit();
            }

            // Verify feed exists and is accessible to the user
            $feedDAO = FreshRSS_Factory::createFeedDAO();
            $feed = $feedDAO->searchById($feedId);
            if (!$feed) {
                Minz_Log::warning('AiConverter: Feed reload failed - feed ID ' . $feedId . ' not found for user ' . $username);
                echo json_encode(['error' => 'Feed not found']);
                exit();
            }

            Minz_Log::notice('AiConverter: Truncating entries for feed ID ' . $feedId);

            // Clear all existing entries from the feed
            $feedDAO->truncate($feedId);

            Minz_Log::notice('AiConverter: Triggering feed refresh for feed ID ' . $feedId);

            // Trigger a fresh reload of the feed (will apply current AI conversion settings)
            FreshRSS_feed_Controller::actualizeFeedsAndCommit($feedId);

            Minz_Log::notice('AiConverter: Successfully reloaded feed ID ' . $feedId);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Feed reload error for feed ID ' . $feedId . ' - ' . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * AJAX action to process pending articles in background
     *
     * Processes articles marked with <!--AI_PENDING--> in batches
     *
     * Expected POST parameters:
     * - batch_size: Number of articles to process (default: 5)
     * - _csrf: CSRF token for security
     *
     * @return void Outputs JSON response and exits
     */
    public function processAction() {
        // Disable view rendering for AJAX requests
        Minz_View::_param('layout', false);
        header('Content-Type: application/json');

        // Validate request method
        if (!Minz_Request::isPost()) {
            echo json_encode(['error' => 'POST method required']);
            exit();
        }

        try {
            // Verify user is authenticated
            $username = Minz_User::name();
            if (!$username) {
                echo json_encode(['error' => 'Not logged in']);
                exit();
            }

            $batchSize = Minz_Request::paramInt('batch_size') ?: 5;

            // Get configuration
            $config = FreshRSS_Context::$user_conf->aiconverter ?? array();
            $apiEndpoint = $config['api_endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
            $apiToken = $config['api_token'] ?? '';
            $model = $config['model'] ?? 'gpt-4o-mini';
            $defaultPrompt = $config['default_prompt'] ?? '';
            $feedConfigs = $config['feed_configs'] ?? array();

            if (empty($apiToken)) {
                echo json_encode(['error' => 'No API token configured']);
                exit();
            }

            // Find entries with AI_PENDING marker
            $entryDAO = FreshRSS_Factory::createEntryDAO();
            $pendingEntries = $entryDAO->listWhere('a', 0, FreshRSS_Entry::STATE_ALL, null, '0', '0', 'id', 'DESC', '0', [], $batchSize * 3);

            $processed = 0;
            $errors = 0;
            $remaining = 0;

            foreach ($pendingEntries as $entry) {
                if ($processed >= $batchSize) {
                    $remaining++;
                    continue;
                }

                $content = $entry->content();

                // Check if entry has pending marker
                if (strpos($content, '<!--AI_PENDING-->') === false) {
                    continue;
                }

                $feed = $entry->feed(false);
                $feedId = is_object($feed) ? $feed->id() : $feed;

                // Check if feed is still enabled
                if (!isset($feedConfigs[$feedId]) || !($feedConfigs[$feedId]['enabled'] ?? false)) {
                    // Remove marker if feed is disabled
                    $cleanContent = str_replace('<!--AI_PENDING-->', '', $content);
                    AiConverterExtension::updateEntryWithRetryPublic($entryDAO, $entry, $cleanContent);
                    continue;
                }

                // Get prompt for this feed
                $prompt = $feedConfigs[$feedId]['custom_prompt'] ?? '';
                if (empty($prompt)) {
                    $prompt = $defaultPrompt;
                }

                if (empty($prompt)) {
                    continue;
                }

                // Remove marker from content
                $cleanContent = str_replace('<!--AI_PENDING-->', '', $content);

                // Prepare message
                $userMessage = $prompt . "\n\n" . "Article URL: " . $entry->link() . "\n" . "Article Title: " . $entry->title() . "\n\n" . "Content:\n" . $cleanContent;

                // Process with AI
                $aiResponse = AiConverterExtension::callAiApiPublic($apiEndpoint, $apiToken, $model, $userMessage);

                if ($aiResponse !== null) {
                    // Update entry with AI response
                    if (AiConverterExtension::updateEntryWithRetryPublic($entryDAO, $entry, $aiResponse)) {
                        $processed++;
                        Minz_Log::notice('AiConverter: Background processed entry ID ' . $entry->id());
                    } else {
                        $errors++;
                    }
                } else {
                    // Remove marker but keep original content
                    AiConverterExtension::updateEntryWithRetryPublic($entryDAO, $entry, $cleanContent);
                    $errors++;
                    Minz_Log::error('AiConverter: Failed to process entry ID ' . $entry->id());
                }
            }

            echo json_encode([
                'success' => true,
                'processed' => $processed,
                'errors' => $errors,
                'remaining' => $remaining
            ]);
        } catch (Exception $e) {
            Minz_Log::error('AiConverter: Background processing error - ' . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }

    /**
     * AJAX action to count pending articles
     *
     * @return void Outputs JSON response and exits
     */
    public function countPendingAction() {
        // Disable view rendering for AJAX requests
        Minz_View::_param('layout', false);
        header('Content-Type: application/json');

        try {
            // Verify user is authenticated
            $username = Minz_User::name();
            if (!$username) {
                echo json_encode(['error' => 'Not logged in']);
                exit();
            }

            $entryDAO = FreshRSS_Factory::createEntryDAO();
            $entries = $entryDAO->listWhere('a', 0, FreshRSS_Entry::STATE_ALL, null, '0', '0', 'id', 'DESC', '0', [], 1000);

            $count = 0;
            foreach ($entries as $entry) {
                if (strpos($entry->content(), '<!--AI_PENDING-->') !== false) {
                    $count++;
                }
            }

            echo json_encode(['count' => $count]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }
}
