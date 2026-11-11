<?php
/**
 * Standalone Qwiki - Subwiki Documentation Synchronization Utility (CLI)
 *
 * Synchronizes the documentation subwiki (e.g. home.qwiki.wiki/docs) with the
 * latest canonical documentation and navigation tree from demo-data/.
 *
 * Usage:
 *   php bin/sync-subwiki-docs.php [subwiki_slug] [--symlink|--copy]
 *
 * Examples:
 *   php bin/sync-subwiki-docs.php docs --symlink   (Recommended for VPS/dedicated: zero-maintenance content link)
 *   php bin/sync-subwiki-docs.php docs --copy      (Physical file copy for hosts like Xneelo that forbid symlinks)
 */

require_once dirname(__DIR__) . '/lib/Core/Config.php';
require_once dirname(__DIR__) . '/lib/Core/SubwikiManager.php';

use Qwiki\Core\Config;
use Qwiki\Core\SubwikiManager;

Config::init();

$args = $argv ?? [];
$subwikiSlug = 'docs';
$mode = 'auto'; // default to auto (tries symlink, falls back to copy)
$force = in_array('--force', $args);

for ($i = 1; $i < count($args); $i++) {
    $arg = trim($args[$i]);
    if ($arg === '--copy') {
        $mode = 'copy';
    } elseif ($arg === '--symlink') {
        $mode = 'symlink';
    } elseif ($arg === '--force') {
        $force = true;
    } elseif (strpos($arg, '--') !== 0) {
        $subwikiSlug = Config::makeSlug($arg);
    }
}

echo "======================================================\n";
echo "  Qwiki Subwiki Documentation Sync: '{$subwikiSlug}'\n";
echo "  Requested Mode: " . strtoupper($mode) . ($force ? " (FORCE)" : "") . "\n";
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
