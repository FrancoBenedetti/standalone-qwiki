<?php
$configFile = __DIR__ . '/qwiki.json';
$config = json_decode(file_get_contents($configFile), true);

$payload = json_encode(['tree' => $config['books']]);
$ch = curl_init('http://127.0.0.1:8080/api/admin.php?action=reorder_tree');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$response = curl_exec($ch);
echo "Response: $response\n";
