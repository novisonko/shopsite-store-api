<?php
//exec('php composer.phar');

@ini_set( 'display_errors', 'On' );
@ini_set( 'log_errors', 'On' );
@error_reporting(E_ALL & ~E_NOTICE);
@ini_set( 'error_log', 'error.log' );

define ('ROOT_DIR', dirname(__FILE__));

require_once(ROOT_DIR . '/vendor/autoload.php');
require_once(ROOT_DIR . '/app/sonkotek.php.lib/php5-functions.php');
require_once(ROOT_DIR . '/config.php');