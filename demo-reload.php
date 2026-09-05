<?php
/**
 * Standalone Qwiki - Demo Reload Utility
 *
 * Clears out content and user data added by visitors and restores
 * the demo package back to a clean, fresh state.
 *
 * Usage:
 *   CLI: php demo-reload.php [--quiet]
 *   Web: GET /demo-reload.php?token=<feedAccessToken> (or as logged-in admin)
 */

require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Auth.php';
require_once __DIR__ . '/lib/Core/DemoManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\DemoManager;

Config::init();

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $args = $argv ?? [];
    $isQuiet = in_array('--quiet', $args) || in_array('-q', $args);
    $showHelp = in_array('--help', $args) || in_array('-h', $args);

    if ($showHelp) {
        echo "Standalone Qwiki Demo Reload Utility\n";
        echo "Usage: php demo-reload.php [options]\n\n";
        echo "Options:\n";
        echo "  -q, --quiet    Suppress output messages\n";
        echo "  -h, --help     Show this help message\n";
        exit(0);
    }

    if (!$isQuiet) {
        echo "========================================\n";
        echo "  Standalone Qwiki - Demo Reload Utility\n";
        echo "========================================\n";
        echo "Reloading demo data to fresh state...\n";
    }

    $result = DemoManager::reload();

    if ($result['success']) {
        if (!$isQuiet) {
            echo "[✓] Cleared document edit locks\n";
            echo "[✓] Reset content/ from demo-data/content/\n";
            echo "[✓] Reset qwiki.json from demo-data/qwiki-default.json\n";
            echo "[✓] Reset users.json from demo-data/users-default.json\n";
            echo "[✓] Cleaned uploads/ directory and restored .htaccess\n";
            echo "----------------------------------------\n";
            echo "Success: " . $result['message'] . "\n";
            echo "========================================\n";
        }
        exit(0);
    } else {
        if (!$isQuiet) {
            fwrite(STDERR, "Error: " . ($result['error'] ?? 'Unknown error occurred.') . "\n");
        }
        exit(1);
    }
} else {
    // Web execution
    header('Content-Type: application/json; charset=utf-8');

    $config = Config::load();
    $token = $_REQUEST['token'] ?? '';
    $configuredToken = $config['feedAccessToken'] ?? '';
    $envToken = getenv('DEMO_RELOAD_TOKEN');

    $isAuthorized = false;

    // 1. Check if logged in as Admin
    if (Auth::isAdmin()) {
        $isAuthorized = true;
    }
    // 2. Check token against env DEMO_RELOAD_TOKEN if set
    elseif (!empty($envToken) && hash_equals($envToken, $token)) {
        $isAuthorized = true;
    }
    // 3. Check token against feedAccessToken if configured
    elseif (!empty($configuredToken) && hash_equals($configuredToken, $token)) {
        $isAuthorized = true;
    }

    if (!$isAuthorized) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized: Admin session or valid security token required.'
        ]);
        exit;
    }

    $result = DemoManager::reload();

    if (!$result['success']) {
        http_response_code(500);
    }

    echo json_encode($result);
    exit;
}
