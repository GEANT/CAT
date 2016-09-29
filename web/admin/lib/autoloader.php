<?php
require_once (__DIR__ . "/autoloader/Psr4Autoloader.php");
use lib\autoloader\Psr4Autoloader;

// instantiate the loader
$loader = new Psr4Autoloader();

// register the autoloader
$loader->register();

// register the base directories for the namespace prefix
$loader->addNamespace('lib', __DIR__);