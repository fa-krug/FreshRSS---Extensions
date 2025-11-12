# xExtension-InlineImages

## Description

This FreshRSS extension downloads images from RSS entries, shrinks them, and embeds them as base64 inline images. This provides several benefits:

- **Privacy**: Prevents external image tracking
- **Offline Access**: Images are stored directly in the database
- **Performance**: Reduces external HTTP requests
- **Consistency**: Ensures images remain available even if the source is removed

## Features

- ✅ Per-feed enable/disable configuration
- ✅ Automatic image downloading during entry insertion
- ✅ Image resizing (maximum 800x800 pixels while maintaining aspect ratio)
- ✅ JPEG compression for reduced file size
- ✅ Support for PNG, JPEG, GIF, and WebP formats
- ✅ Transparency preservation for PNG and GIF
- ✅ Configurable timeout and file size limits
- ✅ Error handling and logging

## Installation

### Prerequisites

This extension requires the **PHP GD extension** to be installed.

#### For Docker users:

If you're running FreshRSS in Docker, you need to install the GD extension in your container. Add this to your Dockerfile or run it in your container:

```bash
# Install GD extension
docker exec -it <your-freshrss-container> apk add --no-cache php83-gd

# Or for Debian-based images:
docker exec -it <your-freshrss-container> apt-get update && apt-get install -y php-gd

# Restart the container
docker restart <your-freshrss-container>
```

Or add to your `docker-compose.yml`:

```yaml
services:
  freshrss:
    image: freshrss/freshrss:latest
    # ... other config ...
    command: sh -c "apk add --no-cache php83-gd && /entrypoint.sh"
```

#### For standard PHP installations:

```bash
# Ubuntu/Debian
sudo apt-get install php-gd

# CentOS/RHEL
sudo yum install php-gd

# Check if installed
php -m | grep gd
```

### Extension Installation

1. Download or clone this repository into your FreshRSS `extensions` directory:
   ```bash
   cd /path/to/FreshRSS/extensions
   git clone https://github.com/yourusername/xExtension-InlineImages.git
   ```

2. Navigate to **Settings → Extensions** in FreshRSS

3. Find "Inline Images" in the extension list

4. Click **Enable**

   **Note:** If the extension shows an error about GD not being installed, check the logs and install the GD extension as described above.

## Configuration

1. Go to **Settings → Extensions**

2. Click the **Configure** button next to "Inline Images"

3. Select the feeds for which you want to enable inline image processing

4. Click **Save**

## Technical Details

### Settings

- **Maximum Image Size**: 800x800 pixels (maintains aspect ratio)
- **JPEG Quality**: 85% compression
- **Download Timeout**: 10 seconds
- **Maximum File Size**: 5 MB

### How It Works

1. When a new RSS entry is inserted, the extension checks if inline images are enabled for that feed
2. If enabled, it scans the entry content for `<img>` tags
3. For each image:
   - Downloads the image from the URL
   - Validates file size and format
   - Resizes the image if it exceeds maximum dimensions
   - Converts the image to base64
   - Replaces the original URL with a data URI (`data:image/...;base64,...`)

### Supported Image Formats

- JPEG/JPG
- PNG (with transparency)
- GIF (with transparency)
- WebP

### Hook Used

- `entry_before_insert`: Processes images before the entry is stored in the database

## Performance Considerations

- **Processing Time**: Image processing happens during feed refresh, which may slightly increase refresh time for feeds with many images
- **Database Size**: Base64-encoded images take approximately 33% more space than binary data. Large images can significantly increase your database size
- **Memory Usage**: Image processing requires sufficient PHP memory. Adjust `memory_limit` in php.ini if you encounter issues

## Troubleshooting

### Images Not Being Converted

Check the log file at `/data/users/[username]/log.txt` for error messages:

```bash
tail -f data/users/_/log.txt
```

Common issues:
- Image URL is invalid or unreachable
- Image file size exceeds 5 MB
- Download timeout (server too slow)
- PHP GD extension not installed
- Insufficient PHP memory

### Extension Not Appearing

Ensure:
- Files are in the correct directory: `extensions/xExtension-InlineImages/`
- `metadata.json` and `extension.php` exist
- PHP has read permissions for the directory

### PHP Requirements

Required PHP extensions:
- GD library (`php-gd`)
- Fileinfo (`php-fileinfo`)

Check if installed:
```bash
php -m | grep -E "(gd|fileinfo)"
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

### 1.0.0 (2025-11-12)
- Initial release
- Per-feed configuration
- Image downloading and resizing
- Base64 inline embedding
- Support for multiple image formats
- English and German translations
