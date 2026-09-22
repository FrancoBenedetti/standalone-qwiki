const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('--- Testing Gallery Editor & Image Selection ---');

const htmlContent = `
<!DOCTYPE html>
<html>
<head></head>
<body>
    <!-- Edit Actions with Image Gallery Button -->
    <div id="edit-actions" style="display: none;">
        <button type="button" id="btn-editor-gallery">Image Gallery</button>
        <button id="btn-cancel-edit">Cancel</button>
        <button id="btn-save-inline-markdown">Save Changes</button>
    </div>

    <!-- Inline Editor Container -->
    <div id="inline-editor-container" style="display: none;"></div>
    <textarea id="raw-markdown-data"></textarea>

    <!-- Main Gallery Modal -->
    <div class="modal-overlay" id="modal-gallery" style="z-index: 1060;">
        <div class="modal-card gallery-modal-card">
            <div class="modal-header">
                <h3>🖼️ Image Gallery</h3>
                <span id="gallery-count-badge">0 images</span>
                <button class="modal-close" data-close="modal-gallery">&times;</button>
            </div>
            <div id="gallery-editor-banner" class="gallery-editor-banner" style="display: none;">
                <div class="gallery-editor-banner-text">
                    <span class="gallery-editor-banner-icon">📝</span>
                    <span>Article Editor Active</span>
                </div>
                <span class="gallery-mode-badge">SELECT MODE</span>
            </div>
            <div class="gallery-toolbar">
                <input type="text" id="gallery-search-input">
                <button type="button" id="gallery-search-clear" style="display: none;">&times;</button>
                <input type="file" id="gallery-file-input" style="display: none;">
                <button type="button" id="btn-gallery-upload-trigger">Upload Image</button>
                <button type="button" id="btn-gallery-refresh">Refresh</button>
            </div>
            <div id="gallery-dropzone" class="gallery-dropzone"></div>
            <div class="gallery-body" id="gallery-body">
                <div id="gallery-loading" style="display: none;"></div>
                <div id="gallery-empty" style="display: none;"></div>
                <div id="gallery-grid" class="gallery-grid" style="display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Gallery Delete Modal -->
    <div class="modal-overlay" id="modal-gallery-delete" style="z-index: 1160;">
        <button class="modal-close" id="btn-gallery-delete-close">&times;</button>
        <button id="btn-gallery-delete-cancel">Cancel</button>
        <button id="btn-gallery-delete-confirm">Delete</button>
        <img id="gallery-delete-thumb" src="">
        <span id="gallery-delete-filename"></span>
        <span id="gallery-delete-meta-info"></span>
        <div id="gallery-delete-warning-box" style="display: none;"></div>
        <div id="gallery-delete-safe-box" style="display: none;"></div>
        <div id="gallery-used-doc-list"></div>
        <div id="gallery-delete-checking" style="display: none;"></div>
    </div>

    <!-- Gallery Preview Modal -->
    <div class="modal-overlay" id="modal-gallery-preview" style="z-index: 1120;">
        <button class="modal-close" id="btn-gallery-preview-close">&times;</button>
        <img id="gallery-preview-img" src="">
        <h3 id="gallery-preview-title"></h3>
        <span id="gallery-preview-meta-dim"></span>
        <span id="gallery-preview-meta-size"></span>
        <span id="gallery-preview-meta-date"></span>
        <input type="text" id="gallery-preview-alt-input">
        <input type="text" id="gallery-preview-md-input">
        <input type="text" id="gallery-preview-html-input">
        <button id="btn-copy-preview-md">Copy MD</button>
        <button id="btn-copy-preview-html">Copy HTML</button>
        <button id="btn-preview-delete">Delete</button>
        <button id="btn-preview-open-newtab">Open New Tab</button>
        <button id="btn-preview-insert-editor" style="display: none;">✓ Select & Insert</button>
    </div>

    <!-- HTML Edit Modal & Textarea -->
    <div class="modal-overlay" id="edit-html-modal">
        <button type="button" class="btn-open-gallery" id="btn-edit-html-gallery">Gallery</button>
        <textarea id="edit-html-textarea"></textarea>
    </div>
</body>
</html>
`;

const dom = new JSDOM(htmlContent, { runScripts: "dangerously" });
const { window } = dom;
global.window = window;
global.document = window.document;
global.navigator = window.navigator;
global.HTMLElement = window.HTMLElement;
global.FormData = window.FormData;

// Mock fetch for image list
const mockImages = [
    {
        filename: "1725546271-dashboard_architecture.png",
        path: "uploads/images/1725546271-dashboard_architecture.png",
        url: "uploads/images/1725546271-dashboard_architecture.png",
        size: 102400,
        sizeFormatted: "100.0 KB",
        dimensions: "1280x720",
        dateFormatted: "2026-09-05 12:00:00",
        extension: "png"
    },
    {
        filename: "logo.png",
        path: "uploads/images/logo.png",
        url: "uploads/images/logo.png",
        size: 20480,
        sizeFormatted: "20.0 KB",
        dimensions: "400x200",
        dateFormatted: "2026-09-06 14:00:00",
        extension: "png"
    }
];

window.fetch = async (url) => {
    if (url.includes('action=ext_gallery_list')) {
        return {
            json: async () => ({ success: true, images: mockImages })
        };
    }
    return {
        json: async () => ({ success: true })
    };
};

// Mock Toast UI Editor
let lastExecCommand = null;
let lastExecArgs = null;
let lastInsertedText = null;
let focusCalled = false;

window.tuiEditorInstance = {
    exec: (command, args) => {
        lastExecCommand = command;
        lastExecArgs = args;
    },
    insertText: (text) => {
        lastInsertedText = text;
    },
    focus: () => {
        focusCalled = true;
    }
};

// Load tool-gallery/script.js
const galleryScriptCode = fs.readFileSync(path.join(__dirname, '../assets/extensions/tool-gallery/script.js'), 'utf8');
window.eval(galleryScriptCode);

(async () => {
    // Wait for DOMContentLoaded to fire naturally
    await new Promise(r => setTimeout(r, 20));

    // 1. Verify window.openGalleryModal is exposed
    assert.strictEqual(typeof window.openGalleryModal, 'function', 'window.openGalleryModal must be exposed globally');
    console.log('✓ Test 1 Passed: window.openGalleryModal is globally exposed');

    // 2. Open gallery when editor is NOT active
    const inlineEditor = window.document.getElementById('inline-editor-container');
    inlineEditor.style.display = 'none';

    window.openGalleryModal();
    // Allow async loadGallery fetch to complete
    await new Promise(r => setTimeout(r, 50));

    const galleryModal = window.document.getElementById('modal-gallery');
    assert.ok(galleryModal.classList.contains('open'), 'modal-gallery should be open');

    const editorBanner = window.document.getElementById('gallery-editor-banner');
    assert.strictEqual(editorBanner.style.display, 'none', 'Editor banner should be hidden when editor is inactive');

    // Verify cards rendered without select buttons
    const selectBtns = window.document.querySelectorAll('.gallery-btn-select');
    assert.strictEqual(selectBtns.length, 0, 'No select buttons should render when editor is inactive');
    console.log('✓ Test 2 Passed: Gallery in standard mode hides editor banner and select buttons');

    // Close modal
    galleryModal.classList.remove('open');

    // 3. Open gallery when editor IS active (simulating article editing)
    inlineEditor.style.display = 'block';

    // Click #btn-editor-gallery
    const btnEditorGallery = window.document.getElementById('btn-editor-gallery');
    btnEditorGallery.click();
    await new Promise(r => setTimeout(r, 50));

    assert.ok(galleryModal.classList.contains('open'), 'modal-gallery should be open after clicking btn-editor-gallery');
    assert.strictEqual(editorBanner.style.display, 'flex', 'Editor banner should be visible when editor is active');

    const activeSelectBtns = window.document.querySelectorAll('.gallery-btn-select');
    assert.strictEqual(activeSelectBtns.length, 2, 'Each image card should have a select button when editing');

    const overlaySelectBtns = window.document.querySelectorAll('.btn-overlay-select');
    assert.strictEqual(overlaySelectBtns.length, 2, 'Each image card should have an overlay select button');
    console.log('✓ Test 3 Passed: Gallery in editor mode displays banner and select buttons on all cards');

    // 4. Click Select on the first image card
    lastExecCommand = null;
    lastExecArgs = null;
    focusCalled = false;

    activeSelectBtns[0].click();

    assert.strictEqual(lastExecCommand, 'addImage', 'Toast UI exec command should be addImage');
    assert.strictEqual(lastExecArgs.imageUrl, mockImages[0].url, 'Image URL should match the selected image');
    assert.strictEqual(lastExecArgs.altText, 'dashboard architecture', 'Alt text should be cleaned from timestamp and underscores');
    assert.ok(!galleryModal.classList.contains('open'), 'modal-gallery should close after selecting an image');
    assert.ok(focusCalled, 'Editor should be refocused after image selection');
    console.log('✓ Test 4 Passed: Clicking Select inserts image via editor API and closes gallery');

    // 5. Preview modal selection with custom alt text
    inlineEditor.style.display = 'block';
    window.openGalleryModal();
    await new Promise(r => setTimeout(r, 50));

    // Click thumbnail on second image to open preview modal
    const thumbs = window.document.querySelectorAll('.gallery-card-thumb-wrap');
    thumbs[1].click();

    const previewModal = window.document.getElementById('modal-gallery-preview');
    assert.ok(previewModal.classList.contains('open'), 'Preview modal should open upon clicking thumbnail');

    const btnPreviewInsert = window.document.getElementById('btn-preview-insert-editor');
    assert.strictEqual(btnPreviewInsert.style.display, 'inline-block', 'Insert button in preview modal should be visible');

    // Customize alt text
    const altInput = window.document.getElementById('gallery-preview-alt-input');
    altInput.value = 'Company Official Logo';
    altInput.dispatchEvent(new window.Event('input'));

    lastExecCommand = null;
    lastExecArgs = null;
    btnPreviewInsert.click();

    assert.strictEqual(lastExecCommand, 'addImage', 'Insert from preview modal should execute addImage');
    assert.strictEqual(lastExecArgs.altText, 'Company Official Logo', 'Custom Alt text should be passed to editor');
    assert.ok(!previewModal.classList.contains('open'), 'Preview modal should close after selection');
    assert.ok(!galleryModal.classList.contains('open'), 'Gallery modal should close after preview selection');
    console.log('✓ Test 5 Passed: Preview modal allows customizing Alt text and inserting into editor');

    // 6. Delegate click on .btn-open-gallery
    const btnHtmlGallery = window.document.getElementById('btn-edit-html-gallery');
    btnHtmlGallery.click();
    assert.ok(galleryModal.classList.contains('open'), 'Clicking .btn-open-gallery should open the gallery modal');
    console.log('✓ Test 6 Passed: .btn-open-gallery elements trigger opening the gallery');

    // 7. Test Image Selection in HTML Editor Mode
    galleryModal.classList.remove('open');
    inlineEditor.style.display = 'none';
    const editHtmlModal = window.document.getElementById('edit-html-modal');
    const htmlTextarea = window.document.getElementById('edit-html-textarea');
    editHtmlModal.classList.add('open');
    htmlTextarea.value = '<h1>My Dashboard</h1>\n';

    // Open gallery from HTML editor
    btnHtmlGallery.click();
    await new Promise(r => setTimeout(r, 50));

    assert.ok(galleryModal.classList.contains('open'), 'Gallery should open when triggered from HTML editor');
    assert.strictEqual(editorBanner.style.display, 'flex', 'Editor banner should be visible when HTML editor is active');

    const htmlSelectBtns = window.document.querySelectorAll('.gallery-btn-select');
    assert.strictEqual(htmlSelectBtns.length, 2, 'Select buttons should be present on image cards');

    // Select second image
    htmlSelectBtns[1].click();

    const expectedHtmlImg = `<img src="${mockImages[1].url}" alt="logo">`;
    assert.ok(htmlTextarea.value.includes(expectedHtmlImg), 'HTML textarea should receive the formatted <img ...> snippet');
    assert.ok(!galleryModal.classList.contains('open'), 'Gallery modal should close after selecting image in HTML mode');
    console.log('✓ Test 7 Passed: Selecting image in HTML editor inserts valid <img> tag and closes gallery');

    console.log('\n🎉 ALL GALLERY EDITOR TESTS PASSED SUCCESSFULLY! 🎉');
})();
