<?php

declare(strict_types=1);

final class FixXEmbeddingExtension extends Minz_Extension {
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

		// Get entry URL
		$entryUrl = method_exists($entry, 'link') ? $entry->link() : '';

		// Process entry content
		if (method_exists($entry, 'content') && $entryUrl !== '') {
			$content = $entry->content();
			$newContent = $this->replaceXErrorMessage($content, $entryUrl);
			if ($newContent !== $content) {
				$entry->_content($newContent);
			}
		}

		return $entry;
	}

	/**
	 * Replace X.com error messages with embedded media from fxtwitter.com API
	 * @param string $content
	 * @param string $url
	 * @return string
	 */
	private function replaceXErrorMessage(string $content, string $url): string {
		// Regex pattern to match the X.com error message div
		$pattern = '#<div><p><span>Something went wrong.*?</span></p></div>#is';

		// Check if content contains the error message
		if (!preg_match($pattern, $content)) {
			return $content;
		}

		// Extract tweet ID from URL
		if (preg_match('#/status/(\d+)#', $url, $matches)) {
			$tweetId = $matches[1];
			$apiUrl = "https://api.fxtwitter.com/status/{$tweetId}";

			$mediaUrls = $this->getMediaFromFxTwitter($apiUrl);

			if (!empty($mediaUrls)) {
				// Create embedded images with link to original post
				$images = [];
				foreach ($mediaUrls as $mediaUrl) {
					$images[] = sprintf(
						'<img src="%s" alt="X post media" style="max-width:100%%;height:auto;" />',
						htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8')
					);
				}
				$replacement = sprintf(
					'<a href="%s">%s</a>',
					htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
					implode('<br/>', $images)
				);
			} else {
				// Fallback to simple link if we can't get media
				$replacement = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">View on X</a>';
			}
		} else {
			$replacement = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">View on X</a>';
		}

		return preg_replace($pattern, $replacement, $content);
	}

	/**
	 * Fetch media URLs from fxtwitter.com API
	 * @param string $apiUrl
	 * @return array
	 */
	private function getMediaFromFxTwitter(string $apiUrl): array {
		try {
			$json = @file_get_contents($apiUrl, false, stream_context_create([
				'http' => [
					'timeout' => 5,
					'user_agent' => 'Mozilla/5.0 (compatible; FreshRSS)',
					'follow_location' => 1
				]
			]));

			if ($json === false) {
				return [];
			}

			$data = json_decode($json, true);
			if (!$data || !isset($data['tweet'])) {
				return [];
			}

			$mediaUrls = [];

			// Check for photos in media.photos array
			if (isset($data['tweet']['media']['photos']) && is_array($data['tweet']['media']['photos'])) {
				foreach ($data['tweet']['media']['photos'] as $photo) {
					if (isset($photo['url'])) {
						$mediaUrls[] = $photo['url'];
					}
				}
			}

			// If no photos found, check media.all array as fallback
			if (empty($mediaUrls) && isset($data['tweet']['media']['all']) && is_array($data['tweet']['media']['all'])) {
				foreach ($data['tweet']['media']['all'] as $media) {
					if ($media['type'] === 'photo' && isset($media['url'])) {
						$mediaUrls[] = $media['url'];
					}
				}
			}

			return $mediaUrls;
		} catch (Exception $e) {
			return [];
		}
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
