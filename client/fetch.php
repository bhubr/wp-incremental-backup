<?php

define('WPIB_CLIENT_DEBUG_MODE', true);
define('WPIB_CLIENT_DEBUG_LEN', 400);
define('BACKUP_ROOT', '/Volumes/Backup/Geek/Sites');

require realpath(__DIR__ . '/../common/constants.php');

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
	 * Latest ZIP file name
	 */
	private $zip_filename;

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
		curl_setopt ($ch, CURLOPT_TIMEOUT, 600);
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
			printf ("\n\n *******   Begin process for site: %25s   ********\n", $site);

			if (substr($config['url'], -1) !== '/') {
				$config['url'] .= '/';
			}
			$this->get_login($config);
			$this->get_login($config);
			$this->post_login($config);
			$this->get_admin($config);
			$this->post_generate_backup($config, $site);
			$this->get_fetch_backup_and_concat($config, $site);
		}
		curl_close($this->ch);
	}

	/**
	 * Basic log function
	 */
	private function log($label, $str) {
		echo "----- $label -----\n";
		echo substr($str, 0, WPIB_CLIENT_DEBUG_LEN) . "\n\n"; 
	}

	/**
	 * GET WordPress login page
	 */
	private function get_login($config) {
		$login_url = array_key_exists('login_url', $config) ? $config['login_url'] : 'wp-login.php';
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . $login_url);
		$result = curl_exec ($this->ch);
		if (! $result) die("[get_login] cURL error: " . curl_error($this->ch) . "\n");
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('GET login page', $result);
	}

	/**
	 * POST request to sign in to WordPress
	 */
	private function post_login($config) {
		$postdata = "log=". $config['username'] ."&pwd=". urlencode($config['password']) ."&wp-submit=Se+connecter&redirect_to=". $config['url'] ."wp-admin/&testcookie=1";
		// die($config['password']);

		curl_setopt ($this->ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($this->ch, CURLOPT_POST, 1);
		$result = curl_exec ($this->ch);
		$lines = explode("\n", $result);
		if (count($lines) < 2) {
			echo "This doesn't seem like an HTML output:\n";
			var_dump($lines);
			exit;
		}
		if (! preg_match('/.*wp\-toolbar.*/', $lines[2])) {
			echo "There should be 'wp-toolbar' on this line:\n";
			echo $lines[2] . "\n";
			var_dump($lines);
			// exit;
		}
		if (! $result) die("[post_login] cURL error: " . curl_error($this->ch) . "\n");
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('POST login credentials', $result);
	}

	/**
	 * GET WordPress admin page
	 */
	private function get_admin($config) {
		curl_setopt ($this->ch, CURLOPT_POST, 0);
		curl_setopt ($this->ch, CURLOPT_POSTFIELDS, "");
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . 'wp-admin/');
		$result = curl_exec ($this->ch);
		if (! $result) die("[get_login] cURL error: " . curl_error($this->ch) . "\n");
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('GET login page', $result);
	}

	/**
	 * POST request to generate backup
	 */
	private function post_generate_backup($config, $site) {

		// clear POST data
		curl_setopt ($this->ch, CURLOPT_POSTFIELDS, "");

		// base URL (step param will be appended later)
		$gen_url = $config['url'] . "wp-admin/admin-ajax.php?action=wpib_generate";
		$check_url = $config['url'] . "wp-admin/admin-ajax.php?action=wpib_check_progress";

		// various steps of process
		$steps = ['lists', 'md5', 'tar', 'sql', 'zip'];

		foreach($steps as $step) {
			$url = "$gen_url&step=$step";
			if(isset($config['php_path'])) $url .= '&php_path=' . urlencode($config['php_path']);
			curl_setopt ($this->ch, CURLOPT_URL, $url);
			printf(" * Start step %5s", $step);
			// "$step ==> generate ($gen_url&step=$step)";

			// Send request and die on cURL error
			$json_response = curl_exec ($this->ch);
			$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

			// Die on HTTP error
			if ($http_code !== 200) {
				die(" !!! HTTP error: $json_response\n");
			}

			// Die on cURL error
			if (empty($json_response)) {
				die(" !!! [post_generate_backup] cURL error: " . curl_error($this->ch) . "\n");
			}

// var_dump($json_response);
			// Parse JSON response
			$parsed_response = json_decode($json_response);

			// Die on empty parsed response
			if (empty($parsed_response)) {
				echo " !!! [post_generate_backup] JSON parse error. Received payload:\n";
				die($json_response . "\n");
			}

var_dump($parsed_response);
			// Die on process error and give details
			if (!$parsed_response->success) {
				echo "\n\n!!! An error occurred during processing ($step - {$parsed_response->step_of_total}). ABORTING !!!\n";
				// echo $parsed_response->error_details . "\n";
				var_dump($parsed_response);
				exit;
			}

			echo " ($parsed_response->step_of_total)  ==>  .";
			$num_calls = 1;


			// var_dump($parsed_response);
			// Parse response and die on error
			curl_setopt ($this->ch, CURLOPT_URL, "$check_url&step=$step");
			while($parsed_response->done === false) {
				// echo "$step ==> check ($check_url&step=$step)\n";
				echo ".";
				$num_calls += 1;
				

				$json_response = curl_exec ($this->ch);
				$parsed_response = json_decode($json_response);
				// var_dump($json_response);
				// file_put_contents(__DIR__ . '/fetch_dbg.html', $json_response);
				// var_dump($parsed_response);
				// sleep(1);
			}
			$padding = 31 - $num_calls;
			printf("%{$padding}s", "OK *\n");


			// if ($parsed_response->success === false) {
			// 	die("[post_generate_backup] error:\n * type: {$parsed_response->error_type}\n * details: {$parsed_response->error_details}\n");
			// }
			

			// if (WPIB_CLIENT_DEBUG_MODE) $this->log('POST generate backup', $this->zip_filename);

		}
		$this->zip_filename = basename($parsed_response->files[0]);
		printf(" * Process done for %25s! *\n", $site);
		printf(" * Output ZIP filename: %38s *\n", $this->zip_filename);
		
	}

	private function get_destination_dir($site) {
		return BACKUP_ROOT . DIRECTORY_SEPARATOR . $site;
	}

	/**
	 * GET request to fetch backup
	 */
	private function get_fetch_backup_and_concat($config, $site) {
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . "wp-admin/admin-ajax.php?action=wpib_download");
		curl_setopt ($this->ch, CURLOPT_POST, 0);
		$data = curl_exec ($this->ch);
		if (! $data) die("[get_fetch_backup_and_concat] cURL error: " . curl_error($this->ch) . "\n");

		$dest_dir_prefix = $this->get_destination_dir($site);
		$dest_dir = $dest_dir_prefix . DIRECTORY_SEPARATOR . "wpib";
		$wp_expanded_dir = $dest_dir_prefix . DIRECTORY_SEPARATOR . "wordpress";

		if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);
		if (!is_dir($wp_expanded_dir)) mkdir($wp_expanded_dir);

		$destination = $dest_dir . DIRECTORY_SEPARATOR . $this->zip_filename;
		$file = fopen($destination, "w+");
		fputs($file, $data);
		fclose($file);

		$info = pathinfo($destination);
		$filename_prefix =  basename($destination,'.'.$info['extension']);
		$tar = "$filename_prefix.tar";
		$tar_fullpath = $dest_dir . DIRECTORY_SEPARATOR . $tar;

		$cmd1 = "cd $dest_dir; unzip {$this->zip_filename};";
		echo $cmd1 . "\n";
		echo shell_exec($cmd1);

		if(file_exists($tar_fullpath)) {
			$cmd2 = "cd $wp_expanded_dir; tar xvf $tar_fullpath";
			echo $cmd2 . "\n";
			echo shell_exec($cmd2);

			$to_delete_list_file = $wp_expanded_dir . DIRECTORY_SEPARATOR . FILES_TO_DELETE;
			if(file_exists($to_delete_list_file)) {
				$files_to_delete = file($to_delete_list_file);
				$files_to_delete_escaped = array_map(function($file) {
					return escapeshellarg(trim($file));
				}, $files_to_delete);
				$files_to_delete_str = implode(' ', $files_to_delete_escaped);
				// var_dump($files_to_delete_str);
				$cmd3 = "cd $wp_expanded_dir; rm $files_to_delete_str";
				echo shell_exec($cmd3);

				// unlink($to_delete_list_file);
			}
		}
	}
}



// // 2- POST




$client = new T1z_WP_Incremental_Backup_Client();
$client->run();
exit;