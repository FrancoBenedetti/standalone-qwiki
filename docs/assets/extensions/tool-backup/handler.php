<?php
/**
 * Standalone Qwiki - Backup & Export Extension Handler
 * 
 * Provides backend actions for scanning wiki content/uploads/config
 * and creating downloadable ZIP archives in both Full and Selective modes.
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;
use Qwiki\Core\SubwikiManager;

// 1. Authorization Guard: Admin only
if (!Auth::isAdmin()) {
    if (!headers_sent()) {
        http_response_code(403);
    }
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Administrator privileges required.']);
    return;
}

$baseDir = Config::getBaseDir();

// Helper to format byte sizes
if (!function_exists('backupFormatBytes')) {
    function backupFormatBytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max((int)$bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min((int)$pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// Helper to recursively scan a directory and return a structured tree
if (!function_exists('backupScanDirectory')) {
    function backupScanDirectory($dir, $baseDir, &$totalBytes = 0, &$totalFiles = 0) {
        if (!is_dir($dir)) return [];
        $items = [];
        $files = @scandir($dir);
        if (!$files) return [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if ($file === '.git' || $file === '.env') continue;
            if (substr($file, -4) === '.tmp' || substr($file, -5) === '.lock') continue;

            $fullPath = $dir . '/' . $file;
            $relPath = ltrim(str_replace('\\', '/', substr($fullPath, strlen($baseDir))), '/');

            if (is_dir($fullPath)) {
                $dirBytes = 0;
                $dirFiles = 0;
                $children = backupScanDirectory($fullPath, $baseDir, $dirBytes, $dirFiles);
                $totalBytes += $dirBytes;
                $totalFiles += $dirFiles;
                $items[] = [
                    'name' => $file,
                    'path' => $relPath,
                    'type' => 'folder',
                    'bytes' => $dirBytes,
                    'filesCount' => $dirFiles,
                    'formattedSize' => backupFormatBytes($dirBytes),
                    'children' => $children
                ];
            } else {
                $size = @filesize($fullPath) ?: 0;
                $totalBytes += $size;
                $totalFiles++;
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $items[] = [
                    'name' => $file,
                    'path' => $relPath,
                    'type' => 'file',
                    'ext' => $ext,
                    'bytes' => $size,
                    'formattedSize' => backupFormatBytes($size)
                ];
            }
        }
        return $items;
    }
}

// Helper to validate path safety against directory traversal and sensitive files
if (!function_exists('backupIsSafePath')) {
    function backupIsSafePath($relPath, $baseDir) {
        if (empty($relPath) || !is_string($relPath)) return false;
        $relPath = str_replace('\\', '/', trim($relPath));
        if (strpos($relPath, "\0") !== false || strpos($relPath, '..') !== false) {
            return false;
        }
        $relPath = ltrim($relPath, '/');
        $fullPath = realpath($baseDir . '/' . $relPath);
        $realBase = realpath($baseDir);
        if (!$fullPath || !$realBase) return false;
        if (strpos($fullPath, $realBase) !== 0) return false;

        $basename = basename($fullPath);
        if ($basename === '.env' || $basename === '.git' || strpos($basename, '.env.') === 0) {
            return false;
        }
        if (substr($basename, -4) === '.tmp' || substr($basename, -5) === '.lock') {
            return false;
        }

        return $fullPath;
    }
}

// Helper to collect all files under a directory recursively into an associative map [fullPath => zipRelPath]
if (!function_exists('backupCollectDirFiles')) {
    function backupCollectDirFiles($dir, $baseDir, &$fileMap) {
        if (!is_dir($dir)) return;
        $files = @scandir($dir);
        if (!$files) return;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if ($file === '.git' || $file === '.env') continue;
            if (substr($file, -4) === '.tmp' || substr($file, -5) === '.lock') continue;

            $fullPath = $dir . '/' . $file;
            $relPath = ltrim(str_replace('\\', '/', substr($fullPath, strlen($baseDir))), '/');

            if (is_dir($fullPath)) {
                backupCollectDirFiles($fullPath, $baseDir, $fileMap);
            } elseif (is_file($fullPath)) {
                $fileMap[$fullPath] = $relPath;
            }
        }
    }
}

$action = $_REQUEST['action'] ?? '';

// ACTION 1: SCAN FILES AND CALCULATE SIZES
if ($action === 'ext_backup_scan') {
    $contentBytes = 0;
    $contentFiles = 0;
    $contentTree = backupScanDirectory($baseDir . '/content', $baseDir, $contentBytes, $contentFiles);

    $uploadBytes = 0;
    $uploadFiles = 0;
    $uploadsTree = backupScanDirectory($baseDir . '/uploads', $baseDir, $uploadBytes, $uploadFiles);

    $qwikiJsonFile = $baseDir . '/qwiki.json';
    $qwikiExists = file_exists($qwikiJsonFile);
    $qwikiSize = $qwikiExists ? filesize($qwikiJsonFile) : 0;

    $usersJsonFile = $baseDir . '/users.json';
    $usersExists = file_exists($usersJsonFile);
    $usersSize = $usersExists ? filesize($usersJsonFile) : 0;

    // Subwikis discovery
    $subwikisList = [];
    if (class_exists('Qwiki\Core\SubwikiManager') && !SubwikiManager::isSubwiki()) {
        $discovered = SubwikiManager::listSubwikis();
        foreach ($discovered as $sub) {
            $slug = $sub['slug'] ?? '';
            $subPath = $baseDir . '/' . $slug;
            if ($slug && is_dir($subPath)) {
                $subBytes = 0;
                $subFiles = 0;
                backupScanDirectory($subPath . '/content', $baseDir, $subBytes, $subFiles);
                backupScanDirectory($subPath . '/uploads', $baseDir, $subBytes, $subFiles);
                if (file_exists($subPath . '/qwiki.json')) {
                    $subBytes += filesize($subPath . '/qwiki.json');
                    $subFiles++;
                }
                if (file_exists($subPath . '/users.json')) {
                    $subBytes += filesize($subPath . '/users.json');
                    $subFiles++;
                }
                $subwikisList[] = [
                    'slug' => $slug,
                    'title' => $sub['title'] ?? $slug,
                    'filesCount' => $subFiles,
                    'bytes' => $subBytes,
                    'formattedSize' => backupFormatBytes($subBytes)
                ];
            }
        }
    }

    $totalFiles = $contentFiles + $uploadFiles + ($qwikiExists ? 1 : 0) + ($usersExists ? 1 : 0);
    $totalBytes = $contentBytes + $uploadBytes + $qwikiSize + $usersSize;

    echo json_encode([
        'success' => true,
        'zipSupported' => class_exists('ZipArchive'),
        'summary' => [
            'content' => [
                'filesCount' => $contentFiles,
                'bytes' => $contentBytes,
                'formattedSize' => backupFormatBytes($contentBytes)
            ],
            'uploads' => [
                'filesCount' => $uploadFiles,
                'bytes' => $uploadBytes,
                'formattedSize' => backupFormatBytes($uploadBytes)
            ],
            'qwikiJson' => [
                'exists' => $qwikiExists,
                'bytes' => $qwikiSize,
                'formattedSize' => backupFormatBytes($qwikiSize)
            ],
            'usersJson' => [
                'exists' => $usersExists,
                'bytes' => $usersSize,
                'formattedSize' => backupFormatBytes($usersSize)
            ],
            'subwikis' => $subwikisList,
            'totalFiles' => $totalFiles,
            'totalBytes' => $totalBytes,
            'formattedTotalSize' => backupFormatBytes($totalBytes)
        ],
        'tree' => [
            'content' => $contentTree,
            'uploads' => $uploadsTree
        ]
    ]);
    return;
}

// ACTION 2: DOWNLOAD ZIP ARCHIVE
if ($action === 'ext_backup_download') {
    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'error' => 'ZipArchive PHP extension is not installed on this server.']);
        return;
    }

    $mode = $_REQUEST['mode'] ?? 'full';
    $includeUsers = isset($_REQUEST['include_users']) ? filter_var($_REQUEST['include_users'], FILTER_VALIDATE_BOOLEAN) : true;
    $includeSubwikis = isset($_REQUEST['include_subwikis']) ? filter_var($_REQUEST['include_subwikis'], FILTER_VALIDATE_BOOLEAN) : false;

    $filesToAdd = []; // fullPath => zipRelativePath

    if ($mode === 'full') {
        // 1. Navigation & Master Config
        if (file_exists($baseDir . '/qwiki.json')) {
            $filesToAdd[$baseDir . '/qwiki.json'] = 'qwiki.json';
        }

        // 2. User database
        if ($includeUsers && file_exists($baseDir . '/users.json')) {
            $filesToAdd[$baseDir . '/users.json'] = 'users.json';
        }

        // 3. Content directory
        if (is_dir($baseDir . '/content')) {
            backupCollectDirFiles($baseDir . '/content', $baseDir, $filesToAdd);
        }

        // 4. Uploads directory
        if (is_dir($baseDir . '/uploads')) {
            backupCollectDirFiles($baseDir . '/uploads', $baseDir, $filesToAdd);
        }

        // 5. Subwikis
        if ($includeSubwikis && class_exists('Qwiki\Core\SubwikiManager') && !SubwikiManager::isSubwiki()) {
            $discovered = SubwikiManager::listSubwikis();
            foreach ($discovered as $sub) {
                $slug = $sub['slug'] ?? '';
                if ($slug && is_dir($baseDir . '/' . $slug)) {
                    $subDir = $baseDir . '/' . $slug;
                    if (file_exists($subDir . '/qwiki.json')) {
                        $filesToAdd[$subDir . '/qwiki.json'] = $slug . '/qwiki.json';
                    }
                    if ($includeUsers && file_exists($subDir . '/users.json')) {
                        $filesToAdd[$subDir . '/users.json'] = $slug . '/users.json';
                    }
                    if (is_dir($subDir . '/content')) {
                        backupCollectDirFiles($subDir . '/content', $baseDir, $filesToAdd);
                    }
                    if (is_dir($subDir . '/uploads')) {
                        backupCollectDirFiles($subDir . '/uploads', $baseDir, $filesToAdd);
                    }
                }
            }
        }
    } elseif ($mode === 'selective') {
        $rawItems = $_REQUEST['items'] ?? [];
        if (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (is_array($decoded)) {
                $rawItems = $decoded;
            } else {
                $rawItems = array_filter(array_map('trim', explode(',', $rawItems)));
            }
        }

        if (!is_array($rawItems) || empty($rawItems)) {
            echo json_encode(['success' => false, 'error' => 'No files or categories were selected for export.']);
            return;
        }

        foreach ($rawItems as $item) {
            $safePath = backupIsSafePath($item, $baseDir);
            if (!$safePath) continue;

            if (is_dir($safePath)) {
                backupCollectDirFiles($safePath, $baseDir, $filesToAdd);
            } elseif (is_file($safePath)) {
                $relPath = ltrim(str_replace('\\', '/', substr($safePath, strlen($baseDir))), '/');
                $filesToAdd[$safePath] = $relPath;
            }
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid backup mode specified.']);
        return;
    }

    if (empty($filesToAdd)) {
        echo json_encode(['success' => false, 'error' => 'No valid files found to add to the archive.']);
        return;
    }

    // Create temporary zip
    $tmpDir = sys_get_temp_dir();
    $tempZip = tempnam($tmpDir, 'qwiki_export_');
    if (!$tempZip) {
        echo json_encode(['success' => false, 'error' => 'Failed to initialize temporary archive file.']);
        return;
    }

    $zip = new \ZipArchive();
    if ($zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
        @unlink($tempZip);
        echo json_encode(['success' => false, 'error' => 'Failed to create ZIP archive.']);
        return;
    }

    $addedCount = 0;
    foreach ($filesToAdd as $diskPath => $zipPath) {
        if (is_file($diskPath)) {
            $zip->addFile($diskPath, $zipPath);
            $addedCount++;
        }
    }
    $zip->close();

    if ($addedCount === 0 || !file_exists($tempZip) || filesize($tempZip) === 0) {
        @unlink($tempZip);
        echo json_encode(['success' => false, 'error' => 'Archive creation failed or yielded empty archive.']);
        return;
    }

    // Flush and clean all output buffers before binary streaming
    while (ob_get_level()) {
        ob_end_clean();
    }

    $timestamp = date('Y-m-d-His');
    $filename = ($mode === 'selective' ? 'qwiki-export-' : 'qwiki-backup-') . $timestamp . '.zip';
    $fileSize = filesize($tempZip);

    if (!headers_sent()) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    readfile($tempZip);
    @unlink($tempZip);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid backup action requested.']);
