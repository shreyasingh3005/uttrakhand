<?php
// Diagnostic script for localhost setup
header('Content-Type: text/plain; charset=utf-8');
echo "abhi — Localhost diagnostic\n";
echo "===========================\n\n";

// PHP / Server info
echo "PHP version: " . PHP_VERSION . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'n/a') . "\n";
echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a') . "\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'n/a') . "\n\n";

// Key files
$root = __DIR__ . '/..';
$files = [
    '.env.php',
    'index.php',
    'process_login.php',
    'includes/config.php',
    'includes/db_connect.php',
];
foreach ($files as $f) {
    $p = realpath($root . '/' . $f) ?: ($root . '/' . $f);
    echo sprintf("%s: %s\n", $f, file_exists($p) ? "FOUND ($p)" : "MISSING ($p)");
}

// Try loading config
echo "\nLoading config...\n";
if (file_exists($root . '/includes/config.php')) {
    require_once $root . '/includes/config.php';
    try {
        $cfg = config();
        echo "Config loaded. DB_HOST={$cfg['DB_HOST']} DB_NAME={$cfg['DB_NAME']} DB_USER={$cfg['DB_USER']}\n";
    } catch (Exception $e) {
        echo "Config load failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "includes/config.php missing, cannot load config.\n";
}

// Test DB connection using config values
echo "\nTesting DB connection...\n";
if (isset($cfg) && is_array($cfg)) {
    try {
        $dsn = 'mysql:host=' . ($cfg['DB_HOST'] ?? 'localhost') . ';dbname=' . ($cfg['DB_NAME'] ?? '') . ';charset=' . ($cfg['DB_CHARSET'] ?? 'utf8mb4');
        $pdo = new PDO($dsn, $cfg['DB_USER'] ?? '', $cfg['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "Connected to database `" . ($cfg['DB_NAME'] ?? '') . "` successfully.\n";
        $row = $pdo->query('SELECT 1')->fetchColumn();
        echo "Simple query OK (SELECT 1 returned: {$row}).\n";
    } catch (PDOException $e) {
        echo "DB connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Skipping DB test because config not loaded.\n";
}

echo "\nEnd of diagnostic.\n";

?>
