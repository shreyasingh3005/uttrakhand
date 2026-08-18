<?php
/**
 * Database Connection — Uttarakhand Ventures CRM
 * Uses config from .env.php. Never exposes errors to users.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

$cfg = config();

try {
    $conn = new PDO(
        'mysql:host=' . $cfg['DB_HOST'] . ';dbname=' . $cfg['DB_NAME'] . ';charset=' . ($cfg['DB_CHARSET'] ?? 'utf8mb4'),
        $cfg['DB_USER'],
        $cfg['DB_PASS'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    safe_error_response(500, 'Unable to connect to the database. Please try again later.', $e);
}

function ensure_core_tables(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                role ENUM('admin', 'employee') DEFAULT 'employee',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS dashboard_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stat_date DATE NOT NULL UNIQUE,
                note VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS agents_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                company_name VARCHAR(150) DEFAULT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                phone VARCHAR(20) NOT NULL,
                location VARCHAR(120) NOT NULL,
                rating DECIMAL(2,1) DEFAULT 0.0,
                status ENUM('Active', 'On Leave', 'Inactive') DEFAULT 'Active',
                total_deals INT DEFAULT 0,
                total_revenue DECIMAL(12,2) DEFAULT 0,
                created_by VARCHAR(255) NOT NULL DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS employees_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                phone VARCHAR(20) NOT NULL,
                designation VARCHAR(120) NOT NULL,
                department VARCHAR(80) NOT NULL,
                status ENUM('Active', 'On Leave', 'Inactive') DEFAULT 'Active',
                monthly_salary DECIMAL(12,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS hotel_listings_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                hotel_name VARCHAR(150) NOT NULL,
                category VARCHAR(80) NOT NULL,
                location VARCHAR(120) NOT NULL,
                main_image_url VARCHAR(255) DEFAULT NULL,
                room_type VARCHAR(120) NOT NULL,
                weekday_price DECIMAL(12,2) DEFAULT 0,
                weekend_price DECIMAL(12,2) DEFAULT 0,
                gst DECIMAL(5,2) DEFAULT 0,
                status ENUM('Active', 'Inactive') DEFAULT 'Active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS bookings_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                booking_code VARCHAR(40) NOT NULL UNIQUE,
                client_name VARCHAR(120) NOT NULL,
                client_phone VARCHAR(20) NOT NULL,
                client_email VARCHAR(150) DEFAULT NULL,
                hotel_listing_id INT NOT NULL,
                agent_id INT NOT NULL,
                employee_id INT DEFAULT NULL,
                check_in DATE NOT NULL,
                check_out DATE NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                booking_source VARCHAR(80) DEFAULT NULL,
                guest_count INT NOT NULL DEFAULT 1,
                room_count INT NOT NULL DEFAULT 1,
                special_request TEXT DEFAULT NULL,
                status ENUM('Confirmed', 'Pending Payment', 'Cancelled') DEFAULT 'Confirmed',
                booking_date DATE NOT NULL,
                created_by VARCHAR(255) NOT NULL DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS accounts_details (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entry_date DATE NOT NULL,
                employee_id INT NOT NULL,
                entry_type ENUM('commission', 'payout', 'expense', 'receipt') NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                notes VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
    } catch (PDOException $e) {
        // Schema creation is non-blocking
    }
}

ensure_core_tables($conn);

function ensure_booking_payment_columns(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $columns = [];
        try {
            $colStmt = $conn->query("SHOW COLUMNS FROM bookings_details");
        } catch (PDOException $inner) {
            $conn->exec(
                "CREATE TABLE IF NOT EXISTS bookings_details (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    booking_code VARCHAR(40) NOT NULL UNIQUE,
                    client_name VARCHAR(120) NOT NULL,
                    client_phone VARCHAR(20) NOT NULL,
                    client_email VARCHAR(150) DEFAULT NULL,
                    hotel_listing_id INT NOT NULL,
                    agent_id INT NOT NULL,
                    employee_id INT DEFAULT NULL,
                    check_in DATE NOT NULL,
                    check_out DATE NOT NULL,
                    amount DECIMAL(12,2) NOT NULL,
                    booking_source VARCHAR(80) DEFAULT NULL,
                    guest_count INT NOT NULL DEFAULT 1,
                    room_count INT NOT NULL DEFAULT 1,
                    special_request TEXT DEFAULT NULL,
                    status ENUM('Confirmed', 'Pending Payment', 'Cancelled') DEFAULT 'Confirmed',
                    booking_date DATE NOT NULL,
                    created_by VARCHAR(255) NOT NULL DEFAULT 'admin',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $colStmt = $conn->query("SHOW COLUMNS FROM bookings_details");
        }
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[$col['Field']] = true;
        }
        $alterParts = [];
        $addCols = [
            'paid_amount' => "ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0",
            'due_amount' => "ADD COLUMN due_amount DECIMAL(12,2) NOT NULL DEFAULT 0",
            'payment_status' => "ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'Pending'",
            'booking_status' => "ADD COLUMN booking_status VARCHAR(20) DEFAULT NULL",
            'payment_note' => "ADD COLUMN payment_note VARCHAR(255) DEFAULT NULL",
            'payment_updated_by' => "ADD COLUMN payment_updated_by VARCHAR(255) DEFAULT NULL",
            'payment_updated_at' => "ADD COLUMN payment_updated_at DATETIME DEFAULT NULL",
            'hotel_name_snapshot' => "ADD COLUMN hotel_name_snapshot VARCHAR(150) DEFAULT NULL",
            'hotel_location_snapshot' => "ADD COLUMN hotel_location_snapshot VARCHAR(120) DEFAULT NULL",
            'room_type_snapshot' => "ADD COLUMN room_type_snapshot VARCHAR(120) DEFAULT NULL",
            'hotel_category_snapshot' => "ADD COLUMN hotel_category_snapshot VARCHAR(80) DEFAULT NULL",
        ];
        foreach ($addCols as $field => $sql) {
            if (!isset($columns[$field])) $alterParts[] = $sql;
        }
        if (!empty($alterParts)) {
            $conn->exec("ALTER TABLE bookings_details " . implode(', ', $alterParts));
        }
        $conn->exec("UPDATE bookings_details SET paid_amount = COALESCE(paid_amount, 0), due_amount = GREATEST(COALESCE(amount, 0) - COALESCE(paid_amount, 0), 0), payment_status = CASE WHEN COALESCE(paid_amount, 0) >= COALESCE(amount, 0) AND COALESCE(amount, 0) > 0 THEN 'Paid' WHEN COALESCE(paid_amount, 0) > 0 THEN 'Partial' ELSE 'Pending' END, booking_status = CASE WHEN booking_status IS NOT NULL AND booking_status <> '' THEN booking_status WHEN status = 'Cancelled' THEN 'Cancelled' WHEN status = 'Confirmed' THEN 'Completed' WHEN status = 'Pending Payment' THEN 'Pending' ELSE COALESCE(booking_status, 'Pending') END, booking_source = COALESCE(booking_source, 'Direct'), guest_count = CASE WHEN guest_count IS NULL OR guest_count < 1 THEN 1 ELSE guest_count END, room_count = CASE WHEN room_count IS NULL OR room_count < 1 THEN 1 ELSE room_count END");
    } catch (PDOException $e) {
        // Non-blocking
    }
}

ensure_booking_payment_columns($conn);

function ensure_agents_company_column(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $columns = [];
        $colStmt = $conn->query("SHOW COLUMNS FROM agents_details");
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[$col['Field']] = true;
        }

        if (!isset($columns['company_name'])) {
            $conn->exec("ALTER TABLE agents_details ADD COLUMN company_name VARCHAR(150) DEFAULT NULL AFTER name");
        }
    } catch (PDOException $e) {
        // Non-blocking
    }
}

ensure_agents_company_column($conn);

function ensure_user_login_tracking_columns(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $columns = [];
        $colStmt = $conn->query("SHOW COLUMNS FROM users");
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[$col['Field']] = true;
        }
        $alterParts = [];
        if (!isset($columns['is_logged_in'])) $alterParts[] = "ADD COLUMN is_logged_in TINYINT(1) NOT NULL DEFAULT 0";
        if (!isset($columns['last_login_at'])) $alterParts[] = "ADD COLUMN last_login_at DATETIME DEFAULT NULL";
        if (!isset($columns['last_logout_at'])) $alterParts[] = "ADD COLUMN last_logout_at DATETIME DEFAULT NULL";
        if (!empty($alterParts)) {
            $conn->exec("ALTER TABLE users " . implode(', ', $alterParts));
        }
    } catch (PDOException $e) {
        // Non-blocking
    }
}

ensure_user_login_tracking_columns($conn);

function ensure_hotels_master_columns_indexes(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $existsStmt = $conn->query("SHOW TABLES LIKE 'hotels'");
        if (!$existsStmt || !$existsStmt->fetchColumn()) {
            return;
        }

        $columns = [];
        $colStmt = $conn->query("SHOW COLUMNS FROM hotels");
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columns[$col['Field']] = true;
        }

        if (!isset($columns['contact_details'])) {
            $conn->exec("ALTER TABLE hotels ADD COLUMN contact_details VARCHAR(255) DEFAULT '' AFTER phone");
        }
        if (!isset($columns['image_urls'])) {
            $conn->exec("ALTER TABLE hotels ADD COLUMN image_urls TEXT NULL AFTER description");
        }
        if (!isset($columns['property_category'])) {
            $conn->exec("ALTER TABLE hotels ADD COLUMN property_category VARCHAR(20) NOT NULL DEFAULT '' AFTER star_rating");
            // Backfill existing rows from star_rating so category filtering works immediately.
            $conn->exec("UPDATE hotels SET property_category = CONCAT(star_rating, ' Star') WHERE property_category = '' AND star_rating > 0");
        }

        $idxRows = $conn->query("SHOW INDEX FROM hotels")->fetchAll(PDO::FETCH_ASSOC);
        $idx = [];
        foreach ($idxRows as $r) {
            $idx[$r['Key_name']] = true;
        }

        if (!isset($idx['idx_hotels_name'])) {
            $conn->exec("CREATE INDEX idx_hotels_name ON hotels (name)");
        }
        if (!isset($idx['idx_hotels_city'])) {
            $conn->exec("CREATE INDEX idx_hotels_city ON hotels (city)");
        }
        if (!isset($idx['idx_hotels_state'])) {
            $conn->exec("CREATE INDEX idx_hotels_state ON hotels (state)");
        }
        if (!isset($idx['idx_hotels_phone'])) {
            $conn->exec("CREATE INDEX idx_hotels_phone ON hotels (phone)");
        }
        if (!isset($idx['idx_hotels_email'])) {
            $conn->exec("CREATE INDEX idx_hotels_email ON hotels (email)");
        }
        if (!isset($idx['idx_hotels_category'])) {
            $conn->exec("CREATE INDEX idx_hotels_category ON hotels (property_category)");
        }
    } catch (PDOException $e) {
        // Non-blocking compatibility migration
    }
}

ensure_hotels_master_columns_indexes($conn);

/** Fixed list of supported hotel/property categories (admin is source of truth for saved values). */
function hotel_category_options(): array {
    return ['1 Star', '2 Star', '3 Star', '4 Star', '5 Star', 'Resort', 'Boutique'];
}

/** Derives the legacy numeric star_rating column from the merged category value (0 for Resort/Boutique). */
function hotel_category_to_star_rating(string $category): int {
    if (preg_match('/^(\d)\s*Star$/i', trim($category), $m)) {
        return (int)$m[1];
    }
    return 0;
}

function ensure_hotel_manager_schema(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $cols_of = function ($table) use ($conn) {
        $cols = [];
        try {
            foreach ($conn->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[$c['Field']] = true;
            }
        } catch (PDOException $e) { /* table may not exist */ }
        return $cols;
    };

    try {
        foreach (['hotel_room_categories', 'hotel_bookings', 'room_prices'] as $viewName) {
            $chk = $conn->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND TABLE_TYPE='VIEW'");
            $chk->execute([$viewName]);
            if ((int)$chk->fetchColumn() > 0) {
                $conn->exec("DROP VIEW IF EXISTS `$viewName`");
            }
        }

        $conn->exec("CREATE TABLE IF NOT EXISTS hotels (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_code VARCHAR(30) NOT NULL,
            name VARCHAR(200) NOT NULL,
            city VARCHAR(100) NOT NULL,
            state VARCHAR(100) DEFAULT '',
            address TEXT NULL,
            pin_code VARCHAR(15) DEFAULT '',
            phone VARCHAR(30) DEFAULT '',
            email VARCHAR(150) DEFAULT '',
            website VARCHAR(255) DEFAULT '',
            star_rating TINYINT UNSIGNED NOT NULL DEFAULT 3,
            description TEXT NULL,
            status ENUM('active','inactive','deleted') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hotel_code (hotel_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS meal_plans (
            id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(5) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL DEFAULT '',
            label VARCHAR(100) NOT NULL DEFAULT '',
            description VARCHAR(255) DEFAULT '',
            sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS hotel_room_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_id INT UNSIGNED NOT NULL,
            name VARCHAR(200) NOT NULL,
            bed_type ENUM('Single','Double','Twin','King','Queen','Bunk') NOT NULL DEFAULT 'Double',
            room_size VARCHAR(50) NOT NULL DEFAULT '',
            total_rooms SMALLINT NOT NULL DEFAULT 0,
            available_rooms SMALLINT NOT NULL DEFAULT 0,
            booked_rooms SMALLINT NOT NULL DEFAULT 0,
            blocked_rooms SMALLINT NOT NULL DEFAULT 0,
            extra_bed_allowed TINYINT(1) NOT NULL DEFAULT 0,
            extra_bed_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            max_extra_beds TINYINT NOT NULL DEFAULT 0,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_hrc_hotel (hotel_id),
            KEY idx_hrc_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS room_prices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_id INT UNSIGNED NOT NULL,
            room_category_id INT UNSIGNED NOT NULL,
            meal_plan_id TINYINT UNSIGNED NOT NULL,
            base_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            rate_date DATE NULL,
            date_wise_price DECIMAL(10,2) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rp_room_plan_date (room_category_id, meal_plan_id, rate_date),
            KEY idx_rp_hotel (hotel_id),
            KEY idx_rp_room (room_category_id),
            KEY idx_rp_date (rate_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS hotel_bookings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_number VARCHAR(25) NOT NULL,
            hotel_id INT UNSIGNED NOT NULL,
            guest_name VARCHAR(200) NOT NULL,
            guest_phone VARCHAR(20) NOT NULL DEFAULT '',
            guest_email VARCHAR(150) NOT NULL DEFAULT '',
            checkin_date DATE NOT NULL,
            checkout_date DATE NOT NULL,
            total_nights SMALLINT NOT NULL DEFAULT 1,
            total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            meal_plan_id TINYINT UNSIGNED DEFAULT NULL,
            special_requests TEXT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'direct',
            booking_status ENUM('confirmed','pending','checked_in','checked_out','cancelled') NOT NULL DEFAULT 'confirmed',
            payment_status ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_booking_number (booking_number),
            KEY idx_hb_hotel (hotel_id),
            KEY idx_hb_checkin (checkin_date),
            KEY idx_hb_status (booking_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS room_availability (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hotel_id INT UNSIGNED NULL,
            room_category_id INT UNSIGNED NOT NULL,
            availability_date DATE NOT NULL,
            total_rooms SMALLINT NOT NULL DEFAULT 0,
            available_rooms SMALLINT NOT NULL DEFAULT 0,
            booked_rooms SMALLINT NOT NULL DEFAULT 0,
            blocked_rooms SMALLINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ra_room_date (room_category_id, availability_date),
            KEY idx_ra_hotel (hotel_id),
            KEY idx_ra_date (availability_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->exec("CREATE TABLE IF NOT EXISTS booking_rooms (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            booking_id INT UNSIGNED NOT NULL,
            room_category_id INT UNSIGNED NOT NULL,
            meal_plan_id TINYINT UNSIGNED NOT NULL DEFAULT 1,
            rooms_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
            adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
            children TINYINT UNSIGNED NOT NULL DEFAULT 0,
            extra_beds TINYINT UNSIGNED NOT NULL DEFAULT 0,
            price_per_night DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_br_booking (booking_id),
            KEY idx_br_room (room_category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $mp = $cols_of('meal_plans');
        if (!isset($mp['name'])) {
            $conn->exec("ALTER TABLE meal_plans ADD COLUMN name VARCHAR(120) NOT NULL DEFAULT '' AFTER code");
        }
        $cnt = (int)$conn->query("SELECT COUNT(*) FROM meal_plans")->fetchColumn();
        if ($cnt === 0) {
            $conn->exec("INSERT INTO meal_plans (code,name,label,description,sort_order) VALUES
                ('EP','EP - Room Only','EP - Room Only','Room only plan',1),
                ('CP','CP - Breakfast Included','CP - Breakfast Included','Room with breakfast',2),
                ('MAP','MAP - Breakfast + Dinner','MAP - Breakfast + Dinner','Rooms + dinner',3),
                ('AP','AP - All Meals','AP - All Meals','All meals included',4),
                ('AI','AI - All Inclusive','AI - All Inclusive','All inclusive plan',5)");
        }

        $ra = $cols_of('room_availability');
        if (isset($ra['date']) && !isset($ra['availability_date'])) {
            $conn->exec("ALTER TABLE room_availability CHANGE COLUMN `date` availability_date DATE NOT NULL");
        }
        $ra = $cols_of('room_availability');
        if (!isset($ra['hotel_id'])) {
            $conn->exec("ALTER TABLE room_availability ADD COLUMN hotel_id INT UNSIGNED NULL AFTER room_category_id");
        }
    } catch (PDOException $e) {
        // Non-blocking
    }
}

ensure_hotel_manager_schema($conn);

function ensure_agent_query_locks_table(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS agent_query_locks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            agent_id INT NOT NULL,
            employee_id INT DEFAULT NULL,
            employee_username VARCHAR(255) NOT NULL,
            assigned_employee_id INT DEFAULT NULL,
            assigned_employee_username VARCHAR(255) DEFAULT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            lock_until DATETIME NOT NULL,
            query_text TEXT,
            hotel_name VARCHAR(255) DEFAULT NULL,
            room_category VARCHAR(120) DEFAULT NULL,
            check_in DATE DEFAULT NULL,
            check_out DATE DEFAULT NULL,
            adults INT DEFAULT 1,
            children INT DEFAULT 0,
            rooms INT DEFAULT 1,
            extra_bed VARCHAR(20) DEFAULT NULL,
            meal_plan VARCHAR(80) DEFAULT NULL,
            total_amount DECIMAL(12,2) DEFAULT 0,
            paid_amount DECIMAL(12,2) DEFAULT 0,
            client_name VARCHAR(120) DEFAULT NULL,
            client_mobile VARCHAR(20) DEFAULT NULL,
            client_email VARCHAR(150) DEFAULT NULL,
            special_request TEXT DEFAULT NULL,
            booking_status ENUM('Unbooked','Booked','Confirmed','Cancelled') DEFAULT 'Unbooked',
            status ENUM('Open','Locked','Closed','Cancelled') DEFAULT 'Open',
            created_by_user_id INT DEFAULT NULL,
            created_by_role ENUM('admin','employee') DEFAULT 'employee',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (agent_id) REFERENCES agents_details(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) { /* non-blocking */ }
}

function ensure_activity_logs_table(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            query_lock_id INT DEFAULT NULL,
            booking_id INT DEFAULT NULL,
            action VARCHAR(120) NOT NULL,
            performed_by_user_id INT DEFAULT NULL,
            performed_by_username VARCHAR(255) DEFAULT NULL,
            performed_by_role ENUM('admin','employee') DEFAULT 'employee',
            action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            INDEX idx_query_lock_id (query_lock_id),
            INDEX idx_booking_id (booking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) { /* non-blocking */ }
}

ensure_agent_query_locks_table($conn);
ensure_activity_logs_table($conn);

function ensure_booking_query_history_table(PDO $conn) {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS booking_query_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            created_by_user_id INT DEFAULT NULL,
            created_by_username VARCHAR(255) NOT NULL,
            created_by_role ENUM('admin','employee') DEFAULT 'employee',
            location VARCHAR(255) DEFAULT NULL,
            hotel_category VARCHAR(120) DEFAULT NULL,
            check_in DATE DEFAULT NULL,
            check_out DATE DEFAULT NULL,
            nights INT DEFAULT 0,
            adults INT DEFAULT 1,
            children INT DEFAULT 0,
            rooms INT DEFAULT 1,
            budget DECIMAL(12,2) DEFAULT 0,
            query_text TEXT NOT NULL,
            matched_hotels_json LONGTEXT DEFAULT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_bqh_user (created_by_user_id),
            INDEX idx_bqh_generated (generated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) { /* non-blocking */ }
}

ensure_booking_query_history_table($conn);

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function send_response($status, $message, $data = null) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['status' => $status, 'message' => $message];
    if ($data) $response['data'] = $data;
    echo json_encode($response);
    exit();
}
