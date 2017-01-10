<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\PannelloAmministrazioneUtils;
use Symfony\Component\Process\Process;

class Commands
{

    private $container;
    private $apppaths;
    private $pammutils;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppaths = new ProjectPath($container);
        $this->pammutils = new PannelloAmministrazioneUtils($container);
    }

    public function getVcs()
    {
        $fs = new Filesystem();

        $sepchr = OsFunctions::getSeparator();
        $projectDir = $this->apppaths->getRootPath();
        if ($fs->exists($projectDir . DIRECTORY_SEPARATOR . '.svn')) {
            $vcscommand = 'svn update';
        }
        if ($fs->exists($projectDir . DIRECTORY_SEPARATOR . '.git')) {
            $vcscommand = 'git pull';
        }

        $command = 'cd ' . $projectDir . $sepchr . $vcscommand;
        return $this->pammutils->runCommand($command);
    }

    public function generateBundle($bundleName)
    {
        /* @var $fs \Symfony\Component\Filesystem\Filesystem */
        $fs = new Filesystem();

        $commands = new PannelloAmministrazioneUtils($this->container);

        $srcPath = $this->apppaths->getSrcPath();

        $bundlePath = $this->apppaths->getSrcPath() . DIRECTORY_SEPARATOR . $bundleName;

        $addmessage = '';

        if ($fs->exists($bundlePath)) {
            return array('errcode' => -1, 'command' => 'generate:bundle', 'message' => "Il bundle esiste gia' in $bundlePath");
        }
//        if (!is_writable($bundlePath)) {
//            return array('errcode' => -1, 'command' => 'generate:bundle', 'message' => "$bundlePath non scrivibile");
//        }

        $commandparms = array(
            '--namespace' => $bundleName,
            '--dir' => $srcPath . DIRECTORY_SEPARATOR,
            '--format' => 'yml',
            '--env' => $this->container->get('kernel')->getEnvironment(),
            '--no-interaction' => true);
        $result = $commands->runSymfonyCommand('generate:bundle', $commandparms);
        $bundlePath = $srcPath . DIRECTORY_SEPARATOR . $bundleName;
        if ($fs->exists($bundlePath)) {
            $addmessage = 'Per abilitare il nuovo bundle nel kernel controllare che sia presente in app/AppKernel.php '
                    . 'e aggiornare la pagina';
            $ret = array('errcode' => 0, 'command' => 'generate:bundle', 'message' => $result["message"] . $addmessage);
        } else {
            $addmessage = "Non e' stato creato il bundle in $bundlePath";
            $ret = array('errcode' => -1, 'command' => 'generate:bundle', 'message' => $result["message"] . $addmessage);
        }
        return $ret;
    }

    public function generateEntity($wbFile, $bundlePath)
    {
        $console = $this->apppaths->getConsole();
        $pannellocmd = "pannelloamministrazione:generateentities $wbFile $bundlePath";

        $scriptGenerator = $console . " " . $pannellocmd;

        $phpPath = OsFunctions::getPHPExecutableFromPath();
        $sepchr = OsFunctions::getSeparator();

        $command = 'cd ' . $this->apppaths->getRootPath() . $sepchr
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
        $srcPath = $this->apppaths->getSrcPath();
        $appPath = $this->apppaths->getAppPath();
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

        $resultcrud = $this->pammutils->runSymfonyCommand('doctrine:generate:crud', $crudparms);

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

    public function clearcache()
    {
        $cmdoutput = "";
        $envs = array("dev", "test", "prod");
        foreach ($envs as $env) {
            $cmdoutput = $cmdoutput . $this->clearcacheEnv($env);
        }

        return $cmdoutput;
    }

    public function clearcacheEnv($env)
    {
        $ret = $this->pammutils->clearcache($env);

        return $ret["errmsg"];
    }

    public function aggiornaSchemaDatabase()
    {
        $result = $this->pammutils->runSymfonyCommand('doctrine:schema:update', array('--force' => true));

        return $result;
    }
}
