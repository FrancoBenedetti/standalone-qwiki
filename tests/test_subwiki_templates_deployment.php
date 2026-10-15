<?php
/**
 * Test Suite: Subwiki Templates Deployment & Fallback Resolution (v1.20.1)
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\SubwikiManager;

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

echo "Running Subwiki Templates Deployment & Fallback Tests...\n\n";

$tempDir = sys_get_temp_dir() . '/qwiki_subwiki_tpl_test_' . uniqid();
@mkdir($tempDir, 0755, true);

// Create parent directory structure
@mkdir($tempDir . '/lib/Core', 0755, true);
@mkdir($tempDir . '/assets/css', 0755, true);
@mkdir($tempDir . '/api', 0755, true);
@mkdir($tempDir . '/templates/modals', 0755, true);

// Copy actual templates to temp parent
$actualTemplates = glob(__DIR__ . '/../templates/modals/*.php');
foreach ($actualTemplates as $tpl) {
    copy($tpl, $tempDir . '/templates/modals/' . basename($tpl));
}

// Copy minimal required lib, assets, api files
copy(__DIR__ . '/../lib/Core/Config.php', $tempDir . '/lib/Core/Config.php');
copy(__DIR__ . '/../lib/Core/SubwikiManager.php', $tempDir . '/lib/Core/SubwikiManager.php');
copy(__DIR__ . '/../lib/Core/Navigation.php', $tempDir . '/lib/Core/Navigation.php');
file_put_contents($tempDir . '/index.php', '<?php // parent index');
file_put_contents($tempDir . '/assets/css/qwiki.css', '/* css */');
file_put_contents($tempDir . '/api/admin.php', '<?php // admin');

$parentConfig = [
    'title' => 'Parent Wiki',
    'publishApiKey' => 'test-publish-key',
    'books' => []
];
file_put_contents($tempDir . '/qwiki.json', json_encode($parentConfig));
file_put_contents($tempDir . '/users.json', json_encode(['users' => []]));

Config::init($tempDir);

// 1. Test standalone deploySubwiki copies templates/
$deploy = SubwikiManager::deploySubwiki('team-alpha', 'Team Alpha', 'admin', 'Password123!');
assertTest(!empty($deploy['success']), 'deploySubwiki succeeded in standalone mode');
assertTest(is_dir($tempDir . '/team-alpha/templates/modals'), 'Standalone subwiki includes templates/modals directory');
assertTest(file_exists($tempDir . '/team-alpha/templates/modals/login.php'), 'templates/modals/login.php was copied to subwiki');
assertTest(file_exists($tempDir . '/team-alpha/templates/modals/settings.php'), 'templates/modals/settings.php was copied to subwiki');

// 2. Test fallback resolution in subwiki if templates folder is missing
function removeDirRecursive($dir) {
    if (!is_dir($dir)) return;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $p = $dir . '/' . $item;
        is_dir($p) ? removeDirRecursive($p) : @unlink($p);
    }
    @rmdir($dir);
}
removeDirRecursive($tempDir . '/team-alpha/templates');
assertTest(!is_dir($tempDir . '/team-alpha/templates'), 'Simulated subwiki without local templates directory');

// Evaluate the fallback logic from index.php
$subwikiDir = $tempDir . '/team-alpha';
$resolvedTemplatesDir = is_dir($subwikiDir . '/templates') ? ($subwikiDir . '/templates') : (dirname($subwikiDir) . '/templates');
assertTest($resolvedTemplatesDir === $tempDir . '/templates', 'TemplatesDir correctly fell back to parent templates');
assertTest(file_exists($resolvedTemplatesDir . '/modals/login.php'), 'Fallback templatesDir contains login.php');
assertTest(file_exists($resolvedTemplatesDir . '/modals/settings.php'), 'Fallback templatesDir contains settings.php');

// 3. Test cascadeUpdates restores templates to subwikis
$cascade = SubwikiManager::cascadeUpdates($tempDir);
assertTest(!empty($cascade['success']), 'cascadeUpdates succeeded');
assertTest(is_dir($tempDir . '/team-alpha/templates/modals'), 'cascadeUpdates restored templates/modals to subwiki');
assertTest(file_exists($tempDir . '/team-alpha/templates/modals/login.php'), 'templates/modals/login.php present after cascadeUpdates');

// 4. Test api/publish.php upload_image handler
$publishApi = __DIR__ . '/../api/publish.php';

// 4a. Reject invalid file extension
$fakeUploadScript = sprintf('
    require_once "%s/lib/Core/Config.php";
    \Qwiki\Core\Config::init("%s");
    $_POST = ["action" => "upload_image", "apiKey" => "test-publish-key"];
    $_FILES = [
        "image" => [
            "name" => "malicious.php",
            "type" => "application/x-php",
            "tmp_name" => "%s",
            "error" => UPLOAD_ERR_OK,
            "size" => 123
        ]
    ];
    require "%s";
', __DIR__ . '/..', $tempDir, $tempDir . '/dummy.txt', $publishApi);

file_put_contents($tempDir . '/dummy.txt', 'dummy content');
$out = shell_exec('php -r ' . escapeshellarg($fakeUploadScript));
$json = json_decode($out, true);
assertTest(isset($json['success']) && $json['success'] === false, 'upload_image rejects non-image extension');
assertTest(strpos($json['error'] ?? '', 'Invalid image format') !== false, 'upload_image returns error message on invalid format');

// 4b. Accept valid image
$pngPath = $tempDir . '/test_sample.png';
file_put_contents($pngPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
$validUploadScript = sprintf('
    require_once "%s/lib/Core/Config.php";
    \Qwiki\Core\Config::init("%s");
    $_SERVER["HTTP_HOST"] = "test.qwiki.local";
    $_SERVER["SCRIPT_NAME"] = "/api/publish.php";
    $_POST = ["action" => "upload_image", "apiKey" => "test-publish-key"];
    $_FILES = [
        "image" => [
            "name" => "hero-banner.png",
            "type" => "image/png",
            "tmp_name" => "%s",
            "error" => UPLOAD_ERR_OK,
            "size" => 70
        ]
    ];
    require "%s";
', __DIR__ . '/..', $tempDir, $pngPath, $publishApi);

$outValid = shell_exec('php -r ' . escapeshellarg($validUploadScript));
$jsonValid = json_decode($outValid, true);
assertTest(!empty($jsonValid['success']), 'upload_image succeeded for valid PNG');
assertTest(strpos($jsonValid['url'] ?? '', 'uploads/images/') === 0, 'upload_image returned relative url in uploads/images/');
assertTest(strpos($jsonValid['fullUrl'] ?? '', 'http://test.qwiki.local/uploads/images/') === 0, 'upload_image returned valid fullUrl with Config::getBaseUrl');
assertTest(file_exists($tempDir . '/' . $jsonValid['url']), 'Uploaded image file exists on disk');

// Cleanup
removeDirRecursive($tempDir);

echo "\n----------------------------------------------------\n";
echo "Subwiki Templates Deployment Tests Completed: Passed: {$passed}, Failed: {$failed}\n";
if ($failed > 0) {
    exit(1);
}
