<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}
require_once __DIR__ . '/../includes/db_connect.php';

$sqlFile = __DIR__ . '/../database/hotel_manager_complete.sql';
if (!file_exists($sqlFile)) {
    echo "SQL file not found: $sqlFile\n";
    exit(1);
}

$content = file_get_contents($sqlFile);
$content = str_replace(["\r\n", "\r"], "\n", $content);

$delimiter = ';';
$statements = [];
$buffer = '';
$lines = explode("\n", $content);
foreach ($lines as $line) {
    if (preg_match('/^\s*DELIMITER\s+(.+)$/i', $line, $m)) {
        $delimiter = $m[1];
        continue;
    }
    $buffer .= $line . "\n";
    $trimmed = rtrim($buffer);
    if ($delimiter !== '' && substr($trimmed, -strlen($delimiter)) === $delimiter) {
        $stmt = substr($trimmed, 0, -strlen($delimiter));
        $statements[] = $stmt;
        $buffer = '';
    }
}
if (trim($buffer) !== '') {
    $statements[] = $buffer;
}

$ok = 0; $err = 0; $errors = [];

// Execute statements sequentially (DDL may cause implicit commits)
foreach ($statements as $i => $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '') continue;
    if (preg_match('/^\s*(?:--|#)$/', $stmt)) continue;
    try {
        $conn->exec($stmt);
        $ok++;
    } catch (PDOException $e) {
        $errors[] = [
            'index' => $i,
            'error' => $e->getMessage(),
            'sql' => substr($stmt,0,500)
        ];
        $err++;
    }
}

echo "Applied statements: $ok, Errors: $err\n";
if ($err) {
    foreach ($errors as $e) {
        echo "[stmt #{$e['index']}] error: {$e['error']}\n";
    }
}

echo "Done.\n";
