<?php
require_once 'constants.php';

class T1z_Incremental_Backup_Task {

    /**
     * Progress check type: internal (class can monitor its own process's progress)
     */
    const PROGRESS_INTERNAL = 0;

    /**
     * Progress check type: internal (class's process's progress is monitored by process invoker)
     */
    const PROGRESS_EXTERNAL = 1;

    /**
     * Non-applicable progress check type
     */
    const PROGRESS_NA = 2;

    /**
     * input file/md5 list
     */
    const MD5 = 'MD5';

    /**
     * output delete files list
     */
    const DEL = 'DEL';


    /**
     * list of files to archive
     */
    const ARC = 'ARC';

    /**
     * Task name
     */
    private $name;

    /**
     * Input dir
     */
    protected $input_dir;

    /**
     * Input files
     */
    private $input_files = [];

    /**
     * Output dir
     */
    protected $output_dir;

    /**
     * Output files
     */
    private $output_files = [];

    /**
     * Datetime stamp for current run
     */
    protected $datetime;

    /**
     * Progress total (can be number of files, etc.)
     */
    private $progress_total;

    /**
     * Current progress
     */
    protected $progress_current = 0;

    /**
     * Last progress
     */
    protected $last_percent = 0;
    protected $percent = 0;

    /**
     * Tasks and associated classes
     */
    protected static $tasks = [
        TASK_LIST_DELETED => 'T1z_Incremental_Backup_Deleted_Walker',
        TASK_BUILD_MD5_LIST => 'T1z_Incremental_Backup_MD5_Walker',
        TASK_BUILD_ARCHIVES => 'T1z_Incremental_Backup_Archiver'
    ];

    protected $files = [];

    /**
     * Progress check type
     */
    private $progress_type;

    public function __construct($name, $input_dir, $output_dir, $datetime, $progress_type) {
        $this->name = $name;
        $this->input_dir = $input_dir;
        $this->output_dir = $output_dir;
        $this->datetime = $datetime;
        $this->progress_type = $progress_type;
        $this->del_list = $this->input_dir . DIRECTORY_SEPARATOR . FILE_LIST_TO_DELETE;
        $this->md5_csv = $this->output_dir . DIRECTORY_SEPARATOR . FILE_MD5_LIST;
        $this->arc_list = $this->output_dir . DIRECTORY_SEPARATOR . FILE_ARC_LIST;
        $this->echo_start();
    }

    public static function get_task_class($task_name) {
        if (! isset(self::$tasks[$task_name])) {
            $message = "Could not find task: [$task_name] (valid tasks are: " .
                implode(', ', array_keys(self::$tasks)) . ")";
            throw new Exception($message);

        }
        return self::$tasks[$task_name];
    }

    protected function echo_start() {
        // ob_start();
        echo $this->name . ':' . $this->datetime;
    }

    protected function echo_status($success) {
        echo ':' . ($success ? "SETUP_OK" : "SETUP_FAIL") . "\n"; // . ':' . $this->input_dir . ':' . $this->output_dir;
        echo "% 0\n";
        flush();
    }

    protected function set_progress_total($progress_total) {
        $this->progress_total = $progress_total;
        // die("pt: $progress_total\n");
    }

    protected function increment_progress() {
        $this->progress_current++;
        // if (($this->progress_current * 10) % $this->progress_total === 0)
        // echo (($this->progress_current * 10) % $this->progress_total) . "\n";
        $this->last_percent = $this->percent;
        $this->percent = (int)($this->progress_current * 100 / $this->progress_total);
        
         // echo '#'; //sprintf("%d\n", $this->percent);
        // $modulo =(this->progress_current * 100) % $this->progress_total
        if ($this->percent > $this->last_percent) echo "% " . $this->percent . "\n";
        // if (! $modulo) printf("%d/%d %d\n", $this->progress_current, $this->progress_total, $modulo);
        
        //     echo $this->progress_total . "\n";
    }

    protected function echo_end() {
        // echo "\n";
        foreach($this->output_files as $f) {
            echo $f . "\n";
        }
        echo "done";

        // $buffer = ob_get_contents();
        // ob_end_clean();
        // var_dump($this->parse_output_log($buffer));
    }

    public function parse_output_file($file) {
        $buffer = file_get_contents($file);
        return $this->parse_output_log($buffer);
    }

    protected function parse_output_log($buffer) {
        // var_dump($buffer);
        $lines = explode("\n", $buffer);
        $status_bits = explode(':', array_shift($lines));
        $last_line = $lines[count($lines) - 1];
        $parsed = [
            'task_name' => $status_bits[0],
            'datetime'  => $status_bits[1],
            'success'   => $last_line === 'done',
            'setup_ok'  => $status_bits[2] === 'SETUP_OK'
        ];
        $num_pc_lines = 0;

        while(1) {
            if (! preg_match('/% [0-9]+/', $lines[$num_pc_lines])) break;
            $num_pc_lines++;
            // $percent = array_shift($lines);
        }
        $percents = array_splice($lines, 0, $num_pc_lines, []);
        $parsed['percent'] = (int)substr(array_pop($percents), 2);
        if ($last_line === 'done') {
            array_pop($lines);
            $parsed['output_files'] = $lines;   
        }
        // var_dump($percents);
        // var_dump($lines);
        // $parsed['percent'] = $percent;
        return $parsed;
    }

    protected function get_name() {
        return $this->name;
    }

    protected function get_input_dir() {
        return $this->input_dir;
    }

    public function get_output_dir() {
        return $this->output_dir;
    }

    protected function get_datetime() {
        return $this->datetime;
    }

    protected function path_from_in($file) {
        return $this->input_dir . DIRECTORY_SEPARATOR . $file;
    }

    protected function path_from_out($file) {
        return $this->output_dir . DIRECTORY_SEPARATOR . $file;
    }

    // protected function add_infile($id, $filename) {
    //     if (array_key_exists($id, $this->input_files)) {
    //         throw new Exception("Could not find add file key: [$id] (key exits)");
    //     }
    //     $this->input_files[$id] = $filename;
    // }

    // protected function get_infile($id) {
    //     if (!array_key_exists($id, $this->input_files)) {
    //         $message = "Could not find input file key: [$id] (valid keys are: " .
    //             implode(', ', array_keys($this->input_files)) . ")";
    //         throw new Exception($message);
    //     }
    //     return $this->input_dir . DIRECTORY_SEPARATOR . $this->input_files[$id];
    // }

    protected function add_infile($filename) {
        if(! file_exists($filename)) {
            throw new Exception("Could not find input file: $filename");
        }
    }

    // protected function add_outfile($id, $filename) {
    protected function add_outfile($filename) {
    //     if (array_key_exists($id, $this->output_files)) {
    //         throw new Exception("Could not find add file key: [$id] (key exits)");
    //     }
        $this->output_files[] = $filename;
    }

    // protected function get_outfile($id) {
    //     if (!array_key_exists($id, $this->output_files)) {
    //         $message = "Could not find output file key: [$id] (valid keys are: " .
    //             implode(', ', array_keys($this->output_files)) . ")";
    //         throw new Exception($message);
    //     }
    //     return $this->output_dir . DIRECTORY_SEPARATOR . $this->output_files[$id];
    // }

    // abstract protected function get_input_file($file_id);


}