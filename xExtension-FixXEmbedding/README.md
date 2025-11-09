# FixXEmbedding Extension for FreshRSS

## Overview

FixXEmbedding is a FreshRSS extension that automatically replaces X.com (Twitter) error messages with embedded media content. When X.com posts contain the "Something went wrong" error message, this extension fetches the actual media from the fxtwitter.com API and displays it properly in your feed.

## Features

- **Automatic Error Detection**: Detects X.com error messages in feed entries
- **Media Embedding**: Fetches and embeds images from X.com posts using the fxtwitter.com API
- **Per-Feed Configuration**: Enable or disable the extension for specific feeds
- **Fallback Support**: Provides a simple link if media cannot be retrieved
- **Comprehensive Logging**: Detailed logging for debugging and monitoring API requests and replacements

## Installation

1. Download or clone this extension into your FreshRSS extensions directory:
   ```
   ./extensions/xExtension-FixXEmbedding/
   ```

2. In FreshRSS, navigate to **Settings → Extensions**

3. Enable the **FixXEmbedding** extension

4. Click the **⚙️ Configure** button to select which feeds should have this feature enabled

## Configuration

After enabling the extension:

1. Go to the extension's configuration page
2. Check the boxes next to the feeds where you want to fix X.com embedding issues
3. Save your settings

The extension will only process entries from the feeds you've selected.

## How It Works

When a new entry is added to FreshRSS:

1. The extension checks if the entry is from an enabled feed
2. It scans the content for X.com error messages (the "Something went wrong" div)
3. If found, it extracts the tweet ID from the entry URL
4. It queries the fxtwitter.com API to fetch the actual media
5. It replaces the error message with embedded images or a simple link

## Requirements

- FreshRSS 1.20.0 or higher
- PHP 7.4 or higher
- Internet access to reach the fxtwitter.com API

## Technical Details

- **API Endpoint**: `https://api.fxtwitter.com/status/{tweetId}`
- **Timeout**: 5 seconds for API requests
- **Hook**: Uses `entry_before_insert` to process entries before they're stored
- **Logging**: Logs API requests, successful replacements, and error conditions for easier troubleshooting
- **Code Quality**: Includes comprehensive inline documentation and error handling

## License

MIT License - see LICENSE file for details

## Author

Sascha Krug

## Version

1.0.0
