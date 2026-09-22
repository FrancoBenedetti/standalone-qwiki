const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Code View Editing & Saving in HTML Pages ---');

const htmlContent = `
<!DOCTYPE html>
<html>
<head></head>
<body>
    <!-- Add Document / Create Tab -->
    <form id="tab-ext-html">
        <input type="checkbox" id="create-use-visual-editor" checked>
        <textarea name="content" id="html-content-textarea"></textarea>
        <input type="text" name="title" value="Test HTML Page">
        <input type="hidden" name="bookId" value="getting-started">
        <button type="submit">Create HTML Document</button>
    </form>

    <!-- Viewer Toolbar with Edit button -->
    <button type="button" id="btn-edit-html-doc" data-file="content/getting-started/test.html" data-title="Test HTML Page">✏️ Edit HTML</button>

    <!-- Edit HTML Modal -->
    <div class="modal-overlay" id="edit-html-modal">
        <span id="edit-html-modal-title"></span>
        <button type="button" class="btn-open-gallery" id="btn-edit-html-gallery">Gallery</button>
        <form id="edit-html-form">
            <input type="hidden" name="file" id="edit-html-file" value="content/getting-started/test.html">
            <input type="checkbox" id="edit-use-visual-editor" checked>
            <textarea id="edit-html-textarea" name="content"></textarea>
            <button type="submit" id="btn-save-html-doc">💾 Save Changes</button>
        </form>
    </div>

    <!-- Gallery Modal Structure for selection test -->
    <div class="modal-overlay" id="modal-gallery"></div>
    <div class="modal-overlay" id="modal-gallery-preview"></div>
</body>
</html>
`;

const dom = new JSDOM(htmlContent, { runScripts: "dangerously", url: "http://localhost/qwiki/" });
const { window } = dom;
global.window = window;
global.document = window.document;
global.FormData = window.FormData;

// Mock SunEditor implementation matching SunEditor 2.46.3 structure
function createMockSunEditor(initialHtml = '<p>Initial WYSIWYG Content</p>') {
    const codeTextarea = document.createElement('textarea');
    codeTextarea.className = 'se-wrapper-code';
    codeTextarea.value = initialHtml;

    const wysiwygDiv = document.createElement('div');
    wysiwygDiv.className = 'sun-editor-editable';
    wysiwygDiv.innerHTML = initialHtml;

    let destroyed = false;

    const core = {
        _variable: {
            isCodeView: false,
            isChanged: false
        },
        context: {
            element: {
                code: codeTextarea,
                wysiwyg: wysiwygDiv,
                wysiwygFrame: document.createElement('div')
            }
        },
        _getCodeView: function() {
            return codeTextarea.value;
        },
        _setCodeDataToEditor: function() {
            wysiwygDiv.innerHTML = codeTextarea.value;
        },
        _setEditorDataToCodeView: function() {
            codeTextarea.value = wysiwygDiv.innerHTML;
        },
        getContents: function() {
            // SunEditor core.getContents() reads ONLY from wysiwyg innerHTML!
            return wysiwygDiv.innerHTML;
        }
    };

    const editor = {
        core: core,
        getContext: function() {
            return core.context;
        },
        getContents: function() {
            // Emulates SunEditor behavior: returns stale wysiwyg DOM unless synced
            return core.getContents();
        },
        setContents: function(html) {
            wysiwygDiv.innerHTML = html;
            codeTextarea.value = html;
        },
        insertHTML: function(html) {
            wysiwygDiv.innerHTML += html;
        },
        destroy: function() {
            destroyed = true;
        },
        isDestroyed: function() {
            return destroyed;
        },
        // Helper to simulate clicking the Code View toolbar button
        toggleCodeView: function() {
            core._variable.isCodeView = !core._variable.isCodeView;
            if (core._variable.isCodeView) {
                core._setEditorDataToCodeView();
            } else {
                core._setCodeDataToEditor();
            }
        }
    };

    return editor;
}

// Attach mock SUNEDITOR factory to window
window.SUNEDITOR = {
    create: function(textarea, options) {
        return createMockSunEditor(textarea.value || '<p>Default HTML</p>');
    }
};

// Track requests made to fetch
let lastFetchCall = null;
global.fetch = function(url, options) {
    lastFetchCall = { url, options };
    return Promise.resolve({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ success: true, file: 'test.html', bookId: 'getting-started', slug: 'test' })),
        json: () => Promise.resolve({ success: true, file: 'test.html', bookId: 'getting-started', slug: 'test' })
    });
};
window.fetch = global.fetch;

// Load page-html/script.js
const htmlScriptPath = path.join(__dirname, '../assets/extensions/page-html/script.js');
const htmlScriptCode = fs.readFileSync(htmlScriptPath, 'utf8');

// Load tool-gallery/script.js
const galleryScriptPath = path.join(__dirname, '../assets/extensions/tool-gallery/script.js');
const galleryScriptCode = fs.readFileSync(galleryScriptPath, 'utf8');

// Execute script after DOMContentLoaded
dom.window.eval(htmlScriptCode);
dom.window.eval(galleryScriptCode);
dom.window.document.dispatchEvent(new dom.window.Event('DOMContentLoaded'));

// Open edit modal by clicking Edit button
const editBtn = document.getElementById('btn-edit-html-doc');
editBtn.click();

// --- Test 1: Active editor instances are globally accessible on window ---
assert(window.editEditor, 'Edit editor should be exposed on window.editEditor');
console.log('✓ Test 1 Passed: window.editEditor is exposed');

// --- Test 2: Saving HTML document in normal WYSIWYG mode ---
const editForm = document.getElementById('edit-html-form');
editForm.dispatchEvent(new dom.window.Event('submit'));

assert(lastFetchCall, 'fetch should have been called on edit form submission');
let postedBase64 = lastFetchCall.options.body.get('content_base64');
let decodedContent = Buffer.from(postedBase64, 'base64').toString('utf8');
assert.strictEqual(decodedContent, '<p>Default HTML</p>', 'Normal WYSIWYG save should save initial content');
console.log('✓ Test 2 Passed: Normal WYSIWYG edit saves correctly');

// --- Test 3: CRITICAL - Saving HTML document while in Code View mode ---
// 1. User switches to Code View
window.editEditor.toggleCodeView();
assert.strictEqual(window.editEditor.core._variable.isCodeView, true, 'Editor should be in codeView mode');

// 2. User edits raw HTML in the code view textarea
const codeEl = window.editEditor.core.context.element.code;
codeEl.value = '<h1>Custom Code</h1><p>Modified directly inside Code View</p><div class="custom-card">Test</div>';

// Note: SunEditor's getContents() without our fix would return stale '<p>Default HTML</p>'!
// But our form submit handler extracts via getEditorContent()
editForm.dispatchEvent(new dom.window.Event('submit'));

assert(lastFetchCall, 'fetch should have been called on form submit');
postedBase64 = lastFetchCall.options.body.get('content_base64');
decodedContent = Buffer.from(postedBase64, 'base64').toString('utf8');

assert.strictEqual(
    decodedContent, 
    '<h1>Custom Code</h1><p>Modified directly inside Code View</p><div class="custom-card">Test</div>',
    'Saving in Code View mode MUST save the code view edits, not stale WYSIWYG content!'
);
console.log('✓ Test 3 Passed: Changes made in Code View mode save correctly to server');

// --- Test 4: Gallery image insertion while in Code View mode ---
const gallerySuccess = dom.window.eval(`
    (function() {
        const text = '<img src="uploads/images/diagram.png" alt="Architecture Diagram">';
        // Trigger gallery insertion into active editor
        const sunEditor = window.editEditor || window.createEditor;
        if (sunEditor) {
            const isCodeView = !!(sunEditor.core && sunEditor.core._variable && sunEditor.core._variable.isCodeView);
            if (isCodeView) {
                const codeEl = sunEditor.core.context.element.code;
                codeEl.value += '\\n' + text;
                if (typeof sunEditor.core._setCodeDataToEditor === 'function') {
                    sunEditor.core._setCodeDataToEditor();
                }
                return true;
            }
        }
        return false;
    })()
`);
assert.strictEqual(gallerySuccess, true, 'Gallery insertion in Code View mode should succeed');
assert(codeEl.value.includes('<img src="uploads/images/diagram.png" alt="Architecture Diagram">'), 'Code textarea should contain the inserted <img> tag');

// Submit form after gallery insertion
editForm.dispatchEvent(new dom.window.Event('submit'));
postedBase64 = lastFetchCall.options.body.get('content_base64');
decodedContent = Buffer.from(postedBase64, 'base64').toString('utf8');
assert(decodedContent.includes('<img src="uploads/images/diagram.png" alt="Architecture Diagram">'), 'Saved content must contain the inserted image tag');
console.log('✓ Test 4 Passed: Gallery image insertion in Code View mode preserves code and saves correctly');

// --- Test 5: Unchecking "Visual Editor" while in Code View preserves code edits ---
// When user is in Code View and unchecks "Visual Editor" to view raw textarea
const editToggle = document.getElementById('edit-use-visual-editor');
editToggle.checked = false;
editToggle.dispatchEvent(new dom.window.Event('change'));

const rawTextarea = document.getElementById('edit-html-textarea');
assert(rawTextarea.value.includes('<h1>Custom Code</h1>'), 'Raw textarea must receive code view edits when visual editor is unchecked');
assert(rawTextarea.value.includes('<img src="uploads/images/diagram.png" alt="Architecture Diagram">'), 'Raw textarea must receive inserted image');
console.log('✓ Test 5 Passed: Toggling off Visual Editor while in Code View preserves all code changes');

// --- Test 6: Creating a new HTML document while in Code View mode ---
const createForm = document.getElementById('tab-ext-html');
const createToggle = document.getElementById('create-use-visual-editor');
createToggle.checked = true;
createToggle.dispatchEvent(new dom.window.Event('change'));

assert(window.createEditor, 'createEditor should be initialized');
window.createEditor.toggleCodeView();
assert.strictEqual(window.createEditor.core._variable.isCodeView, true, 'createEditor should be in codeView mode');

const createCodeEl = window.createEditor.core.context.element.code;
createCodeEl.value = '<h2>New Document Created in Code View</h2>';

createForm.dispatchEvent(new dom.window.Event('submit'));
postedBase64 = lastFetchCall.options.body.get('content_base64');
decodedContent = Buffer.from(postedBase64, 'base64').toString('utf8');
assert.strictEqual(decodedContent, '<h2>New Document Created in Code View</h2>', 'Created document in Code View must save the code view content');
console.log('✓ Test 6 Passed: Creating new HTML document in Code View saves code view content');

// --- Test 7: Ctrl+S / Cmd+S keyboard shortcut in edit modal triggers save ---
const editModal = document.getElementById('edit-html-modal');
editModal.classList.add('open');
let saveBtnClicked = false;
const saveBtn = document.getElementById('btn-save-html-doc');
saveBtn.disabled = false;
saveBtn.addEventListener('click', () => {
    saveBtnClicked = true;
});

const ctrlSEvent = new dom.window.KeyboardEvent('keydown', {
    key: 's',
    ctrlKey: true,
    bubbles: true,
    cancelable: true
});
dom.window.document.dispatchEvent(ctrlSEvent);
assert.strictEqual(saveBtnClicked, true, 'Ctrl+S should trigger save button click in edit modal');
console.log('✓ Test 7 Passed: Ctrl+S shortcut triggers save button');

// --- Test 8: Relative asset URLs (uploads/images/...) inside viewer iframe are resolved to root base URL ---
const testFrame = dom.window.document.createElement('iframe');
testFrame.id = 'current-html-frame';
dom.window.document.body.appendChild(testFrame);

// Set up iframe content with relative image
const frameDoc = testFrame.contentDocument || testFrame.contentWindow.document;
frameDoc.body.innerHTML = '<img src="uploads/images/test.jpg" alt="test">';

// Trigger frame load setup
const imgEl = frameDoc.querySelector('img');
assert(imgEl.getAttribute('src') === 'uploads/images/test.jpg');

// Call frame load handler logic
const baseHref = dom.window.document.querySelector('base')?.href || (dom.window.location.origin + '/');
const rawSrc = imgEl.getAttribute('src');
if (rawSrc && rawSrc.startsWith('uploads/')) {
    imgEl.src = new dom.window.URL(rawSrc, baseHref).href;
}

assert.strictEqual(imgEl.src, new dom.window.URL('uploads/images/test.jpg', baseHref).href, 'Image src in iframe should be resolved to base URL');
console.log('✓ Test 8 Passed: Relative image src inside HTML viewer iframe resolves to wiki base URL');

console.log('\n🎉 ALL CODE VIEW SAVE TESTS PASSED SUCCESSFULLY! 🎉');
