<?php
/**
 * Agentic Visual & Chart Generator Backend Handler
 */
use Qwiki\Core\Auth;
use Qwiki\Core\Config;

if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    return;
}

$prompt = trim($_POST['prompt'] ?? '');
$type   = trim($_POST['type'] ?? 'chart_bar');
$data   = trim($_POST['data'] ?? '');

if (empty($prompt)) {
    echo json_encode(['success' => false, 'error' => 'Prompt/directive is required']);
    return;
}

$baseDir = Config::getBaseDir();
$uploadsDir = $baseDir . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
}

$timestamp = time();
$slug = Config::makeSlug($prompt);
if (strlen($slug) > 30) {
    $slug = substr($slug, 0, 30);
}
if (empty($slug)) {
    $slug = 'visual';
}

$filename = 'generated-' . $slug . '-' . $timestamp . '.svg';
$filepath = $uploadsDir . '/' . $filename;
$relativeUrl = 'uploads/' . $filename;

// Check if user just pasted raw Mermaid code
$mermaidKeywords = ['erdiagram', 'flowchart', 'graph', 'sequencediagram', 'classdiagram', 'statediagram-v2', 'statediagram', 'pie', 'gantt', 'gitgraph', 'journey', 'mindmap', 'quadrantchart', 'xychart', 'timeline', 'sankey-beta', 'architecture'];

$isMermaid = false;
$mermaidCode = '';

// Helper to check if a string starts with any mermaid keyword
function getMermaidKeyword($text, $keywords) {
    $lowerText = strtolower(trim($text));
    foreach ($keywords as $kw) {
        if (strpos($lowerText, $kw) === 0) {
            // Check if it's followed by a space, newline, or a character (if newlines were stripped by input type=text)
            return $kw;
        }
    }
    return false;
}

if (($kw = getMermaidKeyword($prompt, $mermaidKeywords)) !== false) {
    $isMermaid = true;
    // If newlines were stripped, it might be 'erDiagramCUSTOMER'
    // Let's ensure there's a space after the keyword if it was squished
    $promptFixed = preg_replace('/^(' . preg_quote($kw, '/') . ')([A-Za-z0-9_])/i', '$1 $2', trim($prompt));
    $mermaidCode = trim($promptFixed . "\n" . $data);
} elseif (($kw = getMermaidKeyword($data, $mermaidKeywords)) !== false) {
    $isMermaid = true;
    $titleLine = "title: " . htmlspecialchars($prompt, ENT_NOQUOTES);
    $mermaidCode = "---\n{$titleLine}\n---\n" . trim($data);
}

if ($isMermaid) {
    // Treat as raw Mermaid block
    $markdown = "```mermaid\n" . $mermaidCode . "\n```";
    $previewHtml = "<div class='mermaid'>" . htmlspecialchars($mermaidCode) . "</div>";
    
    echo json_encode([
        'success' => true,
        'isMermaid' => true,
        'markdown' => $markdown,
        'previewHtml' => $previewHtml
    ]);
    return;
}

// Helper to parse key-value data or comma numbers
function parse_visual_data($prompt, $data) {
    $labels = [];
    $values = [];
    $combined = trim($prompt . " " . $data);

    // 1. Try parsing JSON
    $trimData = trim($data);
    if ((strpos($trimData, '{') === 0) || (strpos($trimData, '[') === 0)) {
        $json = json_decode($trimData, true);
        if (is_array($json)) {
            // e.g. {"Jan": 100, "Feb": 200}
            foreach ($json as $k => $v) {
                if (is_numeric($v)) {
                    $labels[] = $k;
                    $values[] = floatval($v);
                } elseif (is_array($v) && count($v) >= 2 && is_numeric($v[1])) {
                    // e.g. [["Jan", 100], ["Feb", 200]]
                    $labels[] = $v[0];
                    $values[] = floatval($v[1]);
                }
            }
            if (!empty($labels) && !empty($values)) return [$labels, $values];
        }
    }

    // 2. Check for explicit Labels: / Data: lines
    if (!empty($data)) {
        $lines = explode("\n", $data);
        $tempLabels = [];
        $tempValues = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (stripos($line, 'labels:') === 0) {
                $tempLabels = array_map('trim', explode(',', substr($line, 7)));
            } elseif (stripos($line, 'data:') === 0 || stripos($line, 'values:') === 0) {
                $parts = explode(':', $line, 2);
                $tempValues = array_map('floatval', array_map('trim', explode(',', $parts[1])));
            }
        }
        if (!empty($tempLabels) && !empty($tempValues)) {
            return [$tempLabels, $tempValues];
        }
        
        // 3. Try parsing 2-row CSV where row 1 = labels, row 2 = values
        if (count($lines) >= 2) {
            $firstRow = array_map('trim', explode(',', $lines[0]));
            $secondRow = array_map('trim', explode(',', $lines[1]));
            $isNumeric = true;
            foreach ($secondRow as $val) {
                if (!is_numeric($val) && !empty($val)) $isNumeric = false;
            }
            if ($isNumeric && count($firstRow) > 0 && count($firstRow) === count($secondRow)) {
                return [$firstRow, array_map('floatval', $secondRow)];
            }
        }
    }

    // 4. Try parsing Key: Value or Key=Value pairs anywhere
    if (preg_match_all('/([A-Za-z0-9\s_-]+)\s*[:=]\s*([0-9.]+)/', $combined, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $labels[] = trim($m[1]);
            $values[] = floatval($m[2]);
        }
        if (!empty($labels) && !empty($values)) return [$labels, $values];
    }
    
    // 5. Try parsing CSV rows like "Jan, 100"
    if (!empty($data)) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 2 && is_numeric(trim(end($parts)))) {
                $labels[] = trim($parts[0]);
                $values[] = floatval(trim(end($parts)));
            }
        }
        if (!empty($labels) && !empty($values)) return [$labels, $values];
    }

    // No valid data found
    return [[], []];
}

$svg = '';

if ($type === 'chart_bar') {
    list($labels, $values) = parse_visual_data($prompt, $data);
    if (empty($labels) || empty($values)) {
        echo json_encode(['success' => false, 'error' => 'No numeric data found. Please provide data points (e.g. Q1: 40, Q2: 50)']);
        return;
    }
    $maxVal = max($values) > 0 ? max($values) : 1;
    $width = max(600, count($labels) * 110 + 100);
    $height = 360;
    $chartHeight = 220;
    $chartBottom = 280;
    $barWidth = min(60, intval(($width - 150) / count($labels) * 0.65));

    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}' width='100%' height='auto' style='background: #1e1e24; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; border-radius: 8px;'>\n";
    // Title
    $svg .= "  <text x='" . ($width / 2) . "' y='40' fill='#ffffff' font-size='18' font-weight='600' text-anchor='middle'>" . htmlspecialchars($prompt) . "</text>\n";
    // Grid Lines
    for ($g = 0; $g <= 4; $g++) {
        $gy = $chartBottom - ($g * ($chartHeight / 4));
        $valLabel = round($maxVal * ($g / 4), 1);
        $svg .= "  <line x1='60' y1='{$gy}' x2='" . ($width - 40) . "' y2='{$gy}' stroke='#33333f' stroke-dasharray='4' />\n";
        $svg .= "  <text x='50' y='" . ($gy + 4) . "' fill='#888899' font-size='11' text-anchor='end'>{$valLabel}</text>\n";
    }

    // Bars
    $spacing = ($width - 120) / count($labels);
    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];

    foreach ($labels as $idx => $lbl) {
        $val = $values[$idx] ?? 0;
        $barH = ($val / $maxVal) * $chartHeight;
        $bx = 80 + ($idx * $spacing);
        $by = $chartBottom - $barH;
        $color = $colors[$idx % count($colors)];

        $svg .= "  <rect x='{$bx}' y='{$by}' width='{$barWidth}' height='{$barH}' rx='4' fill='{$color}' />\n";
        $svg .= "  <text x='" . ($bx + $barWidth / 2) . "' y='" . ($by - 8) . "' fill='#ffffff' font-size='12' font-weight='bold' text-anchor='middle'>{$val}</text>\n";
        $svg .= "  <text x='" . ($bx + $barWidth / 2) . "' y='" . ($chartBottom + 22) . "' fill='#aaaaaa' font-size='12' text-anchor='middle'>" . htmlspecialchars($lbl) . "</text>\n";
    }
    $svg .= "</svg>";

} elseif ($type === 'chart_line') {
    list($labels, $values) = parse_visual_data($prompt, $data);
    if (empty($labels) || empty($values)) {
        echo json_encode(['success' => false, 'error' => 'No numeric data found. Please provide data points (e.g. Q1: 40, Q2: 50)']);
        return;
    }
    $maxVal = max($values) > 0 ? max($values) : 1;
    $width = max(600, count($labels) * 110 + 100);
    $height = 360;
    $chartHeight = 220;
    $chartBottom = 280;

    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}' width='100%' height='auto' style='background: #1e1e24; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; border-radius: 8px;'>\n";
    $svg .= "  <text x='" . ($width / 2) . "' y='40' fill='#ffffff' font-size='18' font-weight='600' text-anchor='middle'>" . htmlspecialchars($prompt) . "</text>\n";

    for ($g = 0; $g <= 4; $g++) {
        $gy = $chartBottom - ($g * ($chartHeight / 4));
        $valLabel = round($maxVal * ($g / 4), 1);
        $svg .= "  <line x1='60' y1='{$gy}' x2='" . ($width - 40) . "' y2='{$gy}' stroke='#33333f' stroke-dasharray='4' />\n";
        $svg .= "  <text x='50' y='" . ($gy + 4) . "' fill='#888899' font-size='11' text-anchor='end'>{$valLabel}</text>\n";
    }

    $spacing = ($width - 140) / max(1, count($labels) - 1);
    $points = [];
    foreach ($labels as $idx => $lbl) {
        $val = $values[$idx] ?? 0;
        $px = 70 + ($idx * $spacing);
        $py = $chartBottom - (($val / $maxVal) * $chartHeight);
        $points[] = "{$px},{$py}";
    }

    $ptsStr = implode(' ', $points);
    $svg .= "  <polyline fill='none' stroke='#3b82f6' stroke-width='4' stroke-linecap='round' stroke-linejoin='round' points='{$ptsStr}' />\n";

    foreach ($labels as $idx => $lbl) {
        $val = $values[$idx] ?? 0;
        $px = 70 + ($idx * $spacing);
        $py = $chartBottom - (($val / $maxVal) * $chartHeight);

        $svg .= "  <circle cx='{$px}' cy='{$py}' r='6' fill='#3b82f6' stroke='#ffffff' stroke-width='2' />\n";
        $svg .= "  <text x='{$px}' y='" . ($py - 12) . "' fill='#ffffff' font-size='12' font-weight='bold' text-anchor='middle'>{$val}</text>\n";
        $svg .= "  <text x='{$px}' y='" . ($chartBottom + 22) . "' fill='#aaaaaa' font-size='12' text-anchor='middle'>" . htmlspecialchars($lbl) . "</text>\n";
    }
    $svg .= "</svg>";

} elseif ($type === 'chart_pie') {
    list($labels, $values) = parse_visual_data($prompt, $data);
    if (empty($labels) || empty($values)) {
        echo json_encode(['success' => false, 'error' => 'No numeric data found. Please provide data points (e.g. Q1: 40, Q2: 50)']);
        return;
    }
    $total = array_sum($values);
    if ($total <= 0) $total = 1;
    $width = 600;
    $height = 360;
    $cx = 200;
    $cy = 190;
    $r = 105;
    $innerR = 48;

    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}' width='100%' height='auto' style='background: #1e1e24; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; border-radius: 8px;'>\n";
    $svg .= "  <text x='" . ($width / 2) . "' y='40' fill='#ffffff' font-size='18' font-weight='600' text-anchor='middle'>" . htmlspecialchars($prompt) . "</text>\n";

    $currentAngle = 0;
    foreach ($values as $idx => $val) {
        $pct = $val / $total;
        $angle = $pct * 360;
        $startAngle = $currentAngle;
        $endAngle = $currentAngle + $angle;
        $currentAngle = $endAngle;

        $x1 = $cx + $r * cos(deg2rad($startAngle - 90));
        $y1 = $cy + $r * sin(deg2rad($startAngle - 90));
        $x2 = $cx + $r * cos(deg2rad($endAngle - 90));
        $y2 = $cy + $r * sin(deg2rad($endAngle - 90));

        $ix1 = $cx + $innerR * cos(deg2rad($endAngle - 90));
        $iy1 = $cy + $innerR * sin(deg2rad($endAngle - 90));
        $ix2 = $cx + $innerR * cos(deg2rad($startAngle - 90));
        $iy2 = $cy + $innerR * sin(deg2rad($startAngle - 90));

        $largeArc = ($angle > 180) ? 1 : 0;
        $color = $colors[$idx % count($colors)];

        if (count($values) === 1 || $angle >= 359.9) {
            $svg .= "  <circle cx='{$cx}' cy='{$cy}' r='{$r}' fill='{$color}' />\n";
            $svg .= "  <circle cx='{$cx}' cy='{$cy}' r='{$innerR}' fill='#1e1e24' />\n";
        } else {
            $pathD = "M {$x1} {$y1} A {$r} {$r} 0 {$largeArc} 1 {$x2} {$y2} L {$ix1} {$iy1} A {$innerR} {$innerR} 0 {$largeArc} 0 {$ix2} {$iy2} Z";
            $svg .= "  <path d='{$pathD}' fill='{$color}' stroke='#1e1e24' stroke-width='2' />\n";
        }

        $lbl = $labels[$idx] ?? "Item " . ($idx + 1);
        $pctFormatted = round($pct * 100, 1) . '%';
        $ly = 100 + ($idx * 28);
        if ($ly < $height - 20) {
            $svg .= "  <rect x='370' y='{$ly}' width='14' height='14' rx='3' fill='{$color}' />\n";
            $svg .= "  <text x='395' y='" . ($ly + 12) . "' fill='#e2e8f0' font-size='13'>" . htmlspecialchars($lbl) . " (" . $val . " - " . $pctFormatted . ")</text>\n";
        }
    }
    $svg .= "</svg>";

} elseif ($type === 'diagram_flow') {
    $steps = !empty($data) ? array_map('trim', explode("\n", $data)) : [];
    if (empty($steps)) {
        if (strpos($prompt, '->') !== false) {
            $steps = array_map('trim', explode('->', $prompt));
        } else {
            $steps = ['Client Request', 'Authentication Guard', 'Core Router', 'Extension Dispatcher', 'Response Render'];
        }
    }
    $steps = array_filter($steps);
    $count = count($steps);
    $nodeW = 160;
    $nodeH = 60;
    $spacing = 60;
    $width = max(650, ($count * ($nodeW + $spacing)) + 40);
    $height = 240;

    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}' width='100%' height='auto' style='background: #1e1e24; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; border-radius: 8px;'>\n";
    $svg .= "  <defs>\n";
    $svg .= "    <marker id='arrow' viewBox='0 0 10 10' refX='6' refY='5' markerWidth='6' markerHeight='6' orient='auto-start-reverse'>\n";
    $svg .= "      <path d='M 0 1 L 10 5 L 0 9 z' fill='#3b82f6' />\n";
    $svg .= "    </marker>\n";
    $svg .= "  </defs>\n";
    $svg .= "  <text x='" . ($width / 2) . "' y='40' fill='#ffffff' font-size='18' font-weight='600' text-anchor='middle'>" . htmlspecialchars($prompt) . "</text>\n";

    $i = 0;
    foreach ($steps as $step) {
        $x = 30 + ($i * ($nodeW + $spacing));
        $y = 100;
        $svg .= "  <rect x='{$x}' y='{$y}' width='{$nodeW}' height='{$nodeH}' rx='8' fill='#2a2a35' stroke='#3b82f6' stroke-width='2' />\n";
        $svg .= "  <text x='" . ($x + $nodeW / 2) . "' y='" . ($y + 35) . "' fill='#ffffff' font-size='13' font-weight='500' text-anchor='middle'>" . htmlspecialchars($step) . "</text>\n";

        if ($i < $count - 1) {
            $x1 = $x + $nodeW;
            $x2 = $x1 + $spacing;
            $my = $y + ($nodeH / 2);
            $svg .= "  <line x1='{$x1}' y1='{$my}' x2='{$x2}' y2='{$my}' stroke='#3b82f6' stroke-width='2' marker-end='url(#arrow)' />\n";
        }
        $i++;
    }
    $svg .= "</svg>";

} else {
    // Status Badge / Pill
    $text = htmlspecialchars($prompt);
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 450 120' width='100%' height='auto' style='background: #1e1e24; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif; border-radius: 8px;'>\n";
    $svg .= "  <rect x='25' y='30' width='400' height='60' rx='30' fill='#10b981' fill-opacity='0.15' stroke='#10b981' stroke-width='2' />\n";
    $svg .= "  <circle cx='60' cy='60' r='10' fill='#10b981' />\n";
    $svg .= "  <text x='85' y='66' fill='#ffffff' font-size='16' font-weight='600'>{$text}</text>\n";
    $svg .= "</svg>";
}

if (file_put_contents($filepath, $svg) === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to save generated visual to uploads/']);
    return;
}

$markdown = '![' . addslashes($prompt) . '](' . $relativeUrl . ')';

echo json_encode([
    'success' => true,
    'url' => $relativeUrl,
    'markdown' => $markdown,
    'filename' => $filename,
    'previewSvg' => $svg
]);
return;
