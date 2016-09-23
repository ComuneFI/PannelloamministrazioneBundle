<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Symfony\Component\Process\Process;

class LockSystem {

    private $container;

    public function __construct($container) {
        $this->container = $container;
        $this->apppath = new ProjectPath($container);
    }

    public function getFileLock() {
        return $this->container->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'running.run';
    }

    public function isLockedFile() {
        return file_exists($this->getFileLock());
    }

    public function LockFile($lockstate) {
        if ($lockstate) {
            file_put_contents($this->getFileLock(), 0777);
        } else {
            unlink($this->getFileLock());
        }
    }

    public function LockedFunctionMessage() {
        return new Response("<h2 style='color: orange;
'>E' già in esecuzione un comando, riprova tra qualche secondo!</h2>");
    }

    public function forceCleanLockFile() {
        $this->LockFile(false);
    }

}
