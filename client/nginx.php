<?php

define('WPIB_CLIENT_DEBUG_MODE', true);
define('WPIB_CLIENT_DEBUG_LEN', 400);
define('BACKUP_ROOT', '/Volumes/Backup/Geek/Sites');

require realpath(__DIR__ . '/../common/constants.php');
require 'trait-t1z-wpib-utils.php';

class T1z_WP_Incremental_Backup_Nginx_Conf_Writer {
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
	 * Latest ZIP file name
	 */
	private $zip_filename;

	/**
	 * Virtual host config template
	 */
	private $template;

	/**
	 * Cloned domain names
	 */
	private $local_domain_names = [];

	/**
	 * Constructor: read ini file and setup cURL
	 */
	public function __construct() {
		$this->read_configs();
		$this->template = file_get_contents(__DIR__ . '/nginx_vhost.txt');
		$this->write_nginx_vhosts();
		// $this->write_hosts_file();
	}

	/**
	 * read ini file
	 */
	private function read_configs() {
		$this->sites = parse_ini_file(__DIR__ . '/fetch.ini', true);
		$config = parse_ini_file(__DIR__ . '/nginx.ini', true);
		$this->doc_root = $config['doc_root'];
		$this->nginx_sites = $config['nginx_sites'];
		$this->nginx_conf_dir = $config['nginx_conf_dir'];
		$this->nginx_sites_enabled = str_replace('sites-available', 'sites-enabled', $this->nginx_sites);
		// if (!is_dir($this->nginx_sites_enabled)) {
		// 	$did_create = mkdir($this->nginx_sites_enabled);
		// 	if (! $did_create) {
		// 		die("Could not create mirrors directory: {$this->nginx_sites_enabled}\n");
		// 	}
		// }
	}

	private function prepare_patterns($vars) {
		$patterns = [];
		foreach ($vars as $k) {
			$patterns[] = '/\{\{' . $k . '\}\}/';
		}
		return $patterns;
	}

	/**
	 * Write nginx vhost configs
	 */
	private function write_nginx_vhosts() {
		foreach($this->sites as $domain => $config) {

			$new_domain = $this->replace_domain_ext($domain);
			// Replace domain.ext with domain.clone
			$this->local_domain_names[] = $new_domain;

			// Replace variables in vhost conf template
			$patterns = $this->prepare_patterns(['domain', 'new_domain', 'doc_root', 'nginx_conf_dir']);
			$vars = [$domain, $new_domain, $this->doc_root, $this->nginx_conf_dir];
			$vhost_conf = preg_replace($patterns, $vars, $this->template);

			// Backup template if exists
			$vhost_file = $this->nginx_sites . DIRECTORY_SEPARATOR . $new_domain;
			$vhost_enabled_link = $this->nginx_sites_enabled . DIRECTORY_SEPARATOR . $new_domain;
			$vhost_exists = file_exists($vhost_file);
			if ($vhost_exists) {
				copy($vhost_file, $vhost_file . '.bak');
			}
			file_put_contents($vhost_file, $vhost_conf);
			// $vhost_enabled_link = str_replace('sites-available', 'sites-enabled', $vhost_file);

			echo "$vhost_enabled_link\n";
			// if (!is_link($vhost_enabled_link)) {
			// 	symlink($vhost_file, $vhost_enabled_link);	
			// }
		}
	}

	/**
	 * Write hosts file
	 */
	// private function write_hosts_file() {
	// 	$hosts_file = file('/etc/hosts');
	// 	$hosts_trimmed = array_map(function($line) {
	// 		return trim($line);
	// 	}, $hosts_file);
	// 	$wpib_begin_tag = array_search("# WPIB_BEGIN", $hosts_trimmed);
	// 	$wpib_end_tag = array_search("# WPIB_END", $hosts_trimmed);
	// 	if (!$wpib_begin_tag || !$wpib_end_tag) {
	// 		echo "Please insert those lines in your /etc/hosts file:\n# WPIB_BEGIN\n# WPIB_END\n";
	// 	}
	// 	$hosts_lines = array_map(function($domain) {
	// 		return "127.0.0.1 $domain\n";
	// 	}, $this->local_domain_names);
	// 	var_dump($hosts_lines);
	// 	array_splice($hosts_file, $wpib_end_tag, 0, $hosts_lines);
	// 	var_dump($hosts_file);
	// 	fil
	// 	// foreach($this->local_domain_names as $domain)
	// }
}

$nginx_conf_writer = new T1z_WP_Incremental_Backup_Nginx_Conf_Writer();
// var_dump($nginx_conf_writer);