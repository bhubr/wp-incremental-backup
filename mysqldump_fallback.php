<?php
use Ifsnop\Mysqldump as IMysqldump;
require 'vendor/autoload.php';
require 'class-t1z-wpib-exception.php';

if (php_sapi_name() !== 'cli') {
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
    $error = '404 Not Found';
    header($protocol . ' ' . $error);
    die($error);
}
if($argc < 6) exit;
$output_fullpath_prefix = $argv[1];
$host = $argv[2];
$db = $argv[3];
$user = $argv[4];
$pass = $argv[5];

try {
    $dump = new IMysqldump\Mysqldump("mysql:host={$host};dbname={$db}", $user, $pass);
    $dump->start("{$output_fullpath_prefix}.sql");
} catch (\Exception $e) {
    throw new T1z_WPIB_Exception($e->getMessage(), T1z_WPIB_Exception::MYSQL);
}
