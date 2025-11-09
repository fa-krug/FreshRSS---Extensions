# AiConverter Extension for FreshRSS

Convert RSS feed content using OpenAI-compatible APIs to extract and reformat articles automatically.

## Features

- **OpenAI-Compatible API Support**: Works with OpenAI, Azure OpenAI, LocalAI, Ollama (with OpenAI compatibility layer), and other compatible endpoints
- **Global Configuration**: Set a default API endpoint, access token, and prompt for all feeds
- **Per-Feed Control**: Enable/disable AI conversion for each feed individually
- **Custom Prompts**: Override the default prompt on a per-feed basis for specialized processing
- **Automatic Processing**: Articles are processed automatically when new entries are imported
- **Feed Reload**: Easily reprocess existing feeds after changing settings

## Installation

1. Copy or clone this repository to your FreshRSS extensions directory:
   ```bash
   cd /var/www/FreshRSS/extensions
   git clone [repository-url] xExtension-AiConverter
   ```

2. Enable the extension in FreshRSS:
   - Go to Settings → Extensions
   - Find "AiConverter" in the list
   - Click the checkbox to enable it

3. Configure the extension:
   - Click "Configure" next to the AiConverter extension
   - Set your API endpoint and access token
   - Customize the default prompt (optional)
   - Enable AI conversion for desired feeds

## Configuration

### Global Settings

- **API Endpoint**: The OpenAI-compatible API endpoint (default: `https://api.openai.com/v1/chat/completions`)
- **API Access Token**: Your API access token (e.g., `sk-...` for OpenAI)
- **Default Prompt**: The default prompt template used for all feeds

### Per-Feed Settings

For each feed, you can:
- **Enable AI Conversion**: Toggle AI processing for this specific feed
- **Custom Prompt**: Override the default prompt with a feed-specific prompt
- **Reload Feed**: Delete all entries and reload the feed to reprocess with current settings

## Default Prompt

The extension comes with a pre-configured prompt designed to extract and reformat article content:

```
Please extract and reformat the main content from the following article. Return the result as clean HTML containing:
1. The main text content in readable paragraphs
2. A header image (if available) at the top using an <img> tag
3. At the bottom, add a right-aligned link to the original article with the text "Read original article"

Make sure to preserve important formatting and structure while removing ads, navigation, and other non-essential elements.
```

You can customize this prompt globally or per-feed to match your specific needs.

## How It Works

1. When a new article is imported from an RSS feed, the extension checks if AI conversion is enabled for that feed
2. If enabled, it takes the configured prompt (custom or default) and appends the article content
3. The combined message is sent to the configured API endpoint with your access token
4. The AI's response replaces the original article content in your FreshRSS database
5. You see the processed, reformatted content when reading the article

## API Compatibility

This extension is designed to work with any OpenAI-compatible API endpoint, including:

- **OpenAI**: `https://api.openai.com/v1/chat/completions`
- **Azure OpenAI**: `https://YOUR-RESOURCE.openai.azure.com/openai/deployments/YOUR-DEPLOYMENT/chat/completions?api-version=2024-02-15-preview`
- **LocalAI**: `http://localhost:8080/v1/chat/completions`
- **Ollama** (with OpenAI compatibility): `http://localhost:11434/v1/chat/completions`
- Other compatible services

The extension sends requests in OpenAI's chat completion format with the `gpt-4o-mini` model specified by default.

## Usage Tips

- **Test with One Feed First**: Enable AI conversion on a single, low-volume feed to test your configuration
- **Monitor API Costs**: Each article processed requires an API call, which may incur costs depending on your provider
- **Adjust Prompts**: Experiment with different prompts to get the best results for your use case
- **Use Feed Reload Sparingly**: The reload feature deletes all entries and re-imports them, which processes all articles through the AI

## Troubleshooting

- **No content being processed**: Check that AI conversion is enabled for the feed and that your API token is correct
- **API errors**: Check the FreshRSS logs (`data/users/*/log.txt`) for detailed error messages
- **Timeout errors**: Increase the timeout value in `extension.php` if your API is slow to respond
- **Formatting issues**: Adjust your prompt to specify the desired output format more precisely

## Privacy & Security

- API access tokens are stored securely in FreshRSS user configuration
- Tokens are never logged or exposed in the UI
- Article content is sent to the configured API endpoint for processing
- Consider using a local AI service (LocalAI, Ollama) if you have privacy concerns about sending content to external APIs

## Requirements

- FreshRSS 1.20.0 or higher
- PHP with cURL support
- Access to an OpenAI-compatible API endpoint

## License

This extension is provided as-is for use with FreshRSS.

## Author

Sascha Krug

## Contributing

Issues and pull requests are welcome!
