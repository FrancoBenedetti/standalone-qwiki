<?php
// api/llm.php - Controlled LLM & AI Agent Access Endpoint

require_once __DIR__ . '/../lib/Core/Config.php';
require_once __DIR__ . '/../lib/Core/Auth.php';
require_once __DIR__ . '/../lib/Core/Navigation.php';
require_once __DIR__ . '/../lib/Core/ExtensionManager.php';
require_once __DIR__ . '/../lib/Core/LlmAccess.php';

use Qwiki\Core\Config;
use Qwiki\Core\LlmAccess;

function llm_header($header) {
    if (!headers_sent()) {
        header($header);
    }
}

function llm_response_code($code) {
    if (!headers_sent()) {
        http_response_code($code);
    }
}

$config = Config::load();
$baseUrl = Config::getBaseUrl();

$mode = strtolower(trim($_GET['mode'] ?? 'tree'));

// 1. Unauthenticated or Open API Schema Endpoint
if ($mode === 'schema' || $mode === 'openapi') {
    llm_header('Content-Type: application/json; charset=utf-8');
    echo json_encode(LlmAccess::getOpenApiSpec($baseUrl), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// 2. Validate API Key
$val = LlmAccess::validateKey();
if (!$val['valid']) {
    llm_response_code($val['code']);
    llm_header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $val['error'],
        'code' => $val['code']
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$keyRecord = $val['key'];

// Update key last used timestamp
LlmAccess::updateLastUsed($keyRecord['id']);

// 3. Handle Operation Modes
switch ($mode) {
    case 'tree':
        $categoryFilter = $_GET['category'] ?? null;
        $format = strtolower(trim($_GET['format'] ?? 'json'));

        $treeDocs = LlmAccess::buildDocumentTree($keyRecord, $categoryFilter);

        // HTTP Conditional Caching (ETag / Last-Modified)
        $mtimes = array_column($treeDocs, 'mtime');
        $maxMtime = !empty($mtimes) ? max($mtimes) : time();
        $eTag = '"' . md5(implode('-', $mtimes) . count($treeDocs) . ($categoryFilter ?? '')) . '"';

        llm_header('ETag: ' . $eTag);
        llm_header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $maxMtime) . ' GMT');
        llm_header('Cache-Control: private, must-revalidate, max-age=60');

        $clientETag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : '';
        if (!empty($clientETag) && $clientETag === $eTag) {
            llm_response_code(304);
            exit;
        }

        if ($format === 'llms.txt' || $format === 'markdown' || $format === 'md') {
            llm_header('Content-Type: text/markdown; charset=utf-8');
            echo LlmAccess::renderLlmsTxt($treeDocs, $config['title'] ?? 'Standalone Qwiki', $config['shareDescription'] ?? '');
            exit;
        }

        llm_header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'wiki_title' => $config['title'] ?? 'Standalone Qwiki',
            'key_name' => $keyRecord['name'],
            'category_scope' => !empty($keyRecord['category']) ? $keyRecord['category'] : 'all',
            'allowed_types' => $keyRecord['allowedTypes'],
            'total_documents' => count($treeDocs),
            'documents' => $treeDocs
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        break;

    case 'doc':
    case 'document':
        $slug = $_GET['slug'] ?? '';
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $limit = isset($_GET['limit']) ? max(0, (int)$_GET['limit']) : 0;
        $format = strtolower(trim($_GET['format'] ?? 'json'));

        $docResult = LlmAccess::retrieveDocument($keyRecord, $slug, $offset, $limit);

        if (!$docResult['success']) {
            llm_response_code($docResult['code'] ?? 400);
            llm_header('Content-Type: application/json; charset=utf-8');
            echo json_encode($docResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $doc = $docResult['document'];

        // If raw/markdown format requested, return directly with YAML frontmatter
        if ($format === 'raw' || $format === 'markdown' || $format === 'text' || $format === 'md') {
            llm_header('Content-Type: text/markdown; charset=utf-8');
            echo "---\n";
            echo "title: " . json_encode($doc['title']) . "\n";
            echo "slug: " . json_encode($doc['slug']) . "\n";
            echo "category: " . json_encode($doc['category']) . "\n";
            echo "date_modified: " . json_encode($doc['date_modified']) . "\n";
            echo "url: " . json_encode($doc['url']) . "\n";
            echo "---\n\n";
            echo $doc['content'];
            exit;
        }

        llm_header('Content-Type: application/json; charset=utf-8');
        echo json_encode($docResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        break;

    case 'search':
        $query = $_GET['q'] ?? '';
        $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 10;

        $matches = LlmAccess::searchDocuments($keyRecord, $query, $limit);

        llm_header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'query' => $query,
            'total_matches' => count($matches),
            'matches' => $matches
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        break;

    default:
        llm_response_code(400);
        llm_header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => "Unknown mode '{$mode}'. Supported modes are: tree, doc, search, schema."
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;
}
