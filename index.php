<?php

date_default_timezone_set('Asia/Tashkent');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';

use Paycom\Application;

// load configuration
$paycomConfig = require_once 'paycom.config.php';

$application = new Application($paycomConfig);
$application->run();