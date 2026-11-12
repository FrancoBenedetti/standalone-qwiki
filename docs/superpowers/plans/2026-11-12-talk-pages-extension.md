# Wikipedia-Style Talk Pages Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a zero-database, Wikipedia-style Talk Pages extension (`tool-talk`) that provides a companion editorial discussion document for any chapter, accessible via an interactive `[ Document | Discussion ]` tab switcher with topic-based discussions, quick replies, and full raw Markdown refactoring.

**Architecture:** A native Standalone Qwiki utility extension in `assets/extensions/tool-talk/` managed by `ExtensionManager`. Discussions persist in flat Markdown files under `data/talk/{book}__{slug}.talk.md` with atomic `flock` operations. The front-end mounts a discreet header tab switcher `[ Document | Discussion (N) ]` above `#content-body`, supporting structured topic additions, section-level quick replies, and a raw Markdown editing mode for authorized editors/admins.

**Tech Stack:** PHP 8+, JavaScript (ES6+ Vanilla), CSS3 (Qwiki design system custom properties), Parsedown / QwikiParsedown (safe mode), flat-file Markdown storage with `flock`.

**Spec:** Synthesized from design discussions in `conversation:"Chapter Comment System Architecture And Implementation"`, `conversation:"Wiki Extension Management Implications"`, and Wikipedia `Talk:` namespace conventions:
- Co-exists cleanly with or acts mutually exclusive to `tool-comments`.
- Flat-file Markdown storage in `data/talk/` protected by `.htaccess`.
- First-class wiki document: topics organized under `## Subject` headers, timestamped and signed with author badges.
- Dual interaction modes: Streamlined topic/reply forms for quick contributions, plus a raw Markdown editor for editorial refactoring and archiving.
- **Opt-in Cascading Enablement (Disabled by Default):**
  - **Category Level:** Configurable per category with default (if not set) being **Disabled** (`false`). When an admin sets it to active for a category, Talk pages become available for all documents in that category.
  - **Document / Chapter Level:** Configurable per document with default being **"Inherit from Category"** (`"inherit"` / `null`). The document can explicitly override this with **"Enabled"** or **"Disabled"**.
  - **Effective Result:** An unconfigured wiki has the Talk function completely inactive. When enabled for a category, all documents in that category gain a Talk page unless individually disabled for a specific document.
- LLM API support via `api/llm.php?mode=talk` for context-aware summaries.

## Global Constraints

- Zero database dependencies: flat-file Markdown storage only.
- Strict directory isolation: direct web access to `data/talk/` must be denied via `.htaccess`.
- Safe Markdown rendering: strip `<script>`, `<iframe>`, `onclick`, and `javascript:` URLs; never trust raw client HTML.
- UI styling must strictly use existing Qwiki CSS custom properties (`--bg-primary`, `--text-primary`, `--border-color`, `--accent-color`, etc.) to support light and dark themes.
- All HTML opening and closing tags in raw HTML blocks must be flush-left with 0 leading spaces to maintain Toast UI WYSIWYG editor compatibility.
- Never break or conflict with `tool-comments`: both extensions can run simultaneously or independently.

## Review Focus

1. **Unauthenticated Contribution Attempts:** An unauthenticated visitor calling `ext_talk_add_topic`, `ext_talk_reply`, or `ext_talk_save_raw` must receive `401 Unauthorized`.
2. **XSS Injection via Markdown or Signatures:** Malicious payloads like `<script>alert(1)</script>` or `[click](javascript:...)` in topic headers or messages must be stripped and rendered safely.
3. **Edit Privilege Escalation on Raw Save:** Only authenticated users with editorial or administrative permissions can invoke `ext_talk_save_raw`; unauthorized viewers must receive `403 Forbidden`.
4. **Concurrent Append Integrity:** Rapid simultaneous topic additions or replies must use `flock(..., LOCK_EX)` to prevent race conditions or document truncation.
5. **Default-Disabled Cascading Inheritance:** An unconfigured wiki or category without `allowTalk` must strictly evaluate to `false`. When a category enables Talk, chapters with `inherit` (or unset) must evaluate to `true`, while chapters with explicit `disabled` must evaluate to `false`. Chapters with explicit `enabled` must evaluate to `true` even if their parent category is disabled.

---

### Task 1: Core Talk Backend & Flat-File Storage Engine (`TalkService.php`)

**Files:**
- Create: `assets/extensions/tool-talk/TalkService.php`
- Test: `tests/talk_test.php`

**Interfaces:**
- Consumes: `Config::getBaseDir()`, `Auth::getCurrentUser()`
- Produces:
  - `TalkService::getStorageDir(): string`
  - `TalkService::getFilePath(string $bookId, string $slug): string`
  - `TalkService::isTalkEnabled(string $bookId, string $slug, array $config): bool`
  - `TalkService::getRawContent(string $bookId, string $slug): string`
  - `TalkService::saveRawContent(string $bookId, string $slug, string $markdown): bool`
  - `TalkService::getTopics(string $bookId, string $slug): array`
  - `TalkService::addTopic(string $bookId, string $slug, string $username, string $role, string $title, string $message): array`
  - `TalkService::addReply(string $bookId, string $slug, string $username, string $role, int $topicIndex, string $message): array`
  - `TalkService::renderSafeMarkdown(string $markdown): string`

- [ ] **Step 1: Write the failing unit tests in `tests/talk_test.php`**

Create `tests/talk_test.php` covering:
- Initialization of `data/talk/` and `.htaccess` file creation.
- Opt-in cascading enablement truth table:
  - Unset/unconfigured category and chapter -> `false` (default disabled).
  - Category `allowTalk: false`, chapter unset -> `false`.
  - Category `allowTalk: true`, chapter unset (`null` / `"inherit"`) -> `true`.
  - Category `allowTalk: true`, chapter `allowTalk: false` (`"disabled"`) -> `false`.
  - Category `allowTalk: false`, chapter `allowTalk: true` (`"enabled"`) -> `true`.
- Reading empty talk page (returns empty string / 0 topics).
- Adding a new topic with title, body, author, and timestamp.
- Adding an indented reply to an existing topic section.
- Extracting parsed topics array with heading titles and reply counts.
- Saving raw Markdown content and reading it back.
- Sanitizing XSS payloads (`<script>`, inline handlers, `javascript:` URLs).

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/talk_test.php`  
Expected: FAIL (class `TalkService` does not exist).

- [ ] **Step 3: Implement `TalkService.php`**

In `assets/extensions/tool-talk/TalkService.php`:
- Implement `getStorageDir()`: creates `data/talk` if missing, writes `.htaccess` with `Require all denied`.
- Implement `getFilePath($bookId, $slug)`: computes sanitized filename `data/talk/{safeBook}__{safeSlug}.talk.md`.
- Implement `isTalkEnabled($bookId, $slug, $config)`:
  - Locates the category and chapter matching `$bookId` and `$slug`.
  - Resolves chapter-level setting: if `'enabled'` or `true`, returns `true`; if `'disabled'` or `false`, returns `false`.
  - If chapter is `'inherit'`, `null`, or unset: resolves category-level setting: if `!empty($category['allowTalk'])`, returns `true`; otherwise returns `false` (default disabled).
- Implement `getRawContent($bookId, $slug)`: reads file content safely with `flock(..., LOCK_SH)`.
- Implement `saveRawContent($bookId, $slug, $markdown)`: writes file atomically with `flock(..., LOCK_EX)` and temporary file rename.
- Implement `getTopics($bookId, $slug)`: parses `## ` markdown headings to extract topic objects (index, title, rawMarkdown, html).
- Implement `addTopic($bookId, $slug, $username, $role, $title, $message)`: appends formatted markdown block `## {title}\n\n**@{username}** ({role}) *({date})*:\n{message}\n\n`.
- Implement `addReply($bookId, $slug, $username, $role, $topicIndex, $message)`: inserts an indented reply block under the targeted topic section before the next `## ` heading.
- Implement `renderSafeMarkdown($markdown)`: wraps `Parsedown` with XSS attribute and tag stripping.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/talk_test.php`  
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add assets/extensions/tool-talk/TalkService.php tests/talk_test.php
git commit -m "feat(talk): implement core TalkService storage engine and topic parser"
```

---

### Task 2: Extension Manifest & Action Handler (`manifest.json` & `handler.php`)

**Files:**
- Create: `assets/extensions/tool-talk/manifest.json`
- Create: `assets/extensions/tool-talk/handler.php`
- Modify: `tests/talk_test.php`

**Interfaces:**
- Consumes: `TalkService`, `Auth`, `AuditLogger`, `ExtensionManager`
- Produces: API actions `ext_talk_get`, `ext_talk_add_topic`, `ext_talk_reply`, `ext_talk_save_raw`

- [ ] **Step 1: Write failing tests in `tests/talk_test.php` for extension discovery and action dispatching**

Add tests covering:
- `ExtensionManager::getInstance()->getUtility('talk')` discovery.
- Unauthenticated requests to `ext_talk_add_topic` and `ext_talk_save_raw` returning 401 error.
- Non-admin viewer attempting `ext_talk_save_raw` returning 403 error.
- Authenticated user adding topic and reply via `$extManager->handleAction(...)`.
- Audit logging verification on topic addition and raw markdown save.
- Extension deactivation test: when disabled in `qwiki.json`, action returns 403 Forbidden.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/talk_test.php`  
Expected: FAIL (`manifest.json` and `handler.php` not found).

- [ ] **Step 3: Create `manifest.json`**

In `assets/extensions/tool-talk/manifest.json`:
```json
{
  "id": "talk",
  "type": "utility",
  "adminOnly": false,
  "title": "Talk Pages",
  "icon": "🗣️",
  "placement": "chapter_header",
  "description": "Wikipedia-style editorial discussion and debate pages for chapters.",
  "handler": "handler.php",
  "actions": [
    "ext_talk_get",
    "ext_talk_add_topic",
    "ext_talk_reply",
    "ext_talk_save_raw"
  ],
  "styles": ["style.css"],
  "scripts": ["script.js"]
}
```

- [ ] **Step 4: Implement `handler.php`**

In `assets/extensions/tool-talk/handler.php`:
- Check extension enablement via `ExtensionManager::getInstance()->isExtensionEnabled('talk')`. If false, respond 403.
- Verify session via `Auth::startSession()`.
- Handle `ext_talk_get`:
  - Validate `book` and `slug`.
  - Return `{ success: true, talkEnabled, topicsCount, topics, raw, canEdit, canPost, currentUser }`.
- Handle `ext_talk_add_topic`:
  - Require `Auth::isLoggedIn()`.
  - Validate `title` (3-120 chars) and `message` (1-10000 chars).
  - Call `TalkService::addTopic()`.
  - Log audit event `TALK_TOPIC_ADD`.
  - Return newly created topic and updated count.
- Handle `ext_talk_reply`:
  - Require `Auth::isLoggedIn()`.
  - Validate `topicIndex` and `message` (1-5000 chars).
  - Call `TalkService::addReply()`.
  - Log audit event `TALK_REPLY_ADD`.
  - Return updated topic.
- Handle `ext_talk_save_raw`:
  - Require `Auth::isLoggedIn()` and (`Auth::isAdmin()` or editor role).
  - Validate `markdown`.
  - Call `TalkService::saveRawContent()`.
  - Log audit event `TALK_RAW_SAVE`.
  - Return success.

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/talk_test.php`  
Expected: PASS

- [ ] **Step 6: Commit changes**

```bash
git add assets/extensions/tool-talk/manifest.json assets/extensions/tool-talk/handler.php tests/talk_test.php
git commit -m "feat(talk): add extension manifest and API action handler"
```

---

### Task 3: Category & Chapter Settings in Modals and Admin API

**Files:**
- Modify: `templates/modals/create_category.php`
- Modify: `templates/modals/edit_category.php`
- Modify: `templates/modals/create_chapter.php`
- Modify: `templates/modals/edit_chapter.php`
- Modify: `api/admin.php:120-220`
- Modify: `assets/js/app.js`
- Test: `tests/talk_test.php`

**Interfaces:**
- Consumes: Category and Chapter metadata forms
- Produces: Persisted `allowTalk` configuration in `qwiki.json` books hierarchy

- [ ] **Step 1: Write failing tests in `tests/talk_test.php` for `allowTalk` persistence**

Add tests covering:
- Creating category with `allowTalk: true` via `api/admin.php?action=create_category`.
- Updating chapter with `allowTalk: "disabled"` or `allowTalk: "enabled"` via `api/admin.php?action=edit_chapter`.
- Resolving `TalkService::isTalkEnabled()` across various inheritance permutations.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/talk_test.php`  
Expected: FAIL (`allowTalk` not captured in `api/admin.php`).

- [ ] **Step 3: Update Category Modals (`create_category.php`, `edit_category.php`)**

Add toggle checkbox (unchecked by default so Talk is disabled unless actively enabled):
```html
<div class="form-group" style="margin-top: 1rem;">
    <label class="custom-control custom-checkbox" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
        <input type="checkbox" id="create-category-allow-talk" name="allowTalk" value="1">
        <span>Enable Discussion & Talk Pages for chapters in this category (Disabled by default)</span>
    </label>
</div>
```
In `edit_category.php`, wire the checkbox id `#edit-category-allow-talk` to reflect the saved category state (unchecked if missing/false, checked if true).

- [ ] **Step 4: Update Chapter Modals (`create_chapter.php`, `edit_chapter.php`)**

Add dropdown selector for chapter override defaulting to `inherit`:
```html
<div class="form-group">
    <label for="create-chapter-allow-talk">Discussion & Talk Page</label>
    <select id="create-chapter-allow-talk" name="allowTalk" class="form-control">
        <option value="inherit" selected>Inherit from category (Default)</option>
        <option value="enabled">Enabled (Always show Talk tab)</option>
        <option value="disabled">Disabled (Hide Talk tab)</option>
    </select>
</div>
```
In `edit_chapter.php`, use `#edit-chapter-allow-talk` and populate with the chapter's configured override (`inherit`, `enabled`, or `disabled`).

- [ ] **Step 5: Update `api/admin.php` and `assets/js/app.js`**

- In `api/admin.php`:
  - In `create_category` and `update_category`: extract `allowTalk` as a boolean (`!empty($_POST['allowTalk'])`). If not checked or omitted, store `false`.
  - In `add_chapter` and `edit_chapter`: extract `allowTalk` (`'inherit'` or empty -> `null`, `'enabled'` -> `true`, `'disabled'` -> `false`).
- In `assets/js/app.js`:
  - Reset `#create-category-allow-talk` to unchecked when opening create category modal.
  - Populate `#edit-category-allow-talk` (checked if category has `allowTalk: true`, unchecked otherwise) when opening edit category modal.
  - Reset `#create-chapter-allow-talk` to `'inherit'` when opening create chapter modal.
  - Populate `#edit-chapter-allow-talk` with `chapter.allowTalk ? 'enabled' : (chapter.allowTalk === false ? 'disabled' : 'inherit')` when opening edit chapter modal.

- [ ] **Step 6: Run test to verify it passes**

Run: `php tests/talk_test.php`  
Expected: PASS

- [ ] **Step 7: Commit changes**

```bash
git add templates/modals/ api/admin.php assets/js/app.js tests/talk_test.php
git commit -m "feat(talk): add category and chapter talk page toggles to admin modals"
```

---

### Task 4: AI Agent / LLM Integration (`LlmAccess.php` & `api/llm.php`)

**Files:**
- Modify: `lib/Core/LlmAccess.php:60-150`
- Modify: `api/llm.php:30-80`
- Test: `tests/talk_test.php`

**Interfaces:**
- Consumes: Scoped LLM tokens (`feedAccessToken` or AI read tokens)
- Produces: `mode=talk` and `mode=doc&include_talk=1` in `api/llm.php`

- [ ] **Step 1: Write failing tests in `tests/talk_test.php` for LLM talk page endpoints**

Add tests covering:
- Calling `api/llm.php?mode=talk&book=default&slug=test` with valid token returns talk topics in markdown and structured JSON.
- Calling `api/llm.php?mode=doc&slug=test&include_talk=1` includes discussion section in document context.
- Unauthenticated or invalid token requests receive 401 Unauthorized.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/talk_test.php`  
Expected: FAIL (`mode=talk` not recognized).

- [ ] **Step 3: Implement LLM talk retrieval in `LlmAccess.php` and `api/llm.php`**

- In `lib/Core/LlmAccess.php`:
  - Add method `getTalkPageSummary(string $bookId, string $slug): ?array` returning structured topics, participant count, and raw discussion text.
  - Update `getDocumentContext()`: when `$options['include_talk']` is true, append discussion topics summary under `## Editorial Discussion & Talk Page`.
- In `api/llm.php`:
  - Handle `mode === 'talk'`: validate token, return JSON with talk summary and topics.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/talk_test.php`  
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add lib/Core/LlmAccess.php api/llm.php tests/talk_test.php
git commit -m "feat(talk): expose talk page discussions to scoped LLM tokens"
```

---

### Task 5: Frontend UI Component & Tab Switcher (`style.css` & `script.js`)

**Files:**
- Create: `assets/extensions/tool-talk/style.css`
- Create: `assets/extensions/tool-talk/script.js`
- Test: `tests/test_talk_ui.js`

**Interfaces:**
- Consumes: DOM `#content-body`, `.content-header`, API `ext_talk_*`
- Produces: Interactive top tabs `[ 📄 Document | 💬 Talk (N) ]`, discussion thread renderer, topic form, reply form, and raw markdown editor.

- [ ] **Step 1: Write `style.css`**

In `assets/extensions/tool-talk/style.css`:
- Design `.qwiki-doc-tabs`: tab bar placed neatly at the top of the content area (`.doc-tab-btn`, `.doc-tab-active`, `.doc-tab-badge`).
- Design `.talk-container`: container for discussion view (hidden by default until Talk tab selected).
- Design `.talk-toolbar`: "+ Add Topic", "Edit Raw Talk Page", "Refresh".
- Design `.talk-topic-card`: card styling for each `## Topic` section with collapsible replies and topic action buttons.
- Design `.talk-author-meta`: avatar initials, username, role badges (`.badge-admin`, `.badge-viewer`), timestamp.
- Design `.talk-reply-card`: indented response card with quote indicators.
- Design `.talk-form-card`: clean inline form for submitting new topic or reply with live markdown preview.
- Design `.talk-raw-editor`: full-width textarea with monospace styling and save/cancel buttons for raw Markdown mode.
- Strict use of theme variables (`--bg-primary`, `--bg-secondary`, `--border-color`, `--text-primary`, `--text-muted`, `--accent-color`).

- [ ] **Step 2: Write `script.js`**

In `assets/extensions/tool-talk/script.js`:
- Detect active chapter details (`slug`, `book` from DOM).
- Call `api/admin.php?action=ext_talk_get&book={book}&slug={slug}`.
- If `talkEnabled`:
  - Inject the `.qwiki-doc-tabs` navigation bar right before `#content-body` (or inside `.content-header`).
  - Render Tab 1: `📄 Document` (active by default).
  - Render Tab 2: `💬 Discussion (N)` with topic count badge.
  - Create `.talk-container` alongside `#content-body`.
  - Wire tab click handlers to toggle visibility between `#content-body` and `.talk-container` smoothly.
  - Inside `.talk-container`:
    - If topics exist: render topics and nested replies with "Reply" buttons.
    - If empty: show welcoming empty state with "+ Start First Topic".
    - Wire "+ Add Topic" button to open topic submission form.
    - Wire "Reply" button to open contextual reply box under targeted topic.
    - If user has edit permissions: wire "Edit Raw Markdown" button to toggle full raw textarea editor.
    - Handle AJAX submissions for `ext_talk_add_topic`, `ext_talk_reply`, and `ext_talk_save_raw` with toast notifications and UI refresh.

- [ ] **Step 3: Write DOM verification test in `tests/test_talk_ui.js`**

Create `tests/test_talk_ui.js` testing:
- Script executes cleanly without syntax errors.
- Tab bar DOM structure conforms to accessibility standards.
- CSS variables match Qwiki design tokens.

- [ ] **Step 4: Run test to verify it passes**

Run: `node tests/test_talk_ui.js`  
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add assets/extensions/tool-talk/style.css assets/extensions/tool-talk/script.js tests/test_talk_ui.js
git commit -m "feat(talk): build interactive frontend tab switcher and discussion UI"
```

---

### Task 6: Documentation, Demo Sync, & Release Verification

**Files:**
- Modify: `content/getting-started/features.md`
- Modify: `demo-data/content/getting-started/features.md`
- Modify: `CHANGELOG.md`
- Test: Full test suite

- [ ] **Step 1: Update feature documentation**

In `content/getting-started/features.md` and `demo-data/content/getting-started/features.md`:
- Document the Talk Pages extension:
  - Wikipedia-style editorial discussion tabs.
  - Zero-database flat-file Markdown architecture in `data/talk/`.
  - How Talk Pages differ from and complement Chapter Comments (`tool-comments`).
  - How admins can enable or disable Talk Pages globally or per-wiki in Wiki Settings > Extensions.
  - How category and chapter level inheritance works.

- [ ] **Step 2: Update `CHANGELOG.md`**

Add entry under Unreleased / Next Version detailing:
- Added Wikipedia-style Talk Pages extension (`tool-talk`).
- Flat-file Markdown storage with concurrency protection.
- Interactive header tab switcher `[ Document | Discussion ]`.
- LLM API integration for discussion context retrieval.

- [ ] **Step 3: Run full verification test suite**

Run:
```bash
php tests/talk_test.php && php tests/comments_test.php && php tests/test_extension_management.php && node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php
```
Expected: All tests PASS with 0 failures.

- [ ] **Step 4: Commit changes**

```bash
git add content/ demo-data/ CHANGELOG.md
git commit -m "docs(talk): document talk pages extension and verify test suites"
```
