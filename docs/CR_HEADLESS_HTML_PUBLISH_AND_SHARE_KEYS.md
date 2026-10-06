# Change Request: Headless HTML Publishing & Automated Share Key Generation

**Target Project:** `standalone-qwiki` (`~/Projects/standalone-qwiki`)  
**Document ID:** CR-QWIKI-2026-01  
**Category:** Extension & API Enhancement  
**Status:** Implemented  

---

## 1. Executive Summary & Context

`standalone-qwiki` already possesses a mature, functional HTML page extension (`assets/extensions/page-html`) that registers `type: "html"`, isolates rendered content inside an iframe, manages `<base href>` relative path resolution, and provides a top toolbar with printing and editing controls.

This Change Request specifies the minor backend enhancements required to allow external automation pipelines (such as the MoBioplus sales kit generator in `MobioPlus`) to:
1. Publish self-contained HTML dossiers and 1–2 page grower guides headlessly via API.
2. Automatically generate and return a public **`shareKey`** and **`shareUrl`** upon document creation, allowing external systems (like `mobio-map` GeoCRM) to link directly to the document without requiring manual admin intervention in the Qwiki web UI.

---

## 2. Technical Requirements

### Requirement 1: Automatic `shareKey` Generation on Chapter Creation
* **Current State:** Currently, when an HTML document is created via `create_html` in `assets/extensions/page-html/handler.php`, it is saved without a `shareKey`. The `shareKey` is only generated when an administrator clicks the "Share" button in the frontend.
* **Specification:** 
  During HTML document creation, if no `shareKey` is explicitly supplied, automatically generate a 16-character hexadecimal token using `Qwiki\Core\Navigation::generateShareKey()` (or `bin2hex(random_bytes(8))`) and include it in the `$chapterData` written to `qwiki.json`:
  ```php
  $shareKey = !empty($_POST['shareKey']) ? trim($_POST['shareKey']) : bin2hex(random_bytes(8));
  $chapterData = [
      'title' => $title,
      'slug' => $slug,
      'type' => 'html',
      'file' => $filePath,
      'shareKey' => $shareKey
  ];
  ```

### Requirement 2: Headless API Publishing for HTML Documents
* **Current State:** 
  - `assets/extensions/page-html/handler.php` requires `Auth::isAdmin()` (an active PHP session cookie).
  - `api/publish.php` accepts `publishApiKey` via `HTTP_X_API_KEY`, but is currently hardcoded to Markdown files (`.md`) and strips HTML `<style>` tags.
* **Specification:**
  Update `api/publish.php` (or add a headless action branch in `handler.php`) to:
  1. Accept an optional `type` parameter (`markdown` or `html`). Default to `markdown` for backward compatibility.
  2. When `type === 'html'`:
     - Validate that the caller provided a valid `HTTP_X_API_KEY`.
     - Skip the Markdown HTML tag stripping (allow `<style>`, `<meta>`, `@media print`).
     - Save the file as `.html` in `content/<bookId>/<slug>.html`.
     - Call `ensure_html_base_href($content, $targetRelFile)`.
     - Register the chapter in `qwiki.json` with `'type' => 'html'` and an auto-generated `'shareKey'`.
     - Return the share details in the JSON response:
       ```json
       {
         "success": true,
         "slug": "bellevue-estate-growers-guide",
         "bookId": "western-cape",
         "file": "content/western-cape/bellevue-estate-growers-guide.html",
         "shareKey": "7c8e2a1d9f4b3e6c",
         "shareUrl": "https://mobioplus.co.za/wiki/?share=7c8e2a1d9f4b3e6c"
       }
       ```

### Requirement 3: Support for Multilingual Companion Metadata
* **Specification:**
  Allow the API / creator to pass an optional `translations` associative array (e.g. `{"af": "bellevue-estate-growers-guide-af"}`). When present, store this in the chapter node in `qwiki.json` so that localized language switchers can resolve companion documents.

---

## 3. Implementation Checklist

- [x] **1. `assets/extensions/page-html/handler.php`:**
  - Auto-generate `shareKey` in the `create_html` action block if missing.
  - Include `'shareKey'` and `'shareUrl'` in the success response payload.
- [x] **2. `api/publish.php`:**
  - Support `type: "html"` in POST payloads.
  - Preserve CSS, SVG, and print media rules for HTML uploads when authenticated with `publishApiKey`.
  - Insert chapter with `type: "html"` into `qwiki.json`.
- [x] **3. Verification Test (`tests/test_headless_html_publish.php`):**
  - Verify that a `POST` request with `HTTP_X_API_KEY` and raw HTML creates the `.html` file, writes the node with `shareKey` into `qwiki.json`, and returns a working `shareUrl`.
  - Verify that navigating to `/?share=<shareKey>` renders the HTML document in reader mode.
