# Changelog

All notable changes to Standalone Qwiki will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.9.8] - TreeGuard - 2026-09-12

### 🛡️ Category Lock & Deletion Protection
- **Cascading Category Deletion Protection**:
  - Categories containing protected documents (`"readOnly": true` or `"editable": false`) now automatically inherit deletion protection, preventing documents from being accidentally deleted through the deletion of parent folders.
  - Multi-level nested folder hierarchies are fully protected: ancestor folders cannot be removed if any nested subfolder contains a protected document.
- **Direct Category Lock**:
  - Categories can now be directly configured with `"readOnly": true`, `"editable": false`, or `"locked": true` in `qwiki.json` to prevent category deletion.
  - Child documents within directly locked categories inherit read-only protection against modification and deletion.
  - Added `Config::isCategoryProtected($categoryId)` and `Config::isCategoryDirectlyProtected($categoryId)`.
  - Added `is_category_protected($categoryId)` backward-compatibility helper in `api/admin.php`.
- **UI & Administrative Controls**:
  - Added visual lock indicators (`🔒`) in the sidebar for protected categories.
  - In the Edit Category modal, the "Delete Category" button is automatically hidden and replaced with a `Protected Category` status badge when a category or its contents are protected.
  - Added a "Lock Category (Prevent Deletion)" toggle to the Edit Category modal (`#edit-book-modal`).
  - Hardened `save_tree` / `reorder_tree` to preserve category lock attributes and prevent accidental omission of protected categories or documents during menu reordering.

### 🌐 Sidebar Subwiki Navigation & Discovery
- **Configurable Subwiki Sidebar Section**:
  - Added a new administrative toggle in Site Settings (`#settings-modal`): **"Show Subwikis in Left Sidebar Navigation"** (`showSubwikisInSidebar`).
  - Renders a clean, collapsible accordion group in the sidebar navigation displaying all deployed subwikis with their custom titles and document counts (`<N> docs`).
- **Bi-Directional Cross-Wiki Discovery**:
  - Implemented `SubwikiManager::getSidebarSubwikis()` supporting both parent and child wiki contexts.
  - In the parent wiki, lists all deployed child subwikis.
  - In a child subwiki, automatically inspects the parent directory to discover sibling subwikis, generates relative `../slug/` navigation paths, and excludes the active wiki.
- **Visual Integration**:
  - Styled subwiki navigation items with responsive count badges (`.badge-subwiki-count`) and distinct globe icons (`🌐`).

### 🧪 Test Automation
- Added `tests/category_lock_test.php` verifying direct category locks, transitive protection from child documents, multi-level folder cascades, and deletion prevention via `delete_node_recursive`.
- Added `tests/sidebar_subwikis_test.php` verifying subwiki discovery in parent and child contexts, settings persistence, and sidebar accordion rendering.

## [1.9.7] - OmniShare - 2026-09-10

### 🔗 Full-Screen Secure Sharing & Reader Rights

- **Universal Reader Share Modal Access**:
  - Moved `#share-modal` out of admin-only enclosing blocks in `index.php`, granting all authenticated readers (including users with the `viewer` role) direct access to the secure full-screen sharing modal.
  - Viewers on private portals requiring sign-in can now generate and distribute unguessable reader links (`?share=...`), allowing external recipients without portal accounts to view the document without hitting the sign-in barrier.
- **Admin Access Controls Isolation**:
  - Preserved strict role-based access for administrative share toggles (**Allow Public Sharing** and **Reset Key**), cleanly hiding them from read-only viewers while rendering the copy input and social sharing shortcuts.
- **Preloaded Share URL & Fallback Integrity**:
  - Embedded `data-share-url` directly on the document share button (`#btn-share-chapter`) to immediately populate the share input upon opening the modal without waiting for background API calls.
  - Native clipboard fallback now prefers the secure public share URL (`?share=...`) over standard internal URLs (`window.location.href`).
- **Public Share Key Retrieval**:
  - Updated `api/admin.php?action=get_or_create_share_key` to permit public/guest retrieval of existing share keys when public sharing is enabled (`publicShareable: true`), while requiring authentication to generate new keys or view restricted documents.

### 🌐 Social Share Menus & Open Graph Metadata

- **Open Graph & Twitter Card Integration**:
  - Added dynamic Open Graph (`og:title`, `og:description`, `og:image`, `og:url`, `og:type`, `og:site_name`) and Twitter Card (`twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`) meta tags to `<head>`.
  - Social networks and messaging platforms (𝕏, LinkedIn, Facebook, WhatsApp, Slack, Discord) now generate rich visual card previews when sharing documentation links.
- **Floating Bar Social Dropdown**:
  - Added a dedicated social share dropdown menu (`#share-social-dropdown`) to the Zen reader's floating action bar, providing instant 1-click sharing to 𝕏 (Twitter), LinkedIn, Facebook, WhatsApp, and a direct "Copy Share Link" action.
- **Document-Level & Global Social Metadata**:
  - Added custom **Short Description** and **Social Share Image URL** fields to the document **Edit Details** modal (`edit-chapter-modal`).
  - Added global fallback **Global Social Share Description** and **Global Social Share Image URL** configuration in Site Settings (`settings-modal`), cascading smoothly down to article introductions when custom fields are blank.

### 🛡️ HTML Extension & Environment Fixes

- **HTML Page Sandbox Popup Permission**:
  - Added `allow-popups` and `allow-popups-to-escape-sandbox` to the page-html extension iframe sandbox, permitting external links and references inside custom HTML pages to open reliably in new tabs without console security warnings.
- **Base URL CLI Normalization**:
  - Normalized empty script path handling in `Config::getBaseUrl()` to prevent `/./` artifacts when executing in CLI or automated testing environments.

### 🧪 Test Automation

- Added `tests/share_rights_test.php` covering:
  - Viewer role session authentication (`viewer != admin`).
  - Modal rendering for viewers without exposing admin-only controls.
  - Presence of `data-share-url` attributes on the chapter share button.
  - API share key retrieval and generation across both authenticated viewers and unauthenticated visitors.

---

## [1.9.6] - DocFlow - 2026-09-08

### 🗂️ Document Relocation & Hierarchy Organization

- **Hierarchical Category Selector**:
  - Added `Navigation::getCategoriesHierarchy()` to display parent-child category tree paths with visual nesting (`↳`) in the Edit Details modal and Settings default category selector.
- **Dynamic Category Relocation**:
  - Administrators can now move documents between categories/folders directly in **`⚙️ Edit Details`**.
  - Qwiki automatically relocates the physical file on disk (`relocate_document_file`), cleans up empty directories, resolves filename collisions with incremental numeric suffixes (`-1`, `-2`), and updates tree paths in `qwiki.json`.
- **Drag-and-Drop Disk Relocation**:
  - Dropping documents into different categories in the sidebar navigation automatically relocates physical files on disk to match the destination category directory, returning updated file mappings to sync DOM attributes.
- **Slug Renaming & Collision Safeguards**:
  - Changing a document's slug in the Edit Details modal automatically renames the underlying file on disk while preventing collisions against existing documents, categories, and physical folders.

### 🖼️ Active Editor Media Gallery Integration

- **Toast UI Toolbar Integration**:
  - Added a dedicated **`🖼️`** gallery button to the Markdown editor toolbar and edit actions bar (`#btn-editor-gallery`).
- **Context-Aware Editor Detection**:
  - When the gallery modal is opened while editing an article, an "Article Editor Active" status banner and mode badge (`Markdown` or `HTML`) are displayed.
- **1-Click Direct Insertion**:
  - Added prominent **`✓ Select`** buttons on cards and card thumbnail hover overlays. Clicking Select immediately generates the corresponding Markdown (`![alt](url)`) or HTML (`<img ...>`) tag, inserts it at the cursor position in the active editor, and automatically closes the gallery modal.
- **Preview Modal Insertion**:
  - The full preview modal supports direct insertion into active editors with customizable alt text.

### ⚡ HTML Editor & Asset Enhancements

- **SunEditor Code View Synchronization**:
  - Two-way synchronization automatically synchronizes changes made in raw HTML Code View mode back into the WYSIWYG editor DOM and form submission buffer, eliminating lost edits on save.
- **Quick Save Shortcut**:
  - Added **`Ctrl+S`** / **`Cmd+S`** keyboard shortcut to save HTML documents directly from the modal.
- **WAF / ModSecurity Protection**:
  - HTML content submissions are Base64-encoded to prevent false positives from strict web server firewalls.
- **Relative Asset Normalization**:
  - Sandboxed iframes automatically resolve relative asset URLs (e.g. `uploads/images/...`) against the application base URL.
- **Refined Protected HTML Mode**:
  - Markdown editor recognizes benign line break tags (`<br>`, `<br/>`) without inappropriately triggering strict protected HTML mode.

### 🌐 Dynamic Subwiki Navigation

- **Live Parent Title Resolution**:
  - `SubwikiManager::getParentTitle()` dynamically reads the parent wiki's `qwiki.json` so renames to the parent wiki title immediately reflect on child subwiki sidebar banners without hardcoded stale values.
- **Subwiki Site Settings**:
  - Subwiki administrators can customize the parent title and parent URL directly in Site Settings.

### 🧪 Test Automation

- Added comprehensive test suites:
  - `tests/document_management_test.php`: 43 assertions covering slug renaming, category relocation, numeric clash resolution, node mutation, and drag-and-drop file movement.
  - `tests/category_hierarchy_test.php`: 33 assertions covering nested tree traversal, indentation paths, multi-level folder creation, and hierarchy resolution.
  - `tests/test_gallery_workflow.php`: 17 assertions covering image upload, direct Markdown/HTML insertion, and multi-format usage tracking.
  - `tests/test_html_page_extension.php`: 16 assertions covering base href injection, HTML creation, saving, and error handling.

### 📚 Documentation

- Updated `features.md`, `managing-content.md`, and `site-settings-users.md` across both `demo-data/content/` and `content/`.

---

## [1.9.5] - SubwikiShield - 2026-09-07

### 🌐 Subwiki Management & Collision Protection

- **Bi-Directional Slug Collision Prevention**:
  - Implemented comprehensive bi-directional namespace validation in `SubwikiManager::validateSlug()`.
  * Prevents creating or renaming categories in parent wikis that match physical directories, deployed subwiki folders, or reserved system paths (`api`, `assets`, `content`, `uploads`, `lib`, `tests`, `demo-data`, `wikis`).
  * Prevents deploying subwikis whose slug matches an existing parent category, physical directory, or reserved route.
  * Real-time slug validation endpoint (`api/admin.php?action=check_subwiki_slug`) provides instant live feedback in the admin deployment UI.
- **Single-Level Subwiki Depth Enforcement**:
  - Subwikis are marked with `"isSubwiki": true` in `qwiki.json` and a filesystem `.subwiki` marker.
  - Subwikis cannot deploy nested child wikis (`deploySubwiki` strictly enforces a 1-level limit).
  - Multi-team hierarchies are structured using dashed naming at the parent level (e.g., `engineering-electrical`, `engineering-mechanical`).
  - Child subwikis automatically render a prominent **`← Back to [Parent Title]`** navigation banner in the sidebar.
  - In-app update prompts and deployment controls are cleanly suppressed in subwikis (`managed_by_parent: true`).
- **Parent Subwiki Deployment UI**:
  - Added dedicated **`🌐 Subwikis`** management modal in the admin interface with real-time slug verification, active subwiki listing, direct link shortcuts, and safe de-registration/deletion controls.
- **Automated Cascading Updates**:
  - The parent in-app updater (`install_update`) dynamically excludes all registered subwiki folders during zip extraction to protect child documentation and media uploads.
  - Cascades core engine updates (`index.php`, `lib/`, `assets/`, `api/`) to all child subwikis automatically upon parent update, eliminating the need for individual subwiki update button clicks.
- **Upgrade Migration Engine**:
  - Automated migration during upgrades detects un-registered legacy subwikis, registers them in `qwiki.json`, flattens any deeper nested folders ($\ge 2$ levels) into 1-level dashed subwikis, and normalizes article image paths to clean relative paths (`uploads/images/...`).

### 🧪 Test Automation

- **Subwiki Test Suite** (`tests/subwiki_collision_test.php`):
  - Comprehensive automated tests covering reserved word rejection, path traversal prevention, bi-directional category-vs-subwiki collision checks, dashed slug acceptance, subwiki deployment, single-depth restriction, cascading updates without data loss, and migration image path normalization.

### 📚 Documentation

- Synchronized `features.md` and `site-settings-users.md` across both `content/` and `demo-data/content/`.

---

## [1.9.4] - PrintFix - 2026-09-06

### 🖨️ HTML Document Print & PDF

- **Sandbox Print Permission Restored**: Added `allow-modals` to the `<iframe>` sandbox attribute in the HTML page extension renderer. Without this token, browsers silently block `window.print()` regardless of whether it is called from inside the iframe (`onclick="window.print()"`) or from the parent via `contentWindow.print()`, causing all three print buttons to fail in embedded view with no error feedback.
- **Smart Print Button Deduplication**: When an HTML document is active in embedded view, the redundant outer header/share-bar printer icon (`#btn-print-chapter`) is now automatically hidden by the HTML extension script. Only the HTML viewer toolbar's **`🖨️ Print / Save as PDF`** button remains visible — the appropriate entry point for iframe-scoped printing.
- **Context-Aware In-Document Action Bar**: HTML documents that embed their own `Document Options` / `Print / Save as PDF` action bar now detect whether they are running inside a Qwiki iframe (`window.self !== window.top`) and hide the bar automatically when embedded. The bar continues to display normally when the document is opened in a full browser tab or via a share link.

### 📚 Documentation

- **Features & System Capabilities** (`getting-started/features.md`): Added **Smart Print / Save as PDF** bullet under HTML Documents describing the unified, context-aware print behavior across all three access modes.
- **Managing Content** (`user-guide/managing-content.md`): Expanded the **Print & Social Share** section to distinguish Markdown / standard document printing from HTML document printing and updated the Share description to reflect the modal-based share workflow.
- Both `content/` and `demo-data/content/` documentation copies synchronized.

---

## [1.9.3] - FormatGuard - 2026-09-05


### 🛡️ Markdown & HTML Content Preservation
- **Protected Markdown + HTML Mode**:
  - Automatically analyzes Markdown documents (`containsHtmlMarkup`) for raw HTML blocks, inline styling (`style="..."`), CSS classes (`class="..."`), custom grids, flexboxes, and status badges before editor initialization.
  - Locks Toast UI Editor into Markdown mode (`initialEditType: 'markdown'`) with synchronized vertical live preview (`previewStyle: 'vertical'`) and hides mode switcher tabs (`hideModeSwitch: true`).
  - Completely eliminates silent HTML tag stripping, attribute erasure, and paragraph flattening caused by Toast UI's ProseMirror WYSIWYG schema.
  - Reads directly from the raw CodeMirror buffer on save, ensuring 100% byte-for-byte preservation of complex HTML layouts and custom styling without modification.
- **Visual Protection Badge**:
  - Displays a dedicated status banner above the editor (`⚡ Markdown + HTML Mode (Live Preview) [PROTECTED HTML]`) clearly informing authors that formatting and styles are protected.
- **Mode Switch Guarding**:
  - Intercepts internal mode switch events (`needChangeMode`) for pure Markdown documents, preventing accidental transitions to WYSIWYG mode if raw HTML markup or inline styling has been added.
- **Clean State Reset**:
  - Canceling an inline edit session now properly clears notice banners and reverts editor state to the original document content.

### 🧪 Test Automation
- **HTML Preservation Test Suite**:
  - Added comprehensive automated test coverage (`tests/test_markdown_html_preservation.js`) verifying markup detection across pure Markdown, code blocks containing HTML, inline tags, and real-world complex landing page fixtures.

---

## [1.9.2] - DemoShield - 2026-09-05

### 🛡️ Document Protection & Access Control
- **Universal Read-Only Document Protection**:
  - Documents configured with `"readOnly": true` or `"editable": false` in `qwiki.json` are globally locked against inline editing, metadata modifications (`edit_chapter`), deletion (`delete_chapter`), and HTML page saves (`page-html`).
  - Added `Config::isChapterProtected($slugOrFile)` to provide centralized recursive document protection checks across all core handlers and extensions.
  - Displays a visual status badge (`Protected Document` in standard mode, `Protected Demo Page` in demo mode) on locked articles.
  - Automatically suppresses inline editing toolbars, Toast UI editors, and drag-and-drop eviction for protected articles.

### 🔄 Native Demo Engine & Updater Safeguards
- **First-Class Demo Mode Activation**:
  - Added `Config::isDemoMode()` supporting three activation methods: `"demoMode": true` in `qwiki.json`, environment variable `QWIKI_DEMO_MODE=1`, or a `.demo` marker file in the installation root.
  - **Auto-Updater Protection**: Automatically disables GitHub update checks (`has_update: false`) and blocks `install_update` when running in demo mode, protecting public sandboxes from being overwritten by visitors.
  - **Scoped Demo Controls**: Displays the `Reload Demo Package` button in Admin Settings exclusively when demo mode is active. Standard installations remain clean.
- **DemoManager & Reload Engine**:
  - Integrated `Qwiki\Core\DemoManager` and `demo-reload.php` directly into core to provide clean zero-divergence branch alignment between production releases and demo sandboxes.
  - Safely resets visitor content additions, uploads, active locks, and user stores via CLI cron (`php demo-reload.php --quiet`) or authenticated web requests.

### 📚 Documentation & Guidelines
- **System Features**: Added Document Protection & Sandbox Safeguards documentation to `getting-started/features.md`.
- **Release Guidelines**: Formalized the downstream synchronization workflow (`main` ➔ `demo`) and release origin rules in `AGENTS.md`.

---

## [1.9.1] - SecureScope - 2026-09-05

### 🔒 Security & Authentication
- **Multi-Instance Session Isolation**:
  - Automatically derives instance-unique session names (`QWIKISESSID_<hash>`) based on a SHA-256 fingerprint of each installation's canonical base directory.
  - Prevents session collision and privilege leakage across multiple Qwiki instances or nested subfolder installations hosted on the same domain or server.
  - Scopes session cookie paths directly to the application's URL subfolder rather than global `/`.
  - Enforces instance ownership validation (`$_SESSION['qwiki_instance']`) to immediately reject foreign sessions stored on shared temporary storage.
  - Implements clean session and cookie expiration on logout.

### 🛡️ Reliability & Hardening
- **Toast UI Editor Fallback**: Implemented robust initialization fallback handling to ensure graceful editor degradation when external assets or complex raw HTML blocks are encountered.
- **Protocol Enforcement & Content Guidelines**: Integrated markdown authoring guidelines in project specifications (`AGENTS.md`) to maintain clean parsing and ensure editor stability.

---

## [1.9.0] - OmniNav - 2026-09-04

### 🌐 Added
- **Navigation Hyperlinks**: Custom hyperlinks can now be added directly to the sidebar navigation tree at any level—either at the root alongside category headers or nested inside any category folder.
- **Smart Target Routing**:
  - Automatically routes external domain URLs to open in a new tab with secure `target="_blank" rel="noopener noreferrer"`.
  - Automatically routes same-domain and relative URLs to open in the same tab (`target="_self"`).
  - Visual status badges indicate external (`badge-link`) vs. internal (`badge-internal-link`) destinations.
- **Top-Level Pixel-Perfect Alignment**:
  - Root-level hyperlinks match category headers in padding (`0.65rem 1.25rem`), typography (uppercase, bold, letter-spacing), drag handles (`⣿`), and right-aligned actions.
  - Automatically prefixes a `🔗` emoji matching category folder icons (`📂`) with identical character spacing and baseline alignment.
- **Unified Drag & Drop Engine**:
  - Reorder hyperlinks anywhere in the menu hierarchy with instant backend sync to `qwiki.json`.
  - Live DOM updating dynamically toggles top-level vs. nested styling and icons without requiring a page reload.
- **Search Integration**: Real-time filtering and server-side search seamlessly indexes hyperlink titles and descriptions.
- **Protocol Security Validation**: Blocks potentially unsafe or malicious protocol schemes (`javascript:`, `data:`, `vbscript:`).
- **Admin Management Modals**: Dedicated forms in the Add Item modal (`tab-link`) and Edit Hyperlink modal (`#edit-link-modal`) with full edit and delete support.

### 📚 Documentation & Demo Data
- **User Guide Updates**: Documented hyperlink creation, smart tab routing, and management in `content/user-guide/managing-content.md` and `demo-data/content/user-guide/managing-content.md`.
- **Features Guide**: Documented Navigation Hyperlinks in `content/getting-started/features.md` and `demo-data/content/getting-started/features.md`.
- **Default Config Alignment**: Added official GitHub repository hyperlink sample to `demo-data/qwiki-default.json`.

---

## [1.8.1] - 2026-09-04

### 📚 Documentation & Demo Data
- **Demo Data Alignment**: Fully synchronized `demo-data/content/getting-started/` and `demo-data/content/user-guide/` with up-to-date documentation on video embedding, advisory soft locks, image gallery extension, chart generator, and PDF/share tools.
- **Default Configuration Cleanup**: Updated `demo-data/qwiki-default.json` with standardized category types, syndication settings, and cleaned default structure excluding test pages.
- **Agent Guidelines**: Added `AGENTS.md` project rules to enforce automatic alignment of demo data, navigation configs, and release procedures for all future releases.

---

## [1.8.0] - OmniPlay - 2026-09-04

### 🎬 Added
- **Auto-Embedded Playable Videos**: Automatically transforms standalone video URLs and video Markdown links into responsive, interactive video players inside the Markdown viewer.
- **YouTube Embed Engine**: Supports standard watch links (`youtube.com/watch?v=...`), short links (`youtu.be/...`), YouTube Shorts (`youtube.com/shorts/...`), and start timestamps (`?t=1m30s`, `?start=90`). Uses privacy-friendly `youtube-nocookie.com`.
- **Vimeo Player Integration**: Supports standard and player links (`vimeo.com/...`, `player.vimeo.com/video/...`) with automatic Do Not Track (`dnt=1`) privacy protection.
- **Loom Screencast Integration**: Renders clean, full-featured Loom video players directly from share links (`loom.com/share/...`) and embed links (`loom.com/embed/...`).
- **Native HTML5 Video Player**: Seamlessly plays direct video files (`.mp4`, `.webm`, `.ogg`, `.mov`) hosted locally in `uploads/` or externally, equipped with native playback controls, preload metadata, and fallback download links.
- **Custom Video Captions**: Automatically extracts subtitles from Markdown link text (e.g. `[Tutorial Overview](https://...)`) or image syntax (`![Demo Clip](video.mp4)`) and renders an elegant italicized caption beneath the player.
- **Smart Inline Link Preservation**: Intelligent detection ensures that links inside sentences remain standard clickable hyperlinks and are never erroneously converted into embeds.
- **Responsive Video Styling**: Added `.qwiki-video-wrapper`, `.qwiki-video-container` (fluid 16:9 aspect ratio, rounded corners, soft box shadow), `.qwiki-video-player`, and `.qwiki-video-caption` matching dark and light themes.

---

## [1.7.0] - SafeSync - 2026-09-02

### 🔒 Added
- **Advisory Soft Lock System**: Prevents simultaneous editing and silent overwrite collisions when a document is edited by different users or in multiple browser tabs.
- **Ephemeral Tab UUID Tracking**: Distinctly identifies browser tabs using `sessionStorage`, allowing the system to differentiate multiple tabs opened by the same logged-in user.
- **Zero-Latency Cross-Tab Sync**: Integrated the browser `BroadcastChannel` API (`qwiki_doc_locks`) to immediately notify sibling tabs in the same browser when a document enters or exits edit mode without waiting for network polling.
- **Lease-Based Expiration & Heartbeats**: Configured a 60-second lease TTL with a 20-second active heartbeat ping, ensuring locks auto-expire cleanly if a tab or connection drops.
- **Unload Beacon Teardown**: Uses `navigator.sendBeacon` and `pagehide` listeners to promptly release locks upon navigating away or closing the tab.
- **Non-Blocking PHP Sessions**: Heartbeat endpoints immediately invoke `session_write_close()`, ensuring background lease renewal never blocks concurrent page requests from the user.
- **Tab Inactivity & Visibility Recovery**: Pauses renewal after 15 minutes of idle time and immediately resynchronizes lock status upon `visibilitychange` or window focus.
- **Atomic Save Protection**: Server-side write validation rejects save attempts (`LOCKED_BY_OTHER`) if another session holds or broke the lock.
- **Automatic Draft Safety Net**: Automatically mirrors typed changes to `localStorage` (`qwiki_draft_{file}`) to ensure no content is lost if an eviction occurs.
- **Conflict Modal & Takeover Action**: Displays clear dialogs detailing who currently holds the lock, with an option for authorized users to break/take over the lock.
- **Multi-Editor Support**: Full locking parity across both the inline Toast UI Markdown editor and the SunEditor HTML page extension (`assets/extensions/page-html`).

---

## [1.6.3] - 2026-08-29

### 🛡️ Fixed
- **ModSecurity / WAF Bypass**: Encoded HTML editor payloads as Base64 during save operations to prevent `403 Forbidden` errors triggered by strict web application firewalls on production Apache and Nginx servers.

---

## [1.6.2] - 2026-08-29

### 🧩 Added
- **Image Gallery Extension**: Dedicated media and image gallery viewer for uploaded documents and assets.

### 🐛 Fixed
- **HTML Editor & Visual Chart Bugfixes**: Fixed editor toggle persistence, script tag stripping prevention, and visual chart rendering edge cases.

---

## [1.6.1] - 2026-08-29

### 🐛 Fixed
- **Auto-Updater Filter**: Removed `assets/extensions` from the auto-updater exclude list to ensure core extension assets update reliably.

---

## [1.6.0] - 2026-08-29

### 🌟 Added
- **Self-Contained Extension Architecture**: Modular system supporting custom page types and admin utility tools under `assets/extensions/`.
- **WYSIWYG HTML Page Editor**: Built-in SunEditor integration with visual/code toggles for `.html` documents.
- **Agentic Visual & Chart Generator**: Integrated tool generating vector charts, diagrams, and process flows.

---

## [1.5.0] - 2026-08-26

### 🌟 Added
- **Print & PDF Export**: Native print-friendly stylesheet and PDF export trigger.
- **Social Sharing**: 1-click share menu supporting native Web Share API and clipboard copy.
- **Modern SVG Icons**: Replaced legacy text buttons with clean, responsive SVG icons across article action bars.

---

## [1.4.0] - 2026-08-26

### 🌟 Added
- **1-Click Auto Updates**: One-click core upgrade workflow checking against GitHub releases.
- **Dynamic Release Notes**: Render GitHub Markdown release notes directly within the admin update modal.
