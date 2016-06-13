<?php

class T1z_Incremental_Backup_Check {
	public function __construct($output_root_dir) {
        $this->dummy_dir = $output_root_dir . DIRECTORY_SEPARATOR . 'test_create_' . base_convert(microtime(), 10, 36);
        $this->dummy_file = $this->dummy_dir . DIRECTORY_SEPARATOR . 'dummy';
        $this->cmd1 = "tar cf {$this->dummy_file}.tar {$this->dummy_file}";
        $this->cmd2 = "zip {$this->dummy_file}.zip {$this->dummy_file}.tar";
	}

    public function activation_test() {
        // $this->dummy_dir = $this->output_root_dir . DIRECTORY_SEPARATOR . 'test_create_' . time();
        // $this->dummy_file = $this->dummy_dir . DIRECTORY_SEPARATOR . 'dummy';
        $global_test_results = [];
        foreach(['create_dummy_dir', 'create_dummy_file', 'exec_cmd', 'remove_dummy_file'] as $t) {
            $method = "test_{$t}";
            $test_result = $this->$method();
            if (!$test_result) throw new T1z_WPIB_Exception("Error during activation tests: <$t>", T1z_WPIB_Exception::FILES);
            $global_test_results[$t] = $test_result;
        }
        $tar_speed = $this->test_tar_speed();
        $zip_speed = $this->test_zip_speed();
        // $this->test_remove_dummy_file();
        // $global_test_results['remove_dummy_dir'] = $this->test_remove_dummy_dir();
        return [
        	'params' => [
        		'dummy_dir' => $this->dummy_dir,
        		'dummy_file' => $this->dummy_file,
        		'cmd_tar' => $this->cmd1,
        		'cmd_zip' => $this->cmd2
        	],
        	'stats' => [
        		'tar_speed' => $tar_speed,
        		'zip_speed' => $zip_speed
        	],
        	'results' => $global_test_results
        ];
    }

    public function test_create_dummy_dir() {
    	try {
    		return mkdir($this->dummy_dir, 0777, true);	
    	} catch(Exception $e) {
    		die($this->dummy_dir);
    		// return false;
    	}
        
    }

    public function test_create_dummy_file() {
        return touch($this->dummy_file);
    }

    public function test_exec_cmd() {
        exec($this->cmd1, $output, $retcode);
        $this->dummy_cmd_code = $retcode;
        if ($retcode !== 0) throw new T1z_WPIB_Exception("Test exec cmd: <{$this->cmd1}> <$retcode>", T1z_WPIB_Exception::FILES);
        return $retcode === 0;
    }

    public function test_remove_dummy_file() {
        return unlink($this->dummy_file);
    }

    public function test_tar_speed() {
    	$bytes = openssl_random_pseudo_bytes(1024);
    	$fh = fopen($this->dummy_file, "w");
    	for($i = 0 ; $i < 1024 ; $i++) {
    		fwrite($fh, $bytes);
    	}
    	fclose($fh);
    	$start = microtime();
        exec($this->cmd1, $output, $retcode);
        $end = microtime();
        $diff_sec = ($end - $start) / 1000000.0;
        // die("time: $diff_sec");
        // return 1.0 / $diff_sec;
        return $diff_sec;
    }

    public function test_zip_speed() {
    	$start = microtime();
        exec($this->cmd2, $output, $retcode);
        $end = microtime();
        $diff_sec = ($end - $start) / 1000000.0;
        return $diff_sec;
    }

    public function test_remove_dummy_dir() {
        return rmdir($this->dummy_dir);
    }
}