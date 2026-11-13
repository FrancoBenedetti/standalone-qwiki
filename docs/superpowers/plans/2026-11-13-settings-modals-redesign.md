# Settings Modals Redesign & Extension Awareness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the document/chapter and category/folder settings modals into intuitive, tabbed interfaces with logical grouping, and make extension-dependent settings conditionally visible only when their corresponding extensions are active.

**Architecture:** 
- Restructure `templates/modals/edit_chapter.php` into 4 logical tabs (`General`, `Display & SEO`, `Sharing & Access`, and conditional `Collaboration`).
- Restructure `templates/modals/edit_category.php` into 3 logical tabs (`General`, `Access & Security`, and `Feeds & Features`).
- Extension awareness: `comments` and `talk` settings appear only when `ExtensionManager::isExtensionEnabled()` returns true. If neither extension is active, the chapter `Collaboration` tab is omitted entirely. Type selection in chapter settings filters page types via `ExtensionManager::getEnabledPageTypes()`.
- Align creation modals (`create_chapter.php` and `create_category.php`) so extension settings are conditionally displayed.
- Enhance modal tab layout in `assets/css/qwiki.css` and ensure tabs reset to the initial pane on open in `assets/js/app.js`.

**Tech Stack:** PHP 8+, JavaScript (ES6+), CSS3 (Flexbox/CSS Variables).

**Spec:** `docs/superpowers/specs/2026-11-13-settings-modals-redesign-design.md`

## Global Constraints

- Never break existing documents or categories: all existing HTML form input names and IDs (`edit-chapter-title`, `edit-chapter-slug`, `edit-book-title-input`, etc.) must remain intact.
- Form submissions serialize all fields across all tabs via `new FormData(form)`.
- When an extension is disabled, existing documents of that type must remain editable in metadata without losing their type.
- Keep HTML tags flush-left with 0 leading spaces in templates.
- All existing tests must pass: `node tests/test_talk_ui.js && php tests/edit_chapter_category_test.php && node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php`.

## Review Focus

1. **FormData Tab Completeness**: Inputs residing on inactive tabs must still be included in `FormData` submissions so saving from any active tab persists all fields.
2. **Dynamic Collaboration Tab Suppression**: When both `comments` and `talk` extensions are disabled, the `Collaboration` tab button and pane must be completely absent from the Document modal, leaving no empty tab.
3. **Tab Reset on Open**: Opening the modal a second time must always show the primary General tab, not leave an orphaned secondary tab active from a previous session.
4. **Disabled Page Type Preservation**: If an existing document has a page type whose extension was subsequently disabled, `#edit-chapter-type` must retain that option as selected rather than dropping or corrupting it.
5. **Static Test Suite Compliance**: `tests/test_talk_ui.js` verifies raw file string inclusion of `edit-book-allow-talk-input` and `edit-chapter-allow-talk`; those IDs must remain present in the source files.

---

### Task 1: Regression Test Suite for Settings Modals Redesign & Extension Awareness

**Files:**
- Create: `tests/test_settings_modals_redesign.js`

**Interfaces:**
- Consumes: `templates/modals/edit_chapter.php`, `templates/modals/edit_category.php`, `lib/Core/Config.php`, `lib/Core/ExtensionManager.php`
- Produces: Executable CLI test verifying tab structure, IDs, and extension visibility under diverse configurations.

- [ ] **Step 1: Write the failing unit and regression test**

Create `tests/test_settings_modals_redesign.js` covering:
1. Document modal renders 4 tabs when extensions are enabled (`edit-chapter-tab-general`, `edit-chapter-tab-seo`, `edit-chapter-tab-sharing`, `edit-chapter-tab-collab`).
2. Document modal omits `edit-chapter-tab-collab` when both `comments` and `talk` are disabled.
3. Category modal renders 3 tabs (`edit-book-tab-general`, `edit-book-tab-access`, `edit-book-tab-features`).
4. Category modal omits `allowComments` checkbox when `comments` is disabled, and omits `allowTalk` checkbox when `talk` is disabled.
5. Document modal Type dropdown includes enabled page types and preserves legacy type.
6. All core input IDs (`edit-chapter-title`, `edit-chapter-slug`, `edit-chapter-category`, `edit-book-title-input`, etc.) exist in the rendered output.

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_settings_modals_redesign.js`  
Expected: FAIL (missing tab containers and buttons in current templates).

- [ ] **Step 3: Commit test suite**

```bash
git add tests/test_settings_modals_redesign.js
git commit -m "test: add regression suite for tabbed settings modals redesign"
```

---

### Task 2: Modal Card Tab Styling & CSS Layout

**Files:**
- Modify: `assets/css/qwiki.css:2440-2530`
- Test: `tests/test_settings_modals_redesign.js`

**Interfaces:**
- Produces: CSS classes `#edit-chapter-modal .modal-card`, `#edit-book-modal .modal-card`, `.modal-tab-scroll`, `.modal-form-footer`.

- [ ] **Step 1: Add modal layout and tab scroll CSS to `assets/css/qwiki.css`**

Add responsive flex-column container styling for `#edit-chapter-modal .modal-card` (max-width: 680px) and `#edit-book-modal .modal-card` (max-width: 620px), ensuring:
- `display: flex; flex-direction: column; max-height: 90vh;`
- `.modal-tab-scroll { flex: 1; overflow-y: auto; padding-right: 0.25rem; margin-bottom: 1rem; }`
- Fixed header and footer with `border-top: 1px solid var(--border-color); padding-top: 1rem;`.

- [ ] **Step 2: Verify CSS syntax and rules**

Run: `node -e "const fs = require('fs'); const css = fs.readFileSync('assets/css/qwiki.css', 'utf8'); console.log('CSS length:', css.length);"`  
Expected: PASS.

- [ ] **Step 3: Commit CSS changes**

```bash
git add assets/css/qwiki.css
git commit -m "style: add responsive tabbed modal card and scroll styles"
```

---

### Task 3: Redesign Document / Chapter Settings Modal (`templates/modals/edit_chapter.php`)

**Files:**
- Modify: `templates/modals/edit_chapter.php`
- Test: `tests/test_settings_modals_redesign.js`, `tests/edit_chapter_category_test.php`, `tests/test_talk_ui.js`

**Interfaces:**
- Consumes: `$activeChapter`, `$categoryHierarchy`, `$currentCategoryId`, `$extManager`
- Produces: Tabbed `#edit-chapter-modal` with 4 logical panes and conditional Collaboration tab.

- [ ] **Step 1: Implement the tabbed structure and extension checks in `templates/modals/edit_chapter.php`**

Reorganize the form content:
- Header: Title `Edit Document Entry Details` and close button.
- Tab Buttons:
  - `📄 General` (`data-tab="edit-chapter-tab-general"`, active by default)
  - `🎨 Display & SEO` (`data-tab="edit-chapter-tab-seo"`)
  - `🔗 Sharing & Access` (`data-tab="edit-chapter-tab-sharing"`)
  - `💬 Collaboration` (`data-tab="edit-chapter-tab-collab"` - only if `$extManager->isExtensionEnabled('comments') || $extManager->isExtensionEnabled('talk')`)
- Form with `.modal-tab-scroll`:
  - Pane 1 `edit-chapter-tab-general`: Title (`#edit-chapter-title`), Category (`#edit-chapter-category`), Type (`#edit-chapter-type` using `$extManager->getEnabledPageTypes()`), Source URLs / File path, Replacement upload, Slug (`#edit-chapter-slug`).
  - Pane 2 `edit-chapter-tab-seo`: Theme selector (`#edit-chapter-theme`), Description (`#edit-chapter-description`), Image URL (`#edit-chapter-image`).
  - Pane 3 `edit-chapter-tab-sharing`: Public Sharing (`#edit-chapter-public-shareable`), Share Key (`#edit-chapter-share-key` + Reset button), Lock Document (`#edit-chapter-readonly-input`).
  - Pane 4 `edit-chapter-tab-collab`: Comments policy (`#edit-chapter-allow-comments` if `comments` enabled), Talk page policy (`#edit-chapter-allow-talk` if `talk` enabled).
- Footer: Submit button `#btn-submit-edit-chapter` (`Save Document Details`).

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l templates/modals/edit_chapter.php`  
Expected: No syntax errors detected.

- [ ] **Step 3: Run existing category and talk tests**

Run: `php tests/edit_chapter_category_test.php && node tests/test_talk_ui.js`  
Expected: PASS.

- [ ] **Step 4: Commit changes**

```bash
git add templates/modals/edit_chapter.php
git commit -m "refactor: reorganize document settings modal into tabbed layout with extension awareness"
```

---

### Task 4: Redesign Category / Folder Settings Modal (`templates/modals/edit_category.php`)

**Files:**
- Modify: `templates/modals/edit_category.php`
- Test: `tests/test_settings_modals_redesign.js`, `tests/test_talk_ui.js`

**Interfaces:**
- Consumes: `$extManager`, `$categoryHierarchy`, `$config`
- Produces: Tabbed `#edit-book-modal` with 3 logical panes.

- [ ] **Step 1: Implement the tabbed structure and extension checks in `templates/modals/edit_category.php`**

Reorganize the form content:
- Header: Title `Edit Category` and close button.
- Tab Buttons:
  - `📁 General` (`data-tab="edit-book-tab-general"`, active by default)
  - `🔒 Access & Security` (`data-tab="edit-book-tab-access"`)
  - `📡 Feeds & Features` (`data-tab="edit-book-tab-features"`)
- Form with `.modal-tab-scroll`:
  - Pane 1 `edit-book-tab-general`: Category Title (`#edit-book-title-input`), Description (`#edit-book-description-input`), Theme selector (`#edit-book-theme-input`).
  - Pane 2 `edit-book-tab-access`: Visibility (`#edit-book-visibility-input`), Lock Category (`#edit-book-readonly-input`), Delete Category button (`#btn-delete-book`) and Protected Category notice (`#edit-book-protected-notice`).
  - Pane 3 `edit-book-tab-features`: Category RSS Feed URL & copy button (`#edit-book-rss-url`, `#btn-copy-cat-rss`), Allow Comments checkbox (`#edit-book-allow-comments-input` if `comments` enabled), Enable Discussion & Talk checkbox (`#edit-book-allow-talk-input` if `talk` enabled).
- Footer: Submit button `Save Category Details`.

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l templates/modals/edit_category.php`  
Expected: No syntax errors detected.

- [ ] **Step 3: Run existing talk tests**

Run: `node tests/test_talk_ui.js`  
Expected: PASS.

- [ ] **Step 4: Commit changes**

```bash
git add templates/modals/edit_category.php
git commit -m "refactor: reorganize category settings modal into tabbed layout with extension awareness"
```

---

### Task 5: Align Creation Modals with Extension Awareness (`create_chapter.php`, `create_category.php`)

**Files:**
- Modify: `templates/modals/create_chapter.php`
- Modify: `templates/modals/create_category.php`
- Test: `node tests/test_talk_ui.js`

**Interfaces:**
- Consumes: `$extManager->isExtensionEnabled('comments')`, `$extManager->isExtensionEnabled('talk')`

- [ ] **Step 1: Wrap extension settings in creation modals with extension checks**

In `templates/modals/create_chapter.php`:
- Wrap `#create-chapter-allow-comments` in `<?php if ($extManager->isExtensionEnabled('comments')): ?>`.
- Wrap `#create-chapter-allow-talk` in `<?php if ($extManager->isExtensionEnabled('talk')): ?>`.

In `templates/modals/create_category.php`:
- Wrap `#create-category-allow-comments` in `<?php if ($extManager->isExtensionEnabled('comments')): ?>`.
- Wrap `#create-category-allow-talk` in `<?php if ($extManager->isExtensionEnabled('talk')): ?>`.

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l templates/modals/create_chapter.php && php -l templates/modals/create_category.php`  
Expected: No syntax errors detected.

- [ ] **Step 3: Run talk test suite**

Run: `node tests/test_talk_ui.js`  
Expected: PASS.

- [ ] **Step 4: Commit changes**

```bash
git add templates/modals/create_chapter.php templates/modals/create_category.php
git commit -m "feat: align creation modals with extension activation status"
```

---

### Task 6: JavaScript Tab Reset on Modal Open (`assets/js/app.js`)

**Files:**
- Modify: `assets/js/app.js:59-198, 1080-1170`
- Test: `node tests/test_settings_modals_redesign.js`

**Interfaces:**
- Consumes: DOM elements `#edit-chapter-modal`, `#edit-book-modal`
- Produces: Tab reset logic ensuring first tab is active when opening either modal.

- [ ] **Step 1: Add tab reset logic when opening `#edit-chapter-modal` and `#edit-book-modal`**

In `assets/js/app.js`:
- In `modalId === 'edit-chapter-modal'` block: activate tab button `[data-tab="edit-chapter-tab-general"]` and content pane `#edit-chapter-tab-general`, deactivating any others.
- In `editBookModal` click handler: activate tab button `[data-tab="edit-book-tab-general"]` and content pane `#edit-book-tab-general`, deactivating any others.

- [ ] **Step 2: Verify JavaScript syntax**

Run: `node -c assets/js/app.js`  
Expected: PASS.

- [ ] **Step 3: Commit changes**

```bash
git add assets/js/app.js
git commit -m "feat: reset settings modals to primary tab on open"
```

---

### Task 7: Comprehensive Verification & Test Suite Execution

**Files:**
- Test: All suites

- [ ] **Step 1: Run the new settings modals regression test**

Run: `node tests/test_settings_modals_redesign.js`  
Expected: ALL PASS.

- [ ] **Step 2: Run all existing test suites**

Run:
```bash
node tests/test_talk_ui.js && \
php tests/edit_chapter_category_test.php && \
node tests/test_settings_modal_tabs.js && \
node tests/test_video_embed.js && \
php tests/lock_test.php && \
php tests/lock_integration_test.php
```
Expected: ALL SUITES PASS.

- [ ] **Step 3: Final sanity commit and changelog update**

Update `CHANGELOG.md` with:
- Category & Chapter settings modals tabbed redesign with logical grouping.
- Dynamic extension suppression for comments, talk pages, and page types.
```bash
git add CHANGELOG.md
git commit -m "docs: record settings modals tabbed redesign in changelog"
```
