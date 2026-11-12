# Per-Wiki Extension Management & Tabbed Settings Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow wiki and subwiki administrators to independently enable and disable extensions on a per-wiki basis, support read-only mode for existing documents of disabled page types, and reorganize the Wiki Settings modal into an unintimidating tabbed interface.

**Architecture:** Extensions declare their status via `qwiki.json` per wiki instance. `ExtensionManager` checks active extension state to filter frontend asset injection, hide disabled page type authoring tabs, hide disabled utility header buttons/modals, block unauthorized API actions, and render disabled page types in read-only mode. `templates/modals/settings.php` is restructured into 5 distinct tab panes (`General`, `Security & Access`, `Extensions`, `Email & SMTP`, `Feeds & Integrations`) styled with toggle switches and cards.

**Tech Stack:** PHP 8+, JavaScript (ES6+), CSS3 (Flexbox/Grid/CSS Variables), JSON configuration storage, Parsedown.

**Spec:** The design doc approved during brainstorming:
- Approach 1: Modernized Tabbed Settings Modal
- Read-only rendering mode for existing documents when a page type extension is disabled
- Unrestricted authority for subwiki administrators over their own subwiki extensions
- Default-on backward compatibility for all unconfigured extensions

## Global Constraints

- Never break existing documents: when a page type extension is disabled, existing documents must render their content in read-only mode with an advisory badge.
- Backward compatibility: any extension not explicitly specified in `qwiki.json` evaluates to `true` (enabled by default).
- Subwiki isolation: changing extension toggles in a subwiki modifies only that subwiki's `qwiki.json` and never parent or sibling installations.
- All HTML opening and closing tags in raw HTML blocks must be flush-left with 0 leading spaces to satisfy Toast UI WYSIWYG editor rules.
- Existing tests must pass: `node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php`.

## Review Focus

1. **Unchecked Checkbox Submission**: When an admin disables an extension by unchecking a toggle, HTML form submission does not include unchecked boxes; backend must properly identify unselected extensions and store `false`.
2. **Read-Only Edit Interception**: When an HTML or Form document is viewed while its extension is disabled, the "Edit" button must be suppressed and backend save calls must be rejected with an informative error.
3. **Frontend Asset Leakage**: Disabling an extension must prevent its CSS and JS from being included via `getFrontendAssets()`, preventing third-party bundle overhead on pages that do not use it.
4. **Subwiki Independence**: A subwiki admin toggling an extension must only affect their subwiki's `qwiki.json`, leaving parent wiki and sibling subwikis unchanged.
5. **Dynamic Theme Editor Button**: `assets/js/app.js` currently injects `btnOpenThemeEditor` before `settingsForm.lastElementChild`. Redesigning the modal must not cause JS errors or misplace this button.

---

### Task 1: Extension State Resolution & Asset Filtering in `ExtensionManager`

**Files:**
- Modify: `lib/Core/ExtensionManager.php:70-120, 260-397`
- Test: `tests/test_extension_management.php`

**Interfaces:**
- Consumes: `Config::load()['extensions'] ?? []`
- Produces: 
  - `ExtensionManager::isExtensionEnabled(string $id): bool`
  - `ExtensionManager::getAllExtensions(): array`
  - `ExtensionManager::getEnabledPageTypes(): array`
  - `ExtensionManager::getEnabledUtilities(): array`

- [ ] **Step 1: Write the failing unit test for extension state resolution & filtering**

Create `tests/test_extension_management.php` testing:
- Default state: all discovered extensions return `isExtensionEnabled($id) === true` when no `extensions` key exists in `qwiki.json`.
- Explicit disable: when `"extensions": {"comments": false, "html": false}`, `isExtensionEnabled('comments')` and `isExtensionEnabled('html')` return `false`.
- `getEnabledPageTypes()` excludes disabled page types.
- `getEnabledUtilities()` excludes disabled utilities.
- `getFrontendAssets()` excludes scripts/styles belonging to disabled extensions.
- `getAllExtensions()` returns all extensions with an `'enabled'` boolean key.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_extension_management.php`  
Expected: FAIL (methods `isExtensionEnabled` or `getAllExtensions` do not exist).

- [ ] **Step 3: Implement state methods and asset filtering in `ExtensionManager.php`**

In `lib/Core/ExtensionManager.php`:
- Implement `isExtensionEnabled(string $id): bool`:
  ```php
  public function isExtensionEnabled(string $id): bool {
      $config = Config::load();
      $extensions = $config['extensions'] ?? [];
      if (array_key_exists($id, $extensions)) {
          return !empty($extensions[$id]);
      }
      return true;
  }
  ```
- Implement `getAllExtensions(): array`: returns an associative array of all discovered extensions (both page types and utilities), each decorated with `'enabled' => $this->isExtensionEnabled($id)`.
- Implement `getEnabledPageTypes(): array`: filters `$this->pageTypes` using `isExtensionEnabled`.
- Implement `getEnabledUtilities(): array`: filters `$this->utilities` using `isExtensionEnabled`.
- Update `getFrontendAssets()`: loop only through enabled page types and enabled utilities.
- Update `renderHeaderUtilityButtons($isAdmin)`: iterate only through enabled utilities.
- Update `renderUtilityModals()`: iterate only through enabled utilities.
- Update `renderAddDocumentTabs()`: iterate only through enabled page types.
- Update `renderAddDocumentForms()`: iterate only through enabled page types.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_extension_management.php`  
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add lib/Core/ExtensionManager.php tests/test_extension_management.php
git commit -m "feat: add extension enablement checks and asset filtering to ExtensionManager"
```

---

### Task 2: Action Interception & Read-Only Handling for Disabled Page Types

**Files:**
- Modify: `lib/Core/ExtensionManager.php:200-300`
- Modify: `index.php:800-850`
- Test: `tests/test_extension_management.php`

**Interfaces:**
- Consumes: `isExtensionEnabled(string $id)`
- Produces: 
  - `ExtensionManager::handleAction()` returns `true` with `403 Forbidden` error if extension is disabled.
  - `ExtensionManager::renderPage()` renders existing documents of disabled page types with a read-only advisory banner.
  - Document toolbar suppresses the "Edit" button when viewing a document whose page type extension is disabled.

- [ ] **Step 1: Write tests for action blocking and read-only rendering**

In `tests/test_extension_management.php`, add assertions for:
- When extension `activity_log` is disabled, calling `handleAction('ext_activity_get_logs', ...)` echoes a JSON error `{"success": false, "error": "Extension 'activity_log' is disabled on this wiki"}` and returns `true`.
- When extension `html` is disabled, calling `handleAction('save_html', ...)` echoes a JSON error `{"success": false, "error": "Extension 'html' is disabled on this wiki"}`.
- When extension `html` is disabled, `renderPage($htmlChapter, ...)` still renders the HTML body and prepends a read-only advisory banner `<div class="alert alert-info">...</div>`.
- When extension `html` is enabled, `renderPage($htmlChapter, ...)` renders normally without the read-only banner.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_extension_management.php`  
Expected: FAIL (actions are not blocked; read-only banner not rendered).

- [ ] **Step 3: Implement action blocking and read-only rendering in `ExtensionManager.php` and `index.php`**

In `lib/Core/ExtensionManager.php`:
- In `handleAction($action, $requestData)`:
  - When matching a utility extension `$id`, check `$this->isExtensionEnabled($id)`. If `false`:
    ```php
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => "Extension '{$id}' is disabled on this wiki"]);
    return true;
    ```
  - When matching a page type extension `$id`, check `$this->isExtensionEnabled($id)`. If `false`:
    ```php
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => "Extension '{$id}' is disabled on this wiki"]);
    return true;
    ```
- In `renderPage($activeChapter, $activeBook, $config)`:
  - If `$type` matches a custom page type in `$this->pageTypes`:
    - Check if `$this->isExtensionEnabled($type)`.
    - If disabled: render the extension via its renderer, but prepend:
      ```php
      "<div class=\"alert alert-info\" style=\"margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;\">"
      . "<span>🔒</span> <span><strong>Read-only mode:</strong> The <em>" . htmlspecialchars($ext['title'] ?? $type) . "</em> extension is disabled on this wiki. Editing is disabled.</span>"
      . "</div>"
      ```
- In `index.php`:
  - When rendering document header actions / toolbar (around lines 750-850), if the active document is a custom extension page type and `$extManager->isExtensionEnabled($docType)` is `false`, suppress or disable the "Edit" button.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_extension_management.php`  
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add lib/Core/ExtensionManager.php index.php tests/test_extension_management.php
git commit -m "feat: enforce action blocking and read-only mode for disabled extensions"
```

---

### Task 3: Backend Settings Persistence in `api/admin.php`

**Files:**
- Modify: `api/admin.php:1633-1705`
- Test: `tests/test_extension_management.php`

**Interfaces:**
- Consumes: `$_POST['extensions']`, `$_POST['extensions_configured']`
- Produces: `$config['extensions']` updated in `qwiki.json` via `Config::save()`

- [ ] **Step 1: Write test for saving extension settings via `api/admin.php`**

In `tests/test_extension_management.php`, add test cases for `update_settings`:
- Simulating admin authentication.
- Posting `update_settings` with `extensions_configured = 1` and `extensions[comments] = 1`, `extensions[postbox] = 0`.
- Verify `qwiki.json` reflects `"extensions": {"comments": true, "postbox": false}`.
- Unchecked/omitted extensions during submission with `extensions_configured = 1` are set to `false`.
- Subwiki simulation: updating settings on a subwiki config does not alter parent wiki's `qwiki.json`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/test_extension_management.php`  
Expected: FAIL (`update_settings` does not process `extensions`).

- [ ] **Step 3: Update `update_settings` in `api/admin.php`**

In `api/admin.php` within `case 'update_settings':`:
```php
if (!empty($_POST['extensions_configured'])) {
    $submittedExtensions = isset($_POST['extensions']) && is_array($_POST['extensions']) ? $_POST['extensions'] : [];
    $allDiscovered = ExtensionManager::getInstance()->getAllExtensions();
    $extensionsConfig = $config['extensions'] ?? [];
    
    foreach ($allDiscovered as $extId => $extInfo) {
        $extensionsConfig[$extId] = !empty($submittedExtensions[$extId]);
    }
    $config['extensions'] = $extensionsConfig;
}
```
Save with `Config::save($config)`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/test_extension_management.php`  
Expected: PASS

- [ ] **Step 5: Commit changes**

```bash
git add api/admin.php tests/test_extension_management.php
git commit -m "feat: persist extension enabled states in update_settings API"
```

---

### Task 4: UI/UX Modernization of Wiki Settings Modal (`templates/modals/settings.php`)

**Files:**
- Modify: `templates/modals/settings.php`
- Modify: `assets/css/qwiki.css`
- Modify: `assets/js/app.js`
- Test: `tests/test_settings_modal_tabs.js`

**Interfaces:**
- Consumes: `ExtensionManager::getInstance()->getAllExtensions()`, existing `$config`
- Produces:
  - 5 tab panels: `General`, `Security & Access`, `Extensions`, `Email & SMTP`, `Feeds & Integrations`
  - Clean extension cards with toggle switches
  - Native "Open Theme Editor" button in General tab (replacing DOM injection)

- [ ] **Step 1: Write automated DOM / structural test for tabbed settings modal**

Create `tests/test_settings_modal_tabs.js` using Node.js / JSDOM or parsing PHP output to verify:
- Modal contains navigation tabs with `data-tab` targets: `settings-tab-general`, `settings-tab-security`, `settings-tab-extensions`, `settings-tab-smtp`, `settings-tab-integrations`.
- Each tab content container has corresponding ID and class `settings-tab-content`.
- Extensions tab contains `extensions_configured` hidden input.
- All discovered extensions are listed with checkboxes/switches under `extensions[<id>]`.
- `btn-open-theme-editor-direct` is present in the General tab.
- Form preserves all existing inputs (`title`, `logoText`, `theme`, `smtp[...]`, `feedAccessToken`, etc.).

- [ ] **Step 2: Run test to verify it fails**

Run: `node tests/test_settings_modal_tabs.js`  
Expected: FAIL (modal is still the old single form without tabs).

- [ ] **Step 3: Add CSS for Settings modal tabs and switch toggles in `assets/css/qwiki.css`**

Add CSS styles:
- `.settings-tabs`: horizontal tab bar inside `.modal-header` or directly below it, styled with `.tab-btn` consistency.
- `.settings-tab-pane`: display none by default; `.active` displays block.
- `.ext-card-list`: grid or flex column of cards with border, border-radius, background, padding.
- `.ext-card`: card containing icon, title, badge (`.doc-badge`), description, and toggle switch.
- `.switch-toggle`: accessible toggle switch slider with checked accent color.

- [ ] **Step 4: Refactor `templates/modals/settings.php` into the 5 tab panes**

Organize `settings.php`:
- Tab Bar:
  - `🌐 General`
  - `🔒 Access & Security`
  - `🧩 Extensions`
  - `📧 Email (SMTP)`
  - `📡 Feeds & Integrations`
- Tab 1: General (Title, Logo Text, Logo Upload, Theme select, "🎨 Open Theme Editor" button, Default Category select, Badges checkboxes).
- Tab 2: Access & Security (Access Mode, 2FA Policy, Social Share Description & Image).
- Tab 3: Extensions (Grouped by Page Types & Utilities, hidden input `extensions_configured`, extension cards with toggle switch inputs).
- Tab 4: Email (SMTP) (Custom socket SMTP toggle, host, port, encryption, user, pass, sender, test connection button).
- Tab 5: Feeds & Integrations (RSS feed count, token, feed URL with copy button, LLM Keys button, Gemini settings button, Subwiki parent fields if `$isSubwiki`).
- Keep "Save Settings" sticky at bottom of the modal card.
- Keep "Reset Demo Package" in footer if in demo mode.

- [ ] **Step 5: Update `assets/js/app.js` for tab handling & Theme Editor integration**

In `assets/js/app.js`:
- Ensure `settings-modal` tab buttons switch `.active` class between `.settings-tab-pane` containers using the existing `.tab-btn` event listener.
- Update Theme Editor button logic to hook into `btn-open-theme-editor-direct` natively instead of dynamically inserting it before `settingsForm.lastElementChild`.

- [ ] **Step 6: Run test to verify it passes**

Run: `node tests/test_settings_modal_tabs.js`  
Expected: PASS

- [ ] **Step 7: Commit changes**

```bash
git add templates/modals/settings.php assets/css/qwiki.css assets/js/app.js tests/test_settings_modal_tabs.js
git commit -m "feat: restructure Wiki Settings modal into an unintimidating tabbed interface"
```

---

### Task 5: End-to-End Regression & Full Verification

**Files:**
- All modified files
- Documentation: `demo-data/content/user-guide/site-settings-users.md` and `content/user-guide/site-settings-users.md`

**Interfaces:**
- Full system regression

- [ ] **Step 1: Run complete test suite**

Run:
```bash
php tests/test_extension_management.php
node tests/test_settings_modal_tabs.js
node tests/test_video_embed.js
php tests/lock_test.php
php tests/lock_integration_test.php
```
Expected: All tests PASS with 0 failures.

- [ ] **Step 2: Update documentation**

Update `content/user-guide/site-settings-users.md` and `demo-data/content/user-guide/site-settings-users.md` to document the new Extensions tab in Wiki Settings, how to toggle tools, and the read-only behavior for disabled page types. Adhere to the markdown authoring rules in `AGENTS.md` (flush-left HTML tags, no blank lines inside HTML blocks).

- [ ] **Step 3: Commit final documentation and tests**

```bash
git add content/user-guide/site-settings-users.md demo-data/content/user-guide/site-settings-users.md
git commit -m "docs: document extension management and tabbed settings modal in user guide"
```
