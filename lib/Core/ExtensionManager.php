<?php
namespace Qwiki\Core;

class ExtensionManager {
    private static $instance = null;
    private $extensionsDir = null;
    private $webExtensionsDir = 'assets/extensions';
    private $pageTypes = [];
    private $utilities = [];
    private $discovered = false;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $baseDir = Config::getBaseDir();
        $this->extensionsDir = $baseDir . '/assets/extensions';
    }

    public function discover() {
        if ($this->discovered) {
            return;
        }

        if (!is_dir($this->extensionsDir)) {
            @mkdir($this->extensionsDir, 0755, true);
        }

        $dirs = glob($this->extensionsDir . '/*', GLOB_ONLYDIR);
        if ($dirs) {
            foreach ($dirs as $dir) {
                $manifestFile = $dir . '/manifest.json';
                if (file_exists($manifestFile)) {
                    $manifest = json_decode(file_get_contents($manifestFile), true);
                    if (is_array($manifest)) {
                        $this->registerExtension($dir, $manifest);
                    }
                }
            }
        }

        $this->discovered = true;
    }

    private function registerExtension($dir, array $manifest) {
        $id = $manifest['id'] ?? basename($dir);
        $type = $manifest['type'] ?? 'page_type';
        $folderName = basename($dir);
        $webPath = $this->webExtensionsDir . '/' . $folderName;

        $manifest['base_dir'] = $dir;
        $manifest['web_path'] = $webPath;

        if ($type === 'page_type') {
            $this->pageTypes[$id] = $manifest;
        } elseif ($type === 'utility') {
            $this->utilities[$id] = $manifest;
        }
    }

    public function getPageTypes() {
        $this->discover();
        return $this->pageTypes;
    }

    public function getPageType($id) {
        $this->discover();
        return $this->pageTypes[$id] ?? null;
    }

    public function getUtilities() {
        $this->discover();
        return $this->utilities;
    }

    public function getUtility($id) {
        $this->discover();
        return $this->utilities[$id] ?? null;
    }

    public function renderBadge($docType) {
        $this->discover();
        $type = strtolower($docType);
        if (isset($this->pageTypes[$type])) {
            $badge = $this->pageTypes[$type]['badge'] ?? null;
            if (is_array($badge)) {
                if (!empty($badge['svg'])) {
                    return $badge['svg'];
                }
                if (!empty($badge['label'])) {
                    return htmlspecialchars($badge['label']);
                }
            } elseif (is_string($badge)) {
                return htmlspecialchars($badge);
            }
        }
        return null;
    }

    public function renderPage($activeChapter, $activeBook, $config) {
        $this->discover();
        if (!$activeChapter) {
            return '';
        }

        $type = strtolower($activeChapter['type'] ?? 'markdown');
        $baseDir = Config::getBaseDir();

        // 1. Built-in: Markdown
        if ($type === 'markdown' || $type === 'md') {
            $filePath = $baseDir . '/' . ($activeChapter['file'] ?? '');
            if (file_exists($filePath)) {
                $rawMarkdown = file_get_contents($filePath);
                $parsedown = new \QwikiParsedown();
                return $parsedown->text($rawMarkdown);
            } else {
                return "<div class='alert warning'>Markdown file not found: " . htmlspecialchars($activeChapter['file'] ?? '') . "</div>";
            }
        }

        // 2. Built-in: PDF
        if ($type === 'pdf') {
            $pdfUrl = htmlspecialchars($activeChapter['file'] ?? '');
            return "
                <div class='pdf-viewer-container'>
                    <iframe src='{$pdfUrl}' title='PDF Viewer'></iframe>
                </div>
                <p style='margin-top: 1rem;'><a href='{$pdfUrl}' target='_blank' class='btn btn-outline btn-sm'>Download Original PDF</a></p>
            ";
        }

        // 3. Built-in: Google Doc
        if ($type === 'gdoc') {
            $docUrl = $activeChapter['url'] ?? '';
            if ($docUrl) {
                if (strpos($docUrl, 'embedded=true') === false) {
                    $docUrl .= (strpos($docUrl, '?') !== false) ? '&embedded=true' : '?embedded=true';
                }
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => "User-Agent: Mozilla/5.0\r\n"]]);
                $html = @file_get_contents($docUrl, false, $ctx);
                
                if ($html && function_exists('str_get_html')) {
                    $dom = str_get_html($html);
                    if ($dom && $dom->find('#contents', 0)) {
                        $body = $dom->find('#contents', 0)->innertext;
                        return "<div class='gdoc-content'>" . $body . "</div>";
                    } elseif ($dom && $dom->find('body', 0)) {
                        return "<div class='gdoc-content'>" . $dom->find('body', 0)->innertext . "</div>";
                    }
                }
                return "<div class='gdoc-container'><iframe src='" . htmlspecialchars($docUrl) . "' style='width:100%; height:750px; border:1px solid var(--border-color); border-radius:8px;'></iframe></div>";
            }
            return "<p>No Google Doc URL provided.</p>";
        }

        // 4. Custom Extension Page Types
        if (isset($this->pageTypes[$type])) {
            $ext = $this->pageTypes[$type];
            $rendererFile = $ext['base_dir'] . '/' . ($ext['renderer'] ?? 'renderer.php');
            if (file_exists($rendererFile)) {
                ob_start();
                // Pass variables into scope of included renderer
                $chapter = $activeChapter;
                $book = $activeBook;
                $extension = $ext;
                include $rendererFile;
                return ob_get_clean();
            }
        }

        return "<div class='alert warning'>Unsupported document type: " . htmlspecialchars($type) . "</div>";
    }

    public function extractSearchableText($item, $baseDir) {
        $this->discover();
        $type = strtolower($item['type'] ?? 'markdown');

        if ($type === 'markdown' || $type === 'md') {
            $file = $item['file'] ?? '';
            if ($file) {
                $filePath = $baseDir . '/' . $file;
                if (file_exists($filePath)) {
                    return file_get_contents($filePath);
                }
            }
            return '';
        }

        if (isset($this->pageTypes[$type])) {
            $ext = $this->pageTypes[$type];
            $searchExtractor = $ext['base_dir'] . '/' . ($ext['search_extractor'] ?? 'search_extractor.php');
            if (file_exists($searchExtractor)) {
                $chapter = $item;
                return include $searchExtractor;
            }

            // Fallback: read file and strip HTML tags if HTML
            $file = $item['file'] ?? '';
            if ($file) {
                $filePath = $baseDir . '/' . $file;
                if (file_exists($filePath)) {
                    return strip_tags(file_get_contents($filePath));
                }
            }
        }

        return '';
    }

    public function handleAction($action, array $requestData) {
        $this->discover();

        // 1. Check if an extension matches action directly or via action prefix
        foreach ($this->utilities as $id => $util) {
            $handlerFile = $util['base_dir'] . '/' . ($util['handler'] ?? 'handler.php');
            if (file_exists($handlerFile)) {
                $supportedActions = $util['actions'] ?? [$id, 'ext_' . $id];
                if (in_array($action, $supportedActions) || strpos($action, 'ext_' . $id) === 0) {
                    $utility = $util;
                    include $handlerFile;
                    return true;
                }
            }
        }

        // 2. Check page types for custom handlers (e.g. create_html)
        foreach ($this->pageTypes as $id => $pt) {
            $handlerFile = $pt['base_dir'] . '/' . ($pt['handler'] ?? 'handler.php');
            if (file_exists($handlerFile)) {
                $supportedActions = $pt['actions'] ?? ['add_' . $id, 'create_' . $id, 'edit_' . $id, 'ext_' . $id];
                if (in_array($action, $supportedActions) || strpos($action, 'ext_' . $id) === 0) {
                    $pageType = $pt;
                    include $handlerFile;
                    return true;
                }
            }
        }

        return false;
    }

    public function getFrontendAssets() {
        $this->discover();
        $styles = [];
        $scripts = [];

        $all = array_merge($this->pageTypes, $this->utilities);
        foreach ($all as $ext) {
            if (!empty($ext['styles'])) {
                foreach ((array)$ext['styles'] as $style) {
                    if (preg_match('#^(https?:)?//#i', $style)) {
                        $styles[] = $style;
                    } else {
                        $styles[] = $ext['web_path'] . '/' . ltrim($style, '/');
                    }
                }
            } elseif (file_exists($ext['base_dir'] . '/style.css')) {
                $styles[] = $ext['web_path'] . '/style.css';
            }

            if (!empty($ext['scripts'])) {
                foreach ((array)$ext['scripts'] as $script) {
                    if (preg_match('#^(https?:)?//#i', $script)) {
                        $scripts[] = $script;
                    } else {
                        $scripts[] = $ext['web_path'] . '/' . ltrim($script, '/');
                    }
                }
            } elseif (file_exists($ext['base_dir'] . '/script.js')) {
                $scripts[] = $ext['web_path'] . '/script.js';
            }
        }

        return [
            'styles' => array_unique($styles),
            'scripts' => array_unique($scripts)
        ];
    }

    public function renderAddDocumentTabs() {
        $this->discover();
        foreach ($this->pageTypes as $id => $ext) {
            $tabId = 'tab-ext-' . htmlspecialchars($id);
            $icon = $ext['icon'] ?? '📄';
            $title = htmlspecialchars($ext['title'] ?? ucfirst($id));
            echo "<button class='tab-btn' data-tab='{$tabId}'>{$icon} {$title}</button>\n";
        }
    }

    public function renderAddDocumentForms($activeBook, $config) {
        $this->discover();
        foreach ($this->pageTypes as $id => $ext) {
            $tabId = 'tab-ext-' . htmlspecialchars($id);
            $modalFile = $ext['base_dir'] . '/' . ($ext['modal'] ?? 'modal.html.php');
            if (file_exists($modalFile)) {
                echo "<form id='{$tabId}' class='tab-content' data-ext-id='" . htmlspecialchars($id) . "'>\n";
                $extension = $ext;
                include $modalFile;
                echo "</form>\n";
            }
        }
    }

    public function renderUtilityModals() {
        $this->discover();
        foreach ($this->utilities as $id => $util) {
            $modalFile = $util['base_dir'] . '/' . ($util['modal'] ?? 'modal.html.php');
            if (file_exists($modalFile)) {
                $utility = $util;
                include $modalFile;
            }
        }
    }

    public function renderHeaderUtilityButtons() {
        $this->discover();
        foreach ($this->utilities as $id => $util) {
            $placement = $util['placement'] ?? 'dropdown';
            $icon = $util['icon'] ?? '⚡';
            $title = htmlspecialchars($util['title'] ?? ucfirst($id));
            $btnId = 'btn-util-' . htmlspecialchars($id);
            echo "<button class='dropdown-item' id='{$btnId}'>{$icon} {$title}</button>\n";
        }
    }
}
