<?php
trait T1z_Walker_Common {

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
        $prefix_len = strlen($this->input_dir);
        $last_char = $this->input_dir[$prefix_len - 1];
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