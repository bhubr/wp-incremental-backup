<?php
use Ifsnop\Mysqldump as IMysqldump;
require 'vendor/autoload.php';

define('FILES_TO_DELETE', '__deleted_files__.txt');

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

    private function output_dir_content_cleanup() {
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
        // if(dirname($object->getPathname()) === $this->output_dir) echo dirname($object->getPathname()) . ' ' . $this->output_dir . '<br>';
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
        $this->cnt++;
        $md5 = md5_file($name);
        // echo "$name $md5\n";
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
        $dest = get_home_path() . DIRECTORY_SEPARATOR . FILES_TO_DELETE;
        $fh = fopen($dest, 'w');
        $num_to_delete = count($files_to_delete);
        for($i = 0 ; $i < $num_to_delete ; $i++) {
            $filename = $this->filename_from_root($files_to_delete[$i]);
            $not_last = $i < $num_to_delete - 1;
            fwrite($fh, $filename . ($not_last ? "\n" : ""));
        }
        return $num_to_delete > 0 ? $dest : "";
    }

    /**
     * Write archive
     */
    private function write_archive($files_to_archive) {
        $args = "";
        foreach($files_to_archive as $filename) {
            $args .= ' ' . escapeshellarg($this->filename_from_root($filename));
        }
        if (empty($args)) {
            // echo "no archive to create\n";
            return;
        }
        $cmd = "cd {$this->input_dir}; tar cvf {$this->output_fullpath_prefix}.tar{$args}";
        shell_exec($cmd);
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
        $files = $this->get_output_dir_content();
        $filename = array_pop($files);
        return $filename;
    }

    /**
     * Prepare zip archive from files tar archive and sql dump
     */
    public function prepare_zip() {
        $zip = new ZipArchive();
        $filename = "{$this->output_fullpath_prefix}.zip";

        if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
            throw new Exception("Could not open ZIP archive <$filename>\n");
        }

        $zip->addFile("{$this->output_fullpath_prefix}.sql","{$this->output_file_prefix}.sql");
        if (file_exists("{$this->output_fullpath_prefix}.tar")) {
            $zip->addFile("{$this->output_fullpath_prefix}.tar","{$this->output_file_prefix}.tar");
        }

        // echo "Nombre de fichiers : " . $zip->numFiles . "\n";
        // echo "Statut :" . $zip->status . "\n";
        $zip->close();
    }

    /**
     * Dump SQL
     */
    public function prepare_sql_dump($host, $db, $user, $pass) {
        $dump = new IMysqldump\Mysqldump("mysql:host={$host};dbname={$db}", $user, $pass);
        $dump->start("{$this->output_fullpath_prefix}.sql");
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
        // echo "done: {$this->cnt}\n";

        return [
            'new'      => $files_new,
            'modified' => $files_modified,
            'deleted'  => $files_to_delete
        ];
    }
}