# Features & System Capabilities

**Standalone Qwiki** is a modern, fast, zero-database documentation web application. It combines local Markdown, rich HTML pages, embedded PDFs, and live Google Docs into a unified documentation portal.

---

## 📄 1. Multi-Format Rendering Engine

### A. Local Markdown Files (`.md`)
- Server-side GitHub-Flavored Markdown parsing via `lib/Parsedown.php`.
- Full online visual/code editor (`✏️ Edit Content`) for real-time document editing.
- **In-Editor Image Uploader & Gallery Browser**: Click **`📷 Insert Image`** to upload `.png`, `.jpg`, `.svg`, or `.webp` files directly to `uploads/images/`, or click the dedicated **`🖼️`** toolbar icon to browse the full media gallery. When an editor is active, the gallery displays an "Article Editor Active" banner and **`✓ Select`** buttons to instantly insert image Markdown tags (`![alt](url)`) or HTML tags directly at your cursor position and automatically close the modal.
- **Auto-Embedded Playable Videos**: Paste standalone video links on their own line to automatically render responsive, theme-styled video players:
  - **YouTube**: Standard watch URLs, short `youtu.be` links, and Shorts with timestamp support (`?t=1m30s`), served via privacy-friendly `youtube-nocookie.com`.
  - **Vimeo**: Direct embeds with Do Not Track (`dnt=1`) privacy.
  - **Loom**: Instant screencast video playback directly from share links.
  - **HTML5 Direct Video**: Native player for `.mp4`, `.webm`, `.ogg`, and `.mov` files with controls and download links.
  - **Subtitles & Captions**: Add a custom title using Markdown link syntax `[Video Title](https://...)` or image syntax `![Video Title](video.mp4)` to display an italicized caption below the player.
- **Protected Markdown + HTML Mode**: Automatically detects raw HTML blocks, inline styling, CSS grids, and custom badges. Locks the editor into Markdown mode with synchronized live preview to prevent WYSIWYG tag sanitization and style loss (with refined detection allowing benign line breaks like `<br>`).

### B. HTML Documents (`.html`)
- Native sandboxed HTML embedding with interactive JavaScript execution and isolated styling.
- **SunEditor WYSIWYG Editor**: Create and visually format HTML documents with headings, font sizes, tables, lists, colors, links, and media.
- **1-Click Raw Code View & Auto-Sync**: Seamlessly toggle between visual WYSIWYG editing and raw HTML code editing. Edits made in raw Code View mode are automatically synchronized back to the WYSIWYG editor DOM and preserved byte-for-byte upon saving.
- **Hotkeys & WAF Protection**: Press **`Ctrl+S`** / **`Cmd+S`** inside the HTML editor to save instantly. Content submissions are safely Base64-encoded to bypass restrictive Apache ModSecurity and Web Application Firewall (WAF) filters.
- **Asset URL Normalization**: Media tags (`<img>`, `<video>`, `<audio>`) with relative paths (such as `uploads/images/...`) automatically resolve against the wiki base URL inside sandboxed iframes.
- **In-Place Editing**: Admins can click **`✏️ Edit HTML`** directly from the viewer toolbar to edit and update `.html` files in real-time.
- **File Loader**: Upload existing `.html` files directly into the editor.
- **Smart Print / Save as PDF**: All print buttons (toolbar, header icon, and any in-document button) delegate to the sandboxed iframe and trigger the browser print dialog. When viewed embedded in Qwiki, redundant print controls are automatically hidden — the HTML viewer toolbar button is the single entry point. In the full-tab expanded view or share mode, only the in-document button is shown.


### C. Interactive Forms (`.form.json`)
- Native flat-file interactive forms, surveys, and event RSVPs without any SQL database.
- **Visual Field Builder**: Add, configure, and reorder text inputs, textareas, email fields, select dropdowns, radio buttons, and checkboxes with customized labels, placeholders, and validation rules.
- **Starter Templates**: Rapidly initialize forms from presets including **Feedback Survey**, **Contact Us**, **Event RSVP**, or start from a blank canvas.
- **Submission Management & Alerts**: Collect visitor submissions into append-only flat-file JSON storage (`.submissions.json`). Authenticated Admins can inspect submissions in a responsive data table, delete individual entries, or clear all responses.
- **Anti-Spam Defenses**: Built-in silent honeypot fields deceive automated spam bots without impeding real visitors, paired with timestamp token verification.
- **CSV Export**: Export all collected responses with 1 click to Excel/UTF-8 formatted `.csv` spreadsheets.
- **Outgoing Notifications & Webhooks**: Optionally configure email alerts and webhook URLs (Slack, Discord, Zapier) to receive real-time POST payloads on every new submission.

### D. Published Google Docs (`gdoc`)
- Embed published Google Doc URLs directly into your wiki tree.
- Automatic formatting: automatically appends `?embedded=true` if omitted.
- HTML cleaning & extraction via `lib/simple_html_dom.php` to match dark/light theme styling seamlessly.

### E. PDF Documents (`.pdf`)
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

## 🖐️ 4. Drag & Drop Navigation & Category Relocation

When logged in as an **Admin**, drag handles (`⣿`) appear next to every menu item in the sidebar:
- **Reorder Documents**: Drag pages up or down within a section.
- **Nest Items into Categories**: Drag a document into a category or sub-folder. Physical files on disk are automatically moved to the new category folder without broken links.
- **Reorder Categories**: Drag category headers to re-arrange main sections.
- **Document Relocation & Pre-filled Category Hierarchy**: In **`⚙️ Edit Details`**, administrators can change a document's parent category/folder via a hierarchical category selector (`↳`), or edit its slug. The document's active category is always accurately prefilled—even when deeply nested in multi-level subfolders—ensuring folder placement is never accidentally reset when updating titles or metadata. Qwiki automatically moves the physical file on disk to the destination folder, renames it, resolves any filename clashes with numeric suffixes, and updates `qwiki.json`.
- **Instant Backend Sync**: Menu changes and physical file moves automatically save to `qwiki.json`.

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
- **UI Customization**: Restrict document type badges (`MD`, `PDF`, `GDOC`, `HTML`, `FORM`) to admin users.
- **Full-Text Search**: Real-time search across titles, descriptions, Markdown content, HTML documents, and interactive forms.
- **Clear Search Button**: A `×` clear button appears in the sidebar search bar when text is present, instantly resetting the search and restoring the pre-search navigation state. Press `Escape` to achieve the same result with keyboard.

---

## 🔗 9. Navigation Hyperlinks & Tab Routing

- **Sidebar Hyperlinks**: Place custom links directly into the navigation tree at any level—either at the top level alongside category headers or nested inside any category folder.
- **Smart Target Routing**:
  - **External Links**: Automatically open in a new tab (`target="_blank" rel="noopener noreferrer"`) and display an external link badge.
  - **Internal Links**: Same-domain and relative links open in the same tab (`target="_self"`) with an internal link badge.
- **Drag-and-Drop Reordering**: Move links freely between top-level navigation and nested category folders with drag handles (`⣿`).
- **Search Integration**: Hyperlinks are fully indexed and searchable by title and description.

---

## 🛡️ 10. Document Protection & Sandbox Safeguards

- **UI Lock Toggle (Document & Category Locking)**: Lock or unlock individual documents directly from the **Edit Details** modal (`⚙️`) without editing `qwiki.json` by hand. Locking prevents content modifications, inline markdown edits, and deletions — the edit and delete buttons are hidden and replaced with a **Protected Document** badge.
- **Ancestor Inheritance**: Documents inside a directly locked category automatically inherit read-only protection. The lock toggle in Edit Details is disabled with a notice explaining that the lock is inherited from a parent category — individual documents cannot be unlocked while their containing category is locked.
- **Cascading Category Deletion Protection**: Categories containing protected documents automatically inherit deletion protection at all hierarchy levels, preventing documents from being accidentally removed by deleting a parent folder.
- **Visual Lock Indicators**: Protected pages and categories display visual lock indicators (`🔒`), hide inline editor actions, and suppress deletion controls.
- **Native Demo Mode & Auto-Updater Safeguards**: Activate sandbox mode via `"demoMode": true` in `qwiki.json`, environment variable `QWIKI_DEMO_MODE=1`, or a `.demo` marker file. Automatically suppresses the in-app auto-updater to prevent sandboxes from being overwritten, blocks visitors from unlocking protected demo content, and surfaces the `Reload Demo Package` reset engine.
- **Multi-Instance Session Isolation**: Generates unique `QWIKISESSID_<hash>` session names and subfolder cookie paths derived from each installation's filesystem path, preventing session bleed across adjacent sites or nested subfolders.

---

## 🔗 11. Secure Full-Screen Document Sharing & Social Metadata

- **Unguessable Unique Share Keys**: Signed-in users with view rights (both Viewers and Administrators) can generate a secure share link for any document. Links use a cryptographically random 16-hexadecimal key (`?share=...`), completely masking internal category structures, folder paths, and slugs from recipients.
- **Role-Agnostic Sharing from Private Portals**: Viewers on private portals requiring sign-in can generate and distribute public full-screen share links, allowing recipients without accounts to view shared documents without encountering the portal login gate.
- **Distraction-Free Zen Reader**: Shared documents open in full-screen reader mode with the sidebar, brand header, search bar, and previous/next buttons hidden. Features a centered reading canvas and a floating glassmorphic bar with theme toggle, print/PDF button, social sharing dropdown, and an "Open in Wiki" exit link for authenticated users.
- **Dynamic Social Sharing & Rich Previews**: Built-in 1-click sharing to 𝕏 (Twitter), LinkedIn, Facebook, WhatsApp, Telegram, and Slack from both the share modal and the floating Zen reader dropdown. Complete Open Graph (`og:title`, `og:description`, `og:image`, `og:url`) and Twitter Card meta tag integration ensures rich link previews across social networks and messaging platforms.
- **Custom Social Metadata & Fallback Chain**: Authors can set document-specific social descriptions and image URLs in **Edit Details**, with automatic fallback to site-wide social settings in Settings (`Global Social Share Description`, `Global Social Share Image URL`), portal defaults, or article intros.
- **Admin Access Controls & Instant Revocation**: Documents are publicly shareable by default. Administrators can disable public sharing for any document (`publicShareable: false`) or click **Reset Key** to immediately invalidate all previously distributed links.

---

## 🌐 12. Subwiki Management & Collision Protection

- **Single-Level Subwiki Deployments**: Administrators in parent wikis can provision independent, single-level subwikis with dedicated admin accounts directly from the UI (`🌐 Subwikis`).
- **Bi-Directional Slug Collision Prevention**: Real-time validation ensures that new subwikis cannot shadow parent categories, and parent categories cannot share names with subwiki folders or reserved system directories.
- **Logical Dashed Grouping**: Subwikis cannot deploy nested child wikis; hierarchical multi-team structures are grouped using dashed slugs (e.g. `engineering-electrical`, `engineering-mechanical`).
- **Live Dynamic Parent Wiki Title**: Subwikis dynamically inspect the parent wiki's `qwiki.json` to reflect live parent wiki title renames on the sidebar "← Back to..." banner, eliminating stale names. Subwiki administrators can also configure custom parent titles and target URLs in Settings.
- **Automated Cascading Updates**: When the parent wiki installs core updates, code changes (`lib/`, `assets/`, `api/`, `index.php`) are automatically pushed to all child subwikis while preserving child content, uploads, and accounts.
- **Zero Broken Links & Image Normalization**: Background migration safely flattens any legacy nested subwikis and normalizes article image references.

---

## 🏠 13. Multitenant Hosted Mode

For managed hosting environments where many subwikis should share a single central Qwiki core:

- **Shared Core Architecture**: Define `QWIKI_BASE_DIR` and `QWIKI_ASSETS_URL` constants before including the central `index.php`. Each subwiki gets a lightweight two-line bootstrap file instead of duplicating the entire `lib/`, `assets/`, and `api/` tree.
- **Lightweight Subwiki Bootstraps**: When provisioning a new subwiki in hosted mode, SubwikiManager generates an `index.php` bootstrap automatically pointing to the central core — no manual setup required.
- **Shared Extension Fallback**: ExtensionManager automatically falls back to the central shared extensions directory when local per-subwiki extensions are not present, ensuring all subwikis have access to core extensions.
- **Automatic Update Propagation**: In hosted mode, a single core update rolls out to every subwiki simultaneously — no per-subwiki update runs needed.
- **Reserved Name Expansion**: `_core` and `admin` are added to the reserved slug list to prevent subwikis from accidentally shadowing core system paths.

---

## 📬 14. Editorial Postbox Document Transfer & Desktop Integration

Asynchronously transfer documents and folders across independent wikis or directly from your local workstation with non-destructive, reviewer-controlled staging:

- **Peer-to-Peer & Multitenant Transfers**: Copy single documents, entire categories, or bulk document sets between standalone wikis and subwikis, or across separate hosted tenants via token-authenticated webhooks.
- **Zero In-Place Overwrites (Mandatory Editorial Review)**: Inbound transfers land safely in a `.htaccess`-protected staging inbox queue. The recipient reviews the incoming document, sees category suggestions, selects the target category, customizes the title or slug, and confirms the import.
- **Desktop & Server CLI (`qwiki-postbox.py`)**: Zero-dependency Python 3 CLI tool to transmit local `.md` and `.html` files or entire folders from local computers, CI/CD pipelines, or servers directly to any Qwiki instance.
- **Automated Local Asset Packaging**: Automatically detects and bundles referenced local images into a self-contained envelope, unpacks them to the destination `uploads/images/`, and remaps Markdown and HTML links without broken paths.
- **Native OS File Manager Integrations**:
  - **Linux (Nautilus, Nemo, Caja)**: Right-click any document or folder ➔ `Scripts` ➔ `Send to Qwiki`.
  - **Windows (File Explorer)**: Right-click any document or folder ➔ `Send to` ➔ `Send to Qwiki`.
  - **macOS (Finder Quick Actions)**: Right-click any document or folder ➔ `Quick Actions` ➔ `Send to Qwiki`.
- **1-Click Desktop Bundle Download**: Download pre-configured desktop tools directly from the Postbox modal in Qwiki with pre-populated URL and access token.

---

## 🤖 15. Controlled LLM & AI Agent Access Pipeline

Standalone Qwiki provides a dedicated, key-authenticated API endpoint (`api/llm.php`) engineered specifically for Large Language Models, autonomous coding agents (Claude Desktop, Cursor IDE, Gemini CLI, OpenAI GPT Actions), and Retrieval-Augmented Generation (RAG) pipelines:

- **Token-Authenticated API (`qwk_llm_...`)**: Generate granular API keys from **⚙️ Settings ➔ 🤖 LLM & AI Agent Access** or the Admin menu. Authenticate requests via standard `Authorization: Bearer <key>` header or query parameter (`?key=<key>`).
- **Granular Category Scoping**: Confine an agent key to a specific folder branch (with recursive subcategory access) or grant full-wiki visibility.
- **Document Type Filtering**: Restrict keys to Markdown only (`markdown`), or permit binary and structured formats (`pdf`, `html`, `text`, `all`).
- **Lifecycle & Expiration Controls**: Configure optional expiration dates with 1-click renewal extensions, or instantly revoke keys without deleting audit entries.
- **Multiple Operational Retrieval Modes**:
  - **Document Tree Mode** (`/api/llm.php?mode=tree`): Returns structured document catalog including document titles, URL slugs, byte sizes, word counts, token estimates, and API retrieval endpoints. Supports standard [llms.txt](https://llmstxt.org) Markdown format (`format=llms.txt`).
  - **Document Content Mode** (`/api/llm.php?mode=doc&slug=<slug>`): Retrieves clean document content with absolute canonical link and image rewriting, word counts, token estimation, and byte-range truncation (`max_bytes`) for context window management.
  - **Live Search Mode** (`/api/llm.php?mode=search&q=<query>`): Fast keyword and semantic relevance search across authorized documents with calculated scoring and contextual excerpts.
  - **OpenAPI 3.0 Schema** (`/api/llm.php?mode=schema`): Provides an auto-generated OpenAPI 3.0 specification for 1-click import into OpenAI Custom GPT Actions, LangChain, or Claude Custom Tools.

---

## ✨ 16. Native Gemini AI Assistant Extension

Standalone Qwiki bundles a native AI assistant utility extension (`tool-gemini-assistant`) powered by Google's Gemini models (`gemini-2.5-pro`, `gemini-2.5-flash`, or custom models):

- **Automated Metadata & SEO Synthesis**: Analyzes active document content with 1 click to generate concise meta descriptions and relevant topic tags.
- **Live OpenGraph / Social Share Simulator**: Renders an interactive social preview card showing how the title, generated description, URL, and tag pills will unfurl when shared on social networks or messaging apps.
- **1-Click Metadata Application**: Apply generated descriptions and tags directly to the active document in `qwiki.json` without leaving the viewer.
- **Secure Key Management & Sandbox Protection**: Configure Gemini API keys in the extension settings tab. Keys are securely stored and masked in the UI. In demo mode, live API key updates are protected and simulated metadata is returned safely.

---

## 📁 17. Upload Conflict Resolution & In-Place Document Replacement

Intelligent conflict detection and in-place replacement safeguards streamline document uploads and media asset maintenance:

- **Automated Collision Detection**: When uploading a Markdown, HTML, PDF, or media file whose identifier already exists in the wiki, Qwiki detects the collision and displays an interactive resolution modal.
- **Option 1: In-Place Document Replacement**: Overwrites physical file contents on disk with the uploaded version while preserving the document's URL slug, parent category, custom CSS themes, read-only protection status, and public share keys.
- **Option 2: Upload as Copy (Auto-Incremented Slug)**: Automatically increments numeric suffixes (`my-doc-1`, `my-doc-2`) or accepts a custom slug, verifying uniqueness against both the navigation tree and physical files on disk before saving.
- **Direct "Replace File" Action**: In the document toolbar (`btn-replace-document`) and document properties modal (`⚙️ Edit Details`), administrators can upload a new file directly to replace the active document without deleting or recreating the navigation node.

