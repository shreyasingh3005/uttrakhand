<?php
if(PHP_SAPI!=='cli')exit;
putenv('CRM_DB_HOST=127.0.0.1');putenv('CRM_DB_NAME=crm_validation_20261007');putenv('CRM_DB_USER=root');putenv('CRM_DB_PASS=');
require __DIR__.'/../scripts/migrate_bookings.php';
