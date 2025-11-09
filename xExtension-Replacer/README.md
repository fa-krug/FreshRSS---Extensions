# Replacer Extension for FreshRSS

## Overview

Replacer is a powerful FreshRSS extension that allows you to modify feed entry content using regular expressions. You can configure multiple replacement rules per feed with support for dynamic placeholders like URLs, feed titles, and more.

## Features

- **Regex-based replacements**: Use powerful regular expressions to find and replace content
- **Multiple rules per feed**: Apply several replacement rules to each feed in sequence
- **Dynamic placeholders**: Insert dynamic content using `{url}`, `{feed_url}`, and `{title}` placeholders
- **Per-feed configuration**: Different replacement rules for different feeds
- **Content-only modification**: Only modifies entry content, not titles
- **Dynamic UI**: Add and remove rules on the fly without page reloads
- **Feed reload functionality**: Re-apply new rules to existing entries by reloading the feed
- **Error handling**: Invalid regex patterns are logged and skipped automatically

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
3. Use the **+ Add Rule** button to dynamically add multiple rules to a feed
4. Use the **Remove** button to delete individual rules
5. Use the **Reload Feed** button to re-apply rules to existing entries (see below)
6. Save your settings

### Reload Feed Feature

When you modify replacement rules, they only apply to new entries by default. To apply the new rules to existing entries:

1. Click the **Reload Feed** button for the feed
2. Confirm the action (this will delete all entries from that feed)
3. The feed will be refreshed with all entries re-downloaded and processed with your current rules

**Warning**: This operation deletes all existing entries from the feed and re-downloads them. Use with caution.

### Available Placeholders

You can use these placeholders in your replacement strings:

- `{url}` - The entry's URL (article link)
- `{feed_url}` - The feed's URL
- `{title}` - The feed's title/name

## Use Cases

### Use Case 1: Fix Broken Image Paths
Some feeds have relative image URLs that don't work in feed readers. Use the `{feed_url}` placeholder to convert them to absolute URLs.

### Use Case 2: Remove Advertisements
Strip out advertisement sections or tracking scripts from feed content using regex patterns.

### Use Case 3: Add Read More Links
Append a "Read more" link with the full article URL using the `{url}` placeholder.

### Use Case 4: Format Enhancement
Add custom styling, structure, or formatting to feed entries that lack proper HTML formatting.

### Use Case 5: Content Sanitization
Remove or replace unwanted elements like social media embeds, newsletter footers, or paywalls.

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
2. It applies each rule in **sequential order** to the entry content (order matters!)
3. For each rule:
   - Validates the regex pattern syntax
   - If invalid, the pattern is logged as an error and skipped
   - Replaces placeholders (`{url}`, `{feed_url}`, `{title}`) with actual values
   - Applies the regex replacement to the content
4. The modified content is saved to the database
5. A log entry records how many rules were successfully applied

**Note**: Rules are applied sequentially, meaning the output of one rule becomes the input for the next. This allows for chained transformations.

## Requirements

- FreshRSS 1.20.0 or higher
- PHP 7.4 or higher
- Basic knowledge of regular expressions for advanced usage

## Technical Details

- **Hook**: Uses `entry_before_insert` to process entries before they're stored
- **Scope**: Only modifies entry content, not titles or other metadata
- **Rule order**: Rules are applied in the order they're defined
- **Error handling**: Invalid regex patterns are logged and skipped
- **AJAX Controller**: Provides a custom controller for feed reload operations
- **Backward compatibility**: Automatically migrates old single-rule format to new multi-rule format
- **HTML entity handling**: Properly decodes HTML entities in patterns and replacements

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
