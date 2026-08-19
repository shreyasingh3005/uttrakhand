-- ═══════════════════════════════════════════════════════════════════════════
-- all.sql — MERGED database: full employee_management schema + seed data
--
-- Consolidated, deduplicated version assembled intact from:
--   1. sql.sql                    -> CRM/listing tables + seed
--   2. hotel_manager_complete.sql -> Hotel room-manager tables, views + seed
--
-- hotel_manager.sql and hotel_listing_clean.sql are older variants of the same
-- hotel module, superseded by hotel_manager_complete.sql (which ships views so
-- their legacy naming still works). They are intentionally NOT merged here.
--
-- Import: Get-Content database\all.sql | C:\xampp\mysql\bin\mysql.exe -u root
-- ═══════════════════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `employee_management`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `employee_management`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ═══════════════════════════════════════════════════════════════════════════
-- CLEANUP OLD TABLES (views first, then tables — deduplicated union)
-- ═══════════════════════════════════════════════════════════════════════════

DROP VIEW IF EXISTS `hotel_room_categories`;
DROP VIEW IF EXISTS `hotel_bookings`;
DROP VIEW IF EXISTS `room_prices`;

DROP TABLE IF EXISTS `project_assignments`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `booking_rooms`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `room_rates`;
DROP TABLE IF EXISTS `room_availability`;
DROP TABLE IF EXISTS `room_meal_plan_prices`;
DROP TABLE IF EXISTS `extra_bed_settings`;
DROP TABLE IF EXISTS `room_categories`;
DROP TABLE IF EXISTS `meal_plans`;
DROP TABLE IF EXISTS `hotels`;
DROP TABLE IF EXISTS `hm_activity_logs`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `agent_query_locks`;
DROP TABLE IF EXISTS `accounts_details`;
DROP TABLE IF EXISTS `bookings_details`;
DROP TABLE IF EXISTS `employees_details`;
DROP TABLE IF EXISTS `agents_details`;
DROP TABLE IF EXISTS `hotel_listing_room_categories`;
DROP TABLE IF EXISTS `hotel_listings_details`;
DROP TABLE IF EXISTS `dashboard_details`;

-- ═══════════════════════════════════════════════════════════════════════════
-- PART 1 — CRM / LISTING TABLES  (from sql_manager.sql)
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 1) Dashboard
CREATE TABLE dashboard_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL UNIQUE,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2) Agents Details
CREATE TABLE agents_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
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

-- 3) Employees Details
CREATE TABLE employees_details (
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

-- 4) Hotel Listings Details
CREATE TABLE hotel_listings_details (
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

-- Agent Query Locks
CREATE TABLE IF NOT EXISTS agent_query_locks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    employee_id INT DEFAULT NULL,
    employee_username VARCHAR(255) NOT NULL,
    assigned_employee_id INT DEFAULT NULL,
    assigned_employee_username VARCHAR(255) DEFAULT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

-- 5) Bookings Details
CREATE TABLE bookings_details (
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

-- 6) Accounts Details
CREATE TABLE accounts_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    employee_id INT NOT NULL,
    entry_type ENUM('commission', 'payout', 'expense', 'receipt') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_accounts_employee FOREIGN KEY (employee_id) REFERENCES employees_details(id) ON DELETE RESTRICT
);
--
-- ═══════════════════════════════════════════════════════════════════════════
-- PART 2 — HOTEL ROOM-MANAGER TABLES  (canonical, matches listing.php + ajax/*)
-- ═══════════════════════════════════════════════════════════════════════════

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

--
-- ═══════════════════════════════════════════════════════════════════════════
-- PART 3 — HOTEL ROOM-MANAGER SEED DATA  (canonical)
-- ═══════════════════════════════════════════════════════════════════════════
INSERT INTO `meal_plans` (`code`,`name`,`label`,`description`,`sort_order`,`status`) VALUES
('EP','EP - Room Only','EP - Room Only','Room only plan without meals',1,'active'),
('CP','CP - Breakfast Included','CP - Breakfast Included','Room with breakfast',2,'active'),
('MAP','MAP - Breakfast + Dinner','MAP - Breakfast + Dinner','Breakfast plus dinner',3,'active'),
('AP','AP - All Meals','AP - All Meals','Breakfast, lunch and dinner',4,'active'),
('AI','AI - All Inclusive','AI - All Inclusive','All inclusive plan',5,'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `hotels` (`hotel_code`,`name`,`city`,`state`,`address`,`pin_code`,`phone`,`email`,`website`,`star_rating`,`description`,`status`) VALUES
('HTL-GOA-001','Adamo The Bellus','Goa','Goa','Calangute, North Goa','403516','+91 9876543210','reservations@adamobellus.com','https://adamobellus.com',5,'Luxury beachfront resort with premium rooms and suites.','active'),
('HTL-MUM-001','The Grand Marina','Mumbai','Maharashtra','Marine Drive, Mumbai','400020','+91 9876543211','booking@grandmarina.in','https://grandmarina.in',4,'Premium city hotel near Marine Drive.','active')
ON DUPLICATE KEY UPDATE `status` = 'active';

SET @hgoa = (SELECT id FROM hotels WHERE hotel_code = 'HTL-GOA-001');
SET @hmum = (SELECT id FROM hotels WHERE hotel_code = 'HTL-MUM-001');

INSERT INTO `hotel_room_categories`
(`hotel_id`,`name`,`bed_type`,`room_size`,`total_rooms`,`available_rooms`,`booked_rooms`,`blocked_rooms`,`extra_bed_allowed`,`extra_bed_price`,`max_extra_beds`,`status`) VALUES
(@hgoa,'Deluxe Double Or Twin Room','Double','280 sq ft',8,8,0,0,1,1200.00,1,'active'),
(@hgoa,'Regency Suite','King','420 sq ft',6,6,0,0,1,1500.00,1,'active'),
(@hgoa,'Bellus Suite - Pool View','King','750 sq ft',2,1,1,0,0,0.00,0,'active'),
(@hmum,'Superior Room','Twin','220 sq ft',10,8,0,2,1,900.00,1,'active'),
(@hmum,'Deluxe Room','Double','300 sq ft',10,10,0,0,1,1200.00,1,'active');

-- Base prices for each room category x meal plan (rate_date NULL = base)
SET @ep=(SELECT id FROM meal_plans WHERE code='EP');
SET @cp=(SELECT id FROM meal_plans WHERE code='CP');
SET @map=(SELECT id FROM meal_plans WHERE code='MAP');
SET @ap=(SELECT id FROM meal_plans WHERE code='AP');
SET @ai=(SELECT id FROM meal_plans WHERE code='AI');

SET @r1=(SELECT id FROM hotel_room_categories WHERE hotel_id=@hgoa AND name='Deluxe Double Or Twin Room');
SET @r2=(SELECT id FROM hotel_room_categories WHERE hotel_id=@hgoa AND name='Regency Suite');
SET @r3=(SELECT id FROM hotel_room_categories WHERE hotel_id=@hgoa AND name='Bellus Suite - Pool View');
SET @r4=(SELECT id FROM hotel_room_categories WHERE hotel_id=@hmum AND name='Superior Room');
SET @r5=(SELECT id FROM hotel_room_categories WHERE hotel_id=@hmum AND name='Deluxe Room');

INSERT INTO `room_prices` (`hotel_id`,`room_category_id`,`meal_plan_id`,`base_price`,`rate_date`,`date_wise_price`) VALUES
(@hgoa,@r1,@ep,5000,NULL,NULL),(@hgoa,@r1,@cp,5800,NULL,NULL),(@hgoa,@r1,@map,7200,NULL,NULL),(@hgoa,@r1,@ap,8500,NULL,NULL),(@hgoa,@r1,@ai,10000,NULL,NULL),
(@hgoa,@r2,@ep,8000,NULL,NULL),(@hgoa,@r2,@cp,9000,NULL,NULL),(@hgoa,@r2,@map,10500,NULL,NULL),(@hgoa,@r2,@ap,12000,NULL,NULL),(@hgoa,@r2,@ai,14000,NULL,NULL),
(@hgoa,@r3,@ep,12000,NULL,NULL),(@hgoa,@r3,@cp,13500,NULL,NULL),(@hgoa,@r3,@map,15000,NULL,NULL),(@hgoa,@r3,@ap,17000,NULL,NULL),(@hgoa,@r3,@ai,20000,NULL,NULL),
(@hmum,@r4,@ep,4000,NULL,NULL),(@hmum,@r4,@cp,4700,NULL,NULL),(@hmum,@r4,@map,6000,NULL,NULL),(@hmum,@r4,@ap,7200,NULL,NULL),(@hmum,@r4,@ai,9000,NULL,NULL),
(@hmum,@r5,@ep,6000,NULL,NULL),(@hmum,@r5,@cp,7000,NULL,NULL),(@hmum,@r5,@map,8500,NULL,NULL),(@hmum,@r5,@ap,10000,NULL,NULL),(@hmum,@r5,@ai,12000,NULL,NULL);

-- 14-day availability for each room (used by the Availability calendar)
DROP PROCEDURE IF EXISTS `seed_mgr_availability`;
DELIMITER $$
CREATE PROCEDURE `seed_mgr_availability`()
BEGIN
  DECLARE i INT DEFAULT 0;
  WHILE i < 14 DO
    SET @d = DATE_ADD(CURDATE(), INTERVAL i DAY);
    INSERT IGNORE INTO `room_availability` (`hotel_id`,`room_category_id`,`availability_date`,`total_rooms`,`available_rooms`,`booked_rooms`,`blocked_rooms`)
    SELECT hr.hotel_id, hr.id, @d, hr.total_rooms,
           GREATEST(CAST(hr.total_rooms AS SIGNED) - 2, 0), 0, 0
    FROM `hotel_room_categories` hr WHERE hr.status='active';
    SET i = i + 1;
  END WHILE;
END$$
DELIMITER ;
CALL `seed_mgr_availability`();
DROP PROCEDURE IF EXISTS `seed_mgr_availability`;

SET FOREIGN_KEY_CHECKS = 1;-- ═══════════════════════════════════════════════════════════════════════════
-- PART 4 — CRM / LISTING SEED DATA  (from sql.sql)
-- ═══════════════════════════════════════════════════════════════════════════
-- Login users (upsert)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@company.com', '$2y$10$EgPJSbA1QtsLdd8UECASLu6qG7fH2ty88p4.lBU/5PWauGFrR1aFG', 'admin')
ON DUPLICATE KEY UPDATE email = VALUES(email), password = VALUES(password), role = VALUES(role);

INSERT INTO users (username, email, password, role) VALUES
('employee', 'employee@company.com', '$2y$10$0hKGoDw4pMz26uvzKH2p0ew9Sj9X1yI5DDYPwsOkdguLc0jVlsvLW', 'employee')
ON DUPLICATE KEY UPDATE email = VALUES(email), password = VALUES(password), role = VALUES(role);

-- Dashboard rows
INSERT INTO dashboard_details (stat_date, note) VALUES
(CURDATE(), 'Daily dashboard snapshot'),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Previous day snapshot'),
(DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Two days ago snapshot'),
(DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Three days ago snapshot'),
(DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Four days ago snapshot'),
(DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Five days ago snapshot'),
(DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Six days ago snapshot')
ON DUPLICATE KEY UPDATE note = VALUES(note);

-- Agents
INSERT INTO agents_details (name, email, phone, location, rating, status, total_deals, total_revenue) VALUES
('Amit Kumar', 'amit.k@estate.com', '+91 98765 43210', 'Sector 150, Noida', 4.8, 'Active', 84, 45000000),
('Neha Singh', 'neha.s@estate.com', '+91 91234 56789', 'Noida Extension', 4.9, 'Active', 62, 31000000),
('Rahul Deshmukh', 'rahul.d@estate.com', '+91 99887 76655', 'Sector 62, Noida', 4.7, 'Active', 38, 58000000),
('Priya Mehta', 'priya.m@estate.com', '+91 98765 01234', 'Greater Noida West', 4.9, 'On Leave', 55, 82000000),
('Vikas Sharma', 'vikas.s@estate.com', '+91 91111 22222', 'Yamuna Expressway', 4.2, 'Active', 14, 11000000),
('Sneha Gupta', 'sneha.g@estate.com', '+91 99988 87776', 'Sector 110, Noida', 4.6, 'Active', 92, 28000000),
('Karan Mehra', 'karan.mehra@estate.com', '+91 98989 12121', 'Gurgaon', 4.5, 'Active', 47, 25000000),
('Maya Patel', 'maya.patel@estate.com', '+91 98111 22334', 'Noida Sector 63', 4.4, 'Active', 33, 18000000),
('Rohit Singh', 'rohit.singh@estate.com', '+91 97654 32109', 'Greater Noida', 4.3, 'Inactive', 18, 9000000),
('Anjali Khanna', 'anjali.k@estate.com', '+91 99000 44556', 'Sector 78, Noida', 4.7, 'Active', 50, 32000000)
ON DUPLICATE KEY UPDATE
phone = VALUES(phone), location = VALUES(location), rating = VALUES(rating), status = VALUES(status),
total_deals = VALUES(total_deals), total_revenue = VALUES(total_revenue);

-- Employees
INSERT INTO employees_details (name, email, phone, designation, department, status, monthly_salary, created_at) VALUES
('Amit Kumar', 'amit.kumar@company.com', '+91 9876543210', 'Senior Sales Executive', 'Sales', 'Active', 52000, NOW()),
('Neha Singh', 'neha.singh@company.com', '+91 9123456789', 'Property Consultant', 'Sales', 'Active', 47000, NOW()),
('Rahul Verma', 'rahul.verma@company.com', '+91 9988776655', 'Commercial Sales Lead', 'Commercial', 'Active', 58000, NOW()),
('Priya Mehta', 'priya.mehta@company.com', '+91 9876501234', 'Luxury Accounts Manager', 'Accounts', 'On Leave', 61000, NOW()),
('Vikas Sharma', 'vikas.sharma@company.com', '+91 9111122222', 'Junior Estate Agent', 'Sales', 'Active', 45000, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Sneha Gupta', 'sneha.gupta@company.com', '+91 9998887776', 'Leasing Expert', 'Leasing', 'Active', 49000, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Aakash Jain', 'aakash.jain@company.com', '+91 9988002211', 'Operations Executive', 'Operations', 'Active', 43000, NOW()),
('Ritu Sharma', 'ritu.sharma@company.com', '+91 9123409876', 'Accounts Associate', 'Accounts', 'Active', 47000, DATE_SUB(NOW(), INTERVAL 1 DAY)),
('Manish Gupta', 'manish.gupta@company.com', '+91 9811223344', 'Property Analyst', 'Research', 'Active', 52000, DATE_SUB(NOW(), INTERVAL 2 DAY)),
('Deepa Kapoor', 'deepa.kapoor@company.com', '+91 9900112233', 'Client Relations Manager', 'Sales', 'Active', 56000, NOW())
ON DUPLICATE KEY UPDATE
phone = VALUES(phone), designation = VALUES(designation), department = VALUES(department),
status = VALUES(status), monthly_salary = VALUES(monthly_salary);

-- Hotels
INSERT INTO hotel_listings_details (hotel_name, category, location, room_type, weekday_price, weekend_price, gst, status) VALUES
('Taj Mahal Palace', 'Luxury', 'Mumbai', 'Suite Room', 42000, 45000, 18, 'Active'),
('Radisson Blu', 'Business', 'Noida', 'Deluxe Double', 11000, 12500, 18, 'Active'),
('ITC Maurya', 'Luxury', 'Delhi', 'Presidential Suite', 76000, 80000, 18, 'Active'),
('JW Marriott', 'Premium', 'Delhi', 'Standard Twin', 20000, 22000, 18, 'Active'),
('Novotel', 'Business', 'Noida', 'Executive Room', 14000, 15500, 18, 'Active'),
('The Lalit', 'Luxury', 'Delhi', 'Luxury Suite', 65000, 69000, 18, 'Active'),
('Playce', 'Premium', 'Noida', 'Family Room', 12500, 14500, 18, 'Active'),
('Holiday Inn', 'Business', 'Noida', 'Executive Suite', 16000, 18500, 18, 'Active')
ON DUPLICATE KEY UPDATE
category = VALUES(category), location = VALUES(location), room_type = VALUES(room_type),
weekday_price = VALUES(weekday_price), weekend_price = VALUES(weekend_price), gst = VALUES(gst), status = VALUES(status);

-- Bookings
INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9021', 'Rajesh Sharma', '+91 9876543210', 'rajesh@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 45000, 'Confirmed', CURDATE()
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'amit.k@estate.com'
JOIN employees_details e ON e.email = 'amit.kumar@company.com'
WHERE h.hotel_name = 'Taj Mahal Palace'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9022', 'Priya Singh', '+91 9123456789', 'priya.s@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 6 DAY), 12500, 'Confirmed', DATE_SUB(CURDATE(), INTERVAL 1 DAY)
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'neha.s@estate.com'
JOIN employees_details e ON e.email = 'neha.singh@company.com'
WHERE h.hotel_name = 'Radisson Blu'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9023', 'Vikram Verma', '+91 9988776655', 'v.verma@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 9 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY), 80000, 'Pending Payment', DATE_SUB(CURDATE(), INTERVAL 2 DAY)
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'rahul.d@estate.com'
JOIN employees_details e ON e.email = 'rahul.verma@company.com'
WHERE h.hotel_name = 'ITC Maurya'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9024', 'Sneha Gupta', '+91 9876501234', 'sneha.g@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 22000, 'Confirmed', DATE_SUB(CURDATE(), INTERVAL 3 DAY)
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'amit.k@estate.com'
JOIN employees_details e ON e.email = 'amit.kumar@company.com'
WHERE h.hotel_name = 'JW Marriott'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9025', 'Ananya Desai', '+91 9998887776', 'adesai@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 11 DAY), DATE_ADD(CURDATE(), INTERVAL 13 DAY), 15500, 'Cancelled', DATE_SUB(CURDATE(), INTERVAL 4 DAY)
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'neha.s@estate.com'
JOIN employees_details e ON e.email = 'neha.singh@company.com'
WHERE h.hotel_name = 'Novotel'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9026', 'Suresh Patel', '+91 9988001122', 'suresh.patel@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 8 DAY), 12500, 'Confirmed', DATE_SUB(CURDATE(), INTERVAL 1 DAY)
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'neha.s@estate.com'
JOIN employees_details e ON e.email = 'neha.singh@company.com'
WHERE h.hotel_name = 'Radisson Blu'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9027', 'Anjali Singh', '+91 9812345678', 'anjali.singh@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 4 DAY), 69000, 'Confirmed', CURDATE()
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'rahul.d@estate.com'
JOIN employees_details e ON e.email = 'rahul.verma@company.com'
WHERE h.hotel_name = 'ITC Maurya'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

INSERT INTO bookings_details (booking_code, client_name, client_phone, client_email, hotel_listing_id, agent_id, employee_id, check_in, check_out, amount, status, booking_date)
SELECT 'BK-9028', 'Neelam Sharma', '+91 9661122334', 'neelam.sharma@example.com', h.id, a.id, e.id,
       DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 3 DAY), 15500, 'Pending Payment', DATE_SUB(CURDATE(), INTERVAL 1 DAY)
FROM hotel_listings_details h
JOIN agents_details a ON a.email = 'amit.k@estate.com'
JOIN employees_details e ON e.email = 'aakash.jain@company.com'
WHERE h.hotel_name = 'Playce'
ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), booking_date = VALUES(booking_date);

-- Accounts
INSERT INTO accounts_details (entry_date, employee_id, entry_type, amount, notes)
SELECT CURDATE(), e.id, 'commission', 45000, 'Commission from BK-9021'
FROM employees_details e WHERE e.email = 'amit.kumar@company.com';

INSERT INTO accounts_details (entry_date, employee_id, entry_type, amount, notes)
SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY), e.id, 'payout', 12500, 'Payout for BK-9022'
FROM employees_details e WHERE e.email = 'neha.singh@company.com';

INSERT INTO accounts_details (entry_date, employee_id, entry_type, amount, notes)
SELECT DATE_SUB(CURDATE(), INTERVAL 2 DAY), e.id, 'receipt', 80000, 'Pending receipt for BK-9023'
FROM employees_details e WHERE e.email = 'rahul.verma@company.com';

INSERT INTO accounts_details (entry_date, employee_id, entry_type, amount, notes)
SELECT DATE_SUB(CURDATE(), INTERVAL 3 DAY), e.id, 'commission', 22000, 'Commission from BK-9024'
FROM employees_details e WHERE e.email = 'amit.kumar@company.com';

INSERT INTO accounts_details (entry_date, employee_id, entry_type, amount, notes)
SELECT DATE_SUB(CURDATE(), INTERVAL 4 DAY), e.id, 'expense', 5400, 'Office supplies for booking operations'
FROM employees_details e WHERE e.email = 'ritu.sharma@company.com';

INSERT INTO accounts_details (entry_date, employee_id, entry_type, amount, notes)
SELECT DATE_SUB(CURDATE(), INTERVAL 5 DAY), e.id, 'receipt', 12500, 'Payment received for BK-9026'
FROM employees_details e WHERE e.email = 'neha.singh@company.com';

-- Hotel room category sample data
INSERT INTO hotel_listing_room_categories (listing_id, category_name, validity, validity_start, validity_end, weekday_price, weekend_price, gst,
    weekday_cpai, weekday_mapai, weekday_apai, weekend_cpai, weekend_mapai, weekend_apai,
    child_no_bed_cpai, child_no_bed_mapai, child_no_bed_apai,
    child_with_bed_cpai, child_with_bed_mapai, child_with_bed_apai,
    adult_with_bed_cpai, adult_with_bed_mapai, adult_with_bed_apai,
    cpai_price, mapai_price, extra_person_with_bed, extra_person_without_bed,
    child_no_bed_cp, child_no_bed_map, child_with_bed_cp, child_with_bed_map,
    room_image_url, room_details)
SELECT h.id, 'Premium Suite', 'Peak Season', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 90 DAY), 42000, 45000, 18,
    39000, 41000, 43000, 42000, 44000, 46000,
    8000, 8500, 9000,
    10000, 10500, 11000,
    15000, 15500, 16000,
    42000, 45000, 1400, 1200,
    8000, 8500, 10000, 10500,
    'Mon,Tue,Wed,Thu,Fri', 'Sat,Sun', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 'Mon,Tue,Wed,Thu,Fri,Sat,Sun',
    'https://example.com/images/taj-suite.jpg', 'Spacious suite with city view'
FROM hotel_listings_details h
WHERE h.hotel_name = 'Taj Mahal Palace'
  AND NOT EXISTS (
      SELECT 1 FROM hotel_listing_room_categories c WHERE c.listing_id = h.id AND c.category_name = 'Premium Suite'
  );

INSERT INTO hotel_listing_room_categories (listing_id, category_name, validity, validity_start, validity_end, weekday_price, weekend_price, gst,
    weekday_cpai, weekday_mapai, weekday_apai, weekend_cpai, weekend_mapai, weekend_apai,
    child_no_bed_cpai, child_no_bed_mapai, child_no_bed_apai,
    child_with_bed_cpai, child_with_bed_mapai, child_with_bed_apai,
    adult_with_bed_cpai, adult_with_bed_mapai, adult_with_bed_apai,
    cpai_price, mapai_price, extra_person_with_bed, extra_person_without_bed,
    child_no_bed_cp, child_no_bed_map, child_with_bed_cp, child_with_bed_map,
    room_image_url, room_details)
SELECT h.id, 'Deluxe Double', 'Monsoon Special', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 60 DAY), 11000, 12500, 18,
    9800, 10500, 11000, 10200, 10800, 11500,
    2400, 2600, 2800,
    3200, 3400, 3600,
    4200, 4500, 4800,
    11000, 12500, 900, 700,
    2400, 2600, 3200, 3400,
    'https://example.com/images/radisson-deluxe.jpg', 'Comfortable double room with business amenities'
FROM hotel_listings_details h
WHERE h.hotel_name = 'Radisson Blu'
  AND NOT EXISTS (
      SELECT 1 FROM hotel_listing_room_categories c WHERE c.listing_id = h.id AND c.category_name = 'Deluxe Double'
  );

INSERT INTO hotel_listing_room_categories (listing_id, category_name, validity, validity_start, validity_end, weekday_price, weekend_price, gst,
    weekday_cpai, weekday_mapai, weekday_apai, weekend_cpai, weekend_mapai, weekend_apai,
    child_no_bed_cpai, child_no_bed_mapai, child_no_bed_apai,
    child_with_bed_cpai, child_with_bed_mapai, child_with_bed_apai,
    adult_with_bed_cpai, adult_with_bed_mapai, adult_with_bed_apai,
    cpai_price, mapai_price, extra_person_with_bed, extra_person_without_bed,
    child_no_bed_cp, child_no_bed_map, child_with_bed_cp, child_with_bed_map,
    room_image_url, room_details)
SELECT h.id, 'Presidential Suite', 'Summer Offer', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 120 DAY), 76000, 80000, 18,
    71000, 74000, 78000, 73000, 76000, 80000,
    12500, 13200, 14000,
    15200, 15900, 16500,
    18500, 19000, 19500,
    76000, 80000, 2200, 2000,
    12500, 13200, 15200, 15900,
    'https://example.com/images/itc-presidential.jpg', 'Opulent suite with executive service'
FROM hotel_listings_details h
WHERE h.hotel_name = 'ITC Maurya'
  AND NOT EXISTS (
      SELECT 1 FROM hotel_listing_room_categories c WHERE c.listing_id = h.id AND c.category_name = 'Presidential Suite'
  );

INSERT INTO hotel_listing_room_categories (listing_id, category_name, validity, validity_start, validity_end, weekday_price, weekend_price, gst,
    weekday_cpai, weekday_mapai, weekday_apai, weekend_cpai, weekend_mapai, weekend_apai,
    child_no_bed_cpai, child_no_bed_mapai, child_no_bed_apai,
    child_with_bed_cpai, child_with_bed_mapai, child_with_bed_apai,
    adult_with_bed_cpai, adult_with_bed_mapai, adult_with_bed_apai,
    cpai_price, mapai_price, extra_person_with_bed, extra_person_without_bed,
    child_no_bed_cp, child_no_bed_map, child_with_bed_cp, child_with_bed_map,
    room_image_url, room_details)
SELECT h.id, 'Standard Twin', 'Weekend Deal', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 45 DAY), 20000, 22000, 18,
    18500, 19500, 20500, 19000, 20500, 22000,
    5600, 5800, 6000,
    6500, 6700, 6900,
    7800, 8200, 8500,
    20000, 22000, 1100, 1000,
    5600, 5800, 6500, 6700,
    'https://example.com/images/jw-twin.jpg', 'Comfortable twin beds with city view'
FROM hotel_listings_details h
WHERE h.hotel_name = 'JW Marriott'
  AND NOT EXISTS (
      SELECT 1 FROM hotel_listing_room_categories c WHERE c.listing_id = h.id AND c.category_name = 'Standard Twin'
  );

INSERT INTO hotel_listing_room_categories (listing_id, category_name, validity, validity_start, validity_end, weekday_price, weekend_price, gst,
    weekday_cpai, weekday_mapai, weekday_apai, weekend_cpai, weekend_mapai, weekend_apai,
    child_no_bed_cpai, child_no_bed_mapai, child_no_bed_apai,
    child_with_bed_cpai, child_with_bed_mapai, child_with_bed_apai,
    adult_with_bed_cpai, adult_with_bed_mapai, adult_with_bed_apai,
    cpai_price, mapai_price, extra_person_with_bed, extra_person_without_bed,
    child_no_bed_cp, child_no_bed_map, child_with_bed_cp, child_with_bed_map,
    room_image_url, room_details)
SELECT h.id, 'Executive Room', 'Corporate Package', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 75 DAY), 14000, 15500, 18,
    13000, 14000, 15000, 13500, 14500, 15500,
    4300, 4500, 4700,
    5100, 5300, 5500,
    6200, 6500, 6800,
    14000, 15500, 1000, 900,
    4300, 4500, 5100, 5300,
    'https://example.com/images/novotel-executive.jpg', 'Executive room with workspace and premium services'
FROM hotel_listings_details h
WHERE h.hotel_name = 'Novotel'
  AND NOT EXISTS (
      SELECT 1 FROM hotel_listing_room_categories c WHERE c.listing_id = h.id AND c.category_name = 'Executive Room'
  );
 
-- Additional helpful indexes for faster listing queries
CREATE INDEX idx_hotel_category ON hotel_listings_details (category);
CREATE INDEX idx_hotel_location ON hotel_listings_details (location);
CREATE INDEX idx_hotel_status ON hotel_listings_details (status);
CREATE INDEX idx_room_listing_id ON hotel_listing_room_categories (listing_id);


-- ═══════════════════════════════════════════════════════════════════════════
-- PRODUCTION CLEAN START — remove all demo personnel, listings and transactions
-- ═══════════════════════════════════════════════════════════════════════════
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE booking_rooms;
TRUNCATE TABLE hotel_bookings;
TRUNCATE TABLE room_availability;
TRUNCATE TABLE room_prices;
TRUNCATE TABLE hotel_room_categories;
TRUNCATE TABLE hotels;
TRUNCATE TABLE hotel_listing_room_categories;
TRUNCATE TABLE hotel_listings_details;
TRUNCATE TABLE accounts_details;
TRUNCATE TABLE bookings_details;
TRUNCATE TABLE agent_query_locks;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE employees_details;
TRUNCATE TABLE agents_details;
TRUNCATE TABLE dashboard_details;
DELETE FROM users WHERE role = 'employee';
SET FOREIGN_KEY_CHECKS = 1;


-- ═══════════════════════════════════════════════════════════════════════════
-- FINAL CHECK QUERIES
-- ═══════════════════════════════════════════════════════════════════════════

SELECT 'Database imported successfully' AS message;
SELECT COUNT(*) AS total_hotels FROM `hotels`;
SELECT COUNT(*) AS total_rooms FROM `hotel_room_categories`;
SELECT COUNT(*) AS total_meal_plans FROM `meal_plans`;
SELECT COUNT(*) AS total_availability_rows FROM `room_availability`;
SELECT COUNT(*) AS total_rate_rows FROM `room_prices`;
SELECT COUNT(*) AS total_hotel_bookings FROM `hotel_bookings`;
SELECT COUNT(*) AS total_users FROM `users`;
SELECT COUNT(*) AS total_agents FROM `agents_details`;
SELECT COUNT(*) AS total_employees FROM `employees_details`;
SELECT COUNT(*) AS total_listings FROM `hotel_listings_details`;

-- ═══════════════════════════════════════════════════════════════════════════
-- END of all.sql
-- ═══════════════════════════════════════════════════════════════════════════
