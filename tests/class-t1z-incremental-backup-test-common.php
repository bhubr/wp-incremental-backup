<?php
use PHPUnit\Framework\TestCase;

// define('DIR_PREFIX', sys_get_temp_dir());
define('DIR_PREFIX', '/tmp');
define('FILE_PREFIX', 'wpib-test-output');

class T1z_Incremental_Backup_Test_Common extends TestCase
{
    protected static $input_dir;
    protected static $output_root_dir;
    protected static $backup_set_id;

    public static function setUpBeforeClass()
    {
        self::$backup_set_id = base_convert(time(), 10, 36);
        self::$input_dir = DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir';
        self::$output_root_dir = self::$input_dir . DIRECTORY_SEPARATOR . 'uploads';
        $in_created = mkdir(self::$input_dir);
        if (!$in_created) throw new Exception("Could not create input dir: " . self::$input_dir);
        $out_created = mkdir(self::$output_root_dir);
        if (!$out_created) throw new Exception("Could not create output dir: " . self::$output_root_dir);
        
    }

    public static function tearDownAfterClass()
    {
        $cmd = "rm -rf " . self::$input_dir;
        shell_exec($cmd);
    }
}