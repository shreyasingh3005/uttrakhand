<?php
/** ajax/update_hotel.php — Update hotel details */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$hotel_id    = i($d['hotel_id']    ?? 0);
$name        = s($d['name']        ?? '');
$city        = s($d['city']        ?? '');
$state       = s($d['state']       ?? '');
$address     = s($d['address']     ?? '');
$pincode     = s($d['pincode']     ?? '');
$phone       = s($d['phone']       ?? '');
$email       = s($d['email']       ?? '');
$website     = s($d['website']     ?? '');
$property_category = s($d['property_category'] ?? '');
$description = s($d['description'] ?? '');
$status      = in_array($d['status'] ?? '', ['active','inactive']) ? $d['status'] : 'active';

if ($hotel_id <= 0) hl_err('Valid hotel_id required.');
if ($name === '')   hl_err('Hotel name is required.');
if (!in_array($property_category, hotel_category_options(), true)) hl_err('Valid hotel category is required.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) hl_err('Invalid email.');

$star_rating = hotel_category_to_star_rating($property_category);

$chk = $pdo->prepare('SELECT id FROM hotels WHERE id=?');
$chk->execute([$hotel_id]);
if (!$chk->fetch()) hl_err('Hotel not found.', 404);

try {
    $pdo->prepare("UPDATE hotels SET name=?,city=?,state=?,address=?,pin_code=?,phone=?,email=?,website=?,star_rating=?,property_category=?,description=?,status=?,updated_at=NOW() WHERE id=?")
        ->execute([$name,$city,$state,$address,$pincode,$phone,$email,$website,$star_rating,$property_category,$description,$status,$hotel_id]);
    hl_ok(['hotel_id' => $hotel_id], 'Hotel updated successfully.');
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
