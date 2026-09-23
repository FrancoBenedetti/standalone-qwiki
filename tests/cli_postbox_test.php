<?php
/**
 * Standalone Qwiki - Desktop Postbox CLI Automated Test Suite
 */
require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../assets/extensions/tool-postbox/Envelope.php';

use Qwiki\Core\Config;
use Qwiki\Extension\Postbox\Envelope;

echo "Running Desktop Postbox CLI Tests...\n\n";

$cliPath = realpath(__DIR__ . '/../tools/qwiki-postbox.py');
if (!$cliPath || !file_exists($cliPath)) {
    echo "FAIL: Could not find tools/qwiki-postbox.py\n";
    exit(1);
}

$tmpDir = sys_get_temp_dir();
$testDesktopDir = $tmpDir . '/qwiki_desktop_source_' . uniqid();
@mkdir($testDesktopDir . '/subfolder/img', 0755, true);

// 1. Create desktop test files
$sampleImage = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($testDesktopDir . '/subfolder/img/architecture.png', $sampleImage);

// Create Markdown file referencing local image
$doc1Content = "# System Architecture\n\nOverview of the system design.\n\n![Architecture](./img/architecture.png)\n";
file_put_contents($testDesktopDir . '/subfolder/architecture.md', $doc1Content);

// Create second Markdown file
$doc2Content = "# Quickstart Guide\n\nInstructions to get started quickly.\n";
file_put_contents($testDesktopDir . '/quickstart.md', $doc2Content);

// -------------------------------------------------------------
// Test 1: Dry-Run Single File with Local Image
// -------------------------------------------------------------
echo "1. Testing CLI Dry-Run on Single File with Asset Resolution...\n";
$cmd1 = escapeshellcmd("python3 {$cliPath} send " . escapeshellarg($testDesktopDir . '/subfolder/architecture.md') . " --dry-run");
exec($cmd1, $out1, $ret1);

$fullOut1 = implode("\n", $out1);
if ($ret1 !== 0 || strpos($fullOut1, 'architecture.png') === false) {
    echo "FAIL: Dry run on single file failed or did not resolve architecture.png\n";
    echo $fullOut1 . "\n";
    exit(1);
}
echo "PASS: Single file packaging and local asset resolution verified.\n\n";

// -------------------------------------------------------------
// Test 2: Dry-Run Directory Scan with Recursive Subfolders
// -------------------------------------------------------------
echo "2. Testing CLI Dry-Run on Entire Folder Tree...\n";
$cmd2 = escapeshellcmd("python3 {$cliPath} send " . escapeshellarg($testDesktopDir) . " --category \"Desktop Specs\" --dry-run");
exec($cmd2, $out2, $ret2);

$fullOut2 = implode("\n", $out2);
if ($ret2 !== 0 || strpos($fullOut2, '2 document(s)') === false) {
    echo "FAIL: Directory scan did not find both documents\n";
    echo $fullOut2 . "\n";
    exit(1);
}
echo "PASS: Directory scan and multi-document packaging verified.\n\n";

// -------------------------------------------------------------
// Test 3: Staging Verification in Test Wiki via Receiver Logic
// -------------------------------------------------------------
echo "3. Testing Staging and Category Hint in Destination Wiki...\n";
$testWikiDir = $tmpDir . '/qwiki_dest_wiki_' . uniqid();
@mkdir($testWikiDir . '/content', 0755, true);
@mkdir($testWikiDir . '/uploads', 0755, true);

$destConfig = [
    'title' => 'Receiving Wiki',
    'postboxToken' => 'desktop-test-token-999',
    'books' => [
        [
            'id' => 'guides',
            'title' => 'Guides',
            'type' => 'folder',
            'items' => []
        ]
    ]
];
file_put_contents($testWikiDir . '/qwiki.json', json_encode($destConfig, JSON_PRETTY_PRINT));
Config::init($testWikiDir);

// Execute CLI send function with --json flag to test envelope payload
$cmdJson = "python3 " . escapeshellarg($cliPath) . " send " . escapeshellarg($testDesktopDir) . " --category \"Desktop Specs\" --json";
exec($cmdJson, $envOut, $envRet);
$envJson = implode("\n", $envOut);

if ($envRet !== 0 || empty($envJson)) {
    echo "FAIL: Could not generate envelope via CLI --json flag\n";
    echo $envJson . "\n";
    exit(1);
}

$envelope = json_decode($envJson, true);
if (empty($envelope['batch_id']) || count($envelope['documents']) !== 2) {
    echo "FAIL: Envelope structure invalid\n";
    exit(1);
}
if (($envelope['category_hint'] ?? '') !== 'Desktop Specs') {
    echo "FAIL: Category hint mismatch in envelope: " . ($envelope['category_hint'] ?? '') . "\n";
    exit(1);
}

// Stage envelope into destination wiki
$stageResult = Envelope::saveStagedEnvelope($testWikiDir, $envelope);
if (empty($stageResult['success'])) {
    echo "FAIL: Could not save staged envelope: " . ($stageResult['error'] ?? '') . "\n";
    exit(1);
}

// Check that envelope is listed in inbox with category_hint
$inboxEnvelopes = Envelope::listInboxEnvelopes($testWikiDir);
if (count($inboxEnvelopes) !== 1 || ($inboxEnvelopes[0]['category_hint'] ?? '') !== 'Desktop Specs') {
    echo "FAIL: listInboxEnvelopes did not preserve category_hint\n";
    exit(1);
}

// Ingest the architecture doc into category 'guides'
$ingestRes = Envelope::ingestDocument(
    $testWikiDir,
    $destConfig,
    $envelope['batch_id'],
    'architecture',
    'guides',
    ['title' => 'Ingested Architecture']
);

if (empty($ingestRes['success'])) {
    echo "FAIL: Ingest document failed: " . ($ingestRes['error'] ?? '') . "\n";
    exit(1);
}

// Verify asset was unpacked to uploads/images/
$imgFiles = glob($testWikiDir . '/uploads/images/*architecture*');
if (empty($imgFiles)) {
    echo "FAIL: Referenced image was not unpacked to uploads/images/\n";
    exit(1);
}

// Verify markdown link was updated to uploads/images/
$ingestedMd = file_get_contents($testWikiDir . '/content/guides/architecture.md');
if (strpos($ingestedMd, 'uploads/images/') === false) {
    echo "FAIL: Ingested markdown does not contain updated uploads/images/ asset path\n";
    exit(1);
}
echo "PASS: End-to-end desktop payload staging, category hint, and asset link remapping verified.\n\n";

// -------------------------------------------------------------
// Test 4: ZIP Download Action Verification
// -------------------------------------------------------------
echo "4. Testing ext_postbox_download_cli Tool Bundle Creation...\n";
$coreTools = realpath(__DIR__ . '/../tools');
if (!file_exists($coreTools . '/qwiki-postbox.py') ||
    !file_exists($coreTools . '/desktop/linux/install-nautilus.sh') ||
    !file_exists($coreTools . '/desktop/windows/setup-sendto.bat') ||
    !file_exists($coreTools . '/desktop/macos/install-quickaction.sh')) {
    echo "FAIL: Missing OS desktop integration scripts in tools/\n";
    exit(1);
}
echo "PASS: Desktop tool bundle and OS integration scripts verified.\n\n";

// -------------------------------------------------------------
// Test 5: Multi-Path Arguments and Profiles Command
// -------------------------------------------------------------
echo "5. Testing Multi-Path Batch Packaging and Profiles Command...\n";
$docPath1 = escapeshellarg($testDesktopDir . '/subfolder/architecture.md');
$docPath2 = escapeshellarg($testDesktopDir . '/quickstart.md');
$cmdMulti = "python3 {$cliPath} send {$docPath1} {$docPath2} --json";
exec($cmdMulti, $outMulti, $retMulti);
$jsonMulti = implode("\n", $outMulti);

if ($retMulti !== 0 || empty($jsonMulti)) {
    echo "FAIL: Multi-path send failed\n";
    exit(1);
}

$envMulti = json_decode($jsonMulti, true);
if (count($envMulti['documents'] ?? []) !== 2) {
    echo "FAIL: Multi-path send did not bundle both documents into one envelope\n";
    exit(1);
}

$cmdProf = "python3 {$cliPath} profiles --json";
exec($cmdProf, $outProf, $retProf);
if ($retProf !== 0) {
    echo "FAIL: profiles --json command failed\n";
    exit(1);
}
echo "PASS: Multi-path batch bundling and profiles CLI verified.\n\n";

// -------------------------------------------------------------
// Test 6: CLI Packaging of HTML Document & Title Extraction
// -------------------------------------------------------------
echo "6. Testing CLI Packaging of HTML Document with <title> and Assets...\n";
$htmlTestFile = $testDesktopDir . '/dashboard.html';
$sampleHtmlContent = "<!DOCTYPE html>\n<html>\n<head>\n    <meta charset=\"UTF-8\">\n    <title>Customer Success Metric Dashboard</title>\n</head>\n<body>\n    <h1>Dashboard</h1>\n    <img src=\"./subfolder/img/architecture.png\">\n</body>\n</html>";
file_put_contents($htmlTestFile, $sampleHtmlContent);

$cmdHtml = "python3 " . escapeshellarg($cliPath) . " send " . escapeshellarg($htmlTestFile) . " --json";
exec($cmdHtml, $outHtml, $retHtml);
$jsonHtml = implode("\n", $outHtml);

if ($retHtml !== 0 || empty($jsonHtml)) {
    echo "FAIL: CLI packaging of HTML file failed\n";
    exit(1);
}

$envHtml = json_decode($jsonHtml, true);
$htmlDoc = $envHtml['documents'][0] ?? null;

if (!$htmlDoc || $htmlDoc['type'] !== 'html') {
    echo "FAIL: Expected document type 'html', got " . ($htmlDoc['type'] ?? 'null') . "\n";
    exit(1);
}
if ($htmlDoc['title'] !== 'Customer Success Metric Dashboard') {
    echo "FAIL: Title not extracted from <title> tag. Got: " . ($htmlDoc['title'] ?? 'null') . "\n";
    exit(1);
}
if (count($htmlDoc['assets'] ?? []) !== 1 || $htmlDoc['assets'][0]['basename'] !== 'architecture.png') {
    echo "FAIL: Referenced image was not extracted in HTML document packaging\n";
    exit(1);
}

echo "PASS: CLI packaging of HTML document, <title> extraction, and asset extraction verified.\n\n";

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
cleanRecursive($testDesktopDir);
cleanRecursive($testWikiDir);

echo "ALL DESKTOP CLI POSTBOX TESTS PASSED! 🚀🖥️\n";
