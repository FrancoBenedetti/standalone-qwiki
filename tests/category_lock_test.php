<?php
require_once __DIR__ . '/../lib/Core/Config.php';
use Qwiki\Core\Config;

ob_start();
require_once __DIR__ . '/../api/admin.php';
ob_end_clean();

Config::init();

echo "Running Category Lock & Deletion Protection Tests...\n\n";

// ----------------------------------------------------
// Test Fixture Tree
// ----------------------------------------------------
$testTree = [
    [
        'id' => 'directly-locked-cat',
        'title' => 'Directly Locked Category',
        'type' => 'folder',
        'readOnly' => true,
        'items' => [
            [
                'title' => 'Inherited Protected Doc',
                'slug' => 'inherited-doc',
                'file' => 'content/directly-locked-cat/inherited-doc.md'
            ]
        ]
    ],
    [
        'id' => 'uneditable-cat',
        'title' => 'Uneditable Category',
        'type' => 'folder',
        'editable' => false,
        'items' => []
    ],
    [
        'id' => 'parent-with-locked-child',
        'title' => 'Parent With Locked Child Doc',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Regular Doc A',
                'slug' => 'regular-doc-a',
                'file' => 'content/parent/regular-a.md'
            ],
            [
                'title' => 'Protected Doc B',
                'slug' => 'protected-doc-b',
                'file' => 'content/parent/protected-b.md',
                'readOnly' => true
            ]
        ]
    ],
    [
        'id' => 'multi-level-parent',
        'title' => 'Multi Level Parent',
        'type' => 'folder',
        'items' => [
            [
                'id' => 'nested-child-folder',
                'title' => 'Nested Child Folder',
                'type' => 'folder',
                'items' => [
                    [
                        'title' => 'Deep Protected Doc',
                        'slug' => 'deep-protected-doc',
                        'file' => 'content/deep/protected.md',
                        'readOnly' => true
                    ]
                ]
            ]
        ]
    ],
    [
        'id' => 'regular-unlocked-cat',
        'title' => 'Regular Unlocked Category',
        'type' => 'folder',
        'items' => [
            [
                'title' => 'Regular Doc C',
                'slug' => 'regular-doc-c',
                'file' => 'content/regular/regular-c.md'
            ]
        ]
    ]
];

// 1. Direct Category Protection Checks
echo "1. Direct Category Protection Checks: ";
assert(Config::isCategoryProtected('directly-locked-cat', $testTree) === true, "Directly locked category must be protected");
assert(Config::isCategoryDirectlyProtected('directly-locked-cat', $testTree) === true, "Directly locked category must be detected as directly protected");
assert(Config::isCategoryProtected('uneditable-cat', $testTree) === true, "editable: false category must be protected");
assert(Config::isCategoryDirectlyProtected('uneditable-cat', $testTree) === true, "editable: false category must be detected as directly protected");
echo "PASS\n";

// 2. Transitive Category Protection via Child Documents
echo "2. Transitive Category Protection via Child Documents: ";
assert(Config::isCategoryProtected('parent-with-locked-child', $testTree) === true, "Category containing protected document must be protected from deletion");
assert(Config::isCategoryDirectlyProtected('parent-with-locked-child', $testTree) === false, "Parent category without direct lock should NOT be marked directly protected");
echo "PASS\n";

// 3. Multi-Level Nested Protection Checks
echo "3. Multi-Level Nested Protection Checks: ";
assert(Config::isCategoryProtected('nested-child-folder', $testTree) === true, "Nested folder containing protected document must be protected");
assert(Config::isCategoryProtected('multi-level-parent', $testTree) === true, "Ancestor folder containing nested folder with protected document must be protected");
assert(Config::isCategoryDirectlyProtected('nested-child-folder', $testTree) === false, "Nested folder is not directly locked");
assert(Config::isCategoryDirectlyProtected('multi-level-parent', $testTree) === false, "Ancestor folder is not directly locked");
echo "PASS\n";

// 4. Unprotected Category Checks
echo "4. Unprotected Category Checks: ";
assert(Config::isCategoryProtected('regular-unlocked-cat', $testTree) === false, "Category with no locked items must NOT be protected");
assert(Config::isCategoryDirectlyProtected('regular-unlocked-cat', $testTree) === false, "Regular category is not directly protected");
assert(Config::isCategoryProtected('non-existent-cat', $testTree) === false, "Non-existent category should return false");
echo "PASS\n";

// 5. Inherited Document Protection Checks
echo "5. Inherited Document Protection Checks: ";
assert(Config::isChapterProtected('inherited-doc', $testTree) === true, "Document inside a directly locked category must inherit protection");
assert(Config::isChapterProtected('regular-doc-a', $testTree) === false, "Regular document in un-locked parent should not be protected");
assert(Config::isChapterProtected('protected-doc-b', $testTree) === true, "Directly protected document must be protected");
assert(Config::isChapterProtected('regular-doc-c', $testTree) === false, "Document in regular category should not be protected");
echo "PASS\n";

// 6. Deletion Logic via delete_node_recursive
echo "6. Deletion Protection via delete_node_recursive: ";
$treeCopy = $testTree;

// Try to delete directly locked category
$deleted1 = delete_node_recursive($treeCopy, 'directly-locked-cat');
assert($deleted1 === false, "delete_node_recursive must block deleting directly locked category");
assert(count($treeCopy) === 5, "Tree must retain directly-locked-cat");

// Try to delete parent with locked child
$deleted2 = delete_node_recursive($treeCopy, 'parent-with-locked-child');
assert($deleted2 === false, "delete_node_recursive must block deleting parent of locked document");
assert(count($treeCopy) === 5, "Tree must retain parent-with-locked-child");

// Try to delete multi-level parent
$deleted3 = delete_node_recursive($treeCopy, 'multi-level-parent');
assert($deleted3 === false, "delete_node_recursive must block deleting multi-level parent");
assert(count($treeCopy) === 5, "Tree must retain multi-level-parent");

// Delete regular unlocked category
$deleted4 = delete_node_recursive($treeCopy, 'regular-unlocked-cat');
assert($deleted4 === true, "delete_node_recursive must allow deleting unprotected category");
assert(count($treeCopy) === 4, "Tree must have 4 categories remaining after deleting regular category");
echo "PASS\n";

// 7. Backward Compatibility Helper
echo "7. Backward Compatibility Helper: ";
assert(function_exists('is_category_protected'), "is_category_protected function must exist");
assert(is_category_protected('directly-locked-cat', $testTree) === true, "Helper must return true for protected category");
assert(is_category_protected('regular-unlocked-cat', $testTree) === false, "Helper must return false for unlocked category");
echo "PASS\n";

// 8. Updating Category Meta with readOnly
echo "8. update_node_meta with readOnly toggling: ";
$testNode = [
    'id' => 'sample-cat',
    'title' => 'Sample Category',
    'type' => 'folder',
    'theme' => '',
    'visibility' => 'public'
];

update_node_meta($testNode, 'sample-cat', 'New Title', 'theme-dark.css', 'logged_in', true);
assert($testNode['title'] === 'New Title', "Title should update");
assert($testNode['readOnly'] === true, "readOnly should be set to true");
assert($testNode['editable'] === false, "editable should be set to false");

update_node_meta($testNode, 'sample-cat', 'New Title', 'theme-dark.css', 'logged_in', false);
assert(!isset($testNode['readOnly']), "readOnly should be removed when toggled off");
assert(!isset($testNode['editable']), "editable should be removed when toggled off");
echo "PASS\n";

echo "\nALL CATEGORY LOCK & DELETION PROTECTION TESTS PASSED! 🎉\n";
