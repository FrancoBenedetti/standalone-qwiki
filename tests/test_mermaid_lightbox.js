const fs = require('fs');
const assert = require('assert');
const path = require('path');

const appJs = fs.readFileSync(path.join(__dirname, '../assets/js/app.js'), 'utf8');
const qwikiCss = fs.readFileSync(path.join(__dirname, '../assets/css/qwiki.css'), 'utf8');
const agentsMd = fs.readFileSync(path.join(__dirname, '../AGENTS.md'), 'utf8');

// 1. Check app.js implements openMermaidLightbox & attachDiagramToolbars
assert.ok(appJs.includes('openMermaidLightbox'), 'app.js must define openMermaidLightbox');
assert.ok(appJs.includes('attachDiagramToolbars'), 'app.js must define attachDiagramToolbars');
assert.ok(appJs.includes('diagram-toolbar'), 'app.js must create .diagram-toolbar');
assert.ok(appJs.includes('mermaid-lightbox-modal'), 'app.js must create #mermaid-lightbox-modal');
assert.ok(appJs.includes('Escape'), 'app.js must handle Escape key dismissal for lightbox');

// 2. Check qwiki.css styles toolbar, lightbox, and @media print rules
assert.ok(qwikiCss.includes('.diagram-toolbar'), 'qwiki.css must style .diagram-toolbar');
assert.ok(qwikiCss.includes('#mermaid-lightbox-modal'), 'qwiki.css must style #mermaid-lightbox-modal');
assert.ok(qwikiCss.includes('page-break-inside: avoid'), 'qwiki.css must include page-break-inside: avoid for print diagrams');

// 3. Check AGENTS.md includes Mermaid Screen & PDF Authoring Directives
assert.ok(agentsMd.includes('flowchart TD'), 'AGENTS.md must document flowchart TD preference');
assert.ok(agentsMd.includes('<br/>'), 'AGENTS.md must document <br/> label wrapping');

console.log('PASS: Mermaid lightbox, print CSS, and AI directives tests passed');
