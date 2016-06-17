<?php

define('WPIB_CLIENT_DEBUG_MODE', true);
define('WPIB_CLIENT_DEBUG_LEN', 15);
define('BACKUP_ROOT', '/Volumes/Backup/Geek/Sites');

require realpath(__DIR__ . '/../inc/constants.php');

$global_fh = null;




function progress($resource,$download_size, $downloaded, $upload_size, $uploaded)
{
    if($download_size > 0)
         echo $downloaded / $download_size  * 100;
    // ob_flush();
    // flush();
    // sleep(1); // just to see effect
}


class T1z_WP_Incremental_Backup_Client {

	/**
	 * cURL handle
	 */
	private $ch;

	/**
	 * Second handle for downloading files
	 */
	// private $ch_download;

	/**
	 * Parsed json response
	 */
	private $parsed_response;

	/**
	 * Config data
	 */
	private $config;

	private $login_url;

	private $num_archives;

	/**
	 * Latest ZIP file name
	 */
	private $zip_filename;

	private $downloaded_files = [];

	/**
	 * Constructor: read ini file and setup cURL
	 */
	public function __construct() {
		$this->cookie = tempnam ("/tmp", "CURLCOOKIE");
		$this->read_config();
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
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
		curl_setopt($ch, CURLOPT_TIMEOUT, 6000);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		return $ch;
	}


	/**
	 * Run process
	 */
	public function run() {
		foreach ($this->config as $site => $config) {
			$this->ch = $this->setup_curl();
			if (! $this->ch) { die ("cURL init failed for 1st handle!!"); }
			$this->setup_curl_for_html();
			// $this->ch_download = $this->setup_curl();
			// if (! $this->ch_download) { die ("cURL init failed for 1st handle!!"); }
			printf ("\n\n *******   Begin process for site: %25s   ********\n", $site);

			if (substr($config['url'], -1) !== '/') {
				$config['url'] .= '/';
			}
			// $this->get_login($config);
			// foreach (['json' => $this->ch, 'binary' => $this->ch_download] as $type => 	$curl_handle) {
			// 	
				
			// }
			$this->login_to_wordpress($this->ch, $config);

			$this->setup_curl_for_json();
			// die('ok login');
			$this->post_generate_backup($config, $site);
			// $this->get_fetch_backup_and_concat($config, $site);
			curl_close($this->ch);
		}
	}

	/**
	 * Basic log function
	 */
	private function log($label, $str) {
		echo "----- $label -----\n";
		echo substr($str, 0, WPIB_CLIENT_DEBUG_LEN) . "\n\n"; 
	}

	private function login_to_wordpress($ch, $config) {
		$this->get_login($ch, $config);
		$this->post_login($ch, $config);
	}

	/**
	 * GET WordPress login page
	 */
	private function get_login($ch, $config) {
		var_dump($config);
		$this->login_url = $config['url'] . (array_key_exists('login_url', $config) ? $config['login_url'] : 'wp-login.php');
		printf("* GET login page [url = %s]\n", $this->login_url);
		curl_setopt ($ch, CURLOPT_URL,  $this->login_url);
		curl_exec ($ch);
		// $response = $this->send_request();
		if (! $this->response) die("[get_login] cURL error: " . curl_error($ch) . "\n");
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('GET login page', $this->response);
	}

	/**
	 * POST request to sign in to WordPress
	 */
	private function post_login($ch, $config) {
		$postdata = "log=". $config['username'] ."&pwd=". urlencode($config['password']) ."&wp-submit=Se+connecter&redirect_to=". $config['url'] ."wp-admin/&testcookie=1";
		printf("\n\n * POST login to %\n", $this->login_url);

		// $html_response = $this->send_request();

		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($ch, CURLOPT_POST, 1);
		curl_exec ($ch);
		// $response = $this->send_request();
		$lines = explode("\n", $this->response);
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
		if (! $this->response) die("[post_login] cURL error: " . curl_error($ch) . "\n");
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('POST login credentials', $this->response);
	}

	/**
	 * GET WordPress admin page
	 */
	private function get_admin($config) {
		curl_setopt ($this->ch, CURLOPT_POST, 0);
		curl_setopt ($this->ch, CURLOPT_POSTFIELDS, "");
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . 'wp-admin/');
		// $response = curl_exec ($this->ch);
		if (! $response) die("[get_login] cURL error: " . curl_error($this->ch) . "\n");
		if (WPIB_CLIENT_DEBUG_MODE) $this->log('GET login page', $response);
	}

	private function check_request_response($data) {

		$this->response = $data;
		// Send request and die on cURL error
		// $response = curl_exec($this->ch);
		$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

		// Die on HTTP error
		if ($http_code !== 200) {
			die(" !!! HTTP error (code = $http_code): $data\n");
		}
		// Die on cURL error
		if (empty($this->response)) {
			die(" !!! [post_generate_backup] cURL error: " . curl_error($this->ch) . "\n");
		}
		
		// return $this->response;
	}

	// private function curl_parse_html_response() {
	// 	$html_response = $this->send_request();
	// }	
	private function curl_get_html_response($ch, $data) {
		
		$this->check_request_response($data);
		// die($data);
	}

	private function curl_parse_json_response($ch, $data) {
		
		// $json_response = $this->send_request();
		$this->check_request_response($data);
		printf("\ncurl_parse_json_response: %s\n",substr($this->response, 0, 30));


		// Parse JSON response
		$this->parsed_response = json_decode($this->response);

		// Die on empty parsed response
		if (empty($this->parsed_response)) {
			echo " !!! [post_generate_backup] JSON parse error. Received payload:\n";
			die('[>' . $this->response . "<]\n");
		}

		// return $parsed_response;
	}

	private function setup_curl_for_html() {
		// curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, 0);
		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, [$this, 'curl_get_html_response']);
	}

	private function setup_curl_for_json() {
		// curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, 0);
		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, [$this, 'curl_parse_json_response']);
	}


	private function setup_curl_for_download() {
		// curl_setopt($ch, CURLOPT_POST, 0);
		// curl_setopt($ch, CURLOPT_VERBOSE, 0);
		// curl_setopt($ch, CURLOPT_POSTFIELDS,"");
		// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, [$this, 'curl_write_file']);
		// curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'progress');
	}


	function curl_write_file($cp, $data) {
	  global $global_fh;
	  $len = fwrite($global_fh, $data);
	  return $len;
	}


	private function loop_downloads($config, $site) {

		// Setup output dir first
		$dest_dir_prefix = $this->get_destination_dir($site);
		$dest_dir = $dest_dir_prefix . DIRECTORY_SEPARATOR . "wpib";
		$wp_expanded_dir = $dest_dir_prefix . DIRECTORY_SEPARATOR . "wordpress";

		if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);
		if (!is_dir($wp_expanded_dir)) mkdir($wp_expanded_dir);

		global $global_fh;
		$download_url = $config['url'] . "wp-admin/admin-ajax.php?action=wpib_download";
		$list_url = $download_url . "&list=1";
		$check_md5_url = $config['url'] . "wp-admin/admin-ajax.php?action=wpib_check_md5";

		curl_setopt ($this->ch, CURLOPT_URL, $list_url);
		curl_exec($this->ch);
		// $json_response = $this->get_parsed_json_response($this->ch);
		$json_response = $this->parsed_response;
		// var_dump($json_response);
		if (empty($json_response->files)) {
			echo "*** EMPTY files array\n";
			return;
		}
		$files = $json_response->files;
		// var_dump($files);die();
		
		while (! empty($files)) {
			$file = basename(array_shift($files));
			$destination = $dest_dir . DIRECTORY_SEPARATOR . $file;
			echo "Preparing download for $file => $destination\n";
			echo "Downloading $destination ... \n";
			$global_fh = fopen($destination, "w+");
			if (!$global_fh) die("could not open $destination\n");
			// $ch = $this->setup_curl();

			// $this->login_to_wordpress($this->ch_download, $config);
			curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . "wp-admin/admin-ajax.php?action=wpib_download&filename=$file");

			$this->setup_curl_for_download();
			curl_exec($this->ch);
			if(! file_exists($destination)) {
				printf("Error while downloading %s\n", $destination);
				exit(1);
			}
			$this->downloaded_files[] = $destination;

			$this->setup_curl_for_json();
			$md5 = md5_file($destination);
			curl_setopt ($this->ch, CURLOPT_URL, "$check_md5_url&file=" . urlencode($file) . "&md5=$md5");
			curl_exec($this->ch);

			if($this->parsed_response->md5_match !== true) {
				printf("md5 differ: srv=%s, cli=%s", $this->parsed_response->md5_server, $md5);
				exit(1);
			}

			fclose($global_fh);
		}

		

		// Open output file

		// $tmp_filename = 'output-' .time() . '.zip';
		// $this->zip_filename; //  $this->zip_filename

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
		$steps = ['list_deleted', 'list_md5', 'build_archives']; //, 'sql']; //, 'zip'];
		

		foreach($steps as $step) {
			$num_substeps = $step === 'build_archives' ? $this->num_archives : 1;
			$url = "$gen_url&step=$step";
			if(isset($config['php_path'])) $url .= '&php_path=' . urlencode($config['php_path']);
			if(isset($config['exclude'])) $url .= '&exclude=' . urlencode($config['exclude']);
			echo "Send request to: $url\n";
			curl_setopt ($this->ch, CURLOPT_URL, $url);
			printf(" * Start step %5s", $step);

			for ($idx_sub = 0 ; $idx_sub < $num_substeps ; $idx_sub++)  {
				$url_sub = $step === 'build_archives' ? "&arc_idx=$idx_sub" : "";
				curl_setopt ($this->ch, CURLOPT_URL, $url . $url_sub);

				curl_exec($this->ch);
				$parsed_response = $this->parsed_response;

				echo " ($parsed_response->step_of_total)  ==>  .";
				$num_calls = 1;


				// Parse response and die on error

				curl_setopt ($this->ch, CURLOPT_URL, "$check_url&step=$step" . $url_sub);

				while($parsed_response->done === false) {
					// echo "$step ==> check ($check_url&step=$step)\n";
					echo ".";
					$num_calls += 1;

					if($step === 'build_archives') {
						$this->loop_downloads($config, $site);
						
					}
					curl_setopt ($this->ch, CURLOPT_URL, "$check_url&step=$step");
					curl_exec ($this->ch);
					$parsed_response = $this->parsed_response; //json_decode($json_response);
				}
				$padding = 31 - $num_calls;
				printf("%{$padding}s", "OK *\n");

				if($step === 'list_md5') {
					$this->num_archives = $parsed_response->num_archives;
					// die("num arc:" . $this->num_archives);
				}

				if($step === 'build_archives') {
					$this->loop_downloads($config, $site);
				}

			}

			// if ($parsed_response->success === false) {
			// 	die("[post_generate_backup] error:\n * type: {$parsed_response->error_type}\n * details: {$parsed_response->error_details}\n");
			// }
			

			// if (WPIB_CLIENT_DEBUG_MODE) $this->log('POST generate backup', $this->zip_filename);

		}
		// $this->zip_filename = basename($parsed_response->files[0]);
		printf(" * Process done for %25s! *\n", $site);
		// printf(" * Output ZIP filename: %38s *\n", $this->zip_filename);
		
	}

	private function get_destination_dir($site) {
		return BACKUP_ROOT . DIRECTORY_SEPARATOR . $site;
	}

	/**
	 * GET request to fetch backup
	 */
	private function get_fetch_backup_and_concat($config, $site) {
		global $global_fh;
		// Setup output dir first
		$dest_dir_prefix = $this->get_destination_dir($site);
		$dest_dir = $dest_dir_prefix . DIRECTORY_SEPARATOR . "wpib";
		$wp_expanded_dir = $dest_dir_prefix . DIRECTORY_SEPARATOR . "wordpress";

		if (!is_dir($dest_dir)) mkdir($dest_dir, 0777, true);
		if (!is_dir($wp_expanded_dir)) mkdir($wp_expanded_dir);

		// Open output file
		set_time_limit(0); //prevent timeout

		// $ch2 = curl_init();
		$tmp_filename = 'output-' .time() . '.zip';
		$destination = $dest_dir . DIRECTORY_SEPARATOR . $this->zip_filename; //  $this->zip_filename
		$global_fh = fopen($destination, "w+");
		if (!$global_fh) die("could not open $destination\n");
		curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . "wp-admin/admin-ajax.php?action=wpib_download");
		// curl_setopt($this->ch, CURLOPT_URL, "http://www.lesvaguabondes.fr/wp-content/uploads/wp-incremental-backup-output/o8mqb5/cie-les-vaguabondes_20160614-225241.zip");
		// curl_setopt($this->ch, CURLOPT_FILE, $global_fh); //auto write to file

		curl_setopt($this->ch, CURLOPT_TIMEOUT, 5040);
		curl_setopt($this->ch, CURLOPT_POST, 0);
		curl_setopt($this->ch, CURLOPT_VERBOSE, 0);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS,"");
		curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, 1);
		// curl_setopt ($this->ch, CURLOPT_HTTPHEADER, array('Expect:'));
		// curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 1);
		// curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE); 
		// curl_setopt($this->ch, CURLOPT_SSLVERSION, 3); 
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
		// curl_setopt($this->ch, CURLOPT_TCP_KEEPALIVE, 1);
		// curl_setopt($this->ch, CURLOPT_TCP_KEEPIDLE, 2);
		curl_setopt($this->ch, CURLOPT_HEADER, 0);
		curl_setopt ($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_WRITEFUNCTION, 'curl_write_file');
		// curl_setopt($this->ch, CURLOPT_PROGRESSFUNCTION, 'progress');


		curl_exec($this->ch);


		// curl_close($this->ch);
		fclose($global_fh);

		// $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
		// $header = substr($response, 0, $header_size);
		// var_dump($header);
		// die();

		// echo "open $destination\n";
		// $fh = fopen($destination, "w+");
		// if($fh === false) die("could not output file $destination\n");

		// // Setup cURL
		// curl_setopt ($this->ch, CURLOPT_URL, $config['url'] . "wp-admin/admin-ajax.php?action=wpib_download");
		// curl_setopt ($this->ch, CURLOPT_POST, 0);
		
		// curl_setopt ($this->ch, CURLOPT_FILE, $fh);
		// curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, 1);
		
		// // curl_setopt($this->ch, CURLOPT_NOPROGRESS, false); // needed to make progress function work
		// // curl_setopt($this->ch, CURLOPT_VERBOSE, 1);




		// // ob_start();

		// // echo "<pre>";
		// // echo "Loading ...";

		// // ob_flush();
		// // flush();
		// curl_exec ($this->ch);
		// curl_close ($this->ch);
		// fclose($fh);

		if(filesize($destination)<= 1) {
			echo "An error occurred: could not download $destination\n";
			var_dump(curl_error($this->ch));
			die();
		}
		// return;
		// ob_flush();
		// flush();
		// ob_end_clean();

		// Then, after your curl_exec call:

		// if (! $data) die("[get_fetch_backup_and_concat] cURL error: " . curl_error($this->ch) . "\n");



		$info = pathinfo($destination);
		$filename_prefix =  basename($destination,'.'.$info['extension']);
		$tar = "$filename_prefix.tar";
		$tar_fullpath = $dest_dir . DIRECTORY_SEPARATOR . $tar;

		$cmd1 = "cd $dest_dir; unzip " . $this->zip_filename;
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