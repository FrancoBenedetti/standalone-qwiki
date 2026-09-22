<?php
// tests/llm_access_test.php
// Comprehensive unit and integration tests for Controlled LLM & AI Agent Access

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/LlmAccess.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\LlmAccess;

Config::init();
Auth::startSession();

echo "=== Running Controlled LLM & AI Agent Access Tests ===\n\n";

$originalConfig = Config::load();
$originalKeys = $originalConfig['llmKeys'] ?? [];

function assert_test($condition, $message) {
    if ($condition) {
        echo "PASS: {$message}\n";
    } else {
        echo "FAIL: {$message}\n";
        exit(1);
    }
}

try {
    // 1. Key Generation & Management
    echo "--- 1. Testing Key Generation & Lifecycle ---\n";
    
    // Generate a test key
    $gen1 = LlmAccess::generateKey('Test Agent All', '', ['markdown'], null);
    assert_test($gen1['success'] === true, "Key 1 generated successfully");
    $key1 = $gen1['key'];
    assert_test(strpos($key1['key'], 'qwk_llm_') === 0, "Key has prefix qwk_llm_");
    assert_test($key1['category'] === '', "Key 1 has all-categories scope");
    assert_test($key1['allowedTypes'] === ['markdown'], "Key 1 has markdown allowed type");
    assert_test($key1['status'] === 'active', "Key 1 starts as active");

    // Generate a scoped key with expiry
    $futureDate = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 days in future
    $gen2 = LlmAccess::generateKey('Scoped Agent', 'getting-started', ['markdown', 'pdf'], $futureDate);
    assert_test($gen2['success'] === true, "Key 2 generated with category scope and expiry");
    $key2 = $gen2['key'];
    assert_test($key2['category'] === 'getting-started', "Key 2 scoped to getting-started");
    assert_test(in_array('pdf', $key2['allowedTypes']), "Key 2 permits PDF");

    // Test listing keys
    $allKeys = LlmAccess::listKeys();
    assert_test(count($allKeys) >= 2, "listKeys returns configured keys");

    // 2. Key Validation
    echo "\n--- 2. Testing Key Validation & Authorization Channels ---\n";

    // Validate missing key
    $valMissing = LlmAccess::validateKey('');
    assert_test($valMissing['valid'] === false && $valMissing['code'] === 401, "Missing key rejected with 401");

    // Validate non-existent key
    $valInvalid = LlmAccess::validateKey('qwk_llm_nonexistent1234567890');
    assert_test($valInvalid['valid'] === false && $valInvalid['code'] === 401, "Invalid key rejected with 401");

    // Validate valid key
    $valValid = LlmAccess::validateKey($key1['key']);
    assert_test($valValid['valid'] === true && $valValid['key']['id'] === $key1['id'], "Valid key accepted");

    // Validate HTTP Bearer Header
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $key1['key'];
    unset($_GET['key']);
    $valBearer = LlmAccess::validateKey(null);
    assert_test($valBearer['valid'] === true, "Authorization: Bearer <key> header validated");

    // Validate URL query parameter
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $_GET['key'] = $key1['key'];
    $valQuery = LlmAccess::validateKey(null);
    assert_test($valQuery['valid'] === true, "Query parameter ?key=<key> validated");
    unset($_GET['key']);

    // 3. Key Revocation & Expiry
    echo "\n--- 3. Testing Revocation & Expiration ---\n";

    // Revoke key 1
    $rev1 = LlmAccess::revokeKey($key1['id'], 'revoked');
    assert_test($rev1['success'] === true && $rev1['status'] === 'revoked', "Key 1 revoked");

    $valRevoked = LlmAccess::validateKey($key1['key']);
    assert_test($valRevoked['valid'] === false && $valRevoked['code'] === 403, "Revoked key rejected with 403 Forbidden");

    // Reactivate key 1
    $react1 = LlmAccess::revokeKey($key1['id'], 'active');
    assert_test($react1['success'] === true && $react1['status'] === 'active', "Key 1 reactivated");
    $valActiveAgain = LlmAccess::validateKey($key1['key']);
    assert_test($valActiveAgain['valid'] === true, "Reactivated key valid again");

    // Test Expired Key
    $pastDate = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago
    $genExp = LlmAccess::generateKey('Expired Agent', '', ['markdown'], null);
    $keyExp = $genExp['key'];
    // Manually force expiry in past to test validation
    $cfg = Config::load();
    foreach ($cfg['llmKeys'] as &$k) {
        if ($k['id'] === $keyExp['id']) {
            $k['expiresAt'] = $pastDate;
        }
    }
    unset($k);
    Config::save($cfg);

    $valExp = LlmAccess::validateKey($keyExp['key']);
    assert_test($valExp['valid'] === false && $valExp['code'] === 401, "Expired key rejected with 401");

    // Extend / renew expired key
    $newFuture = date('Y-m-d H:i:s', time() + 7200);
    $extResult = LlmAccess::updateKeyExpiry($keyExp['id'], $newFuture);
    assert_test($extResult['success'] === true, "Expired key extended");
    $valRenewed = LlmAccess::validateKey($keyExp['key']);
    assert_test($valRenewed['valid'] === true, "Renewed key valid again");

    // 4. Mode 1: Document Tree Mode
    echo "\n--- 4. Testing Mode 1: Document Tree Mode ---\n";

    // Unrestricted key tree
    $treeAll = LlmAccess::buildDocumentTree($key1);
    assert_test(!empty($treeAll), "Unrestricted tree returned documents");
    
    $foundGettingStarted = false;
    $foundUserGuide = false;
    $foundPdf = false;

    foreach ($treeAll as $doc) {
        assert_test(isset($doc['title']), "Doc has title");
        assert_test(isset($doc['slug']), "Doc has slug");
        assert_test(isset($doc['description']), "Doc has description");
        assert_test(isset($doc['url']), "Doc has web URL");
        assert_test(isset($doc['api_url']), "Doc has api_url");
        assert_test(isset($doc['word_count']), "Doc has word count");
        assert_test(isset($doc['estimated_tokens']), "Doc has estimated tokens");

        if ($doc['category_id'] === 'getting-started') $foundGettingStarted = true;
        if ($doc['category_id'] === 'user-guide') $foundUserGuide = true;
        if ($doc['type'] === 'pdf') $foundPdf = true;
    }
    assert_test($foundGettingStarted, "Tree includes getting-started documents");
    assert_test($foundUserGuide, "Tree includes user-guide documents");
    assert_test(!$foundPdf, "Key 1 (markdown-only) excluded PDF documents");

    // Scoped key tree (Key 2 scoped to 'getting-started' and allows pdf)
    $treeScoped = LlmAccess::buildDocumentTree($key2);
    assert_test(!empty($treeScoped), "Scoped tree returned documents");

    $scopedHasUserGuide = false;
    $scopedHasPdf = false;
    foreach ($treeScoped as $doc) {
        if ($doc['category_id'] === 'user-guide') {
            $scopedHasUserGuide = true;
        }
        if ($doc['type'] === 'pdf') {
            $scopedHasPdf = true;
        }
    }
    assert_test(!$scopedHasUserGuide, "Key 2 scoped to getting-started strictly excludes user-guide documents");
    assert_test($scopedHasPdf, "Key 2 with PDF permitted includes sample-pdf");

    // Test llms.txt formatting
    $llmsTxt = LlmAccess::renderLlmsTxt($treeScoped, 'Test Wiki', 'A test wiki');
    assert_test(strpos($llmsTxt, '# Test Wiki') !== false, "llms.txt has markdown title");
    assert_test(strpos($llmsTxt, '## Available Documents') !== false, "llms.txt has documents section");
    assert_test(strpos($llmsTxt, '- [') !== false, "llms.txt has document links");

    // 5. Mode 2: Document Access Mode
    echo "\n--- 5. Testing Mode 2: Document Access Mode ---\n";

    // Valid markdown retrieval
    $docRes = LlmAccess::retrieveDocument($key1, 'features');
    assert_test($docRes['success'] === true, "features document retrieved");
    $doc = $docRes['document'];
    assert_test($doc['slug'] === 'features', "Document slug matches");
    assert_test(!empty($doc['content']), "Document content is non-empty");
    assert_test($doc['word_count'] > 0, "Document word count calculated");
    assert_test($doc['estimated_tokens'] > 0, "Document token estimate calculated");

    // Test offset and limit
    $partialRes = LlmAccess::retrieveDocument($key1, 'features', 0, 100);
    assert_test($partialRes['success'] === true, "Partial retrieval succeeded");
    assert_test($partialRes['document']['returned_bytes'] <= 100, "Returned bytes respected limit");
    assert_test($partialRes['document']['is_truncated'] === true, "is_truncated flag is true");

    // Test Scope Violation: Key 2 is scoped to 'getting-started'. Try to retrieve 'managing-content' (in user-guide)
    $scopeViolation = LlmAccess::retrieveDocument($key2, 'managing-content');
    assert_test($scopeViolation['success'] === false, "Out-of-scope document retrieval failed");
    assert_test($scopeViolation['code'] === 403, "Out-of-scope document returns 403 Forbidden");

    // Test Document Type Violation: Key 1 only allows markdown. Try to retrieve 'sample-pdf'
    $typeViolation = LlmAccess::retrieveDocument($key1, 'sample-pdf');
    assert_test($typeViolation['success'] === false, "Disallowed document type retrieval failed");
    assert_test($typeViolation['code'] === 403, "Disallowed document type returns 403 Forbidden");

    // Test Non-existent Document
    $notFound = LlmAccess::retrieveDocument($key1, 'non-existent-slug-xyz');
    assert_test($notFound['success'] === false && $notFound['code'] === 404, "Unknown document returns 404 Not Found");

    // 6. Mode 3: Search Mode
    echo "\n--- 6. Testing Mode 3: Search Mode ---\n";

    $searchRes = LlmAccess::searchDocuments($key1, 'standalone');
    assert_test(!empty($searchRes), "Search returned matches for 'standalone'");
    assert_test(isset($searchRes[0]['relevance_score']), "Search result includes relevance score");
    assert_test(isset($searchRes[0]['excerpt']), "Search result includes excerpt");

    // Search with scoped key
    $searchScoped = LlmAccess::searchDocuments($key2, 'theme');
    // 'theme' is in user-guide, which Key 2 cannot access
    $hasUserGuideInSearch = false;
    foreach ($searchScoped as $match) {
        if ($match['slug'] === 'themes-overview' || $match['slug'] === 'customizing-themes') {
            $hasUserGuideInSearch = true;
        }
    }
    assert_test(!$hasUserGuideInSearch, "Scoped key cannot find documents outside its permitted branch");

    // 7. OpenAPI Schema Mode
    echo "\n--- 7. Testing OpenAPI Schema ---\n";
    $schema = LlmAccess::getOpenApiSpec(Config::getBaseUrl());
    assert_test(isset($schema['openapi']) && strpos($schema['openapi'], '3.0') === 0, "OpenAPI version is 3.0.x");
    assert_test(isset($schema['paths']['/api/llm.php']), "Schema defines /api/llm.php path");

    // 8. Integration: api/llm.php Endpoint Dispatch via CLI Subprocesses
    echo "\n--- 8. Testing api/llm.php Endpoint Dispatch ---\n";

    function run_api_llm(array $getParams, array $serverParams = []) {
        $phpCode = '$_GET = ' . var_export($getParams, true) . '; ';
        $phpCode .= '$_SERVER = array_merge($_SERVER, ' . var_export($serverParams, true) . '); ';
        $phpCode .= 'include "' . __DIR__ . '/../api/llm.php";';
        $cmd = 'php -r ' . escapeshellarg($phpCode);
        return shell_exec($cmd);
    }

    // Test schema endpoint via api/llm.php
    $schemaOutput = run_api_llm(['mode' => 'schema']);
    $schemaJson = json_decode($schemaOutput, true);
    assert_test(!empty($schemaJson['openapi']), "api/llm.php?mode=schema returned valid JSON OpenAPI spec");

    // Test tree mode endpoint via api/llm.php
    $treeOutput = run_api_llm(['mode' => 'tree', 'key' => $key1['key']]);
    $treeJson = json_decode($treeOutput, true);
    assert_test(!empty($treeJson['success']) && is_array($treeJson['documents']), "api/llm.php?mode=tree returned valid JSON documents tree");

    // Test llms.txt format via api/llm.php
    $llmsOutput = run_api_llm(['mode' => 'tree', 'key' => $key1['key'], 'format' => 'llms.txt']);
    assert_test(strpos($llmsOutput, '# Standalone Qwiki') !== false, "api/llm.php?format=llms.txt returned markdown");

    // Test doc mode endpoint via api/llm.php
    $docOutput = run_api_llm(['mode' => 'doc', 'slug' => 'introduction', 'key' => $key1['key']]);
    $docJson = json_decode($docOutput, true);
    assert_test(!empty($docJson['success']) && !empty($docJson['document']['content']), "api/llm.php?mode=doc returned document content");

    // Test search mode endpoint via api/llm.php
    $searchOutput = run_api_llm(['mode' => 'search', 'q' => 'wiki', 'key' => $key1['key']]);
    $searchJson = json_decode($searchOutput, true);
    assert_test(!empty($searchJson['success']) && is_array($searchJson['matches']), "api/llm.php?mode=search returned matches");

    echo "\n=== ALL 32 ASSERTIONS PASSED SUCCESSFULLY! ===\n";

} finally {
    // Cleanup: Restore original keys in qwiki.json
    $restoreCfg = Config::load();
    $restoreCfg['llmKeys'] = $originalKeys;
    Config::save($restoreCfg);
    echo "Test cleanup complete: restored original qwiki.json keys.\n";
}
