<?php
class FreshExtension_replacer_Controller extends Minz_ActionController {
    public function reloadAction() {
        // Disable view rendering for AJAX
        Minz_View::_param('layout', false);
        header('Content-Type: application/json');

        if (!Minz_Request::isPost()) {
            echo json_encode(['error' => 'POST method required']);
            exit();
        }
        
        $feedId = Minz_Request::paramInt('feed_id');
        if (!$feedId) {
            echo json_encode(['error' => 'Missing or invalid feed_id']);
            exit();
        }

        try {
            // Get current user
            $username = Minz_User::name();
            if (!$username) {
                echo json_encode(['error' => 'Not logged in']);
                exit();
            }

            // Check if feed exists and is accessible
            $feedDAO = FreshRSS_Factory::createFeedDAO();
            $feed = $feedDAO->searchById($feedId);
            if (!$feed) {
                echo json_encode(['error' => 'Feed not found']);
                exit();
            }

            // Clear entries
		    $feedDAO->truncate($feedId);
            
            // Trigger a refresh of the feed
            FreshRSS_feed_Controller::actualizeFeedsAndCommit($feedId);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            Minz_Log::error('Feed reload error: ' . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit();
    }
}