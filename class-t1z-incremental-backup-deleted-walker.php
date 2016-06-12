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
        $has_deleted = $this->prepare_and_write_delete_list();
        if ($has_deleted) $this->append_archive_list();
    }

    /**
     * Recurse wp installation
     */
    public function prepare_and_write_delete_list() {
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
            if(is_dir($object->getPathname())) {
                continue;
            }

            if(!$this->is_regular_file($object)) continue;
            $found_in_dirs[] = $name;
        }

        // Iterate existing files from previous backup's list
        foreach($this->files as $name => $md5) {
            if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
                $files_to_delete[] = $name;
            }
        }
        return $this->write_delete_list($files_to_delete);
    }


    /**
     * Write files to delete list
     */
    private function write_delete_list($files_to_delete) {
        // $dest = $this->input_dir . DIRECTORY_SEPARATOR . FILES_TO_DELETE;
        $fh = fopen($this->delete_list, 'w');
        $this->write_file_list($fh, $files_to_delete);
        // $num_to_delete = count($files_to_delete);
        // for($i = 0 ; $i < $num_to_delete ; $i++) {
        //     $filename = $this->filename_from_root($files_to_delete[$i]);
        //     $not_last = $i < $num_to_delete - 1;
        //     fwrite($fh, $filename . ($not_last ? "\n" : ""));
        // }
        fclose($fh);
    }
    private function append_archive_list() {
        // $list = $this->archive_list; //"{$this->output_fullpath_prefix}_tar_list.txt";
        // echo "write start: " . $this->current_time_diff() . "<br>";
        // $files_to_archive = array_keys($this->files);
        // if ($has_deleted) array_unshift($files_to_archive, $this->delete_list);
        $fh = fopen($this->archive_list, 'a+');
        // $this->write_file_list($fh, $files_to_archive);
        fwrite($fh, "\n" . $this->delete_list);
        fclose($fh);
    }

}
