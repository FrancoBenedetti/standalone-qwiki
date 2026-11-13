<?php
$indexPhp = file_get_contents(__DIR__ . '/../index.php');
$qwikiCss = file_get_contents(__DIR__ . '/../assets/css/qwiki.css');

// 1. Check that toc-nav-buttons is NOT inside #app-toc
if (preg_match('/<aside[^>]*id="app-toc"[^>]*>.*?toc-nav-buttons.*?<\/aside>/s', $indexPhp)) {
    fwrite(STDERR, "FAIL: .toc-nav-buttons should not be inside #app-toc\n");
    exit(1);
}

// 2. Check that .chapter-pager exists in index.php
if (strpos($indexPhp, 'class="chapter-pager"') === false) {
    fwrite(STDERR, "FAIL: class=\"chapter-pager\" missing from index.php\n");
    exit(1);
}

// 3. Check that .header-chapter-nav exists in index.php
if (strpos($indexPhp, 'header-chapter-nav') === false) {
    fwrite(STDERR, "FAIL: header-chapter-nav missing from index.php\n");
    exit(1);
}

// 4. Check that qwiki.css styles .chapter-pager and hides it in @media print
if (strpos($qwikiCss, '.chapter-pager') === false) {
    fwrite(STDERR, "FAIL: .chapter-pager CSS missing from qwiki.css\n");
    exit(1);
}
if (!preg_match('/@media print\s*\{[^}]*\.chapter-pager/s', $qwikiCss)) {
    fwrite(STDERR, "FAIL: .chapter-pager not hidden in @media print\n");
    exit(1);
}

echo "PASS: Chapter navigation tests passed\n";
