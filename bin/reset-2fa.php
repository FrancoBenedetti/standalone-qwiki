<?php
/**
 * Standalone Qwiki - Emergency CLI 2FA Reset Tool
 *
 * Usage:
 *   php bin/reset-2fa.php [username]
 *
 * Example:
 *   php bin/reset-2fa.php admin
 */

if (php_sapi_name() !== 'cli') {
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../lib/Core/Config.php';

use Qwiki\Core\Config;

Config::init();

$targetUsername = isset($argv[1]) ? trim($argv[1]) : 'admin';

echo "⚡ Standalone Qwiki - Emergency 2FA Reset\n";
echo "Target User: {$targetUsername}\n";

$userData = Config::loadUsers();
$found = false;
$modified = false;

if (!empty($userData['users'])) {
    foreach ($userData['users'] as &$u) {
        if (strtolower($u['username']) === strtolower($targetUsername)) {
            $found = true;
            if (empty($u['twoFactor']) || empty($u['twoFactor']['enabled'])) {
                echo "ℹ️  User '{$u['username']}' does not have Two-Factor Authentication enabled.\n";
            } else {
                $u['twoFactor'] = null;
                $modified = true;
                echo "🔓 Two-Factor Authentication removed for user '{$u['username']}'.\n";
            }
            break;
        }
    }
    unset($u);
}

if (!$found) {
    echo "❌ Error: User '{$targetUsername}' was not found in users.json.\n";
    exit(1);
}

if ($modified) {
    if (Config::saveUsers($userData)) {
        echo "✅ Success: users.json updated. User '{$targetUsername}' can now log in using their password directly.\n";
        exit(0);
    } else {
        echo "❌ Error: Failed to write updates to users.json. Check file permissions.\n";
        exit(1);
    }
}

exit(0);
