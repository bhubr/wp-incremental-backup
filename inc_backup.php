<?php

function regexize_path($path) {
	return '.*' . preg_quote($path, '/') . '.*';
}


class Md5Walker {

	/**
	 * Initialize walk_dir, count, csv file
	 */	
	public function __construct($walk_dir) {
		$this->walk_dir = $walk_dir;
		$this->cnt = 0;
		$this->output_file = __DIR__ . "/list.csv";
	}

	/**
	 * Check if file is a special dir
	 */
	private function is_special_dir($object) {
		return $object->getFilename() === '.' || $object->getFilename() === '..';
	}

	/**
	 * Check if file is a regular file
	 */
	private function is_regular_file($object) {
		// return $object->getFilename() !== '.' && $object->getFilename() !== '..';
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
	}

	/**
	 * Add a file to output
	 */
	private function add_dir($name) {
		fwrite($this->fh, $this->line($name));
	}

	public function read() {
		$this->fh = fopen($this->output_file, "r");
		do {
			$line_read = fgetcsv($this->fh);
			if (is_null($line_read)) {
				throw new Exception("invalid handle, aborting");
			}
			
			var_dump($line_read);
		} while($line_read !== false);
		echo "end of file\n";
	}

	public function walk() {
		$this->fh = fopen($this->output_file, "w");

		$objects = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->walk_dir),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach($objects as $name => $object){
			
			// Skip if this is . or ..
			if($this->is_special_dir($object)) {
				continue;
			}

			// Add dir
			if(!$this->is_regular_file($object)) {
				$this->add_dir($name);
				continue;
			}

			$this->add_file($name);
			// if($this->is_regular_file($object)) {
			// 	echo "$name\n";
			// 	$this->cnt++;
			// }
			// var_dump($object);
			// if(!is_file($name)) {
			// 	echo "skip $name\n";
			// 	continue;
			// }
			// $path = dirname($name);
			// $file_name = basename($name);
			// $file_size = filesize($name);
			// if ($file_size < $this->min_size) continue;


			// echo "$name,$md5_sum\n";
			// $query = sprintf("SELECT file_name,md5_sum FROM files WHERE file_name='%s' AND md5_sum = '%s'", $file_name, $md5_sum);
			// try {
			// 	// echo "1...";
			// 	// $stmt = $this->db->prepare("SELECT file_name,md5_sum FROM files WHERE file_name=? AND md5_sum = ?");
			// 	$res = $this->db->query($query);
			// 	// echo "2...";
			// 	// $stmt->bind_param('ss', $file_name, $md5_sum);
			// 	// , [$file_name, $md5_sum]);
			// 	// echo "3...";
			// 	// $stmt->execute();
			// 	// echo "4...";
			// 	// $res = $stmt->get_result();
			// 	// echo "5...";
			// } catch(Exception $e) {
			// 	echo "exception catched...";
			// 	die($e->getMessage());
			// }
			// // var_dump($res);
			// // var_dump($res->num_rows);
			// // echo "6...";
			// if (!$res || $res->num_rows === 0) {
			// 	$query = sprintf(
			// 		"INSERT INTO files(host, volume, path, file_name, size, md5_sum, created_at) VALUES('%s', '%s', '%s', '%s', '%s', '%s', NOW())",
			// 		$this->host, $this->volume, $path, $file_name, $file_size, $md5_sum
			// 	);
			// 	// var_dump($query);
			// 	$res = $this->db->query($query);
			// 	$this->cnt++;

			// 	// $stmt = $this->db->prepare("INSERT INTO files(host, volume, path, file_name, size, md5_sum, created_at) VALUES(?, ?, ?, ?, ?, ?, NOW())");
			// 	// $stmt->bind_param('ssssss', $this->host, $this->volume, $path, $file_name, $file_size, $md5_sum);
			// 	// $stmt->execute();
			// 	// $res = $stmt->get_result();
			// 	// var_dump($res->num_rows);
			// }
			
			// 	// printf("%s %s\n", dirname($name), basename($name));
		    
		}

		fclose($this->fh);
		echo "done: {$this->cnt}\n";

	}
}

$args = !isset($argv) ? [ 'root' => $_GET['root'] ] :
	[ 'root' => $argv[1] ];

$walker = new Md5Walker($args['root']);

$walker->walk();
$walker->read();