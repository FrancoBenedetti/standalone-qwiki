<?php
// tests/test_latex_rendering.php

require_once __DIR__ . '/../lib/Parsedown.php';

// Load QwikiParsedown from index.php definition
$indexCode = file_get_contents(__DIR__ . '/../index.php');
$startMarker = "if (!class_exists('QwikiParsedown')) {";
$endMarker = '$config = Config::load();';
$p1 = strpos($indexCode, $startMarker);
$p2 = strpos($indexCode, $endMarker);
if ($p1 !== false && $p2 !== false) {
    eval(substr($indexCode, $p1, $p2 - $p1));
}

$passed = 0;
$failed = 0;

function assertTest($condition, $name) {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: {$name}\n";
        $passed++;
    } else {
        echo "❌ FAIL: {$name}\n";
        $failed++;
    }
}

echo "Running LaTeX / KaTeX & Symbol Rendering Tests...\n\n";

$parsedown = new QwikiParsedown();

// ----------------------------------------------------
// 1. Inline Math with Underscores & Asterisks (Shielding from Markdown)
// ----------------------------------------------------
$md1 = 'Formula with subscripts: $x_1 + x_2 = y_k$ and more.';
$html1 = $parsedown->text($md1);

assertTest(strpos($html1, '<em>') === false, 'Inline math does NOT convert underscores to <em> tags');
assertTest(strpos($html1, 'class="katex-inline"') !== false, 'Inline math is wrapped in class="katex-inline"');
assertTest(strpos($html1, 'data-tex="x_1 + x_2 = y_k"') !== false, 'Inline math preserves exact data-tex attribute');

$md2 = 'Multiplication formula: $a * b * c$ in text.';
$html2 = $parsedown->text($md2);
assertTest(strpos($html2, '<em>') === false, 'Inline math does NOT convert asterisks to <em> tags');
assertTest(strpos($html2, 'data-tex="a * b * c"') !== false, 'Inline math with asterisks preserves formula');

// ----------------------------------------------------
// 2. Block Display Math
// ----------------------------------------------------
$mdBlock = <<<MD
# Calculus

$$
\\int_0^\\infty e^{-x^2} dx = \\frac{\\sqrt{\\pi}}{2}
$$

Next paragraph.
MD;

$htmlBlock = $parsedown->text($mdBlock);
assertTest(strpos($htmlBlock, 'class="katex-display-block"') !== false, 'Multi-line display math renders with class="katex-display-block"');
assertTest(strpos($htmlBlock, '\frac{\sqrt{\pi}}{2}') !== false, 'Multi-line display math contains equation body');
assertTest(strpos($htmlBlock, 'data-tex="\int_0^\infty') !== false, 'Multi-line display math has data-tex attribute');

$mdSingleBlock = '$$E = mc^2$$';
$htmlSingleBlock = $parsedown->text($mdSingleBlock);
assertTest(strpos($htmlSingleBlock, 'class="katex-display-block"') !== false, 'Single-line $$ block renders with class="katex-display-block"');
assertTest(strpos($htmlSingleBlock, 'data-tex="E = mc^2"') !== false, 'Single-line $$ block has data-tex attribute');

// ----------------------------------------------------
// 3. Symbol Normalization: Shorthand ($to$, $\to$, $approx$, etc.)
// ----------------------------------------------------
$mdSymbols = 'Step 1 $to$ Step 2 and $\to$ Step 3, with $alpha$ and $approx$ and $le$.';
$htmlSymbols = $parsedown->text($mdSymbols);

assertTest(strpos($htmlSymbols, '→') !== false, '$to$ and $\to$ converted to Unicode arrow →');
assertTest(strpos($htmlSymbols, 'α') !== false, '$alpha$ converted to Unicode α');
assertTest(strpos($htmlSymbols, '≈') !== false, '$approx$ converted to Unicode ≈');
assertTest(strpos($htmlSymbols, '≤') !== false, '$le$ converted to Unicode ≤');
assertTest(strpos($htmlSymbols, 'class="math-symbol-unicode"') !== false, 'Symbols wrapped with math-symbol-unicode class');

// ----------------------------------------------------
// 4. Backslash Symbol Macros in Prose (\to, \approx, \degree)
// ----------------------------------------------------
$mdMacro = 'From point A \to point B at 45\degree with error \approx 0.1.';
$htmlMacro = $parsedown->text($mdMacro);

assertTest(strpos($htmlMacro, '→') !== false, '\to in prose converted to →');
assertTest(strpos($htmlMacro, '°') !== false, '\degree in prose converted to °');
assertTest(strpos($htmlMacro, '≈') !== false, '\approx in prose converted to ≈');

// ----------------------------------------------------
// 5. Currency & Pure Number Protection
// ----------------------------------------------------
$mdCurrency = 'Item costs $50 now and $100 later with tax of $4.99.';
$htmlCurrency = $parsedown->text($mdCurrency);

assertTest(strpos($htmlCurrency, 'katex-inline') === false, 'Currency numbers are not converted to katex-inline');
assertTest(strpos($htmlCurrency, '$50') !== false, 'Currency amount $50 is preserved');
assertTest(strpos($htmlCurrency, '$100') !== false, 'Currency amount $100 is preserved');
assertTest(strpos($htmlCurrency, '$4.99') !== false, 'Currency amount $4.99 is preserved');

echo "\n----------------------------------------------------\n";
echo "Tests Completed: Passed: {$passed}, Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
