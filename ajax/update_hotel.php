<?php
/** ajax/update_hotel.php — Update hotel details */
require_once __DIR__ . '/helpers.php';
hl_require_admin();
$pdo = hl_pdo();
$d   = hl_body();

$hotel_id    = i($d['hotel_id']    ?? 0);
$name        = s($d['name']        ?? '');
$hotel_code  = strtoupper(preg_replace('/[^A-Z0-9\-]/', '', s($d['hotel_code'] ?? '')));
$city        = s($d['city']        ?? '');
$state       = s($d['state']       ?? '');
$address     = s($d['address']     ?? '');
$pincode     = s($d['pincode']     ?? '');
$phone       = s($d['phone']       ?? '');
$contact_details = s($d['contact_details'] ?? $phone);
$email       = s($d['email']       ?? '');
$website     = s($d['website']     ?? '');
$property_category = s($d['property_category'] ?? '');
$description = s($d['description'] ?? '');
$image_urls  = $d['image_urls'] ?? [];
$status      = in_array($d['status'] ?? '', ['active','inactive']) ? $d['status'] : 'active';

if ($hotel_id <= 0) hl_err('Valid hotel_id required.');
if ($name === '')   hl_err('Hotel name is required.');
if (!in_array($property_category, hotel_category_options(), true)) hl_err('Valid hotel category is required.');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) hl_err('Invalid email.');
if ($hotel_code === '') hl_err('Hotel code is required.');

if (is_string($image_urls)) {
    $decoded = json_decode($image_urls, true);
    $image_urls = is_array($decoded) ? $decoded : (preg_split('/[\r\n,]+/', $image_urls) ?: []);
}
$image_urls = array_values(array_filter(array_map(static fn($url) => trim((string)$url), is_array($image_urls) ? $image_urls : [])));
$image_json = !empty($image_urls) ? json_encode($image_urls, JSON_UNESCAPED_UNICODE) : null;

$star_rating = hotel_category_to_star_rating($property_category);

$chk = $pdo->prepare('SELECT id FROM hotels WHERE id=?');
$chk->execute([$hotel_id]);
if (!$chk->fetch()) hl_err('Hotel not found.', 404);

$codeChk = $pdo->prepare('SELECT id FROM hotels WHERE hotel_code=? AND id<>?');
$codeChk->execute([$hotel_code, $hotel_id]);
if ($codeChk->fetch()) hl_err('Hotel code already exists. Please use a unique hotel code.', 409);

try {
    $pdo->prepare("UPDATE hotels SET hotel_code=?,name=?,city=?,state=?,address=?,pin_code=?,phone=?,contact_details=?,email=?,website=?,star_rating=?,property_category=?,description=?,image_urls=?,status=?,updated_at=NOW() WHERE id=?")
        ->execute([$hotel_code,$name,$city,$state,$address,$pincode,$phone,$contact_details,$email,$website,$star_rating,$property_category,$description,$image_json,$status,$hotel_id]);
    hl_ok(['hotel_id' => $hotel_id], 'Hotel updated successfully.');
} catch (PDOException $e) {
    hl_err('Database error occurred. Please try again.', 500);
}
