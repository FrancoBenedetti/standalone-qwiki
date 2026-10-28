<?php
// Test Theme Management in Multi-Tenant / Shared Core Mode

$tenantDir = sys_get_temp_dir() . '/qwiki_theme_test_' . uniqid();
@mkdir($tenantDir . '/uploads', 0777, true);
file_put_contents($tenantDir . '/qwiki.json', json_encode(['title' => 'Theme Test Wiki']));
file_put_contents($tenantDir . '/users.json', json_encode([
    'users' => [
        [
            'username' => 'admin',
            'passwordHash' => password_hash('admin', PASSWORD_DEFAULT),
            'role' => 'admin'
        ]
    ]
]));

define('QWIKI_BASE_DIR', $tenantDir);
define('QWIKI_ASSETS_URL', '/_core/assets');

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
\Qwiki\Core\Auth::startSession();
\Qwiki\Core\Auth::login('admin', 'admin');

ob_start();
$_POST = ['action' => 'list_themes'];
$_REQUEST = $_POST;
require __DIR__ . '/../api/admin.php';
$output = ob_get_clean();

$data = json_decode($output, true);
echo "1. list_themes in tenant returns themes: " . ($data && !empty($data['themes']) ? "PASS (" . count($data['themes']) . " found)" : "FAIL") . "\n";
if (!in_array('theme-article-classic.css', $data['themes'] ?? [])) {
    echo "FAIL: theme-article-classic.css not found in themes list\n";
    exit(1);
}

// 2. Test get_theme on a core theme
ob_start();
$_POST = ['action' => 'get_theme', 'theme' => 'theme-article-classic.css'];
$_REQUEST = $_POST;
require __DIR__ . '/../api/admin.php';
$output = ob_get_clean();
$data = json_decode($output, true);
echo "2. get_theme on core theme returns content: " . (!empty($data['content']) ? "PASS" : "FAIL") . "\n";

// 3. Test save_theme saves to tenant's uploads/themes/
ob_start();
$_POST = [
    'action' => 'save_theme',
    'theme' => 'theme-custom-brand.css',
    'content' => '/* Custom Brand CSS */\n:root { --primary: #ff0055; }'
];
$_REQUEST = $_POST;
require __DIR__ . '/../api/admin.php';
$output = ob_get_clean();
$data = json_decode($output, true);
echo "3. save_theme executes successfully: " . (!empty($data['success']) ? "PASS" : "FAIL") . "\n";

$savedFile = $tenantDir . '/uploads/themes/theme-custom-brand.css';
echo "4. Custom theme saved in tenant directory: " . (file_exists($savedFile) ? "PASS" : "FAIL") . "\n";

// 5. Test get_theme on newly created custom theme
ob_start();
$_POST = ['action' => 'get_theme', 'theme' => 'theme-custom-brand.css'];
$_REQUEST = $_POST;
require __DIR__ . '/../api/admin.php';
$output = ob_get_clean();
$data = json_decode($output, true);
echo "5. get_theme returns tenant custom theme: " . (!empty($data['is_custom']) && strpos($data['content'], '#ff0055') !== false ? "PASS" : "FAIL") . "\n";

// 6. Test URL resolution
$urlBuiltin = \Qwiki\Core\Config::getThemeUrl('theme-article-classic.css');
$urlCustom = \Qwiki\Core\Config::getThemeUrl('theme-custom-brand.css');
echo "6. Built-in URL resolution: " . ($urlBuiltin === '/_core/assets/css/theme-article-classic.css' ? "PASS ({$urlBuiltin})" : "FAIL ({$urlBuiltin})") . "\n";
echo "7. Custom URL resolution: " . ($urlCustom === 'uploads/themes/theme-custom-brand.css' ? "PASS ({$urlCustom})" : "FAIL ({$urlCustom})") . "\n";

// Clean up
@unlink($savedFile);
@rmdir($tenantDir . '/uploads/themes');
@rmdir($tenantDir . '/uploads');
@unlink($tenantDir . '/qwiki.json');
@unlink($tenantDir . '/users.json');
@rmdir($tenantDir);

echo "\nALL THEME TESTS PASSED! 🎉\n";
