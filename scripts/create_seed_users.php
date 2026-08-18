<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'This script can only be run from the command line.';
    exit(1);
}
require_once __DIR__ . '/../includes/db_connect.php';

$users = [
    ['username'=>'admin','password'=>'admin123','email'=>'admin@example.com','role'=>'admin'],
    ['username'=>'employee','password'=>'employee123','email'=>'employee@example.com','role'=>'employee']
];

foreach ($users as $u) {
    $stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$u['username']]);
    if ($stmt->fetch()) {
        echo "User {$u['username']} exists\n";
        continue;
    }
    $hash = hash_password($u['password']);
    $ins = $conn->prepare('INSERT INTO users (username,password,email,role,created_at,is_logged_in) VALUES (?, ?, ?, ?, NOW(), 0)');
    $ins->execute([$u['username'],$hash,$u['email'],$u['role']]);
    echo "Created user {$u['username']}\n";
}
