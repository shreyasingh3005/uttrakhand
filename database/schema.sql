CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee',
  reset_otp VARCHAR(255) DEFAULT NULL,
  otp_expires_at DATETIME DEFAULT NULL,
  otp_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  otp_requested_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS dashboard_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL UNIQUE,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS agents_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    gst_number VARCHAR(30) DEFAULT NULL,
  email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    location VARCHAR(120) NOT NULL,
    rating DECIMAL(2,1) DEFAULT 0.0,
    status ENUM('Active', 'On Leave', 'Inactive') DEFAULT 'Active',
    total_deals INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    created_by VARCHAR(255) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_agents_details_phone (phone)
);

CREATE TABLE IF NOT EXISTS employees_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    designation VARCHAR(120) NOT NULL,
    department VARCHAR(80) NOT NULL,
    status ENUM('Active', 'On Leave', 'Inactive') DEFAULT 'Active',
    monthly_salary DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employees_details_phone (phone)
);

CREATE TABLE IF NOT EXISTS hotel_listings_details (
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
);

CREATE TABLE IF NOT EXISTS hotel_listing_room_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    category_name VARCHAR(120) NOT NULL,
    validity VARCHAR(120) DEFAULT NULL,
    validity_start VARCHAR(120) DEFAULT NULL,
    validity_end VARCHAR(120) DEFAULT NULL,
    weekday_price DECIMAL(12,2) DEFAULT 0,
    weekend_price DECIMAL(12,2) DEFAULT 0,
    gst DECIMAL(5,2) DEFAULT 0,
    weekday_cpai DECIMAL(12,2) DEFAULT 0,
    weekday_mapai DECIMAL(12,2) DEFAULT 0,
    weekday_apai DECIMAL(12,2) DEFAULT 0,
    weekend_cpai DECIMAL(12,2) DEFAULT 0,
    weekend_mapai DECIMAL(12,2) DEFAULT 0,
    weekend_apai DECIMAL(12,2) DEFAULT 0,
    child_no_bed_cpai DECIMAL(12,2) DEFAULT 0,
    child_no_bed_mapai DECIMAL(12,2) DEFAULT 0,
    child_no_bed_apai DECIMAL(12,2) DEFAULT 0,
    child_with_bed_cpai DECIMAL(12,2) DEFAULT 0,
    child_with_bed_mapai DECIMAL(12,2) DEFAULT 0,
    child_with_bed_apai DECIMAL(12,2) DEFAULT 0,
    adult_with_bed_cpai DECIMAL(12,2) DEFAULT 0,
    adult_with_bed_mapai DECIMAL(12,2) DEFAULT 0,
    adult_with_bed_apai DECIMAL(12,2) DEFAULT 0,
    cpai_price DECIMAL(12,2) DEFAULT 0,
    mapai_price DECIMAL(12,2) DEFAULT 0,
    extra_person_with_bed DECIMAL(12,2) DEFAULT 0,
    extra_person_without_bed DECIMAL(12,2) DEFAULT 0,
    child_no_bed_cp DECIMAL(12,2) DEFAULT 0,
    child_no_bed_map DECIMAL(12,2) DEFAULT 0,
    child_with_bed_cp DECIMAL(12,2) DEFAULT 0,
    child_with_bed_map DECIMAL(12,2) DEFAULT 0,
    weekday_days VARCHAR(128) DEFAULT NULL,
    weekend_days VARCHAR(128) DEFAULT NULL,
    child_no_bed_days VARCHAR(128) DEFAULT NULL,
    child_with_bed_days VARCHAR(128) DEFAULT NULL,
    adult_with_bed_days VARCHAR(128) DEFAULT NULL,
    room_image_url VARCHAR(255) DEFAULT NULL,
    room_details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_room_category_listing FOREIGN KEY (listing_id) REFERENCES hotel_listings_details(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS agent_query_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    employee_id INT DEFAULT NULL,
    employee_username VARCHAR(255) NOT NULL,
    assigned_employee_id INT DEFAULT NULL,
    assigned_employee_username VARCHAR(255) DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME DEFAULT NULL,
    lock_until TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    query_text TEXT,
    hotel_name VARCHAR(255) DEFAULT NULL,
    room_category VARCHAR(120) DEFAULT NULL,
    check_in DATE DEFAULT NULL,
    check_out DATE DEFAULT NULL,
    adults INT DEFAULT 1,
    children INT DEFAULT 0,
    rooms INT DEFAULT 1,
    meal_plan VARCHAR(80) DEFAULT NULL,
    total_amount DECIMAL(12,2) DEFAULT 0,
    client_name VARCHAR(120) DEFAULT NULL,
    client_mobile VARCHAR(20) DEFAULT NULL,
    special_request TEXT DEFAULT NULL,
    booking_status ENUM('Unbooked','Booked','Confirmed','Cancelled') DEFAULT 'Unbooked',
    status ENUM('Open','Locked','Closed','Cancelled') DEFAULT 'Open',
    created_by_user_id INT DEFAULT NULL,
    created_by_role ENUM('admin','employee') DEFAULT 'employee',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (agent_id) REFERENCES agents_details(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS activity_logs (
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
    INDEX idx_booking_id (booking_id),
    FOREIGN KEY (query_lock_id) REFERENCES agent_query_locks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS bookings_details (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_hotel FOREIGN KEY (hotel_listing_id) REFERENCES hotel_listings_details(id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_agent FOREIGN KEY (agent_id) REFERENCES agents_details(id) ON DELETE RESTRICT,
    CONSTRAINT fk_booking_employee FOREIGN KEY (employee_id) REFERENCES employees_details(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS accounts_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    employee_id INT NOT NULL,
    entry_type ENUM('commission', 'payout', 'expense', 'receipt') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_accounts_employee FOREIGN KEY (employee_id) REFERENCES employees_details(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS `hotels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hotel_code` VARCHAR(30) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) DEFAULT '',
  `address` TEXT NULL,
  `pin_code` VARCHAR(15) DEFAULT '',
  `phone` VARCHAR(30) DEFAULT '',
  `contact_details` VARCHAR(255) DEFAULT '',
  `email` VARCHAR(150) DEFAULT '',
  `website` VARCHAR(255) DEFAULT '',
  `star_rating` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `description` TEXT NULL,
  `image_urls` TEXT NULL,
  `status` ENUM('active','inactive','deleted') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hotel_code` (`hotel_code`),
  KEY `idx_hotels_name` (`name`),
  KEY `idx_hotels_city` (`city`),
  KEY `idx_hotels_state` (`state`),
  KEY `idx_hotels_phone` (`phone`),
  KEY `idx_hotels_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meal_plans` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(5) NOT NULL,
  `name` VARCHAR(120) NOT NULL DEFAULT '',
  `label` VARCHAR(100) NOT NULL DEFAULT '',
  `description` VARCHAR(255) DEFAULT '',
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meal_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_room_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hotel_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `bed_type` ENUM('Single','Double','Twin','King','Queen','Bunk') NOT NULL DEFAULT 'Double',
  `room_size` VARCHAR(50) NOT NULL DEFAULT '',
  `total_rooms` SMALLINT NOT NULL DEFAULT 0,
  `available_rooms` SMALLINT NOT NULL DEFAULT 0,
  `booked_rooms` SMALLINT NOT NULL DEFAULT 0,
  `blocked_rooms` SMALLINT NOT NULL DEFAULT 0,
  `extra_bed_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `extra_bed_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_extra_beds` TINYINT NOT NULL DEFAULT 0,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hrc_hotel` (`hotel_id`),
  CHECK (hotel_id > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_prices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hotel_id` INT UNSIGNED NOT NULL,
  `room_category_id` INT UNSIGNED NOT NULL,
  `meal_plan_id` TINYINT UNSIGNED NOT NULL,
  `base_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `rate_date` DATE NULL,
  `date_wise_price` DECIMAL(10,2) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rp_room_plan_date` (`room_category_id`,`meal_plan_id`,`rate_date`),
  KEY `idx_rp_hotel` (`hotel_id`),
  KEY `idx_rp_date` (`rate_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hotel_bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_number` VARCHAR(25) NOT NULL,
  `hotel_id` INT UNSIGNED NOT NULL,
  `guest_name` VARCHAR(200) NOT NULL,
  `guest_phone` VARCHAR(20) NOT NULL DEFAULT '',
  `guest_email` VARCHAR(150) NOT NULL DEFAULT '',
  `checkin_date` DATE NOT NULL,
  `checkout_date` DATE NOT NULL,
  `total_nights` SMALLINT NOT NULL DEFAULT 1,
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `meal_plan_id` TINYINT UNSIGNED DEFAULT NULL,
  `special_requests` TEXT NULL,
  `source` VARCHAR(50) NOT NULL DEFAULT 'direct',
  `booking_status` ENUM('confirmed','pending','checked_in','checked_out','cancelled') NOT NULL DEFAULT 'confirmed',
  `payment_status` ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_number` (`booking_number`),
  KEY `idx_hb_hotel` (`hotel_id`),
  KEY `idx_hb_checkin` (`checkin_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `booking_rooms` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `room_category_id` INT UNSIGNED NOT NULL,
  `meal_plan_id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `rooms_count` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `adults` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `children` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `extra_beds` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `price_per_night` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_br_booking` (`booking_id`),
  KEY `idx_br_room` (`room_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_availability` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hotel_id` INT UNSIGNED NULL,
  `room_category_id` INT UNSIGNED NOT NULL,
  `availability_date` DATE NOT NULL,
  `total_rooms` SMALLINT NOT NULL DEFAULT 0,
  `available_rooms` SMALLINT NOT NULL DEFAULT 0,
  `booked_rooms` SMALLINT NOT NULL DEFAULT 0,
  `blocked_rooms` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ra_room_date` (`room_category_id`,`availability_date`),
  KEY `idx_ra_hotel` (`hotel_id`),
  KEY `idx_ra_date` (`availability_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;