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
