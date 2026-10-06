# Managing Content

As an Admin, you have full control over the documentation tree and content directly from the web interface.

---

## ➕ Creating New Documents

Click the **`New Document`** button at the top of the sidebar navigation (or **`+ Cat`** to add a category):
1. **Markdown (`.md`)**: Write new Markdown using the Toast UI WYSIWYG editor or import an existing `.md` file.
2. **HTML Pages (`.html`)**: Design rich interactive pages using the built-in **SunEditor** WYSIWYG editor, switch to raw code view with automatic two-way synchronization, use **`Ctrl+S`** / **`Cmd+S`** to save quickly, or upload an existing `.html` file.
3. **Interactive Forms (`.form.json`)**: Build native flat-file surveys, feedback forms, and event RSVPs with a visual drag-and-drop builder, field presets, email notifications, webhooks, anti-spam honeypots, and CSV submission export.
4. **Upload PDF / Files**: Upload `.pdf` documents directly to the wiki tree. If a file with the same identifier already exists, an interactive conflict resolution modal lets you either replace the existing document in-place or upload as a copy with an auto-incremented slug.
5. **Google Docs (`gdoc`)**: Embed any published Google Doc URL with automatic formatting and theme integration.
6. **Web Links (`link`)**: Add external websites or same-domain hyperlinks directly into the sidebar navigation.
7. **Remote Sharelinks (`remote`)**: Transclude a document from another Qwiki instance using its public `?share=...` link. The document is rendered natively, cached locally, and locked as read-only (Single Source of Truth).

---

## 🔗 Sidebar Hyperlinks

You can add hyperlinks to external locations or internal pages directly into the left sidebar:
- **Any Level**: Add hyperlinks at the root of the sidebar or nested inside any category or folder.
- **Smart Tab Routing**:
  - **External Pages**: Links to external domains automatically open in a new tab with `target="_blank"` and `rel="noopener noreferrer"`, preserving your active reading session in Qwiki.
  - **Same-Domain Pages**: Links pointing to your current domain or relative paths open in the same tab (`target="_self"`).
- **Admin Management**: Authenticated Admins can click the inline gear icon (`⚙️`) directly on the link in the sidebar to update its title, target URL, or delete it.
- **Drag & Drop**: Grab the drag handle (`⣿`) on any hyperlink to move it between folders or reorder it anywhere in the sidebar.

---

## 📝 Editing Existing Pages

- **Markdown Documents**: Click **`✏️ Edit Content`** in the top right toolbar to open the inline visual editor with live markdown preview.
- **HTML Documents**: Click **`✏️ Edit HTML`** in the document viewer toolbar to open SunEditor, format text, modify tables, or tweak raw HTML code. Edits in Code View mode sync automatically on save.
- **Interactive Form Documents**: Click **`⚙️ Edit Form Builder`** or **`📊 View Submissions`** in the document viewer toolbar to tweak fields, configure outgoing webhooks, or inspect respondent submissions with 1-click CSV export.
- **Document Metadata & Category Relocation**: Click **`⚙️ Edit Details`** to change the document title, custom slug, category/folder, individual CSS theme, short description, or social preview image. The current category is accurately prefilled in the hierarchical selector (even for deep nested folders), preventing accidental folder resets when saving title or metadata updates. Moving a document to another category automatically relocates the physical file on disk (e.g. into `content/<target-folder>/`), renames it if the slug changed, resolves any naming clashes with numeric suffixes, and updates `qwiki.json`.
- **In-Place File Replacement**: Click the **`Replace Document Content`** toolbar button to upload a revised file and overwrite the active document's contents on disk without breaking existing bookmarks, permissions, or share keys.

---

## 🔄 Upload Conflict Resolution & File Replacement

When uploading documents or assets that share a filename or slug with an existing wiki document:

- **Conflict Detection Modal**: Qwiki immediately intercepts collisions and presents the existing document title, slug, and containing category.
- **1. Replace Existing Document**: Overwrites the physical file on disk with your uploaded version. All document settings (slug, category, permissions, custom themes, and secret share tokens) remain intact.
- **2. Upload as Copy (Auto-Incremented Slug)**: Automatically increments numeric suffixes (`doc-1`, `doc-2`) or accepts a custom slug name, checking both `qwiki.json` and physical disk storage to guarantee unique URLs.
- **Direct In-Place Replacement**: When viewing an existing Markdown or PDF document, click the **Replace Document Content** icon in the viewer action bar to swap the underlying file directly without deleting and recreating the article.

---

## 🎬 Embedding Playable Videos

Standalone Qwiki automatically turns standalone video URLs into responsive, full-featured players in Markdown:
- **YouTube**: Paste a watch URL (`https://www.youtube.com/watch?v=...`), short link (`https://youtu.be/...`), or Shorts link. You can also specify start timestamps like `?t=1m30s`. All embeds use privacy-enhanced `youtube-nocookie.com`.
- **Vimeo**: Paste any standard Vimeo link (`https://vimeo.com/...`) for immediate playback with Do Not Track (`dnt=1`) privacy.
- **Loom**: Paste a Loom share URL (`https://www.loom.com/share/...`) to embed responsive screencasts.
- **Direct Video Files**: Paste any direct `.mp4`, `.webm`, `.ogg`, or `.mov` link to render a native HTML5 video player with playback controls and download fallback.
- **Custom Captions**: Format video links as `[Caption Title](https://...)` or image syntax `![Caption Title](video.mp4)` to display an italicized subtitle below the player.
- **Inline Text Links**: Links placed inside sentences remain standard clickable hyperlinks and are never converted into embeds.

---

## 🔒 Multi-Tab & Collaborative Soft Locks

To prevent concurrent write conflicts and accidental overwrites when multiple tabs or users edit the same document:
- **Advisory Lock Banner**: If another tab or user is editing a document, a warning banner alerts you immediately.
- **Cross-Tab Synchronization**: Tabs communicate in real-time via `BroadcastChannel` (`qwiki_doc_locks`) with zero network delay.
- **Takeover Option**: If a previous session was abandoned or an urgent edit is needed, authorized admins can force-takeover the active lease.
- **Automatic Draft Safety Net**: While editing in Toast UI or SunEditor, your work is continuously mirrored to browser `localStorage`. If a session is evicted, your draft is preserved and can be recovered.

---

## 🖼️ Media & Image Gallery

1. Open the header menu and click **`🖼️ Image Gallery`**, or click the **`🖼️`** toolbar icon directly inside the Markdown or HTML editor.
2. Browse, search, filter, and inspect all uploaded images and generated vector visuals in `uploads/`.
3. **Direct Editor Selection**: When an editor is active, the gallery displays an "Article Editor Active" banner and surfaces **`✓ Select`** / **`✓ Select Image`** buttons on every card. Clicking select immediately formats and inserts the image Markdown tag (`![alt](url)`) or HTML `<img>` tag at your cursor position, and automatically closes the modal.
4. Click any asset to preview dimensions and file size, customize alt text, copy embed snippets, or insert directly into the active editor.

---

## ✨ Generating Charts & Visuals

1. Open the header menu and click **`✨ Generate Visual / Chart`**.
2. Select your desired visual type (Bar Chart, Line Chart, Pie/Donut Chart, Flow Diagram, or Status Badge).
3. Type a directive (e.g. `Quarterly Sales: Q1: 30, Q2: 55, Q3: 90, Q4: 120`).
4. Click **`Generate & Save`** to generate the vector SVG graphic into `uploads/`.
5. Click **`Insert into Editor`** (or **`Copy Markdown`**) to place the visual into your document.

---

## 📊 Rendering Mermaid & Visual Diagrams

Standalone Qwiki natively renders Mermaid diagrams directly from standard fenced code blocks (` ```mermaid `) in Markdown articles:

1. **Diverse Diagram Models**: Create flowcharts, sequence diagrams, state machines, entity-relationship diagrams, class hierarchies, user journeys, mindmaps, quadrants, and timelines.
2. **Natural Width & Legibility**: Wide diagrams retain their sharp, uncompressed native scale without shrinking into miniature boxes.
3. **Responsive Horizontal Scrolling**: If a diagram exceeds the article column width, the container provides smooth, touch-friendly horizontal scrolling (`overflow-x: auto`) aligned to the start.
4. **Instant Theme Re-Rendering**: Diagrams adapt instantly when switching between dark and light themes without requiring page reloads.

---

## 📐 Mathematical Formulas & LaTeX/KaTeX Notation

Standalone Qwiki natively renders mathematical notation, equations, and LaTeX symbols across both Markdown content and Mermaid diagrams:

1. **Inline Math (`$...$`)**: Wrap formulas in single dollar signs (e.g. `$x_1 + x_2 = y_k$`). Underscores and asterisks are protected and will never be mangled into Markdown italics.
2. **Display Equations (`$$...$$`)**: Write centered block equations on their own lines or in multi-line blocks:
```
$$
\int_0^\infty e^{-x^2} dx = \frac{\sqrt{\pi}}{2}
$$
```
   Wide equations automatically support responsive horizontal scrolling to prevent overflowing columns on mobile and desktop viewports.
3. **Shorthand & Symbol Normalization**: Common LaTeX symbols and shorthands are automatically converted into crisp Unicode characters:
   - **Arrows**: `$to$`, `$\to$`, or `\to` render as `→`; `\gets` or `\leftarrow` as `←`; `\implies` as `⇒`; `\iff` as `⇔`.
   - **Comparisons**: `\approx` as `≈`, `\le` as `≤`, `\ge` as `≥`, `\ne` as `≠`, `\equiv` as `≡`.
   - **Operators & Sets**: `\pm` as `±`, `\times` as `×`, `\infty` as `∞`, `\in` as `∈`, `\sum` as `∑`, `\partial` as `∂`.
   - **Greek Alphabet**: `\alpha` as `α`, `\beta` as `β`, `\theta` as `θ`, `\pi` as `π`, `\Delta` as `Δ`, `\Omega` as `Ω`.
4. **Seamless Mermaid Diagram Support**:
   - Write shorthands directly inside diagram edge labels and node descriptions without syntax crashes (e.g. `A --> |$to$| B` renders cleanly as `A --> |→| B`).
   - Use `$$...$$` blocks inside node labels (e.g. `A["$$\frac{a}{b}$$"]`) to render publication-grade KaTeX typography directly inside your diagrams.
5. **Theme & Currency Awareness**:
   - Math equations inherit active theme colors in both dark and light modes.
   - Standard currency amounts (e.g. `$50 and $100`) are protected and remain literal text.

---

## 🤖 Gemini AI Assistant & Social Meta Generation

Standalone Qwiki includes a built-in AI Assistant utility (`tool-gemini-assistant`) powered by Google's Gemini API:

- **Launch Assistant**: Click the **`✨ AI Assistant`** button in the header utility bar when viewing any document, or access AI Settings directly from the **Settings** or **LLM Keys** modals.
- **Generate Descriptions & Tags**: Click **`Generate Description & Tags`** to analyze the active document. The assistant produces a concise summary description and semantic topic tags.
- **Interactive Social Card Simulator**: Preview in real time how your document will look when shared on social networks (𝕏, LinkedIn, Facebook, Slack, Telegram), complete with title, URL, description, and tag pills.
- **1-Click Apply**: Click **`Apply to Document`** to save the generated description and tags directly into `qwiki.json`.
- **Configurable Model & API Key**: In the assistant modal, switch to the **Settings** tab to enter your Google Gemini API key, click **Auto-Detect Models** to discover supported models for your key, or select preferred models (`gemini-2.5-flash`, `gemini-2.5-pro`, `gemini-2.0-flash`, or custom models) with 1-click connection testing.

---

## 📷 Uploading Images

1. While in the Markdown Editor, click the **`📷 Insert Image`** button.
2. Select an image from your computer (`.png`, `.jpg`, `.svg`, `.webp`).
3. The file is uploaded to `uploads/images/` and the Markdown tag is inserted at your cursor position automatically.

---

## 🖐️ Drag & Drop Menu Reordering

You can organize your documentation hierarchy visually:
- Grab the drag handle (`⣿`) next to any menu item in the left sidebar.
- **Reorder**: Drag it up or down to change its position in the list.
- **Nest**: Drag a document into a folder or sub-category. When dropped into a new category, Qwiki automatically moves the physical file on disk to match the destination folder structure.
- Changes sync automatically to `qwiki.json`.

---

## 🛡️ Category & Document Protection

Standalone Qwiki provides robust protection mechanisms to safeguard critical articles and structures against accidental edits or deletions:

- **UI Lock Toggle**: Lock or unlock individual documents directly from the **Edit Details** modal (`⚙️ Edit Details`) — no need to manually edit `qwiki.json`. The lock toggle immediately hides the edit (`✏️ Edit Content`) and delete buttons, replacing them with a **Protected Document** badge. Unchecking the toggle cleanly restores full editing capabilities.
- **Ancestor Inheritance**: Documents inside a directly locked category automatically inherit read-only protection. Their lock toggle in Edit Details is disabled with a note indicating the lock comes from a parent category. Individual documents cannot be unlocked while their containing category is locked.
- **Direct Category Lock**: Click the category edit icon (`⚙️`) and toggle **Lock Category (Prevent Deletion)**. Directly locked categories cannot be deleted, and all nested documents inside them automatically inherit read-only protection.
- **Cascading Deletion Safety Net**: Any category or folder containing protected documents automatically inherits deletion protection across all hierarchy levels. Even when a protected document is deeply nested within subfolders, parent folders cannot be deleted.
- **Visual Lock Indicators**: Protected categories display a lock icon (`🔒`) in the navigation sidebar. In the Edit Category modal, the deletion button is replaced with a **Protected Category** badge.

---

## 🖨️ Print & Social Sharing

- **Print / PDF — Markdown & Standard Documents**: Click **`🖨️`** in the document action bar to print or save the article as a clean PDF using the print-optimized stylesheet.
- **Print / PDF — HTML Documents**: The **`🖨️ Print / Save as PDF`** button in the HTML viewer toolbar triggers the browser print dialog scoped to the embedded document. Redundant print icons are hidden automatically when the HTML viewer is active; in the full-tab expanded view or share mode, the in-document button remains the entry point.
- **Full-Screen Sharing & Modal**: Click **`🔗`** in the document action bar to open the Share modal. Available to both Administrators and Viewers, it generates or retrieves the unique reader share link (`?share=...`).
- **Social Sharing Shortcuts**: Inside the Share modal or from the Zen reader's floating **`🔗 Share`** dropdown, 1-click shortcuts let you immediately share the document across 𝕏 (Twitter), LinkedIn, Facebook, WhatsApp, Telegram, and Slack with pre-filled titles and links.
- **Custom Social Card Metadata**: In **`⚙️ Edit Details`**, administrators can specify a tailored **Short Description** and **Social Share Image URL** per article to display rich visual preview cards when shared.

---

## 🏠 Multitenant Hosted Mode

For managed hosting environments where multiple independent wikis need to share a single Qwiki installation:

1. Place the canonical Qwiki core (with `lib/`, `assets/`, `api/`, and `index.php`) in a central directory (e.g. `_core/`).
2. For each subwiki, create a minimal `index.php` bootstrap:
```php
<?php
define('QWIKI_BASE_DIR', __DIR__);
define('QWIKI_ASSETS_URL', '/_core/assets');
require_once '/path/to/_core/index.php';
```
3. Each subwiki has its own `qwiki.json`, `content/`, and `uploads/` — only the core code is shared.

- **Automatic Bootstrap Generation**: When provisioning subwikis through the admin UI in hosted mode, SubwikiManager generates the bootstrap file automatically — no manual file creation required.
- **Shared Extension Fallback**: ExtensionManager falls back to the central shared extensions directory if local per-subwiki extensions are not present.
- **Single-Update Rollouts**: Updating the core once propagates to every subwiki immediately — no per-subwiki update steps.
- **Reserved Paths Protected**: `_core` and `admin` are added to the reserved slug list to prevent subwiki slugs from shadowing core system directories.

---

## 📬 Document Postbox & Desktop Transfers

The **Postbox** extension provides an asynchronous document transfer pipeline to copy documents between wikis or send them directly from your desktop computer:

- **Sending from Qwiki**:
  - Click the **`📬`** quick trigger icon next to any document in the navigation sidebar, or open **Tools ➔ Qwiki Postbox ➔ Send Documents**.
  - Select individual documents, entire categories, or multiple pages.
  - Choose a configured peer wiki, enter a remote wiki webhook URL, or copy to a sibling subwiki.
- **Inbound Review Queue (Zero Overwrites)**:
  - Incoming documents land in the **Inbound Review** inbox (`uploads/.postbox/inbox/`) and will never overwrite existing pages.
  - Administrators review pending documents, preview markdown, accept suggested destination categories (or choose a custom folder), customize title or slug, and click **Ingest Document**.
- **Desktop & Workstation Integration**:
  - Download the zero-dependency Python CLI and native OS integration scripts from **Qwiki Postbox ➔ Peers & Settings**.
  - **Linux (GNOME Files / Nautilus, Nemo, Caja)**: Right-click any document or folder ➔ `Scripts` ➔ `Send to Qwiki`.
  - **Windows (File Explorer)**: Right-click any document or folder ➔ `Send to` ➔ `Send to Qwiki`.
  - **macOS (Finder)**: Right-click any document or folder ➔ `Quick Actions` ➔ `Send to Qwiki`.
  - **Terminal / CI/CD**: Run `python3 qwiki-postbox.py send path/to/file.md --category "Guides"`.

---

## 🌐 Federated Remote Documents (Single Source of Truth)

To embed authoritative documentation from another Qwiki installation without duplicating files or risking content drift:

1. Click **`New Document`** in the left sidebar and choose the **`🌐 Remote Sharelink`** tab.
2. Select the target category/folder.
3. Paste the full share URL from the origin wiki (e.g. `https://origin-wiki.example.com/?share=7c8e2a1d9f4b3e6c`).
4. Enter an optional title (or leave blank to auto-detect the title directly from the origin wiki).
5. Click **Link Remote Document**.

### Key Behaviors:
- **Single Source of Truth**: The remote document is rendered natively in your wiki's theme but is locked against local edits (`data-doc-readonly='1'`). Changes must be made on the origin wiki.
- **Resilient Caching & Offline Fallback**: Content is cached locally for 1 hour. If the origin wiki is temporarily unreachable, your wiki continues to display the cached version with an advisory warning notice.
- **Admin Refresh**: Authenticated administrators can click the **`🔄 Refresh`** button on the document banner to fetch and cache updates from the origin immediately.
- **Automated Asset Rewriting**: Images referenced in the remote article are rewritten on the fly to load from the canonical origin server without broken links.
- **SSRF Hardening**: Requests to private subnets, cloud metadata services, loopback, or non-HTTP protocols are blocked by default.

---

## 🚀 Headless Document Publishing API

External automation scripts, CI/CD pipelines, and internal tools can publish documents directly into Standalone Qwiki via HTTP POST requests:

- **Endpoint**: `POST /api/publish.php`
- **Authentication**: Pass your API key via header `X-API-Key: <your-key>` or query string `?apiKey=<your-key>`.
- **Supported Formats**:
  - `type: "markdown"` (Default): Plain GitHub-Flavored Markdown.
  - `type: "html"`: Self-contained HTML documents (dossiers, reports). Automatically preserves `<style>`, `@media print`, `<meta>`, and SVG tags while sanitizing executable vectors.
- **Response Payload**: The API returns a JSON response containing the document slug, file path, and an auto-generated cryptographic `shareKey` and `shareUrl`:
```json
{
  "success": true,
  "bookId": "guides",
  "slug": "technical-dossier-2026",
  "file": "content/guides/technical-dossier-2026.html",
  "type": "html",
  "shareKey": "7c8e2a1d9f4b3e6c",
  "shareUrl": "https://wiki.example.com/?share=7c8e2a1d9f4b3e6c",
  "url": "https://wiki.example.com/guides/technical-dossier-2026"
}
```
- **Companion Translations**: Include a `translations` object (e.g. `{"af": "technical-dossier-2026-af"}`) to link multilingual versions together.
