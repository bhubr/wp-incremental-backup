<?php
require_once 'constants.php';
// require_once 'class-t1z-wpib-exception.php';
require_once 'trait-t1z-walker-common.php';
require_once 'class-t1z-incremental-backup-task-common.php';

class T1z_Incremental_Backup_Archiver extends T1z_Incremental_Backup_Task {
    use T1z_Walker_Common;

    private $excluded;

    private $archives = [];
    private $archive_sizes = [];
    private $archive_index;
    private $archive_size;
    private $tarball_prefix;
    // private $archive_files;

    public function __construct($input_dir, $output_dir, $datetime, $extra_opts) {
        parent::__construct(TASK_BUILD_ARCHIVES, $input_dir, $output_dir, $datetime, T1z_Incremental_Backup_Task::PROGRESS_INTERNAL);
        // $this->add_outfile($this->, FILE_ARC_LIST);
        $this->add_infile($this->arc_list);
        $this->tarball_prefix = $extra_opts[0];
    }



    public function init_archive() {
        $this->archive_size = 0;
        $this->archives[] = [];
        $this->archive_sizes[] = [];
        $this->archive_index = count($this->archives) - 1;
    }

    public function add_file($file) {
        $fullpath = $this->path_from_in($file);
        $this->archives[$this->archive_index][] = $file;
        $this->archive_size += filesize($fullpath);
        $this->archive_sizes[$this->archive_index] = $this->archive_size;
        return $this->archive_size >= TAR_MAX_SIZE;
    }

    public function run() {
        $this->prepare_file_lists();
        $this->build_archives();
    }

    private function prepare_file_lists() {
        $files_raw = file($this->arc_list);
        $files = array_map(function($file) {
            return trim($file);
        }, $files_raw);
        // var_dump($files);
        $num_files = count($files);
        $this->init_archive();
        for ($f = 0 ; $f < $num_files ; $f++) {
            $is_full = $this->add_file($files[$f]);
            if($is_full) {
                // echo "new archive...\n";
                $this->init_archive();
            }
        }
        $this->set_progress_total(count($this->archives));
        $this->echo_status(true);
    }

    private function get_tarball($idx) {
        return $this->output_dir . DIRECTORY_SEPARATOR . $this->tarball_prefix . '_' . $this->datetime . '_' . $idx . '.tar.bz2';
    }

    private function get_partial_arclist($idx) {
        return $this->output_dir . DIRECTORY_SEPARATOR . 'archive_' . $idx . '.txt';
    }

    private function build_archives() {
        // var_dump($this->archives);
        foreach($this->archives as $idx => $files) {
            $tarball_path = $this->get_tarball($idx);
            $this->add_outfile($tarball_path);
            $arclist_path = $this->get_partial_arclist($idx);
            $this->write_file_list($arclist_path, $files, false);
            $archive_size = $this->archive_sizes[$idx];
            $cmd = "cd {$this->input_dir}; tar cj -T $arclist_path -f $tarball_path";
            exec($cmd, $tar_out, $ret);
            $this->increment_progress();
            // echo "$tarball_path $arclist_path\n";
        }
        $this->echo_end();
    }
}