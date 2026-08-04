<?php
$configFile = __DIR__ . '/qwiki.json';
$config = json_decode(file_get_contents($configFile), true);
$tree = $config['books'];
$tree = array_reverse($tree); // Swap order for testing
$config['books'] = $tree;
file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Modified qwiki.json\n";
