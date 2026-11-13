<?php
require_once __DIR__ . '/../lib/Core/QwikiParsedown.php';
$pd = new \Qwiki\Core\QwikiParsedown();

$cases = [
    ["> [!NOTE]\n> Info body", 'markdown-alert-note', 'Note', 'Info body'],
    ["> [!INFO]\n> Info alias", 'markdown-alert-note', 'Info', 'Info alias'],
    ["> [!TIP]\n> Tip body", 'markdown-alert-tip', 'Tip', 'Tip body'],
    ["> [!HINT]\n> Hint alias", 'markdown-alert-tip', 'Hint', 'Hint alias'],
    ["> [!SUCCESS]\n> Success alias", 'markdown-alert-tip', 'Success', 'Success alias'],
    ["> [!IMPORTANT]\n> Important body", 'markdown-alert-important', 'Important', 'Important body'],
    ["> [!WARNING]\n> Warn body", 'markdown-alert-warning', 'Warning', 'Warn body'],
    ["> [!CAUTION]\n> Caution body", 'markdown-alert-warning', 'Caution', 'Caution body'],
    ["> [!ALERT]\n> Alert body", 'markdown-alert-danger', 'Alert', 'Alert body'],
    ["> [!DANGER]\n> Danger body", 'markdown-alert-danger', 'Danger', 'Danger body'],
    ["> [!FAIL]\n> Fail body", 'markdown-alert-danger', 'Fail', 'Fail body'],
    ["> [!NOTE] Custom Title\n> Line 1\n>\n> Line 2\n> - List item", 'markdown-alert-note', 'Custom Title', 'List item'],
    ["> Regular quote", '<blockquote>', null, 'Regular quote'],
];

foreach ($cases as [$md, $expectedClass, $expectedTitle, $expectedBody]) {
    $html = $pd->text($md);
    if (strpos($html, $expectedClass) === false) {
        fwrite(STDERR, "FAIL: Expected '$expectedClass' in:\n$html\n");
        exit(1);
    }
    if ($expectedTitle !== null && strpos($html, $expectedTitle) === false) {
        fwrite(STDERR, "FAIL: Expected title '$expectedTitle' in:\n$html\n");
        exit(1);
    }
    if (strpos($html, $expectedBody) === false) {
        fwrite(STDERR, "FAIL: Expected body '$expectedBody' in:\n$html\n");
        exit(1);
    }
    if ($expectedTitle !== null && preg_match('/\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION|ALERT|INFO|HINT|SUCCESS|DANGER|FAIL)\]/i', $html)) {
        fwrite(STDERR, "FAIL: Raw tag leaked in:\n$html\n");
        exit(1);
    }
}
echo "PASS: All markdown alert tests passed\n";
