<?php
require_once 'constants.php';
// require_once 'class-t1z-wpib-exception.php';
require_once 'trait-t1z-walker-common.php';
require_once 'class-t1z-incremental-backup-task-common.php';

class T1z_Incremental_Backup_Deleted_Walker extends T1z_Incremental_Backup_Task {
    use T1z_Walker_Common;

    private $files_md5 = [];

    public function __construct($input_dir, $output_dir, $datetime) {
        parent::__construct(TASK_LIST_DELETED, $input_dir, $output_dir, $datetime, T1z_Incremental_Backup_Task::PROGRESS_INTERNAL);
        $this->add_outfile($this->del_list);
        try {
            $this->set_progress_total($this->count_files());
            $this->read_file_md5_list();
        } catch(Exception $e) {
            $this->echo_status(false);
            return;
        }
        $this->echo_status(true);
        // die($this->get_outfile(static::OUT_DEL));
    }

    /**
     * Recurse wp installation
     */
    public function run() {
        $found_in_dirs = [];
        $files_to_delete = [];
        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->get_input_dir()),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // $this->set_progress_total(2 * count($objects));
// $i = 0;
        // Iterate directory
        foreach($objects as $name => $object) {

            // Skip if this is . or .. or output dir 
            if($this->is_special_dir($object) || $this->is_output_dir($object)) {
                continue;
            }
            else {
                $this->increment_progress();
            }

            // Skip dir
            // Skip deleted files list => delete it
            if($object->getFilename() === FILE_LIST_TO_DELETE) {
                unlink($object->getPathname());
                continue;
            }

            if(is_dir($object->getPathname())) {
                continue;
            }

            if(!$this->is_regular_file($object)) continue;
            $found_in_dirs[] = $name;

            
        }

        // Iterate existing files from previous backup's list
        foreach($this->files_md5 as $name => $md5) {
            if (!empty($md5) && array_search($name, $found_in_dirs) === false) {
                $files_to_delete[] = $name;
            }
        }
        $this->write_delete_list($files_to_delete);
        $this->echo_end();
    }

    /**
     * Write files to delete list
     */
    private function write_delete_list($files_to_delete) {
        $this->write_file_list($this->del_list, $files_to_delete);
    }
}
