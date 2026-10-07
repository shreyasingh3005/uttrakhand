<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../includes/config.php';
try {
    $cfg=config();
    $pdo=new PDO('mysql:host='.$cfg['DB_HOST'].';dbname='.$cfg['DB_NAME'].';charset=utf8mb4',$cfg['DB_USER'],$cfg['DB_PASS'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
    require __DIR__.'/migrate_bookings.php';
    $username=trim((string)getenv('CRM_ADMIN_USERNAME'));
    $email=trim((string)getenv('CRM_ADMIN_EMAIL'));
    $password=(string)getenv('CRM_ADMIN_PASSWORD');
    if ($username==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) {
        fwrite(STDERR,"Schema ready. Set CRM_ADMIN_USERNAME, CRM_ADMIN_EMAIL and CRM_ADMIN_PASSWORD (12+ characters), then run again.\n");exit(1);
    }
    if ((int)$conn->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()>0) {
        echo "An administrator exists; credentials were not changed.\n";exit;
    }
    $conn->prepare("INSERT INTO users(username,email,password,role) VALUES (?,?,?,'admin')")->execute([$username,$email,password_hash($password,PASSWORD_BCRYPT)]);
    echo "Admin created. Remove the three admin environment variables.\n";
} catch(Throwable $e) {
    error_log('CRM installation: '.$e->getMessage());
    fwrite(STDERR,"Installation failed. Review the server error log.\n");exit(1);
}
