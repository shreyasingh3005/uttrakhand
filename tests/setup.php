<?php
if (PHP_SAPI !== 'cli') exit;
ini_set('zend.exception_ignore_args','1');
try {
$p=new PDO('mysql:host=127.0.0.1;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$p->exec('CREATE DATABASE IF NOT EXISTS crm_validation_20261007 CHARACTER SET utf8mb4');
$p->exec('USE crm_validation_20261007');
$sql=file_get_contents(__DIR__.'/../database/all.sql');
preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?[a-z_]+`?\s*\([\s\S]*?;/', $sql,$matches);
foreach($matches[0] as $statement) {
 $statement=preg_replace('/CREATE TABLE(?! IF NOT EXISTS)/','CREATE TABLE IF NOT EXISTS',$statement);
 $p->exec($statement);
}
echo 'Isolated schema ready: ',count($matches[0])," tables\n";
} catch(Throwable $e) { echo 'Test schema failure: ',$e->getMessage(),"\n"; exit(1); }
