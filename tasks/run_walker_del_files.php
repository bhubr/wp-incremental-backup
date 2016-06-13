<?php
define('PLUGIN_DIR', realpath(__DIR__ . '/..'));
require PLUGIN_DIR . '/class-t1z-incremental-backup-deleted-walker.php';
if (php_sapi_name() !== 'cli') {
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	$error = '404 Not Found';
	header($protocol . ' ' . $error);
	die($error);
}
if($argc < 3) exit;
$delete_list = $argv[1];
$input_dir = $argv[2];
$output_dir = $argv[3];

$walker = new T1z_Incremental_Backup_Deleted_Walker($delete_list, $input_dir, $output_dir);
$walker->run();