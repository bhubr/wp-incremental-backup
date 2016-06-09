<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'fetch.conf.php';
// $cookie="cookie.txt";
$cookie = tempnam ("/tmp", "CURLCOOKIE");


// 1- GET
$ch = curl_init();
curl_setopt ($ch, CURLOPT_URL, $url . "maitreyoda/");
curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($ch, CURLOPT_COOKIESESSION, true);
curl_setopt ($ch, CURLOPT_COOKIE, $cookie);
curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt ($ch, CURLOPT_REFERER, $url . "wp-admin/");

$result = curl_exec ($ch);
var_dump($result);

// 2- POST
$postdata = "log=". $username ."&pwd=". $password ."&wp-submit=Log%20In&redirect_to=". $url ."wp-admin/&testcookie=1";

// curl_setopt ($ch, CURLOPT_URL, $url . "wp-login.php");
// curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
// curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
// curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
// curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
// curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
// curl_setopt ($ch, CURLOPT_COOKIESESSION, true);
// curl_setopt ($ch, CURLOPT_COOKIE, $cookie);
// curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
// curl_setopt ($ch, CURLOPT_REFERER, $url . "wp-admin/");
curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
curl_setopt ($ch, CURLOPT_POST, 1);
$result = curl_exec ($ch);
var_dump($result);


curl_setopt ($ch, CURLOPT_URL, $url . "wp-admin/tools.php?page=incremental-backup");
curl_setopt ($ch, CURLOPT_POSTFIELDS, "");
$result = curl_exec ($ch);
var_dump($result);


curl_close($ch);




exit;