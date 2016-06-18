<?php
define('PLUGIN_DIR', realpath(__DIR__ . '/..'));
require PLUGIN_DIR . '/inc/class-t1z-incremental-backup-task-common.php';

if (php_sapi_name() !== 'cli') {
	$protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
	$error = '404 Not Found';
	header($protocol . ' ' . $error);
	die($error);
}

function my_autoloader($class) {
	$class_file_mapping = [
		'T1z_Incremental_Backup_Deleted_Walker' => 'deleted-walker',
		'T1z_Incremental_Backup_MD5_Walker' => 'md5-walker',
		'T1z_Incremental_Backup_Archiver' => 'archiver'
	];
	$file = $class_file_mapping[$class];
	// die($file);
    require PLUGIN_DIR . '/inc/class-t1z-incremental-backup-' . $file . '.php';
}

spl_autoload_register('my_autoloader');

// if($argc < 5) {
// 	echo "Usage:\n  php " . basename(__FILE__) . " <task> <input_dir> <output_dir> <datetime>\n";
// 	exit(1);
// }
// var_dump($argv);
// $options = getopt("t:i:o:d:e:");
// var_dump($options);
// foreach(['t' => '<task_name>', 'i' => '<input_dir>', 'o' => '<output_dir>', 'd' => '<datetime>'] as $opt => $value) {
// 	if (!isset($options[$opt])) {
// 		echo "Missing option: -{$opt} $value\n";
// 		echo "Usage:\n  php " . basename(__FILE__) . " -t <task> -i <input_dir> -o <output_dir> -d <datetime>\n";
// 		exit(1);
// 	}
// }

// $task_name = $options['t'];
// $input_dir = $options['i'];
// $output_dir = $options['o'];
// $datetime = $options['d'];
if ($argc < 5) {
	echo "Usage:\n  php " . basename(__FILE__) . " <task> <input_dir> <output_dir> <datetime> [... extra args ...]\n";
	exit(1);
}
$task_name = $argv[1];
$input_dir = $argv[2];
$output_dir = $argv[3];
$datetime = $argv[4];
$extra_opts = array_slice($argv, 5);
$class_name = T1z_Incremental_Backup_Task::get_task_class($task_name);

$task = new $class_name($input_dir, $output_dir, $datetime, $extra_opts);
$task->run();