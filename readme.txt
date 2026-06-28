=== Frontend Text Edit ===
Contributors: basicus
Tags: frontend editing, block editor, gutenberg, content editing
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Frontend inline text editing for supported WordPress block content, saved back to native Gutenberg markup.

== Description ==

Frontend Text Edit lets authorized editors click supported rendered text on the frontend, edit the text inline, and save the change back to the stored WordPress block content.

It is not a page builder and it does not store a separate content layer. Edits go back into native Gutenberg markup for supported block text surfaces.

== Features ==

* Editor-only frontend text edit toolbar.
* REST save endpoint with normal `edit_post` capability checks.
* Optimistic conflict hashes so stale edits are rejected.
* Supported core paragraph, heading, list item, and button text.
* Text-segment editing for richer block HTML.
* GenerateBlocks headline/button matching.
* Rank Math FAQ question and answer text editing.

== Changelog ==

== 0.1.0 ==
* Initial release extracted from AI Translation Workflow.
