# Frontend Text Edit

Edit visible WordPress block text directly on the frontend and save changes back to native Gutenberg content.

[![GitHub release](https://img.shields.io/github/v/release/bjornfix/frontend-text-edit)](https://github.com/bjornfix/frontend-text-edit/releases)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net)

**Tested up to:** 7.0
**Stable tag:** 0.1.5
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

## What It Does

Frontend Text Edit lets authorized editors click supported rendered text on the frontend, edit it inline, and save the change back to native Gutenberg block content.

It is built for fast editorial corrections in the real page context. It is not a page builder, frontend dashboard, modal post editor, raw HTML editor, or translation overlay.

**Example:** "This FAQ answer has one typo." - Turn on frontend text editing, click the answer on the live page, fix the text, and save it back into normal WordPress content.

## The Real Workflow

In practice, the useful path is simple:

1. install and activate the plugin
2. log in as a user who can edit the current post
3. open the frontend page
4. turn on `Frontend Text Edit` from the admin bar
5. click supported text, edit it, and save
6. report unsupported visible text when it should become editable in a future release

The editor's job is to correct visible copy in context.
The plugin's job is to map that text safely back to stored Gutenberg markup.

## Why This Feels Different

Most WordPress frontend editing plugins focus on full post forms, frontend dashboards, modal editors, raw HTML editing, or translation overlays.

This plugin is different because it keeps the scope narrow:

- edit rendered text in place
- save back to native WordPress block content
- avoid page-builder lock-in and custom storage
- reject stale edits with optimistic conflict hashes
- support adapter work for block libraries and rendered plugin output

That changes the experience from:

- `Open wp-admin, find the page, find the block, make the small copy change`

to:

- `Click the visible text and fix the copy where you saw the problem`

## Before vs After

### Before

- small text fixes require opening the backend editor
- editors need to find the right block from memory
- frontend-only QA notes turn into admin navigation work
- unsupported blocks are hard to notice systematically

### After

- supported text can be corrected directly in context
- edits remain normal WordPress content
- unsupported text can be reported for reproducible future adapter work
- disabling the plugin leaves the edited content behind

## Current Support

- Core paragraph, heading, list item, and button text.
- Presentation titles and excerpts exposed by an installed presentation Adapter.
- Text-node segments inside richer core block HTML.
- Linked list-item text while preserving the existing link URL.
- GenerateBlocks headline and button text.
- Rank Math FAQ question and answer text.

## Who It Is For

This is a good fit for:

- WordPress editors who review copy on the frontend
- agencies maintaining many content-heavy sites
- teams using Gutenberg, GenerateBlocks, and Rank Math FAQ blocks
- operators who want small text corrections without content lock-in
- plugin developers looking for adapter patterns around rendered block text

## Requirements

- WordPress 6.9 or newer.
- PHP 8.0 or newer.
- A logged-in user with permission to edit the current post.

## Documentation

Start with the public plugin page:

- [Plugin Page](https://devenia.com/plugins/frontend-text-edit/)

## Start Here

If you are new to the plugin, use this order:

1. Read the plugin page
2. Download the latest release
3. Install it on WordPress
4. Open a frontend page as an editor
5. Turn on `Frontend Text Edit` from the admin bar
6. Verify that supported text saves back to normal Gutenberg content

## Behavior

- Adds an editor-only frontend text edit mode in the admin bar
- Uses the normal WordPress `edit_post` capability check
- Saves edits through a nonce-protected REST endpoint
- Writes changes back to `post_content`
- Writes supported presentation hero title and excerpt edits back to WordPress post fields
- Keeps edited content as ordinary WordPress block markup
- Reports unsupported visible text to the local WordPress administrator only for publicly reachable pages; recipients remain explicitly filterable
- Does not send logged-in user identity in those report emails
- Does not edit layout, design settings, media, templates, or block structure

## Release Notes

### 0.1.6

- Removes the implicit external report recipient and now defaults to the local WordPress administrator.
- Moves provider-specific virtual post-field detection behind a filter Adapter.

### 0.1.5

- Adds frontend editing for Adapter-provided hero excerpts rendered from the WordPress post excerpt.

### 0.1.4

- Adds frontend editing for Adapter-provided hero titles rendered from the WordPress post title.

### 0.1.3

- Removes the logged-in user identity from missing-editable-text report emails.

### 0.1.2

- Adds missing-editable-text reports to help identify reproducible gaps in block support.
- Sends reports only for publicly reachable pages.

### 0.1.1

- Improves public positioning and documentation for the standalone plugin.

### 0.1.0

- Initial release extracted from AI Translation Workflow.

## Contributing

PRs welcome. Keep changes focused on safe frontend text editing and native Gutenberg content storage.

## License

GPL-2.0+

## Author

[basicus](https://profiles.wordpress.org/basicus/)

## Links

- [Plugin Page](https://devenia.com/plugins/frontend-text-edit/)
- [GitHub Releases](https://github.com/bjornfix/frontend-text-edit/releases)
- [Devenia Plugins](https://devenia.com/plugins/)

## Star and Share

If this plugin helps make WordPress copy fixes easier, please:

- star the repo
- share it with people editing WordPress sites
- point them to the plugin page so they can see what it does

Why do it?

Because practical WordPress editing tools are better when they are easy to find, easy to understand, and easy to verify before use.
