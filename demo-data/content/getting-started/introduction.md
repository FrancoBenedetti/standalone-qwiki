# ⚡ Standalone Qwiki

**Standalone Qwiki** is a modern, fast, zero-database documentation portal and wiki system written in PHP, Vanilla JavaScript, and CSS. It supports local Markdown files (`.md`), embedded PDF documents (`.pdf`), and published Google Docs (`gdoc`) within a unified, responsive interface featuring dynamic search, dark/light themes, drag-and-drop menu reordering, resizable sidebar, and role-based user management.

---

## 🌐 Sub-Folder & Sub-Domain Deployment

Standalone Qwiki is **100% location-agnostic**:
- It can be deployed in **any sub-folder** of your server root (e.g. `/help`, `/documentation`, `/docs/v1`, `/wiki`) or as a root domain.
- **Zero Hardcoded Paths**: All web assets, navigation links, and API requests use relative paths (`index.php?`, `assets/css/`, `api/admin.php`).
- **Zero Configuration Required**: Simply place the `standalone-qwiki` folder into your desired directory on your server; no rewrite rules or configuration changes are needed!

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
- **Multi-Format Support**:
  - **Markdown (`.md`)**: Server-side parsing via Parsedown with an inline WYSIWYG Toast UI editor and direct image upload.
  - **Google Docs (`gdoc`)**: Embed published Google Docs URLs with automatic HTML cleaning and theme integration. Automatically appends `?embedded=true` if omitted.
  - **PDF Manuals (`.pdf`)**: Embedded responsive iframe PDF viewer with download links.
- **🖐️ Drag-and-Drop Menu Reordering**: Authenticated Admins can drag items using visual handles (`⣿`) to reorder documents, nest items into categories/sub-folders, or reorder top-level categories. Changes sync instantly to `qwiki.json`.
- **↔️ Resizable Sidebar Menu**: Click and drag the right border of the sidebar menu to adjust width (`200px` to `550px`). Preferred width is saved in `localStorage`.
- **📷 In-Editor Image Upload**: Seamlessly drag and drop or upload images (`.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.webp`) directly into the inline WYSIWYG editor to automatically insert them into your Markdown document.
- **👥 Multi-User Management & RBAC**:
  - **Admin**: Full rights to edit categories, create documents, upload media, reorder menus, and manage users.
  - **Viewer**: Read-only documentation access.
  - Passwords encrypted using native PHP Bcrypt (`password_hash()`).
- **🎨 Cascading Themes & Built-in UI Editor**: Assign different CSS themes across the site, specific categories, or individual documents. Write and preview themes via a live CSS editor directly in the browser!
- **👁️ Visibility Controls**: Restrict entire categories to logged-in users or admins only. Hide document type badges (MD, PDF, GDOC) from public viewers.
- **🖼️ Custom Logo Support**: Easily upload a custom logo from the Settings panel to replace the default text-based branding.
- **🛡️ Security Hardening**:
  - `.htaccess` blocks direct browser downloads of `.json` configuration and user store files.
  - Strict path traversal prevention (`realpath` + project root boundary checks).
  - Sanitized filenames and extension whitelisting.

---

## 📋 System Requirements

- **PHP**: PHP 7.4 or PHP 8.x
- **PHP Extensions**: `json`, `session`, `mbstring`, `fileinfo`
- **Web Server**: Apache, Nginx, or Caddy (or built-in PHP CLI dev server for local testing).

---

## 🔑 Default Credentials & Admin Suite

When accessing for the first time, click **Login** in the top right header:

- **Username**: `admin`
- **Password**: `admin`
