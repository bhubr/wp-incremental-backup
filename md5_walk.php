<?php
require 'class-t1z-incremental-backup-md5-walker.php';
if (php_sapi_name() !== 'cli') {
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	$error = '404 Not Found';
	header($protocol . ' ' . $error);
	die($error);
}
if($argc < 2) exit;
$output_csv = $argv[1];
$input_dir = $argv[2];

$walker = new T1z_Incremental_Backup_MD5_Walker($output_csv, $input_dir);
$walker->prepare_files_archive();