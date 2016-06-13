<?php
require 'trait-t1z-wpib-utils.php';
// require 'sql-utils.php';

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    // see http://php.net/manual/en/class.errorexception.php
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler("exception_error_handler");

class WP_DB_Config_Extractor {
	private static $num = 0;

	function get_sql_config($backup_root, $site) {
		$wp_root = $backup_root . DIRECTORY_SEPARATOR . $site . DIRECTORY_SEPARATOR . 'wordpress';
		if (! file_exists("$wp_root/wp-config.php")) {
			return false;
		}
		$wp_config = file("$wp_root/wp-config.php");
		$db_lines = [];
		$prefix = 'DB' . static::$num++ . '_';
		array_map(function($line) use($prefix, &$db_lines) {
			if (preg_match('/define.*DB_(NAME|USER|PASSWORD|HOST|CHARSET|COLLATE).*/', $line)) {
				$db_lines[] = str_replace('DB_', $prefix, $line);
			}
		}, $wp_config);
		
		$db_config = implode("", $db_lines);
		eval($db_config);
		return [
			'name'     => constant("${prefix}NAME"),
			'user'     => constant("${prefix}USER"),
			'password' => constant("${prefix}PASSWORD"),
			'host'     => constant("${prefix}HOST"),
			'charset'  => constant("${prefix}CHARSET"),
			'collate'  => constant("${prefix}COLLATE")
		];
	}
}




class T1z_WP_Incremental_Backup_SQL_Injector {
	use T1z_WPIB_Utils;

	/**
	 * Sites config
	 */
	private $sites;

	/**
	 * Tool config
	 */
	private $config;

	/**
	 * Cloned domain names
	 */
	private $local_domain_names = [];

	/**
	 * Constructor: read ini file and setup cURL
	 */
	public function __construct() {
		$this->read_configs();
		$this->inject_sql();
	}

	/**
	 * read ini file
	 */
	private function read_configs() {
		$this->sites = parse_ini_file(__DIR__ . '/fetch.ini', true);
		$config = parse_ini_file(__DIR__ . '/sqlin.ini', true);
		$this->backup_root = $config['backup_root'];
	}

	public function get_latest_sql_filename($dir) {
	    $files = glob("$dir/*.sql");
	    $filename = array_pop($files);
	    return basename($filename);
	}


	private function inject_sql() {
		$conf_extractor = new WP_DB_Config_Extractor();
		foreach($this->sites as $site => $config) {
			// Get DB params from wp-config.php
			$db_config = $conf_extractor->get_sql_config($this->backup_root, $site);
			if (!$db_config) {
				echo "Skip: $site\n";
				continue;
			}
			var_dump($db_config);
			// $wp_root = $this->backup_root . DIRECTORY_SEPARATOR . $site . DIRECTORY_SEPARATOR . 'wordpress';
			// $wp_config = file("$wp_root/wp-config.php");
			// Truncate all db tables
			// Inject new dump
			// Replace strings
		}
	}
}

$sql_injector = new T1z_WP_Incremental_Backup_SQL_Injector();