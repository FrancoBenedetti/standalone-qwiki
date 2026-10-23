<?php
require_once __DIR__ . '/lib/Parsedown.php';
require_once __DIR__ . '/lib/simple_html_dom.php';
require_once __DIR__ . '/lib/Core/Config.php';
require_once __DIR__ . '/lib/Core/Auth.php';
require_once __DIR__ . '/lib/Core/Navigation.php';
require_once __DIR__ . '/lib/Core/ExtensionManager.php';
require_once __DIR__ . '/lib/Core/DemoManager.php';
require_once __DIR__ . '/lib/Core/SubwikiManager.php';
require_once __DIR__ . '/lib/Core/QwikiParsedown.php';
require_once __DIR__ . '/lib/Core/MetadataManager.php';
require_once __DIR__ . '/lib/Core/DocumentService.php';

use Qwiki\Core\Config;
use Qwiki\Core\Auth;
use Qwiki\Core\Navigation;
use Qwiki\Core\ExtensionManager;
use Qwiki\Core\DemoManager;
use Qwiki\Core\SubwikiManager;
use Qwiki\Core\QwikiParsedown;
use Qwiki\Core\MetadataManager;
use Qwiki\Core\DocumentService;

Auth::startSession();
if (!defined('QWIKI_VERSION')) {
    define('QWIKI_VERSION', Config::VERSION);
}

// Check for API delegation via front-controller (e.g. multi-tenant hosted routing)
$apiRequestedPath = $_GET['path'] ?? '';
if (is_string($apiRequestedPath) && (strpos($apiRequestedPath, 'api/') === 0 || $apiRequestedPath === 'api')) {
    $endpoint = basename($apiRequestedPath);
    if ($endpoint === 'api' || empty($endpoint)) {
        $endpoint = 'admin.php';
    }
    $targetApiFile = __DIR__ . '/api/' . $endpoint;
    if (file_exists($targetApiFile)) {
        require $targetApiFile;
        exit;
    }
}

$config = Config::load();
$baseDir = Config::getBaseDir();
$extManager = ExtensionManager::getInstance();

// User session evaluation
$currentUser = Auth::getCurrentUser();
$isAdmin = Auth::isAdmin();
$isViewer = Auth::isViewer();
$canViewContent = Auth::canView($config);
$isSubwiki = Config::isSubwiki();
$parentTitle = $isSubwiki ? SubwikiManager::getParentTitle($config) : '';

// Routing parameters
$requestedBookId = '';
$requestedFolderId = '';
$requestedChapterSlug = '';

// Base URL calculation for clean URLs
$baseUrl = Config::getBaseUrl();
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] === '/' || $_SERVER['SCRIPT_NAME'] === '\\' ? '' : $_SERVER['SCRIPT_NAME']), '/\\');
$assetsUrl = defined('QWIKI_ASSETS_URL') ? rtrim(QWIKI_ASSETS_URL, '/') : 'assets';

// Check for action triggers: reset_password or verify_email
$routeAction = $_GET['action'] ?? '';
$resetPasswordToken = null;
$resetPasswordValidation = null;
$emailVerificationResult = null;

if ($routeAction === 'reset_password') {
    $resetPasswordToken = trim($_GET['token'] ?? '');
    $resetPasswordValidation = Auth::validateResetToken($resetPasswordToken);
} elseif ($routeAction === 'verify_email') {
    $verifyToken = trim($_GET['token'] ?? '');
    $emailVerificationResult = Auth::verifyEmail($verifyToken);
}

// Share link routing
$shareKey = trim($_GET['share'] ?? '');
$isShareMode = false;
$shareError = null;

if (!empty($shareKey)) {
    $matchedParentBook = null;
    $matchedChapter = Navigation::findChapterByShareKey($config['books'] ?? [], $shareKey, $matchedParentBook);
    if (!$matchedChapter) {
        $shareError = 'not_found';
    } else {
        $isPublic = !isset($matchedChapter['publicShareable']) || !empty($matchedChapter['publicShareable']);
        if (!$isPublic && !$isAdmin && !$isViewer) {
            $shareError = 'restricted';
        } else {
            $isShareMode = true;
            $activeBook = $matchedParentBook;
            $activeChapter = $matchedChapter;
            $canViewContent = true;
            $breadcrumbsTrail = [];
            $activePathIds = !empty($activeBook['id']) ? [$activeBook['id']] : [];
            $allowedBooks = !empty($activeBook) ? [$activeBook] : [];
        }
    }
}

// Support JSON API content negotiation for share links (federated transclusion)
$isJsonRequested = (($_GET['format'] ?? '') === 'json') ||
    (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!empty($shareKey) && $isJsonRequested) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        exit;
    }

    if ($shareError === 'not_found' || empty($matchedChapter)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Share link not found'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($shareError === 'restricted') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Share link is restricted'], JSON_UNESCAPED_SLASHES);
        exit;
    }

    $chapterType = $matchedChapter['type'] ?? 'markdown';
    $rawContent = '';
    $lastModified = time();
    $filePath = $baseDir . '/' . ($matchedChapter['file'] ?? '');

    if (!empty($matchedChapter['file']) && file_exists($filePath)) {
        $rawContent = file_get_contents($filePath);
        $lastModified = filemtime($filePath);
    } elseif ($chapterType === 'remote' && !empty($matchedChapter['url'])) {
        require_once __DIR__ . '/lib/Core/RemoteContentManager.php';
        $remoteRes = \Qwiki\Core\RemoteContentManager::fetchRemoteDocument($matchedChapter['url']);
        $rawContent = $remoteRes['content'] ?? '';
    } elseif (!empty($matchedChapter['content'])) {
        $rawContent = $matchedChapter['content'];
    }

    $catId = $matchedParentBook['id'] ?? '';
    $fullUrl = $baseUrl . urlencode($catId) . '/' . urlencode($matchedChapter['slug'] ?? '');
    $shareUrl = rtrim($baseUrl, '/') . '/?share=' . urlencode($shareKey);

    echo json_encode([
        'success' => true,
        'shareKey' => $shareKey,
        'bookId' => $catId,
        'category' => $catId,
        'title' => $matchedChapter['title'] ?? '',
        'slug' => $matchedChapter['slug'] ?? '',
        'type' => $chapterType,
        'description' => $matchedChapter['description'] ?? '',
        'url' => $fullUrl,
        'shareUrl' => $shareUrl,
        'content' => $rawContent,
        'origin' => $baseUrl,
        'lastModified' => $lastModified,
        'readOnly' => true
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$activeChapter = $activeChapter ?? null;
$breadcrumbsTrail = $breadcrumbsTrail ?? [];
$activePathIds = $activePathIds ?? (!empty($activeBook['id']) ? [$activeBook['id']] : []);

if (!$isShareMode && !$shareError) {
    // Determine requested path across all server environments
    $rawPath = '';
    if (isset($_GET['path']) && !empty(trim($_GET['path'], '/'))) {
        $rawPath = trim($_GET['path'], '/');
    } elseif (!empty($_SERVER['PATH_INFO'])) {
        $rawPath = trim($_SERVER['PATH_INFO'], '/');
    } elseif (!empty($_SERVER['REQUEST_URI'])) {
        $parsedPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if ($parsedPath) {
            if ($scriptDir !== '' && strpos($parsedPath, $scriptDir) === 0) {
                $parsedPath = substr($parsedPath, strlen($scriptDir));
            }
            $parsedPath = preg_replace('#^/index\.php(/|$)#', '$1', $parsedPath);
            $rawPath = trim($parsedPath, '/');
        }
    }

    if (!empty($rawPath)) {
        $segments = explode('/', $rawPath);
        $segCount = count($segments);
        $requestedBookId = urldecode($segments[0] ?? '');
        if ($segCount === 1) {
            $requestedFolderId = '';
            $requestedChapterSlug = '';
        } elseif ($segCount === 2) {
            $requestedFolderId = '';
            $requestedChapterSlug = urldecode($segments[1] ?? '');
        } else {
            $requestedFolderId = urldecode($segments[1] ?? '');
            $requestedChapterSlug = urldecode($segments[$segCount - 1] ?? '');
        }
    } else {
        $requestedBookId = $_GET['book'] ?? '';
        $requestedFolderId = $_GET['folder'] ?? $_GET['dir'] ?? '';
        $requestedChapterSlug = $_GET['chapter'] ?? $_GET['doc'] ?? '';
    }

    if (empty($requestedBookId)) {
        $requestedBookId = $config['defaultBook'] ?? ($config['books'][0]['id'] ?? '');
    }

    // Filter books based on visibility
    $allowedBooks = Navigation::filterBooks($config['books'] ?? [], $isAdmin, $isViewer);

    $hasExplicitRoute = !empty($rawPath) || !empty($_GET['book']) || !empty($_GET['chapter']) || !empty($_GET['doc']);
    $isAccessRestricted = false;

    // Active tree resolution state
    $activeBook = null;
    foreach ($allowedBooks as $book) {
        if (($book['id'] ?? '') === $requestedBookId) {
            $activeBook = $book;
            break;
        }
    }
    // Subfolder route resolution: if requestedBookId is actually a subfolder ID
    if (!$activeBook && !empty($requestedBookId) && !empty($allowedBooks)) {
        foreach ($allowedBooks as $b) {
            $dummyTrail = [];
            $dummyIds = [];
            $testChapter = Navigation::findChapterAndPath($b, $requestedBookId, $requestedChapterSlug, $dummyTrail, $dummyIds, $isAdmin, $isViewer);
            if ($testChapter) {
                $activeBook = $b;
                $activeChapter = $testChapter;
                $breadcrumbsTrail = $dummyTrail;
                $activePathIds = array_unique(array_merge([$b['id'] ?? ''], $dummyIds));
                break;
            }
        }
    }

    // Check if the requested route points to a restricted book in $config['books']
    if ($hasExplicitRoute && !$isAdmin) {
        if (!$activeBook && !empty($requestedBookId)) {
            foreach ($config['books'] ?? [] as $rawBook) {
                if (($rawBook['id'] ?? '') === $requestedBookId) {
                    $vis = $rawBook['visibility'] ?? 'public';
                    if ($vis === 'admin_only' || ($vis === 'logged_in' && !$isViewer)) {
                        $isAccessRestricted = true;
                        break;
                    }
                }
            }
        }
    }

    if (!$activeBook && !empty($allowedBooks)) {
        foreach ($allowedBooks as $b) {
            if (($b['type'] ?? 'folder') === 'folder') {
                $activeBook = $b;
                break;
            }
        }
        if (!$activeBook) {
            $activeBook = $allowedBooks[0];
        }
    }

    $activeChapter = $activeChapter ?? null;
    $breadcrumbsTrail = $breadcrumbsTrail ?? [];
    $activePathIds = $activePathIds ?? ($activeBook ? [$activeBook['id'] ?? ''] : []);

    if ($activeBook && !$activeChapter && !$isAccessRestricted) {
        $dummyTrail = [];
        $dummyIds = [];
        $activeChapter = Navigation::findChapterAndPath($activeBook, $requestedFolderId, $requestedChapterSlug, $dummyTrail, $dummyIds, $isAdmin, $isViewer);

        // Fallback: 2-segment URL might be a subfolder instead of a chapter (e.g. /book/subfolder)
        if (!$activeChapter && !empty($requestedChapterSlug) && empty($requestedFolderId)) {
            $dummyTrail = [];
            $dummyIds = [];
            $fallbackFolderId = $requestedChapterSlug;
            $activeChapter = Navigation::findChapterAndPath($activeBook, $fallbackFolderId, '', $dummyTrail, $dummyIds, $isAdmin, $isViewer);
        }

        // Global fallback: find chapter across all books if requestedChapterSlug not found in activeBook
        if (!$activeChapter && !empty($requestedChapterSlug)) {
            foreach ($allowedBooks as $b) {
                if (($b['id'] ?? '') === ($activeBook['id'] ?? '')) continue;
                $dummyTrail = [];
                $dummyIds = [];
                $foundCandidate = Navigation::findChapterAndPath($b, '', $requestedChapterSlug, $dummyTrail, $dummyIds, $isAdmin, $isViewer);
                if ($foundCandidate) {
                    $activeBook = $b;
                    $activeChapter = $foundCandidate;
                    break;
                }
            }
        }

        // If not found in allowedBooks, check if it was restricted in any book
        if (!$activeChapter && $hasExplicitRoute && !$isAdmin) {
            foreach ($config['books'] ?? [] as $rawBook) {
                $checkTrail = [];
                $checkIds = [];
                $foundCandidate = Navigation::findChapterAndPath($rawBook, $requestedFolderId, $requestedChapterSlug, $checkTrail, $checkIds, true, true);
                if (!$foundCandidate && !empty($requestedChapterSlug)) {
                    $foundCandidate = Navigation::findChapterAndPath($rawBook, '', $requestedChapterSlug, $checkTrail, $checkIds, true, true);
                }
                if ($foundCandidate) {
                    $bVis = $rawBook['visibility'] ?? 'public';
                    $cVis = $foundCandidate['visibility'] ?? 'public';
                    if ($bVis === 'admin_only' || $cVis === 'admin_only' || (($bVis === 'logged_in' || $cVis === 'logged_in') && !$isViewer)) {
                        $isAccessRestricted = true;
                        break;
                    }
                }
            }
        }

        if ($isAccessRestricted) {
            $activeChapter = null;
            $breadcrumbsTrail = [['title' => 'Access Restricted', 'id' => '']];
        } elseif ($activeChapter) {
            $breadcrumbsTrail = $dummyTrail;
            $activePathIds = array_unique(array_merge([$activeBook['id'] ?? ''], $dummyIds));
        } else {
            // Fallback to first document in the active book
            if (!empty($activeBook['items'])) {
                foreach ($activeBook['items'] as $item) {
                    if (!isset($item['type']) || ($item['type'] !== 'folder' && $item['type'] !== 'link')) {
                        $itemVis = $item['visibility'] ?? 'public';
                        if (!$isAdmin) {
                            if ($itemVis === 'admin_only') continue;
                            if ($itemVis === 'logged_in' && !$isViewer) continue;
                        }
                        $activeChapter = $item;
                        $breadcrumbsTrail = [['title' => $activeBook['title'] ?? '', 'id' => $activeBook['id'] ?? '']];
                        break;
                    }
                }
            }
        }
    } elseif ($isAccessRestricted) {
        $activeChapter = null;
        $breadcrumbsTrail = [['title' => 'Access Restricted', 'id' => '']];
    }
}

// Determine immediate containing category ID for active document
$currentCategoryId = '';
if (!empty($activeChapter['slug'])) {
    $currentCategoryId = Navigation::findChapterParentId($config['books'] ?? [], $activeChapter['slug']) ?? '';
}
if (empty($currentCategoryId) && !empty($breadcrumbsTrail)) {
    $lastCrumb = end($breadcrumbsTrail);
    $currentCategoryId = $lastCrumb['id'] ?? '';
}
if (empty($currentCategoryId) && $activeBook) {
    $currentCategoryId = $activeBook['id'] ?? '';
}

$categoryHierarchy = Navigation::getCategoriesHierarchy($config['books'] ?? [], '', 0, null, $isAdmin, $isViewer);

// Calculate Previous and Next Document Navigation
$flatNavList = [];
$prevDoc = null;
$nextDoc = null;

if (!$isShareMode && !$shareError) {
    foreach ($allowedBooks as $book) {
        Navigation::flattenNavTree($book, $book['id'] ?? '', $flatNavList, $isAdmin, $isViewer);
    }

    if ($activeChapter) {
        $currentIndex = -1;
        foreach ($flatNavList as $index => $item) {
            if ($item['bookId'] === ($activeBook['id'] ?? '') && $item['slug'] === ($activeChapter['slug'] ?? '')) {
                $currentIndex = $index;
                break;
            }
        }
        if ($currentIndex !== -1) {
            if ($currentIndex > 0) {
                $prevDoc = $flatNavList[$currentIndex - 1];
            }
            if ($currentIndex < count($flatNavList) - 1) {
                $nextDoc = $flatNavList[$currentIndex + 1];
            }
        }
    }
}

// Content rendering via ExtensionManager
$renderedContent = '';
$rawMarkdownContent = '';
if ($activeChapter && !$shareError) {
    $renderedContent = $extManager->renderPage($activeChapter, $activeBook, $config);
    if (($activeChapter['type'] ?? 'markdown') === 'markdown') {
        $filePath = $baseDir . '/' . ($activeChapter['file'] ?? '');
        if (file_exists($filePath)) {
            $rawMarkdownContent = file_get_contents($filePath);
        }
    }
}

$isPageReadOnly = false;
if ($activeChapter) {
    $isPageReadOnly = !empty($activeChapter['readOnly'])
        || (isset($activeChapter['editable']) && $activeChapter['editable'] === false)
        || (($activeChapter['type'] ?? '') === 'remote')
        || Config::isChapterProtected($activeChapter['slug'] ?? '', $config['books'] ?? [])
        || Config::isChapterProtected($activeChapter['file'] ?? '', $config['books'] ?? []);
}

// Theme Resolution
$siteTheme = $config['theme'] ?? 'theme-default.css';
$categoryTheme = $activeBook['theme'] ?? null;
$chapterTheme = $activeChapter['theme'] ?? null;
$resolvedTheme = $chapterTheme ?: $categoryTheme ?: $siteTheme;
$showDocTypesOnlyToAdmin = isset($config['showDocTypesOnlyToAdmin']) ? !empty($config['showDocTypesOnlyToAdmin']) : true;
$showPoweredBy = isset($config['showPoweredBy']) ? !empty($config['showPoweredBy']) : true;
$showSubwikisInSidebar = !empty($config['showSubwikisInSidebar']);
$sidebarSubwikis = $showSubwikisInSidebar ? SubwikiManager::getSidebarSubwikis($config) : [];

// Collect Frontend Assets from Extensions
$extensionAssets = $extManager->getFrontendAssets();
$userTheme = isset($_COOKIE['qwiki_theme']) && in_array($_COOKIE['qwiki_theme'], ['light', 'dark']) ? $_COOKIE['qwiki_theme'] : 'dark';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $userTheme ?>" class="<?= $isShareMode ? 'mode-fullscreen' : '' ?>">
<head>
    <meta charset="UTF-8">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('qwiki_theme');
                if (savedTheme && (savedTheme === 'light' || savedTheme === 'dark')) {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
                var savedWidth = localStorage.getItem('qwiki_sidebar_width');
                if (savedWidth) {
                    document.documentElement.style.setProperty('--sidebar-width', savedWidth + 'px');
                }
            } catch(e) {}
        })();
    </script>
    <base href="<?= htmlspecialchars($baseUrl) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php
        if (!$canViewContent) {
            $pageDocTitle = ($config['title'] ?? 'Documentation') . ' — Sign In';
        } elseif (!empty($isAccessRestricted)) {
            $pageDocTitle = 'Access Restricted - ' . ($config['title'] ?? 'Standalone Qwiki');
        } else {
            $pageDocTitle = ($activeChapter['title'] ?? 'Documentation') . ' - ' . ($config['title'] ?? 'Standalone Qwiki');
        }
    ?>
    <title><?= htmlspecialchars($pageDocTitle) ?></title>
    
    <!-- Open Graph & Social Share Tags -->
    <?php
        if ($canViewContent && empty($isAccessRestricted)) {
            $articleMeta = MetadataManager::resolveArticleMetadata(
                $activeChapter,
                $activeBook,
                $config,
                $baseUrl,
                $baseDir,
                $isShareMode,
                $shareKey,
                $shareError,
                $rawMarkdownContent
            );
            $canonicalUrl = $articleMeta['canonicalUrl'];
            echo MetadataManager::renderTags($articleMeta);
        } else {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    ?>

    <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/qwiki.css?v=<?= @filemtime(__DIR__ . '/assets/css/qwiki.css') ?: time() ?>">
    <?php if ($resolvedTheme && $resolvedTheme !== 'theme-default.css'): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($assetsUrl) ?>/css/<?= htmlspecialchars($resolvedTheme) ?>" id="dynamic-theme-css">
    <?php endif; ?>

    <!-- Extension Styles -->
    <?php foreach ($extensionAssets['styles'] as $styleFile): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($styleFile) ?>">
    <?php endforeach; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Toast UI Editor -->
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/theme/toastui-editor-dark.min.css" />
    <!-- KaTeX Mathematics -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.css">
</head>
<body class="<?= $isShareMode ? 'mode-fullscreen' : '' ?>">

    <?php if (!$canViewContent): ?>
        <div class="auth-gateway-wrapper">
            <div class="auth-gateway-card">
                <div class="auth-gateway-topbar">
                    <div class="auth-gateway-brand">
                        <span>⚡</span>
                        <span><?= htmlspecialchars($isSubwiki && !empty($parentTitle) ? $parentTitle : ($config['logoText'] ?? $config['title'] ?? 'Standalone Qwiki')) ?></span>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm theme-toggle-btn" id="theme-toggle" aria-label="Toggle theme" title="Toggle theme">
                        <span class="theme-icon-dark">🌙</span>
                        <span class="theme-icon-light" style="display: none;">☀️</span>
                        <span class="theme-label" style="font-size: 0.8rem; margin-left: 0.25rem;">Theme</span>
                    </button>
                </div>
                <div class="auth-gateway-header">
                    <div class="auth-gateway-icon">🔒</div>
                    <h2 class="auth-gateway-title"><?= htmlspecialchars($config['title'] ?? 'Private Documentation Portal') ?></h2>
                    <p class="auth-gateway-subtitle">Authentication is required to access this documentation portal.</p>
                </div>

                <form id="login-form">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" for="login-username" style="display: block; font-weight: 500; font-size: 0.85rem; margin-bottom: 0.35rem;">Username or Email</label>
                        <input type="text" id="login-username" name="username" class="form-control" placeholder="Enter username or email" autocomplete="username" required style="width: 100%; box-sizing: border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" for="login-password" style="display: block; font-weight: 500; font-size: 0.85rem; margin-bottom: 0.35rem;">Password</label>
                        <input type="password" id="login-password" name="password" class="form-control" placeholder="Enter password" autocomplete="current-password" required style="width: 100%; box-sizing: border-box;">
                    </div>
                    <div style="text-align: right; margin-bottom: 1.25rem;">
                        <button type="button" class="btn-link" id="btn-open-forgot-pwd" style="background: none; border: none; font-size: 0.8rem; color: var(--primary-color, #6366f1); cursor: pointer; text-decoration: underline; padding: 0;">Forgot Password?</button>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-login-submit" style="width: 100%; padding: 0.65rem 1rem; font-weight: 600;">Log In to Portal</button>
                </form>

                <form id="login-2fa-form" style="display: none;">
                    <div style="text-align: center; margin-bottom: 1.25rem;">
                        <div style="font-size: 2.25rem; margin-bottom: 0.25rem;">🔒</div>
                        <h4 style="margin: 0 0 0.25rem 0;" id="login-2fa-heading">Two-Factor Authentication</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;" id="login-2fa-desc">Enter the 6-digit verification code from your authenticator app.</p>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label" for="login-2fa-code" id="login-2fa-label" style="text-align: center; display: block; font-weight: 500; font-size: 0.85rem; margin-bottom: 0.35rem;">Verification Code</label>
                        <input type="text" id="login-2fa-code" name="code" class="form-control" placeholder="000000" maxlength="10" autocomplete="one-time-code" style="text-align: center; font-size: 1.25rem; letter-spacing: 0.2rem; font-family: monospace; width: 100%; box-sizing: border-box;" required>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btn-2fa-submit" style="width: 100%; margin-bottom: 0.75rem; padding: 0.65rem 1rem; font-weight: 600;">Verify &amp; Continue</button>
                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; margin-top: 0.5rem;">
                        <button type="button" class="btn-link" id="btn-toggle-recovery-code" style="background: none; border: none; color: var(--primary-color, #6366f1); cursor: pointer; text-decoration: underline; padding: 0;">Use recovery code</button>
                        <button type="button" class="btn-link" id="btn-back-to-login" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 0;">← Back to login</button>
                    </div>
                </form>

                <?php if ($isSubwiki): ?>
                    <div class="auth-gateway-footer">
                        <a href="<?= htmlspecialchars($config['parentUrl'] ?? '../') ?>">← Back to <?= htmlspecialchars($parentTitle) ?></a>
                    </div>
                <?php elseif ($showPoweredBy): ?>
                    <div class="auth-gateway-footer">
                        Powered by <a href="https://qwiki.wiki" target="_blank" rel="noopener noreferrer"><strong>Qwiki</strong></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php if (!$isShareMode): ?>
        <!-- App Header -->
    <header class="app-header">
        <div class="brand-container">
            <button class="mobile-toggle" id="mobile-toggle" aria-label="Toggle navigation">☰</button>
            <div class="brand-header-group">
                <?php 
                $logoHref = $isSubwiki ? ($config['parentUrl'] ?? '../') : $baseUrl;
                $logoTitle = $isSubwiki ? ('Back to ' . ($parentTitle ?: 'Parent Wiki')) : ($config['title'] ?? 'Home');
                ?>
                <a href="<?= htmlspecialchars($logoHref) ?>" class="brand-logo" title="<?= htmlspecialchars($logoTitle) ?>">
                    <?php if (!empty($config['logoUrl'])): ?>
                        <img src="<?= htmlspecialchars($config['logoUrl']) ?>" alt="Logo" style="max-height: 64px; border-radius: 4px;">
                    <?php else: ?>
                        ⚡ <?= htmlspecialchars($isSubwiki && !empty($parentTitle) ? $parentTitle : ($config['logoText'] ?? 'QWIKI')) ?>
                    <?php endif; ?>
                </a>
                <?php if ($isSubwiki): ?>
                    <span class="brand-separator" aria-hidden="true">/</span>
                    <a href="<?= htmlspecialchars($baseUrl) ?>" class="brand-subwiki-badge" title="Current Subwiki: <?= htmlspecialchars($config['title'] ?? 'Subwiki') ?>">
                        <span class="subwiki-badge-icon">🌐</span>
                        <span class="subwiki-badge-title"><?= htmlspecialchars($config['title'] ?? 'Subwiki') ?></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline btn-sm" id="theme-toggle" title="Toggle Dark/Light Mode">🌙 Theme</button>
            <?php if ($isViewer): ?>
                <div class="dropdown">
                    <button class="btn btn-outline btn-sm dropdown-toggle" id="user-dropdown-toggle">
                        <span class="doc-badge badge-md" style="margin: 0; padding: 0.1rem 0.3rem;"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></span> ▾
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-user-header">
                            <div class="dropdown-user-info">
                                <span class="dropdown-user-name"><?= htmlspecialchars($currentUser['username'] ?? 'User') ?></span>
                                <span class="dropdown-user-role"><?= $isAdmin ? 'Administrator' : 'Viewer' ?></span>
                            </div>
                            <span class="doc-badge badge-md" style="margin: 0; padding: 0.1rem 0.4rem;"><?= $isAdmin ? 'admin' : 'viewer' ?></span>
                        </div>
                        <div class="dropdown-header">Tools & Utilities</div>
                        <?php $extManager->renderHeaderUtilityButtons($isAdmin); ?>
                        <div class="dropdown-divider"></div>
                        <?php if ($isAdmin): ?>
                            <div class="dropdown-header">Administration</div>
                            <button class="dropdown-item" id="btn-settings" data-theme="<?= htmlspecialchars($config['theme'] ?? 'theme-default.css') ?>">
                                <span class="dropdown-item-icon">⚙️</span>
                                <span class="dropdown-item-text">Settings</span>
                            </button>
                            <button class="dropdown-item" id="btn-users">
                                <span class="dropdown-item-icon">👥</span>
                                <span class="dropdown-item-text">Users</span>
                            </button>
                            <button class="dropdown-item" id="btn-llm-keys">
                                <span class="dropdown-item-icon">🤖</span>
                                <span class="dropdown-item-text">LLM & API Access</span>
                            </button>
                            <?php if (!$isSubwiki): ?>
                                <button class="dropdown-item" id="btn-subwikis">
                                    <span class="dropdown-item-icon">🌐</span>
                                    <span class="dropdown-item-text">Subwikis</span>
                                </button>
                                <button class="dropdown-item" id="btn-update-available" style="display: none; background-color: #f59e0b; color: #fff;">
                                    <span class="dropdown-item-icon">🎉</span>
                                    <span class="dropdown-item-text">Update Available!</span>
                                </button>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                        <?php endif; ?>
                        <button class="dropdown-item" id="btn-user-profile">
                            <span class="dropdown-item-icon">👤</span>
                            <span class="dropdown-item-text">Account &amp; Security</span>
                        </button>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item text-danger" id="btn-logout">
                            <span class="dropdown-item-icon">🚪</span>
                            <span class="dropdown-item-text">Logout</span>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <button class="btn btn-outline btn-sm" id="btn-login">Login</button>
            <?php endif; ?>
        </div>
    </header>
    <?php else: ?>
    <!-- Floating Minimal Share Bar -->
    <div class="share-floating-bar">
        <div class="share-bar-brand">
            <?php if (!empty($config['logoUrl'])): ?>
                <img src="<?= htmlspecialchars($config['logoUrl']) ?>" alt="Logo" style="max-height: 24px; border-radius: 3px;">
            <?php else: ?>
                ⚡ <?= htmlspecialchars($config['logoText'] ?? 'QWIKI') ?>
            <?php endif; ?>
            <?php if ($isSubwiki): ?>
                <span class="brand-separator" style="margin: 0 0.35rem; opacity: 0.6; font-size: 0.85rem;" aria-hidden="true">/</span>
                <span class="brand-subwiki-badge-share" style="font-weight: 600; font-size: 0.82rem; color: var(--text-secondary); display: inline-flex; align-items: center; gap: 0.25rem;">
                    <span>🌐</span>
                    <span><?= htmlspecialchars($config['title'] ?? 'Subwiki') ?></span>
                </span>
            <?php endif; ?>
        </div>
        <div class="share-bar-title"><?= htmlspecialchars($activeChapter['title'] ?? ($shareError ? 'Document Share' : 'Document')) ?></div>
        <div class="share-bar-actions">
            <?php if (!$shareError && $activeChapter): ?>
            <div class="dropdown" id="share-social-dropdown">
                <button class="btn btn-outline btn-sm dropdown-toggle" id="btn-share-social" title="Share Document">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                    <span>Share</span>
                </button>
                <div class="dropdown-menu" id="share-social-menu" style="min-width: 175px;">
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($activeChapter['title'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="dropdown-item">
                        𝕏 Share on X
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($canonicalUrl) ?>" target="_blank" rel="noopener noreferrer" class="dropdown-item">
                        💼 Share on LinkedIn
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($canonicalUrl) ?>" target="_blank" rel="noopener noreferrer" class="dropdown-item">
                        📘 Share on Facebook
                    </a>
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode(($activeChapter['title'] ?? '') . ' ' . $canonicalUrl) ?>" target="_blank" rel="noopener noreferrer" class="dropdown-item">
                        💬 Share on WhatsApp
                    </a>
                    <a href="https://t.me/share/url?url=<?= urlencode($canonicalUrl) ?>&text=<?= urlencode($activeChapter['title'] ?? '') ?>" target="_blank" rel="noopener noreferrer" class="dropdown-item">
                        ✈️ Share on Telegram
                    </a>
                    <button type="button" class="dropdown-item" id="btn-share-slack-bar" data-url="<?= htmlspecialchars($canonicalUrl) ?>" data-title="<?= htmlspecialchars($activeChapter['title'] ?? '') ?>">
                        #️⃣ Share on Slack
                    </button>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item" id="btn-copy-bar-share" data-url="<?= htmlspecialchars($canonicalUrl) ?>">
                        🔗 Copy Share Link
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <button class="btn btn-outline btn-sm" id="theme-toggle" title="Toggle Dark/Light Mode">🌙</button>
            <button class="btn btn-outline btn-sm" id="btn-print-chapter" title="Print or Download as PDF">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            </button>
            <?php if ($isViewer && $activeBook && $activeChapter): ?>
                <a href="<?= htmlspecialchars(urlencode($activeBook['id']) . '/' . urlencode($activeChapter['slug'])) ?>" class="btn btn-outline btn-sm" title="Open in Full Wiki">↗ Open in Wiki</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <?php if (!$isShareMode): ?>
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar" id="app-sidebar">
            <div class="sidebar-resizer" id="sidebar-resizer" title="Drag right edge to resize sidebar"></div>
            <div class="sidebar-search">
                <div class="sidebar-search-wrapper">
                    <input type="text" id="search-input" class="search-input" placeholder="Search documentation..." autocomplete="off">
                    <button type="button" id="sidebar-search-clear" class="sidebar-search-clear" aria-label="Clear search" title="Clear search" style="display: none;">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>
            <?php if ($isSubwiki): ?>
            <div class="subwiki-parent-banner">
                <a href="<?= htmlspecialchars($config['parentUrl'] ?? '../') ?>">
                    <span>←</span>
                    <span>Back to <?= htmlspecialchars($parentTitle) ?></span>
                </a>
            </div>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <div class="sidebar-actions">
                <button type="button" class="btn-sidebar-add-doc" id="btn-add-chapter" title="Create New Document">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    <span>New Document</span>
                </button>
                <button type="button" class="btn-sidebar-add-cat" id="btn-add-book" title="Add New Category">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path><line x1="12" y1="6" x2="12" y2="12"></line><line x1="9" y1="9" x2="15" y2="9"></line></svg>
                    <span style="font-size: 0.75rem; font-weight: 600;">+ Cat</span>
                </button>
            </div>
            <?php endif; ?>
            <nav class="sidebar-nav">
                <?php foreach ($allowedBooks as $book): ?>
                    <?php Navigation::renderSidebarNode($book, $book['id'] ?? '', $activePathIds, $activeChapter['slug'] ?? '', 0, $isAdmin, $isViewer, $showDocTypesOnlyToAdmin, $extManager); ?>
                <?php endforeach; ?>
                <?php if ($showSubwikisInSidebar && !empty($sidebarSubwikis)): ?>
                <div class="nav-category-item depth-0 nav-subwikis-group collapsed" data-category-id="subwikis">
                    <div class="nav-category-header">
                        <span>🌐 Subwikis</span>
                        <span class="header-actions-inline">
                            <span class="chevron-icon">▾</span>
                        </span>
                    </div>
                    <div class="nav-document-list">
                        <?php foreach ($sidebarSubwikis as $sub): ?>
                            <a href="<?= htmlspecialchars($sub['url']) ?>" class="nav-link nav-subwiki-link" title="<?= htmlspecialchars($sub['title']) ?>">
                                <span class="nav-link-title-container">
                                    <span class="subwiki-item-icon">🌐</span>
                                    <span><?= htmlspecialchars($sub['title']) ?></span>
                                </span>
                                <span class="badge-subwiki-count" title="<?= (int)$sub['docCount'] ?> Documents"><?= (int)$sub['docCount'] ?> docs</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </nav>
            <?php if ($showPoweredBy): ?>
            <div class="sidebar-footer">
                <a href="https://qwiki.wiki" target="_blank" rel="noopener noreferrer" class="qwiki-powered-badge" title="Powered by Qwiki — Click to open qwiki.wiki in a new tab">
                    <span class="badge-icon">⚡</span>
                    <span class="badge-text">Powered by <strong>Qwiki</strong></span>
                    <svg class="badge-external-icon" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>
            </div>
            <?php endif; ?>
        </aside>
        <?php endif; ?>

        <!-- Main Content Area -->
        <main class="app-content">
            <?php if ($routeAction === 'reset_password'): ?>
                <div class="content-body" style="max-width: 480px; margin: 3rem auto; padding: 2.5rem; background: var(--bg-surface, #fff); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="font-size: 2.75rem; margin-bottom: 0.5rem;">🔑</div>
                        <h2 style="margin: 0 0 0.5rem 0;">Reset Account Password</h2>
                        <?php if ($resetPasswordValidation && $resetPasswordValidation['valid']): ?>
                            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Set a new password for account <strong><?= htmlspecialchars($resetPasswordValidation['username']) ?></strong>.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($resetPasswordValidation && $resetPasswordValidation['valid']): ?>
                        <form id="standalone-reset-password-form">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($resetPasswordToken) ?>">
                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" for="reset-new-password">New Password (min 4 characters)</label>
                                <input type="password" id="reset-new-password" name="newPassword" class="form-control" placeholder="Enter new password" required autocomplete="new-password">
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label class="form-label" for="reset-confirm-password">Confirm New Password</label>
                                <input type="password" id="reset-confirm-password" class="form-control" placeholder="Confirm new password" required autocomplete="new-password">
                            </div>
                            <div id="reset-password-status" style="display: none; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem;"></div>
                            <button type="submit" class="btn btn-primary" id="btn-submit-reset-password" style="width: 100%;">Save New Password &amp; Log In</button>
                        </form>
                    <?php else: ?>
                        <div style="padding: 1rem; background: rgba(239,68,68,0.1); border: 1px solid #ef4444; border-radius: 6px; color: #b91c1c; font-size: 0.9rem; text-align: center; margin-bottom: 1.5rem;">
                            <?= htmlspecialchars($resetPasswordValidation['error'] ?? 'This password reset link is invalid or has expired.') ?>
                        </div>
                        <div style="text-align: center;">
                            <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-primary">Return to Homepage</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($routeAction === 'verify_email'): ?>
                <div class="content-body" style="max-width: 480px; margin: 3rem auto; padding: 2.5rem; text-align: center; background: var(--bg-surface, #fff); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
                    <?php if ($emailVerificationResult && $emailVerificationResult['success']): ?>
                        <div style="font-size: 3rem; margin-bottom: 0.75rem;">✅</div>
                        <h2 style="margin: 0 0 0.75rem 0;">Email Verified!</h2>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.75rem;">
                            Your email address <strong><?= htmlspecialchars($emailVerificationResult['email']) ?></strong> has been verified successfully.
                        </p>
                    <?php else: ?>
                        <div style="font-size: 3rem; margin-bottom: 0.75rem;">⚠️</div>
                        <h2 style="margin: 0 0 0.75rem 0;">Verification Failed</h2>
                        <p style="color: #ef4444; font-size: 0.9rem; margin-bottom: 1.75rem;">
                            <?= htmlspecialchars($emailVerificationResult['error'] ?? 'This email verification link is invalid or has expired.') ?>
                        </p>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-primary">Continue to Documentation</a>
                </div>
            <?php elseif ($shareError === 'not_found'): ?>
                <div class="content-body" style="text-align: center; padding: 4rem 1.5rem;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔍</div>
                    <h2>Document Not Found</h2>
                    <p style="color: var(--text-muted); max-width: 460px; margin: 0 auto 2rem;">The shared document could not be found or the link has expired.</p>
                    <a href="<?= htmlspecialchars($baseUrl) ?>" class="btn btn-primary">Return to Documentation</a>
                </div>
            <?php elseif ($shareError === 'restricted'): ?>
                <div class="content-body" style="text-align: center; padding: 4rem 1.5rem;">
                    <div style="font-size: 3.5rem; margin-bottom: 1rem;">🔒</div>
                    <h2>Public Sharing Disabled</h2>
                    <p style="color: var(--text-muted); max-width: 480px; margin: 0 auto 2rem;">The administrator has disabled public sharing for this document. If you have an authorized account, please log in to access this documentation.</p>
                    <button class="btn btn-primary" onclick="document.getElementById('login-modal').classList.add('open')">Log In</button>
                </div>
            <?php elseif ($activeChapter || !empty($isAccessRestricted)): ?>
                <?php if (!$isShareMode): ?>
                <div class="content-header">
                    <div class="breadcrumbs">
                        <?php if (!empty($isAccessRestricted)): ?>
                            <span>Access Restricted</span>
                        <?php else: ?>
                            <?php foreach ($breadcrumbsTrail as $index => $crumb): ?>
                                <?php if ($index > 0): ?><span>/</span><?php endif; ?>
                                <?php 
                                $crumbUrl = urlencode($activeBook['id'] ?? '');
                                if (($crumb['id'] ?? '') !== ($activeBook['id'] ?? '')) {
                                    $crumbUrl .= '/' . urlencode($crumb['id'] ?? '');
                                }
                                ?>
                                <a href="<?= $crumbUrl ?>"><?= htmlspecialchars($crumb['title'] ?? '') ?></a>
                            <?php endforeach; ?>
                            <?php if ($activeChapter): ?>
                                <span>/</span>
                                <span><?= htmlspecialchars($activeChapter['title'] ?? '') ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($activeChapter): ?>
                    <div class="content-actions">
                        <div id="read-actions" style="display: flex; gap: 0.75rem;">
                            <?php if (($activeChapter['type'] ?? '') === 'gdoc' && !empty($activeChapter['editUrl'])): ?>
                                <a href="<?= htmlspecialchars($activeChapter['editUrl']) ?>" target="_blank" class="btn btn-outline btn-sm" title="Edit Google Doc">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2-2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline btn-sm" id="btn-print-chapter" title="Print or Download as PDF">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            </button>
                            <?php
                            $chapterShareKey = $activeChapter['shareKey'] ?? '';
                            $chapterShareUrl = !empty($chapterShareKey) ? ($baseUrl . '?share=' . urlencode($chapterShareKey)) : '';
                            ?>
                            <button class="btn btn-outline btn-sm" id="btn-share-chapter" title="Share"
                                    data-slug="<?= htmlspecialchars($activeChapter['slug'] ?? '') ?>"
                                    data-title="<?= htmlspecialchars($activeChapter['title'] ?? '') ?>"
                                    data-share-key="<?= htmlspecialchars($chapterShareKey) ?>"
                                    data-share-url="<?= htmlspecialchars($chapterShareUrl) ?>"
                                    data-public-shareable="<?= (!isset($activeChapter['publicShareable']) || !empty($activeChapter['publicShareable'])) ? '1' : '0' ?>">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                            </button>

                            <?php if ($isPageReadOnly): ?>
                                <span class="badge badge-secondary" title="This document is protected against modifications" style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.65rem; font-size: 0.8rem; border-radius: 4px; opacity: 0.85;">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    <?= Config::isDemoMode() ? 'Protected Demo Page' : 'Protected Document' ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($isAdmin): ?>
                                <?php if (!$isPageReadOnly && ($activeChapter['type'] ?? 'markdown') === 'markdown'): ?>
                                    <button class="btn btn-primary btn-sm" id="btn-edit-markdown" title="Edit Content">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                    </button>
                                <?php endif; ?>
                                <?php if (!Config::isDemoMode() || !$isPageReadOnly): ?>
                                    <button class="btn btn-outline btn-sm" id="btn-edit-chapter-meta" title="Edit Details"
                                            data-title="<?= htmlspecialchars($activeChapter['title']) ?>"
                                            data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>"
                                            data-type="<?= htmlspecialchars($activeChapter['type'] ?? 'markdown') ?>"
                                            data-url="<?= htmlspecialchars($activeChapter['url'] ?? '') ?>"
                                            data-edit-url="<?= htmlspecialchars($activeChapter['editUrl'] ?? '') ?>"
                                            data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>"
                                            data-theme="<?= htmlspecialchars($activeChapter['theme'] ?? '') ?>"
                                            data-description="<?= htmlspecialchars($activeChapter['description'] ?? '') ?>"
                                            data-image="<?= htmlspecialchars($activeChapter['image'] ?? '') ?>"
                                            data-public-shareable="<?= (!isset($activeChapter['publicShareable']) || !empty($activeChapter['publicShareable'])) ? '1' : '0' ?>"
                                            data-share-key="<?= htmlspecialchars($activeChapter['shareKey'] ?? '') ?>"
                                            data-book-id="<?= htmlspecialchars($currentCategoryId) ?>"
                                            data-category-id="<?= htmlspecialchars($currentCategoryId) ?>"
                                            data-doc-readonly="<?= (!empty($activeChapter['readOnly']) || (isset($activeChapter['editable']) && $activeChapter['editable'] === false) || !empty($activeChapter['locked'])) ? '1' : '0' ?>"
                                            data-doc-protected="<?= $isPageReadOnly ? '1' : '0' ?>"
                                            data-parent-locked="<?= (!empty($activeChapter['slug']) && Config::isChapterAncestorProtected($activeChapter['slug'], $config['books'] ?? [])) ? '1' : '0' ?>">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                    </button>
                                <?php endif; ?>
                                <?php if (!$isPageReadOnly && in_array($activeChapter['type'] ?? 'markdown', ['markdown', 'pdf'])): ?>
                                    <button class="btn btn-outline btn-sm" id="btn-replace-document" title="Replace Document Content"
                                            data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>"
                                            data-title="<?= htmlspecialchars($activeChapter['title']) ?>"
                                            data-type="<?= htmlspecialchars($activeChapter['type'] ?? 'markdown') ?>">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                    </button>
                                <?php endif; ?>
                                <?php if (!$isPageReadOnly): ?>
                                    <button class="btn btn-outline btn-sm btn-danger-text" id="btn-delete-chapter" title="Delete Document" data-book="<?= htmlspecialchars($activeBook['id'] ?? '') ?>" data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline btn-sm" id="btn-send-to-postbox" title="Send to Postbox..." data-book="<?= htmlspecialchars($activeBook['id'] ?? '') ?>" data-slug="<?= htmlspecialchars($activeChapter['slug']) ?>">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAdmin && !$isPageReadOnly && ($activeChapter['type'] ?? 'markdown') === 'markdown'): ?>
                        <div id="edit-actions" style="display: none; gap: 0.75rem; align-items: center;">
                            <button type="button" class="btn btn-outline btn-sm" id="btn-editor-gallery" title="Browse and select images from gallery">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px; vertical-align: text-bottom;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                Image Gallery
                            </button>
                            <button class="btn btn-outline btn-sm" id="btn-cancel-edit">Cancel</button>
                            <button class="btn btn-primary btn-sm" id="btn-save-inline-markdown" data-file="<?= htmlspecialchars($activeChapter['file'] ?? '') ?>">Save Changes</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin && !$isShareMode && !$isPageReadOnly && ($activeChapter['type'] ?? 'markdown') === 'markdown'): ?>
                    <textarea id="raw-markdown-data" style="display: none;"><?= htmlspecialchars($rawMarkdownContent) ?></textarea>
                    <div id="inline-editor-container" style="display: none; margin-top: 1rem; width: 100%;"></div>
                <?php endif; ?>
                <div class="content-body" id="content-body">
                    <?php if (!empty($isAccessRestricted)): ?>
                        <div style="text-align: center; padding: 4rem 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
                            <h2>Access Restricted</h2>
                            <p style="color: var(--text-muted); margin-bottom: 2rem;">Authentication is required to view this document.</p>
                            <button class="btn btn-primary" onclick="document.getElementById('login-modal').classList.add('open')">Log In to Access</button>
                        </div>
                    <?php elseif ($canViewContent): ?>
                        <?= $renderedContent ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 4rem 2rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
                            <h2>Private Documentation Portal</h2>
                            <p style="color: var(--text-muted); margin-bottom: 2rem;">Authentication is required to view documentation content.</p>
                            <button class="btn btn-primary" onclick="document.getElementById('login-modal').classList.add('open')">Log In to Access</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="content-body">
                    <h1>No Document Selected</h1>
                    <p>Select a category or document from the sidebar to view content.</p>
                </div>
            <?php endif; ?>
        </main>

        <?php if (!$isShareMode && !$shareError && empty($isAccessRestricted)): ?>
        <!-- Table of Contents Sidebar -->
        <aside class="app-toc" id="app-toc">
            <?php if ($activeChapter && ($prevDoc || $nextDoc)): ?>
                <div class="toc-nav-buttons">
                    <?php if ($prevDoc): ?>
                        <a href="<?= htmlspecialchars($prevDoc['url']) ?>" class="btn btn-outline btn-sm" style="flex: 1; text-align: center; padding: 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Previous: <?= htmlspecialchars($prevDoc['title']) ?>">&laquo; Prev</a>
                    <?php else: ?>
                        <div style="flex: 1;"></div>
                    <?php endif; ?>
                    
                    <?php if ($nextDoc): ?>
                        <a href="<?= htmlspecialchars($nextDoc['url']) ?>" class="btn btn-outline btn-sm" style="flex: 1; text-align: center; padding: 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Next: <?= htmlspecialchars($nextDoc['title']) ?>">Next &raquo;</a>
                    <?php else: ?>
                        <div style="flex: 1;"></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="toc-header">Table of Contents</div>
            <div class="toc-content" id="toc-content"></div>
        </aside>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    $templatesDir = is_dir(__DIR__ . '/templates') ? (__DIR__ . '/templates') : (dirname(__DIR__) . '/templates');
    ?>

    <?php if (!$canViewContent): ?>
        <?php require $templatesDir . '/modals/forgot_password.php'; ?>
    <?php else: ?>
        <?php if (!$isAdmin && !$isViewer): ?>
            <?php require $templatesDir . '/modals/login.php'; ?>
            <?php require $templatesDir . '/modals/forgot_password.php'; ?>
        <?php endif; ?>

        <?php require $templatesDir . '/modals/share.php'; ?>

        <?php if ($isAdmin): ?>
            <?php require $templatesDir . '/modals/users.php'; ?>
            <?php require $templatesDir . '/modals/llm_keys.php'; ?>
            <?php require $templatesDir . '/modals/create_category.php'; ?>
            <?php require $templatesDir . '/modals/edit_category.php'; ?>
            <?php require $templatesDir . '/modals/edit_link.php'; ?>
            <?php require $templatesDir . '/modals/create_chapter.php'; ?>
            <?php if ($activeChapter): ?>
                <?php require $templatesDir . '/modals/edit_chapter.php'; ?>
                <?php require $templatesDir . '/modals/replace_document.php'; ?>
            <?php endif; ?>
            <?php require $templatesDir . '/modals/upload_conflict.php'; ?>
            <?php require $templatesDir . '/modals/settings.php'; ?>
            <?php if (!$isSubwiki): ?>
                <?php require $templatesDir . '/modals/subwikis.php'; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isAdmin || $isViewer): ?>
            <?php require $templatesDir . '/modals/user_profile.php'; ?>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <?php require $templatesDir . '/modals/theme_editor.php'; ?>
            <?php require $templatesDir . '/modals/update_available.php'; ?>
            <!-- Utility Modals Injected by Extensions -->
            <?php $extManager->renderUtilityModals(); ?>
        <?php endif; ?>
    <?php endif; ?>

    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/katex.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/katex@0.16.11/dist/contrib/auto-render.min.js"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/soft-lock.js?v=<?= @filemtime(__DIR__ . '/assets/js/soft-lock.js') ?: time() ?>"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/qrcode.min.js?v=<?= @filemtime(__DIR__ . '/assets/js/qrcode.min.js') ?: time() ?>"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/core/theme.js?v=<?= @filemtime(__DIR__ . '/assets/js/core/theme.js') ?: time() ?>"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/core/sidebar.js?v=<?= @filemtime(__DIR__ . '/assets/js/core/sidebar.js') ?: time() ?>"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/core/search.js?v=<?= @filemtime(__DIR__ . '/assets/js/core/search.js') ?: time() ?>"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/core/modals.js?v=<?= @filemtime(__DIR__ . '/assets/js/core/modals.js') ?: time() ?>"></script>
    <script src="<?= htmlspecialchars($assetsUrl) ?>/js/app.js?v=<?= @filemtime(__DIR__ . '/assets/js/app.js') ?: time() ?>"></script>

    <!-- Extension Scripts -->
    <?php foreach ($extensionAssets['scripts'] as $scriptFile): ?>
        <script src="<?= htmlspecialchars($scriptFile) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
