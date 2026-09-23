const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert');

console.log('=== Testing Gemini AI Assistant Dialog Open Functionality ===\n');

// 1. Verify CSS rules in style.css
const extCssPath = path.join(__dirname, '..', 'assets', 'extensions', 'tool-gemini-assistant', 'style.css');
const extCss = fs.readFileSync(extCssPath, 'utf8');

assert(extCss.includes('#modal-gemini-assistant.open') || extCss.includes('#modal-gemini-assistant.active'), 'style.css must have display rule for open/active modal');
assert(extCss.includes('display: flex'), 'style.css must set display: flex on open/active modal');
console.log('PASS: style.css defines display: flex for #modal-gemini-assistant.open / .active');

// 2. Verify HTML markup in index.php
const indexPath = path.join(__dirname, '..', 'index.php');
const indexHtml = fs.readFileSync(indexPath, 'utf8');

assert(indexHtml.includes('id="btn-open-gemini-from-settings"'), 'index.php must contain btn-open-gemini-from-settings in settings modal');
assert(indexHtml.includes('id="btn-open-gemini-from-llm"'), 'index.php must contain btn-open-gemini-from-llm in llm keys modal');
console.log('PASS: index.php contains AI Assistant triggers in both settings-modal and llm-keys-modal');

// 3. Functional JSDOM Test
const modalHtmlPath = path.join(__dirname, '..', 'assets', 'extensions', 'tool-gemini-assistant', 'modal.html.php');
const modalMarkup = fs.readFileSync(modalHtmlPath, 'utf8');

// Strip PHP tags for JSDOM
const cleanModalHtml = modalMarkup.replace(/<\?php[\s\S]*?\?>/g, '').replace(/<\?=[\s\S]*?\?>/g, '');

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
    <div class="user-dropdown-menu">
        <button class="dropdown-item" id="btn-util-gemini_assistant">✨ Gemini AI Assistant</button>
    </div>

    <div class="modal-overlay" id="settings-modal">
        <button id="btn-open-gemini-from-settings" data-gemini-tab="settings">AI Settings</button>
    </div>

    <div class="modal-overlay" id="llm-keys-modal">
        <button id="btn-open-gemini-from-llm" data-gemini-tab="settings">AI Assistant Settings</button>
    </div>

    ${cleanModalHtml}
</body>
</html>
`;

const dom = new JSDOM(fullHtml, { runScripts: 'dangerously' });
const { window } = dom;
const { document } = window;

// Provide mock fetch for JSDOM
window.fetch = function() {
    return Promise.resolve({
        json: () => Promise.resolve({ success: true, model: 'gemini-2.5-flash', hasKey: true })
    });
};

// Load script.js
const scriptPath = path.join(__dirname, '..', 'assets', 'extensions', 'tool-gemini-assistant', 'script.js');
let scriptCode = fs.readFileSync(scriptPath, 'utf8');

// Execute script in JSDOM context
const scriptEl = document.createElement('script');
scriptEl.textContent = scriptCode;
document.body.appendChild(scriptEl);

// Dispatch DOMContentLoaded so initGeminiAssistant runs
document.dispatchEvent(new window.Event('DOMContentLoaded'));

const modal = document.getElementById('modal-gemini-assistant');
assert(modal, 'Modal element #modal-gemini-assistant must exist');
assert(!modal.classList.contains('open') && !modal.classList.contains('active'), 'Modal must initially be closed');
console.log('PASS: Modal starts closed without open or active classes');

// Test Case 1: Clicking header menu button opens dialog
const btnHeader = document.getElementById('btn-util-gemini_assistant');
assert(btnHeader, 'Header menu button must exist');
btnHeader.click();

assert(modal.classList.contains('open') && modal.classList.contains('active'), 'Clicking header button must add open and active classes');
console.log('PASS: Clicking header button #btn-util-gemini_assistant opens the dialog (has .open and .active)');

// Test Case 2: Closing via close button
const closeBtn = modal.querySelector('[data-close="modal-gemini-assistant"]');
assert(closeBtn, 'Close button must exist');
closeBtn.click();

assert(!modal.classList.contains('open') && !modal.classList.contains('active'), 'Clicking close button must remove open and active classes');
console.log('PASS: Clicking close button closes the dialog');

// Test Case 3: Opening from settings-modal switches to settings tab and closes settings modal
const settingsModal = document.getElementById('settings-modal');
settingsModal.classList.add('open');
const btnFromSettings = document.getElementById('btn-open-gemini-from-settings');
btnFromSettings.click();

assert(!settingsModal.classList.contains('open'), 'Opening Gemini Assistant from settings modal must close settings-modal');
assert(modal.classList.contains('open') && modal.classList.contains('active'), 'Gemini modal must be open');

const settingsTabBtn = document.getElementById('gemini-tab-btn-settings');
assert(settingsTabBtn.classList.contains('active'), 'Settings tab button must be active');
const settingsView = document.getElementById('gemini-view-settings');
assert(settingsView.style.display !== 'none', 'Settings view must be visible');
console.log('PASS: Clicking #btn-open-gemini-from-settings closes settings-modal and opens Gemini dialog directly to settings tab');

// Test Case 4: Escape key closes modal
const escEvent = new window.KeyboardEvent('keydown', { key: 'Escape' });
document.dispatchEvent(escEvent);
assert(!modal.classList.contains('open') && !modal.classList.contains('active'), 'Escape key must close the modal');
console.log('PASS: Pressing Escape closes the Gemini dialog');

// Test Case 5: Opening from llm-keys-modal
const llmModal = document.getElementById('llm-keys-modal');
llmModal.classList.add('open');
const btnFromLlm = document.getElementById('btn-open-gemini-from-llm');
btnFromLlm.click();

assert(!llmModal.classList.contains('open'), 'Opening Gemini Assistant from LLM modal must close llm-keys-modal');
assert(modal.classList.contains('open'), 'Gemini modal must be open');
console.log('PASS: Clicking #btn-open-gemini-from-llm closes llm-keys-modal and opens Gemini dialog');

// Test Case 6: Backdrop click closes modal
modal.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
assert(!modal.classList.contains('open'), 'Backdrop click must close modal');
console.log('PASS: Clicking outside modal-card (backdrop) closes the modal');

console.log('\n=== ALL GEMINI DIALOG TESTS PASSED SUCCESSFULLY! 🎉 ===');
