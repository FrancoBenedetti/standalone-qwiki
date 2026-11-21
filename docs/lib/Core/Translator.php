<?php
namespace Qwiki\Core {
    class Translator {
        private static bool $initialized = false;
        private static bool $enabled = false;
        private static string $defaultLang = 'en';
        private static string $activeLang = 'en';
        private static array $languages = [];
        private static array $phpStrings = [];
        private static array $jsStrings = [];

        /**
         * Initialize the active UI translator from the tool-language extension.
         * When the extension is disabled or the active language is the default ('en'),
         * no locale dictionary files are read from disk.
         */
        public static function init(ExtensionManager $extManager, ?string $overrideLang = null): void {
            self::$initialized = true;
            self::$enabled = false;
            self::$defaultLang = 'en';
            self::$activeLang = 'en';
            self::$languages = [];
            self::$phpStrings = [];
            self::$jsStrings = [];

            $extManager->discover();
            if (!$extManager->isExtensionEnabled('language')) {
                return;
            }

            $util = $extManager->getUtility('language');
            if (!$util || empty($util['base_dir'])) {
                return;
            }

            $baseDir = $util['base_dir'];
            $localesFile = $baseDir . '/locales.json';
            if (!file_exists($localesFile)) {
                return;
            }

            $meta = json_decode((string)file_get_contents($localesFile), true);
            if (!is_array($meta) || empty($meta['languages']) || !is_array($meta['languages'])) {
                return;
            }

            self::$enabled = true;
            self::$defaultLang = is_string($meta['default'] ?? null) ? $meta['default'] : 'en';
            self::$languages = $meta['languages'];
            self::$activeLang = self::$defaultLang;

            $requested = $overrideLang !== null ? $overrideLang : ($_COOKIE['qwiki_lang'] ?? '');
            if (
                is_string($requested)
                && $requested !== ''
                && preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,4})?$/', $requested)
                && isset(self::$languages[$requested])
            ) {
                self::$activeLang = $requested;
            }

            if (self::$activeLang === self::$defaultLang) {
                return;
            }

            $langFile = $baseDir . '/locales/' . self::$activeLang . '.json';
            if (!file_exists($langFile)) {
                self::$activeLang = self::$defaultLang;
                return;
            }

            $dict = json_decode((string)file_get_contents($langFile), true);
            if (is_array($dict)) {
                self::$phpStrings = is_array($dict['php'] ?? null) ? $dict['php'] : [];
                self::$jsStrings = is_array($dict['js'] ?? null) ? $dict['js'] : [];
            }
        }

        public static function reset(): void {
            self::$initialized = false;
            self::$enabled = false;
            self::$defaultLang = 'en';
            self::$activeLang = 'en';
            self::$languages = [];
            self::$phpStrings = [];
            self::$jsStrings = [];
        }

        public static function t(string $key, string $default, array $vars = []): string {
            $text = (self::$enabled && self::$activeLang !== self::$defaultLang && isset(self::$phpStrings[$key]))
                ? (string)self::$phpStrings[$key]
                : $default;

            if (!empty($vars)) {
                $replacements = [];
                foreach ($vars as $k => $v) {
                    $replacements['{' . $k . '}'] = (string)$v;
                }
                $text = strtr($text, $replacements);
            }

            return $text;
        }

        public static function isEnabled(): bool {
            return self::$enabled;
        }

        public static function getActiveLang(): string {
            return self::$activeLang;
        }

        public static function getDefaultLang(): string {
            return self::$defaultLang;
        }

        public static function getAvailableLanguages(): array {
            return self::$languages;
        }

        public static function getToastUiLocale(): string {
            $meta = self::$languages[self::$activeLang] ?? null;
            if (is_array($meta) && !empty($meta['tuiLocale'])) {
                return (string)$meta['tuiLocale'];
            }
            return 'en-US';
        }

        public static function getJsDictionary(): array {
            if (!self::$enabled || self::$activeLang === self::$defaultLang) {
                return [];
            }
            return self::$jsStrings;
        }
    }
}

namespace {
    if (!function_exists('__')) {
        function __(string $key, string $default, array $vars = []): string {
            return \Qwiki\Core\Translator::t($key, $default, $vars);
        }
    }
}
