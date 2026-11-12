/**
 * Structural & DOM Verification Test for Tabbed Settings Modal
 */

const { execSync } = require('child_process');
const assert = require('assert');

console.log('=== Running Settings Modal Tabbed UI Tests ===\n');

// Render the settings modal via PHP CLI with mock context
const phpScript = `<?php
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/ExtensionManager.php';
$config = \\Qwiki\\Core\\Config::load();
$categoryHierarchy = [
    ['id' => 'getting-started', 'path' => 'Getting Started', 'depth' => 0]
];
$showDocTypesOnlyToAdmin = false;
$showPoweredBy = true;
$showSubwikisInSidebar = true;
$scriptDir = '';
$isSubwiki = true;
$parentTitle = 'Parent Wiki';

require __DIR__ . '/templates/modals/settings.php';
`;

let renderedHtml = '';
try {
    renderedHtml = execSync('php', {
        input: phpScript,
        cwd: __dirname + '/..'
    }).toString();
} catch (e) {
    console.error('Failed to render templates/modals/settings.php:', e.message);
    process.exit(1);
}

// 1. Verify Tab Buttons
const requiredTabs = [
    'settings-tab-general',
    'settings-tab-security',
    'settings-tab-extensions',
    'settings-tab-smtp',
    'settings-tab-integrations'
];

requiredTabs.forEach(tabId => {
    const hasTabBtn = renderedHtml.includes(`data-tab="${tabId}"`);
    assert(hasTabBtn, `Missing tab button for ${tabId}`);
    console.log(`  [PASS] Tab button present for ${tabId}`);
});

// 2. Verify Tab Content Panes
requiredTabs.forEach(tabId => {
    const hasTabPane = renderedHtml.includes(`id="${tabId}"`);
    assert(hasTabPane, `Missing tab pane container id="${tabId}"`);
    console.log(`  [PASS] Tab pane container present for ${tabId}`);
});

// 3. Verify Extension Configuration Fields
assert(renderedHtml.includes('name="extensions_configured"'), 'Missing hidden input extensions_configured');
console.log('  [PASS] extensions_configured hidden field present');

// Check that extensions are rendered with switches
['comments', 'html', 'form', 'gallery'].forEach(extId => {
    assert(renderedHtml.includes(`name="extensions[${extId}]"`), `Missing switch for extension ${extId}`);
    console.log(`  [PASS] Switch toggle present for extension: ${extId}`);
});

// 4. Verify Theme Editor Button in General Tab
assert(renderedHtml.includes('id="btn-open-theme-editor-direct"'), 'Missing native btn-open-theme-editor-direct in settings modal');
console.log('  [PASS] Direct Theme Editor button present');

// 5. Verify Core Settings Fields are Preserved
const requiredInputs = [
    'name="title"',
    'name="logoText"',
    'name="theme"',
    'name="defaultBook"',
    'name="requireLoginToView"',
    'name="twoFactorPolicy"',
    'name="smtp[host]"',
    'name="feedAccessToken"',
    'name="parentTitle"'
];

requiredInputs.forEach(inputName => {
    assert(renderedHtml.includes(inputName), `Missing required input field: ${inputName}`);
    console.log(`  [PASS] Core setting field preserved: ${inputName}`);
});

console.log('\nAll Settings Modal Tabbed UI tests passed successfully! 🎉');
