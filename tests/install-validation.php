<?php
if(PHP_SAPI!=='cli')exit;
ini_set('zend.exception_ignore_args','1');
try {
$p=new PDO('mysql:host=127.0.0.1','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='crm_install_qa_'.bin2hex(random_bytes(4));
$p->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
putenv('CRM_DB_HOST=127.0.0.1');putenv('CRM_DB_NAME='.$name);putenv('CRM_DB_USER=root');putenv('CRM_DB_PASS=');
putenv('CRM_ADMIN_USERNAME=qa_install_admin');putenv('CRM_ADMIN_EMAIL=qa.install@example.test');
$pass=bin2hex(random_bytes(16));putenv('CRM_ADMIN_PASSWORD='.$pass);
require __DIR__.'/../scripts/install.php';
$record=$conn->query("SELECT password FROM users WHERE username='qa_install_admin' AND role='admin'")->fetchColumn();
if(!$record||!password_verify($pass,$record))throw new RuntimeException('Admin verification failed');
if((int)$conn->query('SELECT COUNT(*) FROM bookings_details')->fetchColumn()!==0)throw new RuntimeException('Unexpected seed data');
echo "PASS: fresh schema, compatibility migrations, admin hash and empty booking data verified.\n";
} catch(Throwable $e){fwrite(STDERR,'Install QA failed: '.$e->getMessage());exit(1);}
