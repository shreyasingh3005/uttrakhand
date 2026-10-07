<?php
if (PHP_SAPI !== 'cli-server') { http_response_code(403); exit; }
ini_set('zend.exception_ignore_args','1'); session_save_path(__DIR__.'/.runtime');
putenv('CRM_DB_HOST=127.0.0.1'); putenv('CRM_DB_NAME=crm_validation_20261007'); putenv('CRM_DB_USER=root'); putenv('CRM_DB_PASS='); putenv('CRM_APP_URL=http://127.0.0.1:8091');
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('~^/(?:tests|scripts|database|includes|\.git)|/\.|^/(?:seed_data|test_)~',$path)) { http_response_code(403); exit; }
if ($path==='/') $path='/index.php';
$file=realpath(__DIR__.'/..'.$path);
if (!$file || !is_file($file)) { http_response_code(404); exit; }
if (pathinfo($file,PATHINFO_EXTENSION)==='php') { require $file; return true; }
return false;

