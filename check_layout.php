<?php
// Put this file in C:\xampp\htdocs\bangeen_pos\
// Open: http://localhost/bangeen_pos/check_layout.php
// This will show us exactly what layout.php outputs

require_once __DIR__ . '/includes/config.php';

echo "<pre style='background:#fff;padding:20px;font-size:12px'>";
echo "=== CONFIG OK ===\n";
echo "LANG: " . LANG . "\n";
echo "DIR: " . DIR . "\n";

// Read layout.php and show last 50 lines
$layout = file_get_contents(__DIR__ . '/includes/layout.php');
$lines = explode("\n", $layout);
$total = count($lines);
echo "\n=== LAST 20 LINES OF layout.php (total: $total lines) ===\n";
$last20 = array_slice($lines, -20);
foreach ($last20 as $i => $line) {
    echo ($total - 20 + $i + 1) . ": " . htmlspecialchars($line) . "\n";
}

echo "\n=== LAYOUT_END.PHP CONTENT ===\n";
$end = file_get_contents(__DIR__ . '/includes/layout_end.php');
echo htmlspecialchars($end);
echo "</pre>";