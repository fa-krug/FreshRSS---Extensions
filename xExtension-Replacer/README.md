# Replacer (FreshRSS extension)

Replace parts of incoming entry content using per‑feed regular expressions, with handy placeholders for the entry URL and feed information.

This version adds:
- Per‑feed configuration of a regex search pattern and a replacement string.
- Placeholders in the replacement string: `{url}`, `{feed_url}`, `{title}`.

## Installation

1. Place this folder inside your FreshRSS `extensions/` directory as `Replacer`.
2. Enable the extension from FreshRSS: Administration → Extensions.

## Configuration

Open Administration → Extensions → Replacer.

For each of your feeds you can set:
- Search Regex Pattern: A PHP regex with delimiters, e.g. `#pattern#` or `/pattern/i`.
- Replace String: The text to insert for the first match found in the entry content.

Available placeholders in the Replace String:
- `{url}`: The URL of the article (entry link)
- `{feed_url}`: The URL of the feed
- `{title}`: The title of the feed

Regex pattern tips:
- Include delimiters, e.g. `#...#` or `/.../`.
- Add flags after the closing delimiter, e.g. `i` for case‑insensitive: `#pattern#i`.

## How it works

- The extension hooks into `entry_before_insert`.
- For each new entry, it looks up the configuration for the entry’s feed.
- If a valid regex pattern and a replacement string are configured, the regex is applied to the entry’s content only (titles/subtitles are not changed).
- Only the first match per entry is replaced.
- Before applying, the replacement string is HTML‑entity decoded, then placeholders are expanded:
  - `{url}` → the entry’s link
  - `{feed_url}` → the feed’s URL
  - `{title}` → the feed’s title
- If the regex pattern is invalid, the entry is left unchanged and an error is logged.

## Notes

- Leave either field empty to disable replacement for a feed.
- Make sure your regex includes delimiters (e.g., `#` or `/`).
- Only entry content is modified; entry titles are never altered.

## Version history

- 1.0.0: Initial release with per‑feed regex replacement and placeholders.
