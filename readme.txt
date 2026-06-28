=== Frontend Text Edit ===
Contributors: basicus
Tags: frontend editing, inline editing, block editor, gutenberg, content editing
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Edit visible WordPress block text directly on the frontend and save changes back to native Gutenberg content.

== Description ==

Frontend Text Edit lets authorized editors click supported rendered text on the frontend, edit it inline, and save the change back to the stored WordPress block content.

It is built for fast editorial corrections in the real page context. The plugin does not replace the block editor, open a separate modal editor, store a shadow copy of the page, or create a custom content layer.

When a supported text surface is edited, the updated text is written back into normal Gutenberg markup in `post_content`. If the plugin is later disabled, the edited content remains ordinary WordPress content; only the frontend editing UI stops appearing.

== Why it is different ==

Most frontend editing plugins focus on full post forms, frontend dashboards, modal editors, raw HTML editing, or translation overlays. Frontend Text Edit is deliberately narrower:

* It edits rendered text in place.
* It saves back to native WordPress block content.
* It avoids page-builder lock-in and custom storage.
* It supports segment-level edits inside richer block HTML when whole-block replacement would be unsafe.
* It can be extended with adapters for block libraries and plugins that render their own markup.

== Features ==

* Editor-only frontend text edit toolbar.
* REST save endpoint with normal `edit_post` capability checks.
* Optimistic conflict hashes so stale edits are rejected.
* Supported core paragraph, heading, list item, and button text.
* Text-segment editing for richer block HTML.
* GenerateBlocks headline/button matching.
* Rank Math FAQ question and answer text editing.
* Missing-editable-text reports sent to `support@devenia.com` for reproducible adapter improvements.

== Current support ==

* Core paragraph, heading, list item, and button text.
* Text-node segments inside richer core block HTML.
* Linked list-item text while preserving the existing link URL.
* GenerateBlocks headline and button text.
* Rank Math FAQ question and answer text.

== FAQ ==

= Is this a page builder? =

No. Frontend Text Edit is for text corrections inside existing content. It does not edit layout, design settings, media, templates, or block structure.

= Does it store content in custom tables? =

No. Saved edits are written back into the original WordPress post content as normal Gutenberg block markup.

= What happens if I deactivate the plugin? =

The edited text remains in the post, page, or custom post type. Deactivating the plugin only removes the frontend editing interface.

= Who can edit content? =

Only logged-in users who can normally edit the current post through WordPress permissions can use the frontend text editor.

= Does it edit every block? =

No. It only marks supported text surfaces where the plugin can safely map rendered text back to stored block content.

= Can editors report text that is not editable yet? =

Yes. When frontend editing mode is on, clicking visible text that is not currently editable can send a report with the page URL, text excerpt, selector hint, and site details to `support@devenia.com`.

Reports are sent only for publicly reachable HTTP/HTTPS pages. They are used to identify reproducible gaps for future plugin improvements, not as a support conversation.

== Changelog ==

= 0.1.2 =
* Adds missing-editable-text reports to help identify reproducible gaps in block support.
* Sends reports only for publicly reachable pages.

= 0.1.1 =
* Improves public positioning and documentation for the standalone plugin.

= 0.1.0 =
* Initial release extracted from AI Translation Workflow.
