# Frontend Text Edit

Correct supported text on the WordPress page where you found it. Save the change back to the original Gutenberg blocks, with existing markup and link destinations preserved.

[![Release](https://img.shields.io/badge/release-0.1.9-blue)](https://downloads.devenia.com/frontend-text-edit.zip)
[![License](https://img.shields.io/badge/license-GPLv2%2B-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)](https://www.php.net/)

**Stable tag:** 0.1.9

**Tested up to:** 7.1 (WordPress 7.1 release candidate)

**License:** GPLv2 or later

**Tags:** frontend editing, inline editing, block editor, gutenberg, content editing

## What It Does

Frontend Text Edit gives editors an inline text mode on individual WordPress pages and posts. Supported text maps to its original block content. A saved correction remains ordinary WordPress content after the plugin is deactivated.

For example, change an outdated closing time in a contact page while reading the complete sentence in its page context. The plugin changes the text; the existing block keeps its layout.

## The Real Workflow

1. Install and activate the plugin.
2. Sign in with permission to edit the selected post or page.
3. Open its WordPress frontend and choose **Frontend Text Edit** in the admin bar.
4. Select supported text and make the correction.
5. Choose **Save**, or press Ctrl+Enter or Cmd+Enter. Escape cancels an unsaved edit.
6. Wait for confirmation, reload and read the result. Check any separate cache or publication process your site uses.

The published interface requires an explicit save. An exported static page has no WordPress editing session; open the WordPress source to edit it.

## Why This Feels Different

The existing page supplies the editing context, and the original WordPress blocks remain the storage. There is no shadow page or separate content database to reconcile. The plugin concentrates on small copy corrections rather than editing layout or replacing the block editor.

## Before vs After

| Task | With Frontend Text Edit |
| --- | --- |
| Find a typo during a page review | Select its supported text in context. |
| Keep the existing button destination | Edit the label while retaining the link address. |
| Save after another edit changed the selected block | The stale selection is rejected; reload before retrying. |
| Deactivate the plugin | Saved text remains; editing controls disappear. |

## Who It Is For

WordPress editors, agencies and content teams who correct small wording errors while reviewing rendered pages. Developers can add integrations for further block types through the filters below.

## Requirements

- WordPress 6.9 or later.
- PHP 8.0 or later. Use a maintained PHP version supported by your site.
- A signed-in user with the native `edit_post` permission for the selected content.
- A supported text block rendered on an individual WordPress post or page. Public custom post types can also qualify.

No external service or paid account is required for text editing. WordPress mail delivery is needed only for optional unsupported-text reports.

## Documentation

- [Product page](https://devenia.com/plugins/frontend-text-edit/)
- [Stable plugin ZIP](https://downloads.devenia.com/frontend-text-edit.zip)
- [WordPress plugins](https://devenia.com/plugins/)

## Start Here

Activate the plugin and try one short paragraph on a page you can edit. Change a word, save, reload and confirm the result. If the text cannot be selected, use the WordPress block editor and check the support list below.

## Supported Text

- Core `core/paragraph`, `core/heading`, `core/list-item` and `core/button` blocks where a safe text replacement is possible.
- Supported text-node segments within richer paragraphs and list items.
- GenerateBlocks current `generateblocks/text` blocks, including headings, paragraphs, buttons and linked text, plus legacy `generateblocks/headline` and `generateblocks/button` blocks through the bundled integration.
- Rank Math `rank-math/faq-block` questions and answers through the bundled integration, including synchronisation with FAQ attributes.
- Native post titles and excerpts when an installed integration exposes their rendered text through `frontend_text_edit_virtual_post_field_visible`.

This is not a promise of support for every block in a library. Layout, media, block structure and link destinations remain work for the WordPress editor. A title appearing in a header does not automatically enable title editing.

## Public Interfaces

### REST endpoints

All endpoints use the `frontend-text-edit/v1` namespace and require the native permission to edit the requested `post_id`. The browser interface sends the WordPress REST nonce with its logged-in cookie session.

| Method and route | Inputs | Result |
| --- | --- | --- |
| `GET /text` | `post_id` | Supported items with stable path, current text, label and conflict hash. |
| `POST /text` | `post_id`, `path`, `text`, `hash` | Saves one supported text selection and returns its updated item, or an error. |
| `POST /report` | `post_id`, `text`; optional `selector`, `url` | Sends an unsupported-text report if the supplied page is publicly reachable and recipients are configured. |

Use the returned item path and hash; do not construct them or retain a hash after another edit. A response body with `success: false` is a failed operation even when its HTTP status is successful.

### Extension filters

| Filter | Purpose |
| --- | --- |
| `frontend_text_edit_supported_block_names` | Select block names eligible for simple text replacement. |
| `frontend_text_edit_button_block_names` | Identify blocks whose editable text sits inside an anchor. |
| `frontend_text_edit_segment_block_names` | Select blocks eligible for individual rich-text segments. |
| `frontend_text_edit_updated_block` | Synchronise block attributes with the changed HTML; receives the block and edit context. |
| `frontend_text_edit_stable_render_class` | Match a library's stable rendered class; receives the current class, HTML and classes. |
| `frontend_text_edit_rendered_segment_selectors` | Locate rendered text segments for a supported block. |
| `frontend_text_edit_virtual_post_field_visible` | Expose a rendered native post title or excerpt; receives visibility, post and field. |
| `frontend_text_edit_context_allowed` | Narrow eligibility for the current frontend context. |
| `frontend_text_edit_post_type_allowed` | Select eligible post types. |
| `frontend_text_edit_report_recipients` | Change or disable the local administrator recipients. |

The `frontend_text_edit_updated` action fires after a successful save with the post ID and edit details. `frontend_text_edit_missing_text_reported` fires after a successful report with the post ID, report and recipients.

## Usage Examples

### Use the native item/save protocol

From an authenticated WordPress client with a valid REST nonce:

```js
const root = '/wp-json/frontend-text-edit/v1';
const headers = {
  'Content-Type': 'application/json',
  'X-WP-Nonce': restNonce
};
const listed = await fetch(`${root}/text?post_id=123`, {
  credentials: 'same-origin', headers
}).then(response => response.json());
const selected = listed.items.find(item => item.text === 'Open until 17:00');
if (!listed.success || !selected) throw new Error('Select a supported item first.');
const saved = await fetch(`${root}/text`, {
  method: 'POST', credentials: 'same-origin', headers,
  body: JSON.stringify({
    post_id: 123, path: selected.path, hash: selected.hash,
    text: 'Open until 18:00'
  })
}).then(response => response.json());
if (!saved.success) throw new Error(saved.message || 'Save failed.');
```

Supply your actual post ID and nonce. An API client can use its established WordPress REST authentication instead of browser cookies.

### Disable unsupported-text report emails

```php
add_filter( 'frontend_text_edit_report_recipients', '__return_empty_array' );
```

This leaves text editing available. No email is sent when there are no recipients.

## Safety and Content Ownership

- Editing uses WordPress permissions and plain-text sanitisation. Saved text is escaped inside existing markup.
- A per-selection hash rejects edits after the stored block or supported post field changes. This is not a collaborative page lock or an atomic multi-editor transaction.
- The current post owns its stored content. Changing one language does not translate other language versions.
- Existing link destinations are retained. Use WordPress to change URLs, layout or media.
- Reports default to the local WordPress administrator. They include the site, page URL, title, post ID, selector hint and up to 500 characters of selected text, without adding the signed-in user's identity. The selected text itself may contain private information; review it before sending.
- Sites can change or disable report recipients. Reports require a publicly reachable HTTP or HTTPS page.
- Deactivation leaves saved content in WordPress. Cache invalidation and separate static publication remain the site's responsibility.

## Installation

Download the stable ZIP. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**, upload the ZIP, install and activate it. Sign in, open a page you can edit, and enable **Frontend Text Edit** from the admin bar.

## Recent Changes

### 0.1.9

- Makes current GenerateBlocks text blocks editable, including button labels and linked text.
- Preserves surrounding markup, link destinations and global style references when saving.

### 0.1.8

- Preserves literal dollar amounts and replacement-like text such as `$1` during simple block and button edits.
- Preserves backslashes in text received through the REST editor protocol.

### 0.1.6

- Defaults unsupported-text reports to the local WordPress administrator.
- Exposes provider-specific native title and excerpt detection through a filter.

### 0.1.5

- Adds editing for native post excerpts exposed by an installed integration.

## Contributing

Keep changes focused on safe text editing and native WordPress storage. Include a reproducible example for unsupported blocks and preserve existing formatting and links. The local behavioural contract runs with `php tests/literal-text-contract.php`.

## License

GPLv2 or later. See the [GPL licence](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

[basicus](https://profiles.wordpress.org/basicus/)

## Links

- [Frontend Text Edit](https://devenia.com/plugins/frontend-text-edit/)
- [Download](https://downloads.devenia.com/frontend-text-edit.zip)
- [Other plugins](https://devenia.com/plugins/)
