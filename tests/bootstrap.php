<?php
if (PHP_SAPI !== 'cli') exit;
ini_set('zend.exception_ignore_args','1');
// Environment is intentionally limited to the isolated test database.
putenv('CRM_DB_PASS=');
require __DIR__.'/../includes/db_connect.php';
require __DIR__.'/../includes/crm_booking.php';
crm_booking_schema($conn);
echo 'Compatibility schema and workflow ready',"\n";
