# UpdateDateToNow Extension for FreshRSS

## Overview

UpdateDateToNow is a FreshRSS extension that automatically sets the publication date of new entries to the current time (now) when they're added to your feed. This is useful for feeds that have incorrect timestamps or when you want entries to appear at the top of your feed based on when they arrived, not when they were originally published.

## Features

- **Automatic date updating**: Sets entry dates to "now" before they're stored
- **Timezone support**: Configure a custom timezone for date calculation
- **Per-feed configuration**: Enable or disable for specific feeds
- **Backward compatibility**: Supports legacy configuration from older versions
- **Comprehensive Logging**: Detailed logging for debugging timezone handling and date updates

## Installation

1. Download or clone this extension into your FreshRSS extensions directory:
   ```
   ./extensions/xExtension-UpdatePubDateNow/
   ```

2. In FreshRSS, navigate to **Settings → Extensions**

3. Enable the **UpdateDateToNow** extension

4. Click the **⚙️ Configure** button to set up your preferences

## Configuration

After enabling the extension:

1. Go to the extension's configuration page
2. **(Optional)** Set a timezone (e.g., `America/New_York`, `Europe/Berlin`, `Asia/Tokyo`)
   - If left empty, uses the server's timezone
   - See [List of Supported Timezones](https://www.php.net/manual/en/timezones.php)
3. Check the boxes next to the feeds where you want to update dates
4. Save your settings

The extension will only process entries from the feeds you've selected.

## Use Cases

### Use Case 1: Broken Feed Timestamps
Some feeds publish entries with incorrect or inconsistent timestamps. This extension ensures they appear in chronological order based on when they arrived.

### Use Case 2: RSS-to-Email Services
When subscribing to email newsletters via RSS, you want them to appear at the top of your feed when they arrive, not buried based on their original publish date.

### Use Case 3: News Aggregation
For certain news feeds where you care more about when the article appeared in your feed rather than its original publication time.

### Use Case 4: Podcast Feeds
Some podcast feeds have inaccurate publish dates. This extension helps keep your podcast episodes in the order they were discovered.

## How It Works

When a new entry is added to FreshRSS:

1. The extension checks if the entry is from an enabled feed
2. It calculates the current timestamp using your configured timezone (or server time)
3. It updates the entry's publication date to this "now" timestamp
4. The entry is then stored with the new date

## Requirements

- FreshRSS 1.20.0 or higher
- PHP 7.4 or higher

## Technical Details

- **Hook**: Uses `entry_before_insert` to process entries before they're stored
- **Timezone handling**: Uses PHP's `DateTime` and `DateTimeZone` classes
- **Fallback behavior**: If an invalid timezone is provided, falls back to server time
- **Backward compatibility**: Supports old `mode` and `feed_ids` configuration format
- **Logging**: Logs date updates with original and new timestamps for tracking
- **Code Quality**: Includes comprehensive inline documentation and error handling

## Timezone Examples

Valid timezone strings include:
- `UTC`
- `America/New_York`
- `Europe/London`
- `Europe/Berlin`
- `Asia/Tokyo`
- `Australia/Sydney`

For a complete list, see [PHP's supported timezones](https://www.php.net/manual/en/timezones.php).

## Upgrade Notes

If you're upgrading from version 1.1.x or earlier:
- The old `mode` and `feed_ids` configuration is still supported
- New installations should use the per-feed checkbox configuration
- Old configurations will continue to work until you save new settings

## License

MIT License - see LICENSE file for details

## Author

Sascha Krug

## Version

1.2.0

## Version History

- **1.2.0**: Added per-feed toggle configuration
- **1.1.x**: Added timezone support and mode selection
- **1.0.0**: Initial release
