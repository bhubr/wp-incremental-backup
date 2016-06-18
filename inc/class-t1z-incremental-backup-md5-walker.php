<?php
require_once 'constants.php';
// require_once 'class-t1z-wpib-exception.php';
require_once 'trait-t1z-walker-common.php';
require_once 'class-t1z-incremental-backup-task-common.php';

class T1z_Incremental_Backup_MD5_Walker extends T1z_Incremental_Backup_Task {
    use T1z_Walker_Common;

    // private $input_dir;
    // private $output_dir;
    // private $output_list_csv;
    // private $archive_list;
    private $excluded = [];
    private $files_md5 = [];

    private $archives = [];
    private $archive_sizes = [];
    private $archive_index;
    private $archive_size;

    public function __construct($input_dir, $output_dir, $datetime, $extra_opts) {
        parent::__construct(TASK_BUILD_MD5_LIST, $input_dir, $output_dir, $datetime, T1z_Incremental_Backup_Task::PROGRESS_INTERNAL);
        // $this->add_outfile(static::MD5, FILE_MD5_LIST);
        $this->add_outfile($this->md5_csv);
        $this->add_outfile($this->arc_list);
        if (!empty($extra_opts)) {
            $this->excluded = explode(',', $extra_opts[0]);
        }

        $this->first_run = !file_exists($this->md5_csv);
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
    public function run() {
        // echo "prepare start: " . $this->current_time_diff() . "<br>";
        $found_in_dirs = [];
        $files_to_archive = [];
        $files_to_delete = [];
        $files_new = [];
        $files_modified = [];

        // $this->fh = fopen($this->output_list_csv, "w");
        $this->fh = fopen($this->md5_csv, "w");
        if($this->fh === false) {
            throw new T1z_WPIB_Exception("Could not open output CSV file in write mode: " . $this->md5_csv, T1z_WPIB_Exception::FILES);
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
        fclose($this->fh);

        $this->write_archive_list($files_to_archive);
        $this->prepare_file_lists();

        $this->echo_end();
    }

    /**
     * Write archive file list
     */
    private function write_archive_list($files_to_archive) {
        $this->write_file_list($this->arc_list, $files_to_archive);
    }

    public function init_archive() {
        $this->archive_size = 0;
        $this->archives[] = [];
        $this->archive_sizes[] = [];
        $this->archive_index = count($this->archives) - 1;
        // echo "init arc {$this->archive_index}\n";
    }

    public function add_archive_file($file) {
        // echo "{$this->archive_index} add file $file\n";
        $fullpath = $this->path_from_in($file);
        $this->archives[$this->archive_index][] = $file;
        $this->archive_size += filesize($fullpath);
        $this->archive_sizes[$this->archive_index] = $this->archive_size;
        return $this->archive_size >= TAR_MAX_SIZE;
    }

    private function get_partial_arclist($index) {
        return $this->output_dir . DIRECTORY_SEPARATOR . 'archive_' . $index . '.txt';
    }

    private function prepare_file_lists() {
        $files_raw = file($this->arc_list);
        // var_dump($files_raw);
        $files = array_map(function($file) {
            return trim($file);
        }, $files_raw);
        $num_files = count($files);
        // echo "prepare file lists...$num_files total files\n";
        $this->init_archive();
        for ($f = 0 ; $f < $num_files ; $f++) {
            $is_full = $this->add_archive_file($files[$f]);
            if($is_full) {
                // printf("Archive %s is full ...\n", $this->archive_index, $this->archive_sizes[$this->archive_index]);
                $arclist_path = $this->get_partial_arclist($this->archive_index);
                $this->write_file_list($arclist_path, $this->archives[$this->archive_index], false);
                // printf("wrote %s with %d files\n", $arclist_path, count($this->archives[$f]));
                $this->init_archive();
                // echo " new archive ...\n";
            }
        }
        $arclist_path = $this->get_partial_arclist($this->archive_index);
        $this->write_file_list($arclist_path, $this->archives[$this->archive_index], false);
        // $this->set_progress_total(count($this->archives));
        // $this->echo_status(true);
    }
}
