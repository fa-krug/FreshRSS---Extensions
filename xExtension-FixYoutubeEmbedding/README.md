# FixYoutubeEmbedding Extension for FreshRSS

## Overview

FixYoutubeEmbedding is a FreshRSS extension that replaces YouTube iframe embeds with thumbnail images linked to the original video. This is useful for reducing bandwidth usage, improving privacy, and speeding up feed loading times.

## Features

- **Replace iframes with thumbnails**: Converts YouTube embeds to static images
- **High-quality thumbnails**: Uses YouTube's `maxresdefault.jpg` for best quality
- **Clickable links**: Thumbnails link directly to the YouTube video
- **Per-Feed Configuration**: Enable or disable the extension for specific feeds
- **Supports multiple YouTube domains**: Works with both youtube.com and youtube-nocookie.com embeds

## Installation

1. Download or clone this extension into your FreshRSS extensions directory:
   ```
   ./extensions/xExtension-FixYoutubeEmbedding/
   ```

2. In FreshRSS, navigate to **Settings → Extensions**

3. Enable the **FixYoutubeEmbedding** extension

4. Click the **⚙️ Configure** button to select which feeds should have this feature enabled

## Configuration

After enabling the extension:

1. Go to the extension's configuration page
2. Check the boxes next to the feeds where you want to replace YouTube embeds
3. Save your settings

The extension will only process entries from the feeds you've selected.

## How It Works

When a new entry is added to FreshRSS:

1. The extension checks if the entry is from an enabled feed
2. It scans the content for YouTube iframe embeds
3. Extracts the video ID from the iframe URL
4. Replaces the iframe with a thumbnail image wrapped in a link to the video

## Example

**Before:**
```html
<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" ...></iframe>
```

**After:**
```html
<a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">
  <figure>
    <img src="https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg">
  </figure>
</a>
```

## Requirements

- FreshRSS 1.20.0 or higher
- PHP 7.4 or higher

## Technical Details

- **Hook**: Uses `entry_before_insert` to process entries before they're stored
- **Pattern matching**: Supports both `youtube.com/embed/` and `youtube-nocookie.com/embed/` URLs
- **Video ID format**: Matches standard 11-character YouTube video IDs

## License

MIT License - see LICENSE file for details

## Author

Sascha Krug

## Version

1.0.0
