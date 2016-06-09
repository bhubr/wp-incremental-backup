<?php
/**
 * Plugin Name: WP Incremental Backup
 * Plugin URI: https://github.com/t1z/wp-incremental-backup
 * Description: Create incremental backups of WordPress files&db
 * Author: t1z
 * Author URI: https://github.com/t1z
 * Version: 0.2.2
 *
 * ChangeLog
 * 0.2.0  First public version
 * 0.2.1  Detect server soft
 * 0.2.2  Write .htaccess for Apache
 *
 * Different cases:
 * - upload media
 * - delete media
 * - add plugin
 * - delete plugin
 * - add theme
 * - delete theme
 * - edit plugin/theme file
 */
use Ifsnop\Mysqldump as IMysqldump;
require 'vendor/autoload.php';

define('FILES_TO_DELETE', '__deleted_files__.txt');

class Md5Walker {

	private $walk_dir;
	private $cnt;
	private $output_list_csv;
	private $output_prefix;
	private $output_dir;
	private $first_run;
	private $files;
	private $activation_id;
	private $server_soft;

	/**
	 * Initialize walk_dir, count, csv file
	 */	
	public function __construct() {
		add_action('admin_menu', [$this, 'wpdocs_register_my_custom_submenu_page']);
		add_action('admin_init', [$this, 'get_activation_id_and_setup']);
		register_activation_hook( __FILE__, [$this, 'set_activation_id'] );
	}

	private function is_apache() {
		return $this->server_soft === 'Apache';
	}

	public function get_activation_id_and_setup() {
		$server_soft = $_SERVER["SERVER_SOFTWARE"];
		$server_soft_bits = explode('/', $server_soft);
		$this->server_soft = $server_soft_bits[0];
		$this->cnt = 0;
		$this->activation_id = get_option('wpib_activation_id', true);
		$this->walk_dir = get_home_path();
		$this->output_dir = get_home_path() . "wp-content/wp-incremental-backup-{$this->activation_id}";
		if (! is_dir($this->output_dir)) {
			mkdir($this->output_dir);
		}
		if ($this->is_apache() && ! file_exists("{$this->output_dir}/.htaccess")) {
			file_get_contents("{$this->output_dir}/.htaccess", "Deny from all");
		}
		$this->output_list_csv = $this->output_dir . "/list.csv";
		$sanitized_blog_name = sanitize_title(get_option('blogname'));
		$this->output_prefix = $this->output_dir . DIRECTORY_SEPARATOR . $sanitized_blog_name . '_' . date("Ymd-Hi");
		$this->first_run = !file_exists($this->output_list_csv);
		if ($this->first_run) {
			$this->files = [];
		}
		else {
			$this->read();
		}
	}

	public function set_activation_id() {
		$activation_id = base_convert(time(), 10, 36);
		update_option( 'wpib_activation_id', $activation_id, true );
	}

	public function wpdocs_register_my_custom_submenu_page() {
			add_management_page( 'Incremental Backup', 'Incremental Backup', 'manage_options', 'incremental-backup', [$this, 'wpib_options_page']);
	}

	public function wpib_options_page() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$this->walk();
			try {
			    $dump = new IMysqldump\Mysqldump('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
			    $dump->start("{$this->output_prefix}.sql");
			} catch (\Exception $e) {
			    echo 'mysqldump-php error: ' . $e->getMessage();
			}
		}

		include 'run_form.php';
	}

	/**
	 * Check if file is a special dir: either . or ..
	 */
	private function is_special_dir($object) {
		return $object->getFilename() === '.' || $object->getFilename() === '..';
	}

	/**
	 * Check if file is the output dir
	 */
	private function is_output_dir($object) {
		// if(dirname($object->getPathname()) === $this->output_dir) echo dirname($object->getPathname()) . ' ' . $this->output_dir . '<br>';
		return dirname($object->getPathname()) === $this->output_dir;
	}

	/**
	 * Check if file is a regular file
	 */
	private function is_regular_file($object) {
		return !is_dir($object->getPathname());
	}

	/**
	 * Prepare a CSV line
	 */
	private function line($name, $md5 = "") {
		return "\"$name\",\"$md5\"\n";
	}

	/**
	 * Add a file to output
	 */
	private function add_file($name) {
		$this->cnt++;
		$md5 = md5_file($name);
		// echo "$name $md5\n";
		fwrite($this->fh, $this->line($name, $md5));
		return $md5;
	}

	/**
	 * Add a file to output
	 */
	private function add_dir($name) {
		fwrite($this->fh, $this->line($name));
	}

	/**
	 * Read last file list
	 */
	public function read() {
		$this->fh = fopen($this->output_list_csv, "r");
		do {
			$line_read = fgetcsv($this->fh);
			if (is_null($line_read)) {
				throw new Exception("invalid handle, aborting");
			}
			
			$name = $line_read[0];
			$md5 = $line_read[1];
			$this->files[$name] = $md5;
		} while($line_read !== false);
	}

	/**
	 * Get md5 from existing file
	 */
	private function get_md5($name) {
		return $this->files[$name];
	}

	/**
	 * Get filename, stripped from root dir (wp installation base dir)
	 */
	private function filename_from_root($filename) {
		$prefix_len = strlen($this->walk_dir);
		$last_char = $this->walk_dir[$prefix_len - 1];
		$prefix_len += ($last_char === '/') ? 0 : 1;
		return substr($filename, $prefix_len);
	}

	/**
	 * Write files to delete list
	 */
	private function write_files_to_delete($files_to_delete) {
		$dest = get_home_path() . DIRECTORY_SEPARATOR . FILES_TO_DELETE;
		$fh = fopen($dest, 'w');
		$num_to_delete = count($files_to_delete);
		for($i = 0 ; $i < $num_to_delete ; $i++) {
		 	$filename = $this->filename_from_root($files_to_delete[$i]);
		 	$not_last = $i < $num_to_delete - 1;
			fwrite($fh, $filename . ($not_last ? "\n" : ""));
		}
		return $num_to_delete > 0 ? $dest : "";
	}

	/**
	 * Write archive
	 */
	private function write_archive($files_to_archive) {
		$args = "";
		foreach($files_to_archive as $filename) {
			$args .= ' ' . escapeshellarg($this->filename_from_root($filename));
		}
		if (empty($args)) {
			echo "no archive to create\n";
			return;
		}
		$cmd = "cd {$this->walk_dir}; tar cvjf {$this->output_prefix}.tar.bz2{$args}";
		shell_exec($cmd);
	}

	/**
	 * Recurse wp installation
	 */
	public function walk() {
		$found_in_dirs = [];
		$files_to_archive = [];
		$files_to_delete = [];
		$this->fh = fopen($this->output_list_csv, "w");

		$objects = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->walk_dir),
			RecursiveIteratorIterator::SELF_FIRST
		);

		// Iterate directory
		foreach($objects as $name => $object) {

			// Skip deleted files list => delete it
			if($object->getFilename() === FILES_TO_DELETE) {
				unlink($object->getPathname());
				continue;
			}
			
			// Skip if this is . or .. or output dir 
			if($this->is_special_dir($object) || $this->is_output_dir($object)) {
				continue;
			}

			// Add dir
			if(!$this->is_regular_file($object)) {
				$this->add_dir($name);
				continue;
			}
			$found_in_dirs[] = $name;

			$md5 = $this->add_file($name);
			if($this->first_run || !array_key_exists($name, $this->files)) {
				echo "new file: $name<br>";
				$files_to_archive[] = $name;
				continue;
			}
			$old_md5 = $this->get_md5($name);
			if($md5 !== $old_md5) {
				echo "<em>modified</em> file: $name (old md5 = $old_md5, new md5 = $md5)<br>";
				$files_to_archive[] = $name;
			}

		}

		// Iterate existing files from previous backup's list
		foreach($this->files as $name => $md5) {
			if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
				echo "<em>deleted</em> file: $name<br>";
				$files_to_delete[] = $name;
			}
		}
		$deleted_list_file = $this->write_files_to_delete($files_to_delete);
		if (!empty($deleted_list_file)) {
			$files_to_archive[] = $deleted_list_file;
		}

		$this->write_archive($files_to_archive);

		fclose($this->fh);
		echo "done: {$this->cnt}\n";

	}
}

$args = !isset($argv) ? [ 'root' => $_GET['root'], 'domain' => $_GET['domain'] ] :
	[ 'root' => $argv[1], 'domain' => $argv[2] ];

$walker = new Md5Walker($args['root'], $args['domain']);

// $walker->walk();
