<?php

/**
 * InlineImages Extension
 *
 * Downloads images from RSS entries and embeds them as base64 inline images.
 * This reduces external requests and improves privacy.
 */
class InlineImagesExtension extends Minz_Extension {

    /**
     * Maximum file size to process (in bytes) - 7MB
     */
    private const MAX_FILE_SIZE = 7340032;

    /**
     * Timeout for image downloads (seconds)
     */
    private const DOWNLOAD_TIMEOUT = 30;

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
        try {
            // Find all img tags - improved pattern to handle edge cases
            $pattern = '/<img\s+([^>]*\s+)?src=(["\'])([^"\']*)\2([^>]*)>/i';

            $result = preg_replace_callback($pattern, function($matches) {
                try {
                    $beforeSrc = $matches[1] ?? '';
                    $quote = $matches[2];  // Preserve original quote style
                    $imageUrl = $matches[3];
                    $afterSrc = $matches[4] ?? '';

                    // Skip empty URLs
                    if (empty($imageUrl)) {
                        Minz_Log::debug('InlineImages: Skipping empty image URL');
                        return $matches[0];
                    }

                    // Download and convert image
                    $base64Data = $this->downloadAndConvertImage($imageUrl);

                    if ($base64Data !== null) {
                        // Replace with inline base64 image
                        Minz_Log::debug('InlineImages: Successfully converted image: ' . substr($imageUrl, 0, 100));
                        return '<img ' . $beforeSrc . 'src=' . $quote . $base64Data . $quote . $afterSrc . '>';
                    }

                    // Return original if conversion failed
                    Minz_Log::debug('InlineImages: Keeping original tag for: ' . substr($imageUrl, 0, 100));
                    return $matches[0];
                } catch (Exception $e) {
                    // If callback fails, preserve original tag
                    Minz_Log::error('InlineImages: Callback error for image tag: ' . $e->getMessage());
                    return $matches[0];
                }
            }, $html);

            // Return original HTML if preg_replace_callback failed
            if ($result === null) {
                Minz_Log::error('InlineImages: preg_replace_callback failed, preserving original HTML');
                return $html;
            }

            return $result;
        } catch (Exception $e) {
            // If entire processing fails, return original HTML
            Minz_Log::error('InlineImages: Error processing image tags: ' . $e->getMessage());
            return $html;
        }
    }

    /**
     * Download image and convert to base64
     *
     * @param string $url Image URL
     * @return string|null Base64 data URI or null on failure
     */
    private function downloadAndConvertImage(string $url): ?string {
        try {
            // Skip data URLs (already inline) - case-insensitive check
            // PHP 7.4 compatible check (str_starts_with requires PHP 8.0+)
            if (stripos($url, 'data:') === 0) {
                Minz_Log::debug('InlineImages: Skipping data URL');
                return null;
            }

            // Skip relative URLs (can't resolve without feed context)
            if (substr($url, 0, 1) === '/' || substr($url, 0, 3) === '../' || substr($url, 0, 2) === './') {
                Minz_Log::warning('InlineImages: Skipping relative URL (not supported): ' . $url);
                return null;
            }

            // Handle protocol-relative URLs by adding https:
            if (substr($url, 0, 2) === '//') {
                $url = 'https:' . $url;
                Minz_Log::debug('InlineImages: Converted protocol-relative URL to: ' . $url);
            }

            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Minz_Log::warning('InlineImages: Invalid URL format: ' . $url);
                return null;
            }

            // Validate URL scheme (only http and https are supported)
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array(strtolower($scheme), ['http', 'https'])) {
                Minz_Log::warning('InlineImages: Unsupported URL scheme: ' . $scheme);
                return null;
            }

            // Download image with timeout (configure both http and https)
            $context = stream_context_create([
                'http' => [
                    'timeout' => self::DOWNLOAD_TIMEOUT,
                    'user_agent' => 'FreshRSS/InlineImages',
                    'follow_location' => true,
                    'max_redirects' => 5,
                ],
                'https' => [
                    'timeout' => self::DOWNLOAD_TIMEOUT,
                    'user_agent' => 'FreshRSS/InlineImages',
                    'follow_location' => true,
                    'max_redirects' => 5,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
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

            // Validate it's actually an image
            if (substr($mimeType, 0, 6) !== 'image/') {
                Minz_Log::warning('InlineImages: Downloaded content is not an image (MIME: ' . $mimeType . '): ' . $url);
                return null;
            }

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
                if ($enabled === '1') {
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
