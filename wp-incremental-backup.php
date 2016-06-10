<?php
/**
 * Plugin Name: WP Incremental Backup
 * Plugin URI: https://github.com/t1z/wp-incremental-backup
 * Description: Create incremental backups of WordPress files&db
 * Author: t1z
 * Author URI: https://github.com/t1z
 * Version: 0.3.5
 *
 * ChangeLog
 * 0.2.0 First public version
 * 0.2.1 Detect server soft
 * 0.2.2 Write .htaccess for Apache
 * 0.2.3 Admin notices & fix indentation
 * 0.2.4 Admin notices message
 * 0.2.5 Change output dir location and fix .htaccess writing
 * 0.2.6 nginx access file
 * 0.2.7 nginx access file fix
 * 0.2.8 insert .sql.zip as media (commented out)
 * 0.2.9 client and server working together
 * 0.3.0 unlink files after processing
 * 0.3.1 allow cleanup of generated files
 * 0.3.2 comment out attachment creation
 * 0.3.3 move download_file function, allow download from list
 * 0.3.4 fix archive file paths
 * 0.3.5 fix download when no filename is specified
 *
 * ToDo
 *   - exclude output_dirs
 *   - fix zip download
 *   - encrypt files: mcrypt/GPG/...?
 *   - make it compatible with other platforms (Drupal, Joomla, all PHP frameworks)
 *
 * Different cases:
 * - upload media
 * - delete media
 * - add plugin
 * - delete plugin
 * - add theme
 * - delete theme
 * - edit plugin/theme file
 */

require 'class-t1z-incremental-backup-wp-plugin.php';

// $args = !isset($argv) ? [ 'root' => $_GET['root'], 'domain' => $_GET['domain'] ] :
//     [ 'root' => $argv[1], 'domain' => $argv[2] ];
// $args['root'], $args['domain']
$walker = new T1z_Incremental_Backup_WP_Plugin();
