# xExtension-InlineImages

## Description

This FreshRSS extension downloads images from RSS entries and embeds them as base64 inline images. This provides several benefits:

- **Privacy**: Prevents external image tracking
- **Offline Access**: Images are stored directly in the database
- **Performance**: Reduces external HTTP requests
- **Consistency**: Ensures images remain available even if the source is removed

## Features

- ✅ Per-feed enable/disable configuration
- ✅ Automatic image downloading during entry insertion
- ✅ Support for PNG, JPEG, GIF, and WebP formats
- ✅ Configurable timeout (30s) and file size limits (7MB)
- ✅ Comprehensive error handling and logging
- ✅ Protocol-relative URL support (`//example.com/image.jpg`)
- ✅ MIME type validation (rejects non-image content)
- ✅ HTTPS support with SSL/TLS verification
- ✅ Automatic redirect following (up to 5 redirects)
- ✅ No external dependencies required (works out of the box)

## Installation

1. Download or clone this repository into your FreshRSS `extensions` directory:
   ```bash
   cd /path/to/FreshRSS/extensions
   git clone https://github.com/yourusername/xExtension-InlineImages.git
   ```

2. Navigate to **Settings → Extensions** in FreshRSS

3. Find "Inline Images" in the extension list

4. Click **Enable**

## Configuration

1. Go to **Settings → Extensions**

2. Click the **Configure** button next to "Inline Images"

3. Select the feeds for which you want to enable inline image processing

4. Click **Save**

## Technical Details

### Settings

- **Download Timeout**: 30 seconds
- **Maximum File Size**: 7 MB

### How It Works

1. When a new RSS entry is inserted, the extension checks if inline images are enabled for that feed
2. If enabled, it scans the entry content for `<img>` tags
3. For each image:
   - Downloads the image from the URL
   - Validates file size and format
   - Converts the image to base64
   - Replaces the original URL with a data URI (`data:image/...;base64,...`)

### Supported Image Formats

- JPEG/JPG
- PNG
- GIF
- WebP
- Any format supported by PHP's fileinfo extension

### Supported URL Types

- ✅ Absolute URLs: `http://example.com/image.jpg`, `https://example.com/image.jpg`
- ✅ Protocol-relative URLs: `//example.com/image.jpg` (automatically converted to HTTPS)
- ✅ Data URLs: `data:image/png;base64,...` (skipped, already inline)
- ❌ Relative paths: `/images/foo.jpg`, `../images/foo.jpg` (not supported - requires feed URL context)

### Hook Used

- `entry_before_insert`: Processes images before the entry is stored in the database

## Performance Considerations

- **Processing Time**: Image downloading happens during feed refresh, which may slightly increase refresh time for feeds with many images
- **Database Size**: Base64-encoded images take approximately 33% more space than binary data. Large images can significantly increase your database size
- **Memory Usage**: Ensure sufficient PHP memory is available. Adjust `memory_limit` in php.ini if you encounter issues

## Troubleshooting

### Images Not Being Converted

Check the log file at `/data/users/[username]/log.txt` for error messages:

```bash
tail -f data/users/_/log.txt
```

Common issues:
- Image URL is invalid or unreachable
- Image file size exceeds 7 MB
- Download timeout (server too slow - timeout is 30 seconds)
- Insufficient PHP memory
- Downloaded content is not an image (HTML page, text file, etc.)
- Relative URLs in RSS feed (not supported - images must be absolute URLs)
- SSL/TLS certificate verification issues (check server certificates)

### Extension Not Appearing

Ensure:
- Files are in the correct directory: `extensions/xExtension-InlineImages/`
- `metadata.json` and `extension.php` exist
- PHP has read permissions for the directory

### PHP Requirements

- PHP 7.4 or higher

Required PHP extensions:
- Fileinfo (`php-fileinfo`) - for MIME type detection

Check if installed:
```bash
php -m | grep fileinfo
```

## Development

### File Structure

```
xExtension-InlineImages/
├── extension.php          # Main extension class
├── metadata.json          # Extension metadata
├── configure.phtml        # Configuration UI template
├── README.md              # This file
└── i18n/                  # Translations
    ├── en/
    │   └── ext.php        # English translations
    └── de/
        └── ext.php        # German translations
```

### Adding Translations

Create a new translation file at `i18n/[language_code]/ext.php`:

```php
<?php
return [
    'inlineimages' => [
        'config' => [
            'info' => 'Translation here...',
            // ... other keys
        ],
    ],
];
```

## License

This extension is provided as-is for use with FreshRSS.

## Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## Changelog

### 1.0.1 (2025-11-16)
- **Fixed**: Critical PHP 7.4 compatibility issue with `str_starts_with()` (requires PHP 8.0+)
- **Fixed**: Improved regex pattern to handle empty src attributes and preserve quote styles
- **Fixed**: Added comprehensive error handling to prevent image tags from disappearing on failures
- **Fixed**: Case-insensitive data URL detection (now handles `DATA:`, `Data:`, etc.)
- **Fixed**: HTTPS context not configured (only HTTP settings were applied)
- **Added**: Protocol-relative URL support (`//example.com/image.jpg` converted to HTTPS)
- **Added**: MIME type validation - rejects non-image content (HTML, text, etc.)
- **Added**: URL scheme validation - only http/https allowed
- **Added**: Automatic redirect following (up to 5 redirects)
- **Added**: SSL/TLS certificate verification for HTTPS
- **Added**: Detailed logging for debugging image processing issues
- **Improved**: Better preservation of original HTML when image processing fails
- **Improved**: More informative warning messages for unsupported URL types (relative paths)
- **Changed**: Increased download timeout from 10s to 30s for slower servers
- **Changed**: Increased maximum file size from 5MB to 7MB to balance image quality with database size

### 1.0.0 (2025-11-12)
- Initial release
- Per-feed configuration
- Image downloading and resizing
- Base64 inline embedding
- Support for multiple image formats
- English and German translations
