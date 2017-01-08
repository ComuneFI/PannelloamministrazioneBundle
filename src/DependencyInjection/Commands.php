<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Input\ArrayInput;
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Symfony\Component\Process\Process;

class Commands
{

    private $container;
    private $apppath;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppath = new ProjectPath($container);
    }

    public function generateEntity($wbFile, $bundlePath)
    {
        $console = $this->apppath->getConsole();
        $pannellocmd = "pannelloamministrazione:generateentities $wbFile $bundlePath";

        $scriptGenerator = $console . " " . $pannellocmd;

        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = '/usr/bin/php';
        }
        $sepchr = self::getSeparator();

        $command = 'cd ' . $this->apppath->getRootPath() . $sepchr
                . $phpPath . ' ' . $scriptGenerator . ' --env=' . $this->container->get('kernel')->getEnvironment();

        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (!$process->isSuccessful()) {
            return array(
                'errcode' => -1,
                'message' => 'Errore nel comando: <i style="color: white;">' .
                $command . '</i><br/><i style="color: red;">' .
                str_replace("\n", '<br/>', ($process->getErrorOutput() ? $process->getErrorOutput() : $process->getOutput())) .
                'in caso di errori eseguire il comando symfony non da web: pannelloamministrazione:generateentities ' .
                $wbFile . ' ' . $bundlePath . '<br/>Opzione --schemaupdate oer aggiornare anche lo schema database</i>',
            );
        }

        return array(
            'errcode' => 0,
            'message' => '<pre>Eseguito comando: <i style = "color: white;">' .
            $command . '</i><br/>' . str_replace("\n", '<br/>', $process->getOutput()) . '</pre>',);
    }

    public function generateFormCrud($bundlename, $entityform)
    {
        /* @var $fs \Symfony\Component\Filesystem\Filesystem */
        $fs = new Filesystem();
        $srcPath = $this->apppath->getSrcPath();
        $appPath = $this->apppath->getAppPath();
        if (!is_writable($appPath)) {
            return array('errcode' => -1, 'message' => $appPath . ' non scrivibile');
        }
        $formPath = $srcPath . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR .
                'Form' . DIRECTORY_SEPARATOR . $entityform . 'Type.php';

        if ($fs->exists($formPath)) {
            return array('errcode' => -1, 'message' => $formPath . ' esistente');
        }

        $controllerPath = $srcPath . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR .
                'Controller' . DIRECTORY_SEPARATOR . $entityform . 'Controller.php';

        if ($fs->exists($controllerPath)) {
            return array('errcode' => -1, 'message' => $controllerPath . ' esistente');
        }

        $viewPathSrc = $srcPath . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR .
                'Resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . $entityform;

        if ($fs->exists($viewPathSrc)) {
            return array('errcode' => -1, 'message' => $viewPathSrc . ' esistente');
        }

        $crudparms = array(
            '--entity' => str_replace('/', '', $bundlename) . ':' . $entityform,
            '--route-prefix' => $entityform,
            "--env" => $this->container->get('kernel')->getEnvironment(),
            '--with-write' => true, '--format' => 'yml', '--overwrite' => false, '--no-interaction' => true,);

        $resultcrud = $this->executeCommand('doctrine:generate:crud', $crudparms);

        if ($resultcrud['errcode'] == 0) {
            $fs->remove($viewPathSrc);
            $generator = new GenerateCode($this->container);

            $retmsggenerateform = $generator->generateFormsTemplates($bundlename, $entityform);

            $generator->generateFormsDefaultTableValues($entityform);

            $appviews = $appPath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views';
            $this->cleanTemplatePath($appviews);

            $resourcesviews = $appPath . DIRECTORY_SEPARATOR . 'Resources';
            $this->cleanTemplatePath($resourcesviews);

            $retmsg = array(
                'errcode' => 0,
                'command' => $resultcrud['command'],
                'message' => $resultcrud['message'] . $retmsggenerateform,
            );
        } else {
            $retmsg = array(
                'errcode' => $resultcrud['errcode'],
                'command' => $resultcrud['command'],
                'message' => $resultcrud['message'],
            );
        }

        return $retmsg;
    }

    private function cleanTemplatePath($path)
    {
        $fs = new Filesystem();
        $ret = 0;
        if ($fs->exists($path)) {
            $finder = new Finder();
            $ret = $finder->files()->in($path);
            if (count($ret) == 0) {
                $fs->remove($path);
            }
        }
    }

    public function executeCommand($command, array $options = array())
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

    public function clearcache()
    {
        $cmdoutput = "";
        $envs = array("dev", "test", "prod");
        foreach ($envs as $env) {
            $cmdoutput = $cmdoutput . $this->clearcacheEnv($env);
        }

        return $cmdoutput;
    }

    public function clearcacheEnv($env = "dev")
    {
        if (!OsFunctions::isWindows()) {
            $phpPath = '/usr/bin/php';
        } else {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        }
        $console = $this->apppath->getConsole();

        $command = $phpPath . ' ' . $console . ' cache:clear --env=' . $env;
        //if ($env == "prod" || $env == "test") {
        $command = $command . ' --no-debug';
        //}

        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        $cmdoutput = $this->getProcessOutput($process);

        return $command . $cmdoutput;
    }

    public function aggiornaSchemaDatabase()
    {
        $result = $this->executeCommand('doctrine:schema:update', array('--force' => true));

        return $result;
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

    private function getProcessOutput($process)
    {
        $erroroutput = $process->getErrorOutput() ? $process->getErrorOutput() : $process->getOutput();
        $output = ($process->isSuccessful()) ? $process->getOutput() : $erroroutput;

        return $output;
    }

    private static function getSeparator()
    {
        if (OsFunctions::isWindows()) {
            return '&';
        } else {
            return ';';
        }
    }
}
