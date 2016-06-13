<?php
use Ifsnop\Mysqldump as IMysqldump;
require 'vendor/autoload.php';
require 'common/constants.php';
require 'class-t1z-wpib-exception.php';
define('CLEANUP_AFTER_ZIP', false);

class T1z_Incremental_Backup {

    /**
     * Root dir for output
     */
    private $output_root_dir;

    /**
     * Walk (input) dir
     */
    private $input_dir;

    /**
     * Output set id
     */
    private $output_set_id;

    /**
     * Output file prefix
     */
    private $output_fullpath_prefix;

    /**
     * Output file prefix
     */
    private $output_file_prefix;

    /**
     * PHP timeout
     */
    private $php_timeout;

    /**
     * Start timestamp
     */
    private $start_timestamp;

    /**
     * Datetime (YYYYMMDD-His)
     */
    private $datetime;

    /**
     * MD5 per file list
     */
    private $md5_csv_file;

    /**
     * File list to feed tar
     */
    private $tar_file_src_list;

    /**
     * Deleted files list
     */
    private $deleted_files_list;

    /**
     * Output TAR file
     */
    private $tar_file;

    /**
     * Output SQL dump
     */
    private $sql_dump;

    /**
     * Output ZIP file
     */
    private $zip_file;

    /**
     * Process steps
     */
    private $steps = ['md5', 'lists', 'tar', 'sql', 'zip'];

    /**
     * Task running
     */
    private $running_task = "";

    public function __construct($input_dir, $output_root_dir, $output_set_id, $file_prefix) {
        $this->start_timestamp = time();
        $this->input_dir = $input_dir;
        $this->output_root_dir = $output_root_dir;
        $this->output_set_id = $output_set_id;
        $this->output_dir = $this->output_root_dir . DIRECTORY_SEPARATOR . $output_set_id;
        if (! is_dir($this->output_dir)) {
            $dir_created = mkdir($this->output_dir, 0777, true);
            if (! $dir_created) throw new Exception("Could not create output_dir: {$this->output_dir}");
        }
        $this->datetime = date("Ymd-His");
        $this->file_prefix = $file_prefix;
        $this->output_file_prefix = $this->file_prefix . '_' . $this->datetime;
        $this->output_fullpath_prefix = $this->output_dir . DIRECTORY_SEPARATOR . $this->output_file_prefix;
        $this->progress = "{$this->output_fullpath_prefix}.run";
    }

    private function setup_process_vars() {
        $this->output_file_prefix = $this->file_prefix . '_' . $this->datetime;
        $this->output_fullpath_prefix = $this->output_dir . DIRECTORY_SEPARATOR . $this->output_file_prefix;
        $this->progress = "{$this->output_fullpath_prefix}.run";
        $this->output_log = $this->output_fullpath_prefix . '_log.csv';
        $this->php_timeout = ini_get('max_execution_time');

        $this->md5_csv_file = $this->output_dir . "/list.csv";
        $this->tar_file_src_list = $this->output_dir . DIRECTORY_SEPARATOR . 'archive.txt';
        $this->deleted_files_list = $this->input_dir . '__deleted_files__.txt'; 
        $this->tar_file = $this->output_fullpath_prefix . '.tar';
        $this->sql_file = $this->output_fullpath_prefix . '.sql';
        $this->zip_file = $this->output_fullpath_prefix . '.zip';
        $this->setup_steps();
    }

    private function setup_steps() {
        $this->output_files = [
            'md5'   => [$this->md5_csv_file, $this->tar_file_src_list],
            'lists' => [$this->tar_file_src_list, $this->deleted_files_list],
            'tar'   => $this->tar_file,
            'sql'   => $this->sql_file,
            'zip'   => $this->zip_file
        ];
    }

    private function get_output_files($step) {
        $files = $this->output_files[$step];
        return is_array($files) ? $files : [$files];
    }

    public function get_params() {
        return [
            'input_dir'          => $this->input_dir,
            'output_root_dir'    => $this->output_root_dir,
            'output_dir'         => $this->output_dir,
            'output_set_id'      => $this->output_set_id,
            'output_file_prefix' => $this->output_fullpath_prefix
        ];
    }

    public function get_output_dir() {
        return is_dir($this->output_dir) ? $this->output_dir : false;
    }

    public function get_output_dir_content() {
        $output_dir_content = scandir($this->output_dir);
        $files = array_slice($output_dir_content, 2);
        return $files;
    }

    public function output_dir_content_cleanup() {
        $files = $this->get_output_dir_content();
        foreach ($files as $file) {
            unlink($this->output_dir . '/' . $file);
        }
    }

    public function get_zip_binary() {
        exec('which zip 2>&1', $which_zip_out, $ret);
        return count($which_zip_out) ? $which_zip_out[0] : "";
    }

    public function get_mysqldump_binary() {
        exec('which mysqldump 2>&1', $which_msd_out, $ret);
        return count($which_msd_out) ? $which_msd_out[0] : "";
    }

    private function get_cmd($step) {
        // $tarfile = "{$this->output_fullpath_prefix}.tar";
        // $zipfile = "{$this->output_fullpath_prefix}.zip";
        // $md5file = $this->md5_csv_file;
        switch($step) {
            case 'md5':
                return "php " . __DIR__ . "/md5_walk.php %s %s {$this->input_dir}";
            case 'lists':
                return "php " . __DIR__ . "/deleted_walk.php %s %s {$this->input_dir}";
            case 'tar':
                return "cd {$this->input_dir}; tar c -T {$this->tar_file_src_list} -f %s";
            case 'zip':
                $to_zip = $this->sql_file;
                if (file_exists($this->tar_file)) {
                    $to_zip .= " " . $this->tar_file;
                }
                $zip_bin = $this->get_zip_binary();
                if(! empty($zip_bin)) {
                    return "cd {$this->output_dir}; zip {$this->zip_file} $to_zip";    
                }
                // die("php " . __DIR__ . "/zip_fallback.php {$this->output_fullpath_prefix}");
                return "php " . __DIR__ . "/zip_fallback.php {$this->output_fullpath_prefix}";
            case 'sql':
                $mysqldump_bin = $this->get_mysqldump_binary();
                if(! empty($mysqldump_bin)) {
                    return sprintf("mysqldump -u%s -p\"%s\" %s > {$this->sql_file}", DB_USER, DB_PASSWORD, DB_NAME);
                }
                // die(sprintf("php " . __DIR__ . "/mysqldump_fallback.php {$this->output_fullpath_prefix} %s %s %s %s", DB_HOST, DB_NAME, DB_USER, DB_PASSWORD));
                return sprintf("php " . __DIR__ . "/mysqldump_fallback.php {$this->output_fullpath_prefix} %s %s %s %s", DB_HOST, DB_NAME, DB_USER, DB_PASSWORD);
            default:
                throw new Exception("Should never get here: " . __FUNCTION__);
        }
    }

    /**
     * Write TAR archive
     */
    private function write_tar_archive($files_to_archive) {
        $list = $this->output_dir . DIRECTORY_SEPARATOR . 'archive.txt';
        $this->write_archive_list($files_to_archive, $list);
        $tarfile = "{$this->output_fullpath_prefix}.tar";
        // $tar_lock = "{$tarfile}.lock";
        // $cmd = "touch $tar_lock; cd {$this->input_dir}; tar cv -T {$list} -f $tarfile; rm $tar_lock";
        $cmd = "cd {$this->input_dir}; %s cv -T {$list} -f %s";
        // $output = [];
        // $return_var = 0;
        // $outputfile = "{$this->output_fullpath_prefix}_out.txt";
        // $pidfile = "{$this->output_fullpath_prefix}_pid.txt";
        // exec(sprintf("%s > %s 2>&1 & echo $! >> %s", $cmd, $outputfile, $pidfile));
        $this->start_background_task($cmd, $tarfile, 'tar');

        // exec($cmd, $output, $return_var);
        // if ($return_var !== 0) {
        //     throw new T1z_WPIB_Exception("Error while creating output TAR file {$tarfile}", T1z_WPIB_Exception::FILES);    
        // }
    }

    private function current_time_diff() {
        return time() - $this->start_timestamp;
    }

    private function not_about_to_timeout() {
        // return $this->current_time_diff() < $this->php_timeout / 2;
        return $this->current_time_diff() < 4;
    }

    private function check_is_running() {
        try{
            $result = shell_exec(sprintf("ps %d", $this->pid));
            // var_dump($result);
            if( count(preg_split("/\n/", $result)) > 2){
                return true;
            }
        } catch(Exception $e){}

        return false;
    }

    private function check_running_task_loop() {
        while($this->not_about_to_timeout()) {
            sleep(1);
            if (! $this->check_is_running()) return true;
            // echo $this->current_time_diff() . ' ' . $this->running_task . ' ' . $this->pid. ' ' . $this->file_wip . filesize($this->file_wip) . '<br>';
        }
        return false;
    }

    private function write_progress($step, $pid, $output_dir_size) {
        $fh = fopen($this->progress, 'a+');
        $step_line = "\n" . $step . ':' . $this->pid . ':' . $output_dir_size;
        fwrite($fh, $step_line);
        fclose($fh);
        // file_put_contents($this->progress, $this->running_task);
        // $this->file_wip = $generated_file;
    }

    private function start_background_task($st_output_dir_sz, $cmd_format, $step, $generated_file1, $generated_file2 = "") {
        $func_args = func_get_args();
        $sprintf_args = array_slice($func_args, 3);
        $cmd = vsprintf($cmd_format, $sprintf_args);
        // $this->cmd_dbg = $cmd;
        $cmdoutfile = "{$this->output_fullpath_prefix}_{$step}_out.txt";
        $pidfile = "{$this->output_fullpath_prefix}_{$step}.pid";
        // die("$cmd<br>$cmdoutfile<br>$pidfile");
        $bg = new diversen\bgJob();
        $bg->execute($cmd, $cmdoutfile, $pidfile);
        usleep(10000);
        try {
            $pidfile_content = file_get_contents($pidfile);
            $this->pid = trim($pidfile_content);    
        } catch(Exception $e) {
            var_dump($e);
            var_dump($cmd);
        }
        
        $this->write_progress($step, $this->pid, $st_output_dir_sz);
        return $this->check_running_task_loop();
    }

    /**
     * Clean-up tar and sql
     */
    public function cleanup_tar_and_sql() {
        unlink("{$this->output_fullpath_prefix}.sql");
        if (file_exists("{$this->output_fullpath_prefix}.tar")) {
            unlink("{$this->output_fullpath_prefix}.tar");
        }
    }

    public function get_latest_zip_filename() {
        $files = glob("{$this->output_dir}/*.zip");
        $filename = array_pop($files);
        return basename($filename);
    }

    private function get_latest_run_filename() {
        $files = glob("{$this->output_dir}/*.run");
        if (count($files) === 0) {
            throw new T1z_WPIB_Exception("No run filename found", T1z_WPIB_Exception::FILES);
        }
        $filename = array_pop($files);
        return basename($filename);
    }

    private function get_latest_run() {
        $latest_run_filename = $this->get_latest_run_filename();
        return file($this->output_dir . DIRECTORY_SEPARATOR . $latest_run_filename);
    }

    public function get_process_datetime() {
        $latest_run = $this->get_latest_run();
        $this->datetime = trim(array_shift($latest_run));
        return $this->datetime;
    }

    /**
     * Prepare zip archive from files tar archive and sql dump
     */
    public function write_zip_archive() {
        $zip = new ZipArchive();
        $filename = "{$this->output_fullpath_prefix}.zip";
        if ($zip->open($filename, ZipArchive::CREATE) !== true) {
            throw new T1z_WPIB_Exception("Could not open ZIP archive $filename\n", T1z_WPIB_Exception::ZIP);
        }
        $zip->addFile("{$this->output_fullpath_prefix}.sql","{$this->output_file_prefix}.sql");
        if (file_exists("{$this->output_fullpath_prefix}.tar")) {
            $zip->addFile("{$this->output_fullpath_prefix}.tar","{$this->output_file_prefix}.tar");
        }
        $zip->close();
    }

    /**
     * Dump SQL
     */
    public function write_sql_dump($host, $db, $user, $pass) {
        try {
            $dump = new IMysqldump\Mysqldump("mysql:host={$host};dbname={$db}", $user, $pass);
            $dump->start("{$this->output_fullpath_prefix}.sql");
        } catch (\Exception $e) {
            throw new T1z_WPIB_Exception($e->getMessage(), T1z_WPIB_Exception::MYSQL);
        }
    }


    private function log($changeset) {
        // var_dump($changeset);die();
        $fh = fopen($this->output_log, "w");
        foreach($changeset as $status_flag => $files) {
            foreach($files as $file) {
                $path_from_root = $this->filename_from_root($file);
                fwrite($fh, "\"$status_flag\",\"$path_from_root\"\n");
            }
        }
        fclose($fh);
    }

    private function get_step_param() {
        $accepted_params = implode(', ', $this->steps);
        // var_dump($this->steps);die();
        if(! isset($_GET['step']) || array_search($_GET['step'], $this->steps) === false) {
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            $error = '412 Missing Parameter';
            $error_details = ! isset($_GET['step']) ? 'missing step parameter' :
                ('invalid step parameter (' . $_GET['step'] . ')');
            header($protocol . ' ' . $error);
            die($error . ": $error_details [$accepted_params]");
        }
        return $_GET['step'];
    }

    private function get_output_dir_size() {
        $du_cmd = "du -k {$this->output_dir} 2>&1";
        exec($du_cmd, $output, $code);
        $size_in_kb = (int)trim($output[0]);
        return $size_in_kb;
    }

    private function json_response($data) {
        $response_payload = json_encode($data);
        header("Content-type: application/json");
        die($response_payload);
    }

    public function start_backup_process() {
        // var_dump($this->progress);
        // var_dump($this->datetime);
        file_put_contents($this->progress, $this->datetime);
    }

    public function generate_backup() {
        $step = $this->get_step_param();
        if($this->is_first_step($step)) {
            $this->start_backup_process();
            // die('first');
        }
        else {
            $this->datetime = $this->get_process_datetime();
            // var_dump($this->datetime);
        }
        $this->setup_process_vars();
        // $step_descriptor = $steps[$step];
        $st_timestamp = $this->current_time_diff();
        $st_output_dir_sz = $this->get_output_dir_size();
// die($this->datetime);
        // $result = $this->prepare_files_archive();
        $cmd = $this->get_cmd($step);
        $task_args = array_merge(
            [$st_output_dir_sz, $cmd, $step],
            $this->get_output_files($step)
        );
        // var_dump($task_args);die();

        $done = call_user_func_array([$this, 'start_background_task'], $task_args);
        $output_dir_size_diff = $this->get_output_dir_size() - $st_output_dir_sz;
        $status = [
            'datetime' => $this->datetime,
            // 'cmd' => $this->cmd_dbg,
            'files' => $this->get_output_files($step),
            'step' => $step,
            'done' => $done,
            'pid'  => ! $done ? (int)$this->pid : null,
            'kb_written' => $output_dir_size_diff
        ];
        $step_num = $this->step_num_progress($step);
        $status['step_index'] = $step_num['index'];
        $status['step_of_total'] = $step_num['of_total'];
        $this->json_response($status);

        echo "sz start: $st_output_dir_sz, diff: $output_dir_size_diff ";
        $time_elapsed = $this->current_time_diff() - $st_timestamp;
        // $this->write_sql_dump(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD);
        // $this->write_zip_archive();
        // if (CLEANUP_AFTER_ZIP) $this->cleanup_tar_and_sql();
        return $this->get_latest_zip_filename();
    }

    function check_progress() {
        $this->datetime = $this->get_process_datetime();
        $this->setup_process_vars();
        try {
            $run = $this->get_latest_run();
            // echo $run;
            $latest_run = $this->get_latest_run();
            $run_info = array_pop($latest_run);
            $info_bits = explode(':', $run_info);
            // var_dump($info_bits);
            $current_step = $info_bits[0];
            $this->pid = (int)$info_bits[1];
            $kb_before = (int)$info_bits[2];
        } catch(Exception $e) {
            // $current = 'done';
        }
        $done = $this->check_running_task_loop();
        $output_dir_size_diff = $this->get_output_dir_size() - $kb_before;
        $status = [
            'step' => $current_step,
            'files' => $this->get_output_files($current_step),
            'done' => $done,
            'pid'  => ! $done ? (int)$this->pid : null,
            'kb_written' => $output_dir_size_diff
        ];
        $step_num = $this->step_num_progress($current_step);
        $status['step_index'] = $step_num['index'];
        $status['step_of_total'] = $step_num['of_total'];
        $response_payload = json_encode($status);
        header("Content-type: application/json");
        die($response_payload);

    }

    private function is_first_step($step) {
        return array_search($step, $this->steps) === 0;
    }

    private function step_num_progress($current_step) {
        $index = array_search($current_step, $this->steps) + 1;
        $total = count($this->steps);
        $step_of_total = "$index/$total";
        return [
            'index' => $index,
            'of_total' => $step_of_total
        ];
    }


    public function download_file($filename) {
        $fullpath = "{$this->output_dir}/$filename";
        // die($fullpath);
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-length: " . filesize($fullpath));
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        readfile($fullpath);
        // unlink($fullpath);
    }
}