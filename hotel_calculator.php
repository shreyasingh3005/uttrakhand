<?php
session_start();

// Set your Admin Password here
$adminPassword = 'M@nish#@uK32'; 

// File to store admin configurations
$configFile = 'admin_settings.json';

// Embedded Default Hotels Data
$defaultHotelsJson = '[{"hotel_name":"My Premium Hotel","discount_percent":20,"commission":0,"tds_on_comm_percent":2,"plb":1200,"orc_percent":0,"tds_percent":0,"markup":0,"gst_markup":0},{"hotel_name":"Pilibhit House, Haridwar \u2013 IHCL SeleQtions","discount_percent":0,"commission":10,"tds_on_comm_percent":2,"plb":1500,"orc_percent":2,"tds_percent":0,"markup":0,"gst_markup":0},{"hotel_name":"The Leela Ambience Gurugram Hotel & Residences","discount_percent":0,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Palace New Delhi","discount_percent":5,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Palace Jaipur","discount_percent":5,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Palace Udaipur","discount_percent":5,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Palace Bengaluru","discount_percent":0,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Palace Chennai","discount_percent":0,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Ambience Convention Hotel Delhi","discount_percent":0,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Hyderabad","discount_percent":0,"commission":20,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Kovalam","discount_percent":0,"commission":15,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Ashtamudi","discount_percent":0,"commission":15,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Coorg","discount_percent":0,"commission":15,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Gandhinagar","discount_percent":0,"commission":10,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18},{"hotel_name":"The Leela Bhartiya City Bengaluru","discount_percent":0,"commission":10,"tds_on_comm_percent":2,"plb":0,"orc_percent":0,"tds_percent":0,"markup":100,"gst_markup":18}]';

$defaultHotels = json_decode($defaultHotelsJson, true);
$defaultHotel = $defaultHotels[0]; // Fallback hotel structure

$rawHotels = [];

// Load and migrate settings if the config file exists
if (file_exists($configFile)) {
    $data = json_decode(file_get_contents($configFile), true);
    if (isset($data['hotel_name'])) {
        $rawHotels[] = $data;
    } else if (is_array($data) && !empty($data)) {
        $rawHotels = $data;
    } else {
        $rawHotels = $defaultHotels;
    }
} else {
    // Auto-create the file with the default list on the very first run
    $rawHotels = $defaultHotels;
    file_put_contents($configFile, $defaultHotelsJson);
}

// AUTO-CLEANUP: Removes any blank/corrupted entries stuck in the JSON file
$hotels = [];
foreach ($rawHotels as $h) {
    if (!empty(trim($h['hotel_name'] ?? ''))) {
        $h['hotel_name'] = htmlspecialchars_decode($h['hotel_name']);
        
        if (!isset($h['tds_on_comm_percent'])) {
            $h['tds_on_comm_percent'] = 2.00;
        }
        
        $hotels[] = $h;
    }
}
// Ensure there is always at least one valid hotel
if (empty($hotels)) {
    $hotels[] = $defaultHotel;
}
$hotels = array_values($hotels); // Force perfect sequential indexing

// Handle Admin Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
    if ($_POST['password'] === $adminPassword) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $loginError = "Incorrect password! Please try again.";
    }
}

// Handle Admin Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ?view=employee");
    exit;
}

// Handle Admin Settings Update (Add or Edit Hotel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $hid = $_POST['hotel_id'];
    
    // Using floatval() safely converts empty/blank inputs to 0 automatically
    $updatedSettings = [
        'hotel_name' => trim($_POST['hotel_name']),
        'discount_percent' => floatval($_POST['discount_percent']),
        'commission' => floatval($_POST['commission']),
        'tds_on_comm_percent' => floatval($_POST['tds_on_comm_percent']),
        'plb' => floatval($_POST['plb']),
        'orc_percent' => floatval($_POST['orc_percent']),
        'tds_percent' => floatval($_POST['tds_percent']),
        'markup' => floatval($_POST['markup']),
        'gst_markup' => floatval($_POST['gst_markup'])
    ];

    if ($hid === 'new') {
        $hotels[] = $updatedSettings; // Add as new hotel
        $hid = count($hotels) - 1;    // Set active ID to the newly created one
    } else {
        $hotels[$hid] = $updatedSettings; // Update existing hotel
    }
    
    file_put_contents($configFile, json_encode(array_values($hotels)));
    header("Location: ?view=admin&hotel=$hid&success=1");
    exit;
}

// Handle Admin Settings Delete (Remove Hotel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hotel'])) {
    $hid = $_POST['hotel_id'];
    
    if ($hid !== 'new' && isset($hotels[$hid])) {
        unset($hotels[$hid]); // Safely remove the specific hotel
        $hotels = array_values($hotels); // Re-index array perfectly
        
        // If all hotels are deleted, add the default one back
        if (empty($hotels)) {
            $hotels[] = $defaultHotel;
        }
        
        file_put_contents($configFile, json_encode($hotels));
        header("Location: ?view=admin&success=2");
        exit;
    }
}

// Handle Calculator Submission (For both Employee & Admin Test)
$calculated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate'])) {
    $barRate = floatval($_POST['bar_rate']);
    $hid = $_POST['calc_hotel_id'] ?? 0;
    
    // Get the specific settings for the selected hotel
    $activeHotel = $hotels[$hid] ?? $hotels[0];

    $hotelName = $activeHotel['hotel_name'];
    $discountPercent = $activeHotel['discount_percent']; 
    $commPercent = $activeHotel['commission'];
    $tdsOnCommPercent = $activeHotel['tds_on_comm_percent'] ?? 2.00; // Pulls dynamic editable value
    $plbAmount = $activeHotel['plb'];
    $orcPercent = $activeHotel['orc_percent'];
    $tdsPercent = $activeHotel['tds_percent'];
    $markupAmount = $activeHotel['markup'];
    $gstOnMarkup = $activeHotel['gst_markup'];

    // If admin is running a live test, they can override discount dynamically
    if (isset($_POST['admin_test'])) {
        $discountPercent = floatval($_POST['discount_percent']);
    }

    // 1. Apply Discount First
    $discountAmount = $barRate * ($discountPercent / 100);
    $discountedBar = $barRate - $discountAmount;

    // 2. Dynamic Tax Calculation (As Per BAR)
    $taxPercent = ($barRate <= 7500) ? 5.00 : 18.00;

    // 3. Deductions & Additions
    $commAmount = $discountedBar * ($commPercent / 100);
    $orcAmount = $discountedBar * ($orcPercent / 100);
    $tdsAmount = $discountedBar * ($tdsPercent / 100);
    
    $nettBeforeTax = $discountedBar - $commAmount - $plbAmount - $orcAmount - $tdsAmount;
    
    // 4. Tax Addition & New TDS on Commission Addition
    $taxAmount = $nettBeforeTax * ($taxPercent / 100);
    $tdsOnCommAmount = $commAmount * ($tdsOnCommPercent / 100); // Calculate TDS on Commission
    
    $nettIncludingTax = $nettBeforeTax + $taxAmount + $tdsOnCommAmount;
    
    // 5. Mark Up Addition
    $markupTaxAmount = $markupAmount * ($gstOnMarkup / 100);
    $finalTotal = $nettIncludingTax + $markupAmount + $markupTaxAmount;
    
    $calculated = true;
}

$view = $_GET['view'] ?? 'employee';

// Set Dynamic Success Messages
$successMessage = null;
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) $successMessage = "Hotel settings saved successfully!";
    if ($_GET['success'] == 2) $successMessage = "Hotel removed successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Premium Hotel Nett Rate Calculator</title>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* === ULTRA-PREMIUM RESPONSIVE UI OVERHAUL === */
        :root {
            --primary: #d4af37;       
            --primary-hover: #b5952f;
            --accent: #38bdf8;        
            --success: #34d399;
            --danger: #f43f5e;       
            --bg-gradient: linear-gradient(135deg, #020617 0%, #0f172a 100%);
            --card-bg: rgba(15, 23, 42, 0.75);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(255, 255, 255, 0.08);
            --focus-ring: rgba(212, 175, 55, 0.3);
        }

        * {
            box-sizing: border-box; /* Crucial for mobile responsiveness */
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-gradient); 
            color: var(--text-main);
            padding: 40px 20px; 
            margin: 0;
            display: flex;
            justify-content: center;
            min-height: 100vh;
            color-scheme: dark;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient glowing orbs - Hardware Accelerated */
        body::before, body::after {
            content: '';
            position: fixed;
            width: 50vw; height: 50vw;
            border-radius: 50%;
            filter: blur(120px);
            z-index: -1;
            animation: floatOrb 12s infinite alternate ease-in-out;
            opacity: 0.4;
            will-change: transform; /* Performance Boost */
        }
        body::before { background: rgba(212, 175, 55, 0.15); top: -10%; left: -10%; }
        body::after { background: rgba(56, 189, 248, 0.15); bottom: -10%; right: -10%; animation-delay: -6s; }
        
        @keyframes floatOrb {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(10%, 10%, 0); }
        }

        .container { 
            width: 100%;
            max-width: 620px; 
            background: var(--card-bg); 
            padding: 40px 50px; 
            border-radius: 24px; 
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 215, 0, 0.1);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        h2 { 
            text-align: center; 
            background: linear-gradient(to right, #fde08b, #d4af37, #fde08b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -0.01em;
            margin-top: 0;
            margin-bottom: 25px;
            text-shadow: 0 4px 15px rgba(212, 175, 55, 0.15);
        }

        .nav { 
            text-align: center; 
            margin-bottom: 35px; 
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap; /* Responsive wrapping */
        }

        .nav a { 
            text-decoration: none; 
            color: var(--text-muted); 
            font-weight: 600; 
            font-size: 0.95rem;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav a:hover { 
            color: var(--primary); 
            background: rgba(212, 175, 55, 0.1);
        }
        
        .nav a[href="?view=<?= $view ?>"] { 
            color: #000 !important; 
            background: var(--primary) !important;
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.4);
        }

        .form-group { margin-bottom: 22px; display: flex; justify-content: space-between; align-items: center; width: 100%; }

        .form-group label { 
            font-weight: 600; 
            width: 50%; 
            font-size: 0.95rem;
            color: #cbd5e1;
        }

        .form-group input, .form-group select { 
            width: 48%; 
            padding: 12px 16px; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 12px; 
            font-size: 1rem;
            font-weight: 500;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: rgba(0, 0, 0, 0.25);
            color: #ffffff;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-group select option { background: #0f172a; color: #fff; }

        .form-group input:hover, .form-group select:hover {
            border-color: rgba(212, 175, 55, 0.5);
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 0 4px var(--focus-ring);
            transform: translateY(-1px);
        }

        /* Override PHP inline styles for disabled input & GET form */
        input[disabled] { background: rgba(255,255,255,0.05) !important; color: #94a3b8 !important; border: 1px dashed rgba(255,255,255,0.2) !important; }
        form[method="GET"] { background: rgba(0,0,0,0.2) !important; border-color: rgba(255,255,255,0.08) !important; }

        input[type="number"]::-webkit-outer-spin-button, input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        input[type="number"] { -moz-appearance: textfield; }

        button { 
            width: 100%; 
            padding: 14px; 
            background: linear-gradient(135deg, #fde08b, #d4af37); 
            color: #000; 
            border: none; 
            border-radius: 12px; 
            font-size: 1.05rem; 
            font-weight: 800;
            cursor: pointer; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
            box-shadow: 0 8px 20px -5px rgba(212, 175, 55, 0.4);
        }

        button:hover { 
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 12px 25px -5px rgba(212, 175, 55, 0.6);
        }
        button:active { transform: translateY(0); }
        
        button[style*="var(--primary)"] { color: #000 !important; }

        .btn-group { display: flex; gap: 15px; margin-top: 30px; }
        
        .btn-reset { 
            background: rgba(255,255,255,0.05); 
            color: #fff;
            text-align: center; 
            text-decoration: none; 
            display: flex; 
            align-items: center;
            justify-content: center;
            width: 100%; 
            padding: 14px; 
            border-radius: 12px; 
            font-weight: 700;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease; 
        }

        .btn-reset:hover { 
            background: rgba(255,255,255,0.1); 
            transform: translateY(-2px);
        }

        .btn-copy { 
            background: linear-gradient(135deg, #0ea5e9, #6366f1); 
            color: #fff;
            margin-top: 25px; 
            font-weight: 700; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            gap: 10px; 
            font-size: 1.05rem;
            box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.5);
            border: none;
        }

        .btn-copy:hover { 
            background: linear-gradient(135deg, #0284c7, #4f46e5); 
            box-shadow: 0 15px 25px -5px rgba(99, 102, 241, 0.7);
        }

        .btn-delete { 
            background: rgba(244, 63, 94, 0.1); 
            color: var(--danger);
            border: 1px solid rgba(244, 63, 94, 0.3);
            box-shadow: none;
        }
        .btn-delete:hover { 
            background: rgba(244, 63, 94, 0.2); 
            border-color: var(--danger);
            color: #fff;
        }

        /* Result & Quick Booking Boxes */
        .result-box { 
            margin-top: 30px; 
            background: linear-gradient(135deg, rgba(52, 211, 153, 0.05), rgba(52, 211, 153, 0.1)) !important; 
            padding: 24px; 
            border-radius: 16px; 
            border: 1px solid rgba(52, 211, 153, 0.2) !important;
            border-left: 6px solid var(--success) !important; 
            box-shadow: 0 10px 30px -10px rgba(52, 211, 153, 0.2);
        }

        .result-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 14px; 
            padding-bottom: 14px; 
            border-bottom: 1px dashed rgba(255,255,255,0.1); 
            font-size: 1rem;
            color: #cbd5e1;
        }

        .result-row:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .bold { font-weight: 700; color: #fff; }
        #finalRateValue { color: #34d399; text-shadow: 0 0 15px rgba(52, 211, 153, 0.4); }
        
        .success, .error { 
            font-weight: 700; margin-bottom: 25px; padding: 14px; 
            border-radius: 12px; text-align: center; width: 100%;
        }
        .success { color: #34d399; background: rgba(52, 211, 153, 0.1); border: 1px solid rgba(52, 211, 153, 0.3); }
        .error { color: #fb7185; background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.3); }
        
        .logout-btn { background: rgba(244,63,94,0.1); color: #fb7185; border: 1px solid rgba(244,63,94,0.3); padding: 8px 16px; font-size: 0.9rem; width: auto; float: right; margin-bottom: 20px; box-shadow: none; border-radius: 8px; }
        .logout-btn:hover { background: rgba(244,63,94,0.2); color: #fff; transform: none; }
        .clearfix::after { content: ""; clear: both; display: table; }
        
        .tester-box { 
            background: rgba(255, 255, 255, 0.03); 
            padding: 25px; 
            border: 1px solid rgba(255, 255, 255, 0.08); 
            border-radius: 16px; 
            margin-top: 45px; 
            box-shadow: inset 0 0 20px rgba(255,255,255,0.02);
        }
        
        .tester-box h3 { margin-top: 0; text-align: center; color: var(--primary); font-size: 1.1em; margin-bottom: 20px;}
        
        .explanation-box { 
            margin-top: 25px; 
            background: rgba(212, 175, 55, 0.05); 
            padding: 24px; 
            border-radius: 16px; 
            border: 1px solid rgba(212, 175, 55, 0.2); 
            font-size: 0.95rem; 
            color: #cbd5e1; 
        }
        
        .explanation-box h4 { margin-top: 0; margin-bottom: 16px; color: var(--primary); font-weight: 800; }
        .explanation-box ul { margin: 0; padding-left: 20px; }
        .explanation-box li { margin-bottom: 10px; line-height: 1.6; }

        .mini-label { font-size: 0.8rem; color: #94a3b8; margin-bottom: 6px; display: block; font-weight: 600; text-align: left; }

        /* === COLOURFUL ANIMATED TOAST (POP-UP) === */
        #custom-toast {
            visibility: hidden;
            min-width: 320px;
            background: linear-gradient(135deg, #ff007a, #7928ca, #0ea5e9, #ff007a);
            background-size: 300% 300%;
            animation: gradientShift 4s ease infinite;
            color: #ffffff;
            text-align: center;
            border-radius: 50px; 
            padding: 18px 24px;
            position: fixed;
            z-index: 9999;
            left: 50%;
            bottom: 40px;
            transform: translate3d(-50%, 100px, 0) scale(0.8);
            box-shadow: 0 15px 40px rgba(121, 40, 202, 0.6), inset 0 2px 2px rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            opacity: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            will-change: transform, opacity; /* Performance Boost */
        }

        @keyframes gradientShift { 
            0% { background-position: 0% 50%; } 
            50% { background-position: 100% 50%; } 
            100% { background-position: 0% 50%; } 
        }

        #custom-toast.show {
            visibility: visible;
            opacity: 1;
            transform: translate3d(-50%, 0, 0) scale(1);
            transition: transform 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55), opacity 0.4s ease;
        }

        #custom-toast .icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(5px);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* === PREMIUM DELETE CONFIRMATION MODAL === */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            z-index: 10000; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
            will-change: opacity;
        }
        .modal-overlay.show { opacity: 1; visibility: visible; }

        .modal-container {
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px; padding: 35px 30px;
            width: 90%; max-width: 420px; text-align: center;
            transform: scale(0.9); transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            will-change: transform;
        }
        .modal-overlay.show .modal-container { transform: scale(1); }

        .modal-icon {
            width: 64px; height: 64px;
            background: rgba(244, 63, 94, 0.1); color: var(--danger);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; border: 1px solid rgba(244,63,94,0.3);
        }

        .modal-container h3 { margin: 0 0 10px; color: #fff; font-size: 1.4rem; font-weight: 800; }
        .modal-container p { margin: 0 0 25px; color: #94a3b8; font-size: 0.95rem; line-height: 1.5; }
        .modal-actions { display: flex; gap: 15px; }

        .modal-btn-cancel { background: rgba(255,255,255,0.05); color: #fff; flex: 1; border: 1px solid rgba(255,255,255,0.1); padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer;}
        .modal-btn-cancel:hover { background: rgba(255,255,255,0.1); }
        .modal-btn-confirm { background: var(--danger); color: white; flex: 1; border: none; padding: 12px; border-radius: 12px; font-weight: 600; cursor: pointer;}
        .modal-btn-confirm:hover { background: #e11d48; box-shadow: 0 4px 15px rgba(244, 63, 94, 0.4); }


        /* =======================================================
           MOBILE RESPONSIVE & HIGH-SPEED MEDIA QUERIES 
           ======================================================= */
        @media (max-width: 650px) {
            body { 
                padding: 15px 10px; 
            }
            
            /* Disable intensive background blur & animation to save mobile battery and ensure 60fps */
            body::before, body::after { 
                display: none; 
            }
            
            .container { 
                padding: 25px 20px; 
                border-radius: 16px; 
                backdrop-filter: blur(10px); /* Lighter blur for mobile */
                -webkit-backdrop-filter: blur(10px);
            }

            h2 { font-size: 1.4rem; margin-bottom: 20px; }

            /* Stack Form Groups Vertically on Mobile */
            .form-group { 
                flex-direction: column; 
                align-items: flex-start; 
                margin-bottom: 16px;
            }

            .form-group label { 
                width: 100%; 
                margin-bottom: 8px; 
                font-size: 0.9rem;
            }

            /* Force Inputs to take full width on small screens */
            .form-group input, 
            .form-group select { 
                width: 100% !important; 
                padding: 14px 16px; /* Slightly taller for thumb-tapping */
                font-size: 16px; /* Prevents auto-zoom on iOS Safari */
            }

            /* Fix inline styled divisions in Quick Booking Format */
            .tester-box .form-group { margin-bottom: 0 !important; }
            .tester-box .form-group > div { 
                width: 100% !important; 
                margin-bottom: 12px; 
            }

            .btn-group { 
                flex-direction: column; 
                gap: 12px; 
                margin-top: 20px; 
            }
            
            /* Admin Buttons stacking */
            form[method="POST"] > div[style*="display: flex"] {
                flex-direction: column;
                gap: 10px;
            }

            .result-row { 
                flex-direction: column; 
                text-align: center; 
                gap: 5px; 
            }
            
            .result-box { padding: 18px; }

            /* Employee fallback buttons stacking */
            div[style*="display: flex; gap: 10px; margin-top: 15px;"] {
                flex-direction: column;
            }

            .modal-container { padding: 25px 20px; }
            .modal-actions { flex-direction: column-reverse; gap: 10px; } /* Put Cancel on bottom for thumb reach */
        }
    </style>
</head>
<body>

<div class="container">
    <h2>HOTEL NETT RATE CALCULATOR</h2>
    
    <div class="nav">
        <a href="?view=employee">Employee Calculator</a>
        <a href="?view=admin">Admin Settings</a>
    </div>

    <?php if ($successMessage) echo "<p class='success'>$successMessage</p>"; ?>

    <?php if ($view === 'admin'): ?>
        
        <?php if (empty($_SESSION['admin_logged_in'])): ?>
            <form method="POST">
                <h3 style="text-align: center; color: var(--primary); margin-bottom: 25px;">Admin Login Required</h3>
                <?php if (isset($loginError)) echo "<p class='error'>$loginError</p>"; ?>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter admin password" required>
                </div>
                <button type="submit" name="login_admin">Secure Login</button>
            </form>

        <?php else: ?>
            <div class="clearfix">
                <a href="?logout=true"><button class="logout-btn">Logout</button></a>
            </div>

            <!-- ADMIN HOTEL SELECTOR -->
            <?php 
                $currentHotelIndex = $_GET['hotel'] ?? 0;
                if ($currentHotelIndex === 'new') {
                    $activeHotel = $defaultHotel;
                    $activeHotel['hotel_name'] = ''; 
                } else {
                    $activeHotel = $hotels[$currentHotelIndex] ?? $hotels[0];
                }
            ?>
            <form method="GET" style="background: #f8fafc; padding: 20px; border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 30px;">
                <input type="hidden" name="view" value="admin">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="color: var(--primary);">Editing Hotel Profile:</label>
                    <select name="hotel" onchange="this.form.submit()">
                        <?php foreach($hotels as $idx => $h): ?>
                            <option value="<?= $idx ?>" <?= ($currentHotelIndex == $idx && $currentHotelIndex !== 'new') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($h['hotel_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="new" <?= ($currentHotelIndex === 'new') ? 'selected' : '' ?>>➕ Add New Hotel...</option>
                    </select>
                </div>
            </form>
            
            <!-- ADMIN SETTINGS FORM -->
            <form method="POST">
                <input type="hidden" name="hotel_id" value="<?= htmlspecialchars($currentHotelIndex) ?>">
                
                <div class="form-group">
                    <label>Hotel Name</label>
                    <input type="text" name="hotel_name" value="<?= htmlspecialchars($activeHotel['hotel_name']) ?>" required placeholder="Enter Hotel Name">
                </div>
                
                <div class="form-group">
                    <label>Discount %</label>
                    <input type="number" step="0.01" name="discount_percent" value="<?= htmlspecialchars($activeHotel['discount_percent']) ?>">
                </div>
                <div class="form-group">
                    <label>Commission %</label>
                    <input type="number" step="0.01" name="commission" value="<?= htmlspecialchars($activeHotel['commission']) ?>">
                </div>
                
                <div class="form-group">
                    <label>TDS on Commission Deducted %</label>
                    <input type="number" step="0.01" name="tds_on_comm_percent" value="<?= htmlspecialchars($activeHotel['tds_on_comm_percent'] ?? '2.00') ?>">
                </div>

                <div class="form-group">
                    <label>PLB (Fixed Amount)</label>
                    <input type="number" step="0.01" name="plb" value="<?= htmlspecialchars($activeHotel['plb']) ?>">
                </div>
                <div class="form-group">
                    <label>ORC %</label>
                    <input type="number" step="0.01" name="orc_percent" value="<?= htmlspecialchars($activeHotel['orc_percent']) ?>">
                </div>
                <div class="form-group">
                    <label>Applicable Tax % (As Per Bar)</label>
                    <input type="text" value="Till 7500: 5% | 7501+: 18%" disabled style="background:#f1f5f9; color:#94a3b8; font-size: 0.85rem; text-align: center; box-shadow: none;">
                </div>
                <div class="form-group">
                    <label>TDS % (On Room Rate)</label>
                    <input type="number" step="0.01" name="tds_percent" value="<?= htmlspecialchars($activeHotel['tds_percent']) ?>">
                </div>
                <div class="form-group">
                    <label>Mark Up (Fixed Amount)</label>
                    <input type="number" step="0.01" name="markup" value="<?= htmlspecialchars($activeHotel['markup']) ?>">
                </div>
                <div class="form-group">
                    <label>GST On Mark UP %</label>
                    <input type="number" step="0.01" name="gst_markup" value="<?= htmlspecialchars($activeHotel['gst_markup']) ?>">
                </div>
                
                <?php if ($currentHotelIndex === 'new'): ?>
                    <button type="submit" name="update_admin">Save New Hotel</button>
                <?php else: ?>
                    <div style="display: flex; gap: 15px; margin-top: 10px;">
                        <button type="submit" name="update_admin" style="flex: 2;">Update Settings</button>
                        
                        <!-- CUSTOM MODAL TRIGGER FOR DELETE -->
                        <button type="button" class="btn-delete" onclick="showDeleteModal()" style="flex: 1;">Delete</button>
                        <button type="submit" name="delete_hotel" id="realDeleteBtn" style="display:none;"></button>
                    </div>
                <?php endif; ?>
            </form>

            <!-- ADMIN LIVE TESTER -->
            <?php if ($currentHotelIndex !== 'new'): ?>
            <div class="tester-box">
                <h3>Admin Live Tester for "<?= htmlspecialchars($activeHotel['hotel_name']) ?>"</h3>
                <form method="POST">
                    <input type="hidden" name="calc_hotel_id" value="<?= htmlspecialchars($currentHotelIndex) ?>">
                    <input type="hidden" name="admin_test" value="1">
                    <div class="form-group">
                        <label>Test BAR Rate (₹)</label>
                        <input type="number" step="0.01" name="bar_rate" value="<?= $_POST['bar_rate'] ?? '' ?>" placeholder="Enter Rate" required>
                    </div>
                    <div class="form-group">
                        <label>Discount %</label>
                        <input type="number" step="0.01" name="discount_percent" value="<?= $_POST['discount_percent'] ?? $activeHotel['discount_percent'] ?>">
                    </div>
                    <button type="submit" name="calculate" style="background:var(--primary);">Run Test Calculation</button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- ADMIN TESTER RESULTS & EXPLANATION -->
            <?php if ($calculated && isset($_POST['admin_test'])): ?>
                <div class="result-box" id="calc-result">
                    <div class="result-row bold"><span>Discounted BAR Rate:</span><span>₹<?= number_format($discountedBar, 2) ?></span></div>
                    <div class="result-row bold"><span>Nett Rate Before Tax:</span><span>₹<?= number_format($nettBeforeTax, 2) ?></span></div>
                    <div class="result-row bold" style="color: #fff;"><span>Nett Rate Including Tax:</span><span>₹<?= number_format($nettIncludingTax, 2) ?></span></div>
                    <div class="result-row bold" style="font-size: 1.35rem; color: var(--success); border:none; padding-top:14px; align-items: center;">
                        <span>Final Total Rate:</span>
                        <span id="finalRateValue">₹<?= number_format($finalTotal, 2) ?></span>
                    </div>
                </div>
                
                <!-- CALCULATION EXPLANATION BOX -->
                <div class="explanation-box">
                    <h4>How this is calculated:</h4>
                    <ul>
                        <li><strong>Hotel Name:</strong> <?= htmlspecialchars($hotelName) ?></li>
                        <li><strong>Discount Amount:</strong> ₹<?= number_format($barRate, 2) ?> × <?= $discountPercent ?>% = <strong>₹<?= number_format($discountAmount, 2) ?></strong></li>
                        <li><strong>Discounted BAR:</strong> ₹<?= number_format($barRate, 2) ?> - ₹<?= number_format($discountAmount, 2) ?> = <strong>₹<?= number_format($discountedBar, 2) ?></strong></li>
                        <li><strong>Commission Deducted:</strong> ₹<?= number_format($discountedBar, 2) ?> × <?= $commPercent ?>% = <strong>₹<?= number_format($commAmount, 2) ?></strong></li>
                        <li><strong>PLB Deducted:</strong> Fixed amount of <strong>₹<?= number_format($plbAmount, 2) ?></strong></li>
                        <li><strong>ORC Deducted:</strong> ₹<?= number_format($discountedBar, 2) ?> × <?= $orcPercent ?>% = <strong>₹<?= number_format($orcAmount, 2) ?></strong></li>
                        <li><strong>TDS Deducted on Room Rate:</strong> ₹<?= number_format($discountedBar, 2) ?> × <?= $tdsPercent ?>% = <strong>₹<?= number_format($tdsAmount, 2) ?></strong></li>
                        <li><strong>Nett Rate Before Tax:</strong> ₹<?= number_format($discountedBar, 2) ?> - Comm - PLB - ORC - TDS = <strong>₹<?= number_format($nettBeforeTax, 2) ?></strong></li>
                        <li><strong>Tax Added (<?= $taxPercent ?>% as BAR is <?= ($barRate <= 7500) ? '≤ 7500' : '≥ 7501' ?>):</strong> ₹<?= number_format($nettBeforeTax, 2) ?> × <?= $taxPercent ?>% = <strong>₹<?= number_format($taxAmount, 2) ?></strong></li>
                        <li><strong>TDS on Comm Added:</strong> ₹<?= number_format($commAmount, 2) ?> × <?= floatval($tdsOnCommPercent) ?>% = <strong>₹<?= number_format($tdsOnCommAmount, 2) ?></strong></li>
                        <li><strong>Nett Rate Including Tax:</strong> ₹<?= number_format($nettBeforeTax, 2) ?> + Tax + TDS on Comm = <strong>₹<?= number_format($nettIncludingTax, 2) ?></strong></li>
                        <li><strong>Mark Up Added:</strong> Fixed amount of <strong>₹<?= number_format($markupAmount, 2) ?></strong></li>
                        <li><strong>GST on Mark Up Added:</strong> ₹<?= number_format($markupAmount, 2) ?> × <?= $gstOnMarkup ?>% = <strong>₹<?= number_format($markupTaxAmount, 2) ?></strong></li>
                        <li><strong>Final Total:</strong> Nett Rate Inc. Tax + Mark Up + GST = <strong>₹<?= number_format($finalTotal, 2) ?></strong></li>
                    </ul>
                </div>

                <button type="button" class="btn-copy" onclick="copyResult()">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Copy Final Rate
                </button>
            <?php endif; ?>

        <?php endif; ?>

    <?php else: ?>
        <!-- EMPLOYEE CALCULATOR -->
        <form method="POST">
            <!-- EMPLOYEE HOTEL SELECTOR -->
            <div class="form-group">
                <label>Select Hotel</label>
                <select name="calc_hotel_id" id="calc_hotel_id" required>
                    <?php foreach($hotels as $idx => $h): ?>
                        <option value="<?= $idx ?>" data-name="<?= htmlspecialchars($h['hotel_name']) ?>" <?= (isset($_POST['calc_hotel_id']) && $_POST['calc_hotel_id'] == $idx) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['hotel_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Member BAR Rate (₹)</label>
                <input type="number" step="0.01" name="bar_rate" value="<?= $_POST['bar_rate'] ?? '' ?>" placeholder="Enter BAR Rate" required>
            </div>

            <!-- MOVED QUICK BOOKING FORMAT (Now inside the form before calculating) -->
            <div class="tester-box" style="margin-top: 15px; padding: 25px; margin-bottom: 25px;">
                <h3 style="margin-top: 0; margin-bottom: 25px; color: var(--primary); text-align: left; font-size: 1.2rem; border-bottom: 2px solid var(--border-color); padding-bottom: 15px;">📋 Quick Booking Format</h3>
                <span id="hiddenHotelName" style="display:none;"><?= htmlspecialchars($hotelName ?? '') ?></span>
                
                <div class="form-group" style="margin-bottom: 18px;">
                    <div style="width: 48%;">
                        <label class="mini-label">Check In</label>
                        <input type="date" id="fmt_checkin" name="fmt_checkin" value="<?= $_POST['fmt_checkin'] ?? '' ?>" style="width: 100%;">
                    </div>
                    <div style="width: 48%;">
                        <label class="mini-label">Check Out</label>
                        <input type="date" id="fmt_checkout" name="fmt_checkout" value="<?= $_POST['fmt_checkout'] ?? '' ?>" style="width: 100%;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 18px;">
                    <div style="width: 48%;">
                        <label class="mini-label">Category</label>
                        <input type="text" id="fmt_category" name="fmt_category" value="<?= $_POST['fmt_category'] ?? '' ?>" placeholder="e.g. Deluxe" style="width: 100%;">
                    </div>
                    <div style="width: 48%;">
                        <label class="mini-label">No. of Rooms</label>
                        <input type="number" id="fmt_room" name="fmt_room" value="<?= $_POST['fmt_room'] ?? '' ?>" placeholder="e.g. 1" min="1" style="width: 100%;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 18px;">
                    <div style="width: 48%;">
                        <label class="mini-label">Adults</label>
                        <input type="number" id="fmt_adults" name="fmt_adults" value="<?= $_POST['fmt_adults'] ?? '' ?>" placeholder="e.g. 2" min="1" style="width: 100%;">
                    </div>
                    <div style="width: 48%;">
                        <label class="mini-label">Children</label>
                        <input type="number" id="fmt_children" name="fmt_children" value="<?= $_POST['fmt_children'] ?? '' ?>" placeholder="e.g. 0" min="0" style="width: 100%;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 25px;">
                    <div style="width: 100%;">
                        <label class="mini-label">Meal Plan</label>
                        <input type="text" id="fmt_meal" name="fmt_meal" value="<?= $_POST['fmt_meal'] ?? 'Breakfast' ?>" placeholder="e.g. Breakfast, MAP, AP, Room Only" style="width: 100%;">
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" name="calculate">Calculate & Copy Format</button>
                <a href="?view=employee" class="btn-reset">Reset</a>
            </div>
        </form>

        <?php if ($calculated && !isset($_POST['admin_test'])): ?>
            <!-- EMPLOYEE RESULT BOX -->
            <div class="result-box" id="calc-result">
                <div class="result-row bold" style="font-size: 1.4rem; color: var(--success); justify-content: center; align-items: center; border: none; margin: 0; padding: 10px 0;">
                    <span style="margin-right: 15px; color: #fff;">Final Rate:</span> 
                    <span id="finalRateValue">₹<?= number_format($finalTotal, 2) ?></span>
                </div>
            </div>
            
            <!-- Fallback buttons just in case browser blocks auto-copy -->
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="button" class="btn-copy" onclick="copyResult()" style="flex: 1; margin-top: 0; font-size: 0.95rem;">
                    Copy Rate Only
                </button>
                <button type="button" class="btn-copy" onclick="copyFullFormat()" style="flex: 2; margin-top: 0; background: var(--primary);">
                    Copy Full Booking Format
                </button>
            </div>

            <!-- Auto Copy Script on Calculate -->
            <textarea id="autoCopyArea" style="position: absolute; left: -9999px;"></textarea>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        const finalRate = "₹<?= number_format($finalTotal, 2) ?>";
                        const hotelName = <?= json_encode($hotelName) ?>;
                        const checkIn = formatDate("<?= $_POST['fmt_checkin'] ?? '' ?>") || '';
                        const checkOut = formatDate("<?= $_POST['fmt_checkout'] ?? '' ?>") || '';
                        const category = "<?= $_POST['fmt_category'] ?? '' ?>";
                        const adults = "<?= $_POST['fmt_adults'] ?? '' ?>";
                        const children = "<?= $_POST['fmt_children'] ?? '' ?>";
                        const room = "<?= $_POST['fmt_room'] ?? '' ?>";
                        const mealPlan = "<?= $_POST['fmt_meal'] ?? 'Breakfast' ?>";

                        const textToCopy = `*🏨 Hotel :* ${hotelName}\n*Check In :* ${checkIn}\n*Check Out :* ${checkOut}\n*Rooms Category :* ${category}\n*Adults :* ${adults} , *Children:* ${children}\n\n*🥗 Meal Plan:* ${mealPlan}\n*🛏️ Room:* ${room}\n*💰 Price :* ${finalRate} ( Per room per night )\n\nRooms are subject to availability at a time of booking.\nRates are Dynamic.`;

                        // Attempt to auto-copy to clipboard
                        const textArea = document.getElementById("autoCopyArea");
                        textArea.value = textToCopy;
                        textArea.select();
                        try {
                            document.execCommand('copy');
                            showCustomToast("Calculated & Format Auto-Copied!");
                        } catch (err) {
                            console.log("Auto-copy blocked by browser. User must click manually.");
                        }
                    }, 200); // small delay ensures elements are loaded
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- BEAUTIFUL CUSTOM DELETE CONFIRMATION MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-container">
        <div class="modal-icon">
            <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h3>Delete Hotel Profile?</h3>
        <p>Are you sure you want to permanently delete this hotel and its settings? This action cannot be undone.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="confirmDelete()">Yes, Delete</button>
        </div>
    </div>
</div>

<!-- JAVASCRIPT FOR UI EFFECTS -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Prevent scroll wheel from modifying number inputs
        const numberInputs = document.querySelectorAll('input[type="number"]');
        numberInputs.forEach(function(input) {
            input.addEventListener('wheel', function(e) {
                e.preventDefault();
            }, { passive: false });
        });

        // Smoothly scroll down to the results if a calculation was just made
        const calcResult = document.getElementById('calc-result');
        if (calcResult) {
            setTimeout(() => {
                calcResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 100);
        }

        // === DATE PICKER LOGIC FOR QUICK BOOKING FORMAT ===
        const checkinInput = document.getElementById('fmt_checkin');
        const checkoutInput = document.getElementById('fmt_checkout');
        
        if (checkinInput && checkoutInput) {
            // Helper to get local date in YYYY-MM-DD format
            const getISODate = (date) => {
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            };

            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);

            const todayStr = getISODate(today);
            const tomorrowStr = getISODate(tomorrow);

            // Set default values (Today & Tomorrow) ONLY if inputs are empty
            if (!checkinInput.value) checkinInput.value = todayStr;
            if (!checkoutInput.value) checkoutInput.value = tomorrowStr;

            // Restrict past dates
            checkinInput.min = todayStr;
            checkoutInput.min = tomorrowStr;

            // When check-in changes, dynamically update check-out minimum and value
            checkinInput.addEventListener('change', function() {
                if (this.value) {
                    const selectedDate = new Date(this.value);
                    const nextDate = new Date(selectedDate);
                    nextDate.setDate(nextDate.getDate() + 1);
                    const nextDateStr = getISODate(nextDate);

                    checkoutInput.min = nextDateStr;

                    // Push checkout date forward if it is now invalid
                    if (checkoutInput.value <= this.value) {
                        checkoutInput.value = nextDateStr;
                    }
                }
            });
        }
    });

    // SIMPLE RATE COPY FUNCTIONALITY (Fallback)
    function copyResult() {
        const finalRate = document.getElementById("finalRateValue").innerText;
        const textToCopy = finalRate; 
        
        navigator.clipboard.writeText(textToCopy).then(function() {
            showCustomToast("Final Rate Copied!");
        }).catch(function(err) {
            alert("Failed to copy text: " + err);
        });
    }

    // HELPER FUNCTION: Format YYYY-MM-DD to DD-MM-YYYY for the copy text
    function formatDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return `${parts[2]}-${parts[1]}-${parts[0]}`;
        }
        return dateStr;
    }

    // FULL FORMAT COPY FUNCTIONALITY (Fallback)
    function copyFullFormat() {
        const hotelName = document.getElementById("hiddenHotelName") ? document.getElementById("hiddenHotelName").innerText : "";
        const finalRate = document.getElementById("finalRateValue") ? document.getElementById("finalRateValue").innerText : "₹0.00";
        
        const checkIn = formatDate(document.getElementById('fmt_checkin').value) || '';
        const checkOut = formatDate(document.getElementById('fmt_checkout').value) || '';
        const category = document.getElementById('fmt_category').value || '';
        const adults = document.getElementById('fmt_adults').value || '';
        const children = document.getElementById('fmt_children').value || '';
        const room = document.getElementById('fmt_room').value || '';
        const mealPlan = document.getElementById('fmt_meal') ? document.getElementById('fmt_meal').value || 'Breakfast' : 'Breakfast';

        const textToCopy = `*🏨 Hotel :* ${hotelName}\n*Check In :* ${checkIn}\n*Check Out :* ${checkOut}\n*Rooms Category :* ${category}\n*Adults :* ${adults} , *Children:* ${children}\n\n*🥗 Meal Plan:* ${mealPlan}\n*🛏️ Room:* ${room}\n*💰 Price :* ${finalRate} ( Per room per night )\n\nRooms are subject to availability at a time of booking.\nRates are Dynamic.`;

        navigator.clipboard.writeText(textToCopy).then(function() {
            showCustomToast("Booking Format Copied!");
        }).catch(function(err) {
            alert("Failed to copy text: " + err);
        });
    }

    // TOAST NOTIFICATION
    function showCustomToast(message) {
        let toast = document.getElementById("custom-toast");
        if (!toast) {
            toast = document.createElement("div");
            toast.id = "custom-toast";
            
            const icon = document.createElement("div");
            icon.className = "icon";
            icon.innerHTML = `<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>`;
            
            const textNode = document.createElement("span");
            textNode.id = "toast-message";
            
            toast.appendChild(icon);
            toast.appendChild(textNode);
            document.body.appendChild(toast);
        }
        
        document.getElementById("toast-message").innerText = message;
        
        toast.classList.remove("show");
        void toast.offsetWidth; 
        toast.classList.add("show"); 
        
        if (window.toastTimeout) clearTimeout(window.toastTimeout);
        
        window.toastTimeout = setTimeout(function() {
            toast.classList.remove("show");
        }, 3500);
    }

    // CUSTOM DELETE MODAL FUNCTIONALITY
    function showDeleteModal() {
        document.getElementById('deleteModal').classList.add('show');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
    }

    function confirmDelete() {
        // Triggers the hidden real submit button to run the PHP delete logic securely
        document.getElementById('realDeleteBtn').click();
    }
</script>

</body>
</html>