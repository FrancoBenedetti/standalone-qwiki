const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing GitHub-Style Header Wiki Selector & Search ---');

const html = `
<!DOCTYPE html>
<html>
<head></head>
<body>
    <header class="app-header">
        <div class="brand-container">
            <div class="brand-header-group">
                <a href="./" class="brand-logo">⚡ QWIKI</a>
                <span class="brand-separator" aria-hidden="true">/</span>
                <div class="dropdown brand-wiki-selector" id="brand-wiki-selector">
                    <button type="button" class="brand-subwiki-badge brand-wiki-selector-toggle" id="brand-wiki-selector-toggle" aria-haspopup="true" aria-expanded="false">
                        <span class="subwiki-badge-icon">📚</span>
                        <span class="subwiki-badge-title">Main Wiki</span>
                        <span class="subwiki-badge-caret" aria-hidden="true">▾</span>
                    </button>
                    <div class="dropdown-menu brand-wiki-selector-menu" id="brand-wiki-selector-menu" role="region" aria-label="Switch wiki">
                        <div class="wiki-selector-header">
                            <span>Switch wiki</span>
                        </div>
                        <div class="wiki-selector-search-wrap">
                            <input type="search" id="wiki-selector-search" class="wiki-selector-search" placeholder="Find a wiki..." autocomplete="off">
                        </div>
                        <div class="wiki-selector-list" id="wiki-selector-list">
                            <a href="./" class="wiki-selector-item active" data-wiki-title="Main Wiki" data-wiki-slug="">
                                <span class="wiki-selector-check" aria-hidden="true">✓</span>
                                <span class="wiki-selector-item-main">
                                    <span class="wiki-selector-item-title">Main Wiki</span>
                                </span>
                                <span class="badge-subwiki-count">4 docs</span>
                            </a>
                            <a href="engineering/" class="wiki-selector-item" data-wiki-title="Engineering Portal" data-wiki-slug="engineering">
                                <span class="wiki-selector-check" aria-hidden="true"></span>
                                <span class="wiki-selector-item-main">
                                    <span class="wiki-selector-item-title">Engineering Portal</span>
                                </span>
                                <span class="badge-subwiki-count">12 docs</span>
                            </a>
                            <a href="marketing/" class="wiki-selector-item" data-wiki-title="Marketing Handbook" data-wiki-slug="marketing">
                                <span class="wiki-selector-check" aria-hidden="true"></span>
                                <span class="wiki-selector-item-main">
                                    <span class="wiki-selector-item-title">Marketing Handbook</span>
                                </span>
                                <span class="badge-subwiki-count">7 docs</span>
                            </a>
                        </div>
                        <div class="wiki-selector-empty" id="wiki-selector-empty" style="display: none;">
                            No matching wikis found
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main class="app-content"><div id="outside-area">Content</div></main>
</body>
</html>
`;

const dom = new JSDOM(html, { url: 'http://localhost/qwiki/' });
const { window } = dom;
global.window = window;
global.document = window.document;

const modalsJsPath = path.join(__dirname, '..', 'assets', 'js', 'core', 'modals.js');
const modalsJsContent = fs.readFileSync(modalsJsPath, 'utf8');
const runModals = new Function('window', 'document', modalsJsContent);
runModals(window, document);
window.QwikiApp.modals.init();

const toggleBtn = document.getElementById('brand-wiki-selector-toggle');
const menu = document.getElementById('brand-wiki-selector-menu');
const searchInput = document.getElementById('wiki-selector-search');
const items = Array.from(document.querySelectorAll('.wiki-selector-item'));
const emptyMsg = document.getElementById('wiki-selector-empty');

// 1. Toggle opens dropdown and sets aria-expanded="true"
assert.strictEqual(menu.classList.contains('show'), false, 'Menu should initially be closed');
toggleBtn.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
assert.strictEqual(menu.classList.contains('show'), true, 'Clicking toggle should open menu');
assert.strictEqual(toggleBtn.getAttribute('aria-expanded'), 'true', 'aria-expanded should be true when open');
console.log('✅ 1. Opening wiki selector dropdown works');

// 2. Filtering via search input hides non-matching items
searchInput.value = 'engin';
searchInput.dispatchEvent(new window.Event('input', { bubbles: true }));

const visibleAfterFilter = items.filter(el => el.style.display !== 'none');
assert.strictEqual(visibleAfterFilter.length, 1, 'Only Engineering Portal should match "engin"');
assert.strictEqual(visibleAfterFilter[0].getAttribute('data-wiki-slug'), 'engineering');
assert.strictEqual(emptyMsg.style.display, 'none', 'Empty state should remain hidden when match exists');
console.log('✅ 2. Search filtering by title/slug works');

// 3. Filtering with no matches shows empty state
searchInput.value = 'nonexistent-xyz';
searchInput.dispatchEvent(new window.Event('input', { bubbles: true }));

const visibleWhenEmpty = items.filter(el => el.style.display !== 'none');
assert.strictEqual(visibleWhenEmpty.length, 0, 'No items should be visible');
assert.notStrictEqual(emptyMsg.style.display, 'none', 'Empty state message must be visible when 0 matches');
console.log('✅ 3. Empty state display when no subwikis match works');

// 4. Escape key clears or closes the menu
const escEvent = new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
searchInput.dispatchEvent(escEvent);
assert.strictEqual(menu.classList.contains('show'), false, 'Escape key should close the wiki selector menu');
assert.strictEqual(toggleBtn.getAttribute('aria-expanded'), 'false', 'aria-expanded should be false after Escape close');
console.log('✅ 4. Escape key dismissal works');

// 5. Re-opening resets search filter and outside click closes menu
toggleBtn.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
assert.strictEqual(menu.classList.contains('show'), true, 'Menu re-opens');
assert.strictEqual(searchInput.value, '', 'Re-opening menu should reset search input');
assert.strictEqual(items.filter(el => el.style.display !== 'none').length, 3, 'All items visible after re-open');

document.getElementById('outside-area').dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
assert.strictEqual(menu.classList.contains('show'), false, 'Clicking outside closes the menu');
assert.strictEqual(toggleBtn.getAttribute('aria-expanded'), 'false', 'aria-expanded resets on outside click');
console.log('✅ 5. Filter reset on re-open and outside-click dismissal work');

// 6. Verify narrow-screen responsive CSS and markup protections against header overlap
const indexPhp = fs.readFileSync(path.join(__dirname, '..', 'index.php'), 'utf8');
const qwikiCss = fs.readFileSync(path.join(__dirname, '..', 'assets', 'css', 'qwiki.css'), 'utf8');

assert(indexPhp.includes('class="theme-btn-label"'), 'index.php #theme-toggle must wrap label in .theme-btn-label for mobile compact icon-only display');
assert(/\.brand-subwiki-badge\s*\{[^}]*min-width:\s*0/s.test(qwikiCss), '.brand-subwiki-badge must set min-width: 0 to prevent flex min-width:auto overflow');
assert(/\.brand-subwiki-badge\s*\{[^}]*overflow:\s*hidden/s.test(qwikiCss), '.brand-subwiki-badge must set overflow: hidden');
assert(/\.header-actions\s*\{[^}]*flex-shrink:\s*0/s.test(qwikiCss), '.header-actions must set flex-shrink: 0 so right-side buttons never crush');
assert(qwikiCss.includes('.theme-btn-label'), 'qwiki.css must define responsive hiding for .theme-btn-label');
console.log('✅ 6. Narrow-screen responsive header CSS & markup protections verified');

console.log('🎉 ALL HEADER WIKI SELECTOR TESTS PASSED!');
