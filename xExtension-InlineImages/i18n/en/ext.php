<?php

return [
    'inlineimages' => [
        'config' => [
            'info' => 'This extension downloads images from RSS entries, shrinks them to a maximum of 800x800 pixels, and embeds them as base64 inline images. This reduces external requests and improves privacy.',
            'enable_for_feeds' => 'Enable for Feeds',
            'no_feeds' => 'No feeds available. Please add some feeds first.',
            'feed_count' => '%d feeds total, %d enabled',
            'settings' => 'Extension Settings',
            'max_size' => 'Maximum image dimensions: 800x800 pixels',
            'quality' => 'JPEG compression quality: 85%',
            'timeout' => 'Download timeout: 10 seconds',
            'max_file_size' => 'Maximum file size: 5 MB',
        ],
    ],
];
