<?php
use PHPUnit\Framework\TestCase;
require realpath(__DIR__ . '/../class-t1z-incremental-backup.php');
require 'class-t1z-incremental-backup-test-common.php';

class T1z_Incremental_Backup_Test extends T1z_Incremental_Backup_Test_Common
{
    private static $instance;


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