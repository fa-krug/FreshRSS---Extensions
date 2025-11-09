<?php

declare(strict_types=1);

final class FixXEmbeddingExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		// Hook into the pre-insert phase of an entry
		Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'beforeInsert']);
		Minz_Log::notice('FixXEmbedding extension initialized');
	}

	/**
	 * Hook: called before inserting an entry into the database.
	 * Fixes X.com (Twitter) error messages by fetching actual media from fxtwitter.com API.
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
			return $entry; // Skip: feed not enabled for X embedding fix
		}

		// Get entry URL (needed to extract tweet ID)
		$entryUrl = method_exists($entry, 'link') ? $entry->link() : '';

		// Process entry content
		if (method_exists($entry, 'content') && $entryUrl !== '') {
			$content = $entry->content();
			$newContent = $this->replaceXErrorMessage($content, $entryUrl);
			if ($newContent !== $content) {
				$entry->_content($newContent);
				Minz_Log::notice('FixXEmbedding: Fixed X.com error message for feed ID ' . $feedId);
			}
		}

		return $entry;
	}

	/**
	 * Replace X.com error messages with embedded media from fxtwitter.com API
	 *
	 * Detects X.com "Something went wrong" error messages and replaces them with
	 * actual media fetched from the fxtwitter.com API.
	 *
	 * @param string $content The entry content to process
	 * @param string $url The entry URL (contains tweet ID)
	 * @return string The content with error messages replaced by media or fallback link
	 */
	private function replaceXErrorMessage(string $content, string $url): string {
		// Regex pattern to match the X.com error message div
		$pattern = '#<div><p><span>Something went wrong.*?</span></p></div>#is';

		// Check if content contains the error message
		if (!preg_match($pattern, $content)) {
			return $content; // No error message found, return unchanged
		}

		Minz_Log::notice('FixXEmbedding: Detected X.com error message in content');

		// Extract tweet ID from URL (format: .../status/1234567890)
		if (preg_match('#/status/(\d+)#', $url, $matches)) {
			$tweetId = $matches[1];
			$apiUrl = "https://api.fxtwitter.com/status/{$tweetId}";

			Minz_Log::notice('FixXEmbedding: Attempting to fetch media for tweet ID ' . $tweetId);

			// Fetch media URLs from fxtwitter API
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
				Minz_Log::notice('FixXEmbedding: Successfully embedded ' . count($mediaUrls) . ' media item(s) for tweet ID ' . $tweetId);
			} else {
				// Fallback to simple link if we can't get media
				$replacement = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">View on X</a>';
				Minz_Log::warning('FixXEmbedding: No media found for tweet ID ' . $tweetId . ', using fallback link');
			}
		} else {
			// No tweet ID found in URL
			$replacement = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">View on X</a>';
			Minz_Log::warning('FixXEmbedding: Could not extract tweet ID from URL: ' . $url);
		}

		return preg_replace($pattern, $replacement, $content);
	}

	/**
	 * Fetch media URLs from fxtwitter.com API
	 *
	 * Makes an HTTP request to the fxtwitter.com API to retrieve media URLs
	 * associated with a tweet. Handles both photos and video thumbnails.
	 *
	 * @param string $apiUrl The fxtwitter.com API URL for the tweet
	 * @return array Array of media URLs, empty if none found or on error
	 */
	private function getMediaFromFxTwitter(string $apiUrl): array {
		try {
			// Make HTTP request with timeout and user agent
			$json = @file_get_contents($apiUrl, false, stream_context_create([
				'http' => [
					'timeout' => 5, // 5 second timeout to avoid hanging
					'user_agent' => 'Mozilla/5.0 (compatible; FreshRSS)',
					'follow_location' => 1 // Follow redirects
				]
			]));

			if ($json === false) {
				Minz_Log::warning('FixXEmbedding: Failed to fetch data from fxtwitter API: ' . $apiUrl);
				return [];
			}

			// Parse JSON response
			$data = json_decode($json, true);
			if (!$data || !isset($data['tweet'])) {
				Minz_Log::warning('FixXEmbedding: Invalid API response format from fxtwitter');
				return [];
			}

			$mediaUrls = [];

			// Check for photos in media.photos array (primary location)
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

			if (empty($mediaUrls)) {
				Minz_Log::notice('FixXEmbedding: No media found in fxtwitter API response');
			}

			return $mediaUrls;
		} catch (Exception $e) {
			Minz_Log::error('FixXEmbedding: Exception while fetching from fxtwitter API - ' . $e->getMessage());
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

			Minz_Log::notice('FixXEmbedding: Configuration saved - ' . count($enabledFeeds) . ' feed(s) enabled');
		}
	}

}
