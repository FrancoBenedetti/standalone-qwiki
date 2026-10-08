const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Sidebar Scroll & Category State Persistence ---');

// 1. Verify index.php & Navigation.php markup attributes
const indexPath = path.join(__dirname, '..', 'index.php');
const indexContent = fs.readFileSync(indexPath, 'utf8');
const navPath = path.join(__dirname, '..', 'lib', 'Core', 'Navigation.php');
const navContent = fs.readFileSync(navPath, 'utf8');

assert(indexContent.includes('data-category-id="subwikis"'), 'index.php must contain data-category-id="subwikis" for subwikis group');
assert(navContent.includes('data-category-id='), 'Navigation.php must output data-category-id for category items');
console.log('✅ Markup verification passed for data-category-id attributes');

// 2. Setup simulated DOM environment with sessionStorage
const html = `
<!DOCTYPE html>
<html>
<head></head>
<body>
    <aside class="app-sidebar" id="app-sidebar">
        <nav class="sidebar-nav">
            <a href="?doc=intro" class="nav-link" data-doc-slug="intro">Introduction</a>
            <div class="nav-category-item collapsed" data-category-id="guides" id="cat-guides">
                <div class="nav-category-header"><span>Guides</span></div>
                <div class="nav-document-list" data-parent-node-id="guides">
                    <a href="?doc=guide-1" class="nav-link" data-doc-slug="guide-1">Guide 1</a>
                    <a href="?doc=guide-2" class="nav-link active" data-doc-slug="guide-2">Guide 2</a>
                </div>
            </div>
            <div class="nav-category-item collapsed" data-category-id="advanced" id="cat-advanced">
                <div class="nav-category-header"><span>Advanced</span></div>
                <div class="nav-document-list" data-parent-node-id="advanced">
                    <a href="?doc=adv-1" class="nav-link" data-doc-slug="adv-1">Advanced 1</a>
                </div>
            </div>
            <div class="nav-category-item depth-0 nav-subwikis-group collapsed" data-category-id="subwikis">
                <div class="nav-category-header"><span>Subwikis</span></div>
                <div class="nav-document-list">
                    <a href="/subwiki" class="nav-link">Subwiki 1</a>
                </div>
            </div>
        </nav>
    </aside>
</body>
</html>
`;

// Helper to evaluate app.js initialization logic
const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJsContent = fs.readFileSync(appJsPath, 'utf8');

assert(appJsContent.includes('qwiki_sidebar_scroll'), 'app.js must persist qwiki_sidebar_scroll');
assert(appJsContent.includes('qwiki_category_states'), 'app.js must persist qwiki_category_states');
console.log('✅ app.js storage key definitions verified');

function createDom(sessionData = {}) {
    const dom = new JSDOM(html, {
        url: "http://localhost/qwiki/",
        runScripts: "outside-only"
    });
    for (const [k, v] of Object.entries(sessionData)) {
        dom.window.sessionStorage.setItem(k, v);
    }
    // Mock requestAnimationFrame and getBoundingClientRect
    dom.window.requestAnimationFrame = (fn) => fn();
    return dom;
}

// Test Case 1: Category state restoration on page load
{
    const dom = createDom({
        'qwiki_category_states': JSON.stringify({
            'advanced': 'expanded',
            'guides': 'collapsed' // contains active document, so must NOT be collapsed
        })
    });

    dom.window.eval(appJsContent);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

    const catAdvanced = dom.window.document.getElementById('cat-advanced');
    const catGuides = dom.window.document.getElementById('cat-guides');

    assert.strictEqual(catAdvanced.classList.contains('collapsed'), false, 'cat-advanced should be restored to expanded');
    assert.strictEqual(catGuides.classList.contains('collapsed'), false, 'cat-guides contains active link and must not be collapsed');
    console.log('✅ Test 1 passed: Category expansion restored and active document protected');
}

// Test Case 2: Manual category toggle updates sessionStorage
{
    const dom = createDom();

    dom.window.eval(appJsContent);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

    const catAdvancedHeader = dom.window.document.querySelector('#cat-advanced .nav-category-header');
    catAdvancedHeader.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));

    const catAdvanced = dom.window.document.getElementById('cat-advanced');
    assert.strictEqual(catAdvanced.classList.contains('collapsed'), false, 'cat-advanced should now be expanded');

    const saved = JSON.parse(dom.window.sessionStorage.getItem('qwiki_category_states') || '{}');
    assert.strictEqual(saved['advanced'], 'expanded', 'sessionStorage should record advanced as expanded');

    // Toggle again to collapse
    catAdvancedHeader.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));
    assert.strictEqual(catAdvanced.classList.contains('collapsed'), true, 'cat-advanced should now be collapsed');
    const savedAfter = JSON.parse(dom.window.sessionStorage.getItem('qwiki_category_states') || '{}');
    assert.strictEqual(savedAfter['advanced'], 'collapsed', 'sessionStorage should record advanced as collapsed');
    console.log('✅ Test 2 passed: Category accordion toggles persist to sessionStorage');
}

// Test Case 3: Sidebar scroll position restoration and click persistence
{
    const dom = createDom({
        'qwiki_sidebar_scroll': '342'
    });

    dom.window.eval(appJsContent);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

    const sidebarNav = dom.window.document.querySelector('.sidebar-nav');
    assert.strictEqual(sidebarNav.scrollTop, 342, 'sidebarNav.scrollTop should be restored to 342');

    // Simulate user scrolling and clicking a document link
    sidebarNav.scrollTop = 580;
    const link = sidebarNav.querySelector('a[data-doc-slug="guide-1"]');
    link.dispatchEvent(new dom.window.MouseEvent('click', { bubbles: true }));

    assert.strictEqual(dom.window.sessionStorage.getItem('qwiki_sidebar_scroll'), '580', 'Clicking link should save scrollTop 580 to sessionStorage');
    console.log('✅ Test 3 passed: Sidebar scrollTop restored and saved on link click');
}

// Test Case 4: Scroll persistence on beforeunload
{
    const dom = createDom();

    dom.window.eval(appJsContent);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

    const sidebarNav = dom.window.document.querySelector('.sidebar-nav');
    sidebarNav.scrollTop = 415;

    dom.window.dispatchEvent(new dom.window.Event('beforeunload'));
    assert.strictEqual(dom.window.sessionStorage.getItem('qwiki_sidebar_scroll'), '415', 'beforeunload should save scrollTop to sessionStorage');
    console.log('✅ Test 4 passed: Sidebar scrollTop saved on beforeunload');
}

// Test Case 5: Fallback scrollIntoView for active link on first visit
{
    const dom = createDom();

    let scrolledIntoView = false;
    const activeLink = dom.window.document.querySelector('.nav-link.active');
    activeLink.scrollIntoView = function(options) {
        scrolledIntoView = true;
        assert.strictEqual(options.block, 'nearest', 'scrollIntoView block should be nearest');
    };

    dom.window.eval(appJsContent);
    dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

    assert.strictEqual(scrolledIntoView, true, 'Active link should have scrollIntoView called when no saved scroll exists');
    console.log('✅ Test 5 passed: Active link brought into view when no saved scroll position exists');
}

console.log('\n🎉 ALL SIDEBAR SCROLL & ACCORDION PERSISTENCE TESTS PASSED!');
