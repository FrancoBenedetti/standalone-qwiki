<?php
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Navigation;

ob_start();
require_once __DIR__ . '/../api/admin.php';
ob_end_clean();

echo "Running Category Description & Tooltip Tests...\n\n";

// 1. Test Navigation::renderSidebarNode with description
$testNode = [
    'id' => 'test-cat',
    'title' => 'Test Category',
    'description' => 'This is a test category description for tooltip',
    'type' => 'folder',
    'items' => []
];

ob_start();
Navigation::renderSidebarNode($testNode, 'test-cat', [], '', 0, true, true, false, null);
$html = ob_get_clean();

assert(strpos($html, "title='This is a test category description for tooltip'") !== false, "Category header must contain title attribute with description for tooltip");
assert(strpos($html, "data-node-description='This is a test category description for tooltip'") !== false, "Draggable category item must have data-node-description");
assert(strpos($html, "data-book-description='This is a test category description for tooltip'") !== false, "Edit category button must have data-book-description");
echo "1. Navigation::renderSidebarNode with description: PASS\n";

// 2. Test Navigation::renderSidebarNode without description
$testNodeNoDesc = [
    'id' => 'no-desc-cat',
    'title' => 'No Desc Category',
    'type' => 'folder',
    'items' => []
];

ob_start();
Navigation::renderSidebarNode($testNodeNoDesc, 'no-desc-cat', [], '', 0, true, true, false, null);
$htmlNoDesc = ob_get_clean();

assert(strpos($htmlNoDesc, "<div class='nav-category-header'>") !== false, "Category header without description should not have empty title attribute");
echo "2. Navigation::renderSidebarNode without description: PASS\n";

// 3. Test update_node_meta helper in api/admin.php
$treeNode = [
    'id' => 'meta-cat',
    'title' => 'Original Title',
    'type' => 'folder',
    'items' => [
        [
            'id' => 'nested-cat',
            'title' => 'Nested Title',
            'type' => 'folder',
            'items' => []
        ]
    ]
];

// Update root category description
$res = update_node_meta($treeNode, 'meta-cat', 'Updated Title', '', 'public', null, 'New root description');
assert($res === true, "update_node_meta should return true for existing category");
assert($treeNode['title'] === 'Updated Title', "Title should be updated");
assert($treeNode['description'] === 'New root description', "Description should be updated");
echo "3. update_node_meta root category description: PASS\n";

// Update nested category description
$resNested = update_node_meta($treeNode, 'nested-cat', 'Updated Nested', '', 'public', null, 'New nested description');
assert($resNested === true, "update_node_meta should return true for nested category");
assert($treeNode['items'][0]['description'] === 'New nested description', "Nested category description should be updated");
echo "4. update_node_meta nested category description: PASS\n";

// Unset description when empty
$resClear = update_node_meta($treeNode, 'meta-cat', 'Updated Title', '', 'public', null, '');
assert($resClear === true, "update_node_meta should clear description when empty");
assert(!isset($treeNode['description']), "Description should be unset when empty string passed");
echo "5. update_node_meta clear description: PASS\n";

echo "\nALL CATEGORY DESCRIPTION TESTS PASSED! 🚀\n";
