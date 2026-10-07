<?php
/**
 * REST Endpoint: GET /api/agents/by-mobile/{mobile}
 * Returns 200 with agent object if found.
 * Returns 404 if not found.
 * Returns 400 if invalid 10-digit mobile number.
 */

header('Content-Type: application/json; charset=utf-8');

// Determine mobile number from query parameter or REQUEST_URI
$mobile = $_GET['mobile'] ?? '';
if ($mobile === '') {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    if (preg_match('#/api/agents/by-mobile/([^/?]+)#', $uriPath, $matches)) {
        $mobile = urldecode($matches[1]);
    }
}

// 1. Trim whitespace, remove spaces/dashes, keep only digits
$rawMobile = trim((string)$mobile);
$digits = preg_replace('/\D+/', '', $rawMobile);

// 2. Accept only valid 10-digit Indian mobile numbers (must start with 6/7/8/9)
// If raw input contained non-digits or does not match 10-digit regex, treat as invalid
if ($digits === '' || strlen($digits) !== 10 || !preg_match('/^[6-9]\d{9}$/', $digits)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Please enter a valid 10-digit mobile number.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../../includes/db_connect.php';

try {
    // 3. Agent lookup based ONLY on exact 10-digit mobile number match
    $stmt = $conn->prepare(
        "SELECT id, name, company_name, gst_number, email, phone, location, status 
         FROM agents_details 
         WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), 10) = :phone10
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':phone10' => $digits]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($agent) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'found' => true,
            'agent' => [
                'id' => (int)$agent['id'],
                'name' => (string)($agent['name'] ?? ''),
                'phone' => (string)($agent['phone'] ?? ''),
                'gst_number' => (string)($agent['gst_number'] ?: 'N/A'),
                'city' => (string)($agent['location'] ?? 'N/A'),
                'location' => (string)($agent['location'] ?? 'N/A'),
                'agency_name' => (string)($agent['company_name'] ?: 'N/A'),
                'company_name' => (string)($agent['company_name'] ?: 'N/A'),
                'email' => (string)($agent['email'] ?? 'N/A'),
                'status' => (string)($agent['status'] ?? 'Active')
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(404);
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Agent not found for this mobile number. Please check and try again.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'found' => false,
        'message' => 'Database error occurred. Please try again.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
