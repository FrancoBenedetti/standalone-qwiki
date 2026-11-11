# Chapter & Category Comments Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a zero-database, privacy-respecting comments extension for Standalone Qwiki allowing authenticated users to comment on individual chapters or categories with 1-level threading, restricted Markdown formatting, and view/hide controls.

**Architecture:** A native Standalone Qwiki utility extension in `assets/extensions/tool-comments/` integrating with `ExtensionManager` and `Auth`. It persists comments in isolated JSON flat files under `data/comments/` with atomic `flock` writes. Restricted Markdown is parsed and sanitized against XSS on write/read. Front-end assets mount a collapsible, theme-aware commenting interface below `#content-body`.

**Tech Stack:** PHP 8+, JavaScript (ES6+ Vanilla), CSS3 (Qwiki design system variables), Parsedown (sanitized mode), JSON flat-file storage.

**Spec:** Documented in conversation above (architectural recommendations approved: 1-level threading, restricted Markdown, relative timestamps, author/admin moderation, client-side hide/show toggle, and cascading category/chapter inheritance).

## Global Constraints

- Zero database dependencies: flat-file JSON storage only.
- Strict session/identity verification via `Auth::getCurrentUser()`, `Auth::isLoggedIn()`, and `Auth::isAdmin()`.
- Directory isolation: direct web access to `data/comments/` must be denied via `.htaccess`.
- Safe Markdown rendering: strip `<script>`, `<iframe>`, `onclick`, and `javascript:` URLs; never trust raw client HTML.
- UI styling must strictly use existing Qwiki CSS custom properties (`--bg-primary`, `--text-primary`, `--border-color`, `--accent-color`, etc.) to support light and dark themes.

## Review Focus

1. **Unauthenticated access attempts:** An unauthenticated visitor calling `ext_comments_get` or `ext_comments_add` must receive `401 Unauthorized`.
2. **XSS injection via Markdown or author field:** Malicious payloads like `<script>alert(1)</script>` or `[click](javascript:alert(1))` in comment content must be sanitized and rendered safely.
3. **Privilege escalation on edit/delete:** A `viewer` user must not be able to edit or delete another user's comment; only the original author or an `admin` can do so.
4. **Tombstoning integrity:** Deleting a parent comment that has child replies must not orphan the replies; the parent content must be replaced with `[Comment deleted]`.
5. **Cascading inheritance:** When a category has `allowComments: true` but a chapter within it has `allowComments: false`, the chapter override must take precedence.

---

### Task 1: Core Comment Backend & Storage Engine (`CommentService.php`)

**Files:**
- Create: `assets/extensions/tool-comments/CommentService.php`
- Test: `tests/comments_test.php`

- [ ] **Step 1: Write the failing tests in `tests/comments_test.php`**
  Cover:
  - Storage directory initialization and `.htaccess` security creation.
  - Cascading comments-enabled resolution (`global` -> `category` -> `chapter`).
  - Adding root comment and adding reply (1-level nesting).
  - Editing comment with ownership check.
  - Soft-deleting parent with replies (tombstoning) vs hard-deleting leaf comments.
  - Restricted Markdown parsing and XSS script stripping.
- [ ] **Step 2: Run test to verify it fails**
  Run: `php tests/comments_test.php`
- [ ] **Step 3: Implement `CommentService.php`**
  - Storage directory: `data/comments/`
  - Atomic read/write using `flock(..., LOCK_EX)` and temporary file rename.
  - Methods:
    - `getStorageDir()`: returns path and ensures `.htaccess` denying web access exists.
    - `isCommentsEnabled($bookId, $slug, $config)`: resolves inheritance.
    - `getComments($bookId, $slug)`: returns formatted thread structure.
    - `addComment($bookId, $slug, $username, $role, $content, $parentId = null)`: persists comment or reply with unique ID, timestamp, and sanitization.
    - `editComment($bookId, $slug, $commentId, $username, $isAdmin, $newContent)`: updates content and `updatedAt`.
    - `deleteComment($bookId, $slug, $commentId, $username, $isAdmin)`: marks deleted or unlinks if no replies.
    - `renderSafeMarkdown($markdown)`: converts Markdown to safe HTML, stripping scripts, iframes, inline event handlers, and `javascript:` URIs.
- [ ] **Step 4: Run test to verify it passes**
  Run: `php tests/comments_test.php`
- [ ] **Step 5: Commit changes**
  Run: `git add assets/extensions/tool-comments/CommentService.php tests/comments_test.php && git commit -m "feat(comments): implement core CommentService storage and sanitization engine"`

---

### Task 2: Extension Manifest & API Handler (`manifest.json` & `handler.php`)

**Files:**
- Create: `assets/extensions/tool-comments/manifest.json`
- Create: `assets/extensions/tool-comments/handler.php`
- Modify: `tests/comments_test.php`

- [ ] **Step 1: Write test cases in `tests/comments_test.php` for extension discovery and action dispatching**
  - Test `ExtensionManager::getInstance()->getUtilities()` contains `comments`.
  - Test unauthenticated requests to `ext_comments_get` and `ext_comments_add` return unauthorized JSON error.
  - Test authenticated requests for adding, editing, and deleting comments via `$extManager->handleAction(...)`.
  - Test audit log emission on comment actions.
- [ ] **Step 2: Run test to verify it fails**
  Run: `php tests/comments_test.php`
- [ ] **Step 3: Create `manifest.json`**
  ```json
  {
    "id": "comments",
    "type": "utility",
    "adminOnly": false,
    "title": "Document Comments",
    "icon": "💬",
    "placement": "content_footer",
    "handler": "handler.php",
    "actions": [
      "ext_comments_get",
      "ext_comments_add",
      "ext_comments_edit",
      "ext_comments_delete"
    ],
    "styles": ["style.css"],
    "scripts": ["script.js"]
  }
  ```
- [ ] **Step 4: Implement `handler.php`**
  - Verify session via `Auth::startSession()` and check `Auth::isLoggedIn()`.
  - Dispatch actions:
    - `ext_comments_get`: Validate `book` and `slug`, return comments array, current user identity, and whether comments are enabled for this chapter.
    - `ext_comments_add`: Check `isCommentsEnabled()`, validate `content` (1 to 2000 characters), pass `parentId`, call `AuditLogger::log()`, return newly created comment.
    - `ext_comments_edit`: Validate `commentId`, `content`, call `AuditLogger::log()`, return updated comment.
    - `ext_comments_delete`: Validate `commentId`, call `AuditLogger::log()`, return success.
- [ ] **Step 5: Run test to verify it passes**
  Run: `php tests/comments_test.php`
- [ ] **Step 6: Commit changes**
  Run: `git add assets/extensions/tool-comments/manifest.json assets/extensions/tool-comments/handler.php tests/comments_test.php && git commit -m "feat(comments): add extension manifest and API action handler"`

---

### Task 3: Category & Chapter Settings in Modals and Admin API

**Files:**
- Modify: `templates/modals/create_category.php`
- Modify: `templates/modals/edit_category.php`
- Modify: `templates/modals/create_chapter.php`
- Modify: `templates/modals/edit_chapter.php`
- Modify: `api/admin.php`
- Modify: `assets/js/app.js`
- Test: `tests/comments_test.php`

- [ ] **Step 1: Write tests for category and chapter comment configuration persistence**
  - Add test in `tests/comments_test.php` testing saving `allowComments` in category and chapter via `api/admin.php` actions (`create_category`, `update_category`, `add_chapter`, `edit_chapter`).
- [ ] **Step 2: Run test to verify it fails**
  Run: `php tests/comments_test.php`
- [ ] **Step 3: Update `create_category.php` and `edit_category.php`**
  - Add checkbox: `<input type="checkbox" id="edit-category-allow-comments" name="allowComments" value="1"> <label for="edit-category-allow-comments">Allow signed-in users to comment on chapters in this category</label>`
- [ ] **Step 4: Update `create_chapter.php` and `edit_chapter.php`**
  - Add dropdown for chapter-level override:
    - `inherit` (Inherit from category)
    - `enabled` (Always allow comments)
    - `disabled` (Disallow comments)
- [ ] **Step 5: Update `api/admin.php` to persist `allowComments`**
  - In `create_category` and `update_category`: capture and store `allowComments` boolean flag.
  - In `add_chapter` and `edit_chapter`: capture and store `allowComments` value (`null` for inherit, `true`, or `false`).
- [ ] **Step 6: Update `assets/js/app.js`**
  - Populate `edit-category-allow-comments` when opening edit category modal.
  - Populate `edit-chapter-allow-comments` when opening edit chapter modal.
- [ ] **Step 7: Run test to verify it passes**
  Run: `php tests/comments_test.php`
- [ ] **Step 8: Commit changes**
  Run: `git add templates/modals/ api/admin.php assets/js/app.js tests/comments_test.php && git commit -m "feat(comments): add category and chapter comment toggles to admin modals"`

---

### Task 4: Frontend UI Component (`style.css` & `script.js`)

**Files:**
- Create: `assets/extensions/tool-comments/style.css`
- Create: `assets/extensions/tool-comments/script.js`

- [ ] **Step 1: Write `style.css`**
  - Style `#qwiki-comments-section`, `.comments-header`, `.comments-toggle-btn`, `.comments-count-pill`.
  - Style comment cards, avatars, user badges (`.badge-admin`, `.badge-viewer`), relative timestamps.
  - Style 1-level indented reply list (`.comments-replies`).
  - Style markdown preview container and action buttons (`Reply`, `Edit`, `Delete`, `Cancel`, `Save`).
  - Dark/Light mode theme awareness using CSS custom variables (`--bg-primary`, `--bg-secondary`, `--border-color`, `--text-primary`, `--text-muted`, `--accent-color`).
- [ ] **Step 2: Write `script.js`**
  - Detect active chapter details (`data-slug`, `data-book` from header or `#btn-edit-chapter-meta`).
  - Fetch comments via `api/admin.php?action=ext_comments_get`.
  - If user is logged in and comments are enabled:
    - Inject container `<div id="qwiki-comments-section">` right after `#content-body`.
    - Render comment threads with avatars, relative time calculation, edit/delete buttons, reply forms.
    - Wire `[Hide Comments / Show Comments]` toggle with persistence in `localStorage.getItem('qwiki_comments_visible')`.
    - Provide live Markdown formatting preview button on comment/reply textareas.
    - Handle asynchronous AJAX submissions for `ext_comments_add`, `ext_comments_edit`, `ext_comments_delete` with friendly error alerts.
- [ ] **Step 3: Verify in browser / automated headless checks**
  - Verify script syntax and DOM creation.
- [ ] **Step 4: Commit changes**
  Run: `git add assets/extensions/tool-comments/style.css assets/extensions/tool-comments/script.js && git commit -m "feat(comments): build reactive comments frontend UI and styles"`

---

### Task 5: Full Regression Testing & Demo Documentation

**Files:**
- Modify: `tests/comments_test.php`
- Modify: `CHANGELOG.md`
- Modify: `demo-data/content/getting-started/features.md`
- Modify: `content/getting-started/features.md`

- [ ] **Step 1: Run full test suite**
  Run: `php tests/comments_test.php && php tests/lock_test.php && php tests/lock_integration_test.php`
- [ ] **Step 2: Update documentation**
  - Document the comments extension in `content/getting-started/features.md` and `demo-data/content/getting-started/features.md`.
  - Add release entry in `CHANGELOG.md`.
- [ ] **Step 3: Final verification and commit**
  Run: `git add CHANGELOG.md content/ demo-data/ tests/ && git commit -m "docs(comments): document comments extension and verify full test suite"`
