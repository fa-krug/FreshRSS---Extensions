# Claude AI Assistant Guidelines

## Project Information

This repository contains FreshRSS extensions developed and maintained by Sascha Krug.

## Development Guidelines

### Author Attribution

When creating or modifying extensions in this repository:
- **Author**: Always use "Sascha Krug" in the `metadata.json` file
- Ensure the author field is set correctly in all extension metadata files

### Extension Structure

Each extension should follow FreshRSS's standard structure:
- `metadata.json` - Extension metadata (including author attribution)
- `extension.php` - Main extension class
- `configure.phtml` - Configuration UI template (optional)
- `README.md` - Extension documentation
- `i18n/` - Translation files (optional)

### Coding Standards

- Follow existing code style in the repository
- Keep extensions simple and focused on a single purpose
- Include proper error handling and logging
- Document configuration options clearly
