# Standalone Qwiki UX Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver five cohesive UX and authoring enhancements across Standalone Qwiki: native GFM Markdown alert callouts, unambiguous chapter navigation pagers, a modernized Add Document modal with persistent category selection, collapsible left/right sidebars, and an interactive Mermaid diagram lightbox with print/AI improvements.

**Architecture:** All changes build directly onto Standalone Qwiki's existing vanilla JS (`assets/js/`), PHP 8 (`index.php`, `lib/Core/`, `templates/modals/`), and CSS variable architecture (`assets/css/qwiki.css`) without introducing external runtime dependencies.

**Tech Stack:** PHP 8+, Parsedown, Vanilla JavaScript (ES6+), CSS Custom Properties, Mermaid.js, Node.js & PHP CLI test scripts.

**Spec:** [`docs/superpowers/specs/2026-11-13-ux-improvements-design.md`](file:///home/franco/Projects/standalone-qwiki/docs/superpowers/specs/2026-11-13-ux-improvements-design.md)

## Global Constraints

- Do not alter the `qwiki.json` data schema or break existing route/API contracts.
- Preserve all existing extension hooks (`ExtensionManager::renderAddDocumentTabs`, `ExtensionManager::renderAddDocumentForms`).
- Maintain full dark/light theme compatibility via CSS variables and clean print output (`@media print`).
- Do not break existing tests (verify `node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php` and related UI tests).

## Review Focus

- **Multi-line & nested alert blockquotes:** A `> [!CAUTION]` block containing multiple paragraphs, lists, or code blocks must render entirely inside `.markdown-alert-content` without breaking out or leaving raw `[!CAUTION]` text.
- **Root-level Web Link creation:** Selecting a category in the unified `#chapter-modal` master dropdown and switching to `tab-link` (Web Link) with "Place at Top Level" checked must submit `bookId=""`, while unchecking it submits the selected master category.
- **Mobile drawer vs Desktop collapse:** Collapsing the left sidebar on desktop (`.sidebar-collapsed`) must not prevent the mobile off-canvas drawer (`#mobile-toggle` / `.app-sidebar.open`) from opening cleanly at viewports $\le 768\text{px}$.
- **Keyboard shortcut input safety:** Pressing `[` or `]` while typing in `<input>`, `<textarea>`, `[contenteditable]`, or the Toast UI editor must NOT collapse or expand sidebars.
- **Print PDF diagram integrity:** Lightbox controls and floating diagram toolbars must be hidden in `@media print`, and wide Mermaid diagrams must not clip or break awkwardly mid-diagram.

---

### Task 1: Native GFM Markdown Alert Callouts

**Files:**
- Modify: `lib/Core/QwikiParsedown.php:226-455`
- Modify: `assets/css/qwiki.css` (append alert callout & print styles)
- Test: `tests/test_markdown_alerts.php`

**Interfaces:**
- Produces: `QwikiParsedown::blockQuote($Line)` and `QwikiParsedown::blockQuoteComplete($Block)` returning `.markdown-alert.markdown-alert-{type}` HTML blocks.

- [ ] **Step 1: Write the failing test**

Create `tests/test_markdown_alerts.php`:
```php
<?php
require_once __DIR__ . '/../lib/Core/QwikiParsedown.php';
$pd = new \Qwiki\Core\QwikiParsedown();

$cases = [
    ["> [!NOTE]\n> Info body", 'markdown-alert-note', 'Note', 'Info body'],
    ["> [!TIP]\n> Tip body", 'markdown-alert-tip', 'Tip', 'Tip body'],
    ["> [!IMPORTANT]\n> Important body", 'markdown-alert-important', 'Important', 'Important body'],
    ["> [!WARNING]\n> Warn body", 'markdown-alert-warning', 'Warning', 'Warn body'],
    ["> [!CAUTION]\n> Caution body", 'markdown-alert-warning', 'Caution', 'Caution body'],
    ["> [!ALERT]\n> Alert body", 'markdown-alert-danger', 'Alert', 'Alert body'],
    ["> [!NOTE] Custom Title\n> Line 1\n>\n> Line 2", 'markdown-alert-note', 'Custom Title', 'Line 2'],
    ["> Regular quote", '<blockquote>', null, 'Regular quote'],
];

foreach ($cases as [$md, $expectedClass, $expectedTitle, $expectedBody]) {
    $html = $pd->text($md);
    if (strpos($html, $expectedClass) === false) {
        fwrite(STDERR, "FAIL: Expected '$expectedClass' in:\n$html\n");
        exit(1);
    }
    if ($expectedTitle !== null && strpos($html, $expectedTitle) === false) {
        fwrite(STDERR, "FAIL: Expected title '$expectedTitle' in:\n$html\n");
        exit(1);
    }
    if (strpos($html, $expectedBody) === false) {
        fwrite(STDERR, "FAIL: Expected body '$expectedBody' in:\n$html\n");
        exit(1);
    }
    if ($expectedTitle !== null && preg_match('/\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION|ALERT)\]/i', $html)) {
        fwrite(STDERR, "FAIL: Raw tag leaked in:\n$html\n");
        exit(1);
    }
}
echo "PASS: All markdown alert tests passed\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_markdown_alerts.php`
Expected: FAIL because `markdown-alert-note` is not found in the output.

- [ ] **Step 3: Implement GFM alert parsing in `lib/Core/QwikiParsedown.php`**

Override `blockQuote($Line)` and `blockQuoteComplete($Block)` in `QwikiParsedown`:
- In `blockQuote($Line)`, call `parent::blockQuote($Line)`. Check if `$Line['text']` starts with `>` followed by optional whitespace and `[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION|INFO|HINT|DANGER|ALERT|SUCCESS|FAIL)](?:\s+(.*))?`.
- If matched, store alert metadata (`type`, `label`, `cssClass`) on `$Block['alert']` and strip the `[!TYPE]...` marker line from `$Block['element']['handler']['argument']` so only the remaining body lines are parsed as children.
- In `blockQuoteComplete($Block)`, if `isset($Block['alert'])`, transform `$Block['element']` from `blockquote` into `<div class="markdown-alert markdown-alert-{cssClass}">` with two child elements: `<div class="markdown-alert-title">` (containing the inline SVG icon for that type and the title `<span>`) and `<div class="markdown-alert-content">` (wrapping the parsed blockquote child elements).

- [ ] **Step 4: Add alert callout CSS to `assets/css/qwiki.css`**

Add `.markdown-alert`, `.markdown-alert-title`, `.markdown-alert-icon`, `.markdown-alert-content`, and semantic color rules for `--note`, `--tip`, `--important`, `--warning`, and `--danger` (including dark mode and `@media print` border/background overrides).

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/test_markdown_alerts.php`
Expected: `PASS: All markdown alert tests passed`

- [ ] **Step 6: Commit**

```bash
git add lib/Core/QwikiParsedown.php assets/css/qwiki.css tests/test_markdown_alerts.php
git commit -m "feat: add native GFM markdown alert callouts with SVG icons"
```

---

### Task 2: Chapter Navigation Re-Architecture (Pagers & Header Arrows)

**Files:**
- Modify: `index.php:999-1010` (add header prev/next arrows in `#read-actions` or `.content-header`)
- Modify: `index.php:1110-1145` (remove `.toc-nav-buttons` from `#app-toc`, add `.chapter-pager` below `#content-body`)
- Modify: `assets/css/qwiki.css` (style `.chapter-pager`, `.chapter-pager-card`, and `@media print { .chapter-pager { display: none !important; } }`)
- Test: `tests/test_chapter_navigation.php`

**Interfaces:**
- Consumes: `$prevDoc` and `$nextDoc` arrays (`['title' => ..., 'url' => ...]`) computed by `index.php`.
- Produces: `.chapter-pager` bottom cards and `.header-chapter-nav` arrow links.

- [ ] **Step 1: Write the failing test**

Create `tests/test_chapter_navigation.php` asserting that:
- `index.php` does NOT contain `toc-nav-buttons` inside `#app-toc`.
- `index.php` contains `class="chapter-pager"` after `#content-body`.
- `index.php` contains `header-chapter-nav` prev/next arrow links inside `.content-actions`.
- `assets/css/qwiki.css` styles `.chapter-pager` and hides it in `@media print`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_chapter_navigation.php`
Expected: FAIL because `.toc-nav-buttons` is still in `#app-toc` and `.chapter-pager` is missing.

- [ ] **Step 3: Refactor chapter navigation in `index.php` and `assets/css/qwiki.css`**

- Remove lines 1126–1140 (`.toc-nav-buttons`) from `#app-toc` in `index.php`.
- In `#read-actions` (`index.php`), add a `.header-chapter-nav` group with `←` and `→` icon buttons linking to `$prevDoc['url']` and `$nextDoc['url']` (with `title="Previous Chapter: ..."` and `title="Next Chapter: ..."`), rendered when `$activeChapter && ($prevDoc || $nextDoc)`.
- Directly after `<div class="content-body" id="content-body">...</div>`, render `<nav class="chapter-pager" aria-label="Chapter navigation">` with `.chapter-pager-prev` and `.chapter-pager-next` cards (using an empty spacer `<div>` when either `$prevDoc` or `$nextDoc` is null).
- Add responsive grid styles for `.chapter-pager` in `assets/css/qwiki.css` and add `.chapter-pager`, `.header-chapter-nav` to the `@media print` hide list.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_chapter_navigation.php`
Expected: `PASS`

- [ ] **Step 5: Commit**

```bash
git add index.php assets/css/qwiki.css tests/test_chapter_navigation.php
git commit -m "feat: move prev/next chapter navigation from TOC to document pager and header"
```

---

### Task 3: Add Document & Add Category Modals Refactoring + Category Sync

**Files:**
- Modify: `templates/modals/create_chapter.php`
- Modify: `templates/modals/create_category.php`
- Modify: `assets/js/app.js` (sync master `#chapter-modal-category` across all tab forms & handle `tab-link` root override)
- Test: `tests/test_add_chapter_modal.js`

**Interfaces:**
- Produces: `#chapter-modal-category` master `<select>` in `create_chapter.php`, `.modal-tabs` tab bar matching `settings.php`, and `#link-root-override` checkbox in `tab-link`.

- [ ] **Step 1: Write the failing test**

Create `tests/test_add_chapter_modal.js` using Node.js (`assert` + file inspection / JSDOM or structural checks) verifying:
- `templates/modals/create_chapter.php` uses `.modal-tabs` (matching `settings.php`) instead of `.tab-header`.
- `templates/modals/create_chapter.php` has a single master category selector `#chapter-modal-category` above `.modal-tabs`.
- Redundant `<select name="bookId">` elements are removed from `#tab-create-md`, `#tab-upload`, `#tab-gdoc`, `#tab-link`, and `#tab-remote` (replaced by `<input type="hidden" name="bookId">` synced to `#chapter-modal-category`), and `#tab-link` includes a `#link-root-override` checkbox.
- `assets/js/app.js` binds `#chapter-modal-category` `change` events to sync all `input[name="bookId"], select[name="bookId"]` inside `#chapter-modal`.

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_add_chapter_modal.js`
Expected: FAIL because `#chapter-modal-category` does not exist yet.

- [ ] **Step 3: Refactor `create_chapter.php`, `create_category.php`, and `assets/js/app.js`**

- In `templates/modals/create_chapter.php`:
  - Add `.modal-category-selector` block above `.modal-tabs` containing `<select id="chapter-modal-category" class="form-control">` populated with `$categoryHierarchy` and pre-selected to `$currentCategoryId`.
  - Change `<div class="tab-header">` to `<div class="modal-tabs">`.
  - Replace `<select name="bookId">` inside `#tab-create-md`, `#tab-upload`, `#tab-gdoc`, `#tab-link`, `#tab-remote` with `<input type="hidden" name="bookId" value="<?= htmlspecialchars($currentCategoryId) ?>">`.
  - In `#tab-link`, add a checkbox `#link-root-override` labeled `"Place at Top Level (Sidebar Root)"`.
- In `templates/modals/create_category.php`:
  - Align card spacing, `.form-group` margins, and submit button layout with `settings.php`.
- In `assets/js/app.js`:
  - Add `syncChapterModalCategory()` helper: reads `#chapter-modal-category.value` and writes it to all `input[name="bookId"], select[name="bookId"]` inside `#chapter-modal` (unless `#tab-link` has `#link-root-override` checked, in which case `#tab-link`'s `bookId` is set to `""`).
  - Call `syncChapterModalCategory()` on `#chapter-modal-category` change, `#link-root-override` change, tab switch, and when opening `#chapter-modal`.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_add_chapter_modal.js`
Expected: `PASS`

- [ ] **Step 5: Commit**

```bash
git add templates/modals/create_chapter.php templates/modals/create_category.php assets/js/app.js tests/test_add_chapter_modal.js
git commit -m "feat: modernize Add Document modal with unified category selector and tab sync"
```

---

### Task 4: Collapsible Left & Right Sidebars with Persistence & Hotkeys

**Files:**
- Modify: `index.php` (add `#btn-toggle-left-sidebar` in `.app-header` and `#btn-toggle-right-toc` in `#read-actions`)
- Modify: `assets/js/core/sidebar.js` (add `initCollapseControls()`, `localStorage` persistence, and `[`/`]` keyboard shortcuts)
- Modify: `assets/css/qwiki.css` (add `.app-container.sidebar-collapsed` and `.app-container.toc-collapsed` rules with `@media (min-width: 769px)` guard so mobile drawer stays intact)
- Test: `tests/test_sidebar_collapse.js`

**Interfaces:**
- Produces: `window.QwikiApp.sidebar.toggleLeftSidebar()`, `window.QwikiApp.sidebar.toggleRightToc()`.

- [ ] **Step 1: Write the failing test**

Create `tests/test_sidebar_collapse.js` asserting:
- `index.php` contains `#btn-toggle-left-sidebar` and `#btn-toggle-right-toc`.
- `assets/js/core/sidebar.js` implements `toggleLeftSidebar`, `toggleRightToc`, persists `qwiki_sidebar_collapsed` and `qwiki_toc_collapsed` in `localStorage`, and ignores `[` / `]` hotkeys when focus is inside `INPUT`, `TEXTAREA`, `SELECT`, or `isContentEditable`.
- `assets/css/qwiki.css` defines `.app-container.sidebar-collapsed` and `.app-container.toc-collapsed` transitions and hides `#sidebar-resizer` when collapsed.

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_sidebar_collapse.js`
Expected: FAIL because toggle buttons and collapse functions do not exist.

- [ ] **Step 3: Implement collapsible sidebars in `index.php`, `assets/js/core/sidebar.js`, and `assets/css/qwiki.css`**

- In `index.php`:
  - Add `<button type="button" class="btn-icon sidebar-collapse-btn" id="btn-toggle-left-sidebar" title="Toggle Navigation Sidebar ([)" aria-label="Toggle Navigation Sidebar">` in `.brand-container` (after `#mobile-toggle`).
  - Add `<button type="button" class="btn btn-outline btn-sm" id="btn-toggle-right-toc" title="Toggle Table of Contents (])" aria-label="Toggle Table of Contents">` in `#read-actions`.
- In `assets/css/qwiki.css`:
  - Under `@media (min-width: 769px)`, add `.app-container.sidebar-collapsed .app-sidebar { width: 0 !important; min-width: 0 !important; border-right: none; overflow: hidden; }` and `.app-container.sidebar-collapsed #sidebar-resizer { display: none !important; pointer-events: none; }`.
  - Add `.app-container.toc-collapsed .app-toc { width: 0 !important; min-width: 0 !important; padding: 0 !important; border-left: none; overflow: hidden; }`.
  - Add `transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), padding 0.25s ease;` to `.app-sidebar` and `.app-toc`.
- In `assets/js/core/sidebar.js`:
  - Implement `initCollapseControls()` reading `localStorage.getItem('qwiki_sidebar_collapsed')` and `localStorage.getItem('qwiki_toc_collapsed')` on init, binding click handlers and `keydown` (`[` and `]`, skipping `INPUT`, `TEXTAREA`, `SELECT`, `isContentEditable`, or `.toastui-editor`).

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_sidebar_collapse.js`
Expected: `PASS`

- [ ] **Step 5: Commit**

```bash
git add index.php assets/js/core/sidebar.js assets/css/qwiki.css tests/test_sidebar_collapse.js
git commit -m "feat: add collapsible left and right sidebars with persistence and hotkeys"
```

---

### Task 5: Mermaid Diagram Lightbox, Print Optimization, & AI Directives

**Files:**
- Modify: `assets/js/app.js` (add diagram floating toolbar, `#mermaid-lightbox-modal` pan/zoom/download/ESC, and `data-mermaid-rendered` hook)
- Modify: `assets/css/qwiki.css` (style `.diagram-toolbar`, `#mermaid-lightbox-modal`, and `@media print` diagram page-break/hide rules)
- Modify: `AGENTS.md` (add Mermaid Screen & PDF Authoring Directives section)
- Test: `tests/test_mermaid_lightbox.js`

**Interfaces:**
- Produces: `window.openMermaidLightbox(container)` and `.diagram-toolbar` controls on `.mermaid-diagram-container`.

- [ ] **Step 1: Write the failing test**

Create `tests/test_mermaid_lightbox.js` verifying:
- `assets/js/app.js` defines `openMermaidLightbox`, injects `.diagram-toolbar` with Expand, Zoom In/Out/Reset, and Download SVG actions into `.mermaid-diagram-container`, and handles `Escape` key dismissal.
- `assets/css/qwiki.css` styles `.diagram-toolbar`, `#mermaid-lightbox-modal`, and hides `.diagram-toolbar` in `@media print` while applying `page-break-inside: avoid` to `.mermaid-diagram-container`.
- `AGENTS.md` documents the Mermaid diagram authoring directives (`flowchart TD`, max 4 concurrent branch nodes, `<br/>` wrapping).

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_mermaid_lightbox.js`
Expected: FAIL because `openMermaidLightbox` and `.diagram-toolbar` do not exist.

- [ ] **Step 3: Implement Mermaid lightbox, print CSS, and AI directives**

- In `assets/js/app.js`:
  - Create helper `attachDiagramToolbars()` called after `mermaid.run(...)` in `renderMermaidDiagrams` and `reRenderMermaidDiagrams`.
  - Inject `.diagram-toolbar` (top-right floating pill) into each `.mermaid-diagram-container` with:
    - Expand button (`openMermaidLightbox(container)`)
    - Inline Zoom In (`+`), Zoom Out (`-`), Reset (`100%`) buttons adjusting inline SVG `transform: scale(...)`
    - Download SVG button serializing the `<svg>` via `XMLSerializer` to a `Blob` download.
  - Implement `openMermaidLightbox(container)`: lazily creates `#mermaid-lightbox-modal` overlay, clones the rendered `<svg>`, wires wheel zoom (`0.25x`–`4x`), mouse drag panning, Reset button, Close button, backdrop click, and `Escape` keydown listener.
- In `assets/css/qwiki.css`:
  - Set `.mermaid-diagram-container { position: relative; }`, `.diagram-toolbar` floating pill (opacity 0.25 at rest, 1 on hover), and `#mermaid-lightbox-modal` full-viewport overlay styles.
  - In `@media print`: hide `.diagram-toolbar`, `#mermaid-lightbox-modal`, and add `page-break-inside: avoid; break-inside: avoid;` on `.mermaid-diagram-container`.
- In `AGENTS.md`:
  - Add a **Mermaid Diagram Screen & PDF Authoring Directives** section specifying: prefer `flowchart TD` for $>4$ nodes, limit horizontal branches to $\le 4$ nodes, wrap labels with `<br/>`, and split complex architectures into focused modular diagrams.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_mermaid_lightbox.js`
Expected: `PASS`

- [ ] **Step 5: Run full verification suite**

Run: `php tests/test_markdown_alerts.php && php tests/test_chapter_navigation.php && node tests/test_add_chapter_modal.js && node tests/test_sidebar_collapse.js && node tests/test_mermaid_lightbox.js && node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php`
Expected: All PASS.

- [ ] **Step 6: Commit**

```bash
git add assets/js/app.js assets/css/qwiki.css AGENTS.md tests/test_mermaid_lightbox.js
git commit -m "feat: add Mermaid diagram lightbox, inline zoom, print rules, and AI directives"
```
