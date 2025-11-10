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
}
