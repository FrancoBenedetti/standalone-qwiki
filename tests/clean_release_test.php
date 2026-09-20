<?php
// tests/clean_release_test.php
// Verifies release packaging cleanliness, gitattributes export-ignore rules, and updater hardening.

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

echo "Running Clean Release & Updater Packaging Tests...\n\n";

$repoRoot = dirname(__DIR__);

// 1. Verify .gitattributes exists and has required export-ignore rules
$gitattributesPath = $repoRoot . '/.gitattributes';
assertTest(file_exists($gitattributesPath), '.gitattributes file exists in repository root');

$gitattrContent = file_get_contents($gitattributesPath);
$requiredRules = [
    '/tests' => 'tests folder excluded',
    'tests/**' => 'test contents excluded',
    '/AGENTS.md' => 'AGENTS.md excluded',
    '/demo-reload.php' => 'demo-reload.php excluded',
    '/.gitignore' => '.gitignore excluded',
    '/.gitattributes' => '.gitattributes excluded',
    '/.github' => '.github excluded',
    '/package.json' => 'package.json excluded',
    '/package-lock.json' => 'package-lock.json excluded',
    '/tools/build-release.sh' => 'tools/build-release.sh excluded'
];

foreach ($requiredRules as $rule => $desc) {
    assertTest(strpos($gitattrContent, $rule) !== false && strpos($gitattrContent, 'export-ignore') !== false, ".gitattributes contains export-ignore for {$desc} ({$rule})");
}

// 2. Verify git archive exclusion behavior
$archiveList = shell_exec('cd ' . escapeshellarg($repoRoot) . ' && git archive --worktree-attributes HEAD | tar -t');
$archiveFiles = explode("\n", trim($archiveList));

$forbiddenPatterns = [
    '#^tests/#',
    '#^AGENTS\.md$#',
    '#^demo-reload\.php$#',
    '#^package\.json$#',
    '#^package-lock\.json$#',
    '#^\.gitignore$#',
    '#^\.gitattributes$#',
    '#^tools/build-release\.sh$#'
];

$foundViolations = [];
foreach ($archiveFiles as $file) {
    if (empty($file)) continue;
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $file)) {
            $foundViolations[] = $file;
        }
    }
}

assertTest(empty($foundViolations), 'git archive output does not contain any forbidden/test/dev files (found ' . count($foundViolations) . ' violations)');
if (!empty($foundViolations)) {
    echo "   Violations found: " . implode(', ', array_slice($foundViolations, 0, 5)) . "\n";
}

// Verify core required files ARE present in the archive
$requiredProductionFiles = [
    'index.php',
    'README.md',
    'CHANGELOG.md',
    'api/admin.php',
    'api/search.php',
    'assets/css/qwiki.css',
    'assets/js/app.js',
    'lib/Core/Config.php',
    'lib/Core/Navigation.php',
    'lib/Parsedown.php',
    'demo-data/qwiki-default.json'
];

foreach ($requiredProductionFiles as $reqFile) {
    assertTest(in_array($reqFile, $archiveFiles), "Production archive includes core file: {$reqFile}");
}

// 3. Verify api/admin.php updater hardening
$adminCode = file_get_contents($repoRoot . '/api/admin.php');

assertTest(strpos($adminCode, "'tests/'") !== false, "admin.php install_update excludes 'tests/'");
assertTest(strpos($adminCode, "'AGENTS.md'") !== false, "admin.php install_update excludes 'AGENTS.md'");
assertTest(strpos($adminCode, "'package.json'") !== false, "admin.php install_update excludes 'package.json'");
assertTest(strpos($adminCode, "'package-lock.json'") !== false, "admin.php install_update excludes 'package-lock.json'");
assertTest(strpos($adminCode, "'demo-reload.php'") !== false, "admin.php install_update excludes 'demo-reload.php'");
assertTest(strpos($adminCode, "browser_download_url") !== false, "admin.php check_updates prioritizes release asset zip");

// 4. Verify tools/build-release.sh exists and is executable
$buildScriptPath = $repoRoot . '/tools/build-release.sh';
assertTest(file_exists($buildScriptPath), 'tools/build-release.sh exists');

echo "\n----------------------------------------------------\n";
echo "Clean Release Tests Completed: Passed: {$passed}, Failed: {$failed}\n";

if ($failed > 0) {
    exit(1);
}
