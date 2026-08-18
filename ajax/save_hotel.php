<?php
/** ajax/save_hotel.php — Create new hotel (basic info only) */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$name            = s($d['name']            ?? '');
$hotel_code_in   = s($d['hotel_code']      ?? '');
$city            = s($d['city']            ?? '');
$state           = s($d['state']           ?? '');
$address         = s($d['address']         ?? '');
$pincode         = s($d['pincode']         ?? '');
$phone           = s($d['phone']           ?? '');
$contact_details = s($d['contact_details'] ?? $phone);
$email           = s($d['email']           ?? '');
$website         = s($d['website']         ?? '');
$property_category = s($d['property_category'] ?? '');
$description     = s($d['description']     ?? '');
$image_urls      = $d['image_urls'] ?? [];

if ($name === '') hl_err('Hotel name is required.');
if ($city === '') hl_err('City is required.');
if (!in_array($property_category, hotel_category_options(), true)) hl_err('Valid hotel category is required.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) hl_err('Invalid email address.');

$star_rating = hotel_category_to_star_rating($property_category);

if (is_string($image_urls)) {
    $decoded = json_decode($image_urls, true);
    if (is_array($decoded)) {
        $image_urls = $decoded;
    } else {
        $image_urls = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $image_urls) ?: [])));
    }
}
if (!is_array($image_urls)) $image_urls = [];
$image_urls = array_values(array_filter(array_map(static function ($u) {
    $v = trim((string)$u);
    return $v;
}, $image_urls), static function ($u) {
    return $u !== '';
}));
$image_json = !empty($image_urls) ? json_encode($image_urls, JSON_UNESCAPED_UNICODE) : null;

$hotel_code = $hotel_code_in !== '' ? strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $hotel_code_in)) : gen_hotel_code($pdo, $city, $name);
if ($hotel_code === '') {
    $hotel_code = gen_hotel_code($pdo, $city, $name);
}

try {
    $stmt = $pdo->prepare("INSERT INTO hotels (hotel_code,name,city,state,address,pin_code,phone,contact_details,email,website,star_rating,property_category,description,image_urls,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active')");
    $stmt->execute([$hotel_code,$name,$city,$state,$address,$pincode,$phone,$contact_details,$email,$website,$star_rating,$property_category,$description,$image_json]);
    $hotel_id = (int)$pdo->lastInsertId();

    hl_ok([
        'hotel_id' => $hotel_id,
        'hotel_code' => $hotel_code,
        'created_defaults' => [
            'categories' => 0,
            'total_rooms' => 0,
            'available' => 0,
            'booked' => 0,
            'blocked' => 0,
            'occupancy_pct' => 0,
            'rate_calendar' => 'empty',
            'availability' => 'empty',
            'bookings' => 'empty',
        ],
    ], "Hotel \"$name\" created successfully.");
} catch (PDOException $e) {
    if ((int)($e->errorInfo[1] ?? 0) === 1062) {
        hl_err('Hotel code already exists. Please use a unique hotel code.', 409);
    }
    hl_err('Database error occurred. Please try again.', 500);
}
