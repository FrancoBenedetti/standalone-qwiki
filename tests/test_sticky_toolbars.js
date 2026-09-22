const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Sticky Toolbars for Markdown and HTML Editors ---');

const qwikiCssPath = path.join(__dirname, '..', 'assets', 'css', 'qwiki.css');
const qwikiCss = fs.readFileSync(qwikiCssPath, 'utf8');

const htmlCssPath = path.join(__dirname, '..', 'assets', 'extensions', 'page-html', 'style.css');
const htmlCss = fs.readFileSync(htmlCssPath, 'utf8');

const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJs = fs.readFileSync(appJsPath, 'utf8');

// 1. Verify CSS custom properties and sticky rules in qwiki.css
console.log('1. Checking document viewing and Markdown sticky rules in qwiki.css...');
assert.ok(qwikiCss.includes('--content-padding-top'), 'qwiki.css should define --content-padding-top');
assert.ok(qwikiCss.includes('.content-header {') && qwikiCss.includes('position: sticky'), 'qwiki.css should style sticky content-header in document viewing mode');
assert.ok(qwikiCss.includes('.toc-nav-buttons') && qwikiCss.includes('position: sticky'), 'qwiki.css should style sticky .toc-nav-buttons in table of contents');
assert.ok(qwikiCss.includes('.app-content.is-editing-doc .content-header'), 'qwiki.css should style sticky header in edit mode');
assert.ok(qwikiCss.includes('position: sticky'), 'qwiki.css should use position: sticky');
assert.ok(qwikiCss.includes('#inline-editor-container .toastui-editor-defaultUI'), 'qwiki.css should target toastui-editor-defaultUI');
assert.ok(qwikiCss.includes('#inline-editor-container .toastui-editor-toolbar'), 'qwiki.css should target toastui-editor-toolbar');
assert.ok(qwikiCss.includes('overflow: visible !important'), 'defaultUI must have overflow: visible to allow child sticky positioning');
console.log('✅ Document viewing and Markdown CSS rules verified.');

// 2. Verify SunEditor sticky toolbar rules in page-html/style.css
console.log('2. Checking SunEditor sticky rules in page-html/style.css...');
assert.ok(htmlCss.includes('.sun-editor .se-toolbar'), 'page-html/style.css should style .sun-editor .se-toolbar');
assert.ok(htmlCss.includes('position: sticky !important'), 'SunEditor toolbar must be position: sticky !important');
assert.ok(htmlCss.includes('top: 0 !important'), 'SunEditor toolbar must have top: 0 !important');
assert.ok(htmlCss.includes('z-index: 30 !important'), 'SunEditor toolbar must have elevated z-index');
console.log('✅ SunEditor HTML CSS rules verified.');

// 3. Verify JS behavior in app.js
console.log('3. Checking JavaScript class management and shortcuts in app.js...');
assert.ok(appJs.includes("classList.add('is-editing-doc')"), 'app.js should add is-editing-doc class on openMarkdownEditor');
assert.ok(appJs.includes("classList.remove('is-editing-doc')"), 'app.js should remove is-editing-doc class on cancel/exit');
assert.ok(appJs.includes('--edit-header-height'), 'app.js should calculate and set --edit-header-height');
assert.ok(appJs.includes("key === 's' || e.key === 'S'"), 'app.js should have Ctrl+S shortcut handler for inline markdown editor');
console.log('✅ JavaScript integration verified.');

console.log('\n🎉 ALL STICKY TOOLBAR TESTS PASSED SUCCESSFULLY!');
