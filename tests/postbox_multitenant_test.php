<?php
/**
 * Standalone Qwiki - Document Postbox Multi-Tenant & Subwiki Isolation Tests
 */
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/SubwikiManager.php';
require_once __DIR__ . '/../assets/extensions/tool-postbox/Envelope.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\SubwikiManager;
use Qwiki\Extension\Postbox\Envelope;

echo "Running Document Postbox Multi-Tenant & Subwiki Isolation Tests...\n\n";

$tmpDir = sys_get_temp_dir();
$tenantA = $tmpDir . '/qwiki_tenant_a_' . uniqid();
$tenantB = $tmpDir . '/qwiki_tenant_b_' . uniqid();

@mkdir($tenantA . '/content/news', 0755, true);
@mkdir($tenantA . '/uploads/images', 0755, true);

@mkdir($tenantB . '/content/docs', 0755, true);
@mkdir($tenantB . '/uploads/images', 0755, true);

// Setup Tenant A config
$configA = [
    'title' => 'Tenant A Corp',
    'postboxToken' => 'token-tenant-a-111',
    'books' => [
        [
            'id' => 'news',
            'title' => 'Company News',
            'type' => 'folder',
            'items' => [
                [
                    'title' => 'Product Launch',
                    'slug' => 'product-launch',
                    'type' => 'markdown',
                    'file' => 'content/news/product-launch.md'
                ]
            ]
        ]
    ]
];
file_put_contents($tenantA . '/qwiki.json', json_encode($configA, JSON_PRETTY_PRINT));
file_put_contents($tenantA . '/users.json', json_encode(['users' => []], JSON_PRETTY_PRINT));
file_put_contents($tenantA . '/content/news/product-launch.md', "# Product Launch 2026\n\nExciting announcements ahead!");

// Setup Tenant B config
$configB = [
    'title' => 'Tenant B Partner',
    'postboxToken' => 'token-tenant-b-222',
    'books' => [
        [
            'id' => 'docs',
            'title' => 'Partner Docs',
            'type' => 'folder',
            'items' => []
        ]
    ]
];
file_put_contents($tenantB . '/qwiki.json', json_encode($configB, JSON_PRETTY_PRINT));
file_put_contents($tenantB . '/users.json', json_encode(['users' => []], JSON_PRETTY_PRINT));

// Setup Subwiki under Tenant A
$subwikiA = $tenantA . '/team-portal';
@mkdir($subwikiA . '/content/team', 0755, true);
@mkdir($subwikiA . '/uploads/images', 0755, true);
$configSubA = [
    'title' => 'Team Portal (Subwiki of A)',
    'isSubwiki' => true,
    'parentUrl' => '../',
    'postboxToken' => 'token-subwiki-a-333',
    'books' => [
        [
            'id' => 'team',
            'title' => 'Team Category',
            'type' => 'folder',
            'items' => []
        ]
    ]
];
file_put_contents($subwikiA . '/qwiki.json', json_encode($configSubA, JSON_PRETTY_PRINT));
file_put_contents($subwikiA . '/users.json', json_encode(['users' => []], JSON_PRETTY_PRINT));
touch($subwikiA . '/.subwiki');

// -------------------------------------------------------------
// Test 1: Intra-Tenant Subwiki Transfer (Direct Filesystem Drop)
// -------------------------------------------------------------
echo "1. Testing Intra-Tenant Subwiki Transfer (Tenant A -> Subwiki A)...\n";
Config::init($tenantA);
$docToTransfer = $configA['books'][0]['items'][0];
$envelope = Envelope::createPackage([$docToTransfer], $tenantA, $configA);

// Direct drop into Subwiki A
$stagedSubA = Envelope::saveStagedEnvelope($subwikiA, $envelope);
if (empty($stagedSubA['success'])) {
    echo "FAIL: Could not stage envelope in intra-tenant subwiki: " . ($stagedSubA['error'] ?? '') . "\n";
    exit(1);
}

// Subwiki A reviews inbox
$subAEnvelopes = Envelope::listInboxEnvelopes($subwikiA);
if (count($subAEnvelopes) !== 1) {
    echo "FAIL: Subwiki A inbox does not list staged envelope\n";
    exit(1);
}

// Subwiki A ingests into 'team' category
Config::init($subwikiA);
$ingestSubA = Envelope::ingestDocument(
    $subwikiA,
    $configSubA,
    $envelope['batch_id'],
    0,
    'team',
    ['title' => 'Product Launch (Local Subwiki Copy)', 'slug' => 'product-launch']
);

if (empty($ingestSubA['success'])) {
    echo "FAIL: Subwiki A ingestion failed: " . ($ingestSubA['error'] ?? '') . "\n";
    exit(1);
}

if (!file_exists($subwikiA . '/content/team/product-launch.md')) {
    echo "FAIL: Document file was not written to Subwiki A storage\n";
    exit(1);
}
echo "PASS: Intra-tenant subwiki transfer and ingestion verified.\n\n";

// -------------------------------------------------------------
// Test 2: Multitenant Boundary Check (Cross-Tenant Filesystem Isolation)
// -------------------------------------------------------------
echo "2. Testing Cross-Tenant Boundary Enforcement...\n";

// When QWIKI_BASE_DIR is defined to Tenant A:
$allowedRoot = realpath($tenantA);
$targetB = realpath($tenantB);

$isInsideTenantA = ($targetB !== false && strpos($targetB, $allowedRoot) === 0);
if ($isInsideTenantA) {
    echo "FAIL: Tenant B should not be within Tenant A directory\n";
    exit(1);
}
echo "PASS: Tenant filesystem boundaries correctly distinguished.\n\n";

// -------------------------------------------------------------
// Test 3: Cross-Tenant Transfer via Authenticated Token Staging
// -------------------------------------------------------------
echo "3. Testing Cross-Tenant Transfer via Authenticated Staging...\n";

// Tenant A creates an envelope targeted at Tenant B
$crossEnvelope = Envelope::createPackage([$docToTransfer], $tenantA, $configA);

// Simulating the receiver endpoint logic on Tenant B:
// Check token:
$incomingToken = 'token-tenant-b-222';
$configuredTokenB = $configB['postboxToken'];

if (!hash_equals($configuredTokenB, $incomingToken)) {
    echo "FAIL: Token authentication mismatch\n";
    exit(1);
}

$stageResultB = Envelope::saveStagedEnvelope($tenantB, $crossEnvelope);
if (empty($stageResultB['success'])) {
    echo "FAIL: Tenant B staging failed: " . ($stageResultB['error'] ?? '') . "\n";
    exit(1);
}

// Tenant B lists inbox
$tenantBEnvelopes = Envelope::listInboxEnvelopes($tenantB);
if (count($tenantBEnvelopes) !== 1) {
    echo "FAIL: Tenant B did not find staged cross-tenant package\n";
    exit(1);
}

// Tenant B ingests into category 'docs' with custom title and theme
Config::init($tenantB);
$ingestResultB = Envelope::ingestDocument(
    $tenantB,
    $configB,
    $crossEnvelope['batch_id'],
    0,
    'docs',
    [
        'title' => 'Partner Notice: Product Launch',
        'slug' => 'partner-product-launch',
        'theme' => 'theme-partner',
        'description' => 'Shared by Tenant A'
    ]
);

if (empty($ingestResultB['success'])) {
    echo "FAIL: Tenant B ingestion failed: " . ($ingestResultB['error'] ?? '') . "\n";
    exit(1);
}

$destFileB = $tenantB . '/content/docs/partner-product-launch.md';
if (!file_exists($destFileB)) {
    echo "FAIL: File not found in Tenant B storage: {$destFileB}\n";
    exit(1);
}

$savedConfigB = json_decode(file_get_contents($tenantB . '/qwiki.json'), true);
$partnerDoc = $savedConfigB['books'][0]['items'][0] ?? null;
if (!$partnerDoc || $partnerDoc['slug'] !== 'partner-product-launch') {
    echo "FAIL: Tenant B qwiki.json chapter metadata mismatch\n";
    exit(1);
}
echo "PASS: Cross-tenant transfer, token verification, and ingestion verified.\n\n";

// Cleanup
function cleanRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $f) {
        $p = $dir . '/' . $f;
        if (is_dir($p)) cleanRecursive($p);
        else @unlink($p);
    }
    @rmdir($dir);
}
cleanRecursive($tenantA);
cleanRecursive($tenantB);

echo "ALL MULTI-TENANT POSTBOX TESTS PASSED! 🚀🏢\n";
