<?php
// tests/anchor_and_contrast_test.php

require_once __DIR__ . '/../lib/Parsedown.php';

// Load QwikiParsedown from index.php definition
$indexCode = file_get_contents(__DIR__ . '/../index.php');
$startMarker = "if (!class_exists('QwikiParsedown')) {";
$endMarker = '$config = Config::load();';
$p1 = strpos($indexCode, $startMarker);
$p2 = strpos($indexCode, $endMarker);
if ($p1 !== false && $p2 !== false) {
    eval(substr($indexCode, $p1, $p2 - $p1));
}

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

echo "Running Anchor Resolution & Dark Mode Link Contrast Tests...\n\n";

// ----------------------------------------------------
// 1. QwikiParsedown Heading ID Generation Tests
// ----------------------------------------------------
$parsedown = new QwikiParsedown();

$mdContent = <<<MD
# Main Title

Some introductory text with [inline link](#system-features) and [external](https://example.com).

## System Features

Content under system features.

### Advanced Settings

Content under advanced settings.

#### Deeply Nested Subsection

Deep content.

##### Level 5 Heading

Level 5 content.

###### Level 6 Heading

Level 6 content.

## System Features

Second section with identical title to test deduplication.

### Advanced Settings

Second sub-heading with identical title.

Setext H1 Style
===============

Setext H2 Style
---------------
MD;

$html = $parsedown->text($mdContent);

assertTest(strpos($html, '<h1 id="main-title">Main Title</h1>') !== false, 'h1 has id="main-title"');
assertTest(strpos($html, '<h2 id="system-features">System Features</h2>') !== false, 'h2 has id="system-features"');
assertTest(strpos($html, '<h3 id="advanced-settings">Advanced Settings</h3>') !== false, 'h3 has id="advanced-settings"');
assertTest(strpos($html, '<h4 id="deeply-nested-subsection">Deeply Nested Subsection</h4>') !== false, 'h4 has id="deeply-nested-subsection"');
assertTest(strpos($html, '<h5 id="level-5-heading">Level 5 Heading</h5>') !== false, 'h5 has id="level-5-heading"');
assertTest(strpos($html, '<h6 id="level-6-heading">Level 6 Heading</h6>') !== false, 'h6 has id="level-6-heading"');
assertTest(strpos($html, '<h2 id="system-features-1">System Features</h2>') !== false, 'Duplicate h2 deduplicated with id="system-features-1"');
assertTest(strpos($html, '<h3 id="advanced-settings-1">Advanced Settings</h3>') !== false, 'Duplicate h3 deduplicated with id="advanced-settings-1"');
assertTest(strpos($html, '<h1 id="setext-h1-style">Setext H1 Style</h1>') !== false, 'Setext h1 has id="setext-h1-style"');
assertTest(strpos($html, '<h2 id="setext-h2-style">Setext H2 Style</h2>') !== false, 'Setext h2 has id="setext-h2-style"');

// Check formatted heading tags e.g. **bold** or `code`
$formattedMd = "## **Important** `Notice` & Details\n";
$formattedHtml = $parsedown->text($formattedMd);
assertTest(strpos($formattedHtml, '<h2 id="important-notice-details">') !== false, 'Markdown formatting stripped from heading id slug');

// Check external link attributes
assertTest(strpos($html, 'target="_blank"') !== false, 'External link includes target="_blank"');
assertTest(strpos($html, 'rel="noopener noreferrer"') !== false, 'External link includes rel="noopener noreferrer"');

// ----------------------------------------------------
// 2. CSS Styling & Contrast Ratio Verification
// ----------------------------------------------------
$css = file_get_contents(__DIR__ . '/../assets/css/qwiki.css');

assertTest(strpos($css, '--link-color: #818cf8;') !== false, 'Dark theme defines --link-color (#818cf8)');
assertTest(strpos($css, '--link-hover: #a5b4fc;') !== false, 'Dark theme defines --link-hover (#a5b4fc)');
assertTest(strpos($css, '--link-visited: #c084fc;') !== false, 'Dark theme defines --link-visited (#c084fc)');

assertTest(strpos($css, '--link-color: #4f46e5;') !== false, 'Light theme defines --link-color (#4f46e5)');
assertTest(strpos($css, '--link-hover: #3730a3;') !== false, 'Light theme defines --link-hover (#3730a3)');
assertTest(strpos($css, '--link-visited: #7c3aed;') !== false, 'Light theme defines --link-visited (#7c3aed)');

assertTest(strpos($css, '.content-body a[href]:not(.btn)') !== false, 'CSS contains .content-body a[href]:not(.btn) selector');
assertTest(strpos($css, '.content-body a[href]:not(.btn):visited') !== false, 'CSS contains .content-body a[href]:not(.btn):visited selector');
assertTest(strpos($css, '.content-body a[href]:not(.btn):hover') !== false, 'CSS contains .content-body a[href]:not(.btn):hover selector');
assertTest(strpos($css, 'scroll-margin-top:') !== false, 'CSS defines scroll-margin-top for headings and target IDs');
assertTest(strpos($css, 'scroll-behavior: smooth;') !== false, 'CSS defines scroll-behavior: smooth on .app-content');

// Helper to compute WCAG 2.1 relative luminance
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
    $lighter = max($l1, $l2);
    $darker = min($l1, $l2);
    return ($lighter + 0.05) / ($darker + 0.05);
}

// Dark Mode (#0f172a background)
$darkBg = '#0f172a';
$darkCardBg = '#1e293b';
$darkLink = '#818cf8';
$darkVisited = '#c084fc';
$darkHover = '#a5b4fc';

$darkLinkContrast = contrastRatio($darkLink, $darkBg);
$darkVisitedContrast = contrastRatio($darkVisited, $darkBg);
$darkHoverContrast = contrastRatio($darkHover, $darkBg);

assertTest($darkLinkContrast >= 4.5, "Dark mode link contrast ({$darkLink} vs {$darkBg}) = " . round($darkLinkContrast, 2) . ":1 >= 4.5:1 (WCAG AA)");
assertTest($darkVisitedContrast >= 4.5, "Dark mode visited contrast ({$darkVisited} vs {$darkBg}) = " . round($darkVisitedContrast, 2) . ":1 >= 4.5:1 (WCAG AA)");
assertTest($darkHoverContrast >= 4.5, "Dark mode hover contrast ({$darkHover} vs {$darkBg}) = " . round($darkHoverContrast, 2) . ":1 >= 4.5:1 (WCAG AA)");

// Light Mode (#f8fafc background)
$lightBg = '#f8fafc';
$lightLink = '#4f46e5';
$lightVisited = '#7c3aed';
$lightHover = '#3730a3';

$lightLinkContrast = contrastRatio($lightLink, $lightBg);
$lightVisitedContrast = contrastRatio($lightVisited, $lightBg);
$lightHoverContrast = contrastRatio($lightHover, $lightBg);

assertTest($lightLinkContrast >= 4.5, "Light mode link contrast ({$lightLink} vs {$lightBg}) = " . round($lightLinkContrast, 2) . ":1 >= 4.5:1 (WCAG AA)");
assertTest($lightVisitedContrast >= 4.5, "Light mode visited contrast ({$lightVisited} vs {$lightBg}) = " . round($lightVisitedContrast, 2) . ":1 >= 4.5:1 (WCAG AA)");
assertTest($lightHoverContrast >= 4.5, "Light mode hover contrast ({$lightHover} vs {$lightBg}) = " . round($lightHoverContrast, 2) . ":1 >= 4.5:1 (WCAG AA)");

echo "\n----------------------------------------------------\n";
echo "Tests Completed: Passed: {$passed}, Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
