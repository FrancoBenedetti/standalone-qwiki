<?php
/**
 * Standalone Qwiki - Image Gallery Extension Handler
 * 
 * Provides backend actions for viewing, uploading, checking document usage,
 * and deleting uploaded images safely.
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;

if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    return;
}

$baseDir = Config::getBaseDir();
$uploadsDir = realpath($baseDir . '/uploads');
if (!$uploadsDir) {
    $dir = $baseDir . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $uploadsDir = realpath($dir);
}

$allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico', 'bmp'];

// Helper to format byte sizes
if (!function_exists('galleryFormatBytes')) {
    function galleryFormatBytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// Helper to sanitize and validate file paths within uploads directory
if (!function_exists('galleryResolveUploadFilePath')) {
    function galleryResolveUploadFilePath($reqFile, $uploadsDir, $baseDir, $allowedExtensions) {
        if (empty($reqFile)) return null;

        $reqFile = str_replace('\\', '/', trim($reqFile));
        $reqFile = preg_replace('#/\.\.(/|$)#', '/', $reqFile);
        $reqFile = ltrim($reqFile, '/');

        // Ensure it references uploads/
        if (strpos($reqFile, 'uploads/') !== 0) {
            $candidate1 = $baseDir . '/uploads/images/' . basename($reqFile);
            $candidate2 = $baseDir . '/uploads/' . basename($reqFile);
            if (file_exists($candidate1)) {
                $reqFile = 'uploads/images/' . basename($reqFile);
            } elseif (file_exists($candidate2)) {
                $reqFile = 'uploads/' . basename($reqFile);
            } else {
                $reqFile = 'uploads/' . $reqFile;
            }
        }

        $fullPath = $baseDir . '/' . $reqFile;
        $realPath = realpath($fullPath);

        if (!$realPath || !file_exists($realPath) || !is_file($realPath)) {
            return null;
        }

        // Verify it is inside the uploads directory
        if (strpos($realPath, $uploadsDir) !== 0) {
            return null;
        }

        // Verify allowed extension
        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            return null;
        }

        return [
            'fullPath' => $realPath,
            'relPath' => str_replace('\\', '/', substr($realPath, strlen($baseDir) + 1)),
            'filename' => basename($realPath),
            'extension' => $ext
        ];
    }
}

// Helper to build document lookup map from qwiki.json
if (!function_exists('galleryGetDocumentMetadataMap')) {
    function galleryGetDocumentMetadataMap($baseDir) {
        $map = [];
        $configFile = $baseDir . '/qwiki.json';
        if (!file_exists($configFile)) return $map;

        $config = json_decode(file_get_contents($configFile), true);
        if (!is_array($config) || empty($config['books'])) return $map;

        $traverseItems = function($items, $bookTitle) use (&$traverseItems, &$map) {
            foreach ($items as $item) {
                if (!empty($item['items']) && is_array($item['items'])) {
                    $subTitle = !empty($item['title']) ? $item['title'] : $bookTitle;
                    $traverseItems($item['items'], $subTitle);
                }
                if (!empty($item['file'])) {
                    $normFile = str_replace('\\', '/', trim($item['file']));
                    $map[$normFile] = [
                        'title' => $item['title'] ?? basename($item['file']),
                        'book' => $bookTitle,
                        'type' => $item['type'] ?? 'markdown'
                    ];
                }
            }
        };

        foreach ($config['books'] as $book) {
            $bookTitle = $book['title'] ?? 'General';
            if (!empty($book['items']) && is_array($book['items'])) {
                $traverseItems($book['items'], $bookTitle);
            }
        }

        return $map;
    }
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    // -------------------------------------------------------------
    // 1. List all uploaded images
    // -------------------------------------------------------------
    case 'ext_gallery_list':
        $images = [];
        $scanDirs = [
            $baseDir . '/uploads/images',
            $baseDir . '/uploads'
        ];

        $seenFiles = [];

        // Recursive directory scan
        $scanDirectory = function($dir) use (&$scanDirectory, &$images, &$seenFiles, $baseDir, $allowedExtensions) {
            if (!is_dir($dir)) return;

            $items = @scandir($dir);
            if (!$items) return;

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $full = $dir . '/' . $item;

                if (is_dir($full)) {
                    // Skip themes directory to prevent listing theme CSS/assets
                    if ($item === 'themes') continue;
                    $scanDirectory($full);
                } elseif (is_file($full)) {
                    $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions)) continue;

                    $real = realpath($full);
                    if (!$real || isset($seenFiles[$real])) continue;
                    $seenFiles[$real] = true;

                    $relPath = str_replace('\\', '/', substr($real, strlen($baseDir) + 1));
                    $mtime = filemtime($real);
                    $size = filesize($real);

                    $width = null;
                    $height = null;
                    if ($ext !== 'svg') {
                        $info = @getimagesize($real);
                        if ($info) {
                            $width = $info[0] ?? null;
                            $height = $info[1] ?? null;
                        }
                    }

                    $images[] = [
                        'filename' => $item,
                        'name' => pathinfo($item, PATHINFO_FILENAME),
                        'path' => $relPath,
                        'url' => $relPath,
                        'extension' => $ext,
                        'size' => $size,
                        'sizeFormatted' => galleryFormatBytes($size),
                        'mtime' => $mtime,
                        'dateFormatted' => date('Y-m-d H:i', $mtime),
                        'width' => $width,
                        'height' => $height,
                        'dimensions' => ($width && $height) ? "{$width}x{$height}" : strtoupper($ext)
                    ];
                }
            }
        };

        if ($uploadsDir && is_dir($uploadsDir)) {
            $scanDirectory($uploadsDir);
        }

        // Sort by modified time descending (newest first)
        usort($images, function($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        echo json_encode([
            'success' => true,
            'images' => $images,
            'total' => count($images)
        ]);
        return;

    // -------------------------------------------------------------
    // 2. Check if an image is used in any document
    // -------------------------------------------------------------
    case 'ext_gallery_check_usage':
        $reqFile = $_REQUEST['file'] ?? '';
        $resolved = galleryResolveUploadFilePath($reqFile, $uploadsDir, $baseDir, $allowedExtensions);

        if (!$resolved) {
            echo json_encode(['success' => false, 'error' => 'Image file not found on server']);
            return;
        }

        $targetFilename = $resolved['filename'];
        $targetRelPath = $resolved['relPath'];
        $docMetaMap = galleryGetDocumentMetadataMap($baseDir);

        $usedDocuments = [];
        $contentDir = $baseDir . '/content';

        // 1. Scan content/ files
        if (is_dir($contentDir)) {
            $docExtensions = ['md', 'markdown', 'html', 'htm', 'txt', 'json'];

            $scanDocs = function($dir) use (&$scanDocs, &$usedDocuments, $contentDir, $baseDir, $docExtensions, $targetFilename, $targetRelPath, $docMetaMap) {
                $entries = @scandir($dir);
                if (!$entries) return;

                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $full = $dir . '/' . $entry;

                    if (is_dir($full)) {
                        $scanDocs($full);
                    } elseif (is_file($full)) {
                        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                        if (!in_array($ext, $docExtensions)) continue;

                        $content = @file_get_contents($full);
                        if ($content === false) continue;

                        // Check if filename or relative path is present
                        if (stripos($content, $targetFilename) !== false || stripos($content, $targetRelPath) !== false) {
                            $relDoc = str_replace('\\', '/', substr($full, strlen($baseDir) + 1));
                            $lines = explode("\n", $content);
                            $snippets = [];

                            foreach ($lines as $idx => $line) {
                                if (stripos($line, $targetFilename) !== false || stripos($line, $targetRelPath) !== false) {
                                    $snippets[] = [
                                        'line' => $idx + 1,
                                        'text' => trim(mb_substr(trim($line), 0, 160))
                                    ];
                                    if (count($snippets) >= 3) break;
                                }
                            }

                            $meta = $docMetaMap[$relDoc] ?? null;
                            $docTitle = $meta ? $meta['title'] : ucwords(str_replace(['-', '_'], ' ', pathinfo($entry, PATHINFO_FILENAME)));
                            $bookTitle = $meta ? $meta['book'] : 'Document';

                            $usedDocuments[] = [
                                'title' => $docTitle,
                                'book' => $bookTitle,
                                'file' => $relDoc,
                                'type' => 'document',
                                'occurrences' => count($snippets),
                                'snippets' => $snippets
                            ];
                        }
                    }
                }
            };

            $scanDocs($contentDir);
        }

        // 2. Check qwiki.json metadata
        $configFile = $baseDir . '/qwiki.json';
        if (file_exists($configFile)) {
            $rawConfig = @file_get_contents($configFile);
            if ($rawConfig && (stripos($rawConfig, $targetFilename) !== false || stripos($rawConfig, $targetRelPath) !== false)) {
                $config = json_decode($rawConfig, true);
                if (is_array($config)) {
                    // Check site logo
                    if (!empty($config['logoUrl']) && (stripos($config['logoUrl'], $targetFilename) !== false || stripos($config['logoUrl'], $targetRelPath) !== false)) {
                        $usedDocuments[] = [
                            'title' => 'Site Logo (Settings)',
                            'book' => 'Configuration',
                            'file' => 'qwiki.json',
                            'type' => 'setting',
                            'occurrences' => 1,
                            'snippets' => [['line' => 1, 'text' => 'Configured as site logo']]
                        ];
                    }

                    // Check share image
                    if (!empty($config['shareImageUrl']) && (stripos($config['shareImageUrl'], $targetFilename) !== false || stripos($config['shareImageUrl'], $targetRelPath) !== false)) {
                        $usedDocuments[] = [
                            'title' => 'Social Share Image (Settings)',
                            'book' => 'Configuration',
                            'file' => 'qwiki.json',
                            'type' => 'setting',
                            'occurrences' => 1,
                            'snippets' => [['line' => 1, 'text' => 'Configured as default social share preview']]
                        ];
                    }

                    // Check books/chapters metadata (cover images)
                    if (!empty($config['books']) && is_array($config['books'])) {
                        $checkItems = function($items, $bookTitle) use (&$checkItems, &$usedDocuments, $targetFilename, $targetRelPath) {
                            foreach ($items as $item) {
                                if (!empty($item['items']) && is_array($item['items'])) {
                                    $checkItems($item['items'], $item['title'] ?? $bookTitle);
                                }
                                if (!empty($item['image']) && (stripos($item['image'], $targetFilename) !== false || stripos($item['image'], $targetRelPath) !== false)) {
                                    $usedDocuments[] = [
                                        'title' => ($item['title'] ?? 'Document') . ' (Cover Image)',
                                        'book' => $bookTitle,
                                        'file' => $item['file'] ?? 'qwiki.json',
                                        'type' => 'cover',
                                        'occurrences' => 1,
                                        'snippets' => [['line' => 1, 'text' => 'Document cover image in navigation']]
                                    ];
                                }
                            }
                        };

                        foreach ($config['books'] as $b) {
                            if (!empty($b['items'])) {
                                $checkItems($b['items'], $b['title'] ?? 'Category');
                            }
                        }
                    }
                }
            }
        }

        $isUsed = !empty($usedDocuments);

        echo json_encode([
            'success' => true,
            'file' => $resolved['relPath'],
            'filename' => $targetFilename,
            'used' => $isUsed,
            'count' => count($usedDocuments),
            'documents' => $usedDocuments
        ]);
        return;

    // -------------------------------------------------------------
    // 3. Delete an image
    // -------------------------------------------------------------
    case 'ext_gallery_delete':
        $reqFile = $_REQUEST['file'] ?? '';
        $resolved = galleryResolveUploadFilePath($reqFile, $uploadsDir, $baseDir, $allowedExtensions);

        if (!$resolved) {
            echo json_encode(['success' => false, 'error' => 'Image file not found or invalid path']);
            return;
        }

        if (!is_writable($resolved['fullPath'])) {
            echo json_encode(['success' => false, 'error' => 'Permission denied: Cannot delete file']);
            return;
        }

        if (@unlink($resolved['fullPath'])) {
            echo json_encode([
                'success' => true,
                'message' => 'Image deleted successfully',
                'file' => $resolved['relPath'],
                'filename' => $resolved['filename']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete file from disk']);
        }
        return;

    // -------------------------------------------------------------
    // 4. Upload an image directly via Gallery
    // -------------------------------------------------------------
    case 'ext_gallery_upload':
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'No image file uploaded or upload error']);
            return;
        }

        $originalName = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            echo json_encode(['success' => false, 'error' => 'Invalid image format. Allowed: ' . implode(', ', $allowedExtensions)]);
            return;
        }

        $targetDir = $baseDir . '/uploads/images';
        if (!is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                echo json_encode(['success' => false, 'error' => 'Failed to create uploads/images directory']);
                return;
            }
        }

        $safeName = preg_replace('/[^a-z0-9\._-]/', '-', strtolower(pathinfo($originalName, PATHINFO_FILENAME)));
        if (empty($safeName)) $safeName = 'image';
        $newFileName = time() . '-' . $safeName . '.' . $ext;
        $destPath = $targetDir . '/' . $newFileName;
        $relPath = 'uploads/images/' . $newFileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
            $width = null;
            $height = null;
            if ($ext !== 'svg') {
                $info = @getimagesize($destPath);
                if ($info) {
                    $width = $info[0] ?? null;
                    $height = $info[1] ?? null;
                }
            }
            $size = filesize($destPath);

            echo json_encode([
                'success' => true,
                'image' => [
                    'filename' => $newFileName,
                    'name' => $safeName,
                    'path' => $relPath,
                    'url' => $relPath,
                    'extension' => $ext,
                    'size' => $size,
                    'sizeFormatted' => galleryFormatBytes($size),
                    'mtime' => time(),
                    'dateFormatted' => date('Y-m-d H:i'),
                    'width' => $width,
                    'height' => $height,
                    'dimensions' => ($width && $height) ? "{$width}x{$height}" : strtoupper($ext)
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to save uploaded image']);
        }
        return;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid gallery action']);
        return;
}
