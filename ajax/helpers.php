<?php
/**
 * ajax/helpers.php — Shared helpers for all Hotel Listing AJAX endpoints
 * Uttarakhand Ventures CRM
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

/* ── DB ─────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/../includes/config.php';
function hl_pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = config();
    $pdo = new PDO(
        'mysql:host=' . $cfg['DB_HOST'] . ';dbname=' . $cfg['DB_NAME'] . ';charset=utf8mb4',
        $cfg['DB_USER'], $cfg['DB_PASS'],
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );
    ensure_hm_tables($pdo);
    return $pdo;
}

/* ── Responses ──────────────────────────────────────────────────────────── */
function hl_ok($data = null, string $msg = 'Success'): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'success', 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit();
}
function hl_err(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit();
}

/* ── Auth ────────────────────────────────────────────────────────────────── */
function hl_auth(): void {
    if (empty($_SESSION['user_id'])) hl_err('Unauthorized. Please log in.', 401);
}

/** Require admin role for structural edits (add/delete hotels, room categories). */
function hl_require_admin(): void {
    hl_auth();
    if (($_SESSION['role'] ?? '') !== 'admin') hl_err('Admin access required.', 403);
}

/** Require admin for structural edits, rates, availability, and booking operations. */
function hl_require_admin_or_manager(): void {
    hl_auth();
    if (($_SESSION['role'] ?? '') !== 'admin') hl_err('Admin access required.', 403);
}

/* ── Input ───────────────────────────────────────────────────────────────── */
function hl_body(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false)
        return json_decode(file_get_contents('php://input'), true) ?? [];
    return $_POST;
}
function s(mixed $v): string { return trim((string)$v); }
function f(mixed $v): float  { return max(0.0, (float)$v); }
function i(mixed $v): int    { return (int)$v; }

/* ── Constants ───────────────────────────────────────────────────────────── */
const MEAL_CODES = ['EP','CP','MAP','AP'];
const VALID_PLANS = MEAL_CODES;
const BED_TYPES  = ['Single','Double','Twin','King','Queen','Bunk'];
const DOW        = ['Sun'=>0,'Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6];

/* ── Compatibility aliases (old AJAX files use these names) ────────────── */
function require_auth(): void { hl_auth(); }
function get_pdo(): PDO { return hl_pdo(); }
function json_success(array $data = [], string $msg = 'Success'): never { hl_ok($data, $msg); }
function json_error(string $msg, int $code = 400): never { hl_err($msg, $code); }
function ensure_hm_tables(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $cols = [];
        $colStmt = $pdo->query("SHOW COLUMNS FROM hotels");
        foreach ($colStmt->fetchAll() as $c) {
            $cols[$c['Field']] = true;
        }

        // Backward-compatible fields for richer hotel master data.
        if (!isset($cols['contact_details'])) {
            $pdo->exec("ALTER TABLE hotels ADD COLUMN contact_details VARCHAR(255) DEFAULT '' AFTER phone");
        }
        if (!isset($cols['image_urls'])) {
            $pdo->exec("ALTER TABLE hotels ADD COLUMN image_urls TEXT NULL AFTER description");
        }
        if (!isset($cols['property_category'])) {
            $pdo->exec("ALTER TABLE hotels ADD COLUMN property_category VARCHAR(20) NOT NULL DEFAULT '' AFTER star_rating");
            $pdo->exec("UPDATE hotels SET property_category = CONCAT(star_rating, ' Star') WHERE property_category = '' AND star_rating > 0");
        }

        // Indexes for scalable search.
        $idxRows = $pdo->query("SHOW INDEX FROM hotels")->fetchAll();
        $idx = [];
        foreach ($idxRows as $r) {
            $idx[$r['Key_name']] = true;
        }

        if (!isset($idx['idx_hotels_name'])) {
            $pdo->exec("CREATE INDEX idx_hotels_name ON hotels (name)");
        }
        if (!isset($idx['idx_hotels_city'])) {
            $pdo->exec("CREATE INDEX idx_hotels_city ON hotels (city)");
        }
        if (!isset($idx['idx_hotels_state'])) {
            $pdo->exec("CREATE INDEX idx_hotels_state ON hotels (state)");
        }
        if (!isset($idx['idx_hotels_phone'])) {
            $pdo->exec("CREATE INDEX idx_hotels_phone ON hotels (phone)");
        }
        if (!isset($idx['idx_hotels_email'])) {
            $pdo->exec("CREATE INDEX idx_hotels_email ON hotels (email)");
        }
        if (!isset($idx['idx_hotels_category'])) {
            $pdo->exec("CREATE INDEX idx_hotels_category ON hotels (property_category)");
        }
    } catch (PDOException $e) {
        // Keep endpoint resilient; schema checks should not break responses.
    }
}

/** Fixed list of supported hotel/property categories (admin is source of truth for saved values). */
function hotel_category_options(): array {
    return ['1 Star', '2 Star', '3 Star', '4 Star', '5 Star', 'Resort', 'Boutique'];
}

/** Derives the legacy numeric star_rating column from the merged category value (0 for Resort/Boutique). */
function hotel_category_to_star_rating(string $category): int {
    if (preg_match('/^(\d)\s*Star$/i', trim($category), $m)) {
        return (int)$m[1];
    }
    return 0;
}

/* ── Get meal_plan_id from code ─────────────────────────────────────────── */
function get_plan_id(PDO $pdo, string $code): int {
    static $map = [];
    if (!$map) {
        $rows = $pdo->query("SELECT id,code FROM meal_plans WHERE status='active'")->fetchAll();
        foreach ($rows as $r) $map[$r['code']] = (int)$r['id'];
    }
    return $map[$code] ?? 0;
}

/* ── Get all meal plans as id=>code map ─────────────────────────────────── */
function get_plans_map(PDO $pdo): array {
    static $map = [];
    if (!$map) {
        $rows = $pdo->query("SELECT id,code,name FROM meal_plans WHERE status='active' ORDER BY sort_order")->fetchAll();
        foreach ($rows as $r) $map[(int)$r['id']] = $r;
    }
    return $map;
}

/* ── Load full room object (with prices) ────────────────────────────────── */
function load_room(PDO $pdo, int $room_id): array {
    $r = $pdo->prepare('SELECT * FROM hotel_room_categories WHERE id=?');
    $r->execute([$room_id]);
    $room = $r->fetch();
    if (!$room) return [];
    $room['prices'] = load_room_prices($pdo, $room_id);
    return $room;
}
function load_room_prices(PDO $pdo, int $room_id): array {
    $p = $pdo->prepare("SELECT mp.code, rp.base_price FROM room_prices rp JOIN meal_plans mp ON mp.id=rp.meal_plan_id WHERE rp.room_category_id=? AND rp.rate_date IS NULL");
    $p->execute([$room_id]);
    $prices = [];
    foreach ($p->fetchAll() as $row) $prices[$row['code']] = (float)$row['base_price'];
    return $prices;
}

/* ── Generate unique booking number ─────────────────────────────────────── */
function gen_booking_num(PDO $pdo): string {
    $chk = $pdo->prepare('SELECT id FROM hotel_bookings WHERE booking_number=?');
    for ($i = 0; $i < 20; $i++) {
        $num = 'BK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $chk->execute([$num]);
        if (!$chk->fetch()) return $num;
    }
    return 'BK-' . time() . '-' . rand(1000,9999);
}

/* ── Generate hotel code ─────────────────────────────────────────────────── */
function gen_hotel_code(PDO $pdo, string $city, string $name): string {
    $pre = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $city), 0, 3));
    $suf = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 2));
    $chk = $pdo->prepare('SELECT id FROM hotels WHERE hotel_code=?');
    for ($i = 0; $i < 30; $i++) {
        $code = 'HTL-' . str_pad($pre . $suf, 4, 'H') . '-' . rand(100, 999);
        $chk->execute([$code]);
        if (!$chk->fetch()) return $code;
    }
    return 'HTL-' . strtoupper(substr(uniqid(), -8));
}
