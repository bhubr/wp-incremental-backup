<?php

function regexize_path($path) {
	return '.*' . preg_quote($path, '/') . '.*';
}

define('FILES_TO_DELETE', '__deleted_files__.txt');

class Md5Walker {

	private $walk_dir;
	private $cnt;
	private $output_file;
	private $output_dir = __DIR__;
	private $first_run;
	private $files;

	/**
	 * Initialize walk_dir, count, csv file
	 */	
	public function __construct($walk_dir) {
		$this->walk_dir = $walk_dir;
		$this->cnt = 0;
		$this->output_file = __DIR__ . "/list.csv";
		$this->first_run = !file_exists($this->output_file);
		if ($this->first_run) {
			$this->files = [];
		}
		else {
			$this->read();
		}
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
		return $md5;
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
			
			$name = $line_read[0];
			$md5 = $line_read[1];
			$this->files[$name] = $md5;
		} while($line_read !== false);
		// echo "end of file\n";
	}

	private function get_md5($name) {
		return $this->files[$name];
	}

	private function write_files_to_delete($files_to_delete) {
		$dest = $this->walk_dir . DIRECTORY_SEPARATOR . FILES_TO_DELETE;
		$fh = fopen($dest, 'w');
		$num_to_delete = count($files_to_delete);
		for($i = 0 ; $i < $num_to_delete ; $i++) {
		 	$name = $files_to_delete[$i];
		 	$not_last = $i < $num_to_delete - 1;
			fwrite($fh, $name . ($not_last ? "\n" : ""));
		}
		return $num_to_delete > 0 ? $dest : "";
	}

	private function write_archive($files_to_archive) {
		$prefix_len = strlen($this->walk_dir);
		$last_char = $this->walk_dir[$prefix_len - 1];
		$prefix_len += ($last_char === '/') ? 0 : 1;
		echo "$prefix_len $last_char\n";
		foreach($files_to_archive as $file) {
			echo substr($file, $prefix_len);
		}
	}

	public function walk() {
		$found_in_dirs = [];
		$files_to_archive = [];
		$files_to_delete = [];
		$this->fh = fopen($this->output_file, "w");

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
			
			// Skip if this is . or ..
			if($this->is_special_dir($object)) {
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
				echo "new file: $name\n";
				$files_to_archive[] = $name;
				continue;
			}
			$old_md5 = $this->get_md5($name);
			if($md5 !== $old_md5) {
				echo "modified file: $name (old md5 = $old_md5, new md5 = $md5)\n";
				$files_to_archive[] = $name;
			}

		}

		// Iterate existing files from previous backup's list
		foreach($this->files as $name => $md5) {
			if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
				echo "deleted file: $name\n";
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

$args = !isset($argv) ? [ 'root' => $_GET['root'] ] :
	[ 'root' => $argv[1] ];

$walker = new Md5Walker($args['root']);

$walker->walk();
// $walker->read();