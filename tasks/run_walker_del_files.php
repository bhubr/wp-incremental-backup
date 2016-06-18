<?php
define('PLUGIN_DIR', realpath(__DIR__ . '/..'));
require PLUGIN_DIR . '/inc/class-t1z-incremental-backup-deleted-walker.php';
if (php_sapi_name() !== 'cli') {
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	$error = '404 Not Found';
	header($protocol . ' ' . $error);
	die($error);
}
if($argc < 4) {
	echo "Usage:\n  php " . basename(__FILE__) . " <input_dir> <output_dir> <datetime>\n";
	exit(1);
}
$input_dir = $argv[1];
$output_dir = $argv[2];
$datetime = $argv[3];

$walker = new T1z_Incremental_Backup_Deleted_Walker($input_dir, $output_dir, $datetime);
$walker->run();