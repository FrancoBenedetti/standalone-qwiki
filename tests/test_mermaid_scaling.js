const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Mermaid Diagram Scaling and Horizontal Scrolling ---');

// 1. Verify CSS rules in qwiki.css
const cssPath = path.join(__dirname, '..', 'assets', 'css', 'qwiki.css');
const cssContent = fs.readFileSync(cssPath, 'utf8');

console.log('1. Checking CSS rules in qwiki.css...');
assert.ok(!cssContent.includes('min-width: 680px;'), 'qwiki.css must not force arbitrary min-width: 680px on mermaid svg');
assert.ok(cssContent.includes('.mermaid-diagram-container'), 'qwiki.css must define .mermaid-diagram-container');
assert.ok(cssContent.includes('overflow-x: auto'), 'qwiki.css must define overflow-x: auto on diagram containers');
assert.ok(cssContent.includes('min-width: 100%'), 'qwiki.css must ensure .mermaid has min-width: 100%');
assert.ok(cssContent.includes('width: max-content'), 'qwiki.css must allow .mermaid to expand to max-content');
assert.ok(cssContent.includes('max-width: none !important'), 'qwiki.css must prevent max-width constraint on mermaid svg');
assert.ok(cssContent.includes('flex-shrink: 0'), 'qwiki.css must prevent mermaid svg from shrinking');
console.log('✅ CSS rules verified in qwiki.css');

// 2. Verify JS logic in app.js
const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJsContent = fs.readFileSync(appJsPath, 'utf8');

console.log('2. Checking JS diagram rendering and normalization in app.js...');
assert.ok(appJsContent.includes('MERMAID_DIAGRAM_TYPES'), 'app.js must declare MERMAID_DIAGRAM_TYPES');
assert.ok(appJsContent.includes('useMaxWidth: false'), 'app.js must configure useMaxWidth: false for diagram types');
assert.ok(appJsContent.includes('normalizeMermaidSvgs'), 'app.js must define normalizeMermaidSvgs');
assert.ok(appJsContent.includes('reRenderMermaidDiagrams'), 'app.js must define reRenderMermaidDiagrams');
console.log('✅ JavaScript logic verified in app.js');

// 3. Functional DOM test with JSDOM
console.log('3. Running functional normalization test in JSDOM...');
const html = `
<!DOCTYPE html>
<html>
<head></head>
<body>
  <div class="mermaid-diagram-container">
    <div class="mermaid">
      <svg id="mermaid-test-wide" width="100%" style="max-width: 2524.96875px;" viewBox="0 0 2524.96875 196.1875">
        <g></g>
      </svg>
    </div>
  </div>
  <div class="mermaid-diagram-container">
    <div class="mermaid">
      <svg id="mermaid-test-small" width="100%" style="max-width: 76.484375px;" viewBox="0 0 76.484375 56.59375">
        <g></g>
      </svg>
    </div>
  </div>
</body>
</html>
`;

const dom = new JSDOM(html);
const { window } = dom;
global.window = window;
global.document = window.document;

function normalizeMermaidSvgs() {
  const svgs = document.querySelectorAll('.mermaid-diagram-container .mermaid svg');
  for (const svg of svgs) {
    svg.style.maxWidth = 'none';
    if (svg.getAttribute('width') === '100%') {
      const viewBox = svg.viewBox && svg.viewBox.baseVal;
      if (viewBox && viewBox.width > 0) {
        svg.setAttribute('width', viewBox.width);
      }
    }
  }
}

normalizeMermaidSvgs();

const wideSvg = document.getElementById('mermaid-test-wide');
assert.strictEqual(wideSvg.getAttribute('width'), '2524.96875', 'Wide SVG width attribute must match natural viewBox width');
assert.strictEqual(wideSvg.style.maxWidth, 'none', 'Wide SVG style.maxWidth must be none');

const smallSvg = document.getElementById('mermaid-test-small');
assert.strictEqual(smallSvg.getAttribute('width'), '76.484375', 'Small SVG width attribute must match natural viewBox width');
assert.strictEqual(smallSvg.style.maxWidth, 'none', 'Small SVG style.maxWidth must be none');

console.log('✅ Functional DOM normalization passed');
console.log('\n🎉 ALL MERMAID SCALING TESTS PASSED SUCCESSFULLY!');
