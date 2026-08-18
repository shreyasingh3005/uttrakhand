<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

echo "Testing fixed booking query filter with unique parameter names:\n\n";

try {
    $filterLocation = 'Mussoorie';
    $where = ["LOWER(TRIM(h.status)) = 'active'"];
    $params = [];
    
    $locationTokens = array_filter(preg_split('/\s+/', trim($filterLocation)));
    if ($locationTokens) {
        $tokenClauses = [];
        $paramIndex = 0;
        foreach (array_values($locationTokens) as $i => $token) {
            $keyCity = ':locc' . $paramIndex;
            $keyState = ':locs' . $paramIndex;
            $tokenClauses[] = "(LOWER(TRIM(h.city)) LIKE $keyCity OR LOWER(TRIM(h.state)) LIKE $keyState)";
            $params[$keyCity] = '%' . strtolower($token) . '%';
            $params[$keyState] = '%' . strtolower($token) . '%';
            $paramIndex++;
        }
        $where[] = '(' . implode(' AND ', $tokenClauses) . ')';
    }
    
    $sql = 'SELECT h.id, h.name FROM hotels h WHERE ' . implode(' AND ', $where) . ' ORDER BY h.name ASC';
    echo "SQL: $sql\n";
    echo "Params: " . json_encode($params) . "\n\n";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    echo "✅ SUCCESS! Hotels matching '{$filterLocation}':\n";
    echo "Count: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo "  - {$r['name']} (ID: {$r['id']})\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
