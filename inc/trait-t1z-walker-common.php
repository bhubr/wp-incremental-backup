<?php
require_once 'class-t1z-incremental-backup-task-common.php';
trait T1z_Walker_Common {

    public function count_files() {
        $input_dir = $this->get_input_dir();
        exec("find {$input_dir} | wc -l", $find_wc_in, $ret);
        if (empty($find_wc_in)) {
            throw new Exception("[in]find|wc failed: " . $find_wc_in[0]);
        }
        $num_in_input = (int)trim($find_wc_in[0]);
        if ($num_in_input === 0) {
            throw new Exception("[in]find|wc failed: " . $find_wc_in[0]);
        }

        $output_dir = $this->get_output_dir();
        if(fnmatch("$input_dir/*", $output_dir)) {
            exec("find {$output_dir} | wc -l", $find_wc_out, $ret);
            if (empty($find_wc_out)) {
                throw new Exception("[out]find|wc failed");
            }
            $num_in_output = (int)trim($find_wc_out[0]);
            if ($num_in_output === 0) {
                throw new Exception("[out]find|wc failed: " . $find_wc_out[0]);
            }
        }
        else {
            $num_in_output = 0;
        }
        // $this->add_debug("num in output: $num_in_output");
        return $num_in_input - $num_in_output;
    }

    /**
     * Read last file list
     */
    public function read_file_md5_list() {
        if (! file_exists(T1z_Incremental_Backup_Task::MD5)) return;
        $this->fh = fopen($this->get_infile(T1z_Incremental_Backup_Task::IN_MD5), "r");
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
        return $this->files_md5[$name];
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
        return dirname($object->getPathname()) === $this->get_output_dir();
    }

    /**
     * Check if excluded dir
     */
    private function is_excluded($object) {
        foreach($this->excluded as $pattern) {
            // If we provide only a wildcard like a*.xz we want it to apply
            // in any subfolder. In this case we match only the file name
            // Otherwise if a folder is specified we match the full path
            $has_path = strpos($pattern, '/');
            $to_match = $object->getPathname();
            // $has_path ? $object->getPathname() : $object->getFilename();

            $is_excluded = fnmatch($pattern, $to_match);
            if ($is_excluded) 
                echo "excluded: $to_match\n";
            // else {
            //     echo "include: $to_match " . $to_match . "\n";
            // }
            if (fnmatch($pattern, $to_match)) return true;
        }
        return false;
    }

    /**
     * Check if file is a regular file
     */
    private function is_regular_file($object) {
        return !is_dir($object->getPathname()) && !is_link($object->getPathname());
    }

    /**
     * Get filename, stripped from root dir (wp installation base dir)
     */
    private function filename_from_root($filename) {
        $input_dir = $this->get_input_dir();
        $prefix_len = strlen($input_dir);
        $last_char = $input_dir[$prefix_len - 1];
        $prefix_len += ($last_char === '/') ? 0 : 1;
        return substr($filename, $prefix_len);
    }

    /**
     * Write a file list
     */
    private function write_file_list($fh, $files) {
        $num_files = count($files);
        for($i = 0 ; $i < $num_files ; $i++) {
            $filename = $this->filename_from_root($files[$i]);
            $not_last = $i < $num_files - 1;
            fwrite($fh, $filename . ($not_last ? "\n" : ""));
        }
    }

}