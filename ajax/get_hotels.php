<?php
require_once __DIR__ . '/helpers.php';
hl_auth();
$pdo = hl_pdo();

$page = max(1, i($_GET['page'] ?? 1));
$perPage = i($_GET['per_page'] ?? 20);
$perPage = max(10, min(100, $perPage));
$offset = ($page - 1) * $perPage;

$sortByReq = s($_GET['sort_by'] ?? 'created_at');
$sortDirReq = strtolower(s($_GET['sort_dir'] ?? 'desc'));

$sortMap = [
    'created_at' => 'h.created_at',
    'hotel_name' => 'h.name',
    'hotel_code' => 'h.hotel_code',
    'city' => 'h.city',
    'state' => 'h.state',
    'contact_number' => 'h.phone',
    'email' => 'h.email',
    'total_rooms' => 'total_rooms',
    'booked' => 'booked_rooms',
    'available' => 'avail_rooms',
    'occupancy' => 'occupancy_pct',
];
$sortBy = $sortMap[$sortByReq] ?? 'h.created_at';
$sortDir = $sortDirReq === 'asc' ? 'ASC' : 'DESC';

$name = s($_GET['hotel_name'] ?? '');
$code = s($_GET['hotel_code'] ?? '');
$city = s($_GET['city'] ?? '');
$state = s($_GET['state'] ?? '');
$contact = s($_GET['contact_number'] ?? '');
$email = s($_GET['email'] ?? '');

$where = ["h.status='active'"];
$params = [];

if ($name !== '') {
    $where[] = 'h.name LIKE :name';
    $params[':name'] = '%' . $name . '%';
}
if ($code !== '') {
    $where[] = 'h.hotel_code LIKE :code';
    $params[':code'] = '%' . $code . '%';
}
if ($city !== '') {
    $where[] = 'h.city LIKE :city';
    $params[':city'] = '%' . $city . '%';
}
if ($state !== '') {
    $where[] = 'h.state LIKE :state';
    $params[':state'] = '%' . $state . '%';
}
if ($contact !== '') {
    $where[] = '(h.phone LIKE :contact OR COALESCE(h.contact_details,\'\') LIKE :contact)';
    $params[':contact'] = '%' . $contact . '%';
}
if ($email !== '') {
    $where[] = 'h.email LIKE :email';
    $params[':email'] = '%' . $email . '%';
}

$whereSql = implode(' AND ', $where);

try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM hotels h WHERE $whereSql");
    $cntStmt->execute($params);
    $total = (int)($cntStmt->fetch()['c'] ?? 0);

    $sql = "
        SELECT h.id, h.hotel_code, h.name, h.city, h.state, h.phone, h.contact_details, h.email,
               COALESCE(agg.room_count,0) AS room_count,
               COALESCE(agg.total_rooms,0) AS total_rooms,
               COALESCE(agg.booked_rooms,0) AS booked_rooms,
               COALESCE(agg.avail_rooms,0) AS avail_rooms,
               COALESCE(agg.blocked_rooms,0) AS blocked_rooms,
               ROUND(COALESCE(agg.booked_rooms,0) / NULLIF(COALESCE(agg.total_rooms,0),0) * 100, 1) AS occupancy_pct,
               h.created_at
        FROM hotels h
        LEFT JOIN (
            SELECT hotel_id,
                   COUNT(*) AS room_count,
                   COALESCE(SUM(total_rooms),0) AS total_rooms,
                   COALESCE(SUM(booked_rooms),0) AS booked_rooms,
                   COALESCE(SUM(available_rooms),0) AS avail_rooms,
                   COALESCE(SUM(blocked_rooms),0) AS blocked_rooms
            FROM hotel_room_categories
            WHERE status='active'
            GROUP BY hotel_id
        ) agg ON agg.hotel_id = h.id
        WHERE $whereSql
        ORDER BY $sortBy $sortDir, h.id DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['room_count'] = (int)$r['room_count'];
        $r['total_rooms'] = (int)$r['total_rooms'];
        $r['booked_rooms'] = (int)$r['booked_rooms'];
        $r['avail_rooms'] = (int)$r['avail_rooms'];
        $r['blocked_rooms'] = (int)$r['blocked_rooms'];
        $r['occupancy_pct'] = (float)($r['occupancy_pct'] ?? 0);
    }
    unset($r);

    hl_ok([
        'hotels' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
        ],
        'filters' => [
            'hotel_name' => $name,
            'hotel_code' => $code,
            'city' => $city,
            'state' => $state,
            'contact_number' => $contact,
            'email' => $email,
        ],
        'sort' => [
            'sort_by' => $sortByReq,
            'sort_dir' => strtolower($sortDir),
        ],
    ]);
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
