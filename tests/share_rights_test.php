<?php
// Test Share Rights for Viewers & Unauthenticated Visitors

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;

Config::init();
Auth::startSession();

echo "Running Share Rights & Modal Rendering Tests...\n\n";

$config = Config::load();
$instanceId = Auth::getInstanceIdentifier();

// 1. Verify roles
$_SESSION['qwiki_instance'] = $instanceId;
$_SESSION['qwiki_user'] = ['username' => 'test_viewer', 'role' => 'viewer'];
unset($_SESSION['qwiki_admin']);

if (Auth::isAdmin()) {
    echo "FAIL: test_viewer must not be admin\n";
    exit(1);
}
if (!Auth::isViewer()) {
    echo "FAIL: test_viewer must be viewer\n";
    exit(1);
}
echo "1. Auth roles verified: viewer != admin (PASS)\n";

// 2. Render index.php output with viewer session and capture HTML
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/index.php';

ob_start();
include __DIR__ . '/../index.php';
$viewerHtml = ob_get_clean();

if (strpos($viewerHtml, 'id="share-modal"') === false) {
    echo "FAIL: share-modal must be rendered for viewers with view rights\n";
    exit(1);
}
echo "2. Share modal is present for viewer (PASS)\n";

if (strpos($viewerHtml, 'id="btn-copy-share-link"') === false) {
    echo "FAIL: btn-copy-share-link must be present inside share-modal for viewers\n";
    exit(1);
}
echo "3. Copy share link button is present for viewer (PASS)\n";

if (strpos($viewerHtml, 'id="share-admin-toggle-public"') !== false) {
    echo "FAIL: Admin toggle 'share-admin-toggle-public' must NOT be rendered for viewers\n";
    exit(1);
}
echo "4. Admin controls hidden from viewer in share modal (PASS)\n";

if (strpos($viewerHtml, 'id="btn-modal-regenerate-share-key"') !== false) {
    echo "FAIL: Admin reset key button must NOT be rendered for viewers\n";
    exit(1);
}
echo "5. Admin reset key button hidden from viewer in share modal (PASS)\n";

// 3. Verify btn-share-chapter has data-share-url attribute
if (strpos($viewerHtml, 'data-share-url=') === false) {
    echo "FAIL: btn-share-chapter must contain data-share-url attribute\n";
    exit(1);
}
echo "6. btn-share-chapter contains data-share-url attribute (PASS)\n";

// 4. Test API: get_or_create_share_key as Viewer
$firstSlug = '';
foreach ($config['books'] as $b) {
    if (!empty($b['items'])) {
        foreach ($b['items'] as $item) {
            if (!empty($item['slug'])) {
                $firstSlug = $item['slug'];
                break 2;
            }
        }
    }
}

if (empty($firstSlug)) {
    echo "FAIL: No slug found in config to test\n";
    exit(1);
}

// 4. Test API: get_or_create_share_key as Viewer in isolated sub-process
$cmdViewer = sprintf(
    'php -r %s',
    escapeshellarg(sprintf('
        require_once "%s/../lib/Core/Config.php";
        require_once "%s/../lib/Core/Auth.php";
        \Qwiki\Core\Config::init();
        \Qwiki\Core\Auth::startSession();
        $_SESSION["qwiki_instance"] = \Qwiki\Core\Auth::getInstanceIdentifier();
        $_SESSION["qwiki_user"] = ["username" => "viewer_user", "role" => "viewer"];
        $_GET["action"] = "get_or_create_share_key";
        $_GET["slug"] = "%s";
        $_REQUEST = $_GET;
        include "%s/../api/admin.php";
    ', __DIR__, __DIR__, $firstSlug, __DIR__))
);

$apiOutput = trim(shell_exec($cmdViewer));
$apiJson = json_decode($apiOutput, true);

if (empty($apiJson['success']) || empty($apiJson['shareUrl'])) {
    echo "FAIL: Viewer get_or_create_share_key returned failure: {$apiOutput}\n";
    exit(1);
}

if (strpos($apiJson['shareUrl'], '?share=') === false) {
    echo "FAIL: shareUrl must contain '?share=', got: {$apiJson['shareUrl']}\n";
    exit(1);
}
echo "7. Viewer can generate/retrieve ?share= link via API: {$apiJson['shareUrl']} (PASS)\n";

// 5. Test API: Unauthenticated guest retrieving existing public share key in isolated sub-process
$cmdGuest = sprintf(
    'php -r %s',
    escapeshellarg(sprintf('
        require_once "%s/../lib/Core/Config.php";
        require_once "%s/../lib/Core/Auth.php";
        \Qwiki\Core\Config::init();
        \Qwiki\Core\Auth::startSession();
        unset($_SESSION["qwiki_user"]);
        unset($_SESSION["qwiki_admin"]);
        $_GET["action"] = "get_or_create_share_key";
        $_GET["slug"] = "%s";
        $_REQUEST = $_GET;
        include "%s/../api/admin.php";
    ', __DIR__, __DIR__, $firstSlug, __DIR__))
);

$guestOutput = trim(shell_exec($cmdGuest));
$guestJson = json_decode($guestOutput, true);

if (empty($guestJson['success']) || empty($guestJson['shareUrl'])) {
    echo "FAIL: Guest should be able to retrieve existing public share key, got: {$guestOutput}\n";
    exit(1);
}
echo "8. Unauthenticated guest can retrieve existing public share link (PASS)\n";

echo "\nALL SHARE RIGHTS TESTS PASSED! 🎉\n";

