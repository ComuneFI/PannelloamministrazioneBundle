<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\HttpFoundation\Response;

class LockSystem
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppath = new ProjectPath($container);
    }

    public function getFileLock()
    {
        $cachedir = $this->container->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . 'cache';
        if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
            if (!file_exists($cachedir)) {
                $cachefile = '..' . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache';
                $cachedir = $this->container->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . $cachefile;
            }
        }
        return $cachedir . DIRECTORY_SEPARATOR . 'running.run';
    }

    public function isLockedFile()
    {
        return file_exists($this->getFileLock());
    }

    public function lockFile($lockstate)
    {
        if ($lockstate) {
            file_put_contents($this->getFileLock(), 0777);
        } else {
            unlink($this->getFileLock());
        }
    }

    public function lockedFunctionMessage()
    {
        return new Response(
                "<h2 style='color: orange;
'>E' già in esecuzione un comando, riprova tra qualche secondo!</h2>"
        );
    }

    public function forceCleanLockFile()
    {
        $this->LockFile(false);
    }
}
