# FreshRSS Extension Development Guide for Claude AI

## Project Information

**Author**: Sascha Krug

All extensions in this repository must use "Sascha Krug" as the author in metadata.json.

## FreshRSS Extension Structure

Every FreshRSS extension follows a specific structure. Here's the complete guide to creating a proper extension.

### Required Files

#### 1. metadata.json (REQUIRED)

The metadata.json file defines the extension's basic information:

```json
{
  "name": "ExtensionName",
  "author": "Sascha Krug",
  "description": "Brief description of what the extension does",
  "version": "1.0.0",
  "entrypoint": "ExtensionClassName",
  "type": "user"
}
```

**Fields:**
- `name`: Display name shown in FreshRSS UI
- `author`: MUST be "Sascha Krug"
- `description`: Short description (shown in extensions list)
- `version`: Semantic version (e.g., "1.0.0")
- `entrypoint`: PHP class name (without "Extension" suffix)
- `type`: Always "user" for user extensions

**Important**: The entrypoint name determines the class name. If entrypoint is "MyExtension", the class must be named "MyExtensionExtension".

#### 2. extension.php (REQUIRED)

The main extension class file. Must contain a class that extends `Minz_Extension`.

**Modern pattern (recommended):**

```php
<?php

declare(strict_types=1);

final class YourExtensionExtension extends Minz_Extension {
    #[\Override]
    public function init(): void {
        // Register hooks
        Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'beforeInsert']);

        // Log initialization
        Minz_Log::notice('YourExtension extension initialized');
    }

    /**
     * Hook callback for entry_before_insert
     */
    public function beforeInsert($entry) {
        // Your logic here
        return $entry;
    }

    #[\Override]
    public function handleConfigureAction(): void {
        parent::handleConfigureAction();

        if (Minz_Request::isPost()) {
            // Handle configuration form submission
            $enabledFeedsRaw = Minz_Request::paramArray('enabled_feeds', plaintext: true);
            $enabledFeeds = [];

            foreach ($enabledFeedsRaw as $k => $v) {
                if (is_string($k) && ctype_digit($k)) {
                    $enabledFeeds[(int)$k] = true;
                } elseif (is_int($k)) {
                    $enabledFeeds[$k] = true;
                }
            }

            $this->setUserConfiguration([
                'enabled_feeds' => $enabledFeeds,
            ]);

            Minz_Log::notice('YourExtension: Configuration saved');
        }
    }
}
```

**Legacy pattern (older extensions):**

```php
<?php

class YourExtensionExtension extends Minz_Extension {
    public function init() {
        // Register hooks using static method
        $this->registerHook('entry_before_insert', array('YourExtensionExtension', 'processEntry'));
    }

    public static function processEntry($entry) {
        // Your logic here
        return $entry;
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {
            // Handle configuration
        }
    }
}
```

### Optional Files

#### 3. configure.phtml (Optional)

Configuration UI template. Used when the extension needs user configuration.

**Standard pattern with per-feed toggles:**

```php
<?php
/** @var YourExtensionExtension $this */
$enabled = $this->getUserConfigurationValue('enabled_feeds', []);
if (!is_array($enabled)) {
    $enabled = [];
}
$feedDAO = FreshRSS_Factory::createFeedDao();
$feeds = $feedDAO->listFeeds(); // array<int, FreshRSS_Feed>
?>
<form method="post" action="<?= _url('extension', 'configure', 'e', urlencode($this->getName())) ?>">
    <input type="hidden" name="_csrf" value="<?= FreshRSS_Auth::csrfToken() ?>"/>

    <fieldset>
        <legend>Extension Settings</legend>

        <div class="form-group">
            <label>Enable for feeds</label>
            <div class="checkboxes-list">
                <?php foreach ($feeds as $id => $feed): ?>
                    <label style="display:block;">
                        <input type="checkbox" name="enabled_feeds[<?= (int)$id ?>]"
                               value="1" <?= !empty($enabled) && !empty($enabled[$id]) ? 'checked' : '' ?> />
                        <?= htmlspecialchars($feed->name(), ENT_QUOTES, 'UTF-8') ?>
                        <small>(ID <?= (int)$id ?>)</small>
                    </label>
                <?php endforeach; ?>
                <?php if (empty($feeds)) : ?>
                    <p class="help">No feeds found.</p>
                <?php endif; ?>
                <p class="help">Description of what enabling does.</p>
            </div>
        </div>
    </fieldset>

    <div class="form-group form-actions">
        <div class="group-controls">
            <button class="btn btn-important" type="submit">Save settings</button>
        </div>
    </div>
</form>
```

#### 4. README.md (Recommended)

Documentation for the extension. Should include:
- Description and features
- Installation instructions
- Configuration guide
- Technical details
- Troubleshooting tips
- Changelog

#### 5. i18n/ directory (Optional)

Translation files organized by language code:

```
i18n/
├── en/
│   └── ext.php
└── de/
    └── ext.php
```

**Translation file format (i18n/en/ext.php):**

```php
<?php

return [
    'extensionname' => [
        'config' => [
            'info' => 'Extension description',
            'setting_name' => 'Setting label',
        ],
    ],
];
```

**Register translations in init():**

```php
public function init(): void {
    parent::init();
    $this->registerTranslates();
    // ... rest of init
}
```

#### 6. static/ directory (Optional)

Static assets like JavaScript or CSS files.

**Example structure:**
```
static/
├── configure.js
└── styles.css
```

**Loading static files in init():**

```php
public function init() {
    // Load JavaScript only on extension configuration page
    if (Minz_Request::controllerName() === 'extension') {
        Minz_View::appendScript($this->getFileUrl('configure.js'));
    }
}
```

#### 7. Controllers/ directory (Optional)

Custom controllers for AJAX operations or special actions.

**Example controller (Controllers/yourController.php):**

```php
<?php

class FreshExtension_your_Controller extends FreshRSS_ActionController {
    public function customAction() {
        // Your custom action logic
        $this->view->result = ['status' => 'success'];
        header('Content-Type: application/json');
        echo json_encode($this->view->result);
    }
}
```

**Register controller in init():**

```php
public function init() {
    $this->registerController('your');
    // ... rest of init
}
```

## Available Hooks

FreshRSS provides several hooks that extensions can use:

### Common Hooks

1. **entry_before_insert** (Most common)
   - Called before an entry is inserted into the database
   - Allows modifying entry content, date, or other properties
   - Must return the entry object

   ```php
   public function beforeInsert($entry) {
       // Modify entry
       return $entry;
   }
   ```

2. **entry_before_display**
   - Called before displaying an entry in the UI
   - Useful for runtime modifications without changing stored data

3. **feed_before_insert**
   - Called when a new feed is added

4. **freshrss_init**
   - Called during FreshRSS initialization
   - Use for early setup

### Hook Registration Patterns

**Modern pattern:**
```php
Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'methodName']);
```

**Legacy pattern:**
```php
$this->registerHook('entry_before_insert', array('ClassName', 'methodName'));
```

## Configuration Management

### Saving Configuration

```php
public function handleConfigureAction(): void {
    parent::handleConfigureAction();

    if (Minz_Request::isPost()) {
        $setting = Minz_Request::paramString('setting_name');

        $this->setUserConfiguration([
            'setting_name' => $setting,
        ]);
    }
}
```

### Reading Configuration

```php
// In hook methods or other class methods
$setting = $this->getUserConfigurationValue('setting_name', 'default_value');
```

### Per-Feed Configuration Pattern

This is the standard pattern used across all extensions in this repository:

```php
// In handleConfigureAction():
$enabledFeedsRaw = Minz_Request::paramArray('enabled_feeds', plaintext: true);
$enabledFeeds = [];

foreach ($enabledFeedsRaw as $k => $v) {
    if (is_string($k) && ctype_digit($k)) {
        $enabledFeeds[(int)$k] = true;
    } elseif (is_int($k)) {
        $enabledFeeds[$k] = true;
    }
}

$this->setUserConfiguration([
    'enabled_feeds' => $enabledFeeds,
]);

// In hook method:
$enabledFeeds = $this->getUserConfigurationValue('enabled_feeds', []);
if (!is_array($enabledFeeds)) {
    $enabledFeeds = [];
}

$feedId = method_exists($entry, 'feedId') ? (int)$entry->feedId() : 0;
$apply = ($feedId > 0) && !empty($enabledFeeds[$feedId]);

if (!$apply) {
    return $entry; // Skip processing for this feed
}
```

## Entry Object Methods

Common methods available on `$entry` objects:

```php
$entry->content()        // Get entry content (HTML)
$entry->_content($html)  // Set entry content

$entry->title()          // Get entry title
$entry->_title($title)   // Set entry title

$entry->link()           // Get entry URL
$entry->_link($url)      // Set entry URL

$entry->date()           // Get entry timestamp
$entry->_date($timestamp) // Set entry timestamp

$entry->feed()           // Get feed object
$entry->feedId()         // Get feed ID

// Feed object methods:
$entry->feed()->id()     // Feed ID
$entry->feed()->name()   // Feed name/title
$entry->feed()->url()    // Feed URL
```

## Logging

FreshRSS provides logging functionality via `Minz_Log`:

```php
Minz_Log::notice('Extension: Info message');
Minz_Log::warning('Extension: Warning message');
Minz_Log::error('Extension: Error message');
Minz_Log::debug('Extension: Debug message');
```

**Best practices:**
- Prefix messages with extension name
- Log initialization, configuration saves, and processing actions
- Log errors with context information
- Use appropriate log levels

## Common Patterns

### Content Modification with Regex

```php
private function processContent(string $content): string {
    $pattern = '#<pattern>.*?</pattern>#is';
    $replacement = '<new>content</new>';

    return preg_replace($pattern, $replacement, $content);
}
```

### HTTP Requests

```php
$url = 'https://api.example.com/data';
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'FreshRSS/YourExtension',
    ],
]);

$data = @file_get_contents($url, false, $context);

if ($data === false) {
    Minz_Log::warning('YourExtension: Failed to fetch data');
    return null;
}

$json = json_decode($data, true);
```

### Validating and Processing Images

```php
// Download image
$imageData = @file_get_contents($imageUrl, false, $context);

// Check file size
$fileSize = strlen($imageData);
if ($fileSize > 5242880) { // 5MB
    Minz_Log::warning('Image too large: ' . $fileSize . ' bytes');
    return null;
}

// Detect MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_buffer($finfo, $imageData);
finfo_close($finfo);

// Convert to base64
$base64 = base64_encode($imageData);
$dataUri = 'data:' . $mimeType . ';base64,' . $base64;
```

## Directory Naming Convention

Extensions must be named with the `xExtension-` prefix:

```
xExtension-YourExtensionName/
├── extension.php
├── metadata.json
├── configure.phtml (optional)
├── README.md (recommended)
├── i18n/ (optional)
├── static/ (optional)
└── Controllers/ (optional)
```

The folder name should match: `xExtension-{EntrypointName}`

## Code Style Guidelines

1. **Modern extensions should use:**
   - `declare(strict_types=1);` at the top
   - Type hints for parameters and return types
   - `final class` for extension classes
   - `#[\Override]` attribute for overridden methods

2. **Security:**
   - Always use `htmlspecialchars($data, ENT_QUOTES, 'UTF-8')` for output
   - Use CSRF tokens in forms: `<?= FreshRSS_Auth::csrfToken() ?>`
   - Validate and sanitize all user input
   - Use `@` error suppression carefully (only for known unsafe functions)

3. **Error Handling:**
   - Check return values from functions
   - Use try-catch for risky operations
   - Always return the entry object from hooks, even on error

4. **Documentation:**
   - Add PHPDoc comments for all public methods
   - Document parameters and return types
   - Explain complex logic with inline comments

## Testing Your Extension

1. **Installation:**
   - Copy folder to FreshRSS `extensions/` directory
   - Enable in Settings → Extensions

2. **Debugging:**
   - Check logs at `data/users/[username]/log.txt`
   - Use `Minz_Log::debug()` for debugging output
   - Test with different feed types and content

3. **Configuration:**
   - Test form submission and persistence
   - Verify per-feed toggles work correctly
   - Check that settings survive refresh

## Example: Minimal Extension

Here's a complete minimal extension that modifies entry content:

**metadata.json:**
```json
{
  "name": "Example",
  "author": "Sascha Krug",
  "description": "Example extension that uppercases titles",
  "version": "1.0.0",
  "entrypoint": "Example",
  "type": "user"
}
```

**extension.php:**
```php
<?php

declare(strict_types=1);

final class ExampleExtension extends Minz_Extension {
    #[\Override]
    public function init(): void {
        Minz_ExtensionManager::addHook('entry_before_insert', [$this, 'beforeInsert']);
        Minz_Log::notice('Example extension initialized');
    }

    public function beforeInsert($entry) {
        $title = $entry->title();
        $entry->_title(strtoupper($title));
        Minz_Log::notice('Example: Uppercased title');
        return $entry;
    }
}
```

That's it! No configuration needed for this simple example.

## Reference Extensions

Study these extensions in this repository for complete examples:

1. **FixYoutubeEmbedding** - Simple regex replacement with per-feed config
2. **FixXEmbedding** - HTTP requests to external API
3. **UpdatePubDateNow** - Date modification with timezone handling
4. **Replacer** - Complex configuration with custom controller and JavaScript
5. **InlineImages** - Image processing with base64 encoding
6. **AiConverter** - OpenAI API integration

## Common Pitfalls

1. **Class naming**: Class name must match entrypoint + "Extension" suffix
2. **Hook returns**: Always return the entry object from hooks
3. **Configuration persistence**: Use `setUserConfiguration()`, not manual saves
4. **Feed ID checking**: Always validate feed ID exists before processing
5. **HTML escaping**: Escape output in templates with htmlspecialchars()
6. **CSRF protection**: Always include CSRF token in forms
7. **Method existence**: Check if methods exist before calling: `method_exists($entry, 'content')`

## Version Control

When updating extensions:
1. Increment version in metadata.json
2. Document changes in README.md changelog
3. Test thoroughly before committing
4. Use semantic versioning (MAJOR.MINOR.PATCH)

---

**Remember**: All extensions must attribute "Sascha Krug" as the author in metadata.json!
