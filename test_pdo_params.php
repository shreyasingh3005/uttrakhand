<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "Testing PDO named parameter with LIKE clause:\n\n";

try {
    $stmt = $conn->prepare("SELECT id, name FROM hotels WHERE LOWER(TRIM(name)) LIKE :term LIMIT 5");
    $stmt->execute([':term' => '%Mussoorie%']);
    $rows = $stmt->fetchAll();
    echo "Results: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  - {$r['name']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n\nTesting with multiple named parameters:\n\n";
try {
    $stmt = $conn->prepare("
        SELECT id, name FROM hotels 
        WHERE (LOWER(TRIM(name)) LIKE :term1 OR LOWER(TRIM(city)) LIKE :term1)
        LIMIT 5
    ");
    $stmt->execute([':term1' => '%Mussoorie%']);
    $rows = $stmt->fetchAll();
    echo "Results: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  - {$r['name']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n\nNow testing the actual booking query filter:\n\n";
try {
    $filterLocation = 'Mussoorie';
    $where = ["LOWER(TRIM(h.status)) = 'active'"];
    $params = [];
    
    $locationTokens = array_filter(preg_split('/\s+/', trim($filterLocation)));
    if ($locationTokens) {
        $tokenClauses = [];
        foreach (array_values($locationTokens) as $i => $token) {
            $key = ':loc' . $i;
            $tokenClauses[] = "(LOWER(TRIM(h.city)) LIKE $key OR LOWER(TRIM(h.state)) LIKE $key)";
            $params[$key] = '%' . strtolower($token) . '%';
        }
        $where[] = '(' . implode(' AND ', $tokenClauses) . ')';
    }
    
    $sql = 'SELECT h.id, h.name FROM hotels h WHERE ' . implode(' AND ', $where) . ' ORDER BY h.name ASC';
    echo "SQL: $sql\n";
    echo "Params: " . json_encode($params) . "\n\n";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    echo "Results: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  - {$r['name']} (ID: {$r['id']})\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
