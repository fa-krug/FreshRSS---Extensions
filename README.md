# FreshRSS Extensions

A small collection of user extensions for FreshRSS. Each extension lives in its own folder with its own README describing features and configuration.

## Extensions in this repository

- Replacer
  - Replace parts of incoming entry content using per‑feed regular expressions.
  - Placeholders supported in the replacement string: {url}, {feed_url}, {title}.
  - Folder: xExtension-Replacer
  - More info: xExtension-Replacer/README.md

- UpdateDateToNow
  - Set the publication date of incoming entries to “now” just before insertion.
  - Supports an optional timezone and per‑feed toggles to control which feeds are affected.
  - Folder: xExtension-UpdatePubDateNow
  - More info: xExtension-UpdatePubDateNow/README.md

## Quick install

You can install an individual extension by copying its folder into your FreshRSS installation and renaming the folder to match the extension entrypoint.

1. Choose an extension from this repo (for example, Replacer).
2. Copy its folder into your FreshRSS extensions directory, typically:
   - FreshRSS/extensions/ (system‑wide) or
   - data/extensions/ (depending on your setup)
4. In FreshRSS: Administration → Extensions → enable the extension.

Refer to each extension’s README for configuration details.

## Compatibility and notes

- Designed for recent versions of FreshRSS. If you encounter issues on older versions, please open an issue.
- Extensions hook into FreshRSS events and do not modify core files.
- Use at your own risk; always back up your data before trying new extensions.

## Development

- Each extension is self‑contained and follows FreshRSS's extension structure: metadata.json, extension.php, optional configure.phtml.
- All extensions include comprehensive inline documentation and detailed logging for easier debugging and maintenance.
- Contributions and suggestions are welcome. Feel free to open a pull request or issue.

## License

This repository is licensed under the MIT License. See LICENSE for details.
