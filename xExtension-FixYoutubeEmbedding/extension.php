<?php

declare(strict_types=1);

final class FixYoutubeEmbeddingExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		// Hook into the pre-insert phase of an entry
		Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'beforeInsert']);
	}

	/**
	 * Hook: called before inserting an entry into the database.
	 * @param FreshRSS_Entry $entry
	 * @return FreshRSS_Entry
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
			return $entry;
		}

		// Process entry content
		if (method_exists($entry, 'content')) {
			$content = $entry->content();
			$newContent = $this->replaceYoutubeEmbeds($content);
			if ($newContent !== $content) {
				$entry->_content($newContent);
			}
		}

		return $entry;
	}

	/**
	 * Replace YouTube iframe embeds with thumbnail images
	 * @param string $content
	 * @return string
	 */
	private function replaceYoutubeEmbeds(string $content): string {
		// Regex pattern to match YouTube iframes with video IDs
		// Matches both youtube.com/embed/VIDEO_ID and youtube-nocookie.com/embed/VIDEO_ID
		$pattern = '#<iframe[^>]*src=["\']https?://(?:www\.)?(?:youtube\.com|youtube-nocookie\.com)/embed/([a-zA-Z0-9_-]{11})[^"\']*["\'][^>]*>.*?</iframe>#is';

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
		}
	}

}
