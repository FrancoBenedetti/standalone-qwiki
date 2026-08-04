<?php
$configFile = __DIR__ . '/qwiki.json';
$config = json_decode(file_get_contents($configFile), true);

$payload = json_encode(['tree' => $config['books']]);

// Simulate exactly what reorder_tree does
$json = json_decode($payload, true);
$tree = $json['tree'];

if (is_array($tree)) {
    $config['books'] = $tree;
    // Check if json_encode fails
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        echo "json_encode failed: " . json_last_error_msg() . "\n";
    } else {
        echo "Encoded size: " . strlen($encoded) . "\n";
        $success = file_put_contents($configFile, $encoded) !== false;
        echo "file_put_contents success: " . ($success ? "true" : "false") . "\n";
    }
}
