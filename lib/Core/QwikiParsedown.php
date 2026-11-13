<?php
namespace Qwiki\Core;

require_once dirname(__DIR__) . '/Parsedown.php';

class QwikiParsedown extends \Parsedown {
    protected $headingIds = [];

    public static $symbolMap = [
        // Arrows
        'to' => '→',
        '\\to' => '→',
        'rightarrow' => '→',
        '\\rightarrow' => '→',
        'longrightarrow' => '→',
        '\\longrightarrow' => '→',
        'gets' => '←',
        '\\gets' => '←',
        'leftarrow' => '←',
        '\\leftarrow' => '←',
        'longleftarrow' => '←',
        '\\longleftarrow' => '←',
        'uparrow' => '↑',
        '\\uparrow' => '↑',
        'downarrow' => '↓',
        '\\downarrow' => '↓',
        'updownarrow' => '↕',
        '\\updownarrow' => '↕',
        'leftrightarrow' => '↔',
        '\\leftrightarrow' => '↔',
        'longleftrightarrow' => '↔',
        '\\longleftrightarrow' => '↔',
        'implies' => '⇒',
        '\\implies' => '⇒',
        'Rightarrow' => '⇒',
        '\\Rightarrow' => '⇒',
        'Leftarrow' => '⇐',
        '\\Leftarrow' => '⇐',
        'iff' => '⇔',
        '\\iff' => '⇔',
        'Leftrightarrow' => '⇔',
        '\\Leftrightarrow' => '⇔',
        'mapsto' => '↦',
        '\\mapsto' => '↦',

        // Comparison & Relations
        'le' => '≤',
        '\\le' => '≤',
        'leq' => '≤',
        '\\leq' => '≤',
        'ge' => '≥',
        '\\ge' => '≥',
        'geq' => '≥',
        '\\geq' => '≥',
        'ne' => '≠',
        '\\ne' => '≠',
        'neq' => '≠',
        '\\neq' => '≠',
        'approx' => '≈',
        '\\approx' => '≈',
        'equiv' => '≡',
        '\\equiv' => '≡',
        'sim' => '∼',
        '\\sim' => '∼',
        'simeq' => '≃',
        '\\simeq' => '≃',
        'propto' => '∝',
        '\\propto' => '∝',

        // Operators & Sets
        'pm' => '±',
        '\\pm' => '±',
        'mp' => '∓',
        '\\mp' => '∓',
        'times' => '×',
        '\\times' => '×',
        'div' => '÷',
        '\\div' => '÷',
        'cdot' => '·',
        '\\cdot' => '·',
        'circ' => '∘',
        '\\circ' => '∘',
        'bullet' => '•',
        '\\bullet' => '•',
        'infty' => '∞',
        '\\infty' => '∞',
        'in' => '∈',
        '\\in' => '∈',
        'notin' => '∉',
        '\\notin' => '∉',
        'subset' => '⊂',
        '\\subset' => '⊂',
        'supset' => '⊃',
        '\\supset' => '⊃',
        'subseteq' => '⊆',
        '\\subseteq' => '⊆',
        'supseteq' => '⊇',
        '\\supseteq' => '⊇',
        'cup' => '∪',
        '\\cup' => '∪',
        'cap' => '∩',
        '\\cap' => '∩',
        'empty' => '∅',
        '\\empty' => '∅',
        'emptyset' => '∅',
        '\\emptyset' => '∅',
        'forall' => '∀',
        '\\forall' => '∀',
        'exists' => '∃',
        '\\exists' => '∃',
        'nexists' => '∄',
        '\\nexists' => '∄',
        'partial' => '∂',
        '\\partial' => '∂',
        'nabla' => '∇',
        '\\nabla' => '∇',
        'sum' => '∑',
        '\\sum' => '∑',
        'prod' => '∏',
        '\\prod' => '∏',
        'int' => '∫',
        '\\int' => '∫',
        'sqrt' => '√',
        '\\sqrt' => '√',
        'therefore' => '∴',
        '\\therefore' => '∴',
        'because' => '∵',
        '\\because' => '∵',

        // Greek Alphabet (lowercase)
        'alpha' => 'α',
        '\\alpha' => 'α',
        'beta' => 'β',
        '\\beta' => 'β',
        'gamma' => 'γ',
        '\\gamma' => 'γ',
        'delta' => 'δ',
        '\\delta' => 'δ',
        'epsilon' => 'ε',
        '\\epsilon' => 'ε',
        'zeta' => 'ζ',
        '\\zeta' => 'ζ',
        'eta' => 'η',
        '\\eta' => 'η',
        'theta' => 'θ',
        '\\theta' => 'θ',
        'iota' => 'ι',
        '\\iota' => 'ι',
        'kappa' => 'κ',
        '\\kappa' => 'κ',
        'lambda' => 'λ',
        '\\lambda' => 'λ',
        'mu' => 'μ',
        '\\mu' => 'μ',
        'nu' => 'ν',
        '\\nu' => 'nu',
        'xi' => 'ξ',
        '\\xi' => 'ξ',
        'pi' => 'π',
        '\\pi' => 'π',
        'rho' => 'ρ',
        '\\rho' => 'ρ',
        'sigma' => 'σ',
        '\\sigma' => 'σ',
        'tau' => 'τ',
        '\\tau' => 'τ',
        'upsilon' => 'υ',
        '\\upsilon' => 'υ',
        'phi' => 'φ',
        '\\phi' => 'φ',
        'chi' => 'χ',
        '\\chi' => 'χ',
        'psi' => 'ψ',
        '\\psi' => 'ψ',
        'omega' => 'ω',
        '\\omega' => 'ω',

        // Greek Alphabet (uppercase)
        'Gamma' => 'Γ',
        '\\Gamma' => 'Γ',
        'Delta' => 'Δ',
        '\\Delta' => 'Δ',
        'Theta' => 'Θ',
        '\\Theta' => 'Θ',
        'Lambda' => 'Λ',
        '\\Lambda' => 'Λ',
        'Xi' => 'Ξ',
        '\\Xi' => 'Ξ',
        'Pi' => 'Π',
        '\\Pi' => 'Π',
        'Sigma' => 'Σ',
        '\\Sigma' => 'Σ',
        'Upsilon' => 'Υ',
        '\\Upsilon' => 'Υ',
        'Phi' => 'Φ',
        '\\Phi' => 'Φ',
        'Psi' => 'Ψ',
        '\\Psi' => 'Ψ',
        'Omega' => 'Ω',
        '\\Omega' => 'Ω',

        // Miscellaneous
        'checkmark' => '✓',
        '\\checkmark' => '✓',
        'dag' => '†',
        '\\dag' => '†',
        'ddag' => '‡',
        '\\ddag' => '‡',
        'star' => '★',
        '\\star' => '★',
        'degree' => '°',
        '\\degree' => '°',
    ];

    public function __construct() {
        $this->BlockTypes['$'] = ['Math'];
        $this->InlineTypes['$'] = ['Math'];
        $this->inlineMarkerList .= '$';
        if (isset($this->InlineTypes['\\'])) {
            array_unshift($this->InlineTypes['\\'], 'MathSymbol');
        } else {
            $this->InlineTypes['\\'] = ['MathSymbol'];
        }
    }

    public function text($text) {
        $this->headingIds = [];
        return parent::text($text);
    }

    protected function slugify($text) {
        $text = strip_tags($text);
        $text = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $text);
        $text = preg_replace('/[*_`~#]/', '', $text);
        $slug = mb_strtolower($text, 'UTF-8');
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/[^\w\-]+/u', '', $slug);
        $slug = preg_replace('/--+/', '-', $slug);
        return trim($slug, '-');
    }

    protected function generateUniqueHeadingId($text) {
        $baseId = $this->slugify($text);
        if ($baseId === '') {
            $baseId = 'section';
        }
        $id = $baseId;
        $counter = 1;
        while (isset($this->headingIds[$id])) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }
        $this->headingIds[$id] = true;
        return $id;
    }

    protected function blockHeader($Line) {
        $Block = parent::blockHeader($Line);
        if (isset($Block['element'])) {
            $arg = $Block['element']['handler']['argument'] ?? '';
            $Block['element']['attributes']['id'] = $this->generateUniqueHeadingId($arg);
        }
        return $Block;
    }

    protected function blockSetextHeader($Line, ?array $Block = null) {
        $Block = parent::blockSetextHeader($Line, $Block);
        if (isset($Block['element'])) {
            $arg = $Block['element']['handler']['argument'] ?? '';
            $Block['element']['attributes']['id'] = $this->generateUniqueHeadingId($arg);
        }
        return $Block;
    }

    protected function blockMath($Line) {
        $text = trim($Line['text']);
        if (strpos($text, '$$') !== 0) {
            return;
        }

        // Single line display math: $$ formula $$
        if (strlen($text) > 4 && substr($text, -2) === '$$') {
            $formula = trim(substr($text, 2, -2));
            return [
                'closed' => true,
                'element' => [
                    'name' => 'div',
                    'text' => '$$' . $formula . '$$',
                    'attributes' => [
                        'class' => 'katex-display-block',
                        'data-tex' => $formula,
                    ],
                ],
            ];
        }

        // Multi-line block math starting with $$
        $initialContent = trim(substr($text, 2));
        return [
            'char' => '$',
            'element' => [
                'name' => 'div',
                'text' => $initialContent,
                'attributes' => [
                    'class' => 'katex-display-block',
                ],
            ],
            'closed' => false,
        ];
    }

    protected function blockMathContinue($Line, $Block) {
        if (!empty($Block['closed'])) {
            return;
        }

        $text = trim($Line['text']);
        if (strlen($text) >= 2 && substr($text, -2) === '$$') {
            $closingContent = trim(substr($text, 0, -2));
            if ($closingContent !== '') {
                $Block['element']['text'] = ($Block['element']['text'] !== '' ? $Block['element']['text'] . "\n" : '') . $closingContent;
            }
            $Block['element']['attributes']['data-tex'] = trim($Block['element']['text']);
            $Block['element']['text'] = '$$' . $Block['element']['attributes']['data-tex'] . '$$';
            $Block['closed'] = true;
            return $Block;
        }

        $Block['element']['text'] = ($Block['element']['text'] !== '' ? $Block['element']['text'] . "\n" : '') . $Line['text'];
        return $Block;
    }

    protected function blockMathComplete($Block) {
        if (!isset($Block['element']['attributes']['data-tex'])) {
            $tex = trim($Block['element']['text']);
            if (substr($tex, 0, 2) === '$$' && substr($tex, -2) === '$$') {
                $tex = trim(substr($tex, 2, -2));
            }
            $Block['element']['attributes']['data-tex'] = $tex;
            $Block['element']['text'] = '$$' . $tex . '$$';
        }
        return $Block;
    }

    protected function inlineMath($Excerpt) {
        $text = $Excerpt['text'];
        if ($text[0] !== '$') {
            return;
        }

        // 1. Display math inline: $$...$$
        if (substr($text, 0, 2) === '$$') {
            if (preg_match('/^\$\$([^\$\n]+?)\$\$/', $text, $matches)) {
                $formula = trim($matches[1]);
                return [
                    'extent' => strlen($matches[0]),
                    'element' => [
                        'name' => 'span',
                        'text' => '$$' . $formula . '$$',
                        'attributes' => [
                            'class' => 'katex-display-inline',
                            'data-tex' => $formula,
                        ],
                    ],
                ];
            }
            return;
        }

        // 2. Inline math: $...$
        if (preg_match('/^\$([^\$\n\s](?:[^\$\n]*?[^\$\n\s])?)\$/', $text, $matches)) {
            $content = $matches[1];

            // Currency check: pure numbers (e.g. $50, $1,000, $4.99)
            if (preg_match('/^\d+(?:,\d{3})*(?:\.\d+)?$/', $content)) {
                return;
            }

            // Check symbol shorthand map: e.g. $to$, $\to$, $alpha$
            $lookup = ltrim($content, '\\');
            if (isset(self::$symbolMap[$content])) {
                $sym = self::$symbolMap[$content];
            } elseif (isset(self::$symbolMap['\\' . $lookup])) {
                $sym = self::$symbolMap['\\' . $lookup];
            } elseif (isset(self::$symbolMap[$lookup])) {
                $sym = self::$symbolMap[$lookup];
            } else {
                $sym = null;
            }

            if ($sym !== null) {
                return [
                    'extent' => strlen($matches[0]),
                    'element' => [
                        'name' => 'span',
                        'text' => $sym,
                        'attributes' => [
                            'class' => 'math-symbol-unicode',
                            'data-symbol' => $content,
                        ],
                    ],
                ];
            }

            return [
                'extent' => strlen($matches[0]),
                'element' => [
                    'name' => 'span',
                    'text' => '$' . $content . '$',
                    'attributes' => [
                        'class' => 'katex-inline',
                        'data-tex' => $content,
                    ],
                ],
            ];
        }
    }

    protected function inlineMathSymbol($Excerpt) {
        if (preg_match('/^\\\\([a-zA-Z]+)/', $Excerpt['text'], $matches)) {
            $macro = '\\' . $matches[1];
            if (isset(self::$symbolMap[$macro])) {
                return [
                    'extent' => strlen($matches[0]),
                    'element' => [
                        'name' => 'span',
                        'text' => self::$symbolMap[$macro],
                        'attributes' => [
                            'class' => 'math-symbol-unicode',
                            'data-symbol' => $macro,
                        ],
                    ],
                ];
            }
        }
    }

    protected static $alertTypeMap = [
        'note'      => ['cssClass' => 'note',      'defaultLabel' => 'Note'],
        'info'      => ['cssClass' => 'note',      'defaultLabel' => 'Info'],
        'tip'       => ['cssClass' => 'tip',       'defaultLabel' => 'Tip'],
        'hint'      => ['cssClass' => 'tip',       'defaultLabel' => 'Hint'],
        'success'   => ['cssClass' => 'tip',       'defaultLabel' => 'Success'],
        'important' => ['cssClass' => 'important', 'defaultLabel' => 'Important'],
        'warning'   => ['cssClass' => 'warning',   'defaultLabel' => 'Warning'],
        'caution'   => ['cssClass' => 'warning',   'defaultLabel' => 'Caution'],
        'danger'    => ['cssClass' => 'danger',    'defaultLabel' => 'Danger'],
        'alert'     => ['cssClass' => 'danger',    'defaultLabel' => 'Alert'],
        'fail'      => ['cssClass' => 'danger',    'defaultLabel' => 'Fail'],
    ];

    protected function getAlertSvgIcon(string $cssClass): string {
        $icons = [
            'note' => '<svg class="markdown-alert-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8Zm8-6.5a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13ZM6.5 7.75A.75.75 0 0 1 7.25 7h1a.75.75 0 0 1 .75.75v2.75h.25a.75.75 0 0 1 0 1.5h-2a.75.75 0 0 1 0-1.5h.25v-2h-.25a.75.75 0 0 1-.75-.75ZM8 6a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path></svg>',
            'tip' => '<svg class="markdown-alert-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M8 1.5c-2.363 0-4 1.69-4 3.75 0 .984.424 1.625.984 2.304l.214.253c.223.264.47.556.673.848.284.411.537.896.621 1.49a.75.75 0 0 1-1.484.211c-.04-.282-.163-.547-.37-.847a8.456 8.456 0 0 0-.542-.68c-.084-.1-.173-.205-.268-.32C3.201 7.75 2.5 6.766 2.5 5.25 2.5 2.31 4.863 0 8 0s5.5 2.31 5.5 5.25c0 1.516-.701 2.5-1.328 3.259-.095.115-.184.22-.268.319-.207.245-.383.453-.541.681-.208.3-.33.565-.37.847a.751.751 0 0 1-1.485-.212c.084-.593.337-1.078.621-1.489.203-.292.45-.584.673-.848.075-.088.147-.173.213-.253.561-.679.985-1.32.985-2.304 0-2.06-1.637-3.75-4-3.75ZM5.75 12h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5ZM6 15.25a.75.75 0 0 1 .75-.75h2.5a.75.75 0 0 1 0 1.5h-2.5a.75.75 0 0 1-.75-.75Z"></path></svg>',
            'important' => '<svg class="markdown-alert-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M0 1.75C0 .784.784 0 1.75 0h12.5C15.216 0 16 .784 16 1.75v9.5A1.75 1.75 0 0 1 14.25 13H8.06l-2.573 2.573A1.458 1.458 0 0 1 3 14.543V13H1.75A1.75 1.75 0 0 1 0 11.25Zm1.75-.25a.25.25 0 0 0-.25.25v9.5c0 .138.112.25.25.25h2a.75.75 0 0 1 .75.75v2.19l2.72-2.72a.749.749 0 0 1 .53-.22h6.5a.25.25 0 0 0 .25-.25v-9.5a.25.25 0 0 0-.25-.25Zm7 2.25v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 9a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"></path></svg>',
            'warning' => '<svg class="markdown-alert-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M6.457 1.047c.659-1.234 2.427-1.234 3.086 0l6.082 11.378A1.75 1.75 0 0 1 14.082 15H1.918a1.75 1.75 0 0 1-1.543-2.575Zm1.763.707a.25.25 0 0 0-.44 0L1.698 13.132a.25.25 0 0 0 .22.368h12.164a.25.25 0 0 0 .22-.368Zm.53 3.996v2.5a.75.75 0 0 1-1.5 0v-2.5a.75.75 0 0 1 1.5 0ZM9 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z"></path></svg>',
            'danger' => '<svg class="markdown-alert-icon" viewBox="0 0 16 16" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M4.47.22A.749.749 0 0 1 5 0h6c.199 0 .389.079.53.22l4.25 4.25c.141.14.22.331.22.53v6a.749.749 0 0 1-.22.53l-4.25 4.25A.749.749 0 0 1 11 16H5a.749.749 0 0 1-.53-.22L.22 11.53A.749.749 0 0 1 0 11V5c0-.199.079-.389.22-.53Zm.84 1.28L1.5 5.31v5.38l3.81 3.81h5.38l3.81-3.81V5.31L10.69 1.5ZM8 4a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"></path></svg>',
        ];
        return $icons[$cssClass] ?? $icons['note'];
    }

    protected function blockQuote($Line) {
        $Block = parent::blockQuote($Line);
        if (!$Block) {
            return;
        }
        $firstLine = trim($Block['element']['handler']['argument'][0] ?? '');
        if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION|INFO|HINT|DANGER|ALERT|SUCCESS|FAIL)\](?:\s+(.*))?$/i', $firstLine, $matches)) {
            $typeKey = strtolower($matches[1]);
            $meta = self::$alertTypeMap[$typeKey] ?? self::$alertTypeMap['note'];
            $customLabel = isset($matches[2]) ? trim($matches[2]) : '';
            $Block['alert'] = [
                'cssClass' => $meta['cssClass'],
                'label' => $customLabel !== '' ? $customLabel : $meta['defaultLabel'],
            ];
            $Block['element']['handler']['argument'] = [];
        }
        return $Block;
    }

    protected function blockQuoteComplete(array $Block) {
        if (!isset($Block['alert'])) {
            return $Block;
        }
        $alert = $Block['alert'];
        $bodyLines = $Block['element']['handler']['argument'] ?? [];
        $bodyElements = $this->linesElements($bodyLines);

        $Block['element'] = [
            'name' => 'div',
            'attributes' => [
                'class' => 'markdown-alert markdown-alert-' . $alert['cssClass'],
            ],
            'elements' => [
                [
                    'name' => 'div',
                    'attributes' => [
                        'class' => 'markdown-alert-title',
                    ],
                    'elements' => [
                        [
                            'rawHtml' => $this->getAlertSvgIcon($alert['cssClass']),
                            'allowRawHtmlInSafeMode' => true,
                        ],
                        [
                            'name' => 'span',
                            'text' => $alert['label'],
                        ],
                    ],
                ],
                [
                    'name' => 'div',
                    'attributes' => [
                        'class' => 'markdown-alert-content',
                    ],
                    'elements' => $bodyElements,
                ],
            ],
        ];
        return $Block;
    }

    protected function inlineLink($Excerpt) {
        $Inline = parent::inlineLink($Excerpt);
        if (!isset($Inline)) {
            return;
        }

        $href = $Inline['element']['attributes']['href'] ?? '';
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $parsedUrl = parse_url($href);
        $linkHost = $parsedUrl['host'] ?? '';
        
        if ($linkHost && $linkHost !== $currentHost) {
            $Inline['element']['attributes']['target'] = '_blank';
            $Inline['element']['attributes']['rel'] = 'noopener noreferrer';
        }
        
        return $Inline;
    }
}

// Backwards-compatibility alias for non-namespaced global references
if (!class_exists('QwikiParsedown', false)) {
    class_alias('\Qwiki\Core\QwikiParsedown', 'QwikiParsedown');
}
