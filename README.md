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
  - **Markdown (`.md`)**: Server-side parsing via Parsedown with an online visual/code editor and direct image upload.
  - **Google Docs (`gdoc`)**: Embed published Google Docs URLs with automatic HTML cleaning and theme integration. Automatically appends `?embedded=true` if omitted.
  - **PDF Manuals (`.pdf`)**: Embedded responsive iframe PDF viewer with download links.
- **🖐️ Drag-and-Drop Menu Reordering**: Authenticated Admins can drag items using visual handles (`⣿`) to reorder documents, nest items into categories/sub-folders, or reorder top-level categories. Changes sync instantly to `qwiki.json`.
- **↔️ Resizable Sidebar Menu**: Click and drag the right border of the sidebar menu to adjust width (`200px` to `550px`). Preferred width is saved in `localStorage`.
- **📷 In-Editor Image Upload**: Click **`📷 Insert Image`** inside the Markdown editor to upload images (`.png`, `.jpg`, `.jpeg`, `.gif`, `.svg`, `.webp`) and automatically insert Markdown image tags at your cursor position.
- **👥 Multi-User Management & RBAC**:
  - **Admin**: Full rights to edit categories, create documents, upload media, reorder menus, and manage users.
  - **Viewer**: Read-only documentation access.
  - Passwords encrypted using native PHP Bcrypt (`password_hash()`).
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

## 🚀 Quick Start & Installation

1. **Clone or Extract the Repository**:
   ```bash
   cd /path/to/webroot/help   # Or any subfolder/domain
   git clone <repository-url> .
   ```

2. **Set File Permissions**:
   Ensure write access for the `content/`, `uploads/`, `qwiki.json`, and `users.json` paths:
   ```bash
   chmod -R 775 content/ uploads/ qwiki.json users.json
   ```

3. **Access in Browser**:
   Open `http://your-domain.com/help/` (or your chosen subfolder).

---

## 🔑 Default Credentials & Admin Suite

When accessing for the first time, click **Login** in the top right header:

- **Username**: `admin`
- **Password**: `admin`

---

## 📁 Directory Structure

```
standalone-qwiki/
├── .htaccess                  # Security rules blocking .json downloads & script execution
├── api/
│   └── admin.php              # REST API endpoint handling auth, CRUD, DND reordering, image uploads
├── assets/
│   ├── css/
│   │   └── qwiki.css          # Design system, dark/light theme, drag indicators, responsive styles
│   └── js/
│       └── app.js             # Theme toggle, search auto-expand, DND engine, sidebar resizer
├── content/                   # Documentation content store
│   ├── api-docs/              # Sample API documentation files
│   └── getting-started/       # Sample Getting Started guides, sub-folders, and PDF
├── lib/
│   ├── Parsedown.php          # Single-file Markdown parser
│   └── simple_html_dom.php    # HTML DOM cleaner for Google Docs
├── uploads/                   # Uploaded media & assets
│   └── images/                # Uploaded Markdown images
├── index.php                  # Main application entry point & routing engine
├── qwiki.json                 # Wiki tree structure configuration
├── users.json                 # File-based user store & Bcrypt password hashes
└── README.md                  # System documentation
```

---

## 📄 License & Credits

Built with Parsedown and simple_html_dom. Free and open-source software under the MIT License.
