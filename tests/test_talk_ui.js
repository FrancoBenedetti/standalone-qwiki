/**
 * Structural & DOM Verification Test for Talk Pages Extension Frontend UI
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const assert = require('assert');

console.log('=== Running Talk Pages Frontend UI & Asset Tests ===\n');

const repoRoot = path.resolve(__dirname, '..');
const scriptPath = path.join(repoRoot, 'assets/extensions/tool-talk/script.js');
const stylePath = path.join(repoRoot, 'assets/extensions/tool-talk/style.css');
const manifestPath = path.join(repoRoot, 'assets/extensions/tool-talk/manifest.json');

// 1. Verify JavaScript syntax
console.log('1. Verifying script.js syntax...');
try {
    execSync(`node -c "${scriptPath}"`);
    console.log('  [PASS] script.js has valid JavaScript syntax');
} catch (e) {
    console.error('  [FAIL] script.js syntax error:', e.message);
    process.exit(1);
}

// 2. Verify CSS rules and Design System CSS custom properties
console.log('\n2. Verifying style.css design system compliance...');
const cssContent = fs.readFileSync(stylePath, 'utf8');
const requiredTokens = [
    '--bg-primary',
    '--bg-secondary',
    '--bg-tertiary',
    '--border-color',
    '--text-primary',
    '--text-muted',
    '--accent-color'
];
requiredTokens.forEach(token => {
    assert(cssContent.includes(token), `Missing design token ${token} in style.css`);
    console.log(`  [PASS] Uses design token: ${token}`);
});

assert(cssContent.includes('.qwiki-doc-tabs'), 'Contains .qwiki-doc-tabs class');
assert(cssContent.includes('.doc-tab-btn'), 'Contains .doc-tab-btn class');
assert(cssContent.includes('.doc-tab-active'), 'Contains .doc-tab-active class');
assert(cssContent.includes('.talk-container'), 'Contains .talk-container class');
console.log('  [PASS] Contains all necessary tab and talk workspace classes');

// 3. Verify Manifest declaration
console.log('\n3. Verifying manifest.json configuration...');
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
assert.strictEqual(manifest.id, 'talk', 'Manifest id is talk');
assert.strictEqual(manifest.type, 'utility', 'Manifest type is utility');
assert(manifest.styles.includes('style.css'), 'Manifest styles includes style.css');
assert(manifest.scripts.includes('script.js'), 'Manifest scripts includes script.js');
console.log('  [PASS] manifest.json correctly declares extension assets and actions');

// 4. Verify Frontend Asset Inclusion via ExtensionManager in PHP
console.log('\n4. Verifying ExtensionManager asset injection...');
const phpAssetTest = `<?php
require_once '${repoRoot}/lib/Core/Config.php';
require_once '${repoRoot}/lib/Core/ExtensionManager.php';
$extManager = \\Qwiki\\Core\\ExtensionManager::getInstance();
$assets = $extManager->getFrontendAssets();
echo json_encode($assets);
`;

const assetsJson = execSync('php', { input: phpAssetTest }).toString();
const assets = JSON.parse(assetsJson);

const hasTalkCss = assets.styles.some(s => s.includes('tool-talk/style.css'));
const hasTalkJs = assets.scripts.some(s => s.includes('tool-talk/script.js'));
assert(hasTalkCss, 'ExtensionManager includes tool-talk/style.css in frontend styles');
assert(hasTalkJs, 'ExtensionManager includes tool-talk/script.js in frontend scripts');
console.log('  [PASS] tool-talk/style.css included in getFrontendAssets()');
console.log('  [PASS] tool-talk/script.js included in getFrontendAssets()');

// 5. Verify Modals have allowTalk form fields
console.log('\n5. Verifying Admin Category and Chapter Modals...');
const createCatModal = fs.readFileSync(path.join(repoRoot, 'templates/modals/create_category.php'), 'utf8');
const editCatModal = fs.readFileSync(path.join(repoRoot, 'templates/modals/edit_category.php'), 'utf8');
const createChModal = fs.readFileSync(path.join(repoRoot, 'templates/modals/create_chapter.php'), 'utf8');
const editChModal = fs.readFileSync(path.join(repoRoot, 'templates/modals/edit_chapter.php'), 'utf8');

assert(createCatModal.includes('create-category-allow-talk'), 'create_category.php has create-category-allow-talk');
assert(editCatModal.includes('edit-book-allow-talk-input'), 'edit_category.php has edit-book-allow-talk-input');
assert(createChModal.includes('create-chapter-allow-talk'), 'create_chapter.php has create-chapter-allow-talk');
assert(editChModal.includes('edit-chapter-allow-talk'), 'edit_chapter.php has edit-chapter-allow-talk');
console.log('  [PASS] All 4 category and chapter modals declare allowTalk form controls');

console.log('\nAll frontend and DOM tests passed successfully!');
