const assert = require('assert');
const fs = require('fs');
const path = require('path');

const rootDir = path.join(__dirname, '..');
const indexPhp = fs.readFileSync(path.join(rootDir, 'index.php'), 'utf8');
const qwikiCss = fs.readFileSync(path.join(rootDir, 'assets/css/qwiki.css'), 'utf8');
const appJs = fs.readFileSync(path.join(rootDir, 'assets/js/app.js'), 'utf8');

console.log('--- Testing Inline Editor Fullscreen Mode ---');

// 1. Verify header fullscreen toggle button in index.php
assert.ok(indexPhp.includes('id="btn-editor-fullscreen"'), 'index.php should include #btn-editor-fullscreen in #edit-actions');
assert.ok(indexPhp.includes('class="fullscreen-btn-label"'), 'index.php should include .fullscreen-btn-label');

// 2. Verify CSS fullscreen rules in qwiki.css
assert.ok(qwikiCss.includes('body.editor-fullscreen .app-content.is-editing-doc'), 'qwiki.css should expand .app-content.is-editing-doc in fullscreen mode');
assert.ok(qwikiCss.includes('body.editor-fullscreen .app-header'), 'qwiki.css should hide .app-header in fullscreen mode');
assert.ok(qwikiCss.includes('body.editor-fullscreen .app-sidebar'), 'qwiki.css should hide .app-sidebar in fullscreen mode');
assert.ok(qwikiCss.includes('body.editor-fullscreen .app-toc'), 'qwiki.css should hide .app-toc in fullscreen mode');
assert.ok(qwikiCss.includes('body.editor-fullscreen .toastui-editor-popup'), 'qwiki.css should elevate .toastui-editor-popup z-index in fullscreen mode');

// 3. Verify JS logic in app.js
assert.ok(appJs.includes('function setEditorFullscreen(enabled)'), 'app.js should define setEditorFullscreen');
assert.ok(appJs.includes('function syncFullscreenEditorHeight()'), 'app.js should define syncFullscreenEditorHeight');
assert.ok(appJs.includes('const createFullscreenToolbarButton = () =>'), 'app.js should define createFullscreenToolbarButton for Toast UI toolbar');
assert.ok(appJs.includes("name: 'fullscreen'"), 'app.js should register fullscreen button in Toast UI toolbarItems');
assert.ok(appJs.includes("tuiEditor.setHeight('600px')"), 'app.js should restore 600px height when exiting fullscreen');
assert.ok(appJs.includes("e.key === 'F11'"), 'app.js should support F11 shortcut to toggle fullscreen');
assert.ok(appJs.includes("e.key === 'Escape'"), 'app.js should support Escape shortcut to exit fullscreen');

console.log('✅ All inline editor fullscreen assertions passed!');
