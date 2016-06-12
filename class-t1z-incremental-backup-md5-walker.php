<?php
require_once 'class-t1z-wpib-exception.php';
require_once 'trait-t1z-walker-common.php';

class T1z_Incremental_Backup_MD5_Walker {
    use T1z_Walker_Common;

    private $input_dir;
    private $output_dir;
    private $output_list_csv;
    private $archive_list;
    public function __construct($output_csv, $archive_list, $input_dir) {
        $this->output_list_csv = $output_csv;
        $this->archive_list = $archive_list;
        $this->input_dir = $input_dir;
        $this->output_dir = dirname($output_csv);
        $this->first_run = !file_exists($this->output_list_csv);
        if ($this->first_run) {
            $this->files = [];
        }
        else {
            $this->read();
        }
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
        $md5 = md5_file($name);
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
     * Recurse wp installation
     */
    public function prepare_files_archive() {
        // echo "prepare start: " . $this->current_time_diff() . "<br>";
        $found_in_dirs = [];
        $files_to_archive = [];
        $files_to_delete = [];
        $files_new = [];
        $files_modified = [];

        $this->fh = fopen($this->output_list_csv, "w");
        if($this->fh === false) {
            throw new T1z_WPIB_Exception("Could not open output CSV file in write mode: {$this->output_list_csv}", T1z_WPIB_Exception::FILES);
        }

        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->input_dir),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Iterate directory
        foreach($objects as $name => $object) {

            // // Skip deleted files list => delete it
            // if($object->getFilename() === FILES_TO_DELETE) {
            //     unlink($object->getPathname());
            //     continue;
            // }

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
                // echo "new file: $name<br>";
                $files_new[] = $name;
                $files_to_archive[] = $name;
                continue;
            }
            $old_md5 = $this->get_md5($name);
            if($md5 !== $old_md5) {
                // echo "<em>modified</em> file: $name (old md5 = $old_md5, new md5 = $md5)<br>";
                $files_modified[$name] = [$old_md5, $md5];
                $files_to_archive[] = $name;
            }

        }

        // Iterate existing files from previous backup's list
        // foreach($this->files as $name => $md5) {
        //     if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
        //         // echo "<em>deleted</em> file: $name<br>";
        //         $files_to_delete[] = $name;
        //     }
        // }
        // $deleted_list_file = $this->write_files_to_delete($files_to_delete);
        // if (!empty($deleted_list_file)) {
        //     $files_to_archive[] = $deleted_list_file;
        // }
        // echo "prepare end: " . $this->current_time_diff() . "<br>";


        // $this->write_tar_archive($files_to_archive);
        fclose($this->fh);

        $this->write_archive_list($files_to_archive);

        // Log what whas done
        // $this->log([
        //     'new'      => $files_new,
        //     'modified' => array_keys($files_modified),
        //     'deleted'  => $files_to_delete
        // ]);

        // return [
        //     'new'      => $files_new,
        //     'modified' => $files_modified,
        //     'deleted'  => $files_to_delete
        // ];
    }
    /**
     * Write archive file list
     */
    private function write_archive_list($files_to_archive) {
        // $list = $this->archive_list; //"{$this->output_fullpath_prefix}_tar_list.txt";
        // echo "write start: " . $this->current_time_diff() . "<br>";
        // $files_to_archive = array_keys($this->files);
        // if ($has_deleted) array_unshift($files_to_archive, $this->delete_list);
        $fh = fopen($this->archive_list, 'w');
        $this->write_file_list($fh, $files_to_archive);
        fclose($fh);
        // if (empty($files_to_archive) && !$has_deleted) {
        //     return;
        // }
        
        // $fh = fopen($list, 'w');
        // $files = array_map(function($file) {
        //     return $this->filename_from_root($file);
        // }, $files_to_archive);
        // $file_list = implode("\n", $files);
        // file_put_contents($list, $file_list);
        // echo "write end: " . $this->current_time_diff() . "<br>";
    }
}
