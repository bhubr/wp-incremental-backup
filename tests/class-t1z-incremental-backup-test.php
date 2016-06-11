<?php
use PHPUnit\Framework\TestCase;
require realpath(__DIR__ . '/../class-t1z-incremental-backup.php');

// define('DIR_PREFIX', sys_get_temp_dir());
define('DIR_PREFIX', '/tmp');
define('FILE_PREFIX', 'wpib-test-output');

class T1z_Incremental_BackupTest extends TestCase
{
    private static $input_dir;
    private static $output_root_dir;
    private static $backup_set_id;
    private static $instance;

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
        echo $cmd;
        shell_exec($cmd);
    }


    public function setUp()
    {
        self::$instance = new T1z_Incremental_Backup(self::$input_dir, self::$output_root_dir, self::$backup_set_id, FILE_PREFIX);
    }

    public function test_get_params()
    {
        $params = self::$instance->get_params();
        $this->assertEquals(
            DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir',
            $params['input_dir']
        );
        $this->assertEquals(
            DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir' . DIRECTORY_SEPARATOR . 'uploads',
            $params['output_root_dir']
        );
        $this->assertEquals(DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . self::$backup_set_id,
            $params['output_dir']
        );
        $this->assertEquals(
            DIR_PREFIX . DIRECTORY_SEPARATOR . 'wpib_test_root_dir' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . self::$backup_set_id,
            $params['output_dir']
        );
    }

    public function test_get_output_dir_content()
    {
        $output_dir_content = self::$instance->get_output_dir_content();
        $this->assertTrue(is_array($output_dir_content));
        $this->assertEquals(0, count($output_dir_content));
    }

    public function test_output_dir_content_cleanup()
    {
        $dummy_file = self::$output_root_dir . DIRECTORY_SEPARATOR . self::$backup_set_id . DIRECTORY_SEPARATOR . 'toto';
        $file_created = touch($dummy_file);
        $this->assertTrue($file_created);
        $this->assertTrue(file_exists($dummy_file));
        $output_dir_content = self::$instance->get_output_dir_content();
        $this->assertEquals(1, count($output_dir_content));
        self::$instance->output_dir_content_cleanup();
        $output_dir_content = self::$instance->get_output_dir_content();
        $this->assertEquals(0, count($output_dir_content));
    }

    // public function test_() {

    // }

    // public function test_() {
        
    // }

    // public function test_() {
        
    // }

    // ...
}