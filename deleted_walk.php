<?php
require 'class-t1z-incremental-backup-deleted-walker.php';
if (php_sapi_name() !== 'cli') {
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	$error = '404 Not Found';
	header($protocol . ' ' . $error);
	die($error);
}
if($argc < 2) exit;
$output_txt = $argv[1];
$input_dir = $argv[2];

$walker = new T1z_Incremental_Backup_Deleted_Walker($output_txt, $input_dir);
$walker->prepare_files_archive();