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
