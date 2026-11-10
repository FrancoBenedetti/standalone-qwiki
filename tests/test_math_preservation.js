/**
 * Test Suite: Markdown LaTeX Math Preservation & Mode Protection
 * 
 * Verifies that Markdown documents containing LaTeX math expressions
 * ($...$, $$...$$, \(...\), \[...\], or math macros) are detected and protected
 * from Toast UI WYSIWYG escaping corruption.
 */

const assert = require('assert');

// The exact detection function from assets/js/app.js
function containsLatexMath(md) {
  if (!md || typeof md !== 'string') return false;

  // Strip fenced code blocks (``` ... ``` and ~~~ ... ~~~) and inline code
  const stripped = md
    .replace(/^```[\s\S]*?^```/gm, '')
    .replace(/^~~~[\s\S]*?^~~~/gm, '')
    .replace(/`[^`\n]+`/g, '');

  // 1. Check for block display math: $$ ... $$
  if (/\$\$[\s\S]+?\$\$/.test(stripped)) return true;

  // 2. Check for LaTeX display delimiters: \[ ... \] or \( ... \)
  if (/\\\[[\s\S]+?\\\]/.test(stripped) || /\\\([\s\S]+?\\\)/.test(stripped)) return true;

  // 3. Check for inline math $...$ (ignoring pure numbers/currency like $50, $1,000, $4.99)
  const inlineMathRegex = /\$([^\$\n\s](?:[^\$\n]*?[^\$\n\s])?)\$/g;
  let match;
  while ((match = inlineMathRegex.exec(stripped)) !== null) {
    const content = match[1];
    if (!/^\d+(?:,\d{3})*(?:\.\d+)?$/.test(content)) {
      return true;
    }
  }

  // 4. Check for LaTeX math commands: e.g. \frac, \sum, \int, \sqrt, \Delta, \alpha, \approx, etc.
  if (/\\(?:frac|sum|int|sqrt|alpha|beta|gamma|delta|epsilon|theta|lambda|mu|pi|sigma|phi|omega|Delta|Sigma|Omega|approx|times|div|le|ge|ne|pm|infty|to|rightarrow|leftarrow|partial|nabla|cdot|left|right|text|mathbb|mathbf|mathrm)\b/i.test(stripped)) {
    return true;
  }

  return false;
}

console.log('Running Markdown LaTeX Math Preservation Tests...\n');

// 1. Test Pure Markdown & Currency (Should return false)
const safeCases = [
  '# Heading 1\n\nSome paragraph with **bold** and *italic* text.',
  'Item costs $50 now and $100 later.',
  'Price is $4.99 or $1,250.00 for wholesale.',
  '```markdown\nHere is math in code block: $$E = mc^2$$\n```',
  'Inline code with math: `$$x = 1$$` and `$y$`',
  'Standard bullet list with * item 1 and * item 2.'
];

safeCases.forEach((md, i) => {
  assert.strictEqual(
    containsLatexMath(md),
    false,
    `Failed: Safe case ${i + 1} should not be detected as LaTeX math:\n${md}`
  );
});
console.log(`✅ Safe / currency cases properly identified (${safeCases.length}/${safeCases.length})`);

// 2. Test Real LaTeX Math Cases (Should return true)
const mathCases = [
  'Single line display math: $$E = mc^2$$',
  'Multi-line display math:\n$$\nR_{base} = \\frac{1}{3} \\sum_{i=1}^{3} (Y_i \\times P_i)\n$$',
  'Inline variable math: The value is $R_{base}$ here.',
  'Subscript notation: Formula $Y_i$ delivery certificates.',
  'LaTeX macro in prose: From A \\to B with error \\approx 0.1',
  'LaTeX delimiter: \\[ x^2 + y^2 = z^2 \\]',
  'LaTeX delimiter inline: \\( a + b = c \\)',
  'Complex equation: $$\\text{Audited Value Uplift } (\\Delta V) = (Y_{actual} - Y_{base}) \\times P_{actual}$$'
];

mathCases.forEach((md, i) => {
  assert.strictEqual(
    containsLatexMath(md),
    true,
    `Failed: Math case ${i + 1} should be detected as LaTeX math:\n${md}`
  );
});
console.log(`✅ LaTeX math cases properly detected for editor protection (${mathCases.length}/${mathCases.length})`);

console.log('\nALL MATH PRESERVATION UNIT TESTS PASSED SUCCESSFULLY! 🎉\n');
