<?php
/**
 * seed_data.php — Bulk Test Data for Hotel Listing
 * Run via browser : http://localhost/seed_data.php
 * Run via CLI     : php seed_data.php
 */
declare(strict_types=1);
set_time_limit(300);
ini_set('memory_limit', '512M');

$isCLI = php_sapi_name() === 'cli';
function out(string $msg, string $type = 'info'): void {
    global $isCLI;
    if ($isCLI) { echo $msg . PHP_EOL; return; }
    $c = ['ok'=>'#16a34a','err'=>'#dc2626','info'=>'#2563eb','warn'=>'#d97706'];
    echo "<p style='font-family:monospace;font-size:13px;margin:2px 0;color:".($c[$type]??'#333')."'>".htmlspecialchars($msg)."</p>\n";
    if (ob_get_level()) { ob_flush(); flush(); }
}

if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Seed Data</title>
    <style>body{background:#0f172a;padding:24px;font-family:monospace}h2{color:#38bdf8}</style></head><body>
    <h2>🌱 Hotel Listing Seed Data Generator</h2>\n";
}

/* ── DB ─────────────────────────────────────────────────────────────────── */
require_once __DIR__ . '/includes/config.php';
try {
    $cfg = config();
    $pdo = new PDO('mysql:host='.$cfg['DB_HOST'].';dbname='.$cfg['DB_NAME'].';charset=utf8mb4', $cfg['DB_USER'], $cfg['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    out('✓ DB connected','ok');
} catch (PDOException $e) { out('✗ DB connection failed. Check .env.php.','err'); exit(1); }

/* ── Get meal plan IDs ───────────────────────────────────────────────────── */
$mpRows   = $pdo->query("SELECT id,code FROM meal_plans WHERE status='active' ORDER BY sort_order")->fetchAll();
$mpByCode = array_column($mpRows,'id','code');
if (empty($mpByCode)) { out('✗ meal_plans empty. Run hotel_listing_clean.sql first.','err'); exit(1); }
out('✓ Meal plans loaded: '.implode(', ',array_keys($mpByCode)),'ok');

/* ── Helpers ─────────────────────────────────────────────────────────────── */
function pr(float $v): float { return round($v/50)*50; }

/* ── Tier base EP prices & meal plan multipliers ────────────────────────── */
$tierEP  = [1=>2000, 2=>4000, 3=>8000, 4=>15000, 5=>28000];
$mpMul   = ['EP'=>1.00,'CP'=>1.30,'MAP'=>1.60,'AP'=>1.90,'AI'=>2.40];
$starMul = [3=>0.70, 4=>1.00, 5=>1.50];
$dowMul  = [0=>1.15, 5=>1.25, 6=>1.35]; // Sun/Fri/Sat premium

/* ─────────────────────────────────────────────────────────────────────────
   HOTEL DATA (18 hotels)
   ───────────────────────────────────────────────────────────────────────── */
$hotelsData = [
    ['HTL-MSR-001','The Grand Palace Mussoorie','Mussoorie','Uttarakhand','Mall Road, Landour','248179','+91 9876501001','reservations@grandpalace-msr.com','https://grandpalace-msr.com',5,'Iconic 5-star hilltop resort with sweeping Himalayan views.'],
    ['HTL-NTL-001','Himalayan Bliss Resort Nainital','Nainital','Uttarakhand','Near Naini Lake, Mall Road','263001','+91 9876502002','info@himalayanbliss.in','https://himalayanbliss.in',4,'Elegant lakeside retreat with mountain backdrop.'],
    ['HTL-RSH-001','Ganga View Hotel Rishikesh','Rishikesh','Uttarakhand','Swarg Ashram, Tapovan','249192','+91 9876503003','stay@gangaviewhotel.in','https://gangaviewhotel.in',3,'Peaceful riverside hotel near yoga and adventure spots.'],
    ['HTL-HRW-001','Dev Bhoomi Retreat Haridwar','Haridwar','Uttarakhand','Har Ki Pauri Road','249401','+91 9876504004','devbhoomi@retreat.in','https://devbhoomiretreat.in',3,'Spiritual stay near the sacred ghats of Haridwar.'],
    ['HTL-JCB-001',"Tiger's Den Lodge Jim Corbett",'Jim Corbett','Uttarakhand','Near Dhikala Zone Gate, Ramnagar','244715','+91 9876505005','bookings@tigersden.in','https://tigersden.in',4,'Jungle lodge on the edge of Jim Corbett National Park.'],
    ['HTL-AUL-001','Snow Peak Chalet Auli','Auli','Uttarakhand','Auli Ropeway, Joshimath','246443','+91 9876506006','stay@snowpeakauli.com','https://snowpeakauli.com',3,'Ski resort chalet with panoramic Nanda Devi views.'],
    ['HTL-SML-001','Apple Blossom Resort Shimla','Shimla','Himachal Pradesh','Kufri Road, Near Scandal Point','171001','+91 9876507007','reservations@appleblossom.in','https://appleblossom.in',4,'Colonial hill resort surrounded by apple orchards.'],
    ['HTL-MNL-001','The Himalayan Retreat Manali','Manali','Himachal Pradesh','Old Manali Road','175131','+91 9876508008','stay@himalayanretreat.in','https://himalayanretreat.in',4,'Premium mountain retreat with adventure sports access.'],
    ['HTL-DRM-001','McLeod Hillside Inn Dharamsala','Dharamsala','Himachal Pradesh','Temple Road, McLeod Ganj','176219','+91 9876509009','info@mcleodinn.in','https://mcleodinn.in',3,'Charming inn in the Tibetan capital with Dhauladhar views.'],
    ['HTL-JPR-001','Rajputana Grand Jaipur','Jaipur','Rajasthan','C-Scheme, Sardar Patel Marg','302001','+91 9876510010','reservations@rajputanagrand.com','https://rajputanagrand.com',5,'Opulent palace hotel with royal Rajputana décor.'],
    ['HTL-UDR-001','Lake Heritage Udaipur','Udaipur','Rajasthan','Near Pichola Lake, City Palace Road','313001','+91 9876511011','stay@lakeheritage-udaipur.com','https://lakeheritage-udaipur.com',5,'Romantic lakeside heritage hotel near City Palace.'],
    ['HTL-JSL-001','Golden Fort Hotel Jaisalmer','Jaisalmer','Rajasthan','Near Jaisalmer Fort, Amar Sagar Road','345001','+91 9876512012','info@goldenforthotel.in','https://goldenforthotel.in',4,'Boutique hotel in the golden city with desert safaris.'],
    ['HTL-JDH-001','Blue City Haveli Jodhpur','Jodhpur','Rajasthan','Near Mehrangarh Fort, Old City','342001','+91 9876513013','stay@bluecityhaveli.in','https://bluecityhaveli.in',4,'Heritage haveli with Mehrangarh Fort views.'],
    ['HTL-GOA-001','Adamo The Bellus Goa','Goa','Goa','Near Calangute Beach, North Goa','403516','+91 9876514014','reservations@adamobellus.com','https://adamobellus.com',5,'Luxurious 5-star beachside resort with infinity pool.'],
    ['HTL-ALP-001','Backwater Villa Alleppey','Alleppey','Kerala','Punnamada Lake Road','688006','+91 9876515015','stay@backwatervilla.in','https://backwatervilla.in',3,'Kerala villa with houseboat rides and backwater experience.'],
    ['HTL-MNR-001','Spice Garden Resort Munnar','Munnar','Kerala','Top Station Road','685612','+91 9876516016','info@spicegarden.in','https://spicegarden.in',4,'Nestled among tea gardens with misty valley views.'],
    ['HTL-AGR-001','Taj Gateway Hotel Agra','Agra','Uttar Pradesh','Fatehabad Road, Taj Mahal East Gate','282001','+91 9876517017','reservations@tajgatewayagra.com','https://tajgatewayagra.com',4,'Premium hotel with Taj Mahal views and Mughal cuisine.'],
    ['HTL-PND-001','French Riviera Pondicherry','Pondicherry','Pondicherry','White Town, Rue Bussy','605001','+91 9876518018','bonjour@frenchriviera.in','https://frenchriviera.in',3,'French colonial boutique hotel in heritage White Town.'],
];

/* ── Room templates [name, bed, size, tier, total, eb_on, eb_price, eb_max] ── */
$stdRooms = [
    ['Standard Room',     'Double','220 sq ft',1,12,1,700,2],
    ['Deluxe Room',       'King',  '320 sq ft',2, 8,1,1000,1],
    ['Premium Room',      'King',  '440 sq ft',3, 5,1,1200,1],
    ['Suite',             'King',  '600 sq ft',4, 3,0,0,0],
    ['Grand Suite',       'King',  '850 sq ft',5, 2,0,0,0],
    ['Twin Sharing Room', 'Twin',  '260 sq ft',1, 6,1,600,1],
];
/* Special room overrides by city keyword */
$specialRoom = [
    'Jim Corbett' => ['Jungle Cottage',      'Double','350 sq ft',3,4,1,1000,1],
    'Auli'        => ['Ski Chalet Room',      'Twin',  '240 sq ft',2,5,1,800,1],
    'Jaisalmer'   => ['Desert Camp Tent',     'Double','300 sq ft',2,6,0,0,0],
    'Alleppey'    => ['Houseboat Deluxe',     'Double','280 sq ft',3,3,0,0,0],
];

/* ── Guest names & phones ────────────────────────────────────────────────── */
$guests = [
    ['Rahul Sharma','+91 9811100001','rahul.sharma'],['Priya Verma','+91 9822200002','priya.verma'],
    ['Arjun Mehta','+91 9833300003','arjun.mehta'],['Sunita Gupta','+91 9844400004','sunita.gupta'],
    ['Vikram Singh','+91 9855500005','vikram.singh'],['Kavya Reddy','+91 9866600006','kavya.reddy'],
    ['Amit Patel','+91 9877700007','amit.patel'],['Pooja Joshi','+91 9888800008','pooja.joshi'],
    ['Rajesh Kumar','+91 9899900009','rajesh.kumar'],['Meena Agarwal','+91 9900000010','meena.agarwal'],
    ['Suresh Iyer','+91 9911100011','suresh.iyer'],['Anita Krishnan','+91 9922200012','anita.krishnan'],
    ['Deepak Malhotra','+91 9933300013','deepak.malhotra'],['Neha Pandey','+91 9944400014','neha.pandey'],
    ['Manish Yadav','+91 9955500015','manish.yadav'],['Sonia Bhatia','+91 9966600016','sonia.bhatia'],
    ['Rohit Chauhan','+91 9977700017','rohit.chauhan'],['Divya Saxena','+91 9988800018','divya.saxena'],
    ['Ajay Thakur','+91 9999900019','ajay.thakur'],['Ritu Deshpande','+91 8800000020','ritu.deshpande'],
    ['Varun Nair','+91 8811100021','varun.nair'],['Shreya Pillai','+91 8822200022','shreya.pillai'],
    ['Nikhil Bansal','+91 8833300023','nikhil.bansal'],['Aparna Ghosh','+91 8844400024','aparna.ghosh'],
    ['Sanjay Mishra','+91 8855500025','sanjay.mishra'],['Rekha Jain','+91 8866600026','rekha.jain'],
    ['Gaurav Kapoor','+91 8877700027','gaurav.kapoor'],['Swati Choudhary','+91 8888800028','swati.choudhary'],
    ['Pawan Dubey','+91 8899900029','pawan.dubey'],['Ananya Srivastava','+91 8900000030','ananya.srivastava'],
    ['Ravi Naidu','+91 7700000031','ravi.naidu'],['Lakshmi Murthy','+91 7711100032','lakshmi.murthy'],
    ['Karan Sethi','+91 7722200033','karan.sethi'],['Tanya Bhatt','+91 7733300034','tanya.bhatt'],
    ['Aditya Rao','+91 7744400035','aditya.rao'],['Smita Kulkarni','+91 7755500036','smita.kulkarni'],
    ['Vinod Tiwari','+91 7766600037','vinod.tiwari'],['Pallavi Goswami','+91 7777700038','pallavi.goswami'],
    ['Ashok Tripathi','+91 7788800039','ashok.tripathi'],['Nandini Menon','+91 7799900040','nandini.menon'],
    ['Hemant Bajaj','+91 6600000041','hemant.bajaj'],['Usha Rangwala','+91 6611100042','usha.rangwala'],
    ['Dinesh Chadha','+91 6622200043','dinesh.chadha'],['Preeti Mathur','+91 6633300044','preeti.mathur'],
    ['Sandeep Oberoi','+91 6644400045','sandeep.oberoi'],
];

$sources  = ['direct','agent','online','phone','walk-in'];
$specials = ['','Early check-in requested.','Late checkout needed.','Honeymoon decoration please.','Anniversary package.','High floor preferred.','Quiet room away from elevator.','King bed confirmed.'];

/* ── Build 45 bookings config: [days_from_now, nights, status] ──────────── */
$bookingsConfig = [
    // Completed (checked_out) — past
    [-18,3,'checked_out','paid'],[-15,2,'checked_out','paid'],[-13,4,'checked_out','paid'],
    [-10,2,'checked_out','paid'],[-8,3,'checked_out','paid'],[-6,2,'checked_out','paid'],
    [-4,3,'checked_out','paid'],[-3,1,'checked_out','paid'],
    // Currently in-house (checked_in)
    [-2,4,'checked_in','partial'],[-1,3,'checked_in','paid'],[-1,2,'checked_in','partial'],
    [0,3,'checked_in','paid'],[0,2,'checked_in','partial'],
    // Upcoming confirmed
    [1,2,'confirmed','partial'],[2,3,'confirmed','pending'],[3,4,'confirmed','pending'],
    [3,2,'confirmed','partial'],[4,3,'confirmed','paid'],[5,2,'confirmed','pending'],
    [6,3,'confirmed','partial'],[7,2,'confirmed','pending'],[8,4,'confirmed','pending'],
    [9,3,'confirmed','partial'],[10,2,'confirmed','paid'],[11,3,'confirmed','pending'],
    [12,2,'confirmed','partial'],[13,4,'confirmed','pending'],[14,3,'confirmed','pending'],
    [15,2,'confirmed','partial'],[16,3,'confirmed','pending'],
    // Pending
    [18,2,'pending','pending'],[20,3,'pending','pending'],[22,2,'pending','pending'],
    [25,4,'pending','pending'],[28,2,'pending','pending'],[30,3,'pending','pending'],
    [33,2,'pending','pending'],[35,3,'pending','pending'],
    // Cancelled
    [5,2,'cancelled','pending'],[8,3,'cancelled','pending'],
    [10,2,'cancelled','pending'],[15,3,'cancelled','pending'],
    [20,2,'cancelled','pending'],[25,4,'cancelled','pending'],[30,2,'cancelled','pending'],
];

/* ═══════════════════════════════════════════════════════════════════════════
   STEP 1 — Clear & Insert Hotels + Rooms
   ═══════════════════════════════════════════════════════════════════════════ */
out('','info');
out('STEP 1: Clearing old data and inserting hotels/rooms...','info');

$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach (['booking_rooms','hotel_bookings','room_availability','room_prices','hotel_room_categories','hotels'] as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
out('✓ Old data cleared','ok');

$hStmt  = $pdo->prepare("INSERT INTO hotels (hotel_code,name,city,state,address,pincode,phone,email,website,star_rating,description,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'active')");
$rcStmt = $pdo->prepare("INSERT INTO hotel_room_categories (hotel_id,name,bed_type,room_size,total_rooms,available_rooms,booked_rooms,blocked_rooms,extra_bed_allowed,extra_bed_price,max_extra_beds,status) VALUES (?,?,?,?,?,?,0,0,?,?,?,'active')");
$bpStmt = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,?,NULL,NULL) ON DUPLICATE KEY UPDATE base_price=VALUES(base_price)");
$drStmt = $pdo->prepare("INSERT INTO room_prices (hotel_id,room_category_id,meal_plan_id,base_price,rate_date,date_wise_price) VALUES (?,?,?,0,?,?) ON DUPLICATE KEY UPDATE date_wise_price=VALUES(date_wise_price)");
$avStmt = $pdo->prepare("INSERT INTO room_availability (hotel_id,room_category_id,availability_date,total_rooms,available_rooms,booked_rooms,blocked_rooms) VALUES (?,?,?,?,?,0,?) ON DUPLICATE KEY UPDATE available_rooms=VALUES(available_rooms),blocked_rooms=VALUES(blocked_rooms)");

$today = new DateTime(); $today->setTime(0,0,0);
$dates = [];
$tmp   = clone $today;
for ($d = 0; $d < 61; $d++) { $dates[] = $tmp->format('Y-m-d'); $tmp->modify('+1 day'); }

$allRooms    = []; // all room records for booking generation
$hotelRooms  = []; // hotel_id => [room records]

$pdo->beginTransaction();
try {
    foreach ($hotelsData as $hd) {
        [$code,$name,$city,$state,$addr,$pin,$phone,$email,$web,$stars,$desc] = $hd;
        $hStmt->execute([$code,$name,$city,$state,$addr,$pin,$phone,$email,$web,$stars,$desc]);
        $hId    = (int)$pdo->lastInsertId();
        $sm     = $starMul[$stars] ?? 1.0;
        $hotelRooms[$hId] = [];

        // Determine room list
        $rooms = array_slice($stdRooms, 0, 5);
        // Replace 5th room with special if city matches
        foreach ($specialRoom as $kw => $sp) {
            if (stripos($city, $kw) !== false) { $rooms[4] = $sp; break; }
        }
        // 4-star+ hotels get the 6th room (twin)
        if ($stars >= 4) $rooms[] = $stdRooms[5];

        foreach ($rooms as $rm) {
            [$rname,$rbed,$rsize,$tier,$rtotal,$eb_on,$eb_pr,$eb_mx] = $rm;
            $epBase  = pr($tierEP[$tier] * $sm);
            $blocked = rand(0, max(0, (int)($rtotal * 0.1)));
            $avail   = $rtotal - $blocked;

            $rcStmt->execute([$hId,$rname,$rbed,$rsize,$rtotal,$avail,$eb_on,$eb_pr,$eb_mx]);
            $rId = (int)$pdo->lastInsertId();

            $rec = ['hotel_id'=>$hId,'room_id'=>$rId,'total'=>$rtotal,'tier'=>$tier,
                    'ep_base'=>$epBase,'blocked'=>$blocked,'init_avail'=>$avail,
                    'eb_price'=>$eb_pr,'city'=>$city,'hotel_name'=>$name,'room_name'=>$rname,'stars'=>$stars];
            $allRooms[]          = $rec;
            $hotelRooms[$hId][]  = $rec;

            // Base prices (5 meal plans)
            foreach ($mpMul as $code => $mul) {
                $pid = $mpByCode[$code] ?? null;
                if (!$pid) continue;
                $bpStmt->execute([$hId, $rId, $pid, pr($epBase * $mul)]);
            }

            // Date-wise rate overrides (Fri/Sat/Sun premium only)
            foreach ($dates as $ds) {
                $dow = (int)(new DateTime($ds))->format('w');
                $mul = $dowMul[$dow] ?? null;
                if ($mul === null) continue;
                foreach ($mpMul as $code => $baseMul) {
                    $pid = $mpByCode[$code] ?? null;
                    if (!$pid) continue;
                    $drStmt->execute([$hId, $rId, $pid, $ds, pr($epBase * $baseMul * $mul)]);
                }
            }

            // 60-day availability (default: avail=total-blocked, booked=0)
            foreach ($dates as $ds) {
                $avStmt->execute([$hId, $rId, $ds, $rtotal, $avail, $blocked]);
            }
        }
        out("  ✓ {$city} — {$name} (".count($rooms)." rooms)",'ok');
    }
    $pdo->commit();
    out('✓ All hotels, rooms, prices, availability committed','ok');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out('✗ Hotel insert failed: '.$e->getMessage(),'err'); exit(1);
}

/* ═══════════════════════════════════════════════════════════════════════════
   STEP 2 — Insert Bookings (each booking in its own auto-commit)
   ═══════════════════════════════════════════════════════════════════════════ */
out('','info');
out('STEP 2: Inserting 45 bookings...','info');

$ibStmt  = $pdo->prepare("INSERT INTO hotel_bookings (booking_number,hotel_id,guest_name,guest_phone,guest_email,checkin_date,checkout_date,total_nights,total_amount,meal_plan_id,special_requests,source,booking_status,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
$ibrStmt = $pdo->prepare("INSERT INTO booking_rooms (booking_id,room_category_id,meal_plan_id,rooms_count,adults,children,extra_beds,price_per_night,total_price) VALUES (?,?,?,?,?,?,?,?,?)");
$uavStmt = $pdo->prepare("UPDATE room_availability SET available_rooms=GREATEST(0,available_rooms-?),booked_rooms=booked_rooms+? WHERE room_category_id=? AND availability_date=?");
$urcStmt = $pdo->prepare("UPDATE hotel_room_categories SET available_rooms=GREATEST(0,available_rooms-?),booked_rooms=booked_rooms+? WHERE id=?");

$bkInserted = 0;
$bkSkipped  = 0;
$mealCodes  = array_keys($mpMul);
$usedBkNums = [];

// Spread bookings across different hotels using round-robin on $allRooms
foreach ($bookingsConfig as $idx => $bcfg) {
    [$daysOff, $nights, $bkStatus, $payStatus] = $bcfg;
    $guestIdx  = $idx % count($guests);
    $roomIdx   = $idx % count($allRooms);
    $mealCode  = $mealCodes[$idx % count($mealCodes)];
    $planId    = $mpByCode[$mealCode] ?? 1;
    $rm        = $allRooms[$roomIdx];

    $checkin  = (clone $today)->modify("{$daysOff} days")->format('Y-m-d');
    $checkout = (new DateTime($checkin))->modify("+{$nights} days")->format('Y-m-d');
    $rooms_count = ($rm['total'] >= 2 && rand(0,3)===0) ? 2 : 1;
    $adults      = rand(1,2) * $rooms_count;
    $children    = (rand(0,3)===0) ? 1 : 0;
    $extra_beds  = ($rm['eb_price']>0 && $children>0) ? 1 : 0;
    $ppn         = pr($rm['ep_base'] * ($mpMul[$mealCode] ?? 1.0));
    $eb_cost     = $extra_beds * $rm['eb_price'] * $nights;
    $total       = ($ppn * $rooms_count * $nights) + $eb_cost;

    [$gName,$gPhone,$gSlug] = $guests[$guestIdx];
    $gEmail = $gSlug.'@example.com';
    $source = $sources[$idx % count($sources)];
    $special= $specials[$idx % count($specials)];

    // Unique booking number
    $bkNum = 'BK-'.date('Ymd').'-'.strtoupper(substr(md5((string)$idx.$checkin.$rm['room_id']), 0, 5));
    if (isset($usedBkNums[$bkNum])) $bkNum .= rand(10,99);
    $usedBkNums[$bkNum] = true;

    try {
        // Each booking: no wrapping transaction — use autocommit for safety
        $ibStmt->execute([$bkNum,$rm['hotel_id'],$gName,$gPhone,$gEmail,$checkin,$checkout,$nights,$total,$planId,$special,$source,$bkStatus,$payStatus]);
        $bookingId = (int)$pdo->lastInsertId();

        $ibrStmt->execute([$bookingId,$rm['room_id'],$planId,$rooms_count,$adults,$children,$extra_beds,$ppn,$total]);

        // Update room_availability for each night (non-cancelled only)
        if ($bkStatus !== 'cancelled') {
            $cur = new DateTime($checkin);
            $end = new DateTime($checkout);
            while ($cur < $end) {
                $ds = $cur->format('Y-m-d');
                $uavStmt->execute([$rooms_count,$rooms_count,$rm['room_id'],$ds]);
                $cur->modify('+1 day');
            }
            // Update hotel_room_categories summary (future/in-house bookings only)
            if ($daysOff >= -2 && !in_array($bkStatus,['checked_out'])) {
                $urcStmt->execute([$rooms_count,$rooms_count,$rm['room_id']]);
            }
        }

        $bkInserted++;
        out("  ✓ #{$bkNum} | {$gName} | {$rm['hotel_name']} | {$checkin}→{$checkout} | {$bkStatus}",'ok');
    } catch (PDOException $e) {
        $bkSkipped++;
        out("  ⚠ Booking #{$idx} skipped: ".$e->getMessage(),'warn');
    }
}

out('','info');
out("✓ Bookings inserted: {$bkInserted}, skipped: {$bkSkipped}",'ok');

/* ═══════════════════════════════════════════════════════════════════════════
   STEP 3 — Summary
   ═══════════════════════════════════════════════════════════════════════════ */
out('','info');
$counts = [];
foreach (['hotels','hotel_room_categories','room_prices','room_availability','hotel_bookings','booking_rooms'] as $t) {
    $counts[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
}
out('═══════════════════════════════════════════════','info');
out('  SEED DATA COMPLETE ✅','ok');
out('═══════════════════════════════════════════════','info');
out(sprintf('  Hotels            : %d',  $counts['hotels']),'ok');
out(sprintf('  Room Categories   : %d',  $counts['hotel_room_categories']),'ok');
out(sprintf('  Price Records     : %d',  $counts['room_prices']),'ok');
out(sprintf('  Availability Rows : %d',  $counts['room_availability']),'ok');
out(sprintf('  Bookings          : %d',  $counts['hotel_bookings']),'ok');
out(sprintf('  Booking Rooms     : %d',  $counts['booking_rooms']),'ok');
out('═══════════════════════════════════════════════','info');

if (!$isCLI) {
    echo "<br><a href='/listing.php' style='display:inline-block;margin-top:16px;padding:14px 28px;background:#2a9d8f;color:#fff;text-decoration:none;border-radius:10px;font-family:monospace;font-weight:700;font-size:14px'>🚀 Open listing.php →</a>";
    echo "<a href='/employee-listings.php' style='display:inline-block;margin-top:16px;margin-left:10px;padding:14px 28px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:10px;font-family:monospace;font-weight:700;font-size:14px'>👥 employee-listings.php →</a>";
    echo "</body></html>";
}
