<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

class ProjectPath {

    /**
     * La funzione ritorna un array con i path dell'applicazione.
     *
     * @param $container Container dell'applicazione
     *
     * @return array Ritorna l'array contenente i path
     */
    private $container;
    private $rootdir;
    private $prjdir;
    private $bindir;
    private $srcdir;
    private $appdir;

    public function __construct($container) {
        $this->container = $container;
        $this->getPaths();
    }

    private function getPaths() {
        $path = array();

        $rootdir = dirname($this->container->get('kernel')->getRootDir());
        $this->rootdir = $rootdir;
        $this->prjdir = $rootdir;
        $this->bindir = $this->getProjectPath() . DIRECTORY_SEPARATOR . 'bin';
        $this->srcdir = $this->getProjectPath() . DIRECTORY_SEPARATOR . 'src';
        $this->srcdir = $this->getProjectPath() . DIRECTORY_SEPARATOR . 'src';
        $this->appdir = $this->getProjectPath() . DIRECTORY_SEPARATOR . 'app';

        return $path;
    }

    public function getRootPath() {
        return $this->rootdir;
    }

    public function getProjectPath() {
        return $this->prjdir;
    }

    public function getBinPath() {
        return $this->bindir;
    }

    public function getSrcPath() {
        return $this->srcdir;
    }

    public function getAppPath() {
        return $this->appdir;
    }

}
