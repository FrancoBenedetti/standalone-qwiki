# ⚡ Standalone Qwiki

[![Website](https://img.shields.io/badge/Website-qwiki.wiki-2563eb?style=flat&logo=googlechrome&logoColor=white)](https://qwiki.wiki)
[![Live Demo](https://img.shields.io/badge/Live%20Demo-Try%20Online-10b981?style=flat&logo=rocket&logoColor=white)](https://qwiki.wiki/demo/)
[![Documentation](https://img.shields.io/badge/Docs-home.qwiki.wiki-8b5cf6?style=flat&logo=gitbook&logoColor=white)](https://home.qwiki.wiki/docs)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D%207.4-777bb4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![Latest Release](https://img.shields.io/github/v/release/FrancoBenedetti/standalone-qwiki?style=flat&color=f59e0b)](https://github.com/FrancoBenedetti/standalone-qwiki/releases/latest)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg?style=flat)](https://opensource.org/licenses/MIT)

**[🌐 Website](https://qwiki.wiki)** &nbsp;•&nbsp; **[🚀 Live Demo](https://qwiki.wiki/demo/)** &nbsp;•&nbsp; **[📖 Documentation](https://home.qwiki.wiki/docs)** &nbsp;•&nbsp; **[⚡ Quick Start](#-quick-start--installation)** &nbsp;•&nbsp; **[📦 Releases](https://github.com/FrancoBenedetti/standalone-qwiki/releases)**

---

**Standalone Qwiki** is a modern, fast, zero-database documentation portal and wiki system written in PHP, Vanilla JavaScript, and CSS. It supports local Markdown files (`.md`), rich HTML documents (`.html`), embedded PDF documents (`.pdf`), and published Google Docs (`gdoc`) within a unified, responsive interface featuring dynamic search, zero-flicker dark/light themes, drag-and-drop menu reordering, resizable sidebar, and role-based user management.

---

## 🎬 See Standalone Qwiki in Action

Watch Standalone Qwiki in action:

https://github.com/user-attachments/assets/22c6bf1b-c2e9-41f4-bc37-7317d839771a

> 💡 **Try it live**: Test features firsthand in the [**Interactive Live Demo**](https://qwiki.wiki/demo/) *(Log in as Admin: `admin` / `admin`)* or explore complete setup guides in the [**Official Documentation**](https://home.qwiki.wiki/docs).

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
- **🧩 Self-Contained Extension System**: Easily extend Qwiki with custom page types (such as raw HTML, interactive dashboards, or custom widgets) and agentic backend utilities (such as AI diagram & chart generators) packaged self-contained in `assets/extensions/`.
- **Multi-Format Support**:
  - **Markdown (`.md`)**: Server-side parsing via Parsedown with an inline Toast UI editor (featuring automatic protected mode for raw HTML blocks & custom styles). Supports direct image uploading, importing existing local Markdown files, and auto-embedded playable videos.
  - **HTML Pages (`.html`)**: Native sandboxed HTML embedding with built-in **SunEditor WYSIWYG visual editor** and raw code view toggle for creating and in-place editing of `.html` pages.
  - **Interactive Forms (`.form.json`)**: Native flat-file surveys, feedback forms, and event RSVPs with a visual drag-and-drop builder, field presets, email alerts, webhooks, anti-spam honeypots, and CSV submission exports.
  - **Google Docs (`gdoc`)**: Embed published Google Docs URLs with automatic HTML cleaning and theme integration. Automatically appends `?embedded=true` if omitted.
  - **PDF Manuals (`.pdf`)**: Embedded responsive iframe PDF viewer with download links.
- **🎬 Playable Video Auto-Embedding**: Seamlessly embed responsive video players by pasting standalone video URLs on their own line. Natively supports YouTube (including timestamps and Shorts via `youtube-nocookie.com`), Vimeo (with Do Not Track), Loom screencasts, and direct video files (`.mp4`, `.webm`, `.ogg`, `.mov`) with custom caption formatting.
- **✨ Agentic Visual & Chart Generator**: Integrated visual generator producing vector Bar Charts, Line Trends, Pie/Donut Charts, Process Flows, and Status Badges. Generates standalone SVG files saved directly to `uploads/` and inserts Markdown snippets with 1 click.
- **🔍 Advanced Search**: Real-time server-side search across document titles, descriptions, and file contents across all registered page types (Markdown, HTML, Forms, etc.), respecting category visibility rules.
- **🌓 Instant Zero-Flicker Theming**: Server-side cookie sync paired with immediate synchronous head script execution eliminates all flash-of-unstyled-theme (FOUC) when switching pages in light or dark mode.
- **📑 Auto-Generated Table of Contents**: Markdown pages automatically generate a responsive, expandable/collapsible sidebar for easy navigation of long documents, complete with scroll tracking.
- **🧭 Seamless Article Navigation**: Context-aware `Next` and `Previous` buttons appear dynamically to let you read through categories without returning to the sidebar.
- **🔗 Universal Clean URLs & SEO Routing**: Utilizes beautiful, SEO-friendly clean URLs (e.g., `/category/document`) that resolve automatically across Apache (`mod_rewrite` enabled or disabled), Nginx, and PHP CLI dev server.
- **🔗 Full-Screen Secure Sharing & Social Cards**: Generate unguessable 16-hexadecimal share links (`?share=...`) to share any document directly in distraction-free full-screen reader mode (even from private portals). Supports 1-click social sharing (𝕏, LinkedIn, Facebook, WhatsApp, Telegram, Slack), rich Open Graph / Twitter Card previews, document-specific descriptions and banners, and instant admin revocation.
- **🌐 Navigation Hyperlinks & Smart Tab Routing**: Embed external and internal hyperlinks directly into the navigation tree at root level or nested inside categories. Features automatic tab routing (`target="_blank" rel="noopener noreferrer"` for external domains, `target="_self"` for same-domain), status indicator badges, drag-and-drop reordering, and search integration.
- **🖐️ Drag-and-Drop Menu Reordering**: Authenticated Admins can drag items using visual handles (`⣿`) to reorder documents, nest items into categories/sub-folders, or reorder top-level categories. Changes sync instantly to `qwiki.json`.
- **↔️ Resizable Sidebar Menu**: Click and drag the right border of the sidebar menu to adjust width (`200px` to `550px`). Preferred width is saved in `localStorage`.
- **👥 Multi-User Management & RBAC**:
  - **Admin**: Full rights to edit categories, create documents, upload media, reorder menus, and manage users.
  - **Viewer**: Read-only documentation access.
  - Passwords encrypted using native PHP Bcrypt (`password_hash()`).
- **🎨 Cascading Themes & Built-in UI Editor**: Assign different CSS themes across the site, specific categories, or individual documents. Write and preview themes via a live CSS editor directly in the browser!
- **👁️ Visibility Controls**: Restrict entire categories to logged-in users or admins only. Restrict document type badges (MD, PDF, GDOC, HTML, FORM) to admin users.
- **📡 RSS Feed Syndication**: Automatically generates full-text RSS feeds per category (e.g. `/api/feed.php?category=blog`), perfectly compatible with RSSHub integrations.
- **🎉 1-Click Auto Updates**: Built-in update checker securely polls for new releases. Admins can download and install new core updates directly from the UI with a single click, without risking any user data or installed extensions.
- **🔒 Multi-Tab & Collaborative Document Soft Locks**: Protects documents against simultaneous editing and overwrite collisions across different users and multiple browser tabs. Powered by an advisory lease engine, background heartbeats, immediate `BroadcastChannel` tab synchronization, automatic local draft preservation, and takeover controls.
- **🛡️ Category Lock & Cascading Deletion Protection**: Lock categories directly (`readOnly: true`, `locked: true`) or inherit deletion protection whenever a category holds protected documents. Visual lock indicators (`🔒`) and smart suppression of deletion actions prevent accidental data loss across deep folder hierarchies.
- **🌐 Sidebar Subwiki Navigation & Discovery**: Seamlessly navigate deployed subwikis with an optional collapsible accordion in the navigation sidebar, real-time document count badges, and bi-directional cross-wiki linking.
- **📬 Editorial Postbox & Desktop Transfer System**: Asynchronously transfer documents and folders across independent wikis or directly from your workstation (Linux, Windows, macOS) with non-destructive, reviewer-controlled staging. Includes zero-dependency Python 3 CLI tools, native OS file manager integrations (`Send to Qwiki`), automated asset packaging, and category mapping without in-place overwrites.
- **🤖 Controlled LLM & AI Agent Access Pipeline**: Dedicated, key-authenticated REST API (`/api/llm.php`) tailored for Large Language Models, autonomous coding agents (Claude Desktop, Cursor, Gemini CLI), and local RAG pipelines. Features category branch scoping, permitted format filtering, token estimation, live search, standard `llms.txt` Markdown export, and OpenAPI 3.0 tool schemas.
- **✨ Native Gemini AI Assistant**: Built-in utility extension (`tool-gemini-assistant`) leveraging Google's Gemini models to analyze active document content, synthesize concise descriptions, generate discovery tags, and preview interactive OpenGraph / Social Share cards with 1-click application to `qwiki.json`.
- **🔄 Smart Upload Conflict Resolution & In-Place Replacement**: Automatically intercepts filename and slug collisions during file uploads, providing 1-click options to either replace document content in-place (preserving URLs, categories, custom themes, and share tokens) or upload as a copy with auto-incremented slugs. Features a dedicated "Replace Document Content" toolbar button.
- **🛡️ Security Hardening**:
  - `.htaccess` blocks direct browser downloads of `.json` configuration and user store files.
  - Strict path traversal prevention (`realpath` + project root boundary checks).
  - Sanitized filenames and extension whitelisting.

---

## 🧩 Building Extensions for Qwiki

Extensions live in `assets/extensions/{extension-id}/` and require zero core modification.

### 1. Custom Page Type (`manifest.json`):
```json
{
  "id": "html",
  "type": "page_type",
  "title": "HTML Document",
  "icon": "🌐",
  "badge": { "label": "HTML", "class": "badge-html" },
  "renderer": "renderer.php",
  "modal": "modal.html.php",
  "handler": "handler.php",
  "styles": ["style.css"],
  "scripts": ["script.js"]
}
```

### 2. Agentic Utility / Admin Tool (`manifest.json`):
```json
{
  "id": "ai_visuals",
  "type": "utility",
  "title": "Generate Visual / Chart",
  "icon": "✨",
  "placement": "header_menu",
  "modal": "modal.html.php",
  "handler": "handler.php"
}
```

---

## 📋 System Requirements

- **PHP**: PHP 7.4 or PHP 8.x
- **PHP Extensions**: `json`, `session`, `mbstring`, `fileinfo`, `zip` (for 1-click updates)
- **Web Server**: Apache, Nginx, or Caddy (or built-in PHP CLI dev server for local testing).

---

## 🚀 Quick Start & Installation

> 📘 **Looking for full documentation?** Comprehensive configuration guides, server recipes (Apache, Nginx, Caddy, Docker), and advanced customization options are available at **[home.qwiki.wiki/docs](https://home.qwiki.wiki/docs)**.

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
- **Manual Zip Update**: Download the newest Release `.zip` and extract it over your existing installation. Your live `qwiki.json`, `users.json`, `content/`, `uploads/`, and custom `assets/extensions/` are completely ignored by the update ZIP and will remain perfectly intact.

---

## 🔑 Default Credentials & Admin Suite

When accessing for the first time, click **Login** in the top right header:

- **Username**: `admin`
- **Password**: `admin`

*(You can also use these credentials right now to explore the [Interactive Live Demo](https://qwiki.wiki/demo/)).*

---

## 📁 Directory Structure

```
standalone-qwiki/
├── .htaccess                  # Security rules blocking .json downloads & script execution
├── .gitignore                 # Ignores live user data (qwiki.json, content, users.json)
├── api/
│   ├── admin.php              # REST API endpoint delegating to Core & Extensions
│   ├── feed.php               # RSS / JSON feed syndication endpoint
│   ├── llm.php                # Authenticated AI Agent & LLM document access endpoint
│   ├── search.php             # Search endpoint querying all registered page types
│   └── publish.php            # External publishing endpoint
├── assets/
│   ├── css/
│   │   └── qwiki.css          # Design system, dark/light theme, responsive styles
│   ├── js/
│   │   └── app.js             # Theme toggle, search, DND engine, sidebar resizer
│   └── extensions/            # Self-contained drop-in extensions
│       ├── page-html/         # HTML Page Type (SunEditor WYSIWYG)
│       ├── page-form/         # Interactive Flat-File Forms & Surveys
│       ├── tool-ai-visuals/   # AI Visual & Chart Generator Utility
│       ├── tool-gallery/      # Media & Image Assets Gallery
│       ├── tool-postbox/      # Editorial Document Transfer & Desktop Pipeline
│       ├── tool-backup/       # Full & Selective Backup Archive Exporter
│       └── tool-gemini-assistant/ # Gemini AI Assistant & Social Card Generator
├── demo-data/                 # Auto-Setup templates for fresh installs
│   ├── content/               # Demo documentation files
│   └── qwiki-default.json     # Demo wiki tree structure
├── lib/
│   ├── Core/
│   │   ├── Auth.php           # Session, authentication, and RBAC
│   │   ├── Config.php         # Config persistence and path validation
│   │   ├── LlmAccess.php      # LLM tree indexing, token estimation, and scope guard
│   │   ├── Navigation.php     # Tree traversal, breadcrumbs, next/prev links
│   │   └── ExtensionManager.php # Dynamic discovery and hook registry
│   ├── Parsedown.php          # Markdown parser
│   └── simple_html_dom.php    # HTML DOM cleaner for Google Docs
├── index.php                  # Slim entry point (< 250 lines)
└── README.md                  # System documentation

(Generated after first run)
├── content/                   # Live documentation content store
├── uploads/                   # Live uploaded media & generated visuals
├── qwiki.json                 # Live wiki tree structure configuration
└── users.json                 # Live file-based user store & Bcrypt password hashes
```

---

## 📄 License & Credits

Built with Parsedown, simple_html_dom, and Toast UI Editor. Free and open-source software under the MIT License.
