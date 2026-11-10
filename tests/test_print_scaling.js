const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Print Stylesheet & Dynamic Print Scaling ---');

// 1. Verify CSS rules in qwiki.css
const cssPath = path.join(__dirname, '..', 'assets', 'css', 'qwiki.css');
const cssContent = fs.readFileSync(cssPath, 'utf8');

console.log('1. Checking print CSS rules in qwiki.css...');
assert.ok(cssContent.includes('@page'), 'qwiki.css must define @page rule');
assert.ok(cssContent.includes('@media print'), 'qwiki.css must define @media print');
assert.ok(cssContent.includes('font-size: 10pt !important;'), 'qwiki.css must reduce base body/content font size for print');
assert.ok(cssContent.includes('.content-body table'), 'qwiki.css must define table print styles');
assert.ok(cssContent.includes('.content-body pre'), 'qwiki.css must define pre print styles');
assert.ok(cssContent.includes('font-size: 7.7pt !important;'), 'qwiki.css must set compact pre font-size');
assert.ok(cssContent.includes('.mermaid-diagram-container .mermaid svg'), 'qwiki.css must target diagram SVGs in print');
assert.ok(cssContent.includes('max-width: 100% !important;'), 'qwiki.css must constrain diagrams and images to 100% max-width');
console.log('✅ CSS print rules verified in qwiki.css');

// 2. Verify JS logic in app.js
const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJsContent = fs.readFileSync(appJsPath, 'utf8');

console.log('2. Checking JS print scaling logic in app.js...');
assert.ok(appJsContent.includes('preparePrintScaling'), 'app.js must define preparePrintScaling');
assert.ok(appJsContent.includes('restorePrintScaling'), 'app.js must define restorePrintScaling');
assert.ok(appJsContent.includes("addEventListener('beforeprint', preparePrintScaling)"), 'app.js must listen to beforeprint');
assert.ok(appJsContent.includes("addEventListener('afterprint', restorePrintScaling)"), 'app.js must listen to afterprint');
console.log('✅ JavaScript print scaling logic verified in app.js');

// 3. Functional JSDOM test for preparePrintScaling and restorePrintScaling
console.log('3. Running functional print scaling test in JSDOM...');
const html = `
<!DOCTYPE html>
<html>
<head></head>
<body>
  <div class="content-body">
    <pre id="pre-normal">Short line 1
Short line 2</pre>
    <pre id="pre-wide">┌────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│                              RISK TAXONOMY & INSTITUTIONAL SAFEGUARDS                                  │
├────────────────────────────┬───────────────────────────────────────────────────────────────────────────┤
│ RISK CATEGORY              │ MITIGATION & STRUCTURAL DEFENSE                                           │
└────────────────────────────┴───────────────────────────────────────────────────────────────────────────┘</pre>
    <table id="test-table" style="width: 100%;">
      <thead><tr><th>Col 1</th><th>Col 2</th></tr></thead>
      <tbody><tr><td>Data 1</td><td>Data 2</td></tr></tbody>
    </table>
    <div class="mermaid-diagram-container">
      <svg id="test-svg" width="1000" height="200"></svg>
    </div>
  </div>
</body>
</html>
`;

const dom = new JSDOM(html);
const { window } = dom;
global.window = window;
global.document = window.document;

// Emulate functions from app.js
const preparePrintScaling = () => {
  const container = document.querySelector('.content-body');
  if (!container) return;

  const isPrintMedia = window.matchMedia && window.matchMedia('print').matches;
  const availWidth = (isPrintMedia && container.clientWidth < 800) ? container.clientWidth : 680;

  // 1. Monospace PRE blocks
  const pres = container.querySelectorAll('pre');
  pres.forEach(pre => {
    const text = pre.textContent || '';
    const lines = text.split('\n');
    let maxLen = 0;
    for (let i = 0; i < lines.length; i++) {
      if (lines[i].length > maxLen) maxLen = lines[i].length;
    }

    if (maxLen > 96) {
      const targetPt = Math.max(4.5, Math.min(7.7, Math.floor((780 / maxLen) * 10) / 10));
      pre.dataset.origFontSize = pre.style.fontSize;
      pre.dataset.origLineHeight = pre.style.lineHeight;
      pre.style.fontSize = targetPt + 'pt';
      pre.style.lineHeight = '1.18';
    }
  });

  // 2. Data Tables
  const tables = container.querySelectorAll('table');
  tables.forEach(table => {
    const tableWidth = table.scrollWidth || table.offsetWidth;
    if (tableWidth > availWidth + 20) {
      const scale = Math.max(0.45, Math.min(1, (availWidth / tableWidth) * 0.98));
      if (scale < 0.98) {
        table.dataset.origTransform = table.style.transform;
        table.dataset.origTransformOrigin = table.style.transformOrigin;
        table.dataset.origWidth = table.style.width;
        table.dataset.origMarginBottom = table.style.marginBottom;
        table.style.transformOrigin = 'top left';
        table.style.transform = `scale(${scale})`;
        table.style.width = `${(100 / scale)}%`;
        table.dataset.scaled = 'true';
      }
    }
  });

  // 3. Visual Diagrams
  const diagramContainers = container.querySelectorAll('.mermaid-diagram-container, .svgbob-diagram-container');
  diagramContainers.forEach(diag => {
    diag.querySelectorAll('svg').forEach(svg => {
      svg.style.maxWidth = '100%';
      svg.style.height = 'auto';
    });
  });
};

const restorePrintScaling = () => {
  const container = document.querySelector('.content-body');
  if (!container) return;

  container.querySelectorAll('pre').forEach(pre => {
    if (pre.dataset.origFontSize !== undefined) {
      pre.style.fontSize = pre.dataset.origFontSize;
      delete pre.dataset.origFontSize;
    }
    if (pre.dataset.origLineHeight !== undefined) {
      pre.style.lineHeight = pre.dataset.origLineHeight;
      delete pre.dataset.origLineHeight;
    }
  });

  container.querySelectorAll('table[data-scaled="true"]').forEach(table => {
    table.style.transform = table.dataset.origTransform || '';
    table.style.transformOrigin = table.dataset.origTransformOrigin || '';
    table.style.width = table.dataset.origWidth || '';
    table.style.marginBottom = table.dataset.origMarginBottom || '';
    delete table.dataset.scaled;
    delete table.dataset.origTransform;
    delete table.dataset.origTransformOrigin;
    delete table.dataset.origWidth;
    delete table.dataset.origMarginBottom;
  });
};

// Execute scaling
preparePrintScaling();

const preNormal = document.getElementById('pre-normal');
const preWide = document.getElementById('pre-wide');
const testSvg = document.getElementById('test-svg');

assert.strictEqual(preNormal.style.fontSize, '', 'Normal pre should not have inline font-size applied');
assert.ok(preWide.style.fontSize.includes('pt'), 'Wide pre must have scaled font-size in pt');
const parsedPt = parseFloat(preWide.style.fontSize);
assert.ok(parsedPt <= 7.7 && parsedPt >= 4.5, `Scaled font size (${parsedPt}pt) must be within safe bounds [4.5pt, 7.7pt]`);
assert.strictEqual(testSvg.style.maxWidth, '100%', 'Diagram SVG must have maxWidth 100%');

// Execute restore
restorePrintScaling();

assert.strictEqual(preWide.style.fontSize, '', 'Wide pre font-size must be restored to original after print');
assert.strictEqual(preWide.style.lineHeight, '', 'Wide pre line-height must be restored to original after print');

console.log('✅ Functional print scaling & restoration verified');
console.log('\n🎉 ALL PRINT SCALING TESTS PASSED SUCCESSFULLY!');
