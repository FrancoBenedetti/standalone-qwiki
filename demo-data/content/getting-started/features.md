# Features & System Capabilities

**Standalone Qwiki** is a modern, fast, zero-database documentation web application. It combines local Markdown, embedded PDFs, and live Google Docs into a unified documentation portal.

---

## 📄 1. Multi-Format Rendering Engine

### A. Local Markdown Files (`.md`)
- Server-side GitHub-Flavored Markdown parsing via `lib/Parsedown.php`.
- Full online visual/code editor (`✏️ Edit Content`) for real-time document editing.
- **In-Editor Image Uploader**: Click **`📷 Insert Image`** to upload images directly to `uploads/images/` and auto-insert Markdown tags.
- **Markdown File Import**: Upload existing `.md` files directly from your computer into the editor for quick creation.

### B. Published Google Docs (`gdoc`)
- Embed published Google Doc URLs directly into your wiki tree.
- Automatic formatting: automatically appends `?embedded=true` if omitted.
- HTML cleaning & extraction via `lib/simple_html_dom.php` to match dark/light theme styling seamlessly.

### C. PDF Documents (`.pdf`)
- Embedded PDF viewer container with zoom, page navigation, and download links.

---

## 🔍 2. Advanced Search Engine

- **Server-Side Rendering**: Searches instantly query the backend for high performance.
- **Deep Indexing**: Searches hit document titles, meta descriptions, and the full content of Markdown (`.md`) files.
- **Role-Aware**: Search results strictly respect category visibility settings (e.g. Viewer vs Admin).

---

## 🖐️ 3. Drag & Drop Navigation Reordering

When logged in as an **Admin**, drag handles (`⣿`) appear next to every menu item in the sidebar:
- **Reorder Documents**: Drag pages up or down within a section.
- **Nest Items into Categories**: Drag a document into a category or sub-folder.
- **Reorder Categories**: Drag category headers to re-arrange main sections.
- **Instant Backend Sync**: Menu changes automatically save to `qwiki.json`.

---

## ↔️ 4. Resizable Sidebar Menu Width

- Drag the **right edge of the sidebar** to widen or narrow the navigation panel (between `200px` and `550px`).
- Your preferred width is saved in `localStorage` and remembered on every page reload.

---

## 👥 5. Multi-User Access Control (RBAC)

- Lightweight user store in `users.json` with PHP Bcrypt password encryption.
- **Admin**: Full creation, editing, file uploading, drag-and-drop menu reordering, and user management.
- **Viewer**: Read-only documentation access.

---

## 🎨 6. Theming Engine & Customization

- **Cascading Themes**: Apply different CSS themes to the entire site, to specific categories, or to individual documents. Documents inherit themes from their parent category, which inherit from the site default.
- **Pre-Built Themes**: Ships with modern, classic, and newsletter layout themes for beautiful reading experiences.
- **Live Theme Editor**: Open the Theme Editor from the Settings modal to write, edit, and save new CSS themes directly in the browser.
- **Custom Logo Upload**: Replace the text-based brand name with your own uploaded logo image via the Settings menu.

---

## 👁️ 7. Visibility Controls

- **Granular Category Access**: Assign visibility to categories as `Public`, `Logged In Users`, or `Admins Only`.
- **UI Customization**: Restrict document type badges (`MD`, `PDF`, `GDOC`) to admin users to present a cleaner interface for visitors.

---

## 📡 8. RSS Feed Syndication

- Access full-text RSS feeds for any category by appending its ID to the feed endpoint (e.g., `/api/feed.php?category=blog`).
- Content is fully localized, transforming relative image and link paths to absolute URLs for RSS readers.
- Out-of-the-box compatibility with **RSSHub**!

---

## 🎉 9. 1-Click Auto Updates

- Standalone Qwiki polls GitHub for new releases.
- Admins are notified of new versions via a dashboard badge.
- Updates can be downloaded and securely extracted with a single click inside the Admin Settings—safely preserving all your existing files, data, and users!
