<?php

/**
 * InlineImages Extension
 *
 * Downloads images from RSS entries, shrinks them, and embeds them as base64 inline images.
 * This reduces external requests and improves privacy.
 */
class InlineImagesExtension extends Minz_Extension {

    /**
     * Maximum image dimensions (width and height)
     */
    private const MAX_IMAGE_SIZE = 800;

    /**
     * JPEG quality for compression (0-100)
     */
    private const JPEG_QUALITY = 85;

    /**
     * Maximum file size to process (in bytes) - 5MB
     */
    private const MAX_FILE_SIZE = 5242880;

    /**
     * Timeout for image downloads (seconds)
     */
    private const DOWNLOAD_TIMEOUT = 10;

    /**
     * Initialize the extension
     */
    public function init(): void {
        parent::init();

        // Register translations
        $this->registerTranslates();

        // Register hook to process entries before insertion
        $this->registerHook('entry_before_insert', [$this, 'processEntryImages']);
    }

    /**
     * Process images in entry content
     *
     * @param FreshRSS_Entry $entry The entry object
     * @return FreshRSS_Entry|null Modified entry or null to skip
     */
    public function processEntryImages($entry) {
        // Get feed ID
        $feedId = $entry->feed()->id();

        // Check if enabled for this feed
        $enabledFeeds = $this->getUserConfigurationValue('enabled_feeds', []);
        if (empty($enabledFeeds[$feedId])) {
            return $entry;
        }

        // Get entry content
        $content = $entry->content();

        if (empty($content)) {
            return $entry;
        }

        // Process all image tags
        $newContent = $this->processImageTags($content);

        // Update entry content if changes were made
        if ($newContent !== $content) {
            $entry->_content($newContent);
            Minz_Log::notice('InlineImages: Processed images for entry: ' . $entry->title());
        }

        return $entry;
    }

    /**
     * Process all image tags in HTML content
     *
     * @param string $html HTML content
     * @return string Modified HTML content
     */
    private function processImageTags(string $html): string {
        // Find all img tags
        $pattern = '/<img\s+([^>]*\s+)?src=["\']([^"\']+)["\']([^>]*)>/i';

        $html = preg_replace_callback($pattern, function($matches) {
            $beforeSrc = $matches[1] ?? '';
            $imageUrl = $matches[2];
            $afterSrc = $matches[3] ?? '';

            // Download and convert image
            $base64Data = $this->downloadAndConvertImage($imageUrl);

            if ($base64Data !== null) {
                // Replace with inline base64 image
                return '<img ' . $beforeSrc . 'src="' . $base64Data . '"' . $afterSrc . '>';
            }

            // Return original if conversion failed
            return $matches[0];
        }, $html);

        return $html;
    }

    /**
     * Download image, shrink it, and convert to base64
     *
     * @param string $url Image URL
     * @return string|null Base64 data URI or null on failure
     */
    private function downloadAndConvertImage(string $url): ?string {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Minz_Log::warning('InlineImages: Invalid URL: ' . $url);
                return null;
            }

            // Skip data URLs (already inline)
            if (str_starts_with($url, 'data:')) {
                return null;
            }

            // Download image with timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::DOWNLOAD_TIMEOUT,
                    'user_agent' => 'FreshRSS/InlineImages',
                ],
            ]);

            $imageData = @file_get_contents($url, false, $context);

            if ($imageData === false) {
                Minz_Log::warning('InlineImages: Failed to download image: ' . $url);
                return null;
            }

            // Check file size
            $fileSize = strlen($imageData);
            if ($fileSize > self::MAX_FILE_SIZE) {
                Minz_Log::warning('InlineImages: Image too large (' . $fileSize . ' bytes): ' . $url);
                return null;
            }

            // Create image resource from data
            $image = @imagecreatefromstring($imageData);

            if ($image === false) {
                Minz_Log::warning('InlineImages: Failed to create image from data: ' . $url);
                return null;
            }

            // Get original dimensions
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // Calculate new dimensions (maintain aspect ratio)
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;

            if ($originalWidth > self::MAX_IMAGE_SIZE || $originalHeight > self::MAX_IMAGE_SIZE) {
                if ($originalWidth > $originalHeight) {
                    $newWidth = self::MAX_IMAGE_SIZE;
                    $newHeight = (int)(($originalHeight / $originalWidth) * self::MAX_IMAGE_SIZE);
                } else {
                    $newHeight = self::MAX_IMAGE_SIZE;
                    $newWidth = (int)(($originalWidth / $originalHeight) * self::MAX_IMAGE_SIZE);
                }
            }

            // Create new image with calculated dimensions
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and GIF
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);

            // Resize image
            imagecopyresampled(
                $newImage,
                $image,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                $originalWidth,
                $originalHeight
            );

            // Convert to base64
            ob_start();

            // Detect original format and use appropriate output function
            $mimeType = $this->detectMimeType($imageData);

            switch ($mimeType) {
                case 'image/png':
                    imagepng($newImage, null, 9); // Max compression
                    break;
                case 'image/gif':
                    imagegif($newImage);
                    break;
                case 'image/webp':
                    imagewebp($newImage, null, self::JPEG_QUALITY);
                    break;
                default:
                    // Default to JPEG for other formats
                    imagejpeg($newImage, null, self::JPEG_QUALITY);
                    $mimeType = 'image/jpeg';
                    break;
            }

            $outputData = ob_get_clean();

            // Free memory
            imagedestroy($image);
            imagedestroy($newImage);

            // Convert to base64
            $base64 = base64_encode($outputData);

            // Return data URI
            return 'data:' . $mimeType . ';base64,' . $base64;

        } catch (Exception $e) {
            Minz_Log::error('InlineImages: Error processing image ' . $url . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Detect MIME type from image data
     *
     * @param string $data Image binary data
     * @return string MIME type
     */
    private function detectMimeType(string $data): string {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $data);
        finfo_close($finfo);

        return $mimeType ?: 'image/jpeg';
    }

    /**
     * Handle configuration form submission
     */
    public function handleConfigureAction(): void {
        parent::handleConfigureAction();

        if (Minz_Request::isPost()) {
            $enabledFeedsRaw = Minz_Request::param('enabled_feeds', []);
            $enabledFeeds = [];

            foreach ($enabledFeedsRaw as $feedId => $enabled) {
                if ($enabled === 'on') {
                    $enabledFeeds[(int)$feedId] = true;
                }
            }

            $this->setUserConfiguration([
                'enabled_feeds' => $enabledFeeds,
            ]);

            Minz_Log::notice('InlineImages: Configuration saved');
        }
    }
}
