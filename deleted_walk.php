<?php
require 'class-t1z-incremental-backup-deleted-walker.php';
if (php_sapi_name() !== 'cli') {
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	$error = '404 Not Found';
	header($protocol . ' ' . $error);
	die($error);
}
if($argc < 2) exit;
$archive_list = $argv[1];
$delete_list = $argv[2];
$input_dir = $argv[3];

$walker = new T1z_Incremental_Backup_Deleted_Walker($archive_list, $delete_list, $input_dir);
$walker->run();