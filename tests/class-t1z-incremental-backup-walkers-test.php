<?php
use PHPUnit\Framework\TestCase;
require realpath(__DIR__ . '/../class-t1z-incremental-backup-md5-walker.php');
require realpath(__DIR__ . '/../class-t1z-incremental-backup-deleted-walker.php');
require 'class-t1z-incremental-backup-test-common.php';

class T1z_Incremental_Backup_Walkers_Test extends T1z_Incremental_Backup_Test_Common
{
	protected static $output_dir;
	protected static $md5_csv;
	protected static $archive_list;
    protected static $delete_list;
	protected static $md5_walker;
    protected static $deleted_walker;

    public function setUp()
    {
    	self::$output_dir = self::$output_root_dir . DIRECTORY_SEPARATOR . self::$backup_set_id;
    	$out_created = mkdir(self::$output_dir);
    	if (!$out_created) throw new Exception("Could not create output dir: " . self::$output_root_dir);
    	self::$md5_csv = self::$output_dir . DIRECTORY_SEPARATOR . 'list.csv';
    	self::$archive_list = self::$output_dir  . DIRECTORY_SEPARATOR . 'tar_list.txt';
        self::$delete_list = self::$output_dir  . DIRECTORY_SEPARATOR . '__deleted_files__.txt';
    }

    protected function touch_file($rel_path) {
    	$dir = dirname(self::$input_dir . DIRECTORY_SEPARATOR . $rel_path);
    	if (! is_dir($dir)) {
    		$dir_created = mkdir($dir, 0777, true);
    		if (!$dir_created) throw new Exception("Could not create dir: $dir");
    	}
    	touch($dir . DIRECTORY_SEPARATOR . basename($rel_path));
    }

    protected function modify_file($rel_path) {
        $dir = dirname(self::$input_dir . DIRECTORY_SEPARATOR . $rel_path);
        $fh = fopen($dir . DIRECTORY_SEPARATOR . basename($rel_path), 'a+');
        fwrite($fh, "\nsome text");
        fclose($fh);
    }

    protected function rm_file($rel_path) {
    	unlink(self::$input_dir . DIRECTORY_SEPARATOR . $rel_path);
	}

    public function test_prepare_files_archive()
    {
        // Create a few files
    	$this->touch_file('toto');
        $this->touch_file('tata');
    	$this->touch_file('tata est partie');
    	$this->touch_file('dir1/toto est parti');
        symlink (self::$input_dir . DIRECTORY_SEPARATOR . 'toto' , self::$input_dir . DIRECTORY_SEPARATOR . 'toto.link' );
    	
        // Instantiate and run walkers
        self::$md5_walker = new T1z_Incremental_Backup_MD5_Walker(self::$md5_csv, self::$archive_list, self::$input_dir);
    	self::$md5_walker->prepare_files_archive();
        self::$deleted_walker = new T1z_Incremental_Backup_Deleted_Walker(self::$archive_list, self::$delete_list, self::$input_dir);
        self::$deleted_walker->run();

        // Read and count entries in md5, tar and deleted lists
        $deleted_list = file(self::$delete_list);
    	$md5_list = file(self::$md5_csv);
        var_dump(file(self::$archive_list));

    	$this->assertEquals(7, count($md5_list)); // symlink must be ignored
    	$archive_list = file(self::$archive_list);
    	$this->assertEquals(4, count($archive_list));
        $this->assertFalse(array_search('toto.link', $archive_list));
        $delete_list = file(self::$delete_list);
        $this->assertEquals(0, count($delete_list));

        // Delete two files, modify one, and re-run walkers
    	$this->rm_file('tata est partie');
    	$this->rm_file('dir1/toto est parti');
        $this->modify_file('tata');
        self::$deleted_walker = new T1z_Incremental_Backup_Deleted_Walker(self::$archive_list, self::$delete_list, self::$input_dir);
        self::$deleted_walker->run();
        self::$md5_walker = new T1z_Incremental_Backup_MD5_Walker(self::$md5_csv, self::$archive_list, self::$input_dir);
    	self::$md5_walker->prepare_files_archive();
        var_dump(file(self::$archive_list));

        // Read and recount entries in md5, tar and deleted lists
    	$md5_list = file(self::$md5_csv);
        // var_dump(file(self::$md5_csv));
    	$this->assertEquals(5, count($md5_list));
    	$archive_list = file(self::$archive_list);
    	$this->assertEquals(1, count($archive_list));
        $this->assertEquals('tata', $archive_list[0]);
        $delete_list = file(self::$delete_list);
        $this->assertEquals(2, count($delete_list));
    }

}