<?php
/**
 * CLI Utility: Audit i18n keys used in PHP and JS against locale JSON packs.
 * Usage: php bin/check-i18n.php
 */

$root = dirname(__DIR__);
$extDir = $root . '/assets/extensions/tool-language';
$localesMeta = json_decode((string)file_get_contents($extDir . '/locales.json'), true);

$phpFiles = array_merge(
    [$root . '/index.php', $root . '/lib/Core/Navigation.php', $root . '/lib/Core/ExtensionManager.php'],
    glob($root . '/templates/modals/*.php') ?: [],
    glob($root . '/assets/extensions/*/*.php') ?: []
);

$jsFiles = array_merge(
    [
        $root . '/assets/js/app.js',
        $root . '/assets/js/core/search.js',
    ],
    glob($root . '/assets/extensions/*/script.js') ?: []
);

$phpKeys = [];
foreach (glob($root . '/assets/extensions/*/manifest.json') ?: [] as $manifestFile) {
    $m = json_decode((string)file_get_contents($manifestFile), true);
    if (is_array($m) && !empty($m['id'])) {
        $id = $m['id'];
        if (!empty($m['title'])) {
            $phpKeys['ext.' . $id . '.title'] = (string)$m['title'];
        }
        if (!empty($m['description'])) {
            $phpKeys['ext.' . $id . '.desc'] = (string)$m['description'];
        }
    }
}

foreach ($phpFiles as $file) {
    $src = (string)file_get_contents($file);
    if (preg_match_all('/\b__\(\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]\s*,\s*([\'"`])((?:\\\\.|(?!\2).)*)\2/s', $src, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $phpKeys[$m[1]] = stripcslashes($m[3]);
        }
    }
}

$jsKeys = [];
foreach ($jsFiles as $file) {
    $src = (string)file_get_contents($file);
    if (preg_match_all('/\b_t\(\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]\s*,\s*([\'"`])((?:\\\\.|(?!\2).)*)\2/s', $src, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $jsKeys[$m[1]] = stripcslashes($m[3]);
        }
    }
}

ksort($phpKeys);
ksort($jsKeys);

if (in_array('--dump-en', $argv, true)) {
    echo json_encode(['php' => $phpKeys, 'js' => $jsKeys], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
}

$hasErrors = false;
foreach ($localesMeta['languages'] ?? [] as $code => $meta) {
    if ($code === ($localesMeta['default'] ?? 'en')) {
        continue;
    }
    $path = $extDir . '/locales/' . $code . '.json';
    if (!file_exists($path)) {
        fwrite(STDERR, "[FAIL] Missing locale file: {$path}\n");
        $hasErrors = true;
        continue;
    }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "[FAIL] Invalid JSON in {$path}\n");
        $hasErrors = true;
        continue;
    }

    $missingPhp = array_diff_key($phpKeys, $data['php'] ?? []);
    $missingJs = array_diff_key($jsKeys, $data['js'] ?? []);
    if (!empty($missingPhp) || !empty($missingJs)) {
        $hasErrors = true;
        echo "[FAIL] Locale '{$code}' is missing keys:\n";
        foreach ($missingPhp as $k => $v) echo "  php.{$k} => {$v}\n";
        foreach ($missingJs as $k => $v) echo "  js.{$k} => {$v}\n";
    } else {
        echo "[PASS] Locale '{$code}' covers all " . count($phpKeys) . " PHP keys and " . count($jsKeys) . " JS keys.\n";
    }
}

exit($hasErrors ? 1 : 0);
