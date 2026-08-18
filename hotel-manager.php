<?php
/* ═══════════════════════════════════════════════════════════════════════════
   hotel-manager.php  —  Uttarakhand Ventures CRM
   Hotel Room Manager: Room Categories, Availability Grid, Rate Calendar
   ═══════════════════════════════════════════════════════════════════════════ */
require_once __DIR__ . '/includes/auth_session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_role('admin');

/* ── Meal Plans ─────────────────────────────────────────────────────────── */
$mealPlans = [
    'EP'  => 'EP – Room Only',
    'CP'  => 'CP – Breakfast Included',
    'MAP' => 'MAP – Breakfast + Dinner',
    'AP'  => 'AP – All Meals',
    'AI'  => 'AI – All Inclusive',
];

/* ── Load hotels + room categories from DB ─────────────────────────────── */
$hotels = [];
try {
    $stmt = $conn->prepare('SELECT id, hotel_name, category, location, main_image_url, weekday_price, weekend_price, gst, status, created_at FROM hotel_listings_details WHERE 1 ORDER BY created_at DESC');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $hid = (int)$r['id'];
        $hotel = [
            'id'   => 'HTL-DB-'.str_pad($hid,5,'0',STR_PAD_LEFT),
            'name' => $r['hotel_name'] ?: 'Untitled Hotel',
            'city' => $r['location'] ?: '',
            'star' => 4,
            'rooms' => [],
            'rates' => [],
            'meta' => [
                'weekday_price' => (float)$r['weekday_price'],
                'weekend_price' => (float)$r['weekend_price'],
            ],
        ];

        $rstmt = $conn->prepare('SELECT id, category_name, validity, validity_start, validity_end, weekday_price, weekend_price, gst, weekday_cpai, weekday_mapai, weekday_apai, weekend_cpai, weekend_mapai, weekend_apai, cpai_price, mapai_price, extra_person_with_bed, extra_person_without_bed, child_no_bed_cp, child_with_bed_cp, room_image_url, room_details, applicable_days FROM hotel_listing_room_categories WHERE listing_id = :lid ORDER BY id ASC');
        $rstmt->execute([':lid' => $hid]);
        $rooms = $rstmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rooms as $rm) {
            $prices = [];
            // Map meal plan codes to available prices (fallbacks applied)
            $prices['EP'] = (float)($rm['weekday_price'] ?: $r['weekday_price'] ?: 0);
            $prices['CP'] = (float)($rm['cpai_price'] ?: $rm['weekday_cpai'] ?: $prices['EP']);
            $prices['MAP'] = (float)($rm['mapai_price'] ?: $rm['weekday_mapai'] ?: $prices['EP']);
            $prices['AP'] = (float)($rm['weekday_apai'] ?: $prices['EP']);
            $prices['AI'] = (float)($rm['weekend_price'] ?: $r['weekend_price'] ?: $prices['EP']);

            $extraAllowed = ((float)($rm['extra_person_with_bed'] ?? 0) > 0) || ((float)($rm['extra_person_without_bed'] ?? 0) > 0);

            $hotel['rooms'][] = [
                'id' => (int)$rm['id'],
                'name' => $rm['category_name'] ?: 'Room',
                'total' => 10,
                'available' => 10,
                'booked' => 0,
                'blocked' => 0,
                'bed' => 'Double',
                'size' => '',
                'prices' => $prices,
                'extra_bed' => ['allowed' => $extraAllowed, 'price' => (float)($rm['extra_person_with_bed'] ?: 0), 'max' => $extraAllowed ? 1 : 0],
                'validity' => $rm['validity'] ?? '',
                'room_image_url' => $rm['room_image_url'] ?? '',
                'room_details' => $rm['room_details'] ?? '',
            ];
        }

        $hotels[] = $hotel;
    }
} catch (Throwable $e) {
    // fallback to empty hotels array
    $hotels = [];
}

/* ── Calendar Setup ─────────────────────────────────────────────────────── */
$calYear      = 2026;
$calMonth     = 6;
$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $calMonth, $calYear);
$firstDow     = (int) date('N', mktime(0, 0, 0, $calMonth, 1, $calYear)) % 7;

/* ── Auto-generate rates (weekends 15% premium) ─────────────────────────── */
foreach ($hotels as &$h) {
    $base = $h['rooms'][0]['prices']['EP'] ?? 5000;
    foreach (range(1, $daysInMonth) as $d) {
        $key = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
        if (!isset($h['rates'][$key])) {
            $dow = (int) date('N', strtotime($key));
            $h['rates'][$key] = ($dow >= 5) ? intval($base * 1.15) : $base;
        }
    }
}
unset($h);

$todayStr    = date('Y-m-d');
$calMonthStr = date('F Y', mktime(0, 0, 0, $calMonth, 1, $calYear));

/* ── Availability date range (next 14 days from month start) ─────────────── */
$availDates = [];
for ($d = 1; $d <= 14; $d++) {
    $availDates[] = sprintf('%04d-%02d-%02d', $calYear, $calMonth, $d);
}

$currentUser = $_SESSION['username'] ?? 'Admin';
$userInitial = strtoupper(substr($currentUser, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hotel Manager — Uttarakhand Ventures CRM</title>
<meta name="description" content="Manage hotel room categories, availability and rate calendars.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/css/ui-consistency.css" rel="stylesheet">
<style>
/* ── Reset & Tokens ─────────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --teal:#2a9d8f;--teal-d:#21867a;--teal-l:#e8f7f5;
  --amber:#e9c46a;--amber-d:#d4a843;
  --coral:#e76f51;--coral-d:#cf5c3e;
  --navy:#0f172a;--navy-l:#1e293b;
  --brand:#4f46e5;--brand-d:#4338ca;
  --accent:#06b6d4;
  --slate:#f8fafc;--white:#fff;
  --border:#e2e8f0;--text:#0f172a;--muted:#6b7a87;
  --primary-50:#eef2ff;--primary-200:#c7d2fe;
  --r:12px;--sh:0 2px 16px rgba(0,0,0,.07);
  --fri:#fff8e1;--sat:#fde8f0;--sun:#f3e5f5;
  --transition:.2s ease;
}
body{font-family:'Inter','Segoe UI',system-ui,sans-serif;background:var(--slate);color:var(--text);min-height:100vh}

/* ── Topbar ──────────────────────────────────────────────────────────────── */
.topbar{
  background:linear-gradient(135deg,var(--navy) 0%,#0f2d4a 100%);
  color:#fff;display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;height:58px;position:sticky;top:0;z-index:200;
  box-shadow:0 2px 12px rgba(0,0,0,.25);
}
.topbar-brand{display:flex;align-items:center;gap:10px;font-size:1.05rem;font-weight:700;letter-spacing:.01em}
.topbar-brand .brand-icon{width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;font-size:18px}
.topbar-r{display:flex;align-items:center;gap:14px;font-size:.84rem}
.topbar-nav a{color:rgba(255,255,255,.75);font-size:.82rem;font-weight:500;padding:6px 12px;border-radius:7px;transition:all var(--transition);text-decoration:none}
.topbar-nav a:hover{color:#fff;background:rgba(255,255,255,.1)}
.avatar{width:34px;height:34px;border-radius:50%;background:var(--brand);display:grid;place-items:center;font-weight:700;font-size:.82rem;border:2px solid rgba(255,255,255,.25)}
.topbar-account{position:relative}
.topbar-profile-btn{display:flex;align-items:center;gap:8px;background:transparent;border:none;color:#fff;cursor:pointer}
.topbar-profile-btn .account-name{opacity:.8;font-size:.8rem}
.topbar-profile-menu{display:none;position:absolute;right:0;top:44px;min-width:210px;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 12px 24px rgba(15,23,42,.16);padding:8px;z-index:230}
.topbar-profile-menu.open{display:block}
.topbar-profile-link{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;color:var(--text);font-size:.82rem;text-decoration:none}
.topbar-profile-link:hover{background:#f1f5f9}
.topbar-profile-link.logout{color:#ef4444}

/* ── Layout ──────────────────────────────────────────────────────────────── */
.page{max-width:1340px;margin:0 auto;padding:28px 20px 60px}

/* ── Page Header ─────────────────────────────────────────────────────────── */
.page-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:14px}
.page-hdr-left h1{font-size:1.35rem;font-weight:800;color:var(--navy);display:flex;align-items:center;gap:10px}
.page-hdr-left p{font-size:.82rem;color:var(--muted);margin-top:3px}

/* ── Summary Stats ───────────────────────────────────────────────────────── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stat-card{background:var(--white);border-radius:var(--r);border:1px solid var(--border);padding:16px 20px;display:flex;align-items:center;gap:14px;box-shadow:var(--sh)}
.stat-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.stat-label{font-size:.74rem;color:var(--muted);margin-bottom:2px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.stat-value{font-size:1.3rem;font-weight:800;line-height:1}

/* ── Hotel Card ──────────────────────────────────────────────────────────── */
.hotel-card{background:var(--white);border-radius:var(--r);box-shadow:var(--sh);margin-bottom:32px;overflow:hidden;border:1px solid var(--border)}
.hotel-header{background:linear-gradient(135deg,var(--navy) 0%,#1a4a6e 100%);color:#fff;padding:20px 26px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px}
.hotel-meta{display:flex;flex-direction:column;gap:4px}
.hotel-id{font-size:.7rem;opacity:.65;letter-spacing:.08em;text-transform:uppercase;font-weight:600}
.hotel-name-row{display:flex;align-items:center;gap:12px;margin-top:2px}
.hotel-name-row h2{font-size:1.25rem;font-weight:800}
.stars{color:var(--amber);font-size:.95rem;letter-spacing:2px}
.hotel-sub{font-size:.79rem;opacity:.7;margin-top:4px;display:flex;align-items:center;gap:6px}
.hotel-pill{background:rgba(255,255,255,.12);padding:2px 10px;border-radius:20px;font-size:.72rem;font-weight:600}
.hotel-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;transition:all var(--transition);white-space:nowrap;text-decoration:none;font-family:inherit}
.btn:hover{filter:brightness(1.08);transform:translateY(-1px)}
.btn:active{transform:scale(.97)}
.btn-white{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(4px)}
.btn-white:hover{background:rgba(255,255,255,.25)}
.btn-amber{background:var(--amber);color:var(--navy)}
.btn-coral{background:var(--coral);color:#fff}
.btn-teal{background:var(--teal);color:#fff}
.btn-navy{background:var(--navy);color:#fff}
.btn-brand{background:var(--brand);color:#fff}
.btn-ghost{background:transparent;color:var(--muted);border:1px solid var(--border)}
.btn-ghost:hover{background:#f4f6fa;color:var(--text)}
.btn-sm{padding:5px 12px;font-size:.78rem}
.btn-xs{padding:3px 8px;font-size:.73rem;border-radius:6px}

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.tabs{display:flex;border-bottom:2px solid var(--border);background:#fafcfd;padding:0 24px;overflow-x:auto;gap:4px}
.tab{padding:13px 18px;font-size:.83rem;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;white-space:nowrap;transition:all var(--transition);display:flex;align-items:center;gap:6px}
.tab:hover{color:var(--navy)}
.tab.active{color:var(--teal);border-color:var(--teal)}
.tab-panel{display:none;padding:24px}
.tab-panel.active{display:block}

/* ── Panel top ───────────────────────────────────────────────────────────── */
.panel-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.panel-top h3{font-size:.95rem;font-weight:700;color:var(--navy)}

/* ── Room Table ──────────────────────────────────────────────────────────── */
.rtable{width:100%;border-collapse:collapse;font-size:.82rem}
.rtable th{background:#f0f4f8;padding:10px 13px;text-align:left;font-weight:700;color:var(--muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;white-space:nowrap;border-bottom:2px solid var(--border)}
.rtable td{padding:12px 13px;border-bottom:1px solid #f0f2f6;vertical-align:middle}
.rtable tr:last-child td{border-bottom:none}
.rtable tr:hover td{background:#f8fbff}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700}
.b-green{background:#d4edda;color:#145c2c}
.b-red{background:#f8d7da;color:#721c24}
.b-orange{background:#fff3cd;color:#856404}
.b-blue{background:#dbeafe;color:#1e40af}
.b-gray{background:#e9ecef;color:#495057}
.b-purple{background:#ede9fe;color:#5b21b6}
.price-cell{font-size:.77rem;line-height:1.9}
.price-cell span{display:inline-block;background:var(--teal-l);color:var(--teal-d);padding:1px 8px;border-radius:5px;margin:1px 2px;font-weight:600}
.eb-tag{font-size:.76rem;line-height:1.7}

/* ── Prices sub-table in modal ───────────────────────────────────────────── */
.price-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.price-row{display:flex;flex-direction:column;gap:5px}
.price-row label{font-size:.74rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.price-row input{border:1px solid var(--border);border-radius:7px;padding:8px 11px;font-size:.88rem;font-family:inherit;color:var(--text);transition:border-color var(--transition)}
.price-row input:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(42,157,143,.12)}

/* ── Availability grid ────────────────────────────────────────────────────── */
.avail-legend{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px;font-size:.77rem;align-items:center}
.ldot{width:13px;height:13px;border-radius:3px;display:inline-block;vertical-align:middle;margin-right:3px}
.avail-scroll{overflow-x:auto;border-radius:10px;box-shadow:inset 0 0 0 1px var(--border)}
.agrid{width:100%;border-collapse:collapse;font-size:.77rem;min-width:900px}
.agrid th{background:var(--navy);color:#fff;padding:8px 7px;text-align:center;white-space:nowrap;font-size:.71rem;font-weight:700;letter-spacing:.03em}
.agrid th.wkfri{background:#5c3d8f}
.agrid th.wksat{background:#8b2252}
.agrid th.tdy{background:var(--teal)}
.agrid td{padding:5px 6px;text-align:center;border:1px solid #eaedf2;background:#fff;min-width:62px}
.agrid td.tname{background:#f0f7f5;font-weight:700;color:var(--navy);text-align:left;padding-left:10px;font-size:.79rem;white-space:nowrap;min-width:160px}
.agrid td.ttype{background:#f7f9fb;font-weight:700;font-size:.68rem;text-transform:uppercase;color:var(--muted);text-align:left;padding-left:10px;white-space:nowrap;min-width:80px}
.agrid .sold{background:#fde8e8;color:#c0392b;font-weight:700}
.agrid .partial{background:#fff3cd;color:#856404;font-weight:600}
.agrid td input[type=number]{width:48px;border:1px solid var(--border);border-radius:5px;padding:3px 4px;font-size:.75rem;text-align:center;font-family:inherit;transition:border-color var(--transition)}
.agrid td input:focus{outline:none;border-color:var(--teal)}
.occ-row td{background:var(--navy);color:#7dcfca;font-weight:700;font-size:.72rem;text-align:center}
.occ-row td:first-child{text-align:left;padding-left:10px}

/* ── Rate Calendar ────────────────────────────────────────────────────────── */
.cal-hdr{display:flex;align-items:center;gap:12px;margin-bottom:18px;flex-wrap:wrap}
.cal-hdr h3{font-size:1rem;font-weight:700;color:var(--navy);min-width:130px;text-align:center}
.day-rule{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#f4f6fa;border:1px solid var(--border);padding:11px 16px;border-radius:10px;margin-bottom:16px}
.day-rule label{font-size:.8rem;font-weight:600;display:flex;align-items:center;gap:4px;cursor:pointer;color:var(--text)}
.room-plan-bar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px;background:#f8f9fc;padding:12px 16px;border-radius:10px;border:1px solid var(--border)}
.room-plan-bar select{border:1px solid var(--border);border-radius:7px;padding:7px 12px;font-size:.83rem;font-family:inherit;color:var(--text);background:#fff}
.room-plan-bar label{font-size:.8rem;font-weight:700;color:var(--muted)}

.rcal{width:100%;border-collapse:collapse;table-layout:fixed}
.rcal th{background:var(--navy);color:#fff;padding:10px 3px;text-align:center;font-size:.78rem;font-weight:700}
.rcal td{border:1px solid var(--border);vertical-align:top;padding:6px 4px;min-width:86px;transition:background var(--transition)}
.rcal td.empty{background:#f8f9fa}
.rcal td.fri-c{background:var(--fri)}
.rcal td.sat-c{background:var(--sat)}
.rcal td.sun-c{background:var(--sun)}
.rcal td.today-c{outline:2.5px solid var(--teal);outline-offset:-2px;border-radius:2px}
.dnum{font-size:.7rem;font-weight:700;color:var(--muted);margin-bottom:5px}
.rcal input[type=number]{width:100%;border:1px solid #cdd5db;border-radius:6px;padding:5px 4px;font-size:.8rem;text-align:center;background:transparent;font-family:inherit;transition:border-color var(--transition)}
.rcal input:focus{outline:none;border-color:var(--teal);background:#fff}

/* ── Forms & Modals ──────────────────────────────────────────────────────── */
.overlay{display:none;position:fixed;inset:0;background:rgba(10,20,50,.55);backdrop-filter:blur(4px);z-index:300;align-items:center;justify-content:center;padding:16px}
.overlay.open{display:flex;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal{background:#fff;border-radius:16px;box-shadow:0 16px 60px rgba(0,0,0,.25);width:min(620px,96vw);max-height:90vh;overflow-y:auto;padding:30px;animation:slideUp .25s ease}
.modal.wide{width:min(780px,96vw)}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-hdr{margin-bottom:22px;padding-bottom:16px;border-bottom:1px solid var(--border)}
.modal-hdr h3{font-size:1.1rem;font-weight:800;color:var(--navy)}
.modal-hdr p{font-size:.8rem;color:var(--muted);margin-top:4px}
.mfooter{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;border-top:1px solid var(--border);padding-top:18px}

/* ── Wizard ──────────────────────────────────────────────────────────────── */
.steps{display:flex;gap:0;margin-bottom:28px;overflow:hidden;border-radius:9px;border:1px solid var(--border)}
.step{flex:1;padding:10px 6px;text-align:center;font-size:.74rem;font-weight:700;background:#f0f2f7;color:var(--muted);border-right:1px solid var(--border);transition:all var(--transition);cursor:default}
.step:last-child{border-right:none}
.step.done{background:var(--teal-l);color:var(--teal-d)}
.step.active{background:var(--navy);color:#fff}
.wiz-panel{display:none}
.wiz-panel.active{display:block;animation:slideUp .2s ease}

/* ── Form elements ───────────────────────────────────────────────────────── */
.frow{margin-bottom:16px}
.frow label{display:block;font-size:.76rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.frow input,.frow select,.frow textarea{width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:8px;font-size:.88rem;color:var(--text);font-family:inherit;transition:border-color var(--transition),box-shadow var(--transition)}
.frow input:focus,.frow select:focus,.frow textarea:focus{outline:none;border-color:var(--teal);box-shadow:0 0 0 3px rgba(42,157,143,.1)}
.fgrid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.fgrid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}

.star-pick{display:flex;flex-direction:row-reverse;gap:5px;margin-top:4px;justify-content:flex-end}
.star-pick input{display:none}
.star-pick label{cursor:pointer;font-size:1.5rem;color:#ddd;transition:color .15s;line-height:1}
.star-pick input:checked ~ label,.star-pick label:hover,.star-pick label:hover ~ label{color:var(--amber)}

.section-divider{font-size:.75rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);padding:12px 0 8px;border-top:1px solid var(--border);margin-top:8px}

/* ── Toggle ──────────────────────────────────────────────────────────────── */
.toggle-wrap{display:flex;align-items:center;gap:10px}
.toggle{width:44px;height:23px;border-radius:12px;background:#cbd5e1;position:relative;cursor:pointer;transition:background var(--transition);border:none;flex-shrink:0}
.toggle.on{background:var(--teal)}
.toggle::after{content:'';position:absolute;left:3px;top:3px;width:17px;height:17px;border-radius:50%;background:#fff;transition:left var(--transition);box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle.on::after{left:24px}

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast{
  position:fixed;bottom:28px;right:28px;
  background:var(--navy);color:#fff;
  padding:13px 22px;border-radius:10px;
  font-size:.86rem;font-weight:600;
  box-shadow:0 6px 24px rgba(0,0,0,.25);
  transform:translateY(80px);opacity:0;
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  z-index:500;display:flex;align-items:center;gap:8px;
  max-width:340px;
}
.toast.show{transform:translateY(0);opacity:1}
.toast.err{background:var(--coral-d)}
.toast.ok{background:var(--teal-d)}

/* ── New room row (wizard step 2) ────────────────────────────────────────── */
.room-row-item{background:#f8f9fc;border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;position:relative}
.room-row-item .rr-remove{position:absolute;top:12px;right:12px;background:none;border:none;cursor:pointer;color:var(--coral);font-size:1.1rem}
.room-row-item .rr-remove:hover{color:var(--coral-d)}

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media(max-width:900px){
  .stats-row{grid-template-columns:repeat(2,1fr)}
  .fgrid2,.fgrid3{grid-template-columns:1fr}
  .price-grid{grid-template-columns:1fr}
  .hotel-header{flex-direction:column}
}
@media(max-width:580px){
  .topbar{padding:0 14px}
  .topbar-nav{display:none}
  .stats-row{grid-template-columns:1fr 1fr}
  .page{padding:16px 12px 40px}
  .tab-panel{padding:16px}
  .hotel-header{padding:16px}
  .page-hdr{flex-direction:column;align-items:flex-start}
}
</style>
</head>
<body>

<!-- ═══ TOP BAR ══════════════════════════════════════════════════════════════ -->
<nav class="topbar">
  <div class="topbar-brand">
    <div class="brand-icon"><i class="bi bi-buildings"></i></div>
    <span>Hotel Manager</span>
  </div>
  <div class="topbar-r">
    <nav class="topbar-nav" style="display:flex;gap:4px;">
      <a href="/dashboard.php"><i class="bi bi-grid-1x2"></i> Dashboard</a>
      <a href="/booking-details.php"><i class="bi bi-calendar-check"></i> Bookings</a>
      <a href="/listing.php"><i class="bi bi-list-ul"></i> Listings</a>
    </nav>
    <div class="topbar-account" id="topbarAccount">
      <button class="topbar-profile-btn" id="topbarProfileBtn" type="button" aria-haspopup="true" aria-expanded="false">
        <span class="account-name"><?=htmlspecialchars($currentUser)?></span>
        <div class="avatar"><?=$userInitial?></div>
      </button>
      <div class="topbar-profile-menu" id="topbarProfileMenu">
        <a class="topbar-profile-link" href="/dashboard.php"><i class="bi bi-person-circle"></i> Profile</a>
        <a class="topbar-profile-link" href="/booking-details.php"><i class="bi bi-clock-history"></i> Booking History</a>
        <a class="topbar-profile-link" href="/listing.php"><i class="bi bi-building"></i> Hotel Listings</a>
        <a class="topbar-profile-link logout" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </div>
</nav>

<!-- ═══ PAGE ══════════════════════════════════════════════════════════════════ -->
<div class="page">

  <!-- Page Header -->
  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1><i class="bi bi-building-fill-gear" style="color:var(--teal);"></i> Hotel Room Manager</h1>
      <p>Manage room categories, live availability, and rate calendars for all properties.</p>
    </div>
    <button class="btn btn-brand" onclick="openAddHotel()">
      <i class="bi bi-plus-lg"></i> Add New Hotel
    </button>
  </div>

  <!-- Summary Stats -->
  <?php
  $totalHotels = count($hotels);
  $totalRooms  = array_sum(array_map(fn($h)=>array_sum(array_column($h['rooms'],'total')), $hotels));
  $totalAvail  = array_sum(array_map(fn($h)=>array_sum(array_column($h['rooms'],'available')), $hotels));
  $totalBooked = array_sum(array_map(fn($h)=>array_sum(array_column($h['rooms'],'booked')), $hotels));
  $occPctAll   = $totalRooms > 0 ? round($totalBooked / $totalRooms * 100) : 0;
  ?>
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:#ede9fe;color:var(--brand);"><i class="bi bi-buildings"></i></div>
      <div><div class="stat-label">Total Hotels</div><div class="stat-value"><?=$totalHotels?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-door-open"></i></div>
      <div><div class="stat-label">Total Rooms</div><div class="stat-value"><?=$totalRooms?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#d4edda;color:#155724;"><i class="bi bi-check2-circle"></i></div>
      <div><div class="stat-label">Available</div><div class="stat-value" style="color:#155724;"><?=$totalAvail?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:#fff3cd;color:#856404;"><i class="bi bi-graph-up-arrow"></i></div>
      <div><div class="stat-label">Occupancy</div><div class="stat-value" style="color:var(--teal);"><?=$occPctAll?>%</div></div>
    </div>
  </div>

<?php foreach($hotels as $hi => $hotel):
  $hTotalRooms = array_sum(array_column($hotel['rooms'],'total'));
  $hBooked     = array_sum(array_column($hotel['rooms'],'booked'));
  $hAvail      = array_sum(array_column($hotel['rooms'],'available'));
  $hOccPct     = $hTotalRooms > 0 ? round($hBooked/$hTotalRooms*100).'%' : '0%';
?>

<div class="hotel-card" id="hcard-<?=$hi?>">

  <!-- Hotel Header -->
  <div class="hotel-header">
    <div class="hotel-meta">
      <div class="hotel-id"><?=htmlspecialchars($hotel['id'])?></div>
      <div class="hotel-name-row">
        <h2><?=htmlspecialchars($hotel['name'])?></h2>
        <span class="stars"><?=str_repeat('★',$hotel['star'])?><?=str_repeat('☆',5-$hotel['star'])?></span>
      </div>
      <div class="hotel-sub">
        <i class="bi bi-geo-alt-fill"></i> <?=htmlspecialchars($hotel['city'])?>
        <span class="hotel-pill"><?=count($hotel['rooms'])?> Room Categories</span>
        <span class="hotel-pill">Occ: <?=$hOccPct?></span>
        <span class="hotel-pill"><?=$hAvail?> Available</span>
      </div>
    </div>
    <div class="hotel-actions">
      <button class="btn btn-white btn-sm" onclick="openRoomModal(<?=$hi?>,null)">
        <i class="bi bi-plus-lg"></i> Add Room
      </button>
      <button class="btn btn-amber btn-sm" onclick="openBulk(<?=$hi?>)">
        <i class="bi bi-arrow-up-circle"></i> Bulk Rates
      </button>
      <button class="btn btn-white btn-sm" onclick="saveAll(<?=$hi?>)">
        <i class="bi bi-floppy2"></i> Save
      </button>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <div class="tab active" onclick="switchTab(this,'tp-rooms-<?=$hi?>')">
      <i class="bi bi-door-open"></i> Room Categories
    </div>
    <div class="tab" onclick="switchTab(this,'tp-avail-<?=$hi?>')">
      <i class="bi bi-grid-3x3"></i> Availability
    </div>
    <div class="tab" onclick="switchTab(this,'tp-rates-<?=$hi?>')">
      <i class="bi bi-calendar2-week"></i> Rate Calendar
    </div>
  </div>

  <!-- ══ TAB: Room Categories ═══════════════════════════════════════════════ -->
  <div class="tab-panel active" id="tp-rooms-<?=$hi?>">
    <div class="panel-top">
      <h3><i class="bi bi-door-open" style="color:var(--teal);"></i> Room Categories</h3>
      <button class="btn btn-teal btn-sm" onclick="openRoomModal(<?=$hi?>,null)">
        <i class="bi bi-plus-lg"></i> Add Category
      </button>
    </div>
    <div style="overflow-x:auto;border-radius:10px;border:1px solid var(--border);">
    <table class="rtable" id="rtable-<?=$hi?>">
      <thead>
        <tr>
          <th>Room Name</th>
          <th>Bed</th>
          <th>Size</th>
          <th style="text-align:center">Total</th>
          <th style="text-align:center">Available</th>
          <th style="text-align:center">Booked</th>
          <th style="text-align:center">Blocked</th>
          <th>Prices by Meal Plan</th>
          <th>Extra Bed</th>
          <th style="text-align:center">Actions</th>
        </tr>
      </thead>
      <tbody id="rbody-<?=$hi?>">
        <?php foreach($hotel['rooms'] as $room): ?>
        <tr id="rrow-<?=$room['id']?>"
            data-name="<?=htmlspecialchars($room['name'],ENT_QUOTES)?>"
            data-total="<?=$room['total']?>"
            data-avail="<?=$room['available']?>"
            data-size="<?=htmlspecialchars($room['size'],ENT_QUOTES)?>"
            data-bed="<?=$room['bed']?>"
            data-prices="<?=htmlspecialchars(json_encode($room['prices']),ENT_QUOTES)?>"
            data-ebprice="<?=$room['extra_bed']['price']?>"
            data-ebmax="<?=$room['extra_bed']['max']?>"
            data-ebon="<?=$room['extra_bed']['allowed']?'1':'0'?>">
          <td style="font-weight:700;max-width:200px"><?=htmlspecialchars($room['name'])?></td>
          <td><span class="badge b-blue"><i class="bi bi-moon"></i> <?=$room['bed']?></span></td>
          <td style="color:var(--muted);font-size:.77rem;white-space:nowrap"><?=$room['size']?></td>
          <td style="text-align:center"><strong><?=$room['total']?></strong></td>
          <td style="text-align:center"><span class="badge b-green"><?=$room['available']?></span></td>
          <td style="text-align:center"><span class="badge b-red"><?=$room['booked']?></span></td>
          <td style="text-align:center"><span class="badge b-orange"><?=$room['blocked']?></span></td>
          <td class="price-cell">
            <?php foreach($mealPlans as $code=>$label):
              if(isset($room['prices'][$code])): ?>
              <span title="<?=htmlspecialchars($label)?>"><?=$code?> ₹<?=number_format($room['prices'][$code])?></span>
            <?php endif; endforeach; ?>
          </td>
          <td class="eb-tag">
            <?php if($room['extra_bed']['allowed']): ?>
              <span style="color:var(--teal);font-weight:700;"><i class="bi bi-check-circle-fill"></i></span>
              ₹<?=number_format($room['extra_bed']['price'])?>/bed &nbsp; Max: <?=$room['extra_bed']['max']?>
            <?php else: ?>
              <span style="color:var(--muted);font-size:.73rem;"><i class="bi bi-x-circle"></i> Not allowed</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center">
            <div style="display:flex;gap:5px;justify-content:center">
              <button class="btn btn-teal btn-xs" onclick="openRoomModal(<?=$hi?>,<?=$room['id']?>)">
                <i class="bi bi-pencil"></i> Edit
              </button>
              <button class="btn btn-coral btn-xs" onclick="removeRoom(<?=$room['id']?>,<?=$hi?>)">
                <i class="bi bi-trash3"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- ══ TAB: Availability ══════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tp-avail-<?=$hi?>">
    <div class="avail-legend">
      <span><span class="ldot" style="background:#e74c3c"></span>Sold Out</span>
      <span><span class="ldot" style="background:#f39c12"></span>Partial (&lt;3)</span>
      <span><span class="ldot" style="background:#95a5a6"></span>Blocked</span>
      <span><span class="ldot" style="background:#2ecc71"></span>Available</span>
      <span><span class="ldot" style="background:#5c3d8f"></span>Fri</span>
      <span><span class="ldot" style="background:#8b2252"></span>Sat</span>
      <span style="margin-left:auto;font-size:.73rem;color:var(--muted);"><i class="bi bi-pencil-square"></i> Click cell to edit</span>
    </div>
    <div class="avail-scroll">
    <table class="agrid">
      <thead>
        <tr>
          <th style="min-width:160px">Room Category</th>
          <th style="min-width:80px">Type</th>
          <?php foreach($availDates as $dt):
            $dow = date('D', strtotime($dt));
            $cls = ($dow==='Fri') ? 'wkfri' : (($dow==='Sat') ? 'wksat' : (($dt===$todayStr) ? 'tdy' : ''));
          ?>
          <th class="<?=$cls?>"><?=strtoupper(substr($dow,0,3))?><br><?=date('j',strtotime($dt))?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($hotel['rooms'] as $room): $tot=$room['total']; ?>
        <tr>
          <td class="tname" rowspan="3">
            <?=htmlspecialchars($room['name'])?>
            <br><small style="font-weight:400;color:var(--muted);font-size:.7rem;">(<?=$tot?> total)</small>
          </td>
          <td class="ttype"><i class="bi bi-check2"></i> Available</td>
          <?php foreach($availDates as $dt):
            $a = $room['available'];
            $cls2 = ($a===0) ? 'sold' : (($a<3) ? 'partial' : '');
          ?><td class="<?=$cls2?>"><input type="number" value="<?=$a?>" min="0" max="<?=$tot?>"></td><?php endforeach; ?>
        </tr>
        <tr>
          <td class="ttype"><i class="bi bi-person-check"></i> Booked</td>
          <?php foreach($availDates as $dt): ?><td style="font-weight:600;color:#e74c3c;"><?=$room['booked']?></td><?php endforeach; ?>
        </tr>
        <tr>
          <td class="ttype"><i class="bi bi-lock"></i> Blocked</td>
          <?php foreach($availDates as $dt): ?>
            <td><input type="number" value="<?=$room['blocked']?>" min="0" max="<?=$tot?>"></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <tr class="occ-row">
          <td><i class="bi bi-graph-up-arrow"></i> Occupancy %</td>
          <td></td>
          <?php foreach($availDates as $dt): ?><td><?=$hOccPct?></td><?php endforeach; ?>
        </tr>
      </tbody>
    </table>
    </div>
    <div style="margin-top:14px;text-align:right">
      <button class="btn btn-teal" onclick="showToast('Availability updated ✓','ok')">
        <i class="bi bi-floppy2"></i> Update Availability
      </button>
    </div>
  </div>

  <!-- ══ TAB: Rate Calendar ══════════════════════════════════════════════════ -->
  <div class="tab-panel" id="tp-rates-<?=$hi?>">

    <!-- Room + Meal Plan Selector -->
    <div class="room-plan-bar">
      <label><i class="bi bi-door-open"></i> Room:</label>
      <select id="cal-room-<?=$hi?>" onchange="refreshCal(<?=$hi?>)">
        <?php foreach($hotel['rooms'] as $r): ?>
        <option value="<?=$r['id']?>"><?=htmlspecialchars($r['name'])?></option>
        <?php endforeach; ?>
      </select>
      <label><i class="bi bi-fork-knife"></i> Meal Plan:</label>
      <select id="cal-plan-<?=$hi?>" onchange="refreshCal(<?=$hi?>)">
        <?php foreach($mealPlans as $code=>$label): ?>
        <option value="<?=$code?>"><?=$label?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Day Override Rule -->
    <div class="day-rule">
      <strong style="font-size:.8rem;color:var(--navy);"><i class="bi bi-calendar2-day"></i> Override by day:</strong>
      <label><input type="checkbox" id="dr-fri-<?=$hi?>" checked> Fri</label>
      <label><input type="checkbox" id="dr-sat-<?=$hi?>" checked> Sat</label>
      <label><input type="checkbox" id="dr-sun-<?=$hi?>"> Sun</label>
      <input type="number" id="dr-val-<?=$hi?>" value="700" placeholder="Rate ₹"
        style="width:90px;border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:.83rem;font-family:inherit;">
      <button class="btn btn-amber btn-sm" onclick="applyDayRule(<?=$hi?>)">
        <i class="bi bi-check2-all"></i> Apply
      </button>
    </div>

    <!-- Month Nav -->
    <div class="cal-hdr">
      <button class="btn btn-ghost btn-sm" onclick="prevMonth(<?=$hi?>)">
        <i class="bi bi-chevron-left"></i> Prev
      </button>
      <h3 id="cal-title-<?=$hi?>"><?=$calMonthStr?></h3>
      <button class="btn btn-ghost btn-sm" onclick="nextMonth(<?=$hi?>)">
        Next <i class="bi bi-chevron-right"></i>
      </button>
      <span style="font-size:.73rem;color:var(--muted);margin-left:auto;">
        <span style="background:var(--fri);padding:2px 7px;border-radius:4px;">Fri</span>
        <span style="background:var(--sat);padding:2px 7px;border-radius:4px;">Sat</span>
        <span style="background:var(--sun);padding:2px 7px;border-radius:4px;">Sun</span>
        <span style="outline:2px solid var(--teal);padding:2px 7px;border-radius:4px;">Today</span>
      </span>
    </div>

    <?php $days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; ?>
    <table class="rcal" id="rcal-<?=$hi?>">
      <thead>
        <tr><?php foreach($days as $d): ?><th><?=$d?></th><?php endforeach; ?></tr>
      </thead>
      <tbody>
      <?php
        $col = $firstDow;
        echo '<tr>';
        for($e=0;$e<$col;$e++) echo '<td class="empty"></td>';
        for($day=1;$day<=$daysInMonth;$day++){
          $ds    = sprintf('%04d-%02d-%02d',$calYear,$calMonth,$day);
          $price = $hotel['rates'][$ds] ?? ($hotel['rooms'][0]['prices']['EP'] ?? 5000);
          $dow2  = $col % 7;
          $cls   = '';
          if($dow2===5)      $cls = 'fri-c';
          elseif($dow2===6)  $cls = 'sat-c';
          elseif($dow2===0)  $cls = 'sun-c';
          if($ds===$todayStr) $cls .= ' today-c';
          echo '<td class="'.trim($cls).'" data-date="'.$ds.'">';
          echo '<div class="dnum">'.$day.'</div>';
          echo '<input type="number" class="rate-inp" data-date="'.$ds.'" name="rate['.$hi.']['.$ds.']" value="'.intval($price).'">';
          echo '</td>';
          $col++;
          if($col%7===0 && $day<$daysInMonth) echo '</tr><tr>';
        }
        $rem = 7 - ($col%7);
        if($rem < 7) for($e=0;$e<$rem;$e++) echo '<td class="empty"></td>';
        echo '</tr>';
      ?>
      </tbody>
    </table>
    <div style="margin-top:18px;display:flex;justify-content:flex-end;gap:10px;">
      <button class="btn btn-ghost" onclick="copyRatesToAll(<?=$hi?>)">
        <i class="bi bi-clipboard2-check"></i> Copy to All Rooms
      </button>
      <button class="btn btn-teal" onclick="saveRates(<?=$hi?>)">
        <i class="bi bi-floppy2"></i> Save Rates
      </button>
    </div>
  </div>

</div><!-- /hotel-card -->
<?php endforeach; ?>
</div><!-- /page -->

<!-- ═══ MODAL: Add Hotel Wizard ══════════════════════════════════════════════ -->
<div class="overlay" id="modal-hotel">
<div class="modal wide">
  <div class="modal-hdr">
    <h3><i class="bi bi-building-add" style="color:var(--teal);"></i> Add New Hotel</h3>
    <p>Complete all steps to register a new property in the system.</p>
  </div>
  <div class="steps" id="wiz-steps">
    <div class="step active" id="ws-1">① Hotel Info</div>
    <div class="step" id="ws-2">② Room Categories</div>
    <div class="step" id="ws-3">③ Extra Bed</div>
    <div class="step" id="ws-4">④ Meal Plan Prices</div>
  </div>

  <!-- Step 1: Hotel Info -->
  <div class="wiz-panel active" id="wp-1">
    <div class="frow"><label>Hotel Name *</label><input type="text" id="hn-name" placeholder="e.g. The Grand Palace"></div>
    <div class="fgrid2">
      <div class="frow"><label>City *</label><input type="text" id="hn-city" placeholder="e.g. Goa"></div>
      <div class="frow"><label>State</label><input type="text" id="hn-state" placeholder="e.g. Goa"></div>
    </div>
    <div class="fgrid2">
      <div class="frow"><label>Pin Code</label><input type="text" id="hn-pin" placeholder="403001"></div>
      <div class="frow"><label>Phone</label><input type="tel" id="hn-phone" placeholder="+91 9876543210"></div>
    </div>
    <div class="frow"><label>Email</label><input type="email" id="hn-email" placeholder="reservations@hotel.com"></div>
    <div class="frow"><label>Website</label><input type="url" id="hn-web" placeholder="https://..."></div>
    <div class="frow">
      <label>Star Rating</label>
      <div class="star-pick" id="hn-stars">
        <?php for($s=5;$s>=1;$s--): ?>
        <input type="radio" name="hstar" id="hs<?=$s?>" value="<?=$s?>" <?=$s==3?'checked':''?>>
        <label for="hs<?=$s?>">★</label>
        <?php endfor; ?>
      </div>
    </div>
    <div class="frow"><label>Description</label><textarea id="hn-desc" rows="3" placeholder="Brief property description..."></textarea></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="closeHotelModal()">Cancel</button>
      <button class="btn btn-navy" onclick="wizNext(1)">Next <i class="bi bi-chevron-right"></i></button>
    </div>
  </div>

  <!-- Step 2: Room Categories -->
  <div class="wiz-panel" id="wp-2">
    <div class="panel-top" style="margin-bottom:14px">
      <span style="font-size:.84rem;color:var(--muted);">Add at least one room category.</span>
      <button class="btn btn-teal btn-sm" onclick="addRoomRow()"><i class="bi bi-plus-lg"></i> Add Room</button>
    </div>
    <div id="new-rooms-list"></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="wizPrev(2)"><i class="bi bi-chevron-left"></i> Back</button>
      <button class="btn btn-navy" onclick="wizNext(2)">Next <i class="bi bi-chevron-right"></i></button>
    </div>
  </div>

  <!-- Step 3: Extra Bed -->
  <div class="wiz-panel" id="wp-3">
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:18px;"><i class="bi bi-info-circle"></i> Configure extra bed options per room category.</p>
    <div id="eb-config-list"></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="wizPrev(3)"><i class="bi bi-chevron-left"></i> Back</button>
      <button class="btn btn-navy" onclick="wizNext(3)">Next <i class="bi bi-chevron-right"></i></button>
    </div>
  </div>

  <!-- Step 4: Meal Plans & Prices -->
  <div class="wiz-panel" id="wp-4">
    <p style="font-size:.82rem;color:var(--muted);margin-bottom:14px;"><i class="bi bi-fork-knife"></i> Set ₹ prices per room per meal plan.</p>
    <div id="meal-price-list"></div>
    <div class="mfooter">
      <button class="btn btn-ghost" onclick="wizPrev(4)"><i class="bi bi-chevron-left"></i> Back</button>
      <button class="btn btn-teal" onclick="finishHotel()"><i class="bi bi-check2-circle"></i> Create Hotel</button>
    </div>
  </div>
</div>
</div>

<!-- ═══ MODAL: Add / Edit Room Category ══════════════════════════════════════ -->
<div class="overlay" id="modal-room">
<div class="modal wide">
  <div class="modal-hdr">
    <h3 id="rm-modal-title"><i class="bi bi-door-open" style="color:var(--teal);"></i> Add Room Category</h3>
    <p>Fill in room details, pricing per meal plan, and extra bed options.</p>
  </div>
  <input type="hidden" id="rm-hi">
  <input type="hidden" id="rm-rid">

  <div class="fgrid2">
    <div class="frow" style="grid-column:1/-1"><label>Room Name *</label><input type="text" id="rm-name" placeholder="e.g. Deluxe King Room"></div>
    <div class="frow">
      <label>Bed Type</label>
      <select id="rm-bed">
        <option>Double</option><option>Twin</option><option>King</option>
        <option>Queen</option><option>Single</option><option>Bunk</option>
      </select>
    </div>
    <div class="frow"><label>Room Size</label><input type="text" id="rm-size" placeholder="e.g. 280 sq ft"></div>
    <div class="frow"><label>Total Rooms *</label><input type="number" id="rm-total" value="10" min="1"></div>
    <div class="frow"><label>Available</label><input type="number" id="rm-avail" value="10" min="0"></div>
  </div>

  <div class="section-divider">Prices by Meal Plan (₹ per night)</div>
  <div class="price-grid">
    <?php foreach($mealPlans as $code=>$label): ?>
    <div class="price-row">
      <label><?=htmlspecialchars($label)?></label>
      <input type="number" id="rmp-<?=$code?>" placeholder="0" min="0">
    </div>
    <?php endforeach; ?>
  </div>

  <div class="section-divider">Extra Bed Settings</div>
  <div class="fgrid3">
    <div class="frow">
      <label>Allow Extra Bed</label>
      <div class="toggle-wrap" style="margin-top:8px">
        <button type="button" class="toggle" id="rm-eb-toggle" onclick="toggleEB()"></button>
        <span id="rm-eb-label" style="font-size:.82rem;color:var(--muted)">Not Allowed</span>
      </div>
    </div>
    <div class="frow"><label>Price per Extra Bed ₹</label><input type="number" id="rm-eb-price" value="0" min="0"></div>
    <div class="frow"><label>Max Extra Beds</label><input type="number" id="rm-eb-max" value="0" min="0" max="3"></div>
  </div>

  <div class="mfooter">
    <button class="btn btn-ghost" onclick="closeRoomModal()">Cancel</button>
    <button class="btn btn-teal" onclick="saveRoomModal()"><i class="bi bi-floppy2"></i> Save Room</button>
  </div>
</div>
</div>

<!-- ═══ MODAL: Bulk Rate Update ════════════════════════════════════════════════ -->
<div class="overlay" id="modal-bulk">
<div class="modal">
  <div class="modal-hdr">
    <h3><i class="bi bi-arrow-up-circle" style="color:var(--amber-d);"></i> Bulk Rate Update</h3>
    <p>Apply a rate across a date range and selected weekdays.</p>
  </div>
  <input type="hidden" id="bulk-hi">
  <div class="fgrid2">
    <div class="frow"><label>From Date</label><input type="date" id="bulk-from" value="2026-06-01"></div>
    <div class="frow"><label>To Date</label><input type="date" id="bulk-to" value="2026-06-30"></div>
  </div>
  <div class="frow"><label>Room Category</label><select id="bulk-room"><option value="all">All Rooms</option></select></div>
  <div class="frow">
    <label>Meal Plan</label>
    <select id="bulk-plan">
      <?php foreach($mealPlans as $c=>$l): ?><option value="<?=$c?>"><?=htmlspecialchars($l)?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="frow"><label>New Rate (₹)</label><input type="number" id="bulk-rate" value="5000" min="0"></div>
  <div class="frow">
    <label>Apply to Days</label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
      <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
      <label style="font-size:.82rem;display:flex;align-items:center;gap:5px;cursor:pointer;font-weight:500;">
        <input type="checkbox" value="<?=$d?>" checked style="accent-color:var(--teal);"> <?=$d?>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="mfooter">
    <button class="btn btn-ghost" onclick="closeBulk()">Cancel</button>
    <button class="btn btn-amber" onclick="applyBulk()"><i class="bi bi-check2-all"></i> Apply Rates</button>
  </div>
</div>
</div>

<!-- Toast -->
<div class="toast" id="toast"><i class="bi bi-check-circle-fill"></i><span id="toast-msg">Done</span></div>

<!-- ════════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════════════════════════════════════ -->
<script>
/* ── Data from PHP ─────────────────────────────────────────────────────────── */
const mealPlanKeys   = <?=json_encode(array_keys($mealPlans))?>;
const mealPlanLabels = <?=json_encode($mealPlans)?>;

/* ── Utilities ─────────────────────────────────────────────────────────────── */
const $$ = id => document.getElementById(id);

function esc(s){
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type='ok'){
  const t = $$('toast'), m = $$('toast-msg');
  m.textContent = msg;
  t.className   = 'toast ' + type;
  requestAnimationFrame(() => t.classList.add('show'));
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3400);
}

function switchTab(el, panelId){
  const card = el.closest('.hotel-card');
  card.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  card.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
  $$(panelId).classList.add('active');
}

/* ── Close modal on backdrop click ─────────────────────────────────────────── */
document.querySelectorAll('.overlay').forEach(ov => {
  ov.addEventListener('click', e => { if(e.target === ov) ov.classList.remove('open'); });
});
document.addEventListener('keydown', e => {
  if(e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(o => o.classList.remove('open'));
});

/* ── Extra Bed Toggle (Room Modal) ─────────────────────────────────────────── */
let ebOn = false;
function toggleEB(){
  ebOn = !ebOn;
  $$('rm-eb-toggle').classList.toggle('on', ebOn);
  $$('rm-eb-label').textContent = ebOn ? 'Allowed' : 'Not Allowed';
}

/* ── Room Modal ─────────────────────────────────────────────────────────────── */
function openRoomModal(hi, rid){
  $$('rm-hi').value  = hi;
  $$('rm-rid').value = rid || '';
  $$('rm-modal-title').innerHTML = rid
    ? '<i class="bi bi-pencil-square" style="color:var(--teal);"></i> Edit Room Category'
    : '<i class="bi bi-door-open" style="color:var(--teal);"></i> Add Room Category';

  // Reset all fields
  ['rm-name','rm-size','rm-eb-price','rm-eb-max'].forEach(id => { if($$(id)) $$(id).value = ''; });
  $$('rm-total').value = 10; $$('rm-avail').value = 10;
  mealPlanKeys.forEach(c => { if($$('rmp-'+c)) $$('rmp-'+c).value = ''; });
  ebOn = false; $$('rm-eb-toggle').classList.remove('on'); $$('rm-eb-label').textContent = 'Not Allowed';
  $$('rm-bed').value = 'Double';

  if(rid){
    const row = $$('rrow-' + rid);
    if(row){
      $$('rm-name').value  = row.dataset.name  || '';
      $$('rm-total').value = row.dataset.total || 10;
      $$('rm-avail').value = row.dataset.avail || 10;
      $$('rm-size').value  = row.dataset.size  || '';
      $$('rm-bed').value   = row.dataset.bed   || 'Double';
      try{
        const pr = JSON.parse(row.dataset.prices || '{}');
        Object.keys(pr).forEach(c => { if($$('rmp-'+c)) $$('rmp-'+c).value = pr[c]; });
      }catch(e){}
      const ebP = parseFloat(row.dataset.ebprice || 0);
      const ebM = parseInt(row.dataset.ebmax || 0);
      const ebA = row.dataset.ebon === '1';
      if(ebA){ ebOn = true; $$('rm-eb-toggle').classList.add('on'); $$('rm-eb-label').textContent = 'Allowed'; }
      $$('rm-eb-price').value = ebP;
      $$('rm-eb-max').value   = ebM;
    }
  }
  $$('modal-room').classList.add('open');
  setTimeout(() => $$('rm-name').focus(), 200);
}

function closeRoomModal(){ $$('modal-room').classList.remove('open'); }

function saveRoomModal(){
  const hi   = $$('rm-hi').value;
  const rid  = $$('rm-rid').value;
  const name = $$('rm-name').value.trim();
  if(!name){ showToast('Room name is required', 'err'); $$('rm-name').focus(); return; }

  const total   = parseInt($$('rm-total').value)   || 10;
  const avail   = parseInt($$('rm-avail').value)   || total;
  const size    = $$('rm-size').value.trim();
  const bed     = $$('rm-bed').value;
  const ebPrice = parseFloat($$('rm-eb-price').value || 0);
  const ebMax   = parseInt($$('rm-eb-max').value   || 0);
  const prices  = {};
  mealPlanKeys.forEach(c => {
    const v = parseFloat($$('rmp-'+c)?.value || 0);
    if(v > 0) prices[c] = v;
  });

  if(rid){
    const row = $$('rrow-' + rid);
    if(row){ renderRoomRow(row, {name, total, avail, size, bed, prices, ebOn, ebPrice, ebMax, hi}); }
    showToast('Room updated ✓', 'ok');
  } else {
    const newId  = 'new' + Date.now();
    const tbody  = $$('rbody-' + hi);
    const tr     = document.createElement('tr');
    tr.id        = 'rrow-' + newId;
    renderRoomRow(tr, {name, total, avail, size, bed, prices, ebOn, ebPrice, ebMax, hi});
    tbody.appendChild(tr);
    showToast('Room category added ✓', 'ok');
  }
  closeRoomModal();
}

function renderRoomRow(tr, {name, total, avail, size, bed, prices, ebOn, ebPrice, ebMax, hi}){
  const id     = tr.id.replace('rrow-','');
  const hiVal  = hi ?? tr.closest('.hotel-card')?.id?.replace('hcard-','') ?? '';

  // Store for re-edit
  tr.dataset.name    = name;
  tr.dataset.total   = total;
  tr.dataset.avail   = avail;
  tr.dataset.size    = size;
  tr.dataset.bed     = bed;
  tr.dataset.prices  = JSON.stringify(prices);
  tr.dataset.ebprice = ebPrice;
  tr.dataset.ebmax   = ebMax;
  tr.dataset.ebon    = ebOn ? '1' : '0';

  const priceHtml = Object.entries(prices).map(([c,v]) =>
    `<span title="${esc(mealPlanLabels[c]||c)}">${c} ₹${Number(v).toLocaleString('en-IN')}</span>`
  ).join('');

  const ebHtml = ebOn && (ebPrice > 0 || ebMax > 0)
    ? `<span style="color:var(--teal);font-weight:700;"><i class="bi bi-check-circle-fill"></i></span> ₹${Number(ebPrice).toLocaleString('en-IN')}/bed &nbsp; Max: ${ebMax}`
    : `<span style="color:var(--muted);font-size:.73rem;"><i class="bi bi-x-circle"></i> Not allowed</span>`;

  tr.innerHTML = `
    <td style="font-weight:700;max-width:200px">${esc(name)}</td>
    <td><span class="badge b-blue"><i class="bi bi-moon"></i> ${esc(bed)}</span></td>
    <td style="color:var(--muted);font-size:.77rem;white-space:nowrap">${esc(size)}</td>
    <td style="text-align:center"><strong>${total}</strong></td>
    <td style="text-align:center"><span class="badge b-green">${avail}</span></td>
    <td style="text-align:center"><span class="badge b-red">0</span></td>
    <td style="text-align:center"><span class="badge b-orange">0</span></td>
    <td class="price-cell">${priceHtml || '<span style="color:var(--muted);font-size:.73rem;">No prices set</span>'}</td>
    <td class="eb-tag">${ebHtml}</td>
    <td style="text-align:center">
      <div style="display:flex;gap:5px;justify-content:center">
        <button class="btn btn-teal btn-xs" onclick="openRoomModal(${hiVal},'${id}')">
          <i class="bi bi-pencil"></i> Edit
        </button>
        <button class="btn btn-coral btn-xs" onclick="removeRoom('${id}','${hiVal}')">
          <i class="bi bi-trash3"></i>
        </button>
      </div>
    </td>`;
}

function removeRoom(rid, hi){
  if(!confirm('Remove this room category?')) return;
  const row = $$('rrow-' + rid);
  if(row){
    row.style.transition = 'opacity .3s,transform .3s';
    row.style.opacity    = '0';
    row.style.transform  = 'translateX(-20px)';
    setTimeout(() => row.remove(), 320);
  }
  showToast('Room removed', 'ok');
}

/* ── Bulk Rate Modal ─────────────────────────────────────────────────────────── */
function openBulk(hi){
  $$('bulk-hi').value = hi;
  const card = $$('hcard-' + hi);
  const sel  = $$('bulk-room');
  sel.innerHTML = '<option value="all">All Rooms</option>';
  card.querySelectorAll('[id^="rrow-"]').forEach(row => {
    const opt       = document.createElement('option');
    opt.value       = row.id.replace('rrow-','');
    opt.textContent = row.querySelector('td:first-child')?.textContent?.trim() || 'Room';
    sel.appendChild(opt);
  });
  $$('modal-bulk').classList.add('open');
}
function closeBulk(){ $$('modal-bulk').classList.remove('open'); }

function applyBulk(){
  const hi    = $$('bulk-hi').value;
  const from  = new Date($$('bulk-from').value);
  const to    = new Date($$('bulk-to').value);
  const rate  = $$('bulk-rate').value;
  if(!rate){ showToast('Please enter a rate', 'err'); return; }

  const checks = document.querySelectorAll('#modal-bulk input[type=checkbox]:checked');
  const days   = [...checks].map(c => c.value);
  const dowMap = {Sun:0,Mon:1,Tue:2,Wed:3,Thu:4,Fri:5,Sat:6};

  let count = 0;
  $$('rcal-' + hi)?.querySelectorAll('.rate-inp').forEach(inp => {
    const d   = new Date(inp.dataset.date);
    const dow = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][d.getDay()];
    if(d >= from && d <= to && days.includes(dow)){
      inp.value = rate;
      count++;
    }
  });
  closeBulk();
  showToast(`Bulk rates applied to ${count} dates ✓`, 'ok');
}

/* ── Day Rule Override ───────────────────────────────────────────────────────── */
function applyDayRule(hi){
  const fri = $$('dr-fri-' + hi)?.checked;
  const sat = $$('dr-sat-' + hi)?.checked;
  const sun = $$('dr-sun-' + hi)?.checked;
  const val = $$('dr-val-' + hi)?.value;
  if(!val){ showToast('Enter a rate value', 'err'); return; }
  let count = 0;
  $$('rcal-' + hi)?.querySelectorAll('.rate-inp').forEach(inp => {
    const dow = new Date(inp.dataset.date).getDay();
    if((fri && dow===5)||(sat && dow===6)||(sun && dow===0)){
      inp.value = val; count++;
    }
  });
  showToast(`Applied to ${count} dates`, 'ok');
}

/* ── Save / Copy ─────────────────────────────────────────────────────────────── */
function saveAll(hi)          { showToast('All changes saved ✓', 'ok'); }
function saveRates(hi)        { showToast('Rates saved successfully ✓', 'ok'); }
function copyRatesToAll(hi)   { showToast('Rates copied to all room categories ✓', 'ok'); }
function refreshCal(hi)       { showToast('Viewing selected room & meal plan rates', 'ok'); }
function prevMonth(hi)        { showToast('← Previous month (connect to backend)', 'ok'); }
function nextMonth(hi)        { showToast('Next month → (connect to backend)', 'ok'); }

/* ── Add Hotel Wizard ─────────────────────────────────────────────────────────── */
let wizStep     = 1;
const TOTAL_WIZ = 4;
let newRoomCount = 0;

function openAddHotel(){
  wizStep      = 1;
  newRoomCount = 0;
  $$('new-rooms-list').innerHTML  = '';
  $$('eb-config-list').innerHTML  = '';
  $$('meal-price-list').innerHTML = '';
  ['hn-name','hn-city','hn-state','hn-pin','hn-phone','hn-email','hn-web','hn-desc'].forEach(id => {
    if($$(id)) $$(id).value = '';
  });
  updateWiz();
  $$('modal-hotel').classList.add('open');
  addRoomRow();
  setTimeout(() => $$('hn-name').focus(), 200);
}

function closeHotelModal(){ $$('modal-hotel').classList.remove('open'); }

function updateWiz(){
  for(let i = 1; i <= TOTAL_WIZ; i++){
    const s = $$('ws-' + i);
    const p = $$('wp-' + i);
    s.className = 'step' + (i < wizStep ? ' done' : (i === wizStep ? ' active' : ''));
    p.className = 'wiz-panel' + (i === wizStep ? ' active' : '');
  }
}

function wizNext(step){
  if(step === 1){
    if(!$$('hn-name').value.trim()){ showToast('Hotel name is required', 'err'); $$('hn-name').focus(); return; }
    if(!$$('hn-city').value.trim()){ showToast('City is required', 'err'); $$('hn-city').focus(); return; }
  }
  if(step === 2){
    const rooms = collectNewRooms();
    if(rooms.length === 0){ showToast('Add at least one room category', 'err'); return; }
    buildEBConfig(rooms);
  }
  if(step === 3){
    const rooms = collectNewRooms();
    buildMealPrices(rooms);
  }
  wizStep = Math.min(wizStep + 1, TOTAL_WIZ);
  updateWiz();
}

function wizPrev(step){
  wizStep = Math.max(wizStep - 1, 1);
  updateWiz();
}

/* ── New Room Row (Wizard Step 2) ───────────────────────────────────────────── */
function addRoomRow(){
  const idx = newRoomCount++;
  const div = document.createElement('div');
  div.className = 'room-row-item';
  div.id        = 'nrr-' + idx;
  div.innerHTML = `
    <button type="button" class="rr-remove" onclick="removeRoomRow(${idx})" title="Remove"><i class="bi bi-x-lg"></i></button>
    <div style="font-weight:700;font-size:.83rem;color:var(--navy);margin-bottom:10px;">Room Category ${idx+1}</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="frow" style="margin:0">
        <label>Room Name *</label>
        <input type="text" id="nrr-name-${idx}" placeholder="e.g. Deluxe Double Room">
      </div>
      <div class="frow" style="margin:0">
        <label>Bed Type</label>
        <select id="nrr-bed-${idx}">
          <option>Double</option><option>Twin</option><option>King</option><option>Queen</option><option>Single</option>
        </select>
      </div>
      <div class="frow" style="margin:0">
        <label>Total Rooms</label>
        <input type="number" id="nrr-total-${idx}" value="10" min="1">
      </div>
      <div class="frow" style="margin:0">
        <label>Size</label>
        <input type="text" id="nrr-size-${idx}" placeholder="e.g. 280 sq ft">
      </div>
    </div>`;
  $$('new-rooms-list').appendChild(div);
}

function removeRoomRow(idx){
  const el = $$('nrr-' + idx);
  if(el){ el.style.opacity='0'; el.style.transform='translateX(-10px)'; el.style.transition='.2s'; setTimeout(()=>el.remove(),200); }
}

function collectNewRooms(){
  const rooms = [];
  $$('new-rooms-list').querySelectorAll('.room-row-item').forEach((div, i) => {
    const idx   = div.id.replace('nrr-','');
    const name  = $$('nrr-name-'  + idx)?.value?.trim() || '';
    const bed   = $$('nrr-bed-'   + idx)?.value || 'Double';
    const total = parseInt($$('nrr-total-'+ idx)?.value || 10);
    const size  = $$('nrr-size-'  + idx)?.value?.trim() || '';
    if(name) rooms.push({idx, name, bed, total, size});
  });
  return rooms;
}

/* ── Build EB Config (Step 3) ───────────────────────────────────────────────── */
function buildEBConfig(rooms){
  const list = $$('eb-config-list');
  list.innerHTML = '';
  rooms.forEach((r, i) => {
    const div = document.createElement('div');
    div.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;background:#fafbfc';
    div.innerHTML = `
      <div style="font-weight:700;margin-bottom:12px;color:var(--navy);">${esc(r.name)}</div>
      <div style="display:grid;grid-template-columns:auto 1fr 1fr;gap:12px;align-items:end;">
        <div>
          <label style="font-size:.74rem;font-weight:700;color:var(--muted);display:block;margin-bottom:6px;">ALLOW</label>
          <button type="button" class="toggle" id="neb-tog-${i}" onclick="toggleNEB(${i})"></button>
        </div>
        <div>
          <label style="font-size:.74rem;font-weight:700;color:var(--muted);display:block;margin-bottom:6px;">PRICE ₹</label>
          <input type="number" id="neb-price-${i}" value="0" min="0" style="border:1px solid var(--border);border-radius:7px;padding:8px 11px;width:100%;font-size:.85rem;font-family:inherit;">
        </div>
        <div>
          <label style="font-size:.74rem;font-weight:700;color:var(--muted);display:block;margin-bottom:6px;">MAX BEDS</label>
          <input type="number" id="neb-max-${i}" value="1" min="0" max="3" style="border:1px solid var(--border);border-radius:7px;padding:8px 11px;width:100%;font-size:.85rem;font-family:inherit;">
        </div>
      </div>`;
    list.appendChild(div);
  });
}

function toggleNEB(i){
  const t = $$('neb-tog-' + i);
  t.classList.toggle('on');
}

/* ── Build Meal Plan Prices (Step 4) ─────────────────────────────────────────── */
function buildMealPrices(rooms){
  const list = $$('meal-price-list');
  list.innerHTML = '';
  rooms.forEach((r, ri) => {
    const div = document.createElement('div');
    div.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:18px;margin-bottom:16px;background:#fafbfc';
    const rowsHtml = mealPlanKeys.map(c => `
      <div class="price-row">
        <label>${esc(mealPlanLabels[c] || c)}</label>
        <input type="number" id="nmp-${ri}-${c}" placeholder="₹ 0" min="0">
      </div>`).join('');
    div.innerHTML = `
      <div style="font-weight:700;margin-bottom:14px;color:var(--navy);">${esc(r.name)}</div>
      <div class="price-grid">${rowsHtml}</div>`;
    list.appendChild(div);
  });
}

/* ── Finish: Create Hotel ─────────────────────────────────────────────────────── */
function finishHotel(){
  const name   = $$('hn-name').value.trim();
  const city   = $$('hn-city').value.trim();
  const rooms  = collectNewRooms();

  if(rooms.length === 0){ showToast('Add at least one room category', 'err'); return; }

  // Build hotel card HTML and prepend to page (demo — in production POST to backend)
  const hotelId  = 'HTL-UV-' + Date.now().toString().slice(-5);
  const starVal  = document.querySelector('input[name="hstar"]:checked')?.value || 3;
  const hiNew    = 'new-' + Date.now();

  const roomsHtml = rooms.map(r =>
    `<tr><td style="font-weight:700">${esc(r.name)}</td>
     <td><span class="badge b-blue">${esc(r.bed)}</span></td>
     <td style="color:var(--muted);font-size:.77rem">${esc(r.size)}</td>
     <td style="text-align:center"><strong>${r.total}</strong></td>
     <td style="text-align:center"><span class="badge b-green">${r.total}</span></td>
     <td style="text-align:center"><span class="badge b-red">0</span></td>
     <td style="text-align:center"><span class="badge b-orange">0</span></td>
     <td class="price-cell"><span style="color:var(--muted);font-size:.73rem">Set prices</span></td>
     <td class="eb-tag"><span style="color:var(--muted);font-size:.73rem">—</span></td>
     <td style="text-align:center">
       <div style="display:flex;gap:5px;justify-content:center">
         <button class="btn btn-teal btn-xs"><i class="bi bi-pencil"></i> Edit</button>
         <button class="btn btn-coral btn-xs"><i class="bi bi-trash3"></i></button>
       </div>
     </td></tr>`
  ).join('');

  const card = document.createElement('div');
  card.className = 'hotel-card';
  card.id = 'hcard-' + hiNew;
  card.innerHTML = `
    <div class="hotel-header">
      <div class="hotel-meta">
        <div class="hotel-id">${esc(hotelId)}</div>
        <div class="hotel-name-row">
          <h2>${esc(name)}</h2>
          <span class="stars">${'★'.repeat(starVal)}${'☆'.repeat(5-starVal)}</span>
        </div>
        <div class="hotel-sub"><i class="bi bi-geo-alt-fill"></i> ${esc(city)}
          <span class="hotel-pill">${rooms.length} Room Categories</span>
          <span class="hotel-pill">Newly Added</span>
        </div>
      </div>
      <div class="hotel-actions">
        <button class="btn btn-white btn-sm"><i class="bi bi-plus-lg"></i> Add Room</button>
        <button class="btn btn-white btn-sm"><i class="bi bi-floppy2"></i> Save</button>
      </div>
    </div>
    <div class="tabs">
      <div class="tab active"><i class="bi bi-door-open"></i> Room Categories</div>
    </div>
    <div class="tab-panel active" style="padding:24px;">
      <div style="overflow-x:auto;border-radius:10px;border:1px solid var(--border);">
        <table class="rtable">
          <thead><tr>
            <th>Room Name</th><th>Bed</th><th>Size</th>
            <th style="text-align:center">Total</th><th style="text-align:center">Available</th>
            <th style="text-align:center">Booked</th><th style="text-align:center">Blocked</th>
            <th>Prices</th><th>Extra Bed</th><th style="text-align:center">Actions</th>
          </tr></thead>
          <tbody>${roomsHtml}</tbody>
        </table>
      </div>
      <div style="margin-top:12px;padding:10px 14px;background:#e8f7f5;border-radius:8px;font-size:.8rem;color:var(--teal-d);">
        <i class="bi bi-info-circle"></i> Hotel created locally. Connect to your backend to persist this data.
      </div>
    </div>`;

  // Insert before the page's first hotel card
  const firstCard = document.querySelector('.hotel-card');
  firstCard ? firstCard.before(card) : document.querySelector('.page').appendChild(card);

  closeHotelModal();
  showToast(`Hotel "${name}" created ✓`, 'ok');
  card.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

(function () {
  var btn = document.getElementById('topbarProfileBtn');
  var menu = document.getElementById('topbarProfileMenu');
  if (!btn || !menu) return;

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    var open = menu.classList.contains('open');
    menu.classList.toggle('open', !open);
    btn.setAttribute('aria-expanded', open ? 'false' : 'true');
  });

  document.addEventListener('click', function () {
    menu.classList.remove('open');
    btn.setAttribute('aria-expanded', 'false');
  });

  menu.addEventListener('click', function (e) {
    e.stopPropagation();
  });
})();
</script>
<script src="/assets/js/ui-common.js"></script>
</body>
</html>
