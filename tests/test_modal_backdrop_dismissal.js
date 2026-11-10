const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Testing Modal Backdrop Click Dismissal Behavior ===\n');

// 1. Load actual core and extension modal HTML templates
const loginHtml = fs.readFileSync(path.join(__dirname, '..', 'templates', 'modals', 'login.php'), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '');
const settingsHtml = fs.readFileSync(path.join(__dirname, '..', 'templates', 'modals', 'settings.php'), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '');
const backupHtml = fs.readFileSync(path.join(__dirname, '..', 'assets', 'extensions', 'tool-backup', 'modal.html.php'), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '');
const galleryHtml = fs.readFileSync(path.join(__dirname, '..', 'assets', 'extensions', 'tool-gallery', 'modal.html.php'), 'utf8')
    .replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '');

const fullHtml = `
<!DOCTYPE html>
<html>
<head>
    <style>
        .modal-overlay { display: none; }
        .modal-overlay.open, .modal-overlay.active { display: flex; }
    </style>
</head>
<body>
    ${loginHtml}
    ${settingsHtml}
    ${backupHtml}
    ${galleryHtml}
</body>
</html>
`;

const dom = new JSDOM(fullHtml, { runScripts: 'dangerously' });
const { window } = dom;
const { document } = window;

// Helper to simulate mouse drag from element A to element B
function simulateDragAndRelease(elementFrom, elementTo) {
    const commonAncestor = elementTo; // In our case, the backdrop
    elementFrom.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true, target: elementFrom }));
    elementTo.dispatchEvent(new window.MouseEvent('mouseup', { bubbles: true, target: elementTo }));
    commonAncestor.dispatchEvent(new window.MouseEvent('click', { bubbles: true, target: commonAncestor }));
}

// Helper to simulate full click on an element
function simulateClick(element) {
    element.dispatchEvent(new window.MouseEvent('mousedown', { bubbles: true, target: element }));
    element.dispatchEvent(new window.MouseEvent('mouseup', { bubbles: true, target: element }));
    element.dispatchEvent(new window.MouseEvent('click', { bubbles: true, target: element }));
}

// 2. Load Core Modals Script
const coreModalsScript = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'core', 'modals.js'), 'utf8');
const scriptEl = document.createElement('script');
scriptEl.textContent = coreModalsScript;
document.body.appendChild(scriptEl);

// Initialize core modals controller
window.QwikiApp.modals.init();

// --- TEST SUITE 1: Sign In Modal (#login-modal) ---
console.log('--- Test Suite 1: Sign In Modal (#login-modal) ---');
const loginModal = document.getElementById('login-modal');
const loginUsernameInput = document.getElementById('login-username');
const loginCard = loginModal.querySelector('.modal-card');
assert(loginModal, 'Login modal must exist');
assert(loginUsernameInput, 'Username input must exist inside login modal');

// Open login modal
window.QwikiApp.modals.open('login-modal');
assert(loginModal.classList.contains('open'), 'Login modal must be open');
console.log('PASS: Login modal opens successfully');

// Case 1A: Selecting/dragging text from input field and releasing mouse on backdrop
console.log('Testing: User starts drag on username input and releases outside modal card on backdrop...');
simulateDragAndRelease(loginUsernameInput, loginModal);
assert(loginModal.classList.contains('open'), 'CRITICAL: Releasing mouse outside modal card MUST NOT close the modal!');
console.log('PASS: Dragging from inside username input and releasing on backdrop does NOT close the modal');

// Case 1B: Dragging from inside modal-card and releasing on backdrop
console.log('Testing: User starts drag on modal card and releases outside on backdrop...');
simulateDragAndRelease(loginCard, loginModal);
assert(loginModal.classList.contains('open'), 'CRITICAL: Releasing mouse outside modal card MUST NOT close the modal!');
console.log('PASS: Dragging from inside modal card and releasing on backdrop does NOT close the modal');

// Case 1C: Dragging from backdrop into modal-card
console.log('Testing: User starts drag on backdrop and releases inside modal card...');
simulateDragAndRelease(loginModal, loginCard);
assert(loginModal.classList.contains('open'), 'Starting on backdrop and ending inside modal card MUST NOT close the modal!');
console.log('PASS: Dragging from backdrop into modal card does NOT close the modal');

// Case 1D: Clicking inside the modal form on an input
console.log('Testing: Clicking inside modal card...');
simulateClick(loginUsernameInput);
assert(loginModal.classList.contains('open'), 'Clicking inside modal card MUST NOT close the modal!');
console.log('PASS: Clicking inside modal card does NOT close the modal');

// Case 1E: Genuine click outside on backdrop (mousedown + mouseup on backdrop)
console.log('Testing: Genuine mouse click outside on backdrop...');
simulateClick(loginModal);
assert(!loginModal.classList.contains('open'), 'Deliberate click outside on backdrop MUST close the modal!');
console.log('PASS: Genuine mouse click on backdrop closes the modal');

// Case 1F: Close button
window.QwikiApp.modals.open('login-modal');
assert(loginModal.classList.contains('open'));
const loginCloseBtn = loginModal.querySelector('[data-close="login-modal"]');
loginCloseBtn.click();
assert(!loginModal.classList.contains('open'), 'Clicking close button must close the modal');
console.log('PASS: Close button closes the modal');

// Case 1G: Escape key
window.QwikiApp.modals.open('login-modal');
assert(loginModal.classList.contains('open'));
document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape' }));
assert(!loginModal.classList.contains('open'), 'Escape key must close the modal');
console.log('PASS: Escape key closes the modal');


// --- TEST SUITE 2: Settings Modal (#settings-modal) ---
console.log('\n--- Test Suite 2: Settings Modal (#settings-modal) ---');
const settingsModal = document.getElementById('settings-modal');
const settingsCard = settingsModal.querySelector('.modal-card');
const settingsFirstInput = settingsModal.querySelector('input, select, textarea');

window.QwikiApp.modals.open('settings-modal');
assert(settingsModal.classList.contains('open'));

simulateDragAndRelease(settingsFirstInput || settingsCard, settingsModal);
assert(settingsModal.classList.contains('open'), 'Drag from inside settings modal must NOT close it');
console.log('PASS: Drag-release outside does NOT close settings modal');

simulateClick(settingsModal);
assert(!settingsModal.classList.contains('open'), 'Click on backdrop closes settings modal');
console.log('PASS: Genuine backdrop click closes settings modal');


// --- TEST SUITE 3: Extension Modals (Tool Backup & Tool Gallery) ---
console.log('\n--- Test Suite 3: Extension Modals ---');

// Load tool-backup script
const backupScript = fs.readFileSync(path.join(__dirname, '..', 'assets', 'extensions', 'tool-backup', 'script.js'), 'utf8');
const backupScriptEl = document.createElement('script');
backupScriptEl.textContent = backupScript;
window.fetch = () => Promise.resolve({
    json: () => Promise.resolve({
        success: true,
        summary: {
            content: { filesCount: 0, formattedSize: '0 B' },
            uploads: { filesCount: 0, formattedSize: '0 B' },
            qwikiJson: { exists: true, formattedSize: '0 B' },
            usersJson: { exists: true, formattedSize: '0 B' },
            subwikis: [],
            totalFiles: 0,
            formattedTotalSize: '0 B'
        },
        tree: []
    })
});
document.body.appendChild(backupScriptEl);
document.dispatchEvent(new window.Event('DOMContentLoaded'));

const backupModal = document.getElementById('modal-backup');
const backupCard = backupModal.querySelector('.modal-card');
assert(backupModal, 'Backup modal must exist');

if (typeof window.openBackupModal === 'function') {
    window.openBackupModal();
} else {
    window.QwikiApp.modals.open('modal-backup');
}
assert(backupModal.classList.contains('open') || backupModal.classList.contains('active'), 'Backup modal must open');

simulateDragAndRelease(backupCard, backupModal);
assert(backupModal.classList.contains('open') || backupModal.classList.contains('active'), 'Backup modal must not close on drag release outside');
console.log('PASS: Backup modal does NOT close on drag release outside');

simulateClick(backupModal);
assert(!backupModal.classList.contains('open') && !backupModal.classList.contains('active'), 'Backup modal must close on backdrop click');
console.log('PASS: Backup modal closes on genuine backdrop click');

console.log('\n=== ALL MODAL BACKDROP DISMISSAL TESTS PASSED SUCCESSFULLY! 🎉 ===');
