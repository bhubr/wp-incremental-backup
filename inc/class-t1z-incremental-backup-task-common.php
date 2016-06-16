<?php

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
     * input file/md5 list
     */
    const IN_MD5 = 'IN_MD5';

    /**
     * output delete files list
     */
    const OUT_DEL = 'OUT_DEL';


    /**
     * Task name
     */
    private $name;

    /**
     * Input dir
     */
    private $input_dir;

    /**
     * Input files
     */
    private $input_files = [];

    /**
     * Output dir
     */
    private $output_dir;

    /**
     * Output files
     */
    private $output_files = [];

    /**
     * Datetime stamp for current run
     */
    private $datetime;

    /**
     * Progress total (can be number of files, etc.)
     */
    private $progress_total;

    /**
     * Current progress
     */
    protected $progress_current = 0;

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
        $this->echo_start();
    }

    protected function echo_start() {
        echo $this->name . ':' . $this->datetime;
    }

    protected function echo_status($success) {
         echo ':' . ($success ? "SETUP_OK" : "SETUP_OK") . "\n"; // . ':' . $this->input_dir . ':' . $this->output_dir;
    }

    protected function set_progress_total($progress_total) {
        $this->progress_total = $progress_total;
        // die("pt: $progress_total\n");
    }

    protected function increment_progress() {
        $this->progress_current++;
        // if (($this->progress_current * 10) % $this->progress_total === 0)
        // echo (($this->progress_current * 10) % $this->progress_total) . "\n";
        $modulo = $this->progress_current % ($this->progress_total  / 10);
        // if (! $modulo) printf("%d/%d %d\n", $this->progress_current, $this->progress_total, $modulo);
        if(! $modulo) echo '#';
        //     echo $this->progress_total . "\n";
    }

    protected function echo_end() {
        echo "\n";
        foreach($this->output_files as $k => $f) {
            echo $this->get_outfile($k) . "\n";
        }
        echo "done\n";
    }

    protected function get_name() {
        return $this->name;
    }

    protected function get_input_dir() {
        return $this->input_dir;
    }

    protected function get_output_dir() {
        return $this->output_dir;
    }

    protected function get_datetime() {
        return $this->datetime;
    }

    protected function add_infile($id, $filename) {
        if (array_key_exists($id, $this->input_files)) {
            throw new Exception("Could not find add file key: [$id] (key exits)");
        }
        $this->input_files[$id] = $filename;
    }

    protected function get_infile($id) {
        if (!array_key_exists($id, $this->input_files)) {
            $message = "Could not find input file key: [$id] (valid keys are: " .
                implode(', ', array_keys($this->input_files)) . ")";
            throw new Exception($message);
        }
        return $this->input_dir . DIRECTORY_SEPARATOR . $this->input_files[$id];
    }


    protected function add_outfile($id, $filename) {
        if (array_key_exists($id, $this->output_files)) {
            throw new Exception("Could not find add file key: [$id] (key exits)");
        }
        $this->output_files[$id] = $filename;
    }

    protected function get_outfile($id) {
        if (!array_key_exists($id, $this->output_files)) {
            $message = "Could not find output file key: [$id] (valid keys are: " .
                implode(', ', array_keys($this->output_files)) . ")";
            throw new Exception($message);
        }
        return $this->output_dir . DIRECTORY_SEPARATOR . $this->output_files[$id];
    }

    // abstract protected function get_input_file($file_id);


}