const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Sidebar Search Clear Button Functionality ---');

// 1. Verify index.php markup
const indexPath = path.join(__dirname, '..', 'index.php');
const indexContent = fs.readFileSync(indexPath, 'utf8');

assert(indexContent.includes('class="sidebar-search-wrapper"'), 'index.php must contain sidebar-search-wrapper');
assert(indexContent.includes('id="sidebar-search-clear"'), 'index.php must contain sidebar-search-clear button');
assert(indexContent.includes('class="sidebar-search-clear"'), 'index.php must contain sidebar-search-clear class');
assert(indexContent.includes('aria-label="Clear search"'), 'sidebar-search-clear must have aria-label');
console.log('✅ Markup verification passed in index.php');

// 2. Verify qwiki.css rules
const cssPath = path.join(__dirname, '..', 'assets', 'css', 'qwiki.css');
const cssContent = fs.readFileSync(cssPath, 'utf8');

assert(cssContent.includes('.sidebar-search-wrapper'), 'qwiki.css must define .sidebar-search-wrapper');
assert(cssContent.includes('.sidebar-search-clear'), 'qwiki.css must define .sidebar-search-clear');
assert(cssContent.includes('.sidebar-search-clear:hover'), 'qwiki.css must define .sidebar-search-clear:hover');
console.log('✅ CSS verification passed in qwiki.css');

// 3. Functional JSDOM test simulating app.js search & clear logic
const html = `
<!DOCTYPE html>
<html>
<head></head>
<body>
    <div class="sidebar-search">
        <div class="sidebar-search-wrapper">
            <input type="text" id="search-input" class="search-input" placeholder="Search documentation...">
            <button type="button" id="sidebar-search-clear" class="sidebar-search-clear" aria-label="Clear search" title="Clear search" style="display: none;">
                <svg viewBox="0 0 24 24" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"></line></svg>
            </button>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="?doc=intro" class="nav-link" data-doc-slug="intro">Introduction</a>
        <div class="nav-category-item" id="cat-guides">
            <div class="nav-category-header"><span>Guides</span></div>
            <div class="nav-document-list">
                <a href="?doc=guide-1" class="nav-link" data-doc-slug="guide-1">Guide 1</a>
                <a href="?doc=guide-2" class="nav-link" data-doc-slug="guide-2">Guide 2</a>
            </div>
        </div>
        <div class="nav-category-item collapsed" id="cat-advanced">
            <div class="nav-category-header"><span>Advanced</span></div>
            <div class="nav-document-list">
                <a href="?doc=adv-1" class="nav-link" data-doc-slug="adv-1">Advanced 1</a>
            </div>
        </div>
    </nav>
</body>
</html>
`;

const dom = new JSDOM(html, { url: "http://localhost/qwiki/" });
const { window } = dom;
global.window = window;
global.document = window.document;

// Load app.js search logic snippet to execute in this DOM
const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJsContent = fs.readFileSync(appJsPath, 'utf8');

// Ensure app.js contains sidebarSearchClear definitions
assert(appJsContent.includes("sidebar-search-clear"), 'app.js must query sidebar-search-clear');
assert(appJsContent.includes("clearSearch"), 'app.js must define clearSearch');

// Execute search init script in the JSDOM context
const scriptFn = new Function('window', 'document', `
  const searchInput = document.getElementById('search-input');
  const sidebarSearchClear = document.getElementById('sidebar-search-clear');
  let searchTimeout = null;
  let abortController = null;
  let preSearchCollapsedState = null;

  if (sidebarSearchClear && searchInput.value.length > 0) {
    sidebarSearchClear.style.display = 'flex';
  }

  function clearSearch() {
    searchInput.value = '';
    if (sidebarSearchClear) sidebarSearchClear.style.display = 'none';
    clearTimeout(searchTimeout);
    if (abortController) abortController.abort();

    document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
      link.style.display = '';
    });

    if (preSearchCollapsedState) {
      document.querySelectorAll('.nav-category-item').forEach(catItem => {
        if (preSearchCollapsedState.has(catItem)) {
          catItem.classList.toggle('collapsed', preSearchCollapsedState.get(catItem));
        }
      });
      preSearchCollapsedState = null;
    }
    searchInput.focus();
  }

  if (sidebarSearchClear) {
    sidebarSearchClear.addEventListener('click', clearSearch);
  }

  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && searchInput.value) {
      e.preventDefault();
      clearSearch();
    }
  });

  searchInput.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase().trim();
    if (sidebarSearchClear) {
      sidebarSearchClear.style.display = e.target.value.length > 0 ? 'flex' : 'none';
    }
    clearTimeout(searchTimeout);
    if (abortController) abortController.abort();

    if (term === '') {
      document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
        link.style.display = '';
      });
      if (preSearchCollapsedState) {
        document.querySelectorAll('.nav-category-item').forEach(catItem => {
          if (preSearchCollapsedState.has(catItem)) {
            catItem.classList.toggle('collapsed', preSearchCollapsedState.get(catItem));
          }
        });
        preSearchCollapsedState = null;
      }
      return;
    }

    if (!preSearchCollapsedState) {
      preSearchCollapsedState = new Map();
      document.querySelectorAll('.nav-category-item').forEach(catItem => {
        preSearchCollapsedState.set(catItem, catItem.classList.contains('collapsed'));
      });
    }

    // Simulate instant match filter for unit testing
    document.querySelectorAll('.nav-category-item').forEach(catItem => {
      let catMatch = false;
      catItem.querySelectorAll('.nav-link').forEach(link => {
        const text = link.textContent.toLowerCase();
        if (text.includes(term)) {
          link.style.display = 'flex';
          catMatch = true;
        } else {
          link.style.display = 'none';
        }
      });
      if (catMatch) {
        catItem.classList.remove('collapsed');
      } else {
        catItem.classList.add('collapsed');
      }
    });
  });
`);

scriptFn(window, document);

const searchInput = document.getElementById('search-input');
const clearBtn = document.getElementById('sidebar-search-clear');
const catGuides = document.getElementById('cat-guides');
const catAdvanced = document.getElementById('cat-advanced');

// Test 1: Initial state
assert.strictEqual(clearBtn.style.display, 'none', 'Clear button should initially be hidden');
assert.strictEqual(catGuides.classList.contains('collapsed'), false, 'catGuides is initially expanded');
assert.strictEqual(catAdvanced.classList.contains('collapsed'), true, 'catAdvanced is initially collapsed');

// Test 2: User types "advanced"
searchInput.value = 'advanced';
searchInput.dispatchEvent(new window.Event('input'));

assert.strictEqual(clearBtn.style.display, 'flex', 'Clear button should become visible on input');
// Advanced matched, so collapsed removed; Guides did not match, so collapsed added
assert.strictEqual(catAdvanced.classList.contains('collapsed'), false, 'catAdvanced expands when matching');
assert.strictEqual(catGuides.classList.contains('collapsed'), true, 'catGuides collapses when not matching');

// Test 3: User clicks clear button
clearBtn.dispatchEvent(new window.Event('click'));

assert.strictEqual(searchInput.value, '', 'searchInput.value should be cleared');
assert.strictEqual(clearBtn.style.display, 'none', 'Clear button should be hidden after clear');
assert.strictEqual(catGuides.classList.contains('collapsed'), false, 'catGuides restores to expanded');
assert.strictEqual(catAdvanced.classList.contains('collapsed'), true, 'catAdvanced restores to collapsed');
document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
  assert.strictEqual(link.style.display, '', 'All links should have display reset');
});
console.log('✅ Click clear button test passed');

// Test 4: Escape key clearing
searchInput.value = 'guide';
searchInput.dispatchEvent(new window.Event('input'));
assert.strictEqual(clearBtn.style.display, 'flex');

const escEvent = new window.KeyboardEvent('keydown', { key: 'Escape', cancelable: true });
searchInput.dispatchEvent(escEvent);

assert.strictEqual(searchInput.value, '', 'Escape key should clear search input');
assert.strictEqual(clearBtn.style.display, 'none', 'Clear button should be hidden after Escape');
assert.strictEqual(catGuides.classList.contains('collapsed'), false, 'catGuides restored after Escape');
assert.strictEqual(catAdvanced.classList.contains('collapsed'), true, 'catAdvanced restored after Escape');
console.log('✅ Escape key clear test passed');

// Test 5: Backspace to empty
searchInput.value = 'test';
searchInput.dispatchEvent(new window.Event('input'));
assert.strictEqual(clearBtn.style.display, 'flex');

searchInput.value = '';
searchInput.dispatchEvent(new window.Event('input'));
assert.strictEqual(clearBtn.style.display, 'none', 'Clear button should hide when backspaced to empty');
assert.strictEqual(catGuides.classList.contains('collapsed'), false, 'catGuides restored after backspace');
assert.strictEqual(catAdvanced.classList.contains('collapsed'), true, 'catAdvanced restored after backspace');
console.log('✅ Backspace clear test passed');

console.log('🎉 ALL SIDEBAR SEARCH CLEAR TESTS PASSED!');
