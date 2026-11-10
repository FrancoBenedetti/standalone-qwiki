<?php
/**
 * Test Category Creation UX, Auto-Slug Generation & Validation
 *
 * Verifies:
 * 1. Category creation succeeds with an empty slug input (auto-generated from title).
 * 2. Slug collision auto-increments for auto-generated slugs.
 * 3. Custom slug collision returns a clear error.
 * 4. check_category_slug endpoint correctly reports availability, suggestions, and reserved names.
 * 5. Nested category creation with empty slug properly nests under parent.
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\SubwikiManager;

echo "Running Category Creation UX & Slug Tests...\n\n";

$failures = 0;

function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

// Backup current qwiki.json
$baseDir = Config::getBaseDir();
$configFile = Config::getConfigFile();
$originalConfig = file_get_contents($configFile);

register_shutdown_function(function() use ($configFile, $originalConfig) {
    file_put_contents($configFile, $originalConfig);
});

// Helper function to dispatch mock requests to api/admin.php via isolated sub-process
function dispatchAdminAction(array $post = [], array $get = []) {
    $script = sprintf('
        require_once "%s/../lib/Core/Config.php";
        require_once "%s/../lib/Core/Auth.php";
        require_once "%s/../lib/Core/Navigation.php";
        require_once "%s/../lib/Core/SubwikiManager.php";
        \Qwiki\Core\Config::init();
        \Qwiki\Core\Auth::startSession();
        $_SESSION["qwiki_instance"] = \Qwiki\Core\Auth::getInstanceIdentifier();
        $_SESSION["qwiki_user"] = ["username" => "admin", "role" => "admin", "fullName" => "Administrator"];
        $_SESSION["qwiki_admin"] = true;
        $_POST = %s;
        $_GET = %s;
        $_SERVER["REQUEST_METHOD"] = !empty($_POST) ? "POST" : "GET";
        $_REQUEST = array_merge($_GET, $_POST);
        include "%s/../api/admin.php";
    ', __DIR__, __DIR__, __DIR__, __DIR__, var_export($post, true), var_export($get, true), __DIR__);

    $cmd = sprintf('php -r %s', escapeshellarg($script));
    $output = trim(shell_exec($cmd));
    $json = json_decode($output, true);
    return is_array($json) ? $json : ['raw' => $output];
}

// ----------------------------------------------------
// 1. Test check_category_slug
// ----------------------------------------------------
echo "--- 1. check_category_slug Validation ---\n";

// A. Available slug
$res = dispatchAdminAction([], ['action' => 'check_category_slug', 'slug' => 'brand-new-cat-' . bin2hex(random_bytes(4))]);
assertCondition("Available slug returns valid: true", ($res['valid'] ?? false) === true && ($res['isTaken'] ?? true) === false, $failures);

// B. Reserved name
$res = dispatchAdminAction([], ['action' => 'check_category_slug', 'slug' => 'assets']);
assertCondition("Reserved system name returns valid: false", ($res['valid'] ?? true) === false, $failures);

// C. Existing category (auto suggestion mode)
$res = dispatchAdminAction([], ['action' => 'check_category_slug', 'slug' => 'getting-started', 'is_custom' => '0']);
assertCondition("Taken slug in auto mode returns valid: true with suggested increment", ($res['valid'] ?? false) === true && ($res['isTaken'] ?? false) === true && !empty($res['suggested']), $failures);

// D. Existing category (custom explicit mode)
$res = dispatchAdminAction([], ['action' => 'check_category_slug', 'slug' => 'getting-started', 'is_custom' => '1']);
assertCondition("Taken slug in custom mode returns valid: false", ($res['valid'] ?? true) === false && ($res['isTaken'] ?? false) === true && !empty($res['error']), $failures);


// ----------------------------------------------------
// 2. Test Category Creation with Empty Slug (Auto-Slug)
// ----------------------------------------------------
echo "\n--- 2. Category Creation with Empty Slug ---\n";

$testTitle = 'UX Test Unique Category';
$res = dispatchAdminAction([
    'action' => 'add_book',
    'title' => $testTitle,
    'description' => 'A test category created with empty slug',
    'id' => '',
    'parentId' => '',
    'isCustomSlug' => '0'
]);

assertCondition("Category creation with empty slug succeeds", ($res['success'] ?? false) === true, $failures);
$createdBookId = $res['bookId'] ?? '';
assertCondition("Created category has valid generated slug", $createdBookId === 'ux-test-unique-category', $failures);

// Verify category directory created on disk
$createdFolder = $baseDir . '/content/' . $createdBookId;
assertCondition("Category directory created on disk", is_dir($createdFolder), $failures);


// ----------------------------------------------------
// 3. Test Auto-Increment on Duplicate Title / Slug
// ----------------------------------------------------
echo "\n--- 3. Auto-Increment on Auto-Slug Collision ---\n";

$resDuplicate = dispatchAdminAction([
    'action' => 'add_book',
    'title' => $testTitle, // Same title
    'description' => 'Another category with same title',
    'id' => '',
    'parentId' => '',
    'isCustomSlug' => '0'
]);

assertCondition("Duplicate title with auto-slug succeeds with auto-increment", ($resDuplicate['success'] ?? false) === true, $failures);
$duplicateBookId = $resDuplicate['bookId'] ?? '';
assertCondition("Auto-incremented slug is 'ux-test-unique-category-1'", $duplicateBookId === 'ux-test-unique-category-1', $failures);


// ----------------------------------------------------
// 4. Test Custom Slug Collision Rejection
// ----------------------------------------------------
echo "\n--- 4. Custom Slug Collision Rejection ---\n";

$resCustomConflict = dispatchAdminAction([
    'action' => 'add_book',
    'title' => 'Conflicting Custom Slug',
    'id' => $createdBookId, // Explicitly pass existing slug
    'parentId' => '',
    'isCustomSlug' => '1'
]);

assertCondition("Custom conflicting slug is rejected", ($resCustomConflict['success'] ?? true) === false, $failures);
assertCondition("Error message mentions slug already exists", strpos($resCustomConflict['error'] ?? '', 'already exists') !== false, $failures);


// ----------------------------------------------------
// 5. Test Nested Category with Empty Slug
// ----------------------------------------------------
echo "\n--- 5. Nested Category with Empty Slug ---\n";

$nestedTitle = 'Nested UX Subfolder';
$resNested = dispatchAdminAction([
    'action' => 'add_book',
    'title' => $nestedTitle,
    'id' => '',
    'parentId' => $createdBookId,
    'isCustomSlug' => '0'
]);

assertCondition("Nested category with empty slug succeeds", ($resNested['success'] ?? false) === true, $failures);
$nestedBookId = $resNested['bookId'] ?? '';
$nestedFolder = $createdFolder . '/' . $nestedBookId;
assertCondition("Nested category directory created in parent folder on disk", is_dir($nestedFolder), $failures);


// ----------------------------------------------------
// Cleanup test directories
// ----------------------------------------------------
if (is_dir($nestedFolder)) {
    @rmdir($nestedFolder);
}
if (is_dir($createdFolder)) {
    @rmdir($createdFolder);
}
if (!empty($duplicateBookId) && is_dir($baseDir . '/content/' . $duplicateBookId)) {
    @rmdir($baseDir . '/content/' . $duplicateBookId);
}

// Restore original config
file_put_contents($configFile, $originalConfig);

echo "\n----------------------------------------------------\n";
if ($failures === 0) {
    echo "🎉 ALL CATEGORY CREATION UX TESTS PASSED!\n";
    exit(0);
} else {
    echo "❌ {$failures} TEST(S) FAILED.\n";
    exit(1);
}
