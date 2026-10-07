<?php
if(PHP_SAPI!=='cli') exit;
ini_set('zend.exception_ignore_args','1');
putenv('CRM_DB_HOST=127.0.0.1');putenv('CRM_DB_NAME=crm_validation_20261007');putenv('CRM_DB_USER=root');putenv('CRM_DB_PASS=');
require __DIR__.'/../includes/db_connect.php';
require __DIR__.'/../includes/crm_booking.php';
crm_booking_schema($conn);
if(!is_dir(__DIR__.'/.runtime')) mkdir(__DIR__.'/.runtime');
$password=bin2hex(random_bytes(12));
$ids=[];
foreach(['admin','employee','other'] as $role) {
 $username='qa_'.$role; $actual=$role==='admin'?'admin':'employee';
 $s=$conn->prepare('INSERT INTO users (username,password,email,role) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE password=VALUES(password)');
 $s->execute([$username,password_hash($password,PASSWORD_BCRYPT),$username.'@example.test',$actual]);
 $s=$conn->prepare('SELECT id FROM users WHERE username=?');$s->execute([$username]);$ids[$role]=(int)$s->fetchColumn();
}
$conn->exec("INSERT INTO meal_plans (code,name,label) VALUES ('EP','Room only','EP') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$conn->exec("INSERT INTO agents_details (name,email,phone,location,status) VALUES ('QA Agent','qa.agent@example.test','9000000001','Test','Active') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$ids['agent']=(int)$conn->query("SELECT id FROM agents_details WHERE email='qa.agent@example.test'")->fetchColumn();
$conn->exec("INSERT INTO hotels (hotel_code,name,city,status) VALUES ('QA-HOTEL','QA Test Hotel','Test City','active') ON DUPLICATE KEY UPDATE name=VALUES(name)");
$ids['hotel']=(int)$conn->query("SELECT id FROM hotels WHERE hotel_code='QA-HOTEL'")->fetchColumn();
$s=$conn->prepare("SELECT id FROM hotel_room_categories WHERE hotel_id=? AND name='QA Room'");$s->execute([$ids['hotel']]);$ids['room']=(int)$s->fetchColumn();
if(!$ids['room']) { $conn->prepare("INSERT INTO hotel_room_categories (hotel_id,name,total_rooms,available_rooms) VALUES (?,'QA Room',2,2)")->execute([$ids['hotel']]);$ids['room']=(int)$conn->lastInsertId(); }
$s=$conn->prepare('SELECT id FROM room_prices WHERE room_category_id=? AND rate_date IS NULL');$s->execute([$ids['room']]);
if(!$s->fetchColumn()) $conn->prepare("INSERT INTO room_prices(hotel_id,room_category_id,meal_plan_id,base_price) SELECT ?,?,id,1000 FROM meal_plans WHERE code='EP'")->execute([$ids['hotel'],$ids['room']]);
file_put_contents(__DIR__.'/.runtime/fixture.json',json_encode(['password'=>$password,'ids'=>$ids]));
echo 'QA fixture prepared; credentials kept in ignored test runtime.',"\n";
