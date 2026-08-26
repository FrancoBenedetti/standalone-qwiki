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
  - **Markdown (`.md`)**: Server-side parsing via Parsedown with an inline WYSIWYG Toast UI editor. Supports direct image uploading and importing existing local Markdown files.
  - **Google Docs (`gdoc`)**: Embed published Google Docs URLs with automatic HTML cleaning and theme integration. Automatically appends `?embedded=true` if omitted.
  - **PDF Manuals (`.pdf`)**: Embedded responsive iframe PDF viewer with download links.
- **🔍 Advanced Search**: Real-time server-side search across document titles, descriptions, and Markdown file contents, respecting category visibility rules.
- **📑 Auto-Generated Table of Contents**: Markdown pages automatically generate a responsive, expandable/collapsible sidebar for easy navigation of long documents, complete with scroll tracking.
- **🧭 Seamless Article Navigation**: Context-aware `Next` and `Previous` buttons appear dynamically to let you read through categories without returning to the sidebar.
- **🔗 Clean URLs & SEO Routing**: Utilizes beautiful, SEO-friendly clean URLs (e.g., `/category/document`) powered by a smart routing engine that removes redundant folders while maintaining full backward compatibility.
- **🖐️ Drag-and-Drop Menu Reordering**: Authenticated Admins can drag items using visual handles (`⣿`) to reorder documents, nest items into categories/sub-folders, or reorder top-level categories. Changes sync instantly to `qwiki.json`.
- **↔️ Resizable Sidebar Menu**: Click and drag the right border of the sidebar menu to adjust width (`200px` to `550px`). Preferred width is saved in `localStorage`.
- **👥 Multi-User Management & RBAC**:
  - **Admin**: Full rights to edit categories, create documents, upload media, reorder menus, and manage users.
  - **Viewer**: Read-only documentation access.
  - Passwords encrypted using native PHP Bcrypt (`password_hash()`).
- **🎨 Cascading Themes & Built-in UI Editor**: Assign different CSS themes across the site, specific categories, or individual documents. Write and preview themes via a live CSS editor directly in the browser!
- **👁️ Visibility Controls**: Restrict entire categories to logged-in users or admins only. Restrict document type badges (MD, PDF, GDOC) to admin users.
- **📡 RSS Feed Syndication**: Automatically generates full-text RSS feeds per category (e.g. `/api/feed.php?category=blog`), perfectly compatible with RSSHub integrations.
- **🎉 1-Click Auto Updates**: Built-in update checker securely polls for new releases. Admins can download and install new core updates directly from the UI with a single click, without risking any user data.
- **🛡️ Security Hardening**:
  - `.htaccess` blocks direct browser downloads of `.json` configuration and user store files.
  - Strict path traversal prevention (`realpath` + project root boundary checks).
  - Sanitized filenames and extension whitelisting.

---

## 📋 System Requirements

- **PHP**: PHP 7.4 or PHP 8.x
- **PHP Extensions**: `json`, `session`, `mbstring`, `fileinfo`, `zip` (for 1-click updates)
- **Web Server**: Apache, Nginx, or Caddy (or built-in PHP CLI dev server for local testing).

---

## 🚀 Quick Start & Installation

1. **Download the Latest Release**:
   Download the latest `.zip` file from the [GitHub Releases page](https://github.com/FrancoBenedetti/standalone-qwiki/releases/latest) and extract it to your web server root or subfolder.

2. **Set File Permissions**:
   Ensure PHP has write access to the directory so it can run the auto-setup:
   ```bash
   chmod -R 775 /path/to/your/qwiki-folder
   chown -R www-data:www-data /path/to/your/qwiki-folder
   ```

3. **Run Auto-Setup**:
   Simply open your deployment URL (e.g. `http://your-domain.com/` or `http://your-domain.com/your-subfolder/`) in your browser. 
   Qwiki will automatically detect that it's a new installation, create your `qwiki.json` and `users.json` files, and generate the `content/` and `uploads/` directories using the included demo data.

### Upgrading Qwiki
Because Qwiki uses a safe separation of logic and data, updating is incredibly simple:
- **1-Click Auto Update**: When logged in as Admin, click the "Update Available" button in your header to automatically fetch and apply the latest release.
- **Manual Zip Update**: Download the newest Release `.zip` and extract it over your existing installation. Your live `qwiki.json`, `users.json`, `content/`, and `uploads/` are completely ignored by the update ZIP and will remain perfectly intact.

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
├── .gitignore                 # Ignores live user data (qwiki.json, content, users.json)
├── api/
│   └── admin.php              # REST API endpoint handling auth, CRUD, DND reordering, image uploads
├── assets/
│   ├── css/
│   │   └── qwiki.css          # Design system, dark/light theme, drag indicators, responsive styles
│   └── js/
│       └── app.js             # Theme toggle, search auto-expand, DND engine, sidebar resizer
├── demo-data/                 # Auto-Setup templates for fresh installs
│   ├── content/               # Demo documentation files
│   └── qwiki-default.json     # Demo wiki tree structure
├── lib/
│   ├── Parsedown.php          # Single-file Markdown parser
│   └── simple_html_dom.php    # HTML DOM cleaner for Google Docs
├── index.php                  # Main application entry point, routing engine, and Auto-Setup script
└── README.md                  # System documentation

(Generated after first run)
├── content/                   # Live documentation content store
├── uploads/                   # Live uploaded media & assets
├── qwiki.json                 # Live wiki tree structure configuration
└── users.json                 # Live file-based user store & Bcrypt password hashes
```

---

## 📄 License & Credits

Built with Parsedown, simple_html_dom, and Toast UI Editor. Free and open-source software under the MIT License.
