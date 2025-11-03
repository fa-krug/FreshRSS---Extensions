# Replacer Extension for FreshRSS

## Overview

Replacer is a powerful FreshRSS extension that allows you to modify feed entry content using regular expressions. You can configure multiple replacement rules per feed with support for dynamic placeholders like URLs, feed titles, and more.

## Features

- **Regex-based replacements**: Use powerful regular expressions to find and replace content
- **Multiple rules per feed**: Apply several replacement rules to each feed in sequence
- **Dynamic placeholders**: Insert dynamic content using `{url}`, `{feed_url}`, and `{title}` placeholders
- **Per-feed configuration**: Different replacement rules for different feeds
- **Content-only modification**: Only modifies entry content, not titles

## Installation

1. Download or clone this extension into your FreshRSS extensions directory:
   ```
   ./extensions/xExtension-Replacer/
   ```

2. In FreshRSS, navigate to **Settings → Extensions**

3. Enable the **Replacer** extension

4. Click the **⚙️ Configure** button to set up your replacement rules

## Configuration

After enabling the extension:

1. Go to the extension's configuration page
2. For each feed where you want to apply replacements:
   - Add one or more replacement rules
   - Enter a **search regex** pattern (e.g., `/old-text/i`)
   - Enter a **replacement string** (e.g., `new-text` or use placeholders)
3. Use the **Add Rule** button to add multiple rules to a feed
4. Save your settings

### Available Placeholders

You can use these placeholders in your replacement strings:

- `{url}` - The entry's URL (article link)
- `{feed_url}` - The feed's URL
- `{title}` - The feed's title/name

## Examples

### Example 1: Simple Text Replacement
- **Search Regex**: `/advertisement/i`
- **Replace String**: `[sponsored content]`

### Example 2: Remove Tracking Parameters
- **Search Regex**: `/\?utm_[^"'\s]*/`
- **Replace String**: `` (empty)

### Example 3: Add Link to Original
- **Search Regex**: `/(Article continues\.\.\.)/`
- **Replace String**: `$1 <a href="{url}">Read full article</a>`

### Example 4: Replace Image Sources
- **Search Regex**: `/<img src="\/images\//`
- **Replace String**: `<img src="{feed_url}/images/`

## How It Works

When a new entry is added to FreshRSS:

1. The extension checks if replacement rules are configured for the entry's feed
2. It applies each rule in sequence to the entry content
3. For each rule:
   - Validates the regex pattern
   - Replaces placeholders with actual values
   - Applies the regex replacement to the content
4. The modified content is saved to the database

## Requirements

- FreshRSS 1.20.0 or higher
- PHP 7.4 or higher
- Basic knowledge of regular expressions for advanced usage

## Technical Details

- **Hook**: Uses `entry_before_insert` to process entries before they're stored
- **Scope**: Only modifies entry content, not titles or other metadata
- **Rule order**: Rules are applied in the order they're defined
- **Error handling**: Invalid regex patterns are logged and skipped

## Regex Tips

- Use delimiters: `/pattern/` or `#pattern#`
- Common flags: `i` (case-insensitive), `m` (multiline), `s` (dotall)
- Test your regex patterns before using them
- Remember to escape special characters: `\.` `\?` `\*` etc.

## License

MIT License - see LICENSE file for details

## Author

Sascha Krug

## Version

1.0.0
