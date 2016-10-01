<?php

$loader = require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/AppKernel.php';

$vendorDir = dirname(dirname(__FILE__));

$autoloadfiles = array(
    'Fi\\ProvaBundle\\' => $vendorDir.'/src/Fi/ProvaBundle',
);
foreach ($autoloadfiles as $namespace => $path) {
    //var_dump($path);exit;
    //var_dump(file_exists($path));exit;
    $loader->addPsr4($namespace, $path);
}
$loader->register(true);
