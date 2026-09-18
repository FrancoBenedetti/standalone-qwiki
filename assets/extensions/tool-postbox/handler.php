<?php
/**
 * Standalone Qwiki - Document Postbox Extension Handler
 * 
 * Handles peer discovery, packaging & dispatching envelopes,
 * remote HTTP ingestion, and inbox review/acceptance.
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\SubwikiManager;
use Qwiki\Extension\Postbox\Envelope;

require_once __DIR__ . '/Envelope.php';

$baseDir = Config::getBaseDir();
$action = $action ?? ($_REQUEST['action'] ?? '');

// Helper to send clean JSON responses
if (!function_exists('postboxJsonReply')) {
    function postboxJsonReply(array $data, int $httpStatus = 200) {
        if (!headers_sent()) {
            http_response_code($httpStatus);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// -------------------------------------------------------------
// 1. INBOUND RECEIVER ENDPOINT (Authenticated via Token)
// -------------------------------------------------------------
if ($action === 'ext_postbox_receive') {
    if (!Config::isPostboxEnabled()) {
        postboxJsonReply(['success' => false, 'error' => 'Postbox service is disabled on this wiki.'], 403);
    }

    $configuredToken = Config::getPostboxToken();
    $providedToken = $_SERVER['HTTP_X_POSTBOX_TOKEN'] ?? '';
    if (empty($providedToken) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $providedToken = trim($m[1]);
        }
    }
    if (empty($providedToken)) {
        $providedToken = $_REQUEST['token'] ?? '';
    }

    if (empty($configuredToken) || !hash_equals($configuredToken, $providedToken)) {
        postboxJsonReply(['success' => false, 'error' => 'Unauthorized: Invalid or missing Postbox Token.'], 401);
    }

    $rawInput = file_get_contents('php://input');
    if (empty($rawInput) && !empty($_POST['payload'])) {
        $rawInput = $_POST['payload'];
    }

    $envelope = json_decode($rawInput, true);
    if (!is_array($envelope) || empty($envelope['documents'])) {
        postboxJsonReply(['success' => false, 'error' => 'Invalid postbox payload.'], 400);
    }

    $result = Envelope::saveStagedEnvelope($baseDir, $envelope);
    if (!empty($result['success'])) {
        postboxJsonReply([
            'success' => true,
            'message' => 'Envelope successfully staged in recipient postbox inbox.',
            'batch_id' => $result['batch_id']
        ]);
    } else {
        postboxJsonReply($result, 500);
    }
}

// -------------------------------------------------------------
// 2. ADMIN GUARD FOR ALL REMAINING ACTIONS
// -------------------------------------------------------------
if (!Auth::isAdmin()) {
    postboxJsonReply(['success' => false, 'error' => 'Unauthorized. Administrator privileges required.'], 403);
}

// -------------------------------------------------------------
// 3. GET UNREAD INBOX COUNT
// -------------------------------------------------------------
if ($action === 'ext_postbox_count') {
    $envelopes = Envelope::listInboxEnvelopes($baseDir);
    $totalDocs = 0;
    foreach ($envelopes as $env) {
        $totalDocs += (int)($env['doc_count'] ?? 0);
    }
    postboxJsonReply([
        'success' => true,
        'batch_count' => count($envelopes),
        'doc_count' => $totalDocs
    ]);
}

// -------------------------------------------------------------
// 4. LIST PENDING INBOX ITEMS
// -------------------------------------------------------------
if ($action === 'ext_postbox_inbox') {
    $envelopes = Envelope::listInboxEnvelopes($baseDir);
    $config = Config::load();
    $categories = [];
    foreach ($config['books'] ?? [] as $book) {
        $categories[] = [
            'id' => $book['id'] ?? '',
            'title' => $book['title'] ?? ($book['id'] ?? 'Untitled'),
            'type' => $book['type'] ?? 'folder'
        ];
    }

    postboxJsonReply([
        'success' => true,
        'envelopes' => $envelopes,
        'categories' => $categories,
        'my_wiki' => [
            'title' => $config['title'] ?? 'Standalone Qwiki',
            'token' => Config::getPostboxToken(),
            'is_subwiki' => Config::isSubwiki()
        ]
    ]);
}

// -------------------------------------------------------------
// 5. ENUMERATE AVAILABLE DESTINATIONS (PEERS)
// -------------------------------------------------------------
if ($action === 'ext_postbox_peers') {
    $config = Config::load();
    $localPeers = [];
    $isSubwiki = Config::isSubwiki();

    if (!$isSubwiki) {
        // Parent wiki: can send to any child subwiki
        $subwikis = SubwikiManager::listSubwikis();
        foreach ($subwikis as $slug => $sub) {
            $dir = $baseDir . '/' . $slug;
            if (is_dir($dir)) {
                $localPeers[] = [
                    'id' => 'local:' . $slug,
                    'type' => 'local',
                    'slug' => $slug,
                    'name' => 'Subwiki: ' . ($sub['title'] ?? $slug),
                    'path' => $dir,
                    'url' => Config::getBaseUrl() . $slug . '/'
                ];
            }
        }
    } else {
        // Child subwiki: can send to parent wiki or sibling subwikis
        $parentDir = dirname($baseDir);
        if (file_exists($parentDir . '/qwiki.json')) {
            $parentTitle = SubwikiManager::getParentTitle($config);
            $localPeers[] = [
                'id' => 'local:parent',
                'type' => 'local',
                'slug' => 'parent',
                'name' => 'Parent Wiki (' . $parentTitle . ')',
                'path' => $parentDir,
                'url' => dirname(rtrim(Config::getBaseUrl(), '/')) . '/'
            ];

            // Discover siblings
            $dirs = @glob($parentDir . '/*', GLOB_ONLYDIR) ?: [];
            $mySlug = basename($baseDir);
            $reserved = array_merge(Config::getReservedNames(), ['demo-data', 'assets', 'lib', 'content', 'uploads', 'api', 'tests']);

            foreach ($dirs as $dir) {
                $slug = basename($dir);
                if ($slug === $mySlug || in_array($slug, $reserved, true)) continue;
                if (file_exists($dir . '/qwiki.json')) {
                    $subData = @json_decode(file_get_contents($dir . '/qwiki.json'), true);
                    $title = is_array($subData) && !empty($subData['title']) ? $subData['title'] : $slug;
                    $localPeers[] = [
                        'id' => 'local:' . $slug,
                        'type' => 'local',
                        'slug' => $slug,
                        'name' => 'Sibling Subwiki: ' . $title,
                        'path' => $dir,
                        'url' => dirname(rtrim(Config::getBaseUrl(), '/')) . '/' . $slug . '/'
                    ];
                }
            }
        }
    }

    $remotePeers = Config::getPostboxPeers();

    postboxJsonReply([
        'success' => true,
        'local_peers' => $localPeers,
        'remote_peers' => $remotePeers,
        'my_token' => Config::getPostboxToken()
    ]);
}

// -------------------------------------------------------------
// 6. SEND / DISPATCH DOCUMENTS
// -------------------------------------------------------------
if ($action === 'ext_postbox_send') {
    $config = Config::load();
    $targetPeerId = trim($_POST['target_peer_id'] ?? '');
    $customUrl = trim($_POST['custom_url'] ?? '');
    $customToken = trim($_POST['custom_token'] ?? '');

    // Gather documents to send
    $mode = $_POST['mode'] ?? 'single';
    $docsToPackage = [];

    // Helper to find document in hierarchy
    $findDocument = function($nodes, $slug, $bookId = null) use (&$findDocument) {
        foreach ($nodes as $node) {
            if (($node['slug'] ?? '') === $slug) {
                return $node;
            }
            if (!empty($node['items']) && is_array($node['items'])) {
                $found = $findDocument($node['items'], $slug, $bookId);
                if ($found) return $found;
            }
        }
        return null;
    };

    if ($mode === 'single') {
        $slug = trim($_POST['slug'] ?? '');
        $bookId = trim($_POST['bookId'] ?? '');
        if (empty($slug)) {
            postboxJsonReply(['success' => false, 'error' => 'No document slug specified.'], 400);
        }
        $docNode = $findDocument($config['books'], $slug, $bookId);
        if (!$docNode) {
            postboxJsonReply(['success' => false, 'error' => "Document '{$slug}' not found in configuration."], 404);
        }
        $docsToPackage[] = $docNode;
    } elseif ($mode === 'category') {
        $categoryId = trim($_POST['category_id'] ?? '');
        if (empty($categoryId)) {
            postboxJsonReply(['success' => false, 'error' => 'No category specified.'], 400);
        }
        // Find category and collect its items
        foreach ($config['books'] as $book) {
            if (($book['id'] ?? '') === $categoryId) {
                foreach ($book['items'] ?? [] as $item) {
                    if (isset($item['type']) && $item['type'] !== 'folder' && !empty($item['slug'])) {
                        $docsToPackage[] = $item;
                    }
                }
                break;
            }
        }
        if (empty($docsToPackage)) {
            postboxJsonReply(['success' => false, 'error' => "No documents found in category '{$categoryId}'."], 404);
        }
    } elseif ($mode === 'bulk') {
        $docKeys = json_decode($_POST['doc_keys'] ?? '[]', true);
        if (!is_array($docKeys) || empty($docKeys)) {
            postboxJsonReply(['success' => false, 'error' => 'No documents selected for bulk transfer.'], 400);
        }
        foreach ($docKeys as $k) {
            $slug = is_array($k) ? ($k['slug'] ?? '') : (string)$k;
            if ($slug) {
                $found = $findDocument($config['books'], $slug);
                if ($found) $docsToPackage[] = $found;
            }
        }
        if (empty($docsToPackage)) {
            postboxJsonReply(['success' => false, 'error' => 'None of the selected documents could be found.'], 404);
        }
    }

    // Build the package envelope
    $envelope = Envelope::createPackage($docsToPackage, $baseDir, $config);

    // Destination handling
    if (strpos($targetPeerId, 'local:') === 0) {
        $localSlug = substr($targetPeerId, 6);
        $targetDir = '';

        if ($localSlug === 'parent') {
            $targetDir = dirname($baseDir);
        } else {
            // Either a subwiki of current or sibling
            $isSubwiki = Config::isSubwiki();
            if (!$isSubwiki) {
                $targetDir = $baseDir . '/' . $localSlug;
            } else {
                $targetDir = dirname($baseDir) . '/' . $localSlug;
            }
        }

        // Multitenant Boundary Check
        if (defined('QWIKI_BASE_DIR')) {
            $allowedRoot = realpath(QWIKI_BASE_DIR);
            $realTarget = realpath($targetDir);
            if ($realTarget === false || strpos($realTarget, $allowedRoot) !== 0) {
                postboxJsonReply([
                    'success' => false,
                    'error' => 'Cross-tenant direct filesystem transfer is forbidden for isolation security. Please use the HTTP Postbox endpoint with token.'
                ], 403);
            }
        }

        if (!is_dir($targetDir) || !file_exists($targetDir . '/qwiki.json')) {
            postboxJsonReply(['success' => false, 'error' => 'Target local wiki directory does not exist or lacks qwiki.json.'], 404);
        }

        $stagedResult = Envelope::saveStagedEnvelope($targetDir, $envelope);
        if (!empty($stagedResult['success'])) {
            postboxJsonReply([
                'success' => true,
                'message' => count($docsToPackage) . ' document(s) delivered directly to recipient local inbox.',
                'batch_id' => $stagedResult['batch_id']
            ]);
        } else {
            postboxJsonReply($stagedResult, 500);
        }
    } else {
        // Remote or domain peer HTTP dispatch
        $targetUrl = '';
        $token = '';

        if (strpos($targetPeerId, 'remote:') === 0) {
            $peerId = substr($targetPeerId, 7);
            $savedPeers = Config::getPostboxPeers();
            foreach ($savedPeers as $p) {
                if (($p['id'] ?? '') === $peerId) {
                    $targetUrl = $p['url'] ?? '';
                    $token = $p['token'] ?? '';
                    break;
                }
            }
        } elseif (!empty($customUrl)) {
            $targetUrl = $customUrl;
            $token = $customToken;
        }

        if (empty($targetUrl)) {
            postboxJsonReply(['success' => false, 'error' => 'Invalid or unspecified destination URL.'], 400);
        }

        // Normalize destination endpoint
        $endpoint = rtrim($targetUrl, '/') . '/api/admin.php?action=ext_postbox_receive';
        $jsonPayload = json_encode($envelope);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Postbox-Token: ' . $token,
            'User-Agent: StandaloneQwiki-Postbox/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed in local dev

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            postboxJsonReply(['success' => false, 'error' => 'cURL error connecting to peer: ' . $curlError], 502);
        }

        $remoteResult = @json_decode($response, true);
        if ($httpCode === 200 && !empty($remoteResult['success'])) {
            postboxJsonReply([
                'success' => true,
                'message' => count($docsToPackage) . ' document(s) sent to remote postbox inbox.',
                'batch_id' => $remoteResult['batch_id'] ?? ''
            ]);
        } else {
            $msg = $remoteResult['error'] ?? ("Peer responded with HTTP " . $httpCode . ": " . substr(strip_tags($response), 0, 150));
            postboxJsonReply(['success' => false, 'error' => $msg], $httpCode ?: 500);
        }
    }
}

// -------------------------------------------------------------
// 7. INGEST / ACCEPT DOCUMENT
// -------------------------------------------------------------
if ($action === 'ext_postbox_accept') {
    $batchId = trim($_POST['batch_id'] ?? '');
    $docIdentifier = $_POST['doc_index'] ?? ($_POST['doc_id'] ?? 0);
    $targetBookId = trim($_POST['target_book_id'] ?? '');

    if (empty($batchId) || empty($targetBookId)) {
        postboxJsonReply(['success' => false, 'error' => 'Batch ID and target category are required.'], 400);
    }

    $overrides = [
        'title' => trim($_POST['title'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'type' => trim($_POST['type'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'theme' => trim($_POST['theme'] ?? ''),
        'readOnly' => !empty($_POST['readOnly']),
        'new_category_title' => trim($_POST['new_category_title'] ?? '')
    ];

    $config = Config::load();
    $result = Envelope::ingestDocument($baseDir, $config, $batchId, $docIdentifier, $targetBookId, $overrides);

    postboxJsonReply($result, !empty($result['success']) ? 200 : 400);
}

// -------------------------------------------------------------
// 8. REJECT DOCUMENT OR BATCH
// -------------------------------------------------------------
if ($action === 'ext_postbox_reject') {
    $batchId = trim($_POST['batch_id'] ?? '');
    $docIndex = $_POST['doc_index'] ?? null;

    if (empty($batchId)) {
        postboxJsonReply(['success' => false, 'error' => 'Batch ID is required.'], 400);
    }

    $result = Envelope::rejectEnvelope($baseDir, $batchId, $docIndex);
    postboxJsonReply($result, !empty($result['success']) ? 200 : 400);
}

// -------------------------------------------------------------
// 9. SAVE SETTINGS (Token & Peers)
// -------------------------------------------------------------
if ($action === 'ext_postbox_save_settings') {
    if (!empty($_POST['regenerate_token'])) {
        try {
            $newToken = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $newToken = md5(uniqid((string)mt_rand(), true));
        }
        Config::setPostboxToken($newToken);
        postboxJsonReply(['success' => true, 'token' => $newToken]);
    }

    if (isset($_POST['peers'])) {
        $peers = json_decode($_POST['peers'], true);
        if (is_array($peers)) {
            Config::savePostboxPeers($peers);
            postboxJsonReply(['success' => true, 'peers' => $peers]);
        }
    }

    postboxJsonReply(['success' => false, 'error' => 'No settings action specified.'], 400);
}

// -------------------------------------------------------------
// 10. DOWNLOAD DESKTOP TOOLS BUNDLE (ZIP)
// -------------------------------------------------------------
if ($action === 'ext_postbox_download_cli') {
    $zipArchive = new \ZipArchive();
    $tmpZip = tempnam(sys_get_temp_dir(), 'qwiki_cli_') . '.zip';

    if ($zipArchive->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        postboxJsonReply(['success' => false, 'error' => 'Failed to create temporary zip archive.'], 500);
    }

    // Locate tools directory (check extension cli dir, project root, baseDir, or shared core)
    $candidateDirs = [
        __DIR__ . '/cli',
        dirname(__DIR__, 3) . '/tools',
        Config::getBaseDir() . '/tools',
        dirname(Config::getBaseDir()) . '/tools',
        dirname(Config::getBaseDir(), 2) . '/tools'
    ];
    $coreTools = null;
    foreach ($candidateDirs as $cand) {
        if (is_dir($cand) && file_exists($cand . '/qwiki-postbox.py')) {
            $coreTools = $cand;
            break;
        }
    }

    $makeExecutable = function($entryName) use ($zipArchive) {
        if (defined('\ZipArchive::OPSYS_UNIX')) {
            $zipArchive->setExternalAttributesName($entryName, \ZipArchive::OPSYS_UNIX, (0100755 << 16));
        }
    };

    if ($coreTools) {
        $cliScript = $coreTools . '/qwiki-postbox.py';
        if (file_exists($cliScript)) {
            $zipArchive->addFile($cliScript, 'qwiki-postbox/qwiki-postbox.py');
            $makeExecutable('qwiki-postbox/qwiki-postbox.py');
        }

        $linuxScript = $coreTools . '/desktop/linux/install-nautilus.sh';
        if (file_exists($linuxScript)) {
            $zipArchive->addFile($linuxScript, 'qwiki-postbox/desktop/linux/install-nautilus.sh');
            $makeExecutable('qwiki-postbox/desktop/linux/install-nautilus.sh');
        }

        $winScript = $coreTools . '/desktop/windows/setup-sendto.bat';
        if (file_exists($winScript)) {
            $zipArchive->addFile($winScript, 'qwiki-postbox/desktop/windows/setup-sendto.bat');
        }

        $macScript = $coreTools . '/desktop/macos/install-quickaction.sh';
        if (file_exists($macScript)) {
            $zipArchive->addFile($macScript, 'qwiki-postbox/desktop/macos/install-quickaction.sh');
            $makeExecutable('qwiki-postbox/desktop/macos/install-quickaction.sh');
        }
    }

    // Include pre-configured profile for this wiki
    $preConfig = [
        'profiles' => [
            'default' => [
                'url' => Config::getBaseUrl(),
                'token' => Config::getPostboxToken()
            ]
        ]
    ];
    $zipArchive->addFromString('qwiki-postbox/config.sample.json', json_encode($preConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    // Include README.md if present
    if ($coreTools && file_exists($coreTools . '/README.md')) {
        $zipArchive->addFile($coreTools . '/README.md', 'qwiki-postbox/README.md');
    }

    $readme = "Standalone Qwiki - Desktop Postbox CLI & OS Tools\n"
            . "=================================================\n\n"
            . "Quick Start:\n\n"
            . "1. Copy 'config.sample.json' to your user configuration directory:\n"
            . "   - Linux/macOS: ~/.config/qwiki/config.json\n"
            . "   - Windows:     %USERPROFILE%\\.qwiki\\config.json\n"
            . "   (This file is pre-configured with this wiki's URL and Postbox Access Token.)\n\n"
            . "2. Terminal CLI Usage:\n"
            . "   python3 qwiki-postbox.py send path/to/document.md\n"
            . "   python3 qwiki-postbox.py send path/to/folder/ --category \"My Category\"\n\n"
            . "3. OS Context Menu Integrations ('Send to Qwiki'):\n\n"
            . "   - Linux (GNOME Nautilus / Nemo / Caja):\n"
            . "       Run: bash desktop/linux/install-nautilus.sh\n"
            . "       (or: chmod +x desktop/linux/install-nautilus.sh && ./desktop/linux/install-nautilus.sh)\n\n"
            . "       How to access:\n"
            . "       1. Select a file or folder in your file manager.\n"
            . "          (Note: The 'Scripts' menu only appears when an item is selected, not on empty space.)\n"
            . "       2. Right-click the selected file or folder.\n"
            . "       3. Hover or click 'Scripts' in the context menu, then choose 'Send to Qwiki'.\n"
            . "       4. If 'Scripts' is not visible immediately, reload your file manager: nautilus -q\n\n"
            . "   - Windows (File Explorer):\n"
            . "       Double-click: desktop/windows/setup-sendto.bat\n"
            . "       * Access via: Right-click any file/folder -> Send to -> Send to Qwiki\n\n"
            . "   - macOS (Finder Quick Actions):\n"
            . "       Run: bash desktop/macos/install-quickaction.sh\n"
            . "       * Access via: Right-click any file/folder -> Quick Actions -> Send to Qwiki\n\n"
            . "4. Requirements:\n"
            . "   - Python 3.6+ (Standard library only; zero pip dependencies required)\n"
            . "   - Linux notifications (optional): libnotify-bin (notify-send) or zenity\n";
    $zipArchive->addFromString('qwiki-postbox/README.txt', $readme);

    $zipArchive->close();

    if (file_exists($tmpZip)) {
        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="qwiki-postbox-desktop-tools.zip"');
            header('Content-Length: ' . filesize($tmpZip));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }

    postboxJsonReply(['success' => false, 'error' => 'Zip generation failed.'], 500);
}

postboxJsonReply(['success' => false, 'error' => 'Invalid Postbox action.'], 400);
