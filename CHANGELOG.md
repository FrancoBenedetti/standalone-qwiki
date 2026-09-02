# Changelog

All notable changes to Standalone Qwiki will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
