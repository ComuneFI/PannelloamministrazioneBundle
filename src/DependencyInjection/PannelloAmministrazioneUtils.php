<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Fi\OsBundle\DependencyInjection\OsFunctions;
use Symfony\Component\Process\Process;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\ProjectPath;

class PannelloAmministrazioneUtils
{

    private $container;
    private $apppaths;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppaths = new ProjectPath($container);
    }

    public function clearcache()
    {
        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = '/usr/bin/php';
        }
        if (defined('HHVM_VERSION') && false !== $hhvm = getenv('PHP_BINARY')) {
            $phpPath = $hhvm;
            $phpPath = $phpPath . " -v Eval.EnableHipHopSyntax=true ";
        }
        $command = $phpPath . ' ' . $this->apppaths->getConsole() . ' cache:clear --no-debug '
                . '--env=' . $this->container->get('kernel')->getEnvironment();

        return PannelloAmministrazioneUtils::runCommand($command);
    }

    public static function runCommand($command)
    {
        /* @var $process \Symfony\Component\Process\Process */
        $return = array();
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (!$process->isSuccessful()) {
            $return = array("errcode" => -1, "errmsg" => 'Errore nel comando ' . $command . $process->getErrorOutput() . $process->getOutput());
        } else {
            $return = array("errcode" => 0, "errmsg" => $process->getOutput());
        }

        return $return;
    }
}
