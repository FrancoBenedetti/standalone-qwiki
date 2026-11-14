const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing In-Document Search Highlighting ---');

const cssPath = path.join(__dirname, '..', 'assets', 'css', 'qwiki.css');
const cssContent = fs.readFileSync(cssPath, 'utf8');

assert(cssContent.includes('::highlight(qwiki-search)'), 'qwiki.css must define ::highlight(qwiki-search)');
assert(cssContent.includes('::highlight(qwiki-search-active)'), 'qwiki.css must define ::highlight(qwiki-search-active)');
assert(cssContent.includes('mark.qwiki-search-mark'), 'qwiki.css must define mark.qwiki-search-mark fallback');
assert(cssContent.includes('.search-highlight-bar'), 'qwiki.css must define .search-highlight-bar');
assert(cssContent.includes('@media (forced-colors: active)'), 'qwiki.css must support forced-colors mode');
console.log('✅ CSS verification passed');

const searchJsPath = path.join(__dirname, '..', 'assets', 'js', 'core', 'search.js');
const searchJsSource = fs.readFileSync(searchJsPath, 'utf8');

function createDom({ url = 'http://localhost/qwiki/getting-started/features', withCustomHighlights = false } = {}) {
  const html = `
    <!DOCTYPE html>
    <html>
    <head></head>
    <body>
      <div class="sidebar-search">
        <div class="sidebar-search-wrapper">
          <input type="text" id="search-input" class="search-input" autocomplete="off">
          <button type="button" id="sidebar-search-clear" class="sidebar-search-clear" style="display: none;"></button>
        </div>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-category-item collapsed" id="cat-gs">
          <div class="nav-document-list">
            <a href="/qwiki/getting-started/features" class="nav-link" data-doc-slug="features">Features</a>
            <a href="/qwiki/getting-started/install" class="nav-link" data-doc-slug="install">Install</a>
            <a href="https://example.com/external" target="_blank" class="nav-link" data-doc-slug="ext">External</a>
          </div>
        </div>
      </nav>
      <button type="button" id="btn-edit-markdown">Edit</button>
      <main class="app-content">
        <div class="content-body" id="content-body">
          <h1>Qwiki Features</h1>
          <p>Standalone Qwiki supports fast search and QWIKI plugins.</p>
          <div class="katex"><span>Qwiki math should be skipped</span></div>
          <div class="mermaid-diagram-container"><div class="mermaid">Qwiki diagram should be skipped</div></div>
          <script>var qwiki = "skip script";</script>
          <textarea>Qwiki textarea skipped</textarea>
          <span aria-hidden="true">Qwiki hidden icon skipped</span>
        </div>
      </main>
    </body>
    </html>
  `;

  const dom = new JSDOM(html, { url, runScripts: 'outside-only' });
  const { window } = dom;

  // Polyfill Range.getBoundingClientRect and rAF in JSDOM
  window.Range.prototype.getBoundingClientRect = () => ({ top: 100, bottom: 120, left: 10, right: 80, width: 70, height: 20 });
  window.HTMLElement.prototype.scrollIntoView = () => {};
  window.requestAnimationFrame = (cb) => { cb(); return 1; };

  let fetchCalls = [];
  window.fetch = async (fetchUrl) => {
    fetchCalls.push(fetchUrl);
    return {
      json: async () => ({ success: true, results: ['features'] })
    };
  };

  let highlightRegistry = null;
  if (withCustomHighlights) {
    highlightRegistry = new Map();
    class FakeHighlight {
      constructor(...ranges) {
        this.ranges = ranges;
        this.priority = 0;
      }
      get size() {
        return this.ranges.length;
      }
    }
    window.Highlight = FakeHighlight;
    window.CSS = { highlights: highlightRegistry };
  } else {
    delete window.Highlight;
    delete window.CSS;
  }

  // Evaluate actual search.js in JSDOM window
  window.eval(searchJsSource);

  return { dom, window, highlightRegistry, fetchCalls };
}

async function runTests() {
  // 1. CSS Custom Highlight API path
  {
    const { window, highlightRegistry } = createDom({ withCustomHighlights: true });
    const { document } = window;
    window.QwikiApp.search.init();

    const count = window.QwikiApp.search.highlightDocumentTerm('qwiki', { scrollToFirst: true });
    assert.strictEqual(count, 3, 'Should match 3 occurrences in visible prose/headings (skipping katex/mermaid/script/textarea/aria-hidden)');
    assert.strictEqual(document.querySelectorAll('mark.qwiki-search-mark').length, 0, 'Custom Highlight API must not mutate DOM with <mark>');

    const allHl = highlightRegistry.get('qwiki-search');
    const activeHl = highlightRegistry.get('qwiki-search-active');
    assert(allHl && allHl.size === 3, 'qwiki-search highlight must contain 3 ranges');
    assert.strictEqual(allHl.priority, 0, 'qwiki-search priority should be 0');
    assert(activeHl && activeHl.size === 1, 'qwiki-search-active highlight must contain 1 range');
    assert.strictEqual(activeHl.priority, 1, 'qwiki-search-active priority should be 1');

    const bar = document.querySelector('.search-highlight-bar');
    assert(bar, 'Floating highlight bar should appear');
    assert.strictEqual(bar.querySelector('.search-highlight-count').textContent, '"qwiki" • 1 / 3');

    // Next / Prev wrapping
    bar.querySelector('[aria-label="Next match"]').click();
    assert.strictEqual(bar.querySelector('.search-highlight-count').textContent, '"qwiki" • 2 / 3');
    bar.querySelector('[aria-label="Previous match"]').click();
    assert.strictEqual(bar.querySelector('.search-highlight-count').textContent, '"qwiki" • 1 / 3');
    bar.querySelector('[aria-label="Previous match"]').click();
    assert.strictEqual(bar.querySelector('.search-highlight-count').textContent, '"qwiki" • 3 / 3');

    // Edit button clears highlights
    document.getElementById('btn-edit-markdown').click();
    assert.strictEqual(highlightRegistry.has('qwiki-search'), false, 'Edit button should clear qwiki-search');
    assert.strictEqual(document.querySelector('.search-highlight-bar'), null, 'Highlight bar should be removed on clear');
    console.log('✅ CSS Custom Highlight API path passed');
  }

  // 2. <mark> fallback path
  {
    const { window } = createDom({ withCustomHighlights: false });
    const { document } = window;
    window.QwikiApp.search.init();

    const pBefore = document.querySelector('#content-body p').textContent;
    const count = window.QwikiApp.search.highlightDocumentTerm('qwiki');
    assert.strictEqual(count, 3, 'Fallback <mark> path should match 3 occurrences');

    const marks = document.querySelectorAll('mark.qwiki-search-mark');
    assert.strictEqual(marks.length, 3, 'Should wrap 3 <mark> elements');
    assert(marks[0].classList.contains('qwiki-search-mark-active'), 'First mark should be active');
    assert.strictEqual(marks[2].textContent, 'QWIKI', 'Original casing must be preserved inside <mark>');

    window.QwikiApp.search.stepMatch(1);
    assert(!marks[0].classList.contains('qwiki-search-mark-active'), 'Old active class removed');
    assert(marks[1].classList.contains('qwiki-search-mark-active'), 'Second mark becomes active');

    // Dismiss button restores clean DOM
    document.querySelector('.search-highlight-bar [aria-label="Dismiss highlights"]').click();
    assert.strictEqual(document.querySelectorAll('mark.qwiki-search-mark').length, 0, 'All <mark> elements must be unwrapped');
    assert.strictEqual(document.querySelector('#content-body p').textContent, pBefore, 'Paragraph text must remain intact after unwrapping');
    assert.strictEqual(document.querySelector('#content-body p').childNodes.length, 1, 'Text nodes should be normalized after unwrapping');
    console.log('✅ <mark> fallback path passed');
  }

  // 3. Cross-navigation sessionStorage persistence
  {
    const { window } = createDom({ url: 'http://localhost/qwiki/getting-started/install', withCustomHighlights: true });
    const { document } = window;
    window.QwikiApp.search.init();

    const searchInput = document.getElementById('search-input');
    searchInput.value = 'qwiki';

    // Clicking external link should NOT save highlight state
    document.querySelector('[data-doc-slug="ext"]').dispatchEvent(new window.MouseEvent('click', { bubbles: true, cancelable: true }));
    assert.strictEqual(window.sessionStorage.getItem('qwiki_search_highlight'), null, 'External links must not persist highlight state');

    // Clicking internal sidebar link saves term + targetPath
    const featuresLink = document.querySelector('[data-doc-slug="features"]');
    featuresLink.addEventListener('click', (e) => e.preventDefault());
    featuresLink.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));

    const saved = JSON.parse(window.sessionStorage.getItem('qwiki_search_highlight'));
    assert(saved && saved.term === 'qwiki' && saved.targetPath === '/qwiki/getting-started/features', 'Saved state must match target path');

    // Simulate landing on destination page with same sessionStorage
    const dest = createDom({ url: 'http://localhost/qwiki/getting-started/features', withCustomHighlights: true });
    dest.window.sessionStorage.setItem('qwiki_search_highlight', JSON.stringify(saved));
    dest.window.QwikiApp.search.init();

    await new Promise(resolve => setTimeout(resolve, 120));
    assert.strictEqual(dest.window.document.getElementById('search-input').value, 'qwiki', 'Destination page restores search input');
    assert(dest.fetchCalls.some(u => u.includes('api/search.php?q=qwiki')), 'Destination page restores sidebar filter');
    assert(dest.highlightRegistry.get('qwiki-search')?.size === 3, 'Destination page highlights document matches');

    // Clear search clears sessionStorage and highlights
    dest.window.QwikiApp.search.clearSearch();
    assert.strictEqual(dest.window.sessionStorage.getItem('qwiki_search_highlight'), null, 'clearSearch clears sessionStorage');
    assert.strictEqual(dest.highlightRegistry.has('qwiki-search'), false, 'clearSearch removes document highlights');

    // Mismatched path must NOT highlight and must clear stale storage
    const other = createDom({ url: 'http://localhost/qwiki/getting-started/other', withCustomHighlights: true });
    other.window.sessionStorage.setItem('qwiki_search_highlight', JSON.stringify(saved));
    other.window.QwikiApp.search.init();
    await new Promise(resolve => setTimeout(resolve, 120));
    assert.strictEqual(other.window.sessionStorage.getItem('qwiki_search_highlight'), null, 'Mismatched path clears stale storage');
    assert.strictEqual(other.highlightRegistry.has('qwiki-search'), false, 'Mismatched path does not highlight');

    console.log('✅ Cross-navigation sessionStorage handoff passed');
  }

  console.log('🎉 ALL SEARCH HIGHLIGHT TESTS PASSED!');
}

runTests().catch(err => {
  console.error(err);
  process.exit(1);
});
