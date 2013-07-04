<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
$db_config = array(
    'engine' => 'mysql',
    'host' => $_SERVER['SERVER_ADDR'],
    'name' => 'c9',
    'charset' => 'utf8',
    'user' => 'tsugehara',
    'pass' => ''
);

require_once('PTDB.class.php');
$db = new PTDB();

include 'do_test.inc.php';