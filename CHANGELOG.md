# Changelog

All notable changes to Standalone Qwiki will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
