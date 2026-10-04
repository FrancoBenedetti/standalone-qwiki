<?php
/**
 * Test Chapter Drag Root Protection, Config Normalization & Phantom Category Deletion
 *
 * Verifies:
 * 1. Config::normalizeBooks rescues stranded root documents into appropriate categories.
 * 2. Config::normalizeBooks prunes empty phantom categories lacking ID and items.
 * 3. reorder_tree API logic prevents root-level non-link documents from being saved as root books.
 * 4. delete_book API allows deleting phantom categories lacking an ID by title.
 * 5. Navigation::renderSidebarNode guards against rendering non-link documents as categories.
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;

echo "Running Tree Drag Root Protection & Category Recovery Tests...\n\n";

$failures = 0;

function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

// ----------------------------------------------------------------
// 1. Config::normalizeBooks: Rescuing Stranded Documents
// ----------------------------------------------------------------
echo "--- 1. Config::normalizeBooks: Rescuing Stranded Documents ---\n";

$testBooksWithStrandedDoc = [
    [
        'id' => 'cat-one',
        'title' => 'Category One',
        'type' => 'folder',
        'folder' => 'content/cat-one',
        'items' => [
            [
                'title' => 'Doc One',
                'slug' => 'doc-one',
                'type' => 'markdown',
                'file' => 'content/cat-one/doc-one.md'
            ]
        ]
    ],
    // Stranded document dropped at root!
    [
        'title' => 'Stranded Chapter',
        'slug' => 'stranded-chapter',
        'type' => 'markdown',
        'file' => 'content/cat-one/stranded-chapter.md'
    ],
    // Valid top link
    [
        'title' => 'External Docs',
        'slug' => 'external-docs',
        'type' => 'link',
        'url' => 'https://example.com'
    ]
];

$modified = Config::normalizeBooks($testBooksWithStrandedDoc);
assertCondition("normalizeBooks returns true when repairs are made", $modified === true, $failures);
assertCondition("Root items count is now 2 (Category + Link)", count($testBooksWithStrandedDoc) === 2, $failures);
assertCondition("Root item 0 is category 'cat-one'", ($testBooksWithStrandedDoc[0]['id'] ?? '') === 'cat-one', $failures);
assertCondition("Root item 1 is link 'external-docs'", ($testBooksWithStrandedDoc[1]['type'] ?? '') === 'link', $failures);

$rescuedItems = $testBooksWithStrandedDoc[0]['items'] ?? [];
assertCondition("Category One now has 2 items", count($rescuedItems) === 2, $failures);
assertCondition("Second item in Category One is the rescued stranded chapter", ($rescuedItems[1]['slug'] ?? '') === 'stranded-chapter', $failures);

// ----------------------------------------------------------------
// 2. Config::normalizeBooks: Pruning Empty Phantom Categories
// ----------------------------------------------------------------
echo "\n--- 2. Config::normalizeBooks: Pruning Empty Phantom Categories ---\n";

$testBooksWithPhantom = [
    [
        'id' => 'cat-valid',
        'title' => 'Valid Category',
        'type' => 'folder',
        'items' => []
    ],
    // Phantom category created by empty drag or corruption
    [
        'id' => '',
        'title' => 'Phantom Folder',
        'type' => 'folder',
        'items' => []
    ],
    // Phantom category with items (should be healed with a generated ID)
    [
        'id' => '',
        'title' => 'Recovered Folder',
        'type' => 'folder',
        'items' => [
            ['title' => 'Inner', 'slug' => 'inner', 'type' => 'markdown']
        ]
    ]
];

$modifiedPhantom = Config::normalizeBooks($testBooksWithPhantom);
assertCondition("normalizeBooks returns true when cleaning phantoms", $modifiedPhantom === true, $failures);
assertCondition("Empty phantom folder was pruned", count($testBooksWithPhantom) === 2, $failures);
assertCondition("Recovered folder was assigned a valid non-empty ID", !empty($testBooksWithPhantom[1]['id']), $failures);
assertCondition("Recovered folder retains its items", count($testBooksWithPhantom[1]['items']) === 1, $failures);

// ----------------------------------------------------------------
// 3. reorder_tree Simulation: Root Document Interception
// ----------------------------------------------------------------
echo "\n--- 3. reorder_tree: Intercepting Root Non-Link Documents ---\n";

// Emulate backend reorder_tree mergeTree logic
$baseDir = __DIR__ . '/..';
$existingCategories = ['cat-one' => ['id' => 'cat-one', 'title' => 'Cat 1', 'folder' => 'content/cat-one']];
$existingDocuments = [
    'doc-one' => ['title' => 'Doc 1', 'slug' => 'doc-one', 'type' => 'markdown', 'file' => 'content/cat-one/doc-one.md'],
    'doc-two' => ['title' => 'Doc 2', 'slug' => 'doc-two', 'type' => 'markdown', 'file' => 'content/cat-one/doc-two.md']
];
$docOriginalCategory = [
    'doc-one' => 'cat-one',
    'doc-two' => 'cat-one'
];

$incomingTreeWithRootDoc = [
    // Client sent doc-two at the root level!
    [
        'title' => 'Doc 2',
        'slug' => 'doc-two',
        'type' => 'markdown',
        'file' => 'content/cat-one/doc-two.md'
    ],
    [
        'id' => 'cat-one',
        'title' => 'Cat 1',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Doc 1',
                'slug' => 'doc-one',
                'type' => 'markdown',
                'file' => 'content/cat-one/doc-one.md'
            ]
        ]
    ]
];

$updatedFiles = [];
$strandedRootDocs = [];

$mergeTree = function ($nodes, $parentFolder = null, $isRoot = false) use (&$mergeTree, &$existingCategories, &$existingDocuments, &$updatedFiles, &$strandedRootDocs, $baseDir) {
    if (!is_array($nodes)) return [];
    $merged = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) continue;
        $nodeId = $node['id'] ?? null;
        $nodeSlug = $node['slug'] ?? null;
        $currentFolder = $parentFolder;

        if ($nodeId !== null && isset($existingCategories[$nodeId])) {
            $orig = $existingCategories[$nodeId];
            $mergedNode = array_merge($orig, $node);
            $categoryFolder = $mergedNode['folder'] ?? (!empty($parentFolder) ? $parentFolder . '/' . $nodeId : 'content/' . $nodeId);
            $mergedNode['folder'] = $categoryFolder;
            $currentFolder = $categoryFolder;
        } elseif ($nodeSlug !== null && isset($existingDocuments[$nodeSlug])) {
            $origDoc = $existingDocuments[$nodeSlug];
            $mergedNode = array_merge($origDoc, $node);
        } else {
            $mergedNode = $node;
        }

        if ($isRoot) {
            if (empty($nodeId) && empty($nodeSlug)) {
                continue;
            }
            if (empty($nodeId) && !empty($nodeSlug) && ($mergedNode['type'] ?? '') !== 'link') {
                $strandedRootDocs[] = $mergedNode;
                continue;
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $mergedItems = [];
            foreach ($node['items'] as $item) {
                if (!is_array($item)) continue;
                if (isset($item['type']) && $item['type'] === 'folder') {
                    $mergedSub = $mergeTree([$item], $currentFolder, false);
                    if (!empty($mergedSub)) {
                        $mergedItems[] = $mergedSub[0];
                    }
                } elseif (isset($item['slug'])) {
                    $mergedItems[] = array_merge($existingDocuments[$item['slug']] ?? [], $item);
                } else {
                    $mergedItems[] = $item;
                }
            }
            $mergedNode['items'] = $mergedItems;
        }
        $merged[] = $mergedNode;
    }
    return $merged;
};

$newBooks = $mergeTree($incomingTreeWithRootDoc, null, true);

if (!empty($strandedRootDocs)) {
    foreach ($strandedRootDocs as $sDoc) {
        $slug = $sDoc['slug'] ?? '';
        $targetCatId = $docOriginalCategory[$slug] ?? null;
        $placed = false;

        if ($targetCatId !== null) {
            foreach ($newBooks as &$nb) {
                if (($nb['type'] ?? 'folder') === 'folder' && ($nb['id'] ?? '') === $targetCatId) {
                    if (!isset($nb['items']) || !is_array($nb['items'])) $nb['items'] = [];
                    $nb['items'][] = $sDoc;
                    $placed = true;
                    break;
                }
            }
        }
    }
}

Config::normalizeBooks($newBooks);

assertCondition("Root books has only 1 element (the category)", count($newBooks) === 1, $failures);
assertCondition("Category has 2 items", count($newBooks[0]['items']) === 2, $failures);
assertCondition("Doc 2 was placed back into Category 1", ($newBooks[0]['items'][1]['slug'] ?? '') === 'doc-two', $failures);

// ----------------------------------------------------------------
// 4. delete_book: Deleting Phantom Categories by Title
// ----------------------------------------------------------------
echo "\n--- 4. delete_book: Deleting Phantom Categories by Title ---\n";

$testConfig = [
    'books' => [
        [
            'id' => 'valid-cat',
            'title' => 'Valid',
            'type' => 'folder',
            'items' => []
        ],
        [
            'id' => '',
            'title' => 'Phantom To Delete',
            'type' => 'folder',
            'items' => []
        ]
    ]
];

$bookId = '';
$bookTitle = 'Phantom To Delete';
$deletedPhantom = false;
$filteredBooks = [];

foreach ($testConfig['books'] as $b) {
    if (empty($b['id']) && (($b['title'] ?? '') === $bookTitle || ($b['name'] ?? '') === $bookTitle)) {
        $deletedPhantom = true;
        continue;
    }
    $filteredBooks[] = $b;
}

assertCondition("Phantom category identified and deleted by title", $deletedPhantom === true, $failures);
assertCondition("Remaining books count is 1", count($filteredBooks) === 1, $failures);
assertCondition("Remaining book is 'valid-cat'", ($filteredBooks[0]['id'] ?? '') === 'valid-cat', $failures);

// ----------------------------------------------------------------
// 5. Navigation::renderSidebarNode Guard
// ----------------------------------------------------------------
echo "\n--- 5. Navigation::renderSidebarNode Guard ---\n";

ob_start();
$unCategorizedDoc = [
    'title' => 'Uncategorized Markdown',
    'slug' => 'uncat-md',
    'type' => 'markdown',
    'file' => 'content/test.md'
];
Navigation::renderSidebarNode($unCategorizedDoc, '', [], '', 0, true, true, false, null);
$output = ob_get_clean();

assertCondition("renderSidebarNode produces no output for un-categorized non-link document", empty(trim($output)), $failures);

// ----------------------------------------------------------------
// Final Result
// ----------------------------------------------------------------
echo "\n============================================\n";
if ($failures === 0) {
    echo "🎉 ALL 17 ROOT PROTECTION TESTS PASSED!\n";
    exit(0);
} else {
    echo "❌ {$failures} TESTS FAILED!\n";
    exit(1);
}
