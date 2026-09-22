<?php
/**
 * Test Contextual Split Admin UI (Approach B)
 * Verifies sidebar content authoring actions and structured header dropdown
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;

Config::init();
Auth::startSession();

echo "Running Contextual Split Admin UI Tests...\n\n";

$failures = 0;

function assertCondition($name, $condition, &$failures) {
    if ($condition) {
        echo "✅ PASS: {$name}\n";
    } else {
        echo "❌ FAIL: {$name}\n";
        $failures++;
    }
}

// 1. Verify CSS rules
$cssContent = file_get_contents(__DIR__ . '/../assets/css/qwiki.css');
assertCondition("CSS: .dropdown-menu specifies width: 230px", strpos($cssContent, 'width: 230px;') !== false, $failures);
assertCondition("CSS: .dropdown-menu specifies min-width: 220px", strpos($cssContent, 'min-width: 220px;') !== false, $failures);
assertCondition("CSS: .sidebar-actions is defined", strpos($cssContent, '.sidebar-actions') !== false, $failures);
assertCondition("CSS: .btn-sidebar-add-doc is defined", strpos($cssContent, '.btn-sidebar-add-doc') !== false, $failures);
assertCondition("CSS: .btn-sidebar-add-cat is defined", strpos($cssContent, '.btn-sidebar-add-cat') !== false, $failures);
assertCondition("CSS: .dropdown-user-header is defined", strpos($cssContent, '.dropdown-user-header') !== false, $failures);
assertCondition("CSS: .dropdown-item-icon is defined", strpos($cssContent, '.dropdown-item-icon') !== false, $failures);

// 2. Setup server environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/index.php';

$instanceId = Auth::getInstanceIdentifier();
$_SESSION['qwiki_instance'] = $instanceId;

// 2a. Render as Admin
$_SESSION['qwiki_admin'] = true;
$_SESSION['qwiki_user'] = ['username' => 'admin_tester', 'role' => 'admin'];

ob_start();
include __DIR__ . '/../index.php';
$adminHtml = ob_get_clean();

assertCondition("Admin: .sidebar-actions is rendered in sidebar", strpos($adminHtml, 'class="sidebar-actions"') !== false, $failures);
assertCondition("Admin: #btn-add-chapter is rendered in sidebar", strpos($adminHtml, 'id="btn-add-chapter"') !== false, $failures);
assertCondition("Admin: #btn-add-book is rendered in sidebar", strpos($adminHtml, 'id="btn-add-book"') !== false, $failures);
assertCondition("Admin: .dropdown-user-header is rendered in header dropdown", strpos($adminHtml, 'class="dropdown-user-header"') !== false, $failures);
assertCondition("Admin: Tools & Utilities section header is rendered", strpos($adminHtml, 'Tools &amp; Utilities') !== false || strpos($adminHtml, 'Tools & Utilities') !== false, $failures);
assertCondition("Admin: Administration section header is rendered", strpos($adminHtml, 'Administration') !== false, $failures);
assertCondition("Admin: Dropdown items use .dropdown-item-icon", strpos($adminHtml, 'class="dropdown-item-icon"') !== false, $failures);

// Verify that "+ Category" and "+ Document" are NOT in the dropdown menu
$dropdownMenuPos = strpos($adminHtml, 'class="dropdown-menu"');
if ($dropdownMenuPos !== false) {
    $dropdownSegment = substr($adminHtml, $dropdownMenuPos, 2200);
    assertCondition("Admin: Dropdown menu does not contain old + Category button", strpos($dropdownSegment, '>+ Category</button>') === false, $failures);
    assertCondition("Admin: Dropdown menu does not contain old + Document button", strpos($dropdownSegment, '>+ Document</button>') === false, $failures);
} else {
    assertCondition("Admin: Dropdown menu found", false, $failures);
}

// 2b. Check Viewer rendering via CLI execution
$viewerOutput = shell_exec('php -r \'
require_once "lib/Core/Config.php";
require_once "lib/Core/Auth.php";
use Qwiki\\Core\\Config;
use Qwiki\\Core\\Auth;
Config::init();
Auth::startSession();
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SCRIPT_NAME"] = "/index.php";
$_SERVER["REQUEST_URI"] = "/index.php";
$inst = Auth::getInstanceIdentifier();
$_SESSION["qwiki_instance"] = $inst;
$_SESSION["qwiki_user"] = ["username" => "viewer_tester", "role" => "viewer"];
unset($_SESSION["qwiki_admin"]);
ob_start();
include "index.php";
$html = ob_get_clean();
echo (strpos($html, "class=\"sidebar-actions\"") === false && strpos($html, "Administration</div>") === false) ? "VIEWER_OK" : "VIEWER_FAIL";
\'');
assertCondition("Viewer: .sidebar-actions and Administration are suppressed", trim($viewerOutput) === 'VIEWER_OK', $failures);

// 2c. Check Guest rendering via CLI execution
$guestOutput = shell_exec('php -r \'
require_once "lib/Core/Config.php";
require_once "lib/Core/Auth.php";
use Qwiki\\Core\\Config;
use Qwiki\\Core\\Auth;
Config::init();
Auth::startSession();
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["HTTP_HOST"] = "localhost";
$_SERVER["SCRIPT_NAME"] = "/index.php";
$_SERVER["REQUEST_URI"] = "/index.php";
unset($_SESSION["qwiki_admin"]);
unset($_SESSION["qwiki_user"]);
ob_start();
include "index.php";
$html = ob_get_clean();
echo (strpos($html, "class=\"sidebar-actions\"") === false) ? "GUEST_OK" : "GUEST_FAIL";
\'');
assertCondition("Guest: .sidebar-actions is suppressed", trim($guestOutput) === 'GUEST_OK', $failures);

echo "\nTests Complete: " . ($failures === 0 ? "ALL PASSED! 🎉" : "{$failures} FAILED! ❌") . "\n";
exit($failures > 0 ? 1 : 0);
