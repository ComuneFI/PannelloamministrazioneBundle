<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

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
        return $this->container->get('kernel')->getRootDir().DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'running.run';
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