<?php
use PHPUnit\Framework\TestCase;
require realpath(__DIR__ . '/../class-t1z-incremental-backup.php');

// define('DIR_PREFIX', sys_get_temp_dir());
define('DIR_PREFIX', '/tmp');
define('FILE_PREFIX', 'wpib-test-output');

class T1z_Incremental_BackupTest extends TestCase
{
    private static $input_dir;
    private static $output_dir;
    private static $backup_set_id;
    private static $instance;

    public static function setUpBeforeClass()
    {
        self::$backup_set_id = base_convert(time(), 10, 36);
        self::$input_dir = DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir';
        self::$output_dir = self::$input_dir .  DIRECTORY_SEPARATOR . 'uploads';
        $in_created = mkdir(self::$input_dir);
        if (!$in_created) throw new Exception("Could not create input dir: " . self::$input_dir);
        $out_created = mkdir(self::$output_dir);
        if (!$out_created) throw new Exception("Could not create output dir: " . self::$output_dir);
        
    }

    public static function tearDownAfterClass()
    {
        $cmd = "rm -rf " . self::$input_dir;
        echo $cmd;
        shell_exec($cmd);
    }


    public function setUp()
    {
        self::$instance = new T1z_Incremental_Backup(self::$input_dir, self::$output_dir, self::$backup_set_id, FILE_PREFIX);
    }

    public function test_get_params()
    {
        $params = self::$instance->get_params();
        $this->assertEquals(DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir', $params['input_dir']);
    }

    // ...
}