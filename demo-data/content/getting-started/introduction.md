# ⚡ Standalone Qwiki

**Standalone Qwiki** is a modern, fast, zero-database documentation portal and wiki system written in PHP, Vanilla JavaScript, and CSS. It supports local Markdown files (`.md`), rich HTML documents (`.html`), embedded PDF documents (`.pdf`), and published Google Docs (`gdoc`) within a unified, responsive interface featuring dynamic search, zero-flicker dark/light themes, drag-and-drop menu reordering, resizable sidebar, self-contained extensions, and role-based user management.

---

## 🌐 Sub-Folder & Sub-Domain Deployment

Standalone Qwiki is **100% location-agnostic**:
- It can be deployed in **any sub-folder** of your server root (e.g. `/help`, `/documentation`, `/docs/v1`, `/wiki`) or as a root domain.
- **Universal Clean URLs**: Beautiful URLs (`/category/document`) resolve seamlessly across all environments (Apache with/without `mod_rewrite`, Nginx, and PHP built-in CLI server).
- **Zero Hardcoded Paths**: All web assets, navigation links, and API requests use relative paths (`index.php?`, `assets/css/`, `api/admin.php`).
- **Zero Configuration Required**: Simply place the `standalone-qwiki` folder into your desired directory on your server; auto-setup takes care of the rest!

---

## 🔒 Public vs. Private Access Modes

Admins can control whether documentation is publicly readable or requires authentication:

1. Open **`⚙️ Settings`** in the header as Admin.
2. Select **Access Mode**:
   - **Public Access (Default)**: Anyone can view and search documentation without logging in. Authentication is only required for editing, uploading, reordering, and user management.
   - **Private Portal**: Authentication (as Viewer or Admin) is strictly required to view documentation content. Unauthenticated visitors see a secure login prompt.

---

## 🌟 Key Features

- **Zero Database Requirement**: Operates completely standalone using file-based JSON configuration (`qwiki.json`) and user store (`users.json`). No MySQL or MariaDB setup needed!
- **🧩 Self-Contained Extension System**: Extend Qwiki with custom page types and agentic tools located in `assets/extensions/` without modifying core files.
- **Multi-Format Support**:
  - **Markdown (`.md`)**: Server-side parsing via Parsedown with an inline WYSIWYG Toast UI editor, direct image upload, and playable video auto-embedding.
  - **HTML Documents (`.html`)**: Sandboxed interactive HTML pages with built-in **SunEditor WYSIWYG visual editor** and raw source code toggle.
  - **Google Docs (`gdoc`)**: Embed published Google Docs URLs with automatic HTML cleaning and theme integration.
  - **PDF Manuals (`.pdf`)**: Embedded responsive iframe PDF viewer with download links.
- **✨ Agentic Visual & Chart Generator**: Create vector Bar Charts, Line Trends, Pie/Donut Charts, Flow Diagrams, and Status Badges. Generates standalone SVG files saved directly to `uploads/` with 1-click Markdown editor insertion.
- **🌓 Zero-Flicker Theming**: Instant theme switching with persistent server-side cookie and synchronous head script sync.
- **🖐️ Drag-and-Drop Menu Reordering**: Authenticated Admins can drag items using visual handles (`⣿`) to reorder documents, nest items into categories/sub-folders, or reorder top-level categories.
- **↔️ Resizable Sidebar Menu**: Click and drag the right border of the sidebar menu to adjust width (`200px` to `550px`).
- **👥 Multi-User Management & RBAC**: Role-based access with Admin and Viewer accounts encrypted using native PHP Bcrypt (`password_hash()`).
- **🎨 Cascading Themes & Live CSS Editor**: Assign different CSS themes across the site, categories, or documents with a live browser-based theme editor.
- **👁️ Visibility Controls**: Restrict entire categories to logged-in users or admins only. Hide document type badges (MD, PDF, GDOC, HTML) from public viewers.
- **🛡️ Security Hardening**: Strict path traversal prevention, sanitized filenames, and protected `.json` stores.

---

## 📋 System Requirements

- **PHP**: PHP 7.4 or PHP 8.x
- **PHP Extensions**: `json`, `session`, `mbstring`, `fileinfo`, `zip` (for 1-click updates)
- **Web Server**: Apache, Nginx, or Caddy (or built-in PHP CLI dev server for local testing).

---

## 🔑 Default Credentials & Admin Suite

When accessing for the first time, click **Login** in the top right header:

- **Username**: `admin`
- **Password**: `admin`
