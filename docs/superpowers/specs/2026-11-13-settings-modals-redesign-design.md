# Design Document: Settings Modals Redesign & Extension Awareness

**Date:** 2026-11-13  
**Status:** Proposed  
**Author:** Pair Programming Agent & User  

---

## 1. Intent and Context

Both the document/chapter settings modal (`#edit-chapter-modal` / `templates/modals/edit_chapter.php`) and category/folder settings modal (`#edit-book-modal` / `templates/modals/edit_category.php`) have grown over multiple releases to include over 16 and 9 fields, respectively. In their current state:
- They present intimidating, tall, single-column vertical forms where routine edits (editing a title or selecting a theme) are intermingled with technical plumbing (slug renaming, raw file paths, replacement uploads, share keys, locks, and danger zone deletions).
- Features provided by optional extensions—such as **Document Comments** (`comments`) and **Discussion / Talk Pages** (`talk`), as well as dynamic page types—are unconditionally rendered even when disabled site-wide in `qwiki.json`.
- Users configuring a category or chapter face visual clutter and unclear hierarchies.

The objective is to redesign both modals into an unintimidating, clean, tabbed structure mirroring the established Qwiki Settings modal UX, while ensuring that all extension-specific settings are visible only when their corresponding extensions are active.

---

## 2. Goals, Non-Goals & Anti-Goals

### Goals
1. **Logical Grouping via Tabs**:
   - Structure `edit_chapter.php` into 3–4 clean tabs: **General**, **Display & SEO**, **Sharing & Access**, and (if active) **Collaboration**.
   - Structure `edit_category.php` into 3 clean tabs: **General**, **Access & Security**, and **Feeds & Features**.
2. **Extension Awareness**:
   - Hide comments settings when `comments` extension is disabled.
   - Hide discussion/talk settings when `talk` extension is disabled.
   - For `edit_chapter.php`, if neither `comments` nor `talk` is active, omit the Collaboration tab entirely so no empty or redundant tabs exist.
   - For Document Type dropdown in `edit_chapter.php`, restrict selectable page types to enabled ones via `ExtensionManager::getEnabledPageTypes()`, while preserving any disabled type if already active on the current document.
   - Align `create_chapter.php` and `create_category.php` so their `allowComments` and `allowTalk` controls are similarly hidden when the corresponding extension is disabled.
3. **Preserve Form & API Compatibility**:
   - Retain all existing HTML input names and IDs (`edit-chapter-title`, `edit-chapter-slug`, `edit-book-title-input`, etc.).
   - Ensure standard form submissions (`FormData(form)`) seamlessly serialize inputs across all tabs.
   - Ensure tab switching defaults to the first tab whenever either modal is opened.
4. **Preserve Test Suite Compliance**:
   - Keep all static assertions in `tests/test_talk_ui.js`, `tests/edit_chapter_category_test.php`, and `tests/test_settings_modal_tabs.js` passing.

### Non-Goals
- Altering the backend API endpoints (`api/admin.php`) or modifying JSON schema in `qwiki.json`.
- Modifying unrelated modals (such as `theme_editor.php`, `users.php`, `settings.php`).

### Anti-Goals
- Hiding core document fields behind confusing multi-level accordions or obscure dropdowns.
- Breaking existing documents whose types belong to an extension that has been toggled off.

---

## 3. Approaches Considered

1. **Approach 1: Tabbed Interface Mirroring Qwiki Settings (Selected)**:
   - Uses the existing `.modal-tabs`, `.tab-btn`, and `.tab-content` system already styled and tested in `qwiki.css` and `app.js`.
   - Clean, compact, accessible, and familiar to existing Qwiki administrators.
   - Dynamically includes or excludes the Collaboration tab based on active extension state.
2. **Approach 2: Single-Scroll Grouped Cards**:
   - Retains a single vertical scroll but groups inputs into distinct visual card sections.
   - *Rejected*: Still results in very tall modals on mobile/laptop screens and lacks the cohesive feel of the tabbed Wiki Settings modal.
3. **Approach 3: Collapsible Accordions**:
   - Uses collapsible accordions for advanced settings.
   - *Rejected*: Adds unnecessary interaction friction and inconsistent design patterns compared to Qwiki's standard modal tabs.

---

## 4. Detailed Specification & Architecture

### A. Document / Chapter Settings Modal (`templates/modals/edit_chapter.php`)

**Modal ID:** `#edit-chapter-modal`  
**Max Width:** `680px`  
**Tab Header (`.modal-tabs`):**
1. **📄 General** (`data-tab="edit-chapter-tab-general"`):
   - Document Title (`#edit-chapter-title`, required)
   - Category / Folder Placement (`#edit-chapter-category`, select dropdown)
   - Document Type (`#edit-chapter-type`, select dropdown containing standard types + enabled extension page types)
   - Conditional Source Location:
     - Google Doc Published URL (`#edit-chapter-url`) & Edit URL (`#edit-chapter-editurl`)
     - File Path (`#edit-chapter-file`, readonly)
   - Replace Document Content Upload (`#edit-chapter-replacement-file`)
   - Document Slug (URL Identifier) (`#edit-chapter-slug`, with helper explanation)
2. **🎨 Display & SEO** (`data-tab="edit-chapter-tab-seo"`):
   - Document Theme Selector (`#edit-chapter-theme`)
   - Short Description for social sharing & search (`#edit-chapter-description`)
   - Social Share Image URL (`#edit-chapter-image`)
3. **🔗 Sharing & Access** (`data-tab="edit-chapter-tab-sharing"`):
   - Allow Public Sharing Checkbox (`#edit-chapter-public-shareable`)
   - Unique Share Key Display & Reset Button (`#edit-chapter-share-key`, `#btn-edit-modal-reset-key`)
   - Document Lock Checkbox (`#edit-chapter-readonly-input` + helper text `#edit-chapter-readonly-help`)
4. **💬 Collaboration** (`data-tab="edit-chapter-tab-collab"` - *Only rendered if `comments` or `talk` extension is enabled*):
   - Comments Policy Dropdown (`#edit-chapter-allow-comments`, inherit / enabled / disabled) - *rendered if `comments` enabled*
   - Discussion & Talk Page Policy Dropdown (`#edit-chapter-allow-talk`, inherit / enabled / disabled) - *rendered if `talk` enabled*

**Footer Action:**
- Submit button `#btn-submit-edit-chapter` labeled `Save Document Details`.

---

### B. Category / Folder Settings Modal (`templates/modals/edit_category.php`)

**Modal ID:** `#edit-book-modal`  
**Max Width:** `620px`  
**Tab Header (`.modal-tabs`):**
1. **📁 General** (`data-tab="edit-book-tab-general"`):
   - Category Title (`#edit-book-title-input`, required)
   - Category Description (`#edit-book-description-input`, optional tooltip)
   - Category Theme Selector (`#edit-book-theme-input`)
2. **🔒 Access & Security** (`data-tab="edit-book-tab-access"`):
   - Visibility Level (`#edit-book-visibility-input`: Public, Logged in only, Admins only)
   - Lock Category Checkbox (`#edit-book-readonly-input` + `#edit-book-readonly-help`)
   - Danger Zone Container: Delete Category button (`#btn-delete-book`) & Protected Category notice (`#edit-book-protected-notice`)
3. **📡 Feeds & Features** (`data-tab="edit-book-tab-features"`):
   - Category RSS Feed URL (`#edit-book-rss-url` + `#btn-copy-cat-rss`)
   - Allow Comments Checkbox (`#edit-book-allow-comments-input`) - *rendered if `comments` enabled*
   - Enable Discussion & Talk Checkbox (`#edit-book-allow-talk-input`) - *rendered if `talk` enabled*

**Footer Action:**
- Submit button labeled `Save Category Details`.

---

### C. Creation Modals Extension Alignment (`create_chapter.php`, `create_category.php`)

- In `templates/modals/create_chapter.php`:
  - Wrap `#create-chapter-allow-comments` in `if ($extManager->isExtensionEnabled('comments'))`.
  - Wrap `#create-chapter-allow-talk` in `if ($extManager->isExtensionEnabled('talk'))`.
- In `templates/modals/create_category.php`:
  - Wrap `#create-category-allow-comments` in `if ($extManager->isExtensionEnabled('comments'))`.
  - Wrap `#create-category-allow-talk` in `if ($extManager->isExtensionEnabled('talk'))`.

---

### D. CSS & Layout Details (`assets/css/qwiki.css`)

Ensure `.modal-card` for `#edit-chapter-modal` and `#edit-book-modal`:
- Uses flex column layout with `max-height: 90vh`.
- Has an inner scroll wrapper `.modal-tab-scroll` for the `.tab-content` areas.
- Retains fixed/pinned headers and footers so the "Save" button is always readily visible without scrolling to the bottom.

---

### E. JavaScript Modal State Management (`assets/js/app.js`)

- In the opening handler for `edit-chapter-modal`:
  - Reset active tabs: activate the first tab (`edit-chapter-tab-general`), deactivate others, and update tab buttons.
- In the opening handler for `edit-book-modal`:
  - Reset active tabs: activate the first tab (`edit-book-tab-general`), deactivate others, and update tab buttons.
- Tab click listeners already bound to `.tab-btn` automatically handle tab switching without modification.

---

## 5. Verification Plan

1. **Automated Unit & Integration Tests**:
   - Run `node tests/test_talk_ui.js`: Verifies presence of `allowTalk` controls in all 4 modals.
   - Run `php tests/edit_chapter_category_test.php`: Verifies category prefilling and preservation on chapter edits.
   - Run `node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php`: Verifies core lock and player suites.
2. **New Dedicated Regression Test**:
   - Create `tests/test_settings_modals_redesign.js` verifying:
     - Document modal tab buttons and panes exist.
     - Category modal tab buttons and panes exist.
     - When `comments` or `talk` is disabled, the respective controls and tab are suppressed.
     - When enabled, they render properly with correct IDs and attributes.
3. **Manual Browser Smoke Test**:
   - Open Document Settings on various document types (Markdown, PDF, HTML). Verify tabs switch smoothly and saving works.
   - Open Category Settings on root and nested categories. Verify tabs switch smoothly and saving works.
