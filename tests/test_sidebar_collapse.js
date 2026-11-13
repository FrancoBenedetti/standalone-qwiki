const fs = require('fs');
const assert = require('assert');
const path = require('path');

const indexPhp = fs.readFileSync(path.join(__dirname, '../index.php'), 'utf8');
const sidebarJs = fs.readFileSync(path.join(__dirname, '../assets/js/core/sidebar.js'), 'utf8');
const qwikiCss = fs.readFileSync(path.join(__dirname, '../assets/css/qwiki.css'), 'utf8');

// 1. Check toggle buttons in index.php
assert.ok(indexPhp.includes('id="btn-toggle-left-sidebar"'), 'index.php must contain #btn-toggle-left-sidebar');
assert.ok(indexPhp.includes('id="btn-toggle-right-toc"'), 'index.php must contain #btn-toggle-right-toc');

// 2. Check sidebar.js functions, localStorage keys, and hotkey input safety
assert.ok(sidebarJs.includes('toggleLeftSidebar'), 'sidebar.js must expose toggleLeftSidebar');
assert.ok(sidebarJs.includes('toggleRightToc'), 'sidebar.js must expose toggleRightToc');
assert.ok(sidebarJs.includes('qwiki_sidebar_collapsed'), 'sidebar.js must persist qwiki_sidebar_collapsed');
assert.ok(sidebarJs.includes('qwiki_toc_collapsed'), 'sidebar.js must persist qwiki_toc_collapsed');
assert.ok(sidebarJs.includes('isContentEditable') && sidebarJs.includes('TEXTAREA'), 'sidebar.js must guard hotkeys against input/editable elements');

// 3. Check CSS collapse rules and resizer hiding
assert.ok(qwikiCss.includes('.app-container.sidebar-collapsed'), 'qwiki.css must define .app-container.sidebar-collapsed');
assert.ok(qwikiCss.includes('.app-container.toc-collapsed'), 'qwiki.css must define .app-container.toc-collapsed');
assert.ok(qwikiCss.includes('#sidebar-resizer'), 'qwiki.css must handle #sidebar-resizer when collapsed');

console.log('PASS: Collapsible sidebar tests passed');
