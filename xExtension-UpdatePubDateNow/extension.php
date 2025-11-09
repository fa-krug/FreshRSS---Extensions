<?php

declare(strict_types=1);

final class UpdateDateToNowExtension extends Minz_Extension {
	#[\Override]
	public function init(): void {
		// Hook into the pre-insert phase of an entry
		Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'beforeInsert']);
		Minz_Log::notice('UpdateDateToNow extension initialized');
	}

	/**
	 * Hook: called before inserting an entry into the database.
	 * Updates the entry's publication date to the current time based on configured timezone.
	 *
	 * @param FreshRSS_Entry $entry The entry to process
	 * @return FreshRSS_Entry The modified entry
	 */
	public function beforeInsert($entry) {
		// Load configuration (user-level)
		$timezone = (string)$this->getUserConfigurationValue('timezone', '');
		$enabledFeeds = $this->getUserConfigurationValue('enabled_feeds', []);
		if (!is_array($enabledFeeds)) {
			$enabledFeeds = [];
		}
		$hasEnabledDefined = array_key_exists('enabled_feeds', $this->getUserConfiguration());

		// Backward-compat fields from v1.1.x
		$mode = (string)$this->getUserConfigurationValue('mode', 'all'); // 'all' | 'only_listed'
		$feedIds = $this->getUserConfigurationValue('feed_ids', []);
		if (!is_array($feedIds)) {
			$feedIds = [];
		}

		// Per-feed control based on enabled_feeds when configured; otherwise fallback to old mode/feed_ids.
		$feedId = method_exists($entry, 'feedId') ? (int)$entry->feedId() : 0;
		if ($hasEnabledDefined) {
			$apply = ($feedId > 0) && !empty($enabledFeeds[$feedId]);
			if (!$apply) {
				return $entry; // Skip: feed not enabled for date updates
			}
		} else {
			// Backward compatibility mode
			if ($mode === 'only_listed' && $feedId > 0 && !in_array($feedId, array_map('intval', $feedIds), true)) {
				return $entry; // Do not modify date for non-listed feeds
			}
		}

		// Compute "now" according to configured timezone without changing global default
		$timestamp = time();
		$timezoneUsed = 'server default';

		if ($timezone !== '') {
			try {
				$dt = new DateTime('now', new DateTimeZone($timezone));
				$timestamp = $dt->getTimestamp();
				$timezoneUsed = $timezone;
			} catch (Throwable $e) {
				// Fallback to server time on invalid timezone
				Minz_Log::warning('UpdateDateToNow: Invalid timezone "' . $timezone . '" - falling back to server time. Error: ' . $e->getMessage());
				$timezoneUsed = 'server default (fallback)';
			}
		}

		// Update entry date to "now"
		$entry->_date($timestamp);
		Minz_Log::notice('UpdateDateToNow: Updated entry date to current time for feed ID ' . $feedId . ' using timezone: ' . $timezoneUsed);

		return $entry;
	}

	#[\Override]
	public function handleConfigureAction(): void {
		parent::handleConfigureAction();

		if (Minz_Request::isPost()) {
			// CSRF is handled by the controller; just read and persist settings
			$timezone = trim(Minz_Request::paramString('timezone'));

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

			// Validate timezone if provided
			if ($timezone !== '') {
				try {
					new DateTimeZone($timezone);
					Minz_Log::notice('UpdateDateToNow: Valid timezone configured: ' . $timezone);
				} catch (Throwable $e) {
					Minz_Log::warning('UpdateDateToNow: Invalid timezone provided in configuration: ' . $timezone . ' - ' . $e->getMessage());
				}
			}

			$this->setUserConfiguration([
				'timezone' => $timezone,
				'enabled_feeds' => $enabledFeeds,
			]);

			Minz_Log::notice('UpdateDateToNow: Configuration saved - ' . count($enabledFeeds) . ' feed(s) enabled, timezone: ' . ($timezone ?: 'server default'));
		}
	}

}
