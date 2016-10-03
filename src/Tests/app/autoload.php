<?php

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

$loader = include __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/AppKernel.php';

startTestsPA();

function startTestsPA() {
    $vendorDir = dirname(dirname(__FILE__));
    $command = 'rm -rf ' . $vendorDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'test';
    $process = new Process($command);
    $process->setTimeout(60 * 100);
    $process->run();

    cleanFilesystemPA();
}

function cleanFilesystemPA() {
    $DELETE = "new Fi\ProvaBundle\FiProvaBundle(),";
    $vendorDir = dirname(dirname(__FILE__));
    $kernelfile = $vendorDir . '/app/AppKernel.php';
    deleteLineFromFile($kernelfile, $DELETE);
    $routingfile = $vendorDir . '/app/config/routing.yml';
    $line = fgets(fopen($routingfile, 'r'));
    if (substr($line, 0, -1) == 'fi_prova:') {
        for ($index = 0; $index < 4; ++$index) {
            deleteFirstLineFile($routingfile);
        }
    }
    $bundledir = $vendorDir . '/src/Fi/ProvaBundle';

    $fs = new Filesystem();
    if ($fs->exists($bundledir)) {
        $fs->remove($bundledir);
    }
}

function deleteFirstLineFile($file) {
    $handle = fopen($file, 'r');
    fgets($handle, 2048); //get first line.
    $outfile = 'temp';
    $o = fopen($outfile, 'w');
    while (!feof($handle)) {
        $buffer = fgets($handle, 2048);
        fwrite($o, $buffer);
    }
    fclose($handle);
    fclose($o);
    rename($outfile, $file);
}

function deleteLineFromFile($file, $DELETE) {
    $data = file($file);

    $out = array();

    foreach ($data as $line) {
        if (trim($line) != $DELETE) {
            $out[] = $line;
        }
    }

    $fp = fopen($file, 'w+');
    flock($fp, LOCK_EX);
    foreach ($out as $line) {
        fwrite($fp, $line);
    }
    flock($fp, LOCK_UN);
    fclose($fp);
}
