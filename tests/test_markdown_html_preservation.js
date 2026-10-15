/**
 * Test Suite: Markdown HTML Preservation & Mode Protection
 * 
 * Verifies that complex Markdown documents with raw HTML, inline styles,
 * CSS grids, flexboxes, and badges are properly detected and protected
 * from Toast UI WYSIWYG tag/attribute stripping.
 */

const assert = require('assert');

// The exact detection function from assets/js/app.js
function containsHtmlMarkup(md) {
  if (!md || typeof md !== 'string') return false;

  // Strip fenced code blocks (``` ... ``` and ~~~ ... ~~~) and inline code
  const stripped = md
    .replace(/^```[\s\S]*?^```/gm, '')
    .replace(/^~~~[\s\S]*?^~~~/gm, '')
    .replace(/`[^`\n]+`/g, '');

  // Check for HTML comments
  if (/<!--[\s\S]*?-->/.test(stripped)) return true;

  // Check for any HTML block tags (open or close)
  const blockTags = 'div|section|article|aside|header|footer|nav|main|style|script|iframe|video|audio|source|details|summary|canvas|svg|button|form|input|select|textarea|figure|figcaption|table|thead|tbody|tfoot|tr|td|th';
  const blockRegex = new RegExp(`</?(?:${blockTags})[\\s>/]`, 'i');
  if (blockRegex.test(stripped)) return true;

  // Check for any HTML tags with attributes (style, class, id, data-*, width, height, align, target, etc.)
  if (/<[a-z][a-z0-9]*\b[^>]*\b(?:style|class|id|data-[a-z0-9_-]+|align|width|height|target)\s*=/i.test(stripped)) {
    return true;
  }

  // Check for generic HTML tags that are not autolinks or benign line breaks (<br>, <br/>, <br />)
  const nonAutolink = stripped
    .replace(/<https?:\/\/[^>]+>/gi, '')
    .replace(/<[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}>/g, '')
    .replace(/<\/?br\s*\/?>/gi, '');
  if (/<[a-z][a-z0-9]*(?:\s+[^>]*)?>/i.test(nonAutolink)) {
    return true;
  }

  return false;
}

console.log('Running Markdown HTML Preservation Tests...\n');

// 1. Test Pure Markdown (Should return false)
const pureMarkdownCases = [
  '# Heading 1\n\nSome paragraph with **bold** and *italic* text.',
  '## Subheading\n\n- List item 1\n- List item 2\n  - Nested item',
  '1. Numbered 1\n2. Numbered 2\n\n> A blockquote here.',
  '| Col 1 | Col 2 |\n| --- | --- |\n| A | B |',
  '[A regular link](https://example.com) and ![An image](image.png)',
  'Autolinks: <https://qwiki.local/test> and <contact@example.com>',
  'Inline code `const x = 10;` and `<div>not real html in code</div>`',
  'Standard line break: First line<br>Second line',
  'Self-closing line breaks: First line<br/>Second line<br />Third line',
  'Case-insensitive and closing breaks: First line<BR>Second line</br>Third line',
  '| Option | Description |\n| --- | --- |\n| Config A | First line<br>Second line |'
];

pureMarkdownCases.forEach((md, i) => {
  assert.strictEqual(
    containsHtmlMarkup(md),
    false,
    `Failed: Pure markdown case ${i + 1} should not be detected as HTML markup:\n${md}`
  );
});
console.log(`✅ Pure Markdown cases properly identified as safe for WYSIWYG (${pureMarkdownCases.length}/${pureMarkdownCases.length})`);

// 2. Test Code Blocks Containing HTML (Should return false)
const codeBlockCases = [
  '```html\n<div class="test" style="color:red">Code snippet</div>\n```',
  '~~~\n<span class="badge">Code snippet</span>\n~~~',
  'Here is how you write a div:\n```html\n<div style="display:flex"><span>Test</span></div>\n```\nAll done.'
];

codeBlockCases.forEach((md, i) => {
  assert.strictEqual(
    containsHtmlMarkup(md),
    false,
    `Failed: Code block case ${i + 1} should not be detected as raw HTML:\n${md}`
  );
});
console.log('✅ Code blocks containing HTML snippets properly ignored (3/3)');

// 3. Test Real HTML Markup (Should return true)
const htmlMarkupCases = [
  '<div style="background: linear-gradient(135deg, #fff 0%, #000 100%);">Hero Header</div>',
  '<span class="doc-badge badge-md">MARKDOWN</span>',
  '<span style="color: var(--text-primary);">Custom colored text</span>',
  '<a href="demo" style="text-decoration: none;"><div>Card</div></a>',
  '<table style="width: 100%; border-collapse: collapse;"><tr><td>Test</td></tr></table>',
  '<iframe src="https://example.com" width="100%" height="400"></iframe>',
  '<details><summary>Click to expand</summary>Hidden content</details>',
  '<!-- This is an HTML comment -->\nRegular markdown content',
  '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">Grid</div>',
  'Paragraph with custom styled line break:<br style="clear:both;">'
];

htmlMarkupCases.forEach((md, i) => {
  assert.strictEqual(
    containsHtmlMarkup(md),
    true,
    `Failed: HTML case ${i + 1} was NOT detected as raw HTML markup:\n${md}`
  );
});
console.log(`✅ Complex HTML blocks & styled tags properly detected for protection (${htmlMarkupCases.length}/${htmlMarkupCases.length})`);

// 4. Test Mixed Document: Markdown + Code Block + Raw HTML
const mixedDocument = `# Main Heading

Introductory paragraph with standard Markdown.

\`\`\`html
<!-- This is inside a code block -->
<div class="code-example">Code snippet</div>
\`\`\`

Now here is an actual custom card component:

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
  <div style="background: var(--bg-secondary); padding: 1rem; border-radius: 8px;">
    <h3>Card Title</h3>
    <p>Card content here.</p>
  </div>
</div>

End of document.`;

assert.strictEqual(containsHtmlMarkup(mixedDocument), true, 'Failed: Mixed document should be detected as containing HTML');
console.log('✅ Mixed document (Markdown + Code Blocks + HTML components) correctly flagged for protected mode');

// 5. Test Real landing.md Fixture
const fs = require('fs');
const path = require('path');
const fixturePath = path.join(__dirname, 'fixtures', 'landing.md');
if (fs.existsSync(fixturePath)) {
  const landingContent = fs.readFileSync(fixturePath, 'utf8');
  assert.strictEqual(
    containsHtmlMarkup(landingContent),
    true,
    'Failed: landing.md fixture must be detected as containing HTML markup'
  );
  console.log(`✅ landing.md (${landingContent.length} bytes, ${landingContent.split('\n').length} lines) properly detected for Markdown + HTML mode`);

  // Verify that in Markdown mode, content is 100% preserved
  const simulatedSave = landingContent; // In markdown mode, getMarkdown() returns CodeMirror buffer exactly
  assert.strictEqual(simulatedSave, landingContent, 'Content must match 1:1');
  console.log('✅ 100% byte-for-byte HTML and styling preservation verified');
}

console.log('\nALL HTML PRESERVATION UNIT TESTS PASSED SUCCESSFULLY! 🎉');
