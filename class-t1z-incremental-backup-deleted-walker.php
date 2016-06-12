<?php
require 'class-t1z-wpib-exception.php';
define('FILES_TO_DELETE', '__deleted_files__.txt');
class T1z_Incremental_Backup_Deleted_Walker {

    private $input_dir;
    private $output_dir;
    private $output_list_txt;
    public function __construct($output_txt, $input_dir) {
        $this->output_list_txt = $output_txt;
        $this->output_dir = dirname($output_txt);
        $this->output_list_csv = $this->output_dir . '/list.csv';
        $this->input_dir = $input_dir;
        $this->read();
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
     * Check if file is a special dir: either . or ..
     */
    private function is_special_dir($object) {
        return $object->getFilename() === '.' || $object->getFilename() === '..';
    }

    /**
     * Check if file is the output dir
     */
    private function is_output_dir($object) {
        return dirname($object->getPathname()) === $this->output_dir;
    }

    /**
     * Check if file is a regular file
     */
    private function is_regular_file($object) {
        return !is_dir($object->getPathname());
    }

    /**
     * Get filename, stripped from root dir (wp installation base dir)
     */
    private function filename_from_root($filename) {
        $prefix_len = strlen($this->input_dir);
        $last_char = $this->input_dir[$prefix_len - 1];
        $prefix_len += ($last_char === '/') ? 0 : 1;
        return substr($filename, $prefix_len);
    }

    /**
     * Recurse wp installation
     */
    public function prepare_files_archive() {
        // echo "prepare start: " . $this->current_time_diff() . "<br>";
        $found_in_dirs = [];
        $files_to_delete = [];

        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->input_dir),
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

            // Skip dir
            if(!$this->is_regular_file($object)) {
                continue;
            }
            $found_in_dirs[] = $name;
        }

        // Iterate existing files from previous backup's list
        foreach($this->files as $name => $md5) {
            if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
                $files_to_delete[] = $name;
            }
        }
        $deleted_list_file = $this->write_files_to_delete($files_to_delete);
    }

    /**
     * Write files to delete list
     */
    private function write_files_to_delete($files_to_delete) {
        $dest = $this->input_dir . DIRECTORY_SEPARATOR . FILES_TO_DELETE;
        $fh = fopen($dest, 'w');
        $num_to_delete = count($files_to_delete);
        for($i = 0 ; $i < $num_to_delete ; $i++) {
            $filename = $this->filename_from_root($files_to_delete[$i]);
            $not_last = $i < $num_to_delete - 1;
            fwrite($fh, $filename . ($not_last ? "\n" : ""));
        }
        fclose($fh);
        return $num_to_delete > 0 ? $dest : '';
    }

}
