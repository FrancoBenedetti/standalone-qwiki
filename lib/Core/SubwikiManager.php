<?php
namespace Qwiki\Core;

class SubwikiManager {

    /**
     * Check if the current installation is a subwiki.
     */
    public static function isSubwiki(): bool {
        return Config::isSubwiki();
    }

    /**
     * Validate a slug bi-directionally for category or subwiki usage.
     *
     * @param string $slug The proposed slug.
     * @param string|null $currentBookId If updating an existing category, its current ID.
     * @param bool $isForSubwiki True if validating for a new subwiki; false if for a category.
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateSlug(string $slug, ?string $currentBookId = null, bool $isForSubwiki = false): array {
        $slug = trim($slug);
        if (empty($slug)) {
            return ['valid' => false, 'error' => 'Identifier or slug cannot be empty.'];
        }

        // Must be alphanumeric with single hyphens (no slashes, dots, or underscores)
        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
            return [
                'valid' => false,
                'error' => 'Invalid slug format. Use only lowercase letters, numbers, and hyphens (e.g. "engineering-electrical").'
            ];
        }

        // Check reserved system routes
        $reserved = Config::getReservedNames();
        if (in_array($slug, $reserved, true)) {
            return [
                'valid' => false,
                'error' => "The slug '{$slug}' is a reserved system directory name and cannot be used."
            ];
        }

        $baseDir = Config::getBaseDir();
        $config = Config::load();

        if ($isForSubwiki) {
            // 1. Subwiki cannot clash with any parent category
            if (!empty($config['books'])) {
                foreach ($config['books'] as $book) {
                    if (($book['id'] ?? '') === $slug) {
                        return [
                            'valid' => false,
                            'error' => "A category named '{$slug}' already exists in the parent wiki. Choose a different name or use a dash prefix (e.g. 'sub-{$slug}')."
                        ];
                    }
                }
            }

            // 2. Subwiki cannot clash with an existing physical directory on disk
            $targetDir = $baseDir . '/' . $slug;
            if (is_dir($targetDir)) {
                return [
                    'valid' => false,
                    'error' => "A folder named '{$slug}' already exists on disk. Please select a unique slug."
                ];
            }

            // 3. Subwiki cannot clash with an already registered subwiki
            if (!empty($config['subwikis'])) {
                foreach ($config['subwikis'] as $sub) {
                    if (($sub['slug'] ?? '') === $slug) {
                        return [
                            'valid' => false,
                            'error' => "A subwiki with slug '{$slug}' is already registered."
                        ];
                    }
                }
            }
        } else {
            // Creating or editing a category in the parent wiki:
            // 1. Cannot clash with any physical directory in root (which would shadow the category via web server -d rules)
            $targetDir = $baseDir . '/' . $slug;
            if (is_dir($targetDir)) {
                return [
                    'valid' => false,
                    'error' => "A physical directory or subwiki named '{$slug}' exists in the web root. Categories cannot share names with physical subdirectories."
                ];
            }

            // 2. Cannot clash with any registered subwiki
            if (!empty($config['subwikis'])) {
                foreach ($config['subwikis'] as $sub) {
                    if (($sub['slug'] ?? '') === $slug) {
                        return [
                            'valid' => false,
                            'error' => "The slug '{$slug}' is reserved by a deployed subwiki."
                        ];
                    }
                }
            }

            // 3. Cannot clash with another existing category (unless editing self)
            if (!empty($config['books'])) {
                foreach ($config['books'] as $book) {
                    if (($book['id'] ?? '') === $slug && $slug !== $currentBookId) {
                        return [
                            'valid' => false,
                            'error' => "Another category with ID '{$slug}' already exists."
                        ];
                    }
                }
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * List all subwikis (both registered and discovered).
     */
    public static function listSubwikis(): array {
        $baseDir = Config::getBaseDir();
        $config = Config::load();
        $registered = $config['subwikis'] ?? [];

        $result = [];
        $reserved = array_merge(Config::getReservedNames(), ['demo-data', 'assets', 'lib', 'content', 'uploads', 'api', 'tests']);

        // Check registered
        foreach ($registered as $sub) {
            $slug = $sub['slug'] ?? '';
            if (empty($slug)) continue;
            $dir = $baseDir . '/' . $slug;
            $subConfigFile = $dir . '/qwiki.json';
            $title = $sub['title'] ?? $slug;
            $docCount = 0;

            if (file_exists($subConfigFile)) {
                $subData = @json_decode(file_get_contents($subConfigFile), true);
                if (is_array($subData)) {
                    $title = $subData['title'] ?? $title;
                    if (!empty($subData['books'])) {
                        foreach ($subData['books'] as $b) {
                            $docCount += count($b['items'] ?? []);
                        }
                    }
                }
            }

            $result[$slug] = [
                'slug' => $slug,
                'title' => $title,
                'url' => $slug . '/',
                'docCount' => $docCount,
                'createdAt' => $sub['createdAt'] ?? date('Y-m-d H:i:s'),
                'exists' => is_dir($dir)
            ];
        }

        // Auto-discover physical folders containing qwiki.json that might not yet be in the registry
        $dirs = @glob($baseDir . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            if (in_array($slug, $reserved, true)) continue;
            if (isset($result[$slug])) continue;

            if (file_exists($dir . '/qwiki.json') && file_exists($dir . '/index.php')) {
                $subData = @json_decode(file_get_contents($dir . '/qwiki.json'), true);
                $title = is_array($subData) && !empty($subData['title']) ? $subData['title'] : $slug;
                $docCount = 0;
                if (is_array($subData) && !empty($subData['books'])) {
                    foreach ($subData['books'] as $b) {
                        $docCount += count($b['items'] ?? []);
                    }
                }

                $result[$slug] = [
                    'slug' => $slug,
                    'title' => $title,
                    'url' => $slug . '/',
                    'docCount' => $docCount,
                    'createdAt' => date('Y-m-d H:i:s', filectime($dir)),
                    'exists' => true,
                    'unregistered' => true
                ];
            }
        }

        return array_values($result);
    }

    /**
     * Deploy a new single-level subwiki from the parent wiki.
     */
    public static function deploySubwiki(string $slug, string $title, string $adminUser, string $adminPass, bool $includeDemoContent = false): array {
        if (self::isSubwiki()) {
            return [
                'success' => false,
                'error' => 'Subwikis cannot deploy further subwikis. Maximum nesting depth (1 level) reached.'
            ];
        }

        $slug = Config::makeSlug($slug);
        $val = self::validateSlug($slug, null, true);
        if (!$val['valid']) {
            return ['success' => false, 'error' => $val['error']];
        }

        $title = trim($title);
        if (empty($title)) {
            return ['success' => false, 'error' => 'Subwiki title is required.'];
        }

        $adminUser = trim($adminUser);
        $adminPass = trim($adminPass);
        if (empty($adminUser) || empty($adminPass)) {
            return ['success' => false, 'error' => 'Admin username and password are required for the new subwiki.'];
        }

        $baseDir = Config::getBaseDir();
        $parentConfig = Config::load();
        $targetDir = $baseDir . '/' . $slug;

        if (!@mkdir($targetDir, 0755, true)) {
            return ['success' => false, 'error' => "Failed to create directory '{$slug}'."];
        }

        // Copy core application files
        @copy($baseDir . '/index.php', $targetDir . '/index.php');
        @copy($baseDir . '/.htaccess', $targetDir . '/.htaccess');
        Config::copyDir($baseDir . '/lib', $targetDir . '/lib');
        Config::copyDir($baseDir . '/assets', $targetDir . '/assets');
        Config::copyDir($baseDir . '/api', $targetDir . '/api');

        // Create uploads directory and protect it
        @mkdir($targetDir . '/uploads/images', 0755, true);
        if (file_exists($baseDir . '/uploads/.htaccess')) {
            @copy($baseDir . '/uploads/.htaccess', $targetDir . '/uploads/.htaccess');
        }

        // Initialize content directory
        @mkdir($targetDir . '/content', 0755, true);
        if ($includeDemoContent && is_dir($baseDir . '/demo-data/content')) {
            Config::copyDir($baseDir . '/demo-data/content', $targetDir . '/content');
            $demoConfigFile = $baseDir . '/demo-data/qwiki-default.json';
            $books = [];
            if (file_exists($demoConfigFile)) {
                $demoData = @json_decode(file_get_contents($demoConfigFile), true);
                $books = $demoData['books'] ?? [];
            }
        } else {
            @mkdir($targetDir . '/content/getting-started', 0755, true);
            $welcomeContent = "# Welcome to {$title}\n\nThis is your new Qwiki documentation space. You can start creating and organizing your documentation here.\n";
            file_put_contents($targetDir . '/content/getting-started/welcome.md', $welcomeContent);

            $books = [
                [
                    'id' => 'getting-started',
                    'title' => 'Getting Started',
                    'type' => 'folder',
                    'items' => [
                        [
                            'title' => 'Welcome',
                            'slug' => 'welcome',
                            'type' => 'markdown',
                            'url' => '',
                            'editUrl' => '',
                            'file' => 'content/getting-started/welcome.md'
                        ]
                    ]
                ]
            ];
        }

        // Create child qwiki.json with subwiki metadata
        $childConfig = [
            'title' => $title,
            'logoText' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $slug), 0, 8)),
            'logoUrl' => '',
            'theme' => 'theme-default.css',
            'shareDescription' => "Documentation for {$title}",
            'isSubwiki' => true,
            'parentUrl' => '../',
            'parentTitle' => $parentConfig['title'] ?? 'Parent Qwiki',
            'defaultBook' => 'getting-started',
            'requireLoginToView' => false,
            'books' => $books
        ];
        file_put_contents($targetDir . '/qwiki.json', json_encode($childConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

        // Create child users.json
        $childUsers = [
            'users' => [
                [
                    'username' => $adminUser,
                    'role' => 'admin',
                    'passwordHash' => password_hash($adminPass, PASSWORD_DEFAULT),
                    'createdAt' => date('Y-m-d H:i:s')
                ]
            ]
        ];
        file_put_contents($targetDir . '/users.json', json_encode($childUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

        // Create subwiki marker
        file_put_contents($targetDir . '/.subwiki', "subwiki\n", LOCK_EX);

        // Register in parent qwiki.json
        if (!isset($parentConfig['subwikis']) || !is_array($parentConfig['subwikis'])) {
            $parentConfig['subwikis'] = [];
        }
        $parentConfig['subwikis'][] = [
            'slug' => $slug,
            'title' => $title,
            'createdAt' => date('Y-m-d H:i:s')
        ];
        Config::save($parentConfig);

        return [
            'success' => true,
            'slug' => $slug,
            'url' => $slug . '/',
            'title' => $title
        ];
    }

    /**
     * Delete or de-register a subwiki.
     */
    public static function deleteSubwiki(string $slug, bool $deleteFiles = false): array {
        if (self::isSubwiki()) {
            return ['success' => false, 'error' => 'Operation not permitted from within a subwiki.'];
        }

        $baseDir = Config::getBaseDir();
        $parentConfig = Config::load();

        // Remove from registry
        if (!empty($parentConfig['subwikis'])) {
            $parentConfig['subwikis'] = array_values(array_filter($parentConfig['subwikis'], function($item) use ($slug) {
                return ($item['slug'] ?? '') !== $slug;
            }));
            Config::save($parentConfig);
        }

        if ($deleteFiles) {
            $targetDir = $baseDir . '/' . $slug;
            if (is_dir($targetDir)) {
                self::recursiveDelete($targetDir);
            }
        }

        return ['success' => true];
    }

    /**
     * Cascade updates from the parent wiki to all registered/discovered subwikis.
     * Overwrites core engine files while protecting content, uploads, and configs.
     */
    public static function cascadeUpdates(string $sourceDir): array {
        $subwikis = self::listSubwikis();
        $updatedCount = 0;
        $errors = [];

        foreach ($subwikis as $sub) {
            $slug = $sub['slug'] ?? '';
            if (empty($slug)) continue;
            $targetDir = $sourceDir . '/' . $slug;
            if (!is_dir($targetDir)) continue;

            try {
                // 1. Update index.php
                if (file_exists($sourceDir . '/index.php')) {
                    @copy($sourceDir . '/index.php', $targetDir . '/index.php');
                }

                // 2. Update core directories
                Config::copyDir($sourceDir . '/lib', $targetDir . '/lib');
                Config::copyDir($sourceDir . '/assets', $targetDir . '/assets');
                Config::copyDir($sourceDir . '/api', $targetDir . '/api');

                // 3. Ensure .subwiki marker exists
                if (!file_exists($targetDir . '/.subwiki')) {
                    file_put_contents($targetDir . '/.subwiki', "subwiki\n", LOCK_EX);
                }

                // 4. Ensure child qwiki.json has isSubwiki: true
                $subConfigFile = $targetDir . '/qwiki.json';
                if (file_exists($subConfigFile)) {
                    $subData = @json_decode(file_get_contents($subConfigFile), true);
                    if (is_array($subData)) {
                        $needsSave = false;
                        if (empty($subData['isSubwiki'])) {
                            $subData['isSubwiki'] = true;
                            $needsSave = true;
                        }
                        if (empty($subData['parentUrl'])) {
                            $subData['parentUrl'] = '../';
                            $needsSave = true;
                        }
                        if ($needsSave) {
                            file_put_contents($subConfigFile, json_encode($subData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
                        }
                    }
                }

                $updatedCount++;
            } catch (\Throwable $e) {
                $errors[] = "Failed updating subwiki '{$slug}': " . $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'updatedCount' => $updatedCount,
            'errors' => $errors
        ];
    }

    /**
     * Migration routine to run during upgrade:
     * - Registers un-registered legacy subwikis
     * - Normalizes image paths
     * - Flattens any deeper nested folders (2+ depth) into 1-level dashed subwikis
     */
    public static function runMigration(): array {
        $baseDir = Config::getBaseDir();
        $parentConfig = Config::load();
        $migrated = [];

        // 1. Discover and register all 1-level subwikis
        $subwikis = self::listSubwikis();
        $registryUpdated = false;
        $existingRegistrySlugs = array_column($parentConfig['subwikis'] ?? [], 'slug');

        foreach ($subwikis as $sub) {
            $slug = $sub['slug'] ?? '';
            if (!in_array($slug, $existingRegistrySlugs, true)) {
                $parentConfig['subwikis'][] = [
                    'slug' => $slug,
                    'title' => $sub['title'] ?? $slug,
                    'createdAt' => date('Y-m-d H:i:s')
                ];
                $registryUpdated = true;
                $migrated[] = "Registered legacy subwiki '{$slug}'";
            }
        }

        if ($registryUpdated) {
            Config::save($parentConfig);
        }

        // 2. Flatten any 2nd-level nested subwikis (e.g. subwiki/another-wiki)
        foreach ($subwikis as $sub) {
            $slug = $sub['slug'] ?? '';
            $subDir = $baseDir . '/' . $slug;
            $nestedDirs = @glob($subDir . '/*', GLOB_ONLYDIR) ?: [];
            foreach ($nestedDirs as $nestedDir) {
                $nestedSlug = basename($nestedDir);
                if (in_array($nestedSlug, Config::getReservedNames(), true)) continue;
                if (file_exists($nestedDir . '/qwiki.json') && file_exists($nestedDir . '/index.php')) {
                    // Deep nesting detected! Flatten to parent level with a dash: slug-nestedSlug
                    $flattenedSlug = $slug . '-' . $nestedSlug;
                    $targetDir = $baseDir . '/' . $flattenedSlug;
                    if (!is_dir($targetDir)) {
                        if (@rename($nestedDir, $targetDir)) {
                            // Update flattened qwiki.json
                            $childJson = $targetDir . '/qwiki.json';
                            if (file_exists($childJson)) {
                                $cData = @json_decode(file_get_contents($childJson), true);
                                if (is_array($cData)) {
                                    $cData['isSubwiki'] = true;
                                    $cData['parentUrl'] = '../';
                                    file_put_contents($childJson, json_encode($cData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
                                }
                            }
                            $parentConfig['subwikis'][] = [
                                'slug' => $flattenedSlug,
                                'title' => ucwords(str_replace('-', ' ', $flattenedSlug)),
                                'createdAt' => date('Y-m-d H:i:s')
                            ];
                            Config::save($parentConfig);
                            $migrated[] = "Flattened deep subwiki '{$slug}/{$nestedSlug}' into 1-level subwiki '{$flattenedSlug}'";
                        }
                    }
                }
            }
        }

        // 3. Scan and normalize image references in all subwikis
        foreach ($subwikis as $sub) {
            $slug = $sub['slug'] ?? '';
            $contentDir = $baseDir . '/' . $slug . '/content';
            if (is_dir($contentDir)) {
                self::normalizeImagePaths($contentDir, $slug);
            }
        }

        return [
            'success' => true,
            'migrated' => $migrated
        ];
    }

    /**
     * Recursively scan markdown files and normalize image paths.
     */
    private static function normalizeImagePaths(string $contentDir, string $subwikiSlug) {
        if (!is_dir($contentDir)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($contentDir));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
                $content = file_get_contents($file->getPathname());
                $modified = false;

                // Strip /subwikiSlug/uploads/ -> uploads/
                $patternSlug = '#(!\[.*?\]\()/?' . preg_quote($subwikiSlug, '#') . '/uploads/#i';
                if (preg_match($patternSlug, $content)) {
                    $content = preg_replace($patternSlug, '$1uploads/', $content);
                    $modified = true;
                }

                // Strip root-relative /uploads/ -> uploads/
                if (preg_match('#(!\[.*?\]\()/uploads/#i', $content)) {
                    $content = preg_replace('#(!\[.*?\]\()/uploads/#i', '$1uploads/', $content);
                    $modified = true;
                }

                if ($modified) {
                    file_put_contents($file->getPathname(), $content, LOCK_EX);
                }
            }
        }
    }

    /**
     * Recursively delete a directory.
     */
    private static function recursiveDelete(string $dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
