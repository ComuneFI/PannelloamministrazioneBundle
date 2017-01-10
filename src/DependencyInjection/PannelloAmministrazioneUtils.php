<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\ProjectPath;
use Fi\OsBundle\DependencyInjection\OsFunctions;

class PannelloAmministrazioneUtils
{

    private $container;
    private $apppaths;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppaths = new ProjectPath($container);
    }

    public function clearcache($env = "")
    {
        if (!$env) {
            $env = $this->container->get('kernel')->getEnvironment();
        }
        $phpPath = OsFunctions::getPHPExecutableFromPath();

        $command = $phpPath . ' ' . $this->apppaths->getConsole() . ' cache:clear --no-debug '
                . '--env=' . $env;

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
            $return = array("errcode" => -1,
                "command" => $command,
                "errmsg" => 'Errore nel comando ' . $command . $process->getErrorOutput() . $process->getOutput());
        } else {
            $return = array("errcode" => 0,
                "command" => $command,
                "errmsg" => $process->getOutput()
            );
        }

        return $return;
    }

    public function runSymfonyCommand($command, array $options = array())
    {
        $application = new Application($this->container->get('kernel'));
        $application->setAutoExit(false);

        $cmdoptions = array_merge(array('command' => $command), $options);

        $fp = tmpfile();
        $outputStream = new StreamOutput($fp);
        $returncode = $application->run(new ArrayInput($cmdoptions), $outputStream);
        $output = $this->getOutput($fp);
        fclose($fp);

        return array('errcode' => ($returncode == 0 ? false : true), 'command' => $cmdoptions['command'], 'message' => $output);
    }

    private function getOutput($fpOutupStream)
    {
        fseek($fpOutupStream, 0);
        $output = '';
        while (!feof($fpOutupStream)) {
            $output = $output . fread($fpOutupStream, 4096);
        }

        return $output;
    }
}
