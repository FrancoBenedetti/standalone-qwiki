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

if (strpos($viewerHtml, 'id="modal-share-telegram"') === false) {
    echo "FAIL: modal-share-telegram must be present inside share-modal for viewers\n";
    exit(1);
}
if (strpos($viewerHtml, 'id="modal-share-slack"') === false) {
    echo "FAIL: modal-share-slack must be present inside share-modal for viewers\n";
    exit(1);
}
echo "3b. Telegram and Slack sharing buttons are present in share-modal (PASS)\n";

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

// 6. Test Zen Reader floating bar rendering for Telegram and Slack
parse_str(parse_url($guestJson['shareUrl'], PHP_URL_QUERY) ?? '', $queryParams);
$shareKey = $queryParams['share'] ?? '';
if (!empty($shareKey)) {
    $_GET = ['share' => $shareKey];
    $_REQUEST = $_GET;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/index.php?share=' . urlencode($shareKey);
    ob_start();
    include __DIR__ . '/../index.php';
    $shareModeHtml = ob_get_clean();

    if (strpos($shareModeHtml, 'https://t.me/share/url') === false) {
        echo "FAIL: Share mode floating bar must contain Telegram share link\n";
        exit(1);
    }
    if (strpos($shareModeHtml, 'id="btn-share-slack-bar"') === false) {
        echo "FAIL: Share mode floating bar must contain Slack share button\n";
        exit(1);
    }
    echo "9. Zen reader floating bar contains Telegram and Slack share actions (PASS)\n";
}

// 7. Test API: Unauthenticated guest generating share key for public doc without existing shareKey,
// and rejection for publicShareable=false or logged_in visibility docs.
$qwikiJsonPath = __DIR__ . '/../qwiki.json';
$origQwikiJson = file_get_contents($qwikiJsonPath);
try {
    $testConfig = json_decode($origQwikiJson, true);
    $testConfig['books'][] = [
        'id' => 'test-share-rights-book',
        'title' => 'Test Share Rights Book',
        'visibility' => 'public',
        'items' => [
            [
                'title' => 'Public No Key',
                'slug' => 'test-public-no-key',
                'type' => 'markdown',
                'file' => 'content/getting-started/introduction.md',
                'status' => 'published',
            ],
            [
                'title' => 'Public Disabled Share',
                'slug' => 'test-public-disabled-share',
                'type' => 'markdown',
                'file' => 'content/getting-started/introduction.md',
                'status' => 'published',
                'publicShareable' => false,
            ],
            [
                'title' => 'Logged In Only',
                'slug' => 'test-logged-in-only',
                'type' => 'markdown',
                'file' => 'content/getting-started/introduction.md',
                'status' => 'published',
                'visibility' => 'logged_in',
            ],
        ],
    ];
    file_put_contents($qwikiJsonPath, json_encode($testConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $runGuestShare = function (string $slug): array {
        $cmd = sprintf(
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
            ', __DIR__, __DIR__, $slug, __DIR__))
        );
        return json_decode(trim(shell_exec($cmd)), true) ?: [];
    };

    $resPublic = $runGuestShare('test-public-no-key');
    if (empty($resPublic['success']) || empty($resPublic['shareUrl']) || strpos($resPublic['shareUrl'], '?share=') === false) {
        echo "FAIL: Guest must be able to generate share link for public doc without existing key: " . json_encode($resPublic) . "\n";
        exit(1);
    }
    echo "10. Unauthenticated guest can generate share link for public doc without existing shareKey (PASS)\n";

    $resDisabled = $runGuestShare('test-public-disabled-share');
    if (!empty($resDisabled['success'])) {
        echo "FAIL: Guest must NOT get share link when publicShareable is false\n";
        exit(1);
    }
    echo "11. Unauthenticated guest is denied when publicShareable is false (PASS)\n";

    $resRestricted = $runGuestShare('test-logged-in-only');
    if (!empty($resRestricted['success'])) {
        echo "FAIL: Guest must NOT get share link for logged_in-only doc\n";
        exit(1);
    }
    echo "12. Unauthenticated guest is denied for logged_in-only doc (PASS)\n";
} finally {
    file_put_contents($qwikiJsonPath, $origQwikiJson);
}

echo "\nALL SHARE RIGHTS TESTS PASSED! 🎉\n";

