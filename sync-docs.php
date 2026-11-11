<?php
/**
 * Standalone Qwiki - Documentation Subwiki Synchronization Utility
 *
 * Synchronizes the documentation subwiki (e.g. home.qwiki.wiki/docs) with the
 * latest canonical documentation and navigation tree from demo-data/.
 *
 * Usage:
 *   CLI: php sync-docs.php [subwiki_slug] [--copy|--symlink]
 *   Web: GET /sync-docs.php[?slug=docs][&mode=auto|copy|symlink][&token=<feedAccessToken>]
 *        (Can be accessed directly in browser by logged-in admin or via cron/webhook with token)
 */

require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Auth.php';
require_once __DIR__ . '/lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\SubwikiManager;

Config::init();
$baseDir = Config::getBaseDir();
$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    $args = $argv ?? [];
    $subwikiSlug = 'docs';
    $mode = 'auto';
    $force = in_array('--force', $args);

    for ($i = 1; $i < count($args); $i++) {
        $arg = trim($args[$i]);
        if ($arg === '--copy') {
            $mode = 'copy';
        } elseif ($arg === '--symlink') {
            $mode = 'symlink';
        } elseif (strpos($arg, '--') !== 0) {
            $subwikiSlug = Config::makeSlug($arg);
        }
    }

    echo "======================================================\n";
    echo "  Qwiki Subwiki Documentation Sync: '{$subwikiSlug}'\n";
    echo "  Mode: " . strtoupper($mode) . ($force ? " (FORCE)" : "") . "\n";
    echo "======================================================\n";

    $result = SubwikiManager::syncDocumentation($subwikiSlug, $mode, $force);

    if ($result['success']) {
        echo "[✓] " . $result['message'] . "\n";
        echo "======================================================\n";
        echo "Synchronization complete! Subwiki is up to date.\n";
        echo "URL: " . $result['url'] . "\n";
        echo "======================================================\n";
        exit(0);
    } else {
        fwrite(STDERR, "Error: " . ($result['error'] ?? 'Unknown error occurred.') . "\n");
        if (!empty($result['requiresForce'])) {
            fwrite(STDERR, "Notice: Pass --force to intentionally overwrite existing subwiki content.\n");
        }
        exit(1);
    }
} else {
    // Web execution
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

    $wantsJson = (!empty($_REQUEST['format']) && $_REQUEST['format'] === 'json') ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false);

    if (!$isAuthorized) {
        http_response_code(403);
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Unauthorized: Admin session or valid security token required.'
            ]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden - Qwiki Docs Sync</title>';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}';
            echo '.card{background:#fff;border-radius:8px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);max-width:480px;width:100%;padding:2rem;text-align:center;}';
            echo 'h2{color:#ef4444;margin-top:0;}p{color:#64748b;line-height:1.6;}code{background:#f1f5f9;padding:0.2rem 0.4rem;border-radius:4px;font-size:0.9em;}</style></head><body>';
            echo '<div class="card"><h2>🔒 Access Denied</h2>';
            echo '<p>You must either be logged into this Qwiki as an <strong>Admin</strong> or supply a valid token via <code>?token=YOUR_FEED_ACCESS_TOKEN</code>.</p>';
            echo '<p><a href="./" style="display:inline-block;margin-top:1rem;color:#2563eb;text-decoration:none;font-weight:600;">← Return to Wiki</a></p>';
            echo '</div></body></html>';
        }
        exit;
    }

    $subwikiSlug = Config::makeSlug($_REQUEST['slug'] ?? 'docs');
    $mode = $_REQUEST['mode'] ?? 'auto';
    $force = !empty($_REQUEST['force']);

    $result = SubwikiManager::syncDocumentation($subwikiSlug, $mode, $force);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit;
    }

    // HTML Output for Browser User
    header('Content-Type: text/html; charset=utf-8');
    $isSuccess = !empty($result['success']);
    $requiresForce = !empty($result['requiresForce']);
    $title = $isSuccess ? 'Documentation Synchronized' : ($requiresForce ? 'Data Protection Notice' : 'Sync Failed');
    $statusIcon = $isSuccess ? '✅' : ($requiresForce ? '🛡️' : '❌');
    $statusColor = $isSuccess ? '#10b981' : ($requiresForce ? '#f59e0b' : '#ef4444');
    $url = $result['url'] ?? ('/' . htmlspecialchars($subwikiSlug) . '/');
    $modeUsed = htmlspecialchars($result['mode'] ?? $mode);
    $msg = htmlspecialchars($result['message'] ?? ($result['error'] ?? ''));

    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $title . ' - Qwiki</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<style>';
    echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#1e293b;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:1rem;}';
    echo '.card{background:#fff;border-radius:10px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);max-width:560px;width:100%;padding:2.5rem;box-sizing:border-box;}';
    echo 'h1{font-size:1.35rem;display:flex;align-items:center;gap:0.6rem;margin-top:0;margin-bottom:1rem;color:' . $statusColor . ';}';
    echo '.desc{color:#475569;line-height:1.6;margin-bottom:1.5rem;font-size:0.95rem;}';
    echo '.info-box{background:#f1f5f9;border-left:4px solid ' . $statusColor . ';border-radius:4px;padding:0.75rem 1rem;margin-bottom:1.75rem;font-size:0.9rem;}';
    echo '.actions{display:flex;gap:0.75rem;flex-wrap:wrap;}';
    echo '.btn{display:inline-block;padding:0.6rem 1.2rem;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.9rem;text-align:center;transition:background-color 0.15s ease;}';
    echo '.btn-primary{background:#2563eb;color:#fff;} .btn-primary:hover{background:#1d4ed8;}';
    echo '.btn-warning{background:#f59e0b;color:#fff;} .btn-warning:hover{background:#d97706;}';
    echo '.btn-secondary{background:#e2e8f0;color:#334155;} .btn-secondary:hover{background:#cbd5e1;}';
    echo '</style></head><body>';
    echo '<div class="card">';
    echo '<h1>' . $statusIcon . ' ' . $title . '</h1>';
    echo '<p class="desc">' . $msg . '</p>';
    if ($isSuccess) {
        echo '<div class="info-box">';
        echo '<div><strong>Subwiki Slug:</strong> <code>' . htmlspecialchars($subwikiSlug) . '</code></div>';
        echo '<div style="margin-top:0.35rem;"><strong>Sync Method:</strong> ' . ($modeUsed === 'symlink' ? 'Symlink (Real-time live mirror)' : 'Direct file copy (Shared host compatible)') . '</div>';
        echo '</div>';
        echo '<div class="actions">';
        echo '<a href="' . $url . '" class="btn btn-primary" target="_blank">Open Subwiki (' . htmlspecialchars($subwikiSlug) . ') ↗</a>';
        echo '<a href="./" class="btn btn-secondary">← Back to Main Wiki</a>';
        echo '</div>';
    } elseif ($requiresForce) {
        $forceParams = $_GET;
        $forceParams['force'] = '1';
        $forceUrl = '?' . http_build_query($forceParams);
        echo '<div class="info-box">';
        echo '<div>This subwiki appears to contain user-authored content. To prevent data loss, Qwiki will not overwrite it without explicit confirmation.</div>';
        echo '</div>';
        echo '<div class="actions">';
        echo '<a href="' . htmlspecialchars($forceUrl) . '" class="btn btn-warning" onclick="return confirm(\'Warning: Overwriting will replace this subwiki\\\'s content with the canonical Qwiki documentation. Are you sure you want to proceed?\');">Overwrite & Convert to Docs</a>';
        echo '<a href="./" class="btn btn-secondary">← Cancel & Return to Wiki</a>';
        echo '</div>';
    } else {
        echo '<div class="actions">';
        echo '<a href="./" class="btn btn-secondary">← Back to Main Wiki</a>';
        echo '</div>';
    }
    echo '</div></body></html>';
}
    echo '</style></head><body>';
    echo '<div class="card">';
    echo '<h1>' . $statusIcon . ' ' . $title . '</h1>';
    echo '<p class="desc">' . $msg . '</p>';
    if ($isSuccess) {
        echo '<div class="info-box">';
        echo '<div><strong>Subwiki Slug:</strong> <code>' . htmlspecialchars($subwikiSlug) . '</code></div>';
        echo '<div style="margin-top:0.35rem;"><strong>Sync Method:</strong> ' . ($modeUsed === 'symlink' ? 'Symlink (Real-time live mirror)' : 'Direct file copy (Shared host compatible)') . '</div>';
        echo '</div>';
        echo '<div class="actions">';
        echo '<a href="' . $url . '" class="btn btn-primary" target="_blank">Open Subwiki (' . htmlspecialchars($subwikiSlug) . ') ↗</a>';
        echo '<a href="./" class="btn btn-secondary">← Back to Main Wiki</a>';
        echo '</div>';
    } else {
        echo '<div class="actions">';
        echo '<a href="./" class="btn btn-secondary">← Back to Main Wiki</a>';
        echo '</div>';
    }
    echo '</div></body></html>';
}
