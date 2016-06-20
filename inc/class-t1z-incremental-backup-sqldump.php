<?php
use Ifsnop\Mysqldump as IMysqldump;
require PLUGIN_DIR . '/vendor/autoload.php';
require PLUGIN_DIR . '/class-t1z-wpib-exception.php';

require_once 'constants.php';
require_once 'trait-t1z-walker-common.php';
require_once 'class-t1z-incremental-backup-task-common.php';



set_time_limit(0);

class T1z_Incremental_Backup_SQLDump extends T1z_Incremental_Backup_Task {

    private $dump_prefix;
    private $dump_file;

    public function __construct($input_dir, $output_dir, $datetime, $extra_opts) {
        parent::__construct(TASK_DUMP_SQL, $input_dir, $output_dir, $datetime, T1z_Incremental_Backup_Task::PROGRESS_INTERNAL);
        // $this->add_infile($this->arc_list);
        $this->dump_prefix = $extra_opts[0];
        $this->dump_file = $this->output_dir . DIRECTORY_SEPARATOR . $this->dump_prefix . '_' . $this->datetime . '.sql'; // $this->get_dump();
        $this->host = $extra_opts[1];
        $this->db = $extra_opts[2];
        $this->user = $extra_opts[3];
        $this->pass = $extra_opts[4];
        $this->echo_status(true);
        // var_dump($extra_opts);die();

    }



    // public function init_archive() {
    //     $this->archive_size = 0;
    //     $this->archives[] = [];
    //     $this->archive_sizes[] = [];
    //     $this->archive_index = count($this->archives) - 1;
    // }

    // public function add_file($file) {
    //     $fullpath = $this->path_from_in($file);
    //     $this->archives[$this->archive_index][] = $file;
    //     $this->archive_size += filesize($fullpath);
    //     $this->archive_sizes[$this->archive_index] = $this->archive_size;
    //     return $this->archive_size >= TAR_MAX_SIZE;
    // }
    // private function get_dump() {
    //     return $this->dump_prefix . '_' . $this->datetime . '.sql';
    // }

    public function run() {

        // $this->prepare_file_lists();
        // $this->build_archives();
        try {
        	$dump_file = $this->dump_file;
        	// var_dump($this);die($dump_file);
            $dump = new IMysqldump\Mysqldump("mysql:host={$this->host};dbname={$this->db}", $this->user, $this->pass);
            $dump->start($dump_file);
            $basename = basename($this->dump_file);
            $bzip2_file = $basename . '.bz2';
            $bzip2 = "cd {$this->output_dir} ; bzip2 $basename";
            exec($bzip2, $out, $ret);
            $this->add_outfile($bzip2_file);
            // var_dump($out);
        } catch (\Exception $e) {
            die("[MySQLdump/PHP] " . $e->getMessage() . "\n");
        }
        $this->echo_end();
    }

}