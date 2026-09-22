# Changelog

All notable changes to Standalone Qwiki will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.13.0] - AgentFlow - 2026-09-22

### 🤖 Controlled LLM & AI Agent Access Pipeline
- **Dedicated Key-Authenticated REST API (`/api/llm.php`)**: Built-in endpoint engineered specifically for Large Language Models, autonomous coding agents (Claude Desktop, Cursor IDE, Gemini CLI, OpenAI GPTs), and local RAG ingestion pipelines.
- **Granular Category & Scope Control**: Confine individual API keys to a specific folder or category branch (with automatic recursive subcategory access) or permit full-wiki exploration.
- **Document Format Filtering**: Authorize keys to access Markdown documents only (`markdown`), or permit binary and structured formats (`pdf`, `html`, `text`, `all`).
- **Lifecycle, Expiration & Revocation**: Set optional expiration dates with 1-click renewal extensions, or immediately revoke/reactivate keys without deleting audit entries.
- **4 Operational Retrieval Modes**:
  - **Document Tree Mode** (`?mode=tree`): Structured catalog with titles, slugs, URLs, byte sizes, word counts, token estimates, and direct API endpoints. Supports standard [llms.txt](https://llmstxt.org) Markdown format (`format=llms.txt`).
  - **Document Content Mode** (`?mode=doc&slug=<slug>`): Clean Markdown and plain-text extraction with absolute canonical link and image rewriting, token counts, and byte-range truncation (`max_bytes`) for context window optimization.
  - **Live Search Mode** (`?mode=search&q=<query>`): Fast keyword and semantic relevance search across authorized documents with calculated relevance scoring and contextual excerpts.
  - **OpenAPI 3.0 Tool Schema** (`?mode=schema`): Auto-generated OpenAPI 3.0 specification for 1-click import into OpenAI Custom GPT Actions, LangChain, or Claude Custom Tools.
- **In-App Key Management UI**: Dedicated administration modal accessible via **Admin Dropdown ➔ 🤖 LLM & API Access** and **⚙️ Settings ➔ LLM & AI Agent Access** for generating, copying, inspecting, and managing API keys.

### ✨ Native Gemini AI Assistant Extension
- **Google Gemini API Integration (`tool-gemini-assistant`)**: Built-in utility extension leveraging Google's Gemini models (`gemini-2.5-pro`, `gemini-2.5-flash`, or custom models) to provide real-time editorial assistance.
- **Automated Metadata & SEO Synthesis**: Analyzes active document content with 1 click to extract concise meta descriptions and semantic topic tags.
- **Interactive OpenGraph / Social Card Simulator**: Real-time interactive preview card demonstrating exactly how the document title, description, domain, and tags will unfurl across social networks (𝕏, LinkedIn, Facebook, Slack, Telegram).
- **1-Click Metadata Application**: Seamlessly writes synthesized descriptions and tags directly to the active document in `qwiki.json`.
- **In-App Key & Model Management**: Configurable Gemini API key input and model selector with real-time connection testing. Built with native demo-mode safeguards to prevent saving API keys in public sandboxes while providing realistic simulated metadata.

### 🔄 Smart Upload Conflict Resolution & In-Place Document Replacement
- **Automated Collision Interception**: Automatically detects when an uploaded Markdown, HTML, PDF, or media file shares an existing document title or slug in the wiki.
- **Conflict Resolution Modal**:
  - **Option 1: In-Place Document Replacement**: Overwrites physical file contents on disk with the uploaded version while preserving the document's URL slug, parent category, custom CSS themes, read-only protection status, and public share keys.
  - **Option 2: Upload as Copy (Auto-Incremented Slug)**: Automatically increments numeric suffixes (`doc-1`, `doc-2`) or accepts a custom slug, verifying uniqueness against both the navigation tree and physical files on disk before saving.
- **Direct "Replace Document Content" Action**: Dedicated action bar button (`btn-replace-document`) and properties modal control allowing administrators to swap out Markdown or PDF document files on disk directly.
- **Single-Item Deletion Resilience**: Ensures duplicate or renamed files are tracked independently without accidental cross-document deletion corruption.

### 🧪 Automated Test Coverage
- `tests/llm_access_test.php`: 32 assertions verifying key generation, authorization channels (Bearer headers, query params), revocation, expiry extension, category scoping, type filtering, `llms.txt` formatting, OpenAPI schema dispatch, and search ranking.
- `tests/gemini_assistant_test.php`: 31 assertions covering extension registration, frontend assets, modal rendering, social card preview, admin authentication, settings storage, metadata persistence to `qwiki.json`, and demo-mode protection.
- `tests/upload_duplicate_replacement_test.php`: Unit and integration assertions verifying `Navigation::isSlugTaken`, `Navigation::generateUniqueSlug`, conflict detection, in-place replacement, copy renaming with auto-incremented slugs, protected document safeguards, and deletion resilience.

---

## [1.12.1] - FormFlow - 2026-09-22

### 📝 Native Form Extension Tracking & Release Restoration
- **Restored Flat-File Form Extension (`page-form`)**: Tracked and committed the native `page-form` extension across repository history and production archives. Includes drag-and-drop field builder, submission storage, anti-spam honeypot, webhook dispatch, and CSV export.
- **Root-Scoped Scratch Test Rules**: Updated `.gitignore` from `test_*` to `/test_*`, ensuring root-level scratch files remain ignored while preventing test suites inside `tests/` (`tests/test_form_page_extension.php`) from being masked.
- **Nested Category Hierarchy in Extension Modals**: Upgraded `ExtensionManager::renderAddDocumentForms` and extension creation modals (`page-form` and `page-html`) to support `$categoryHierarchy` with visual depth indentation (`↳ Subfolder`) and active folder preselection, bringing full subfolder authoring parity with built-in document types.
- **Clean URL Redirection on Form Creation**: Form creation now seamlessly redirects using clean route paths (`category/slug`) matching the rest of the application.
- **Editorial Postbox Form Support**: Added `form` (`.form.json`) to `ALLOWED_DOC_TYPES` in `Envelope.php` and added the Interactive Form format option to the Postbox Ingestion modal for asynchronous cross-wiki transfers.
- **Sample Feedback Survey**: Bundled a clean, professional Community Feedback Survey in `demo-data/` and default configuration.

---

## [1.12.0] - PathNest - 2026-09-20

### 🗂️ Category Prefilling & Hierarchy Preservation
- **Immediate Parent Category Detection (`Navigation::findChapterParentId`)**: Added recursive category resolution to accurately determine the immediate containing folder for any document slug across nested folder hierarchies.
- **Accurate Category Prefilling in Edit Details**: In the document properties modal (`⚙️ Edit Details`), the "Category / Folder" dropdown is now accurately prefilled with the document's immediate parent category (both in initial server-side HTML rendering and JavaScript modal population), preventing nested documents from defaulting to the root category.
- **Accidental Relocation Prevention**: Modifying document properties (such as title, custom slug, or theme) without explicitly changing the category dropdown now reliably preserves the document's original parent category in `qwiki.json` and on disk, eliminating unintentional moves to the root folder.
- **Subfolder Direct Routing & Fallback Resolution**: Direct clean URLs and query requests to subfolder paths (e.g. `/subfolder/document` or `?chapter=slug`) now resolve cleanly across all allowed books without falling back to the wiki root.
- **Add Document Subfolder Context**: When browsing inside a subfolder, opening the Add New Document tabs (`✏️ New Markdown`, `📁 Upload File`, `🌐 Google Doc`, `🔗 Web Link`) now preselects the active subfolder as the target destination.

### 🔗 Document Sharing & Social Shortcuts
- **Telegram & Slack Sharing**: Added 1-click sharing to **Telegram** (`https://t.me/share/url`) and **Slack** (`#️⃣ Share on Slack`) across both the Share Document modal and the floating Zen reader dropdown. The Slack action automatically copies the share URL to the clipboard, displays confirmation (`✅ Copied for Slack!`), and opens the Slack client ready for pasting with full Open Graph unfurling.

### 🛠️ Split Admin Authoring & UI Ergonomics
- **Contextual Split Authoring**: Added prominent **`New Document`** and **`+ Cat`** buttons to the top of the sidebar navigation for instant access to authoring workflows, while streamlining the header profile dropdown to focus on Tools, Administration, and Account actions.
- **Theme Editor & Contrast Accessibility**: Enhanced contrast and dark/light mode readability across interactive modals and toolbars.

### 📦 Clean Release Packaging & Updater Hardening
- **Native Archive Filtering (`.gitattributes`)**: Configured `export-ignore` rules for `tests/`, `AGENTS.md`, `package.json`, `demo-reload.php`, and build tools so GitHub release archives (`zipball_url` and Source code zips) automatically omit internal tests and dev artifacts.
- **In-App Updater Defense-in-Depth**: Hardened `api/admin.php` (`install_update`) to exclude test suites, dev files, and git metadata from extraction, preventing production instances from being polluted during updates. Added release asset prioritization in `check_updates`.
- **Distribution Build Script (`tools/build-release.sh`)**: Added 1-command release packaging script that compiles and verifies clean `dist/standalone-qwiki-v*.zip` production archives.

### 🧪 Automated Test Coverage
- `tests/edit_chapter_category_test.php`: 18 assertions covering `Navigation::findChapterParentId`, server-side modal rendering for root and nested categories, dropdown selection validation, and API category preservation on metadata update.
- `tests/anchor_and_contrast_test.php`: 30 assertions verifying in-page heading ID generation, WCAG AA contrast compliance for links in dark and light modes, and smooth scroll offsets.
- `tests/clean_release_test.php`: 30 assertions verifying `.gitattributes` export-ignore coverage, `git archive` production cleanliness, updater exclusions, and release asset resolution.
- Updated `tests/share_rights_test.php` covering Telegram and Slack sharing buttons in the share modal and Zen reader floating bar.

---

## [1.11.0] - PostFlow - 2026-09-18

### 📬 Editorial Postbox & Asynchronous Document Transfer
- **Non-Destructive Postbox Service**: Asynchronously transfer single documents, entire categories, or bulk pages between standalone wikis, subwikis, and multitenant instances via token-authenticated webhooks. Source documents remain completely untouched.
- **Mandatory Editorial Review & Staging Queue**: Inbound document transfers land safely in a `.htaccess`-protected staging inbox (`uploads/.postbox/inbox/`). Documents are never published or overwritten automatically.
- **Category Hint & Destination Mapping**: Senders can attach a suggested category hint; recipients preview the document in the Inbound Review queue, see suggested categories with 1-click adoption, or designate an alternative target category and customize metadata (title, slug, theme) before committing to the wiki tree.
- **Automated Local Asset Packaging & Link Remapping**: Automatically scans and bundles referenced local images into a self-contained schema 1.0 JSON envelope, extracts them safely to `uploads/images/`, and dynamically rewires Markdown and HTML links without broken paths.
- **Slug Collision Resolution**: Automatically detects slug collisions in target categories and resolves them cleanly using numeric suffixes (`-2`, `-3`).
- **Chapter-Level Quick Trigger**: Quick action `[📬]` button added directly alongside document options in the navigation sidebar for instant single-document dispatch.

### 🖥️ Desktop CLI & Native OS Context Menu Integrations
- **Zero-Dependency Python 3 CLI (`qwiki-postbox.py`)**: Standalone CLI utilizing Python standard libraries only (no external pip dependencies). Supports `send` (single files or directories), `--category` hint, `--all-assets` for unreferenced media, `--dry-run`, and `--json` envelope export.
- **1-Click In-App Bundle Download**: Administrators can download pre-configured desktop tools with their wiki's URL and access token pre-filled in `config.sample.json` directly from **Qwiki Postbox ➔ Peers & Settings**.
- **Linux Context Menu (GNOME Files / Nautilus, Nemo, Caja)**: Native `desktop/linux/install-nautilus.sh` installer adding **Right-click ➔ Scripts ➔ Send to Qwiki** with desktop notifications (`notify-send`) and error dialogs (`zenity`), complete with auto-reload (`nautilus -q`) on install.
- **Windows Context Menu (File Explorer)**: Native `desktop/windows/setup-sendto.bat` installer enabling **Right-click ➔ Send to ➔ Send to Qwiki**.
- **macOS Context Menu (Finder)**: Native `desktop/macos/install-quickaction.sh` Automator Quick Action installer enabling **Right-click ➔ Quick Actions ➔ Send to Qwiki**.

### 🧪 Automated Test Coverage
- `tests/postbox_test.php`: Packaging, asset extraction, staging, category mapping, overrides, slug collisions, and rejection.
- `tests/postbox_multitenant_test.php`: Intra-tenant subwiki transfers, cross-tenant boundary isolation enforcement, and token staging.
- `tests/cli_postbox_test.php`: CLI dry-runs, directory scanning, asset resolution, `--json` payload export, and ZIP download tool generation.

### 📚 Documentation
- Updated `demo-data/content/getting-started/features.md` and `content/getting-started/features.md` with Section 14 covering Postbox transfers and desktop tools.
- Updated `demo-data/content/user-guide/managing-content.md` and `content/user-guide/managing-content.md` with complete usage guides.
- Added comprehensive `tools/README.md` and bundled `README.txt` for desktop users.

---

## [1.10.0] - MultiGuard - 2026-09-17

### 🏠 Multitenant Hosted Mode
- **Shared Core Architecture**: Subwikis can now share a single central Qwiki installation. Define `QWIKI_BASE_DIR` and `QWIKI_ASSETS_URL` constants before including the central `index.php`; each subwiki uses a lightweight two-line bootstrap instead of duplicating `lib/`, `assets/`, and `api/`.
- **Automatic Bootstrap Generation**: When provisioning a new subwiki in hosted mode, `SubwikiManager` generates the per-subwiki `index.php` bootstrap and copies the parent `.htaccess` automatically — no manual setup required.
- **Shared Extension Fallback**: `ExtensionManager` now falls back to the shared central extensions directory when local per-subwiki extensions are not found, ensuring full extension parity across all hosted subwikis.
- **Dynamic Asset URL Routing**: `index.php` reads `$assetsUrl` from `QWIKI_ASSETS_URL` when defined, routing all CSS/JS asset references through the central core path.
- **Automatic Update Propagation**: In hosted mode, `SubwikiManager::pushUpdatesToSubwikis()` short-circuits — a single core update applies to every subwiki immediately without per-subwiki copy runs.
- **Reserved Name Expansion**: `_core` and `admin` added to `Config::getReservedNames()` to prevent subwiki slugs from shadowing core system paths.
- **New Test Suite**: `tests/multitenant_mode_test.php` covers hosted mode bootstrap generation, asset URL injection, and extension fallback scenarios.

### 🔒 Document-Level Protection & Ancestor Inheritance
- **UI Lock Toggle**: Administrators can now lock or unlock individual documents directly from the **Edit Details** modal (`⚙️`) without editing `qwiki.json` by hand. Locking immediately hides the edit and delete controls and displays a **Protected Document** badge; unchecking restores full capabilities.
- **`Config::isChapterDirectlyProtected()`**: New method to check whether a document has a direct `readOnly`, `editable: false`, or `locked` flag set on its own node, independent of any parent category state.
- **`Config::isChapterAncestorProtected()`**: New method to determine whether a document is locked via inheritance from an ancestor category, enabling the UI to display appropriate explanatory notices and disable the individual toggle.
- **Inheritance-Aware UI**: When a document's lock is inherited from a parent category, the Edit Details toggle is disabled with a notice: "Inherited from parent category." Individual document unlocking is blocked while the containing category is locked.
- **Demo Mode Safeguards**: In native demo mode (`Config::isDemoMode()`), protected demo documents cannot be unlocked by visitors — the unlock API endpoint validates demo mode state before applying any changes.
- **API Guards**: `api/admin.php` enforces lock checks on save, rename, move, and delete operations for both direct and ancestor-protected documents.
- **Expanded Test Coverage**: `tests/category_lock_test.php` extended with three new suites covering `isChapterDirectlyProtected`, `isChapterAncestorProtected`, and API guard validation for locked documents.

### 🔍 Clear Search Button
- **`×` Clear Button**: A clear button now appears inline in the sidebar search field when text is present. Clicking it empties the query, hides itself, restores the pre-search category collapse state, and returns focus to the search input.
- **Escape Key Shortcut**: Pressing `Escape` while the search input is focused and non-empty triggers the same clear action as the `×` button.
- **Pre-Search State Restoration**: The sidebar remembers which categories were collapsed before the search began and reinstates that exact state when the search is cleared.

### 📦 Backup & Export Extension
- **1-Click Full Backup**: Administrators can export a complete snapshot of the wiki into a standalone portable ZIP archive containing all document content (`content/`), uploaded media (`uploads/`), master navigation config (`qwiki.json`), and optional user credentials (`users.json`) or child subwiki bundles.
- **Interactive Selective Export**: Granular directory tree explorer with collapsible folder nodes, file type icons, live selection counters, and calculated byte totals.
- **Export Presets & Tree Filtering**: Quick presets for "Select All", "Content Only (No Media)", "Media Only", and "Clear All", alongside a real-time text filter to quickly isolate specific files or folders.
- **Security & Path Validation**: Strict directory traversal safeguards (`backupIsSafePath`) blocking sensitive files (`.env`, `.git`) and unauthorized path breakouts.
- **Asset Cache Busting**: `ExtensionManager::getFrontendAssets()` automatically appends `?v=<filemtime>` version hashes to all local extension stylesheets and scripts, ensuring browsers never serve stale client code.
- **Automated Test Suite**: Added `tests/backup_test.php` with 27 unit and integration tests covering extension registration, authentication enforcement, traversal defenses, and ZIP archive integrity.

### ⚙️ SVGbob WASM Refactor
- Replaced automatic WASM loading with manual `WebAssembly.instantiateStreaming` instantiation for the svgbob diagram renderer, improving cross-environment reliability and eliminating edge-case initialization failures.

### 📚 Documentation
- Updated `demo-data/content/getting-started/features.md` and `demo-data/content/user-guide/managing-content.md` to document multitenant hosted mode, the document lock UI toggle, ancestor inheritance, and the clear search button.
- Synchronized all changes into live `content/` documentation.

---

## [1.9.8] - TreeGuard - 2026-09-12

### 🛡️ Category Lock & Deletion Protection
- **Cascading Category Deletion Protection**:
  - Categories containing protected documents (`"readOnly": true` or `"editable": false`) now automatically inherit deletion protection, preventing documents from being accidentally deleted through the deletion of parent folders.
  - Multi-level nested folder hierarchies are fully protected: ancestor folders cannot be removed if any nested subfolder contains a protected document.
- **Direct Category Lock**:
  - Categories can now be directly configured with `"readOnly": true`, `"editable": false`, or `"locked": true` in `qwiki.json` to prevent category deletion.
  - Child documents within directly locked categories inherit read-only protection against modification and deletion.
  - Added `Config::isCategoryProtected($categoryId)` and `Config::isCategoryDirectlyProtected($categoryId)`.
  - Added `is_category_protected($categoryId)` backward-compatibility helper in `api/admin.php`.
- **UI & Administrative Controls**:
  - Added visual lock indicators (`🔒`) in the sidebar for protected categories.
  - In the Edit Category modal, the "Delete Category" button is automatically hidden and replaced with a `Protected Category` status badge when a category or its contents are protected.
  - Added a "Lock Category (Prevent Deletion)" toggle to the Edit Category modal (`#edit-book-modal`).
  - Hardened `save_tree` / `reorder_tree` to preserve category lock attributes and prevent accidental omission of protected categories or documents during menu reordering.

### 🔒 Interactive Document Lock & Unlock in UI
- **Administrative Lock/Unlock Toggle**:
  - Administrators can now lock or unlock documents directly from the user interface via the Edit Details modal (`#edit-chapter-modal`).
  - Added a "Lock Document (Prevent Deletion & Edits)" toggle with contextual status helper text.
  - Locking a document immediately hides content editing (`#btn-edit-markdown`) and document deletion (`#btn-delete-chapter`), displaying the Protected Document badge.
  - Unchecking the toggle cleanly unlocks the document and restores editing and deletion capabilities.
- **Hierarchy & Inheritance Enforcement**:
  - Documents located within a directly locked category inherit the category lock; the document lock toggle is disabled with a notice indicating the lock is inherited from the parent category.
  - Added `Config::isChapterDirectlyProtected()` and `Config::isChapterAncestorProtected()` to differentiate direct document locks from ancestor category inheritance.
- **Demo Mode Safeguards**:
  - In native demo mode (`Config::isDemoMode()`), protected demo documents cannot be unlocked by visitors; the Edit Details button remains hidden or protected against unlocking.

### 🌐 Sidebar Subwiki Navigation & Discovery
- **Configurable Subwiki Sidebar Section**:
  - Added a new administrative toggle in Site Settings (`#settings-modal`): **"Show Subwikis in Left Sidebar Navigation"** (`showSubwikisInSidebar`).
  - Renders a clean, collapsible accordion group in the sidebar navigation displaying all deployed subwikis with their custom titles and document counts (`<N> docs`).
- **Bi-Directional Cross-Wiki Discovery**:
  - Implemented `SubwikiManager::getSidebarSubwikis()` supporting both parent and child wiki contexts.
  - In the parent wiki, lists all deployed child subwikis.
  - In a child subwiki, automatically inspects the parent directory to discover sibling subwikis, generates relative `../slug/` navigation paths, and excludes the active wiki.
- **Visual Integration**:
  - Styled subwiki navigation items with responsive count badges (`.badge-subwiki-count`) and distinct globe icons (`🌐`).

### 🧪 Test Automation
- Added `tests/category_lock_test.php` verifying direct category locks, transitive protection from child documents, multi-level folder cascades, deletion prevention via `delete_node_recursive`, document direct/ancestor protection detection, document locking/unlocking via `find_chapter_and_update`, and API security guards against unlocking protected demo docs or documents in locked categories.
- Added `tests/sidebar_subwikis_test.php` verifying subwiki discovery in parent and child contexts, settings persistence, and sidebar accordion rendering.

## [1.9.7] - OmniShare - 2026-09-10

### 🔗 Full-Screen Secure Sharing & Reader Rights

- **Universal Reader Share Modal Access**:
  - Moved `#share-modal` out of admin-only enclosing blocks in `index.php`, granting all authenticated readers (including users with the `viewer` role) direct access to the secure full-screen sharing modal.
  - Viewers on private portals requiring sign-in can now generate and distribute unguessable reader links (`?share=...`), allowing external recipients without portal accounts to view the document without hitting the sign-in barrier.
- **Admin Access Controls Isolation**:
  - Preserved strict role-based access for administrative share toggles (**Allow Public Sharing** and **Reset Key**), cleanly hiding them from read-only viewers while rendering the copy input and social sharing shortcuts.
- **Preloaded Share URL & Fallback Integrity**:
  - Embedded `data-share-url` directly on the document share button (`#btn-share-chapter`) to immediately populate the share input upon opening the modal without waiting for background API calls.
  - Native clipboard fallback now prefers the secure public share URL (`?share=...`) over standard internal URLs (`window.location.href`).
- **Public Share Key Retrieval**:
  - Updated `api/admin.php?action=get_or_create_share_key` to permit public/guest retrieval of existing share keys when public sharing is enabled (`publicShareable: true`), while requiring authentication to generate new keys or view restricted documents.

### 🌐 Social Share Menus & Open Graph Metadata

- **Open Graph & Twitter Card Integration**:
  - Added dynamic Open Graph (`og:title`, `og:description`, `og:image`, `og:url`, `og:type`, `og:site_name`) and Twitter Card (`twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`) meta tags to `<head>`.
  - Social networks and messaging platforms (𝕏, LinkedIn, Facebook, WhatsApp, Slack, Discord) now generate rich visual card previews when sharing documentation links.
- **Floating Bar Social Dropdown**:
  - Added a dedicated social share dropdown menu (`#share-social-dropdown`) to the Zen reader's floating action bar, providing instant 1-click sharing to 𝕏 (Twitter), LinkedIn, Facebook, WhatsApp, and a direct "Copy Share Link" action.
- **Document-Level & Global Social Metadata**:
  - Added custom **Short Description** and **Social Share Image URL** fields to the document **Edit Details** modal (`edit-chapter-modal`).
  - Added global fallback **Global Social Share Description** and **Global Social Share Image URL** configuration in Site Settings (`settings-modal`), cascading smoothly down to article introductions when custom fields are blank.

### 🛡️ HTML Extension & Environment Fixes

- **HTML Page Sandbox Popup Permission**:
  - Added `allow-popups` and `allow-popups-to-escape-sandbox` to the page-html extension iframe sandbox, permitting external links and references inside custom HTML pages to open reliably in new tabs without console security warnings.
- **Base URL CLI Normalization**:
  - Normalized empty script path handling in `Config::getBaseUrl()` to prevent `/./` artifacts when executing in CLI or automated testing environments.

### 🧪 Test Automation

- Added `tests/share_rights_test.php` covering:
  - Viewer role session authentication (`viewer != admin`).
  - Modal rendering for viewers without exposing admin-only controls.
  - Presence of `data-share-url` attributes on the chapter share button.
  - API share key retrieval and generation across both authenticated viewers and unauthenticated visitors.

---

## [1.9.6] - DocFlow - 2026-09-08

### 🗂️ Document Relocation & Hierarchy Organization

- **Hierarchical Category Selector**:
  - Added `Navigation::getCategoriesHierarchy()` to display parent-child category tree paths with visual nesting (`↳`) in the Edit Details modal and Settings default category selector.
- **Dynamic Category Relocation**:
  - Administrators can now move documents between categories/folders directly in **`⚙️ Edit Details`**.
  - Qwiki automatically relocates the physical file on disk (`relocate_document_file`), cleans up empty directories, resolves filename collisions with incremental numeric suffixes (`-1`, `-2`), and updates tree paths in `qwiki.json`.
- **Drag-and-Drop Disk Relocation**:
  - Dropping documents into different categories in the sidebar navigation automatically relocates physical files on disk to match the destination category directory, returning updated file mappings to sync DOM attributes.
- **Slug Renaming & Collision Safeguards**:
  - Changing a document's slug in the Edit Details modal automatically renames the underlying file on disk while preventing collisions against existing documents, categories, and physical folders.

### 🖼️ Active Editor Media Gallery Integration

- **Toast UI Toolbar Integration**:
  - Added a dedicated **`🖼️`** gallery button to the Markdown editor toolbar and edit actions bar (`#btn-editor-gallery`).
- **Context-Aware Editor Detection**:
  - When the gallery modal is opened while editing an article, an "Article Editor Active" status banner and mode badge (`Markdown` or `HTML`) are displayed.
- **1-Click Direct Insertion**:
  - Added prominent **`✓ Select`** buttons on cards and card thumbnail hover overlays. Clicking Select immediately generates the corresponding Markdown (`![alt](url)`) or HTML (`<img ...>`) tag, inserts it at the cursor position in the active editor, and automatically closes the gallery modal.
- **Preview Modal Insertion**:
  - The full preview modal supports direct insertion into active editors with customizable alt text.

### ⚡ HTML Editor & Asset Enhancements

- **SunEditor Code View Synchronization**:
  - Two-way synchronization automatically synchronizes changes made in raw HTML Code View mode back into the WYSIWYG editor DOM and form submission buffer, eliminating lost edits on save.
- **Quick Save Shortcut**:
  - Added **`Ctrl+S`** / **`Cmd+S`** keyboard shortcut to save HTML documents directly from the modal.
- **WAF / ModSecurity Protection**:
  - HTML content submissions are Base64-encoded to prevent false positives from strict web server firewalls.
- **Relative Asset Normalization**:
  - Sandboxed iframes automatically resolve relative asset URLs (e.g. `uploads/images/...`) against the application base URL.
- **Refined Protected HTML Mode**:
  - Markdown editor recognizes benign line break tags (`<br>`, `<br/>`) without inappropriately triggering strict protected HTML mode.

### 🌐 Dynamic Subwiki Navigation

- **Live Parent Title Resolution**:
  - `SubwikiManager::getParentTitle()` dynamically reads the parent wiki's `qwiki.json` so renames to the parent wiki title immediately reflect on child subwiki sidebar banners without hardcoded stale values.
- **Subwiki Site Settings**:
  - Subwiki administrators can customize the parent title and parent URL directly in Site Settings.

### 🧪 Test Automation

- Added comprehensive test suites:
  - `tests/document_management_test.php`: 43 assertions covering slug renaming, category relocation, numeric clash resolution, node mutation, and drag-and-drop file movement.
  - `tests/category_hierarchy_test.php`: 33 assertions covering nested tree traversal, indentation paths, multi-level folder creation, and hierarchy resolution.
  - `tests/test_gallery_workflow.php`: 17 assertions covering image upload, direct Markdown/HTML insertion, and multi-format usage tracking.
  - `tests/test_html_page_extension.php`: 16 assertions covering base href injection, HTML creation, saving, and error handling.

### 📚 Documentation

- Updated `features.md`, `managing-content.md`, and `site-settings-users.md` across both `demo-data/content/` and `content/`.

---

## [1.9.5] - SubwikiShield - 2026-09-07

### 🌐 Subwiki Management & Collision Protection

- **Bi-Directional Slug Collision Prevention**:
  - Implemented comprehensive bi-directional namespace validation in `SubwikiManager::validateSlug()`.
  * Prevents creating or renaming categories in parent wikis that match physical directories, deployed subwiki folders, or reserved system paths (`api`, `assets`, `content`, `uploads`, `lib`, `tests`, `demo-data`, `wikis`).
  * Prevents deploying subwikis whose slug matches an existing parent category, physical directory, or reserved route.
  * Real-time slug validation endpoint (`api/admin.php?action=check_subwiki_slug`) provides instant live feedback in the admin deployment UI.
- **Single-Level Subwiki Depth Enforcement**:
  - Subwikis are marked with `"isSubwiki": true` in `qwiki.json` and a filesystem `.subwiki` marker.
  - Subwikis cannot deploy nested child wikis (`deploySubwiki` strictly enforces a 1-level limit).
  - Multi-team hierarchies are structured using dashed naming at the parent level (e.g., `engineering-electrical`, `engineering-mechanical`).
  - Child subwikis automatically render a prominent **`← Back to [Parent Title]`** navigation banner in the sidebar.
  - In-app update prompts and deployment controls are cleanly suppressed in subwikis (`managed_by_parent: true`).
- **Parent Subwiki Deployment UI**:
  - Added dedicated **`🌐 Subwikis`** management modal in the admin interface with real-time slug verification, active subwiki listing, direct link shortcuts, and safe de-registration/deletion controls.
- **Automated Cascading Updates**:
  - The parent in-app updater (`install_update`) dynamically excludes all registered subwiki folders during zip extraction to protect child documentation and media uploads.
  - Cascades core engine updates (`index.php`, `lib/`, `assets/`, `api/`) to all child subwikis automatically upon parent update, eliminating the need for individual subwiki update button clicks.
- **Upgrade Migration Engine**:
  - Automated migration during upgrades detects un-registered legacy subwikis, registers them in `qwiki.json`, flattens any deeper nested folders ($\ge 2$ levels) into 1-level dashed subwikis, and normalizes article image paths to clean relative paths (`uploads/images/...`).

### 🧪 Test Automation

- **Subwiki Test Suite** (`tests/subwiki_collision_test.php`):
  - Comprehensive automated tests covering reserved word rejection, path traversal prevention, bi-directional category-vs-subwiki collision checks, dashed slug acceptance, subwiki deployment, single-depth restriction, cascading updates without data loss, and migration image path normalization.

### 📚 Documentation

- Synchronized `features.md` and `site-settings-users.md` across both `content/` and `demo-data/content/`.

---

## [1.9.4] - PrintFix - 2026-09-06

### 🖨️ HTML Document Print & PDF

- **Sandbox Print Permission Restored**: Added `allow-modals` to the `<iframe>` sandbox attribute in the HTML page extension renderer. Without this token, browsers silently block `window.print()` regardless of whether it is called from inside the iframe (`onclick="window.print()"`) or from the parent via `contentWindow.print()`, causing all three print buttons to fail in embedded view with no error feedback.
- **Smart Print Button Deduplication**: When an HTML document is active in embedded view, the redundant outer header/share-bar printer icon (`#btn-print-chapter`) is now automatically hidden by the HTML extension script. Only the HTML viewer toolbar's **`🖨️ Print / Save as PDF`** button remains visible — the appropriate entry point for iframe-scoped printing.
- **Context-Aware In-Document Action Bar**: HTML documents that embed their own `Document Options` / `Print / Save as PDF` action bar now detect whether they are running inside a Qwiki iframe (`window.self !== window.top`) and hide the bar automatically when embedded. The bar continues to display normally when the document is opened in a full browser tab or via a share link.

### 📚 Documentation

- **Features & System Capabilities** (`getting-started/features.md`): Added **Smart Print / Save as PDF** bullet under HTML Documents describing the unified, context-aware print behavior across all three access modes.
- **Managing Content** (`user-guide/managing-content.md`): Expanded the **Print & Social Share** section to distinguish Markdown / standard document printing from HTML document printing and updated the Share description to reflect the modal-based share workflow.
- Both `content/` and `demo-data/content/` documentation copies synchronized.

---

## [1.9.3] - FormatGuard - 2026-09-05


### 🛡️ Markdown & HTML Content Preservation
- **Protected Markdown + HTML Mode**:
  - Automatically analyzes Markdown documents (`containsHtmlMarkup`) for raw HTML blocks, inline styling (`style="..."`), CSS classes (`class="..."`), custom grids, flexboxes, and status badges before editor initialization.
  - Locks Toast UI Editor into Markdown mode (`initialEditType: 'markdown'`) with synchronized vertical live preview (`previewStyle: 'vertical'`) and hides mode switcher tabs (`hideModeSwitch: true`).
  - Completely eliminates silent HTML tag stripping, attribute erasure, and paragraph flattening caused by Toast UI's ProseMirror WYSIWYG schema.
  - Reads directly from the raw CodeMirror buffer on save, ensuring 100% byte-for-byte preservation of complex HTML layouts and custom styling without modification.
- **Visual Protection Badge**:
  - Displays a dedicated status banner above the editor (`⚡ Markdown + HTML Mode (Live Preview) [PROTECTED HTML]`) clearly informing authors that formatting and styles are protected.
- **Mode Switch Guarding**:
  - Intercepts internal mode switch events (`needChangeMode`) for pure Markdown documents, preventing accidental transitions to WYSIWYG mode if raw HTML markup or inline styling has been added.
- **Clean State Reset**:
  - Canceling an inline edit session now properly clears notice banners and reverts editor state to the original document content.

### 🧪 Test Automation
- **HTML Preservation Test Suite**:
  - Added comprehensive automated test coverage (`tests/test_markdown_html_preservation.js`) verifying markup detection across pure Markdown, code blocks containing HTML, inline tags, and real-world complex landing page fixtures.

---

## [1.9.2] - DemoShield - 2026-09-05

### 🛡️ Document Protection & Access Control
- **Universal Read-Only Document Protection**:
  - Documents configured with `"readOnly": true` or `"editable": false` in `qwiki.json` are globally locked against inline editing, metadata modifications (`edit_chapter`), deletion (`delete_chapter`), and HTML page saves (`page-html`).
  - Added `Config::isChapterProtected($slugOrFile)` to provide centralized recursive document protection checks across all core handlers and extensions.
  - Displays a visual status badge (`Protected Document` in standard mode, `Protected Demo Page` in demo mode) on locked articles.
  - Automatically suppresses inline editing toolbars, Toast UI editors, and drag-and-drop eviction for protected articles.

### 🔄 Native Demo Engine & Updater Safeguards
- **First-Class Demo Mode Activation**:
  - Added `Config::isDemoMode()` supporting three activation methods: `"demoMode": true` in `qwiki.json`, environment variable `QWIKI_DEMO_MODE=1`, or a `.demo` marker file in the installation root.
  - **Auto-Updater Protection**: Automatically disables GitHub update checks (`has_update: false`) and blocks `install_update` when running in demo mode, protecting public sandboxes from being overwritten by visitors.
  - **Scoped Demo Controls**: Displays the `Reload Demo Package` button in Admin Settings exclusively when demo mode is active. Standard installations remain clean.
- **DemoManager & Reload Engine**:
  - Integrated `Qwiki\Core\DemoManager` and `demo-reload.php` directly into core to provide clean zero-divergence branch alignment between production releases and demo sandboxes.
  - Safely resets visitor content additions, uploads, active locks, and user stores via CLI cron (`php demo-reload.php --quiet`) or authenticated web requests.

### 📚 Documentation & Guidelines
- **System Features**: Added Document Protection & Sandbox Safeguards documentation to `getting-started/features.md`.
- **Release Guidelines**: Formalized the downstream synchronization workflow (`main` ➔ `demo`) and release origin rules in `AGENTS.md`.

---

## [1.9.1] - SecureScope - 2026-09-05

### 🔒 Security & Authentication
- **Multi-Instance Session Isolation**:
  - Automatically derives instance-unique session names (`QWIKISESSID_<hash>`) based on a SHA-256 fingerprint of each installation's canonical base directory.
  - Prevents session collision and privilege leakage across multiple Qwiki instances or nested subfolder installations hosted on the same domain or server.
  - Scopes session cookie paths directly to the application's URL subfolder rather than global `/`.
  - Enforces instance ownership validation (`$_SESSION['qwiki_instance']`) to immediately reject foreign sessions stored on shared temporary storage.
  - Implements clean session and cookie expiration on logout.

### 🛡️ Reliability & Hardening
- **Toast UI Editor Fallback**: Implemented robust initialization fallback handling to ensure graceful editor degradation when external assets or complex raw HTML blocks are encountered.
- **Protocol Enforcement & Content Guidelines**: Integrated markdown authoring guidelines in project specifications (`AGENTS.md`) to maintain clean parsing and ensure editor stability.

---

## [1.9.0] - OmniNav - 2026-09-04

### 🌐 Added
- **Navigation Hyperlinks**: Custom hyperlinks can now be added directly to the sidebar navigation tree at any level—either at the root alongside category headers or nested inside any category folder.
- **Smart Target Routing**:
  - Automatically routes external domain URLs to open in a new tab with secure `target="_blank" rel="noopener noreferrer"`.
  - Automatically routes same-domain and relative URLs to open in the same tab (`target="_self"`).
  - Visual status badges indicate external (`badge-link`) vs. internal (`badge-internal-link`) destinations.
- **Top-Level Pixel-Perfect Alignment**:
  - Root-level hyperlinks match category headers in padding (`0.65rem 1.25rem`), typography (uppercase, bold, letter-spacing), drag handles (`⣿`), and right-aligned actions.
  - Automatically prefixes a `🔗` emoji matching category folder icons (`📂`) with identical character spacing and baseline alignment.
- **Unified Drag & Drop Engine**:
  - Reorder hyperlinks anywhere in the menu hierarchy with instant backend sync to `qwiki.json`.
  - Live DOM updating dynamically toggles top-level vs. nested styling and icons without requiring a page reload.
- **Search Integration**: Real-time filtering and server-side search seamlessly indexes hyperlink titles and descriptions.
- **Protocol Security Validation**: Blocks potentially unsafe or malicious protocol schemes (`javascript:`, `data:`, `vbscript:`).
- **Admin Management Modals**: Dedicated forms in the Add Item modal (`tab-link`) and Edit Hyperlink modal (`#edit-link-modal`) with full edit and delete support.

### 📚 Documentation & Demo Data
- **User Guide Updates**: Documented hyperlink creation, smart tab routing, and management in `content/user-guide/managing-content.md` and `demo-data/content/user-guide/managing-content.md`.
- **Features Guide**: Documented Navigation Hyperlinks in `content/getting-started/features.md` and `demo-data/content/getting-started/features.md`.
- **Default Config Alignment**: Added official GitHub repository hyperlink sample to `demo-data/qwiki-default.json`.

---

## [1.8.1] - 2026-09-04

### 📚 Documentation & Demo Data
- **Demo Data Alignment**: Fully synchronized `demo-data/content/getting-started/` and `demo-data/content/user-guide/` with up-to-date documentation on video embedding, advisory soft locks, image gallery extension, chart generator, and PDF/share tools.
- **Default Configuration Cleanup**: Updated `demo-data/qwiki-default.json` with standardized category types, syndication settings, and cleaned default structure excluding test pages.
- **Agent Guidelines**: Added `AGENTS.md` project rules to enforce automatic alignment of demo data, navigation configs, and release procedures for all future releases.

---

## [1.8.0] - OmniPlay - 2026-09-04

### 🎬 Added
- **Auto-Embedded Playable Videos**: Automatically transforms standalone video URLs and video Markdown links into responsive, interactive video players inside the Markdown viewer.
- **YouTube Embed Engine**: Supports standard watch links (`youtube.com/watch?v=...`), short links (`youtu.be/...`), YouTube Shorts (`youtube.com/shorts/...`), and start timestamps (`?t=1m30s`, `?start=90`). Uses privacy-friendly `youtube-nocookie.com`.
- **Vimeo Player Integration**: Supports standard and player links (`vimeo.com/...`, `player.vimeo.com/video/...`) with automatic Do Not Track (`dnt=1`) privacy protection.
- **Loom Screencast Integration**: Renders clean, full-featured Loom video players directly from share links (`loom.com/share/...`) and embed links (`loom.com/embed/...`).
- **Native HTML5 Video Player**: Seamlessly plays direct video files (`.mp4`, `.webm`, `.ogg`, `.mov`) hosted locally in `uploads/` or externally, equipped with native playback controls, preload metadata, and fallback download links.
- **Custom Video Captions**: Automatically extracts subtitles from Markdown link text (e.g. `[Tutorial Overview](https://...)`) or image syntax (`![Demo Clip](video.mp4)`) and renders an elegant italicized caption beneath the player.
- **Smart Inline Link Preservation**: Intelligent detection ensures that links inside sentences remain standard clickable hyperlinks and are never erroneously converted into embeds.
- **Responsive Video Styling**: Added `.qwiki-video-wrapper`, `.qwiki-video-container` (fluid 16:9 aspect ratio, rounded corners, soft box shadow), `.qwiki-video-player`, and `.qwiki-video-caption` matching dark and light themes.

---

## [1.7.0] - SafeSync - 2026-09-02

### 🔒 Added
- **Advisory Soft Lock System**: Prevents simultaneous editing and silent overwrite collisions when a document is edited by different users or in multiple browser tabs.
- **Ephemeral Tab UUID Tracking**: Distinctly identifies browser tabs using `sessionStorage`, allowing the system to differentiate multiple tabs opened by the same logged-in user.
- **Zero-Latency Cross-Tab Sync**: Integrated the browser `BroadcastChannel` API (`qwiki_doc_locks`) to immediately notify sibling tabs in the same browser when a document enters or exits edit mode without waiting for network polling.
- **Lease-Based Expiration & Heartbeats**: Configured a 60-second lease TTL with a 20-second active heartbeat ping, ensuring locks auto-expire cleanly if a tab or connection drops.
- **Unload Beacon Teardown**: Uses `navigator.sendBeacon` and `pagehide` listeners to promptly release locks upon navigating away or closing the tab.
- **Non-Blocking PHP Sessions**: Heartbeat endpoints immediately invoke `session_write_close()`, ensuring background lease renewal never blocks concurrent page requests from the user.
- **Tab Inactivity & Visibility Recovery**: Pauses renewal after 15 minutes of idle time and immediately resynchronizes lock status upon `visibilitychange` or window focus.
- **Atomic Save Protection**: Server-side write validation rejects save attempts (`LOCKED_BY_OTHER`) if another session holds or broke the lock.
- **Automatic Draft Safety Net**: Automatically mirrors typed changes to `localStorage` (`qwiki_draft_{file}`) to ensure no content is lost if an eviction occurs.
- **Conflict Modal & Takeover Action**: Displays clear dialogs detailing who currently holds the lock, with an option for authorized users to break/take over the lock.
- **Multi-Editor Support**: Full locking parity across both the inline Toast UI Markdown editor and the SunEditor HTML page extension (`assets/extensions/page-html`).

---

## [1.6.3] - 2026-08-29

### 🛡️ Fixed
- **ModSecurity / WAF Bypass**: Encoded HTML editor payloads as Base64 during save operations to prevent `403 Forbidden` errors triggered by strict web application firewalls on production Apache and Nginx servers.

---

## [1.6.2] - 2026-08-29

### 🧩 Added
- **Image Gallery Extension**: Dedicated media and image gallery viewer for uploaded documents and assets.

### 🐛 Fixed
- **HTML Editor & Visual Chart Bugfixes**: Fixed editor toggle persistence, script tag stripping prevention, and visual chart rendering edge cases.

---

## [1.6.1] - 2026-08-29

### 🐛 Fixed
- **Auto-Updater Filter**: Removed `assets/extensions` from the auto-updater exclude list to ensure core extension assets update reliably.

---

## [1.6.0] - 2026-08-29

### 🌟 Added
- **Self-Contained Extension Architecture**: Modular system supporting custom page types and admin utility tools under `assets/extensions/`.
- **WYSIWYG HTML Page Editor**: Built-in SunEditor integration with visual/code toggles for `.html` documents.
- **Agentic Visual & Chart Generator**: Integrated tool generating vector charts, diagrams, and process flows.

---

## [1.5.0] - 2026-08-26

### 🌟 Added
- **Print & PDF Export**: Native print-friendly stylesheet and PDF export trigger.
- **Social Sharing**: 1-click share menu supporting native Web Share API and clipboard copy.
- **Modern SVG Icons**: Replaced legacy text buttons with clean, responsive SVG icons across article action bars.

---

## [1.4.0] - 2026-08-26

### 🌟 Added
- **1-Click Auto Updates**: One-click core upgrade workflow checking against GitHub releases.
- **Dynamic Release Notes**: Render GitHub Markdown release notes directly within the admin update modal.
