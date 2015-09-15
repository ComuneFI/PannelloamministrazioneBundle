<?php

namespace Fi\PannelloAmministrazioneBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class DefaultController extends Controller {

    public function indexAction() {
        $finder = new Finder();
        $fs = new Filesystem();

        $projectDir = substr($this->get('kernel')->getRootDir(), 0, -4);
        $bundlelists = $this->container->getParameter('kernel.bundles');
        $bundles = array();
        foreach ($bundlelists as $bundle) {
            if (substr($bundle, 0, 2) === 'Fi') {
                $bundle = str_replace("\\", "/", $bundle);
                if ($fs->exists($projectDir . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . substr($bundle, 0, strripos($bundle, "/")))) {
                    $bundles[] = substr($bundle, 0, strripos($bundle, "/"));
                };
            }
        }
        $docDir = $projectDir . '/doc/';

        if ($fs->exists($docDir)) {
            $finder->in($docDir)->files()->name('*.mwb');
            foreach ($finder as $file) {
                $mwbs[] = $file->getBasename();
            }
        }

        if ($fs->exists($projectDir . "/.svn")) {
            $svn = true;
        } else {
            $svn = false;
        }

        if ($fs->exists($projectDir . "/.git")) {
            $git = true;
        } else {
            $git = false;
        }

        if (!self::isWindows()) {
            $delcmd = "rm -rf";
            $delfoldercmd = "rm -rf";
            $windows = false;
        } else {
            $delcmd = "del";
            $delfoldercmd = "rmdir /s";
            $windows = true;
        }

        $comandishell = array(
            "lockfile" => str_replace("\\", "\\\\", $delcmd . ' ' . $projectDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'running.run'),
            "composerlock" => str_replace("\\", "\\\\", $delcmd . ' ' . $projectDir . DIRECTORY_SEPARATOR . 'composer.lock'),
            "logsfiles" => str_replace("\\", "\\\\", $delcmd . ' ' . $projectDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . "*"),
            "cacheprodfiles" => str_replace("\\", "\\\\", $delcmd . ' ' . $projectDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . "prod" . DIRECTORY_SEPARATOR . "*"),
            "cachedevfiles" => str_replace("\\", "\\\\", $delcmd . ' ' . $projectDir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . "dev" . DIRECTORY_SEPARATOR . "*"),
        );

        return $this->render('FiPannelloAmministrazioneBundle:Default:index.html.twig', array("svn" => $svn, "git" => $git, "bundles" => $bundles, "mwbs" => $mwbs, "rootdir" => str_replace("\\", "\\\\", $projectDir), "comandishell" => $comandishell, "iswindows" => $windows)
        );
    }

    private function getOutput($fpOutupStream) {
        fseek($fpOutupStream, 0);
        $output = '';
        while (!feof($fpOutupStream)) {
            $output = $output . fread($fpOutupStream, 4096);
        }
        return $output;
    }

    private function executeCommand($application, $command, Array $options = array()) {
        $cmdoptions = array_merge(array('command' => $command), $options);

        $fp = tmpfile();
        $outputStream = new StreamOutput($fp);
        $returncode = $application->run(new ArrayInput($cmdoptions), $outputStream);
        $output = $this->getOutput($fp);
        fclose($fp);

        return Array("errcode" => ($returncode == 0 ? false : true), "command" => $cmdoptions['command'], "message" => $output);
    }

    public function aggiornaSchemaDatabaseAction() {

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);

            $application = new Application($this->get("kernel"));
            $application->setAutoExit(false);

            $result = $this->executeCommand($application, "doctrine:schema:update", array("--force" => true));

            $this->LockFile(false);

            return $this->render('FiPannelloAmministrazioneBundle:Default:outputcommand.html.twig', array("errcode" => $result["errcode"], "command" => $result["command"], "message" => $result["message"]));
        }
    }

    /* FORMS */

    public function generateFormAction(Request $request) {

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);

            $application = new Application($this->get("kernel"));
            $application->setAutoExit(false);
            $bundlename = $request->get("bundlename");
            $entityform = $request->get("entityform");

            $resultform = $this->executeCommand($application, "doctrine:generate:form", array("entity" => str_replace("/", "", $bundlename) . ":" . $entityform));

            $this->LockFile(false);
            return $this->render('FiPannelloAmministrazioneBundle:Default:outputcommand.html.twig', array("errcode" => $resultform["errcode"], "command" => $resultform["command"], "message" => $resultform["message"]));
        }
    }

    public function generateFormCrudAction(Request $request) {

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);
            $application = new Application($this->get("kernel"));
            $application->setAutoExit(false);
            $bundlename = $request->get("bundlename");
            $entityform = $request->get("entityform");


            $resultcrud = $this->executeCommand($application, "doctrine:generate:crud", array("--entity" => str_replace("/", "", $bundlename) . ":" . $entityform, "--route-prefix" => $entityform, "--with-write" => true, "--format" => "yml", "--overwrite" => false, "--no-interaction" => true));

            $this->LockFile(false);
            if ($resultcrud["errcode"] == 0) {

                $this->generateFormsTemplates($bundlename, $entityform);

                $this->generateFormsDefaultTableValues($entityform);
            }

            return $this->render('FiPannelloAmministrazioneBundle:Default:outputcommand.html.twig', array("errcode" => $resultcrud["errcode"], "command" => $resultcrud["command"], "message" => $resultcrud["message"]));
        }
    }

    private function generateFormsDefaultTableValues($entityform) {
        //Si inserisce il record di default nella tabella permessi
        $em = $this->container->get("doctrine")->getManager();
        $ruoloAmm = $em->getRepository('FiCoreBundle:ruoli')->findOneBy(array('is_superadmin' => true)); //SuperAdmin

        $newPermesso = new \Fi\CoreBundle\Entity\permessi();
        $newPermesso->setCrud("crud");
        $newPermesso->setModulo($entityform);
        $newPermesso->setRuoli($ruoloAmm);
        $em->persist($newPermesso);
        $em->flush();

        $tabelle = new \Fi\CoreBundle\Entity\tabelle();
        $tabelle->setNometabella($entityform);
        $em->persist($tabelle);
        $em->flush();
    }

    private function generateFormsTemplates($bundlename, $entityform) {
        $fs = new Filesystem();
        $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
        //Controller
        $controlleFile = $prjPath . "/src/" . $bundlename . "/Controller/" . $entityform . "Controller.php";
        $code = $this->getControllerCode(str_replace("/", "\\", $bundlename), $entityform);
        $fs->dumpFile($controlleFile, $code);

        //Routing
        $this->generateFormRouting($bundlename, $entityform);
        //Twig template (Crea i template per new edit show)
        $this->generateFormWiew($bundlename, $entityform, "edit");
        $this->generateFormWiew($bundlename, $entityform, "index");
        $this->generateFormWiew($bundlename, $entityform, "new");
    }

    private function getControllerCode($bundlename, $tabella) {

        $codeTemplate = <<<EOF
<?php
namespace [bundle]\Controller;

use Fi\CoreBundle\Controller\FiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fi\CoreBundle\Controller\griglia;
use [bundle]\Entity\[tabella];
use [bundle]\Form\[tabella]Type;


/**
* [tabella] controller.
*
*/

class [tabella]Controller extends FiController {

}
EOF;
        $codebundle = str_replace("[bundle]", $bundlename, $codeTemplate);
        $code = str_replace("[tabella]", $tabella, $codebundle);
        return $code;
    }

    private function getRoutingCode($bundlename, $tabella) {

        $codeTemplate = <<<EOF
[tabella]_container:
    path:  /
    defaults: { _controller: "[bundle]:[tabella]:index" }

[tabella]_new:
    path:  /new
    defaults: { _controller: "[bundle]:[tabella]:new" }

[tabella]_create:
    path:  /create
    defaults: { _controller: "[bundle]:[tabella]:create" }
    requirements: { methods: post }

[tabella]_edit:
    path:  /{id}/edit
    defaults: { _controller: "[bundle]:[tabella]:edit" }

[tabella]_update:
    path:  /{id}/update
    defaults: { _controller: "[bundle]:[tabella]:update" }
    requirements: { methods: post|put }

[tabella]_aggiorna:
    path:  /aggiorna
    defaults: { _controller: "[bundle]:[tabella]:aggiorna" }
    requirements: { methods: post|put }

[tabella]_delete:
    path:  /{id}/delete
    defaults: { _controller: "[bundle]:[tabella]:delete" }
    requirements: { methods: post|delete }

[tabella]_deletemultiple:
    path:  /delete
    defaults: { _controller: "[bundle]:[tabella]:delete" }
    requirements: { methods: post|delete }

[tabella]_griglia:
    path:  /griglia
    defaults: { _controller: "[bundle]:[tabella]:griglia" }
    requirements: { methods: get }
EOF;
        $codebundle = str_replace("[bundle]", $bundlename, $codeTemplate);
        $code = str_replace("[tabella]", $tabella, $codebundle);
        return $code;
    }

    private function generateFormRouting($bundlename, $entityform) {
        //Routing del form
        $fs = new Filesystem();
        $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);

        $routingFile = $prjPath . "/src/" . $bundlename . "/Resources/config/routing/" . $entityform . ".yml";
        $code = $this->getRoutingCode(str_replace("/", "", $bundlename), $entityform);
        $fs->dumpFile($routingFile, $code);

        //Fixed: Adesso questa parte la fa da solo symfony (05/2015)
        /*
          $dest = $prjPath . "/src/" . $bundlename . "/Resources/config/routing.yml";

          $routingContext = "\n" . str_replace("/", "", $bundlename) . '_' . $entityform . ': ' . "\n" .
          '  resource: "@' . str_replace("/", "", $bundlename) . '/Resources/config/routing/' . strtolower($entityform) . '.yml"' . "\n" .
          '  prefix: /' . $entityform . "\n";

          //Si fa l'append nel file routing del bundle per aggiungerci le rotte della tabella che stiamo gestendo
          $fh = fopen($dest, 'a');
          fwrite($fh, $routingContext);
          fclose($fh);

         */
    }

    private function generateFormWiew($bundlename, $entityform, $view) {
        $fs = new Filesystem();
        $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
        $source = $prjPath . "/vendor/fi/fifreecorebundle/FiTemplate/views/" . $view . ".html.twig";
        $dest = $prjPath . "/src/" . $bundlename . "/Resources/views/" . $entityform . "/" . $view . ".html.twig";
        $fs->copy($source, $dest, true);
    }

    /* ENTITIES */

    public function generateEntityAction(Request $request) {
        $fs = new Filesystem();
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
            $wbFile = $prjPath . DIRECTORY_SEPARATOR . "doc" . DIRECTORY_SEPARATOR . $request->get("file");
            $bundlePath = $request->get("bundle");
            if (!$fs->exists($wbFile)) {
                return new Response("Nella cartella 'doc' non è presente il file " . $wbFile . "!");
            }

            $scriptGenerator = $prjPath . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fi" . DIRECTORY_SEPARATOR . "schemaexporterbundle" . DIRECTORY_SEPARATOR . "cli" . DIRECTORY_SEPARATOR . "export.php";

            $destinationPath = $prjPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR;

            if (!$fs->exists($scriptGenerator)) {
                return new Response("Non è presente il file export.php del bundle SchemaExporterBundle!");
            }

            if (!$fs->exists($destinationPath)) {
                return new Response("Non esiste la cartella per l'esportazione " . $destinationPath . ", controllare il nome del Bundle!");
            }

            $this->LockFile(true);

            $destinationPath = $destinationPath . "doctrine" . DIRECTORY_SEPARATOR;
            $exportJson = $prjPath . "/app/tmp/export.json";

            if ($fs->exists($exportJson)) {
                $fs->remove($exportJson);
            }
            $destinationPathEscaped = str_replace("\\", "/", $destinationPath);
            $destinationPathEscaped = str_replace("/", "\/", $destinationPathEscaped);
            $bundlePathEscaped = str_replace("/", "\\", $bundlePath);
            $bundlePathEscaped = str_replace("\\", "\\\\", $bundlePathEscaped);

            $str = file_get_contents($prjPath . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fi/fifreecorebundle/FiTemplate/config/export.json");
            $str = str_replace("[bundle]", str_replace("/", "", $bundlePathEscaped), $str);
            $str = str_replace("[dir]", $destinationPathEscaped, $str);
            file_put_contents($exportJson, $str);

            if (self::isWindows()) {
                $phpPath = self::getPHPExecutableFromPath();
            } else {
                $phpPath = "/usr/bin/php";
            }
            $pathsrc = $this->get('kernel')->getRootDir();
            $sepchr = self::getSeparator();

            $command = "cd " . substr($pathsrc, 0, -4) . $sepchr
                    . $phpPath . " " . $scriptGenerator . " --export=doctrine2-yaml --config=" . $exportJson . " " . $wbFile . " " . $destinationPathEscaped;

            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            if ($fs->exists($exportJson)) {
                $fs->remove($exportJson);
            }

            $this->LockFile(false);

            if (!$process->isSuccessful()) {
                return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
            }
            return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
        }
    }

    public function generateEntityClassAction(Request $request) {
        set_time_limit(0);
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $bundleName = $request->get("bundlename");

            $this->LockFile(true);

            $application = new Application($this->get("kernel"));
            $application->setAutoExit(false);

            $result = $this->executeCommand($application, "doctrine:generate:entities", array("--no-backup" => true, "name" => str_replace("/", "", $bundleName)));

            $this->LockFile(false);

            return $this->render('FiPannelloAmministrazioneBundle:Default:outputcommand.html.twig', array("errcode" => $result["errcode"], "command" => $result["command"], "message" => $result["message"]));
        }
    }

    /* BUNDLE */

    public function generateBundleAction(Request $request) {
        $fs = new Filesystem();
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);
            $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
            $bundleName = $request->get("bundlename");

            $bundlePath = $prjPath . "/src/" . $bundleName;
            if ($fs->exists($bundlePath)) {
                echo "Il bundle esiste gia' in $bundlePath";
            } else {
                $application = new Application($this->get("kernel"));
                $application->setAutoExit(false);

                $result = $this->executeCommand($application, "generate:bundle", array("--namespace" => $bundleName, "--dir" => $prjPath . "/src/", "--format" => "yml", "--no-interaction" => true));
                $bundlePath = $prjPath . "/src/" . $bundleName;
                $this->showBundleGenerationMessage($bundlePath, $result["message"]);
            }
            $this->LockFile(false);
            //Uso exit perchè la render avendo creato un nuovo bundle schianta perchè non è caricato nel kernel il nuovo bundle ancora
            exit;
            //return $this->render('FiPannelloAmministrazioneBundle:Default:outputcommand.html.twig', array("errcode" => $result["errcode"], "command" => $result["command"], "message" => $result["message"]));
        }
    }

    private function showBundleGenerationMessage($bundlePath, $message) {
        $fs = new Filesystem();
        if ($fs->exists($bundlePath)) {
            echo str_replace("\n", "<br/>", $message);
            echo "Per abilitare il nuovo bundle nel kernel aggiornare la pagina";
            echo '<script type="text/javascript">alert("Per abilitare il nuovo bundle nel kernel aggiornare la pagina");</script>';
        } else {
            echo str_replace("\n", "<br/>", $message);
            echo "Non e' stato creato il bundle in $bundlePath";
        }
    }

    /* SVN */

    public function getSvnAction() {
        set_time_limit(0);
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            if (!self::isWindows()) {
                $this->LockFile(true);
                $sepchr = self::getSeparator();
                //Si fa la substr per togliere app/ perchè getRootDir() ci restituisce appunto .../app/
                $command = "cd " . substr($this->get('kernel')->getRootDir(), 0, -4) . $sepchr . "svn update";
                $process = new Process($command);
                $process->setTimeout(60 * 100);
                $process->run();

                $this->LockFile(false);
                if (!$process->isSuccessful()) {
                    return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
                }
                return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
            } else {
                return new Response("Non previsto in ambiente windows!");
            }
        }
    }

    /* GIT */

    public function getGitAction() {
        set_time_limit(0);
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);
            $sepchr = self::getSeparator();
            //Si fa la substr per togliere app/ perchè getRootDir() ci restituisce appunto .../app/
            $command = "cd " . substr($this->get('kernel')->getRootDir(), 0, -4) . $sepchr . "git pull";
            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            $this->LockFile(false);
            if (!$process->isSuccessful()) {
                return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
            }
            return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
        }
    }

    /* CLEAR CACHE */

    public function clearCacheAction(Request $request) {
        set_time_limit(0);
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            if (!self::isWindows()) {
                $this->LockFile(true);
                if (!self::isWindows()) {
                    $phpPath = "/usr/bin/php";
                } else {
                    $phpPath = $this->getPHPExecutableFromPath();
                }
                $pathsrc = $this->get('kernel')->getRootDir();
                $sepchr = self::getSeparator();

                $commanddev = "cd " . $pathsrc . $sepchr
                        . $phpPath . " console cache:clear";

                $processdev = new Process($commanddev);
                $processdev->setTimeout(60 * 100);
                $processdev->run();
                $cmdoutputdev = ($processdev->isSuccessful()) ? $processdev->getOutput() : $processdev->getErrorOutput();
                $commandprod = "cd " . $pathsrc . $sepchr
                        . $phpPath . " console cache:clear --env=prod";

                $processprod = new Process($commandprod);
                $processprod->setTimeout(60 * 100);
                $processprod->run();
                $cmdoutputprod = ($processprod->isSuccessful()) ? $processprod->getOutput() : $processprod->getErrorOutput();
                $this->LockFile(false);

                return new Response($commanddev . "<br/>" . $cmdoutputdev . "<br/><br/>" . $commandprod . "<br/>" . $cmdoutputprod);
            } else {
                return new Response("Non previsto in ambiente windows!");
            }
        }
    }

    /* CLEAR CACHE */

    public function symfonyCommandAction(Request $request) {
        set_time_limit(0);
        $comando = $request->get("symfonycommand");
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            if (!self::isWindows()) {
                $this->LockFile(true);
                //$phpPath = self::getPHPExecutableFromPath();
                $phpPath = "/usr/bin/php";
                $pathsrc = $this->get('kernel')->getRootDir();
                $sepchr = self::getSeparator();

                $command = "cd " . $pathsrc . $sepchr
                        . $phpPath . " console " . $comando;

                $process = new Process($command);
                $process->setTimeout(60 * 100);
                $process->run();

                $this->LockFile(false);
                if (!$process->isSuccessful()) {
                    return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
                }
                return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
            } else {
                return new Response("Non previsto in ambiente windows!");
            }
        }
    }

    public function unixCommandAction(Request $request) {
        set_time_limit(0);
        $command = $request->get("unixcommand");
        if (!self::isWindows()) {
            $lockdelcmd = "rm -rf ";
        } else {
            $lockdelcmd = "del ";
        }
        //Se viene lanciato il comando per cancellare il file di lock su bypassa tutto e si lancia
        $filelock = str_replace("\\", "\\\\", $this->getFileLock());
        if ($command == $lockdelcmd . $filelock) {
            $fs = new Filesystem();
            if ((!($fs->exists($filelock)))) {
                return new Response('Non esiste il file di lock: <i style="color: white;">' . $filelock . '</i><br/>');
            } else {
                //Sblocca pannello di controllo da lock
                $process = new Process($command);
                $process->setTimeout(60 * 100);
                $process->run();

                // eseguito deopo la fine del comando
                if (!$process->isSuccessful()) {
                    return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
                }
                return new Response('File di lock cancellato');
            }
        }

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);
            //$phpPath = self::getPHPExecutableFromPath();
            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            $this->LockFile(false);
            // eseguito deopo la fine del comando
            if (!$process->isSuccessful()) {
                return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
            }
            return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
        }
    }

    public function phpunittestAction(Request $request) {
        set_time_limit(0);

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            if (!self::isWindows()) {
                $this->LockFile(true);
                //$phpPath = self::getPHPExecutableFromPath();
                $sepchr = self::getSeparator();
                $phpPath = "/usr/bin/php";

                $command = "cd " . substr($this->get('kernel')->getRootDir(), 0, -4) . $sepchr . $phpPath . " " . "bin" . DIRECTORY_SEPARATOR . "phpunit -c app";
                $process = new Process($command);
                $process->run();

                $this->LockFile(false);
                // eseguito deopo la fine del comando
                /* if (!$process->isSuccessful()) {
                  return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
                  } */
                return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
            } else {
                return new Response("Non previsto in ambiente windows!");
            }
        }
    }

    static function getPHPExecutableFromPath() {
        $phpPath = exec("which php");
        if (file_exists($phpPath)) {
            return $phpPath;
        }
        $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        foreach ($paths as $path) {
            $php_executable = $path . DIRECTORY_SEPARATOR . "php" . (isset($_SERVER["WINDIR"]) ? ".exe" : "");
            if (file_exists($php_executable) && is_file($php_executable)) {
                return $php_executable;
            }
        }
        echo "Php non trovato";
        return FALSE; // not found
    }

    static function isWindows() {
        if (PHP_OS == "WINNT") {
            return true;
        } else {
            return false;
        }
    }

    static function getSeparator() {
        if (self::isWindows()) {
            return "&";
        } else {
            return ";";
        }
    }

    public function getFileLock() {
        return $this->get('kernel')->getRootDir() . DIRECTORY_SEPARATOR . "tmp" . DIRECTORY_SEPARATOR . "running.run";
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
        return new Response("<h2 style='color: orange;'>E' già in esecuzione un comando, riprova tra qualche secondo!</h2>");
    }

    public function forceCleanLockFile() {
        $this->LockFile(false);
    }

}
