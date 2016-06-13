<?php
define('PLUGIN_DIR', realpath(__DIR__ . '/..'));
use Ifsnop\Mysqldump as IMysqldump;
require PLUGIN_DIR . '/vendor/autoload.php';
require PLUGIN_DIR . '/class-t1z-wpib-exception.php';

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
    die("[MySQLdump/PHP] " . $e->getMessage() . "\n");
}
echo "done: " . filesize("{$output_fullpath_prefix}.sql") . " bytes written\n";