<?php
// tests/test_form_styling_themes.php

$passed = 0;
$failed = 0;

function assertTest($condition, $name) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: {$name}\n";
        $passed++;
    } else {
        echo "❌ FAIL: {$name}\n";
        $failed++;
    }
}

function sRGBtoLinear($val) {
    $c = $val / 255.0;
    return ($c <= 0.04045) ? ($c / 12.92) : pow(($c + 0.055) / 1.055, 2.4);
}

function relativeLuminance($hex) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return 0.2126 * sRGBtoLinear($r) + 0.7152 * sRGBtoLinear($g) + 0.0722 * sRGBtoLinear($b);
}

function contrastRatio($hex1, $hex2) {
    $l1 = relativeLuminance($hex1);
    $l2 = relativeLuminance($hex2);
    return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

echo "Running Interactive Form Light/Dark Theme & Contrast Tests...\n\n";

$baseDir = __DIR__ . '/../assets/extensions/page-form';
$docsDir = __DIR__ . '/../docs/assets/extensions/page-form';

$css = file_get_contents($baseDir . '/style.css');
$renderer = file_get_contents($baseDir . '/renderer.php');
$script = file_get_contents($baseDir . '/script.js');

// 1. No undefined Qwiki CSS custom properties
assertTest(strpos($css, '--card-bg') === false, 'style.css does not reference undefined --card-bg');
assertTest(strpos($css, '--bg-color') === false, 'style.css does not reference undefined --bg-color');
assertTest(strpos($css, '--border-color-hover') === false, 'style.css does not reference undefined --border-color-hover');

// 2. Uses Qwiki theme custom properties and color-scheme
assertTest(strpos($css, 'var(--bg-card') !== false, 'style.css uses --bg-card');
assertTest(strpos($css, 'var(--bg-primary') !== false, 'style.css uses --bg-primary');
assertTest(strpos($css, 'var(--bg-secondary') !== false, 'style.css uses --bg-secondary');
assertTest(strpos($css, 'var(--text-primary') !== false, 'style.css uses --text-primary');
assertTest(strpos($css, 'var(--text-secondary') !== false, 'style.css uses --text-secondary');
assertTest(strpos($css, 'color-scheme: dark;') !== false, 'style.css declares color-scheme: dark');
assertTest(strpos($css, 'color-scheme: light;') !== false, 'style.css declares color-scheme: light under light theme');
assertTest(strpos($css, '[data-theme="light"]') !== false, 'style.css includes [data-theme="light"] overrides');
assertTest(strpos($css, '@media (forced-colors: active)') !== false, 'style.css includes Forced Colors Mode fallback');

// 3. Modal & accessibility structure in renderer.php and script.js
assertTest(strpos($renderer, 'class="modal-overlay"') !== false, 'renderer.php uses .modal-overlay for admin modals');
assertTest(strpos($renderer, 'class="modal-card') !== false, 'renderer.php uses .modal-card for admin modals');
assertTest(strpos($renderer, 'class="modal"') === false, 'renderer.php no longer uses unstyled .modal class');
assertTest(strpos($renderer, 'class="modal-content') === false, 'renderer.php no longer uses unstyled .modal-content class');
assertTest(strpos($renderer, 'aria-live="polite"') !== false, 'renderer.php feedback area has aria-live="polite"');
assertTest(strpos($script, 'window.QwikiApp.modals.open') !== false, 'script.js opens form modals via QwikiApp.modals');
assertTest(strpos($script, "modalSubmissions.style.display = 'flex'") === false, 'script.js does not force inline display:flex on modal');

// 4. Docs subwiki synchronization
foreach (['style.css', 'renderer.php', 'script.js'] as $file) {
    assertTest(
        file_get_contents($baseDir . '/' . $file) === file_get_contents($docsDir . '/' . $file),
        "docs/assets/extensions/page-form/{$file} is synchronized"
    );
}

// 5. WCAG contrast verification for light and dark tokens
assertTest(contrastRatio('#f8fafc', '#1e293b') >= 4.5, 'Dark mode primary text on card >= 4.5:1');
assertTest(contrastRatio('#94a3b8', '#1e293b') >= 4.5, 'Dark mode secondary text on card >= 4.5:1');
assertTest(contrastRatio('#94a3b8', '#0f172a') >= 4.5, 'Dark mode placeholder on input >= 4.5:1');
assertTest(contrastRatio('#64748b', '#1e293b') >= 3.0, 'Dark mode input border against card >= 3.0:1');

assertTest(contrastRatio('#0f172a', '#ffffff') >= 4.5, 'Light mode primary text on card >= 4.5:1');
assertTest(contrastRatio('#475569', '#ffffff') >= 4.5, 'Light mode secondary text on card >= 4.5:1');
assertTest(contrastRatio('#64748b', '#f8fafc') >= 4.5, 'Light mode placeholder on input >= 4.5:1');
assertTest(contrastRatio('#64748b', '#ffffff') >= 3.0, 'Light mode input border against card >= 3.0:1');
assertTest(contrastRatio('#dc2626', '#ffffff') >= 4.5, 'Light mode required indicator on card >= 4.5:1');
assertTest(contrastRatio('#b91c1c', '#ffffff') >= 4.5, 'Light mode danger alert text >= 4.5:1');
assertTest(contrastRatio('#065f46', '#ffffff') >= 4.5, 'Light mode success alert text >= 4.5:1');
assertTest(contrastRatio('#1e40af', '#ffffff') >= 4.5, 'Light mode info alert text >= 4.5:1');

echo "\nTest Results: {$passed} passed, {$failed} failed.\n";
if ($failed > 0) {
    exit(1);
}
