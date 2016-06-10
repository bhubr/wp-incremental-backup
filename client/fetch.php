<?php

define('WPIB_CLIENT_DEBUG_MODE', true);
define('WPIB_CLIENT_DEBUG_LEN', 300);

class T1z_WP_Incremental_Backup_Client {

	/**
	 * cURL handle
	 */
	private $ch;

	/**
	 * Config data
	 */
	private $config;

	/**
	 * Constructor: read ini file and setup cURL
	 */
	public function __construct() {
		$this->read_config();
		$this->setup_curl();
	}

	/**
	 * read ini file
	 */
	private function read_config() {
		$this->config = parse_ini_file(__DIR__ . '/fetch.ini', true);
	}

	/**
	 * cURL base setup
	 */
	private function setup_curl() {
		$ch = curl_init();
		$cookie = tempnam ("/tmp", "CURLCOOKIE");
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
		curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt ($ch, CURLOPT_COOKIE, $cookie);
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
		// curl_setopt ($ch, CURLOPT_REFERER, $url . "wp-admin/");
		$this->ch = $ch;
	}

	/**
	 * Run process
	 */
	public function run() {
		foreach ($this->config as $site => $config) {
			echo "\n-----===== Run process for site: $site =====-----\n\n";
			$this->get_login($config);
			$this->post_login($config);
			$this->post_generate_backup($config);
			$this->get_fetch_backup($config, $site);
		}
		curl_close($this->ch);
	}

	/**
	 * Basic log function
	 */
	private function log($label, $str) {
		echo "----- $label -----\n";
		echo substr($str, 0, WPIB_CLIENT_DEBUG_LEN) . "\n\n\n"; 
	}

	/**
	 * GET WordPress login page
	 */
	private function get_login($config) {
		$login_url = array_key_exists('login_url', $config) ? $config['login_url'] : 'wp-login.php';
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . $login_url);
		$result = curl_exec ($this->ch);
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('GET login page', $result);
	}

	/**
	 * POST request to sign in to WordPress
	 */
	private function post_login($config) {
		$postdata = "log=". $config['username'] ."&pwd=". $config['password'] ."&wp-submit=Log%20In&redirect_to=". $config['url'] ."wp-admin/&testcookie=1";
		curl_setopt ($this->ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($this->ch, CURLOPT_POST, 1);
		$result = curl_exec ($this->ch);
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('POST login credentials', $result);
	}

	/**
	 * POST request to generate backup
	 */
	private function post_generate_backup($config) {
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . "wp-admin/tools.php?page=incremental-backup");
		curl_setopt ($this->ch, CURLOPT_POSTFIELDS, "");
		$result = curl_exec ($this->ch);
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('POST generate backup', $result);
	}

	/**
	 * GET request to fetch backup
	 */
	private function get_fetch_backup($config, $site) {
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . "wp-admin/admin-ajax.php?action=wpib_download");
		curl_setopt ($this->ch, CURLOPT_POST, 0);
		$data = curl_exec ($this->ch);

		$destination = "./$site-latest.zip";
		$file = fopen($destination, "w+");
		fputs($file, $data);
		fclose($file);
	}
}



// // 2- POST




$client = new T1z_WP_Incremental_Backup_Client();
$client->run();
exit;