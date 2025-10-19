# UpdateDateToNow (FreshRSS extension)

Sets the publication date of incoming entries to “now” just before insertion.

This version adds:
- User-configurable timezone for the "now" value.
- Per‑feed control with a full list of feeds and a toggle for each to decide which feeds can have their dates replaced.

## Installation

1. Place this folder inside your FreshRSS `extensions/` directory as `UpdatePubDateNow`.
2. Enable the extension from FreshRSS: Administration → Extensions.

## Configuration

Open Administration → Extensions → UpdateDateToNow.

Settings available:
- Timezone: Optional PHP timezone identifier (e.g., `Europe/Berlin`, `UTC`). If empty, server time is used. The timestamp is set without changing PHP’s global default timezone.
- Enable for feeds: A list of all your feeds with a checkbox for each. Checked feeds will have their dates replaced; unchecked feeds will keep their original dates.

## How it works

- The extension hooks into `entry_before_insert` and replaces the entry’s publication date with the current timestamp.
- If a timezone is configured, it computes the current time for that timezone; otherwise it uses the server time.
- If a feed is checked in the configuration, new entries from that feed will get their date replaced; others will not.

## Notes

- If you previously used the older “only listed IDs” mode, it will continue to work until you save the new configuration. Once you save, the new per‑feed toggles are used.
- Unix timestamps are absolute; timezone affects how the “current time” is computed, but the stored timestamp remains comparable across timezones.
- If you enter an invalid timezone, the extension falls back to the server time silently.

## Version history

- 1.2.0: Configuration now lists all feeds with a per‑feed toggle.
- 1.1.0: Timezone and per‑feed configuration; README added.
- 1.0.0: Initial release, always set date to now.
