# Frontend Text Edit

Edit visible WordPress block text directly on the frontend and save changes back to native Gutenberg content.

Frontend Text Edit is built for fast editorial corrections in the real page context. It is not a page builder, frontend dashboard, modal post editor, raw HTML editor, or translation overlay. It marks supported rendered text for authorized editors, lets them edit that text inline, and writes the result back to normal WordPress block markup.

The main product point is portability: edited content remains ordinary WordPress content. If the plugin is disabled later, the text changes stay in the post content; only the frontend editing UI disappears.

## What It Does

- Adds an editor-only frontend text editing mode in the admin bar.
- Lets editors click supported text and save small copy changes in place.
- Saves changes through a REST endpoint with normal `edit_post` capability checks.
- Rejects stale edits with optimistic conflict hashes.
- Supports safe segment-level editing where a block contains richer HTML.
- Sends missing-editable-text reports to `support@devenia.com` for reproducible adapter improvements.

## Current Support

- Core paragraph, heading, list item, and button text.
- Text-node segments inside richer core block HTML.
- Linked list-item text while preserving the existing link URL.
- GenerateBlocks headline and button text.
- Rank Math FAQ question and answer text.

## Why It Is Different

Most WordPress frontend editing plugins focus on full post forms, frontend dashboards, modal editors, raw HTML editing, or translation overlays. Frontend Text Edit is deliberately narrower:

- It edits rendered text in place.
- It saves back to native WordPress block content.
- It avoids page-builder lock-in and custom storage.
- It can be extended with adapters for block libraries and plugins that render their own markup.

## Requirements

- WordPress 6.9 or newer.
- PHP 8.0 or newer.
- A logged-in user with permission to edit the current post.

## Release Notes

### 0.1.2

- Adds missing-editable-text reports to help identify reproducible gaps in block support.
- Sends reports only for publicly reachable pages.

### 0.1.1

- Improves public positioning and documentation for the standalone plugin.

### 0.1.0

- Initial release extracted from AI Translation Workflow.
