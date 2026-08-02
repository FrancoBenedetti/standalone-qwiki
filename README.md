# ⚡ Standalone Qwiki

**Standalone Qwiki** is a modern, fast, zero-database documentation portal and wiki system written in PHP, Vanilla JavaScript, and CSS. It supports local Markdown files (`.md`), embedded PDF documents (`.pdf`), and published Google Docs (`gdoc`) within a unified, responsive interface featuring dynamic search, dark/light themes, drag-and-drop menu reordering, resizable sidebar, and role-based user management.

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
- **🎨 Rich Modern Aesthetics**: Dark/light mode toggle, collapsible category accordions, live sidebar search with auto-expanding matches, and responsive mobile navigation.

---

## 📋 System Requirements

- **PHP**: PHP 7.4 or PHP 8.x
- **PHP Extensions**: `json`, `session`, `mbstring`, `fileinfo`
- **Web Server**: Apache, Nginx, or Caddy (or built-in PHP CLI dev server for local testing).

---

## 🚀 Quick Start & Installation

1. **Clone or Extract the Repository**:
   ```bash
   cd /path/to/webroot
   git clone <repository-url> standalone-qwiki
   cd standalone-qwiki
   ```

2. **Set File Permissions**:
   Ensure write access for the `content/`, `uploads/`, `qwiki.json`, and `users.json` paths:
   ```bash
   chmod -R 775 content/ uploads/ qwiki.json users.json
   ```

3. **Run Local Dev Server** (Optional for testing):
   ```bash
   php -S 127.0.0.1:8000
   ```
   Open your browser at `http://127.0.0.1:8000`.

---

## 🔑 Default Credentials & Admin Suite

When accessing for the first time, click **Login** in the top right header:

- **Username**: `admin`
- **Password**: `admin`

### Admin Capabilities:
- **`+ Category`**: Create new top-level categories or sub-folders.
- **`+ Document`**: Add new Markdown pages online, upload `.md`/`.pdf` files, or link Google Docs.
- **`✏️ Edit Content`**: Open the full-screen visual Markdown editor with in-editor image upload.
- **`⚙️ Edit Details`**: Update document titles, type, file paths, or Google Doc URLs.
- **`👥 Users`**: Manage user accounts, create Viewers/Admins, or remove accounts.
- **`⚙️ Settings`**: Update portal title, logo text, and default category.

---

## 📁 Directory Structure

```
standalone-qwiki/
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

## ⚙️ Configuration Schema (`qwiki.json`)

```json
{
  "title": "Standalone Qwiki",
  "logoText": "QWIKI",
  "defaultBook": "getting-started",
  "books": [
    {
      "id": "getting-started",
      "title": "Getting Started",
      "chapters": [
        {
          "title": "Installation Guide",
          "slug": "installation",
          "type": "markdown",
          "file": "content/getting-started/installation.md"
        },
        {
          "title": "Live Google Doc Demo",
          "slug": "live-google-doc",
          "type": "gdoc",
          "url": "https://docs.google.com/document/d/e/.../pub?embedded=true"
        }
      ],
      "subfolders": [
        {
          "id": "advanced-topics",
          "title": "Advanced Topics",
          "chapters": [
            {
              "title": "Performance Optimization",
              "slug": "performance",
              "type": "markdown",
              "file": "content/getting-started/advanced-topics/performance.md"
            }
          ]
        }
      ]
    }
  ]
}
```

---

## 📄 License & Credits

Built with Parsedown and simple_html_dom. Free and open-source software under the MIT License.
