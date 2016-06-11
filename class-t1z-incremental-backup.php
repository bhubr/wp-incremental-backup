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

    public function __construct($input_dir, $output_root_dir, $output_set_id, $output_file_prefix) {
        $this->input_dir = $input_dir;
        $this->output_root_dir = $output_root_dir;
        $this->output_set_id = $output_set_id;
        $this->output_dir = $this->output_root_dir . DIRECTORY_SEPARATOR . $output_set_id;
        $this->output_file_prefix = $output_file_prefix;
        $this->output_fullpath_prefix = $this->output_dir . DIRECTORY_SEPARATOR . $output_file_prefix;
        $this->output_log = $this->output_fullpath_prefix . '_log.csv';

        if (! is_dir($this->output_dir)) {
            $dir_created = mkdir($this->output_dir, 0777, true);
            if (! $dir_created) throw new Exception("Could not create output_dir: {$this->output_dir}");
        }

        $this->output_list_csv = $this->output_dir . "/list.csv";

        $this->first_run = !file_exists($this->output_list_csv);
        if ($this->first_run) {
            $this->files = [];
        }
        else {
            $this->read();
        }
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

    /**
     * Check if file is a special dir: either . or ..
     */
    private function is_special_dir($object) {
        return $object->getFilename() === '.' || $object->getFilename() === '..';
    }

    /**
     * Check if file is the output dir
     */
    private function is_output_dir($object) {
        return dirname($object->getPathname()) === $this->output_dir;
    }

    /**
     * Check if file is a regular file
     */
    private function is_regular_file($object) {
        return !is_dir($object->getPathname());
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
     * Read last file list
     */
    public function read() {
        $this->fh = fopen($this->output_list_csv, "r");
        do {
            $line_read = fgetcsv($this->fh);
            if (is_null($line_read)) {
                throw new Exception("invalid handle, aborting");
            }
            $name = $line_read[0];
            $md5 = $line_read[1];
            $this->files[$name] = $md5;
        } while($line_read !== false);
    }

    /**
     * Get md5 from existing file
     */
    private function get_md5($name) {
        return $this->files[$name];
    }

    /**
     * Get filename, stripped from root dir (wp installation base dir)
     */
    private function filename_from_root($filename) {
        $prefix_len = strlen($this->input_dir);
        $last_char = $this->input_dir[$prefix_len - 1];
        $prefix_len += ($last_char === '/') ? 0 : 1;
        return substr($filename, $prefix_len);
    }

    /**
     * Write files to delete list
     */
    private function write_files_to_delete($files_to_delete) {
        $dest = get_home_path() . FILES_TO_DELETE;
        $fh = fopen($dest, 'w');
        $num_to_delete = count($files_to_delete);
        for($i = 0 ; $i < $num_to_delete ; $i++) {
            $filename = $this->filename_from_root($files_to_delete[$i]);
            $not_last = $i < $num_to_delete - 1;
            fwrite($fh, $filename . ($not_last ? "\n" : ""));
        }
        fclose($fh);
        return $num_to_delete > 0 ? $dest : '';
    }

    /**
     * Write archive
     */
    private function write_archive($files_to_archive) {
        if (empty($files_to_archive)) {
            return;
        }
        $list = $this->output_dir . DIRECTORY_SEPARATOR . 'archive.txt';
        $fh = fopen($list, 'w');

        $files = array_map(function($file) {
            return $this->filename_from_root($file);
        }, $files_to_archive);
        $file_list = implode("\n", $files);
        file_put_contents($list, $file_list);
        $tarfile = "{$this->output_fullpath_prefix}.tar";
        $cmd = "cd {$this->input_dir}; tar cv -T {$list} -f $tarfile";
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);
        if ($return_var !== 0) {
            throw new T1z_WPIB_Exception("Error while creating output TAR file {$tarfile}", T1z_WPIB_Exception::FILES);    
        }
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

    /**
     * Prepare zip archive from files tar archive and sql dump
     */
    public function prepare_zip() {
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
    public function prepare_sql_dump($host, $db, $user, $pass) {
        try {
            $dump = new IMysqldump\Mysqldump("mysql:host={$host};dbname={$db}", $user, $pass);
            $dump->start("{$this->output_fullpath_prefix}.sql");
        } catch (\Exception $e) {
            throw new T1z_WPIB_Exception($e->getMessage(), T1z_WPIB_Exception::MYSQL);
        }
    }

    /**
     * Recurse wp installation
     */
    public function prepare_files_archive() {
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

            // Skip deleted files list => delete it
            if($object->getFilename() === FILES_TO_DELETE) {
                unlink($object->getPathname());
                continue;
            }

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
        foreach($this->files as $name => $md5) {
            if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
                // echo "<em>deleted</em> file: $name<br>";
                $files_to_delete[] = $name;
            }
        }
        $deleted_list_file = $this->write_files_to_delete($files_to_delete);
        if (!empty($deleted_list_file)) {
            $files_to_archive[] = $deleted_list_file;
        }

        $this->write_archive($files_to_archive);

        fclose($this->fh);
        // Log what whas done
        $this->log([
            'new'      => $files_new,
            'modified' => array_keys($files_modified),
            'deleted'  => $files_to_delete
        ]);

        return [
            'new'      => $files_new,
            'modified' => $files_modified,
            'deleted'  => $files_to_delete
        ];
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

    public function generate_backup() {
        error_log(__CLASS__ ."::" . __FUNCTION__ . " {$this->output_file_prefix} #1 before prepare_files_archive");
        $result = $this->prepare_files_archive();
        error_log(__CLASS__ ."::" . __FUNCTION__ . "#2 before prepare_sql_dump");
        $this->prepare_sql_dump(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD);
        error_log(__CLASS__ ."::" . __FUNCTION__ . "#3 before prepare_zip");
        $this->prepare_zip();
        error_log(__CLASS__ ."::" . __FUNCTION__ . "#4 before cleanup");
        if (CLEANUP_AFTER_ZIP) $this->cleanup_tar_and_sql();
        error_log(__CLASS__ ."::" . __FUNCTION__ . "#5 all done");
        return $this->get_latest_zip_filename();
    }

    public function download_file() {
        $fullpath = "{$this->inc_bak->output_dir}/$filename";
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-length: " . filesize($fullpath));
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        readfile($fullpath);
        // unlink($fullpath);
    }
}