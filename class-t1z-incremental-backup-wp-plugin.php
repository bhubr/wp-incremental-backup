<?php
require 'class-t1z-incremental-backup.php';

define('CLEANUP_AFTER_ZIP', false);
class T1z_Incremental_Backup_WP_Plugin {

    private $activation_id;
    private $cnt;

    private $output_list_csv;
    private $output_prefix;
    private $output_file;
    private $output_dir;
    private $first_run;
    private $files;
    private $server_soft;
    private $access_file;

    /**
     * T1z_Incremental_Backup instance
     */
    private $inc_bak;

    /**
     * Initialize count, csv file
     */ 
    public function __construct() {
        $this->setup_server_soft();
        $this->setup_wp();
    }

    private function setup_wp() {
        add_action('admin_menu', [$this, 'wpdocs_register_my_custom_submenu_page']);
        add_action('admin_init', [$this, 'get_activation_id_and_setup']);
        add_action('wp_ajax_wpib_download', [$this, 'download_file']);
        register_activation_hook( __FILE__, [$this, 'set_activation_id'] );
    }


    private function is_apache() {
        return $this->server_soft === 'Apache';
    }

    private function apache_access_file() {
        return "{$this->output_dir}/.htaccess";
    }

    private function is_nginx() {
        return $this->server_soft === 'nginx';
    }

    private function nginx_access_file() {
        return get_home_path() . "wp-content/uploads/wpib-access-nginx";
    }

    private function setup_server_soft() {
        $server_soft = $_SERVER["SERVER_SOFTWARE"];
        $server_soft_bits = explode('/', $server_soft);
        $this->server_soft = $server_soft_bits[0];
    }

    public function get_activation_id_and_setup() {
        $input_dir = get_home_path();
        $output_dir = get_home_path() . "wp-content/uploads/wp-incremental-backup-output";
        $activation_id = get_option('wpib_activation_id', true);
        $sanitized_blog_name = sanitize_title(get_option('blogname'));
        $output_file_prefix = $sanitized_blog_name . '_' . date("Ymd-His");

        try {
            $this->inc_bak = new T1z_Incremental_Backup($input_dir, $output_dir, $activation_id, $output_file_prefix);    
        } catch(Exception $e) {
            add_action( 'admin_notices', [$this, 'admin_notice__error'] );
        }

        if ($this->is_apache() && ! file_exists($this->apache_access_file())) {
            file_put_contents($this->apache_access_file(), "Deny from all");
        }
        else if($this->is_nginx() && ! file_exists($this->nginx_access_file())) {
            $location = $this->inc_bak->get_output_dir();
            file_put_contents($this->nginx_access_file(), "    location $location {\n        deny all;\n    }\n");
        }

    }


    public function set_activation_id() {
        $activation_id = base_convert(time(), 10, 36);
        update_option( 'wpib_activation_id', $activation_id, true );
    }

    public function admin_notice__error() {
        $class = 'notice notice-error';
        $message = __( 'Irks! An error has occurred.', 'sample-text-domain' );

        printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $this->message ); 
    }

    public function wpdocs_register_my_custom_submenu_page() {
            add_management_page( 'Incremental Backup', 'Incremental Backup', 'manage_options', 'incremental-backup', [$this, 'wpib_options_page']);
    }

    public function wpib_options_page() {
        $is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
        if(isset($_GET['do_cleanup'])) {
            $this->inc_bak->output_dir_content_cleanup();
        }
        if ($is_post) {
            $result = $this->inc_bak->prepare_files_archive();
            $this->inc_bak->prepare_sql_dump(DB_HOST, DB_NAME, DB_USER, DB_PASSWORD);
            try {
                $this->inc_bak->prepare_zip();
            } catch (\Exception $e) {
                echo 'mysqldump-php error: ' . $e->getMessage();
            }
            if (CLEANUP_AFTER_ZIP) $this->inc_bak->cleanup_tar_and_sql();
        }

        $files = $this->inc_bak->get_output_dir_content();
        $params = $this->inc_bak->get_params();
        include 'run_form.php';
    }

    public function download_file() {
        if (! isset($_GET['filename'])) {
            $files = $this->inc_bak->get_output_dir_content();
            $filename = array_pop($files);
        }
        else $filename = $_GET['filename'];
        $fullpath = "{$this->inc_bak->output_dir}/$filename";
        header("Content-type: application/zip"); 
        header("Content-Disposition: attachment; filename=$filename");
        header("Content-length: " . filesize($fullpath));
        header("Pragma: no-cache"); 
        header("Expires: 0"); 
        readfile($fullpath);
        // unlink($fullpath);
        exit;
    }
}
