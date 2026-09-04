# Features & System Capabilities

**Standalone Qwiki** is a modern, fast, zero-database documentation web application. It combines local Markdown, rich HTML pages, embedded PDFs, and live Google Docs into a unified documentation portal.

---

## 📄 1. Multi-Format Rendering Engine

### A. Local Markdown Files (`.md`)
- Server-side GitHub-Flavored Markdown parsing via `lib/Parsedown.php`.
- Full online visual/code editor (`✏️ Edit Content`) for real-time document editing.
- **In-Editor Image Uploader**: Click **`📷 Insert Image`** to upload `.png`, `.jpg`, `.svg`, or `.webp` files directly to `uploads/images/` and auto-insert Markdown tags at your cursor position.
- **Auto-Embedded Playable Videos**: Paste standalone video links on their own line to automatically render responsive, theme-styled video players:
  - **YouTube**: Standard watch URLs, short `youtu.be` links, and Shorts with timestamp support (`?t=1m30s`), served via privacy-friendly `youtube-nocookie.com`.
  - **Vimeo**: Direct embeds with Do Not Track (`dnt=1`) privacy.
  - **Loom**: Instant screencast video playback directly from share links.
  - **HTML5 Direct Video**: Native player for `.mp4`, `.webm`, `.ogg`, and `.mov` files with controls and download links.
  - **Subtitles & Captions**: Add a custom title using Markdown link syntax `[Video Title](https://...)` or image syntax `![Video Title](video.mp4)` to display an italicized caption below the player.

### B. HTML Documents (`.html`)
- Native sandboxed HTML embedding with interactive JavaScript execution and isolated styling.
- **SunEditor WYSIWYG Editor**: Create and visually format HTML documents with headings, font sizes, tables, lists, colors, links, and media.
- **1-Click Raw Code View**: Seamlessly toggle between visual WYSIWYG editing and raw HTML code editing without tag sanitization or script corruption.
- **In-Place Editing**: Admins can click **`✏️ Edit HTML`** directly from the viewer toolbar to edit and update `.html` files in real-time.
- **File Loader**: Upload existing `.html` files directly into the editor.

### C. Published Google Docs (`gdoc`)
- Embed published Google Doc URLs directly into your wiki tree.
- Automatic formatting: automatically appends `?embedded=true` if omitted.
- HTML cleaning & extraction via `lib/simple_html_dom.php` to match dark/light theme styling seamlessly.

### D. PDF Documents (`.pdf`)
- Embedded PDF viewer container with zoom, page navigation, and download links.

---

## 🧩 2. Self-Contained Extension System

- Extend Qwiki with custom page types and tools packaged in `assets/extensions/` without touching core logic.
- Auto-discovery via `manifest.json` enables dynamic tab injection, custom badge rendering, backend action routing, and search text extraction.
- Completely isolated and protected against 1-click core updates.

---

## ✨ 3. Agentic Visual & Chart Generator

- Create professional vector graphics directly from natural directives:
  - **📊 Bar Charts**: Metrics, quarterly reports, and comparisons.
  - **📈 Line Charts**: Trends and time-series data.
  - **🥧 Pie / Donut Charts**: Proportions with automated color legends.
  - **🔀 Process & Flow Diagrams**: Multi-stage connected node workflows.
  - **🏷️ Status Badges**: Architecture and system status pills.
- Saves generated SVG files permanently to `uploads/` and offers 1-click insertion into the Markdown editor.

---

## 🖐️ 4. Drag & Drop Navigation Reordering

When logged in as an **Admin**, drag handles (`⣿`) appear next to every menu item in the sidebar:
- **Reorder Documents**: Drag pages up or down within a section.
- **Nest Items into Categories**: Drag a document into a category or sub-folder.
- **Reorder Categories**: Drag category headers to re-arrange main sections.
- **Instant Backend Sync**: Menu changes automatically save to `qwiki.json`.

---

## ↔️ 5. Resizable Sidebar Menu Width

- Drag the **right edge of the sidebar** to widen or narrow the navigation panel (between `200px` and `550px`).
- Your preferred width is saved in `localStorage` and remembered across sessions.

---

## 🌓 6. Instant Zero-Flicker Theming

- Server-side cookie sync paired with synchronous head script execution eliminates dark/light mode flashing (FOUC) when clicking between pages.
- **Cascading Themes**: Apply different CSS themes to the entire site, to specific categories, or to individual documents.
- **Live Theme Editor**: Open the Theme Editor from the Settings modal to write, edit, and save new CSS themes directly in the browser.

---

## 👥 7. Multi-User Access Control (RBAC)

- Lightweight user store in `users.json` with PHP Bcrypt password encryption.
- **Admin**: Full creation, editing, file uploading, drag-and-drop menu reordering, and user management.
- **Viewer**: Read-only documentation access.

---

## 👁️ 8. Visibility Controls & Search

- **Granular Category Access**: Assign visibility to categories as `Public`, `Logged In Users`, or `Admins Only`.
- **UI Customization**: Restrict document type badges (`MD`, `PDF`, `GDOC`, `HTML`) to admin users.
- **Full-Text Search**: Real-time search across titles, descriptions, Markdown content, and HTML documents.
