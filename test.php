<?php
$payload = json_encode(['tree' => [
    ['id' => 'advanced-topics', 'title' => 'Advanced Topics'],
    ['id' => 'getting-started', 'title' => 'About Qwiki']
]]);
$ch = curl_init('http://localhost:8080/api/admin.php?action=reorder_tree');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
// Set session cookie to bypass login? Wait, we don't have session cookie.
$response = curl_exec($ch);
echo "Response: $response\n";
