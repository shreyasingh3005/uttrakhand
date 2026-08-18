<?php
/** ajax/save_availability.php — Batch upsert room availability */
require_once __DIR__ . '/helpers.php';
hl_require_admin_or_manager();
$pdo     = hl_pdo();
$d       = hl_body();
$updates = $d['updates'] ?? [];

if (!is_array($updates) || empty($updates)) hl_err('updates array required.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO room_availability (hotel_id,room_category_id,availability_date,total_rooms,available_rooms,booked_rooms,blocked_rooms) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE available_rooms=VALUES(available_rooms),blocked_rooms=VALUES(blocked_rooms),updated_at=NOW()");
    $rcStmt = $pdo->prepare("UPDATE hotel_room_categories SET available_rooms=?,blocked_rooms=?,updated_at=NOW() WHERE id=?");

    $cnt = 0;
    $summaryUpdated = [];
    foreach ($updates as $u) {
        $rid      = i($u['room_id']       ?? 0);
        $date     = s($u['date']          ?? $u['availability_date'] ?? '');
        $avail    = max(0, i($u['available_rooms'] ?? 0));
        $booked   = max(0, i($u['booked_rooms']   ?? 0));
        $blocked  = max(0, i($u['blocked_rooms']  ?? 0));
        $total    = max(0, i($u['total_rooms']     ?? 0));
        if ($rid <= 0 || !$date) continue;

        // Get hotel_id and total_rooms if not provided
        if (!empty($u['hotel_id'])) {
            $hotel_id = i($u['hotel_id']);
        } else {
            $hq = $pdo->prepare("SELECT hotel_id FROM hotel_room_categories WHERE id=?");
            $hq->execute([$rid]);
            $hr = $hq->fetch();
            if (!$hr) continue;
            $hotel_id = (int)$hr['hotel_id'];
        }
        if ($total === 0) {
            $tq = $pdo->prepare("SELECT total_rooms FROM hotel_room_categories WHERE id=?");
            $tq->execute([$rid]);
            $tr = $tq->fetch();
            $total = $tr ? (int)$tr['total_rooms'] : 0;
        }

        $stmt->execute([$hotel_id,$rid,$date,$total,$avail,$booked,$blocked]);
        $cnt++;
        $summaryUpdated[$rid] = ['avail' => $avail, 'blocked' => $blocked];
    }

    // Update room_categories summary
    foreach ($summaryUpdated as $rid => $vals) {
        $rcStmt->execute([$vals['avail'], $vals['blocked'], $rid]);
    }

    $pdo->commit();
    hl_ok(['count' => $cnt], "$cnt availability record(s) saved.");
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    hl_err('Database error occurred. Please try again.', 500);
}
