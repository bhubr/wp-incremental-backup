<?php
require 'constants.php';
require_once 'class-t1z-wpib-exception.php';
require_once 'trait-t1z-walker-common.php';
require_once 'class-t1z-incremental-backup-task-common.php';

class T1z_Incremental_Backup_MD5_Walker extends T1z_Incremental_Backup_Task {
    use T1z_Walker_Common;

    // private $input_dir;
    // private $output_dir;
    // private $output_list_csv;
    // private $archive_list;
    private $excluded;
    private $files_md5 = [];

    public function __construct($input_dir, $output_dir, $datetime, $excluded) {
        parent::__construct(TASK_BUILD_MD5_LIST, $input_dir, $output_dir, $datetime, T1z_Incremental_Backup_Task::PROGRESS_INTERNAL);
        $this->add_outfile(static::MD5, FILE_MD5_LIST);
        $this->add_outfile(static::ARC, FILE_ARC_LIST);
        $this->excluded = $excluded;
        $this->first_run = !file_exists($this->get_outfile(static::MD5));
        try {
            $this->set_progress_total($this->count_files());
            if ($this->first_run) $this->files_md5 = [];
            else $this->read_file_md5_list();
        } catch(Exception $e) {
            $this->echo_status(false);
            return;
        }

        $this->echo_status(true);
        // die();

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

        // $this->fh = fopen($this->output_list_csv, "w");
        $this->fh = fopen($this->get_outfile(static::MD5), "w");
        if($this->fh === false) {
            throw new T1z_WPIB_Exception("Could not open output CSV file in write mode: " . $this->get_outfile(static::MD5), T1z_WPIB_Exception::FILES);
        }

        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->get_input_dir()),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Iterate directory
        foreach($objects as $name => $object) {

            // Skip if this is . or .. or output dir 
            if($this->is_special_dir($object) || $this->is_output_dir($object)) {
                continue;
            }
            else {
                $this->increment_progress();
            }

            // Skip if excluded pattern
            if($this->is_excluded($object)) {
                continue;
            }

            // Add dir
            if(is_dir($object->getPathname())) {
                $this->add_dir($name);
                continue;
            }
            $found_in_dirs[] = $name;

            if(!$this->is_regular_file($object)) continue;
            $md5 = $this->add_file($name);    
            
            if($this->first_run || !array_key_exists($name, $this->files_md5)) {
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
        $fh = fopen($this->get_outfile(static::ARC), 'w');
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
