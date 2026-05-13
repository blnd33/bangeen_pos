<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Step 1: PHP is working ✅</h2>";

// Test DB
define('DB_HOST',    'localhost');
define('DB_NAME',    'bangeen_pos');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<h2>Step 2: Database connected ✅</h2>";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Tables found: " . implode(', ', $tables) . "</h3>";

    $cats = $pdo->query("SELECT * FROM categories")->fetchAll();
    echo "<h3>Categories count: " . count($cats) . "</h3>";
    foreach($cats as $c) {
        echo "<p>- " . htmlspecialchars($c['name_ar']) . " / " . htmlspecialchars($c['name_en']) . "</p>";
    }

} catch(Exception $e) {
    echo "<h2 style='color:red'>Step 2: DB Error ❌</h2>";
    echo "<p style='color:red'>" . $e->getMessage() . "</p>";
}

echo "<h2>Step 3: Config include test</h2>";
try {
    require_once __DIR__ . '/includes/config.php';
    echo "<p>Config loaded ✅</p>";
    echo "<p>LANG = " . LANG . "</p>";
    echo "<p>DB connection via class: ";
    $db = DB::get();
    echo "OK ✅</p>";
} catch(Exception $e) {
    echo "<p style='color:red'>Config error: " . $e->getMessage() . "</p>";
}
