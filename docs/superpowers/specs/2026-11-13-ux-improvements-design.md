# Design Document: Standalone Qwiki UX Improvements & Reading Canvas Architecture

**Date:** 2026-11-13  
**Status:** Proposed  
**Author:** Pair Programming Agent & User  

---

## 1. Intent and Context

Standalone Qwiki serves as a self-hosted documentation portal and digital garden. While it boasts high performance, local-first search, dynamic extensions, and multiple document types, several user experience friction points have emerged from daily reading, navigation, and authoring:

1. **Reading Canvas Clutter:** Both the navigation sidebar and Table of Contents (TOC) occupy fixed horizontal space on large desktop displays, restricting document width and causing visual clutter when focusing on long-form documentation, wide code snippets, or diagrams.
2. **Mental Model Confusion in Navigation:** The previous and next chapter buttons sit immediately above "Table of Contents" in the right sidebar. Users routinely mistake them for section-jump controls within the active page rather than chapter navigation.
3. **Modal Inconsistency & Category Desynchronization:** In the "Add Document" modal, each document type tab contains its own independent target category dropdown. Changing categories on one tab does not propagate when switching tabs, causing documents to be created in unintended folders. Furthermore, the modal's presentation lacks the polished tabbed aesthetic of the Settings modals.
4. **Mermaid Diagram Legibility:** Wide and long Mermaid diagrams are forced into horizontal scroll containers, breaking reading continuity. In PDF/print views, diagrams shrink down to microscopic, unreadable scales.
5. **Markdown Alert Callouts:** Standard callout syntax (such as `> [!NOTE]`, `> [!CAUTION]`, `> [!WARNING]`, `> [!TIP]`, `> [!IMPORTANT]`) is unparsed by the backend Markdown engine, leaving raw text tags unstyled.

This design document establishes the architectural approach to resolve these issues across 5 cohesive work packages.

---

## 2. Goals, Non-Goals & Anti-Goals

### Goals
1. **Reading Canvas Maximization:**
   - Provide independent, animated collapse/expand controls for both the left navigation sidebar and right Table of Contents.
   - Persist collapse states across page reloads via `localStorage`.
   - Provide intuitive keyboard shortcuts (`[` for left sidebar, `]` for right TOC) with tooltip discovery.
2. **Unambiguous Chapter Navigation:**
   - Move chapter navigation out of `#app-toc`.
   - Introduce primary footer pager cards (`← Previous Chapter` / `Next Chapter →`) at the bottom of the document body.
   - Introduce secondary arrow icons in the document header toolbar for quick flipping.
3. **Ergonomic "Add Document" & "Add Category" Modals:**
   - Unify the "Target Category" selector above the document type tabs in `#chapter-modal`, ensuring a single selected category applies across all formats.
   - Align the visual layout with `.modal-tabs` and `.tab-btn` styling used in `templates/modals/settings.php`.
4. **Enhanced Diagram Interaction & Print Fidelity:**
   - Implement an interactive diagram overlay (lightbox modal) with pan-and-zoom and ESC-to-close for all rendered Mermaid SVGs.
   - Preserve readability in print/PDF output with intelligent page breaks and readable font thresholds.
   - Add clear diagram authoring directives to `AGENTS.md` guiding AI agents to produce compact, vertically balanced Mermaid diagrams.
5. **Native GFM Markdown Alert Callouts:**
   - Extend `QwikiParsedown.php` to natively parse `[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, and `[!CAUTION]` (and their aliases) into styled callout containers with semantic SVG icons and theme-adaptive colors.

### Non-Goals
- Modifying the underlying JSON schema in `qwiki.json`.
- Reworking core page routing or URL structures.
- Breaking any existing extension modal tabs or custom extensions.

### Anti-Goals
- Opening external browser tabs for diagram inspection (which breaks session history, reading scroll positions, and back-button behavior).
- Hiding navigation controls so aggressively that readers lose their sense of place.

---

## 3. Approaches Considered & Trade-offs

### A. Sidebar Collapse Architecture
* **Approach 1: Full-Screen Mode Only (Existing):**
  - Toggles `body.mode-fullscreen`, stripping headers, sidebars, and toolbars completely.
  - *Trade-off:* Too disruptive. Readers lose search, breadcrumbs, edit controls, and theme switches.
* **Approach 2: Dual Collapsible Drawer Rails with CSS Variables (Selected):**
  - Toggle classes `.sidebar-collapsed` and `.toc-collapsed` on `.app-container`.
  - Animate widths to `0px` with `transition: width 0.25s ease`.
  - Maintain an unobtrusive collapse/expand toggle in the header/toolbar.
  - *Trade-off:* High user flexibility, zero disruption to active document reading context.

### B. "Add Document" Category Selection
* **Approach 1: Keep Independent Selects and Sync via JavaScript:**
  - Keep 5+ `<select name="bookId">` inputs in each tab form and attach a change listener to sync values.
  - *Trade-off:* Cluttered DOM, fragile if dynamic extension tabs render before/after listeners attach.
* **Approach 2: Global Category Selector Above Tabs (Selected):**
  - Move the Category dropdown to a shared, persistent header zone above the `.modal-tabs` inside `#chapter-modal`.
  - Keep hidden inputs inside each form that mirror the master selector, or pass the master selector's value on submission.
  - *Trade-off:* Aligns with user mental models: choose *where* first, then choose *how/what*.

### C. Mermaid Diagram Inspection
* **Approach 1: Open SVG in New Browser Tab:**
  - Render SVG and trigger `window.open()` or `target="_blank"`.
  - *Trade-off:* Disorients users, leaves behind orphaned tabs, and loses dark/light theme context.
* **Approach 2: In-App Accessible Lightbox Overlay with Pan/Zoom (Selected):**
  - Hovering a diagram reveals a discreet floating action bar: `[ ⛶ Expand ]`, `[ 🔍 Zoom In/Out ]`, `[ 📥 Download SVG ]`.
  - Clicking "Expand" opens an in-page modal displaying the SVG at full scale with mouse wheel zooming, drag panning, and instant ESC key dismissal.

---

## 4. Detailed Specification

### Work Package 1: Native GFM Markdown Alerts in Parsedown

1. **Parser Implementation (`lib/Core/QwikiParsedown.php`):**
   - Override `blockQuote` and `blockQuoteContinue` to detect alert headings:
     `> [!NOTE]` / `> [!TIP]` / `> [!IMPORTANT]` / `> [!WARNING]` / `> [!CAUTION]`
   - Supported aliases:
     - `note` / `info` → Class: `markdown-alert-note`, Icon: Info circle
     - `tip` / `hint` / `success` → Class: `markdown-alert-tip`, Icon: Lightbulb
     - `important` → Class: `markdown-alert-important`, Icon: Flame / Bookmark
     - `warning` / `caution` → Class: `markdown-alert-warning`, Icon: Alert triangle
     - `danger` / `alert` / `fail` → Class: `markdown-alert-danger`, Icon: Octagon exclamation
   - Output HTML structure:
     ```html
     <div class="markdown-alert markdown-alert-caution">
       <div class="markdown-alert-title">
         <svg class="markdown-alert-icon" ...></svg>
         <span>Caution</span>
       </div>
       <div class="markdown-alert-content">
         <p>Alert message body goes here...</p>
       </div>
     </div>
     ```
2. **Styling (`assets/css/qwiki.css` & themes):**
   - Left accent border: `4px solid var(--alert-border-color)`.
   - Background: `var(--alert-bg-color)` with subtle opacity.
   - Clean margin spacing with collapsible bottom paragraph margins.
   - Full print and dark-theme support.

---

### Work Package 2: Chapter Navigation Re-Architecture

1. **TOC Cleanup (`index.php`):**
   - Remove `.toc-nav-buttons` from `#app-toc`.
   - Table of Contents sidebar focuses solely on intra-page anchor links.
2. **Bottom-of-Page Chapter Pagers (`index.php`):**
   - Positioned immediately following `.content-body` before discussions/comments.
   - Two-column responsive card container `.chapter-pager`:
     - `.chapter-pager-prev`: Displays `« Previous Chapter` label and document title, linking to `$prevDoc['url']`. If `$prevDoc` is null, rendered as an invisible/balanced spacer or omitted.
     - `.chapter-pager-next`: Displays `Next Chapter »` label and document title, linking to `$nextDoc['url']`. If `$nextDoc` is null, rendered as an invisible/balanced spacer or omitted.
3. **Header Quick-Action Arrows (`index.php`):**
   - In `.content-header` next to breadcrumbs, provide compact arrow buttons (`←` / `→`) with tooltips for rapid paging without scrolling to bottom (consuming the existing `$prevDoc` and `$nextDoc`).

---

### Work Package 3: Modal Refactoring & Category Sync

1. **"Add Document" Modal (`templates/modals/create_chapter.php`):**
   - Move Category selector to top header block: `.modal-category-selector` listing all valid categories.
   - For `tab-link` (Web Links), provide an explicit checkbox/toggle `"Place at Top Level (Sidebar Root)"` which, when checked, overrides `bookId` to empty root.
   - Update tab container to `.modal-tabs` with standard `.tab-btn` styling matching `settings.php`.
   - Category changes in the master selector automatically synchronize with any hidden `bookId` inputs across all tabs (including dynamically registered extension tabs).
2. **"Add Category" Modal (`templates/modals/create_category.php`):**
   - Harmonize typography, field labels, switch toggles, and buttons with the Settings modal design standards.

---

### Work Package 4: Collapsible Left & Right Sidebars

1. **State & Layout Classes (`assets/css/qwiki.css`):**
   - `.app-container.sidebar-collapsed .app-sidebar`: width `0px !important`, min-width `0px !important`, overflow hidden, border-right none.
   - `.app-container.sidebar-collapsed #sidebar-resizer`: `display: none !important; pointer-events: none;`.
   - `.app-container.toc-collapsed .app-toc`: width `0px !important`, min-width `0px !important`, padding `0px !important`, overflow hidden, border-left none.
   - Transitions: `width 0.25s cubic-bezier(0.4, 0, 0.2, 1), padding 0.25s ease`.
   - Responsive boundary: On mobile viewports ($\le 768\text{px}$), `.sidebar-collapsed` is disregarded in favor of the mobile off-canvas drawer (`#mobile-toggle` and `.app-sidebar.open`).
2. **Controls (`index.php` & `assets/js/core/sidebar.js`):**
   - Add `#btn-toggle-left-sidebar` in `.app-header` (adjacent to logo/title).
   - Add `#btn-toggle-right-toc` in `.content-actions`.
   - Uncollapsing smoothly restores the user's previously configured `--sidebar-width` from `localStorage.getItem('qwiki_sidebar_width')`.
   - Keyboard listener: `[` toggles left sidebar, `]` toggles right TOC (when active element is not an input/textarea).
   - Persistence in `localStorage`.

---

### Work Package 5: Mermaid Diagram Lightbox & AI Authoring Directives

1. **Diagram Lightbox (`assets/js/app.js` & modal overlay):**
   - Attach hover action bar on each `.mermaid-diagram-container`:
     - `Expand`: Opens diagram in high-resolution SVG lightbox modal `#mermaid-lightbox-modal` with pan-and-zoom controls (mouse wheel zoom, drag to pan, reset zoom, ESC or click outside to dismiss). Clones the rendered `<svg>` directly into the modal for instant, layout-perfect rendering.
     - `Zoom + / - / Reset`: Quick in-place inspection.
     - `Download SVG`: Exports clean vector SVG file.
2. **Print/PDF Optimization (`assets/css/qwiki.css`):**
   - Ensure diagrams avoid mid-diagram page breaks: `page-break-inside: avoid;`.
   - Set max-width thresholds and landscape guidelines.
3. **AI Guidelines Update (`AGENTS.md`):**
   - Add guidelines for Mermaid diagram generators:
     - Prefer `flowchart TD` over wide `flowchart LR` for multi-step workflows ($>4$ nodes).
     - Limit diagram branch width to 4 concurrent nodes.
     - Mandate `<br/>` line wrapping in labels.
     - Split complex architectures into multiple modular diagrams.

---

## 5. Verification Plan

1. **Callout Tests:**
   - Verify `[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, `[!CAUTION]`, and aliases render with icons and proper CSS classes.
   - Run PHP unit tests and check edge cases (nested quotes, multi-line alerts).
2. **Navigation Tests:**
   - Verify pager links navigate to correct adjacent chapters.
   - Confirm TOC is clean and no longer contains chapter buttons.
3. **Modal Tests:**
   - Verify selecting a category in `#chapter-modal` persists when clicking between Markdown, Upload, GDoc, Link, and Remote tabs.
   - Verify document creation places files in the selected category.
4. **Sidebar Collapse Tests:**
   - Test toggle buttons and shortcuts (`[`, `]`).
   - Confirm state persists across page refresh.
5. **Mermaid Tests:**
   - Render wide diagram and test lightbox modal, zoom, pan, and ESC dismissal.
   - Verify print preview layout.
