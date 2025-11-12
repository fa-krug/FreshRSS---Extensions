<?php

/**
 * InlineImages Extension
 *
 * Downloads images from RSS entries and embeds them as base64 inline images.
 * This reduces external requests and improves privacy.
 */
class InlineImagesExtension extends Minz_Extension {

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
     * Download image and convert to base64
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

            // Detect MIME type
            $mimeType = $this->detectMimeType($imageData);

            // Convert to base64
            $base64 = base64_encode($imageData);

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
