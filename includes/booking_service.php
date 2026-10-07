<?php
/** Canonical hotel reservations. Callers retain their existing JSON contracts. */
function booking_dates(string $from, string $to): array {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $end <= $start || $start->diff($end)->days > 366) {
        throw new InvalidArgumentException('Choose valid check-in and check-out dates, at most 366 nights apart.');
    }
    $dates = [];
    for ($day = $start; $day < $end; $day = $day->modify('+1 day')) $dates[] = $day->format('Y-m-d');
    return $dates;
}

function booking_integer(array $data, string $field, int $default, int $min = 0, int $max = 255): int {
    $value = filter_var($data[$field] ?? $default, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || $value > $max) throw new InvalidArgumentException("Invalid $field.");
    return $value;
}

function booking_create(PDO $pdo, array $data): array {
    $hotel = booking_integer($data, 'hotel_id', 0, 1, PHP_INT_MAX);
    $room = booking_integer($data, 'room_category_id', 0, 1, PHP_INT_MAX);
    $count = booking_integer($data, 'rooms_count', 1, 1);
    $adults = booking_integer($data, 'adults', 1, 1);
    $children = booking_integer($data, 'children', 0);
    $extra = booking_integer($data, 'extra_beds', 0);
    $name = trim((string)($data['guest_name'] ?? ''));
    $phone = trim((string)($data['guest_phone'] ?? ''));
    $email = trim((string)($data['guest_email'] ?? ''));
    $source = trim((string)($data['source'] ?? 'direct'));
    if ($name === '' || mb_strlen($name) > 200 || strlen($phone) > 20 || strlen($email) > 150 || strlen($source) > 50) throw new InvalidArgumentException('Invalid guest or source details.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid guest email.');
    $from = (string)($data['checkin_date'] ?? $data['check_in'] ?? '');
    $to = (string)($data['checkout_date'] ?? $data['check_out'] ?? '');
    $dates = booking_dates($from, $to);
    $payment = $data['payment_status'] ?? 'pending';
    if (!in_array($payment, ['pending','partial','paid'], true)) throw new InvalidArgumentException('Invalid payment status.');
    $plan = $pdo->prepare("SELECT id FROM meal_plans WHERE code=? AND status='active'");
    $plan->execute([$data['meal_plan'] ?? 'EP']);
    $planId = (int)$plan->fetchColumn();
    if (!$planId) throw new InvalidArgumentException('Meal plan not found.');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        // Serialize reservations for a room category, including dates with no calendar row yet.
        $stmt = $pdo->prepare("SELECT r.* FROM hotel_room_categories r JOIN hotels h ON h.id=r.hotel_id WHERE r.id=? AND r.hotel_id=? AND r.status='active' AND h.status='active' FOR UPDATE");
        $stmt->execute([$room, $hotel]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$category) throw new InvalidArgumentException('Active room category does not belong to this hotel.');
        if ($extra > 0 && (!$category['extra_bed_allowed'] || $extra > (int)$category['max_extra_beds'] * $count)) throw new InvalidArgumentException('Extra bed allowance exceeded.');
        $rates = $pdo->prepare('SELECT rate_date,base_price,date_wise_price FROM room_prices WHERE room_category_id=? AND meal_plan_id=? AND (rate_date IS NULL OR (rate_date>=? AND rate_date<?)) ORDER BY id');
        $rates->execute([$room,$planId,$from,$to]);
        $base = 0; $daily = [];
        foreach ($rates->fetchAll(PDO::FETCH_ASSOC) as $rate) {
            if ($rate['rate_date'] === null) $base = (float)$rate['base_price'];
            else $daily[$rate['rate_date']] = (float)($rate['date_wise_price'] ?? $rate['base_price']);
        }
        $seed = $pdo->prepare('INSERT INTO room_availability (hotel_id,room_category_id,availability_date,total_rooms,available_rooms,booked_rooms,blocked_rooms) VALUES (?,?,?,?,?,0,?) ON DUPLICATE KEY UPDATE id=id');
        $reserve = $pdo->prepare('UPDATE room_availability SET available_rooms=available_rooms-?,booked_rooms=booked_rooms+? WHERE room_category_id=? AND availability_date=? AND available_rooms>=?');
        $sum = 0;
        foreach ($dates as $date) {
            $price = $daily[$date] ?? $base;
            if ($price <= 0) throw new InvalidArgumentException("Configure a rate for $date before booking.");
            $sum += $price;
            // Calendar rows are date-specific; lifetime booking totals must not reduce future dates.
            $available = max(0, (int)$category['total_rooms'] - (int)$category['blocked_rooms']);
            $seed->execute([$hotel,$room,$date,$category['total_rooms'],$available,$category['blocked_rooms']]);
            $reserve->execute([$count,$count,$room,$date,$count]);
            if ($reserve->rowCount() !== 1) throw new DomainException("Not enough rooms on $date.");
        }
        $nights = count($dates);
        $total = round($sum * $count + (float)$category['extra_bed_price'] * $extra * $nights, 2);
        $number = 'BK-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
        $pdo->prepare("INSERT INTO hotel_bookings (booking_number,hotel_id,guest_name,guest_phone,guest_email,checkin_date,checkout_date,total_nights,total_amount,meal_plan_id,special_requests,source,booking_status,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'confirmed',?)")
            ->execute([$number,$hotel,$name,$phone,$email,$from,$to,$nights,$total,$planId,$data['special_requests'] ?? '',$source,$payment]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO booking_rooms (booking_id,room_category_id,meal_plan_id,rooms_count,adults,children,extra_beds,price_per_night,total_price) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$id,$room,$planId,$count,$adults,$children,$extra,round($sum/$nights,2),$total]);
        if ($ownsTransaction) $pdo->commit();
        return ['booking_id'=>$id,'booking_number'=>$number,'total_amount'=>$total,'nights'=>$nights];
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function booking_update(PDO $pdo, int $id, array $data): array {
    if ($id < 1) throw new InvalidArgumentException('Booking ID is required.');
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM hotel_bookings WHERE id=? FOR UPDATE');
        $stmt->execute([$id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) throw new InvalidArgumentException('Booking not found.');
        $current = $booking['booking_status'];
        $next = ($data['status'] ?? '') ?: $current;
        $allowed = ['pending'=>['confirmed','cancelled'], 'confirmed'=>['checked_in','cancelled'], 'checked_in'=>['checked_out'], 'checked_out'=>[], 'cancelled'=>[]];
        if ($next !== $current && !in_array($next, $allowed[$current] ?? [], true)) throw new DomainException('This booking status transition is not allowed.');
        $payment = ($data['payment_status'] ?? '') ?: $booking['payment_status'];
        if (!in_array($payment, ['pending','partial','paid'], true)) throw new InvalidArgumentException('Invalid payment status.');
        if ($next === 'cancelled' && $current !== 'cancelled') {
            $rooms = $pdo->prepare('SELECT room_category_id, rooms_count FROM booking_rooms WHERE booking_id=? ORDER BY room_category_id');
            $rooms->execute([$id]);
            foreach ($rooms->fetchAll(PDO::FETCH_ASSOC) as $room) {
                $lock = $pdo->prepare('SELECT id FROM hotel_room_categories WHERE id=? FOR UPDATE');
                $lock->execute([$room['room_category_id']]);
                $pdo->prepare('UPDATE room_availability SET available_rooms=LEAST(total_rooms-blocked_rooms,available_rooms+?),booked_rooms=GREATEST(0,booked_rooms-?) WHERE room_category_id=? AND availability_date>=? AND availability_date<?')
                    ->execute([$room['rooms_count'],$room['rooms_count'],$room['room_category_id'],$booking['checkin_date'],$booking['checkout_date']]);
            }
        }
        $notes = $data['special_requests'] ?? $booking['special_requests'];
        $pdo->prepare('UPDATE hotel_bookings SET booking_status=?,payment_status=?,special_requests=?,updated_at=NOW() WHERE id=?')->execute([$next,$payment,$notes,$id]);
        if ($ownsTransaction) $pdo->commit();
        return ['booking_id'=>$id,'booking_number'=>$booking['booking_number'],'status'=>$next];
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
