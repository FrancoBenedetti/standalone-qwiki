<?php
/**
 * Test Suite: api/publish.php Theme CSS Upload, Listing & Hierarchical Assignment (Site / Category / Page)
 */

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';

use Qwiki\Core\Config;

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

echo "Running Publish API Theme Management Test Suite...\n\n";

$tempDir = sys_get_temp_dir() . '/qwiki_publish_theme_test_' . uniqid();
@mkdir($tempDir . '/content/guides', 0755, true);

$apiKey = 'pub-theme-secret-2026';
$scopedLlmKey = 'qwk_llm_scoped_guides_456';

$initialConfig = [
    'title' => 'Theme Publish API Wiki',
    'theme' => 'theme-default.css',
    'publishApiKey' => $apiKey,
    'llmKeys' => [
        [
            'id' => 'key-guides',
            'name' => 'Guides Agent',
            'key' => $scopedLlmKey,
            'category' => 'guides',
            'allowedTypes' => ['markdown', 'html'],
            'status' => 'active',
            'createdAt' => date('Y-m-d H:i:s'),
        ]
    ],
    'books' => [
        [
            'id' => 'guides',
            'title' => 'Guides',
            'type' => 'folder',
            'folder' => 'content/guides',
            'items' => []
        ]
    ]
];
file_put_contents($tempDir . '/qwiki.json', json_encode($initialConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
Config::init($tempDir);

function callPublishApi($tempDir, array $post, array $server = []) {
    $script = sprintf('
        define("QWIKI_BASE_DIR", "%s");
        require_once "%s/lib/Core/Config.php";
        require_once "%s/lib/Core/Navigation.php";
        \Qwiki\Core\Config::init("%s");
        $_POST = %s;
        $_SERVER = array_merge($_SERVER, %s);
        $_REQUEST = $_POST;
        include "%s/api/publish.php";
    ',
    addslashes($tempDir),
    addslashes(realpath(__DIR__ . '/..')),
    addslashes(realpath(__DIR__ . '/..')),
    addslashes($tempDir),
    var_export($post, true),
    var_export($server, true),
    addslashes(realpath(__DIR__ . '/..'))
    );

    $cmd = sprintf('php -r %s 2>&1', escapeshellarg($script));
    $output = shell_exec($cmd);
    $json = json_decode($output, true);
    return ['raw' => $output, 'json' => $json];
}

// 1. list_themes & get_theme
$resList = callPublishApi($tempDir, ['action' => 'list_themes'], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resList['json']['success']), 'list_themes succeeds');
assertTest(($resList['json']['siteTheme'] ?? '') === 'theme-default.css', 'list_themes returns active siteTheme');
assertTest(in_array('theme-article-modern.css', $resList['json']['themes'] ?? [], true), 'list_themes includes core theme-article-modern.css');

$resGet = callPublishApi($tempDir, ['action' => 'get_theme', 'theme' => 'theme-article-modern.css'], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resGet['json']['success']), 'get_theme succeeds for core theme');
assertTest(strpos($resGet['json']['content'] ?? '', '.content-body') !== false, 'get_theme returns CSS content');

// 2. save_theme validation & upload
$resBad = callPublishApi($tempDir, [
    'action' => 'save_theme',
    'theme' => 'bad-name.css',
    'content' => '.content-body { color: red; }'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(empty($resBad['json']['success']), 'save_theme rejects filename not matching theme-*.css');

$customCss = ".content-body h2 { border-bottom: 2px solid #00a86b; }\n@media print { .content-body { font-size: 10pt; } }\n";
$resSave = callPublishApi($tempDir, [
    'action' => 'save_theme',
    'theme' => 'theme-custom-md.css',
    'content_base64' => base64_encode($customCss)
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resSave['json']['success']), 'save_theme uploads custom CSS via base64');
assertTest(is_file($tempDir . '/uploads/themes/theme-custom-md.css'), 'Custom theme saved to tenant uploads/themes/');
assertTest(($resSave['json']['url'] ?? '') === 'uploads/themes/theme-custom-md.css', 'save_theme returns relative theme URL');

// Scoped LLM key cannot upload site themes or set site theme
$resScopedSave = callPublishApi($tempDir, [
    'action' => 'save_theme',
    'theme' => 'theme-scoped.css',
    'content' => '.content-body { color: blue; }'
], ['HTTP_X_API_KEY' => $scopedLlmKey]);
assertTest(empty($resScopedSave['json']['success']), 'Scoped LLM key blocked from save_theme');

// 3. set_site_theme
$resSite = callPublishApi($tempDir, [
    'action' => 'set_site_theme',
    'theme' => 'theme-custom-md.css'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resSite['json']['success']), 'set_site_theme succeeds');
$cfgAfterSite = json_decode(file_get_contents($tempDir . '/qwiki.json'), true);
assertTest(($cfgAfterSite['theme'] ?? '') === 'theme-custom-md.css', 'Site theme persisted to qwiki.json');

// 4. Category theme via create_category / update_category / list_categories
$resCatNew = callPublishApi($tempDir, [
    'action' => 'create_category',
    'id' => 'manuals',
    'title' => 'Manuals',
    'theme' => 'theme-article-classic.css'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resCatNew['json']['success']), 'create_category with theme succeeds');
assertTest(($resCatNew['json']['theme'] ?? '') === 'theme-article-classic.css', 'create_category returns theme');

$resCatUpd = callPublishApi($tempDir, [
    'action' => 'update_category',
    'id' => 'guides',
    'theme' => 'theme-custom-md.css'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resCatUpd['json']['success']), 'update_category with theme succeeds');

$resCats = callPublishApi($tempDir, ['action' => 'list_categories'], ['HTTP_X_API_KEY' => $apiKey]);
$guidesCat = null;
foreach ($resCats['json']['categories'] ?? [] as $c) {
    if (($c['id'] ?? '') === 'guides') $guidesCat = $c;
}
assertTest(($guidesCat['theme'] ?? '') === 'theme-custom-md.css', 'list_categories exposes category theme');

// 5. Page theme via publish_doc, preservation on re-publish, and get_doc
$resPub = callPublishApi($tempDir, [
    'action' => 'publish_doc',
    'bookId' => 'guides',
    'title' => 'Styled Page',
    'slug' => 'styled-page',
    'content' => "# Styled Page\n\nBody text.",
    'theme' => 'theme-article-modern.css'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(!empty($resPub['json']['success']), 'publish_doc with theme succeeds');
assertTest(($resPub['json']['theme'] ?? '') === 'theme-article-modern.css', 'publish_doc returns assigned theme');

$resRepub = callPublishApi($tempDir, [
    'action' => 'publish_doc',
    'bookId' => 'guides',
    'title' => 'Styled Page',
    'slug' => 'styled-page',
    'content' => "# Styled Page\n\nUpdated body text.",
    'update' => 'true',
    'overwrite' => 'true'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(($resRepub['json']['theme'] ?? '') === 'theme-article-modern.css', 'publish_doc preserves existing page theme when theme omitted');

$resDoc = callPublishApi($tempDir, [
    'action' => 'get_doc',
    'slug' => 'styled-page',
    'bookId' => 'guides'
], ['HTTP_X_API_KEY' => $apiKey]);
assertTest(($resDoc['json']['document']['theme'] ?? '') === 'theme-article-modern.css', 'get_doc returns page theme');

// Cleanup
$it = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) {
    if ($file->isDir()) @rmdir($file->getRealPath());
    else @unlink($file->getRealPath());
}
@rmdir($tempDir);

echo "\n============================================\n";
echo "Test Results: {$passed} passed, {$failed} failed.\n";
echo "============================================\n";
exit($failed > 0 ? 1 : 0);
