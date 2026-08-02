# Features & System Capabilities

**Standalone Qwiki** is a modern, fast, zero-database documentation web application. It combines local Markdown, embedded PDFs, and live Google Docs into a unified documentation portal.

---

## 📄 1. Multi-Format Rendering Engine

### A. Local Markdown Files (`.md`)
- Server-side GitHub-Flavored Markdown parsing via `lib/Parsedown.php`.
- Full online visual/code editor (`✏️ Edit Content`) for real-time document editing.
- **In-Editor Image Uploader**: Click **`📷 Insert Image`** to upload `.png`, `.jpg`, `.svg`, or `.webp` files directly to `uploads/images/` and auto-insert Markdown tags at your cursor position.

### B. Published Google Docs (`gdoc`)
- Embed published Google Doc URLs directly into your wiki tree.
- Automatic formatting: automatically appends `?embedded=true` if omitted.
- HTML cleaning & extraction via `lib/simple_html_dom.php` to match dark/light theme styling seamlessly.

### C. PDF Documents (`.pdf`)
- Embedded PDF viewer container with zoom, page navigation, and download links.

---

## 🖐️ 2. Drag & Drop Navigation Reordering

When logged in as an **Admin**, drag handles (`⣿`) appear next to every menu item in the sidebar:
- **Reorder Documents**: Drag pages up or down within a section.
- **Nest Items into Categories**: Drag a document into a category or sub-folder.
- **Reorder Categories**: Drag category headers to re-arrange main sections.
- **Instant Backend Sync**: Menu changes automatically save to `qwiki.json`.

---

## ↔️ 3. Resizable Sidebar Menu Width

- Drag the **right edge of the sidebar** to widen or narrow the navigation panel (between `200px` and `550px`).
- Your preferred width is saved in `localStorage` and remembered on every page reload.

---

## 👥 4. Multi-User Access Control (RBAC)

- Lightweight user store in `users.json` with PHP Bcrypt password encryption.
- **Admin**: Full creation, editing, file uploading, drag-and-drop menu reordering, and user management.
- **Viewer**: Read-only documentation access.
