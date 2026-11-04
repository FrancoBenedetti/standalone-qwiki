<?php
/**
 * Test Suite: Markdown YAML Frontmatter Parsing & Accordion Suppression
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/MetadataManager.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/QwikiParsedown.php';

use Qwiki\Core\MetadataManager;
use Qwiki\Core\ExtensionManager;

$passed = 0;
$failed = 0;

function assert_true($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: $message\n";
        $passed++;
    } else {
        echo "❌ FAIL: $message\n";
        $failed++;
    }
}

echo "Running Frontmatter & Metadata Suppression Tests...\n\n";

// --------------------------------------------------------------------------
// 1. Parsing Real-World Document Frontmatter (Morpheus docs sample)
// --------------------------------------------------------------------------
$sampleMarkdown = <<<MD
---
title: "Object Types Introduction"
section: "02-object-types"
source: "object-types/bl-content/pages/introduction/index.txt"
draft: false
tags: ["bizverse", "presentation-objects"]
---

# Object Types Introduction

This documentation is a resource for use by portal application authors. It is a guide on the use of the full set of application objects that are required to create a functioning Bizverse portal application.

* [Business objects](business-objects)
* [Presentation objects](presentation-objects)
MD;

$fm = MetadataManager::parseFrontmatter($sampleMarkdown);

assert_true($fm['hasFrontmatter'] === true, 'Successfully detects frontmatter at top of document');
assert_true(isset($fm['metadata']['title']) && $fm['metadata']['title'] === 'Object Types Introduction', 'Parses title property with stripped quotes');
assert_true(isset($fm['metadata']['section']) && $fm['metadata']['section'] === '02-object-types', 'Parses section property');
assert_true(isset($fm['metadata']['source']) && $fm['metadata']['source'] === 'object-types/bl-content/pages/introduction/index.txt', 'Parses source path property');
assert_true(isset($fm['metadata']['draft']) && $fm['metadata']['draft'] === false, 'Parses boolean false property correctly');
assert_true(isset($fm['metadata']['tags']) && is_array($fm['metadata']['tags']) && count($fm['metadata']['tags']) === 2, 'Parses inline YAML array for tags');
assert_true(strpos($fm['body'], '---') === false, 'Body text has frontmatter delimiters completely stripped');
assert_true(strpos($fm['body'], '# Object Types Introduction') !== false, 'Body text preserves main Markdown heading');

// --------------------------------------------------------------------------
// 2. YAML Types & Edge Cases
// --------------------------------------------------------------------------
$complexYaml = <<<MD
---
author: 'Dr. Jane Doe'
version: 2.5
active: true
empty_field: null
items:
  - Alpha
  - Beta
  - Gamma
multiline: This is a long
  description wrapped across
  multiple lines
---

Body content here.
MD;

$complexFm = MetadataManager::parseFrontmatter($complexYaml);

assert_true($complexFm['metadata']['author'] === 'Dr. Jane Doe', 'Parses single-quoted string');
assert_true($complexFm['metadata']['version'] === 2.5, 'Parses float numeric value');
assert_true($complexFm['metadata']['active'] === true, 'Parses boolean true');
assert_true($complexFm['metadata']['empty_field'] === null, 'Parses null value');
assert_true(is_array($complexFm['metadata']['items']) && count($complexFm['metadata']['items']) === 3, 'Parses multiline YAML list');
assert_true(strpos($complexFm['metadata']['multiline'], 'wrapped across') !== false, 'Parses multiline indented value');

// --------------------------------------------------------------------------
// 3. Document Without Frontmatter
// --------------------------------------------------------------------------
$plainMarkdown = "# Just a Heading\n\nNo frontmatter here.";
$plainFm = MetadataManager::parseFrontmatter($plainMarkdown);

assert_true($plainFm['hasFrontmatter'] === false, 'Correctly identifies document without frontmatter');
assert_true($plainFm['metadata'] === [], 'Empty metadata array when frontmatter is absent');
assert_true($plainFm['body'] === $plainMarkdown, 'Original body intact when no frontmatter is present');

// --------------------------------------------------------------------------
// 4. Frontmatter Widget HTML Rendering
// --------------------------------------------------------------------------
$widgetHtml = MetadataManager::renderFrontmatterWidget($fm, false);

assert_true(strpos($widgetHtml, '<details class="qwiki-frontmatter"') !== false, 'Renders collapsible <details> container');
assert_true(strpos($widgetHtml, 'open') === false, 'Default collapsed state does not have open attribute');
assert_true(strpos($widgetHtml, '5 properties') !== false, 'Displays accurate properties count badge');
assert_true(strpos($widgetHtml, 'qwiki-frontmatter-key">source</th>') !== false, 'Renders property key table cell');
assert_true(strpos($widgetHtml, '<code class="qwiki-fm-code">object-types/bl-content') !== false, 'Formats path value in code badge');
assert_true(strpos($widgetHtml, '<span class="qwiki-fm-pill qwiki-fm-bool-false">false</span>') !== false, 'Formats boolean value in pill badge');
assert_true(strpos($widgetHtml, '<span class="qwiki-fm-tag">bizverse</span>') !== false, 'Formats tags in tag pills');
assert_true(strpos($widgetHtml, '<details class="qwiki-frontmatter-raw">') !== false, 'Includes nested raw YAML disclosure');

// Expanded widget
$expandedWidget = MetadataManager::renderFrontmatterWidget($fm, true);
assert_true(strpos($expandedWidget, '<details class="qwiki-frontmatter" open>') !== false, 'Expanded widget includes open attribute');

// --------------------------------------------------------------------------
// 5. OpenGraph & SEO Fallback (MetadataManager::resolveArticleMetadata)
// --------------------------------------------------------------------------
$resolvedMeta = MetadataManager::resolveArticleMetadata(
    ['title' => 'Test Doc', 'type' => 'markdown'],
    ['id' => 'test-cat', 'title' => 'Test Category'],
    ['title' => 'My Wiki'],
    'http://localhost/',
    __DIR__,
    false,
    '',
    null,
    $sampleMarkdown
);

assert_true(
    strpos($resolvedMeta['description'], 'title:') === false,
    'SEO description does not leak raw YAML title: prefix'
);
assert_true(
    strpos($resolvedMeta['description'], 'This documentation is a resource') !== false,
    'SEO description correctly pulls from document body paragraph'
);
assert_true(
    in_array('bizverse', $resolvedMeta['articleTags']),
    'Article tags fallback resolves tags from YAML frontmatter'
);

// --------------------------------------------------------------------------
// 6. Config Mode Verification (ExtensionManager integration)
// --------------------------------------------------------------------------
$tempMdFile = __DIR__ . '/scratch/temp_frontmatter_test.md';
@mkdir(__DIR__ . '/scratch', 0777, true);
file_put_contents($tempMdFile, $sampleMarkdown);

$activeChapter = [
    'title' => 'Object Types Introduction',
    'type' => 'markdown',
    'file' => 'tests/scratch/temp_frontmatter_test.md'
];

$extManager = ExtensionManager::getInstance();

// 6a. Default mode: collapse
$renderedCollapse = $extManager->renderPage($activeChapter, null, ['frontmatter' => 'collapse']);
assert_true(strpos($renderedCollapse, '<details class="qwiki-frontmatter"') !== false, 'Mode collapse includes collapsible details');
assert_true(strpos($renderedCollapse, '<h1 id="object-types-introduction">') !== false || strpos($renderedCollapse, '<h1>Object Types Introduction</h1>') !== false, 'Mode collapse cleanly renders Parsedown H1 body');

// 6b. Mode: hidden
$renderedHidden = $extManager->renderPage($activeChapter, null, ['frontmatter' => 'hidden']);
assert_true(strpos($renderedHidden, 'qwiki-frontmatter') === false, 'Mode hidden suppresses frontmatter widget completely');
assert_true(strpos($renderedHidden, 'This documentation is a resource') !== false, 'Mode hidden still renders clean Markdown body');

// Cleanup
@unlink($tempMdFile);

// --------------------------------------------------------------------------
// Summary
// --------------------------------------------------------------------------
echo "\n----------------------------------------------------\n";
echo "Frontmatter Tests Completed: Passed: $passed, Failed: $failed\n";

if ($failed > 0) {
    exit(1);
}
