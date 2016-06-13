<?php
require_once 'class-t1z-wpib-exception.php';
require_once 'trait-t1z-walker-common.php';
define('FILES_TO_DELETE', '__deleted_files__.txt');
class T1z_Incremental_Backup_Deleted_Walker {
    use T1z_Walker_Common;

    private $input_dir;
    private $output_dir;
    private $archive_list;
    private $delete_list;
    public function __construct($archive_list, $delete_list, $input_dir) {
        $this->archive_list = $archive_list;
        $this->delete_list = $delete_list;
        $this->output_dir = dirname($archive_list);
        $this->output_list_csv = $this->output_dir . '/list.csv';
        $this->input_dir = $input_dir;
        $this->read();
    }

    public function run() {
        $this->prepare_and_write_delete_list();
    }

    /**
     * Recurse wp installation
     */
    public function prepare_and_write_delete_list() {
        // echo "prepare start: " . $this->current_time_diff() . "<br>";
        $found_in_dirs = [];
        $files_to_delete = [];
// $fh = fopen(__DIR__.'/deleted.txt', 'w');
        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->input_dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
// fwrite($fh, print_r($objects, true));
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
            if(is_dir($object->getPathname())) {
                continue;
            }

            if(!$this->is_regular_file($object)) continue;
            $found_in_dirs[] = $name;
        }
// fwrite($fh, "\n" . implode("\n", $found_in_dirs));
        // Iterate existing files from previous backup's list
        foreach($this->files as $name => $md5) {
// fwrite($fh, $name . " ". $md5 . " " . ( array_search($name, $found_in_dirs ) ? 'found': (! is_dir($name) ? 'not found' : 'dir') ). "\n");
            if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
                // fwrite($fh, $name . "\n");
                $files_to_delete[] = $name;
            }
        }
        if (!empty($files_to_delete)) $this->write_delete_list($files_to_delete);
    }


    /**
     * Write files to delete list
     */
    private function write_delete_list($files_to_delete) {
        $fh = fopen($this->delete_list, 'w');
        $this->write_file_list($fh, $files_to_delete);
        fclose($fh);
    }
    private function append_archive_list() {
        // $fh = fopen($this->archive_list, 'a+');
        // fwrite($fh, "\n" . $this->delete_list);
        // fclose($fh);
    }

}
