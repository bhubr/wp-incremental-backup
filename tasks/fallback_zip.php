<?php
echo "php manual zip build\n";
if (php_sapi_name() !== 'cli') {
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
    $error = '404 Not Found';
    header($protocol . ' ' . $error);
    die($error);
}
if($argc < 2) exit;
$output_fullpath_prefix = $argv[1];
$output_file_prefix = basename($output_fullpath_prefix);
$zip = new ZipArchive();
$filename = "{$output_fullpath_prefix}.zip";
if ($zip->open($filename, ZipArchive::CREATE) !== true) {
    throw new T1z_WPIB_Exception("Could not open ZIP archive $filename\n", T1z_WPIB_Exception::ZIP);
}
try {
	$zip->addFile("{$output_fullpath_prefix}.sql","{$output_file_prefix}.sql");
	if (file_exists("{$output_fullpath_prefix}.tar")) {
	    $zip->addFile("{$output_fullpath_prefix}.tar","{$output_file_prefix}.tar");
	}
	$zip->close();

} catch(Exception $e) {
	die("Error while creating ZIP: $filename");
}
