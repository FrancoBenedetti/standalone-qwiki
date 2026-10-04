// tests/test_mermaid_latex.js
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Mermaid & KaTeX Symbol Normalization ---');

const appJsPath = path.join(__dirname, '..', 'assets', 'js', 'app.js');
const appJsContent = fs.readFileSync(appJsPath, 'utf8');

// 1. Verify app.js definitions
console.log('1. Checking definitions in app.js...');
assert.ok(appJsContent.includes('LATEX_SYMBOL_MAP'), 'app.js must declare LATEX_SYMBOL_MAP');
assert.ok(appJsContent.includes('normalizeLatexSymbols'), 'app.js must define normalizeLatexSymbols');
assert.ok(appJsContent.includes('renderMathExpressions'), 'app.js must define renderMathExpressions');
assert.ok(appJsContent.includes('window.renderMathExpressions'), 'app.js must expose renderMathExpressions globally');
assert.ok(appJsContent.includes('window.normalizeLatexSymbols'), 'app.js must expose normalizeLatexSymbols globally');
console.log('✅ Functions verified in app.js');

// 2. Functional test in JSDOM
console.log('2. Running functional tests in JSDOM environment...');
const dom = new JSDOM(`<!DOCTYPE html><html><body><div id="content-body"></div></body></html>`, {
  url: 'http://localhost',
  runScripts: "outside-only"
});

// Mock browser objects
dom.window.fetch = () => Promise.resolve({ ok: true, arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)) });
dom.window.WebAssembly = { instantiate: () => Promise.resolve({ instance: { exports: {} } }) };

// Run app.js inside DOM context and fire DOMContentLoaded
dom.window.eval(appJsContent);
dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

const normalize = dom.window.normalizeLatexSymbols;
assert.strictEqual(typeof normalize, 'function', 'normalizeLatexSymbols must be a function on window');

// Test 2.1: Flowchart with $to$ edge label
const input1 = `flowchart LR
    A --> |$to$| B`;
const output1 = normalize(input1);
assert.strictEqual(output1, `flowchart LR\n    A --> |→| B`, '$to$ in edge label must convert to →');

// Test 2.2: Flowchart with $\to$ and \to
const input2 = `flowchart TD
    A["x $to$ y"] --> B["x \\to y"]`;
const output2 = normalize(input2);
assert.strictEqual(output2, `flowchart TD\n    A["x → y"] --> B["x → y"]`, 'both $to$ and \\to in node labels must convert to →');

// Test 2.3: Preservation of $$...$$ math blocks
const input3 = `graph LR
    A["$$\\frac{a}{b}$$"] --> |$to$| B["$$\\sqrt{x}$$"]`;
const output3 = normalize(input3);
assert.strictEqual(output3, `graph LR\n    A["$$\\frac{a}{b}$$"] --> |→| B["$$\\sqrt{x}$$"]`, '$$...$$ math blocks must remain completely intact while $to$ becomes →');

// Test 2.4: Greek letters and comparison symbols
const input4 = `graph TD
    Node1["Status: $approx$ 99%"]
    Node2["Angle: \\theta \\ge 90\\degree"]
    Node3["Alpha: $alpha$, Delta: $Delta$"]`;
const output4 = normalize(input4);
assert.ok(output4.includes('Status: ≈ 99%'), '$approx$ converted to ≈');
assert.ok(output4.includes('Angle: θ ≥ 90°'), '\\theta \\ge 90\\degree converted to θ ≥ 90°');
assert.ok(output4.includes('Alpha: α, Delta: Δ'), '$alpha$ and $Delta$ converted to α and Δ');

console.log('✅ All Mermaid & LaTeX symbol normalization tests passed successfully!');
