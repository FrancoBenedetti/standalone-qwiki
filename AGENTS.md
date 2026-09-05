# Antigravity Agent Guidelines for Standalone Qwiki

## Release Preparation & Demo Data Alignment Workflow

Before bumping a version, tagging, and pushing a new release to the repository:

1. **Synchronize Demo Data Documentation**:
   - Ensure all files in `demo-data/content/getting-started/` and `demo-data/content/user-guide/` reflect all recent features, UI controls, and documentation additions.
   - Keep live documentation in `content/` synchronized with `demo-data/content/`.
2. **Exclude Non-Essential Test Pages**:
   - Do NOT add ad-hoc test pages, scratch scripts, or internal testing files (e.g. `test.html`, `payment-guide.html`, `pitch-deck.html`, `malicious-test*`) into `demo-data/` or `demo-data/qwiki-default.json`. Demo data must remain clean, professional, and directly relevant to Standalone Qwiki.
3. **Align Default Configuration**:
   - Keep `demo-data/qwiki-default.json` aligned with the standard navigation tree, valid document types, and latest configuration keys (`feedAccessToken`, `feedItemCount`, etc.).
4. **Version Bump & Release Sequence**:
   - Update `VERSION` in `lib/Core/Config.php`.
   - Update `CHANGELOG.md` with release version, date, and categorized change summary.
   - Update `README.md` if any new major features or system requirements were introduced.
   - Verify all test suites pass (`node tests/test_video_embed.js && php tests/lock_test.php && php tests/lock_integration_test.php`).
   - Commit changes, create an annotated git tag (`vX.Y.Z`), and push both `main` and the new tag to the remote repository.

---

## Markdown & HTML Content Authoring Guidelines

When creating or updating Markdown documents (`.md`) across `content/` or `demo-data/content/`:

1. **Keep HTML Tags Flush-Left (`^<`)**:
   - All HTML opening and closing tags in raw HTML blocks must be flush-left with 0 leading spaces to maintain compatibility with the Toast UI WYSIWYG editor regex (`^<`) and prevent editor crashes.
2. **No Blank Lines Inside HTML Blocks**:
   - Never include blank lines inside multi-line HTML blocks to prevent Parsedown from interrupting the container and auto-wrapping child tags in unwanted `<p>` tags.
3. **Avoid the 4-Space Indentation Trap**:
   - Do not indent tags or paragraphs by 4 spaces following a blank line unless intentionally creating a `<pre><code>` block.
4. **Multi-Step Setup Guides**:
   - Use numbered Markdown lists (`1.`, `2.`, `3.`) with isolated code blocks for each terminal command rather than one large code block with `#` comments.
5. **Video Auto-Embedding**:
   - Place standalone video links (YouTube, Vimeo, Loom, MP4) on their own line with blank lines before and after. Use `[Caption](url)` for styled subtitles.
6. **Subfolder-Agnostic Relative Routing**:
   - Use relative internal links without leading slashes (e.g. `getting-started/features`) so documentation remains fully portable across subfolders.
