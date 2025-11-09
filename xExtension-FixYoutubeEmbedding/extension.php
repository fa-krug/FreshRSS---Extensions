<?php

declare(strict_types=1);

final class FixYoutubeEmbeddingExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		// Hook into the pre-insert phase of an entry
		Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'beforeInsert']);
		Minz_Log::notice('FixYoutubeEmbedding extension initialized');
	}

	/**
	 * Hook: called before inserting an entry into the database.
	 * Replaces YouTube iframe embeds with thumbnail images for enabled feeds.
	 *
	 * @param FreshRSS_Entry $entry The entry to process
	 * @return FreshRSS_Entry The modified entry
	 */
	public function beforeInsert($entry) {
		// Load configuration (user-level)
		$enabledFeeds = $this->getUserConfigurationValue('enabled_feeds', []);
		if (!is_array($enabledFeeds)) {
			$enabledFeeds = [];
		}

		// Per-feed control based on enabled_feeds
		$feedId = method_exists($entry, 'feedId') ? (int)$entry->feedId() : 0;
		$apply = ($feedId > 0) && !empty($enabledFeeds[$feedId]);
		if (!$apply) {
			return $entry; // Skip: feed not enabled for YouTube embed fixing
		}

		// Process entry content
		if (method_exists($entry, 'content')) {
			$content = $entry->content();
			$newContent = $this->replaceYoutubeEmbeds($content);
			if ($newContent !== $content) {
				$entry->_content($newContent);
				// Count how many replacements were made
				$replacementCount = substr_count($newContent, 'i.ytimg.com/vi/') - substr_count($content, 'i.ytimg.com/vi/');
				Minz_Log::notice('FixYoutubeEmbedding: Replaced ' . $replacementCount . ' YouTube iframe(s) with thumbnail(s) for feed ID ' . $feedId);
			}
		}

		return $entry;
	}

	/**
	 * Replace YouTube iframe embeds with thumbnail images
	 *
	 * Converts YouTube iframe embeds into clickable thumbnail images that link to the video.
	 * This provides a lighter-weight alternative to embedded video players.
	 *
	 * @param string $content The entry content to process
	 * @return string The content with YouTube iframes replaced by thumbnails
	 */
	private function replaceYoutubeEmbeds(string $content): string {
		// Regex pattern to match YouTube iframes with video IDs
		// Matches both youtube.com/embed/VIDEO_ID and youtube-nocookie.com/embed/VIDEO_ID
		// Video IDs are exactly 11 characters: alphanumeric, underscore, or hyphen
		$pattern = '#<iframe[^>]*src=["\']https?://(?:www\.)?(?:youtube\.com|youtube-nocookie\.com)/embed/([a-zA-Z0-9_-]{11})[^"\']*["\'][^>]*>.*?</iframe>#is';

		// Replace with clickable thumbnail image linking to YouTube
		$replacement = '<a href="https://www.youtube.com/watch?v=$1"><figure><img src="https://i.ytimg.com/vi/$1/maxresdefault.jpg"></figure></a>';

		return preg_replace($pattern, $replacement, $content);
	}

	#[\Override]
	public function handleConfigureAction(): void {
		parent::handleConfigureAction();

		if (Minz_Request::isPost()) {
			// CSRF is handled by the controller; just read and persist settings
			// enabled_feeds is an associative array: [feedId => '1'] for checked items
			$enabledFeedsRaw = Minz_Request::paramArray('enabled_feeds', plaintext: true);
			$enabledFeeds = [];
			foreach ($enabledFeedsRaw as $k => $v) {
				if (is_string($k) && ctype_digit($k)) {
					$enabledFeeds[(int)$k] = true;
				} elseif (is_int($k)) {
					$enabledFeeds[$k] = true;
				}
			}

			$this->setUserConfiguration([
				'enabled_feeds' => $enabledFeeds,
			]);

			Minz_Log::notice('FixYoutubeEmbedding: Configuration saved - ' . count($enabledFeeds) . ' feed(s) enabled');
		}
	}

}
