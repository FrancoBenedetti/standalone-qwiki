const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const vm = require('vm');

console.log('--- Testing Mermaid Toolbar, Zoom Indicator & Auto-Fit ---');

const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJsContent = fs.readFileSync(appJsPath, 'utf8');

const cssPath = path.join(__dirname, '..', 'assets', 'css', 'qwiki.css');
const cssContent = fs.readFileSync(cssPath, 'utf8');

// 1. Verify CSS layout keeps toolbar out of diagram area
assert.ok(cssContent.includes('.diagram-toolbar-row'), 'qwiki.css must define .diagram-toolbar-row');
assert.ok(cssContent.includes('.mermaid-viewport'), 'qwiki.css must define .mermaid-viewport');
assert.ok(!/\.diagram-toolbar\s*\{[^}]*position:\s*absolute/.test(cssContent), '.diagram-toolbar must not be position: absolute');

// 2. Extract getSvgNaturalSize and attachDiagramToolbars from app.js
const fnStart = appJsContent.indexOf('  function getSvgNaturalSize(svg)');
const fnEnd = appJsContent.indexOf('  function openMermaidLightbox(container)');
assert.ok(fnStart !== -1 && fnEnd !== -1, 'Could not locate toolbar functions in app.js');
const toolbarCode = appJsContent.slice(fnStart, fnEnd);

const html = `
<!DOCTYPE html>
<html>
<body>
  <div class="mermaid-diagram-container" id="large-container">
    <div class="mermaid">
      <svg id="large-svg" width="1600" height="1200" viewBox="0 0 1600 1200"></svg>
    </div>
  </div>
  <div class="mermaid-diagram-container" id="small-container">
    <div class="mermaid">
      <svg id="small-svg" width="300" height="200" viewBox="0 0 300 200"></svg>
    </div>
  </div>
</body>
</html>
`;

const dom = new JSDOM(html);
const { window } = dom;
const { document } = window;

// Mock viewport/container clientWidth & window.innerHeight
Object.defineProperty(window, 'innerHeight', { value: 900, configurable: true });
Object.defineProperty(window.HTMLElement.prototype, 'clientWidth', {
  get() { return 800; },
  configurable: true
});

// Mock SVG viewBox.baseVal since JSDOM doesn't populate baseVal automatically
for (const svg of document.querySelectorAll('svg')) {
  const vb = svg.getAttribute('viewBox').split(' ').map(Number);
  Object.defineProperty(svg, 'viewBox', {
    value: { baseVal: { x: vb[0], y: vb[1], width: vb[2], height: vb[3] } },
    configurable: true
  });
}

const context = {
  window,
  document,
  _t: (_key, fallback) => fallback,
  openMermaidLightbox: () => {},
  URL,
  Blob,
  XMLSerializer: window.XMLSerializer
};
vm.createContext(context);
vm.runInContext(toolbarCode + '\nattachDiagramToolbars();', context);

// 3. Verify large diagram auto-fit & DOM structure
const largeContainer = document.getElementById('large-container');
const largeToolbarRow = largeContainer.querySelector('.diagram-toolbar-row');
const largeViewport = largeContainer.querySelector('.mermaid-viewport');
assert.ok(largeToolbarRow, 'Large container must have .diagram-toolbar-row');
assert.ok(largeViewport, 'Large container must have .mermaid-viewport');
assert.strictEqual(largeContainer.firstElementChild, largeToolbarRow, 'Toolbar row must be placed before viewport');
assert.ok(largeViewport.contains(document.getElementById('large-svg')), 'Viewport must contain the SVG');

const largeButtons = Array.from(largeToolbarRow.querySelectorAll('button'));
const largeResetBtn = largeButtons[1];
const largeZoomInBtn = largeButtons[2];
const largeZoomOutBtn = largeButtons[0];
const largeFitBtn = largeButtons[3];

// availW = 800, availH = floor(900 * 0.72) = 648; 800/1600 = 0.5, 648/1200 = 0.54 => fitScale = 0.5 (50%)
assert.strictEqual(largeResetBtn.textContent, '50%', 'Large diagram should auto-fit to 50%');
assert.strictEqual(document.getElementById('large-svg').style.width, '800px', 'Large SVG width should scale to 800px');
assert.strictEqual(document.getElementById('large-svg').style.height, '600px', 'Large SVG height should scale to 600px');
assert.strictEqual(largeFitBtn.textContent, 'Fit', 'Fit button should be shown for auto-fitted diagrams');

// 4. Verify zoom in / zoom out updates percentage indicator
largeZoomInBtn.click();
assert.strictEqual(largeResetBtn.textContent, '65%', 'Zooming in from 50% should update indicator to 65%');

largeZoomOutBtn.click();
assert.strictEqual(largeResetBtn.textContent, '50%', 'Zooming out from 65% should update indicator back to 50%');

largeResetBtn.click();
assert.strictEqual(largeResetBtn.textContent, '100%', 'Clicking percentage button should reset to 100%');
assert.strictEqual(document.getElementById('large-svg').style.width, '', 'At 100%, inline width override should be cleared');

largeFitBtn.click();
assert.strictEqual(largeResetBtn.textContent, '50%', 'Clicking Fit button should restore 50% auto-fit');

// 5. Verify small diagram stays at 100% with no Fit button
const smallContainer = document.getElementById('small-container');
const smallButtons = Array.from(smallContainer.querySelectorAll('.diagram-toolbar button'));
assert.strictEqual(smallButtons[1].textContent, '100%', 'Small diagram should remain at 100%');
assert.strictEqual(smallButtons.length, 5, 'Small diagram should not have an extra Fit button');

smallButtons[2].click();
assert.strictEqual(smallButtons[1].textContent, '120%', 'Small diagram zoom in should update indicator to 120%');
smallButtons[0].click();
assert.strictEqual(smallButtons[1].textContent, '100%', 'Small diagram zoom out should update indicator back to 100%');

console.log('✅ All Mermaid toolbar, zoom indicator, and auto-fit tests passed!');
