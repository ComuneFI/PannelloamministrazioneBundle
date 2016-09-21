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
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Doctrine\Common\Inflector\Inflector;
use MwbExporter\Model\Table;

class PannelloAmministrazioneController extends Controller {

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

        $mwbs = array();

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

        if (!OsFunctions::isWindows()) {
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

        return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:index.html.twig', array("svn" => $svn, "git" => $git, "bundles" => $bundles, "mwbs" => $mwbs, "rootdir" => str_replace("\\", "\\\\", $projectDir), "comandishell" => $comandishell, "iswindows" => $windows)
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

            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', array("errcode" => $result["errcode"], "command" => $result["command"], "message" => $result["message"]));
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
            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', array("errcode" => $resultform["errcode"], "command" => $resultform["command"], "message" => $resultform["message"]));
        }
    }

    public function generateFormCrudAction(Request $request) {
        /* @var $fs \Symfony\Component\Filesystem\Filesystem */
        $fs = new Filesystem();

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $bundlename = $request->get("bundlename");
            $entityform = $request->get("entityform");
//$entityform = "attolegale";

            $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
            $srcPath = $prjPath . DIRECTORY_SEPARATOR . "src";
            $appPath = $prjPath . DIRECTORY_SEPARATOR . "app";
            if (!is_writable($appPath)) {
                return new Response($appPath . " non scrivibile");
            }
            $formPath = $srcPath . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR . "Form" . DIRECTORY_SEPARATOR . $entityform . "Type.php";
            $controllerPath = $srcPath . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR . "Controller" . DIRECTORY_SEPARATOR . $entityform . "Controller.php";
            if ($fs->exists($formPath)) {
                return new Response($formPath . " esistente");
            }

            if ($fs->exists($controllerPath)) {
                return new Response($controllerPath . " esistente");
            }


            $viewPath = $appPath . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . strtolower($entityform);
            $viewPathSrc = $srcPath . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . $entityform;
            if ($fs->exists($viewPathSrc)) {
                return new Response($viewPathSrc . " esistente");
            }

            $this->LockFile(true);
            $application = new Application($this->get("kernel"));
            $application->setAutoExit(false);

            $resultcrud = $this->executeCommand($application, "doctrine:generate:crud", array("--entity" => str_replace("/", "", $bundlename) . ":" . $entityform, "--route-prefix" => $entityform, "--with-write" => true, "--format" => "yml", "--overwrite" => false, "--no-interaction" => true));

            $this->LockFile(false);
            if ($resultcrud["errcode"] == 0) {

                $fs->rename($viewPath, $viewPathSrc);

                $retmsg = $this->generateFormsTemplates($bundlename, $entityform);

                $this->generateFormsDefaultTableValues($entityform);

                $appviews = $appPath . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "views";
                if ($fs->exists($appviews)) {
                    $finder = new Finder();
                    $ret = $finder->files()->in($appviews);
                    if (count($ret) == 0) {
                        $fs->remove($appviews);
                    }
                }
                $resourcesviews = $appPath . DIRECTORY_SEPARATOR . "Resources";
                if ($fs->exists($resourcesviews)) {
                    $finder = new Finder();
                    $ret = $finder->files()->in($resourcesviews);
                    if (count($ret) == 0) {
                        $fs->remove($resourcesviews);
                    }
                }
            } else {
                $retmsg = $resultcrud["message"];
            }

            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', array("errcode" => $resultcrud["errcode"], "command" => $resultcrud["command"], "message" => $resultcrud["message"] . $retmsg));
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
        $retmsg = $this->generateFormRouting($bundlename, $entityform);
//Twig template (Crea i template per new edit show)
        $this->generateFormWiew($bundlename, $entityform, "edit");
        $this->generateFormWiew($bundlename, $entityform, "index");
        $this->generateFormWiew($bundlename, $entityform, "new");

        return $retmsg;
    }

    private function getControllerCode($bundlename, $tabella) {

        $codeTemplate = <<<EOF
<?php
namespace [bundle]\Controller;

use Fi\CoreBundle\Controller\FiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fi\CoreBundle\Controller\Griglia;
use Fi\CoreBundle\Controller\gestionepermessiController;
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
    defaults: { _controller: "[bundle]:[tabella]:Griglia" }
    requirements: { methods: get|post }
EOF;
        $codebundle = str_replace("[bundle]", $bundlename, $codeTemplate);
        $code = str_replace("[tabella]", $tabella, $codebundle);
        return $code;
    }

    private function generateFormRouting($bundlename, $entityform) {
//Routing del form
        $fs = new Filesystem();
        $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);

        $routingFile = $prjPath . "/src/" . $bundlename . "/Resources/config/routing/" . strtolower($entityform) . ".yml";

        $code = $this->getRoutingCode(str_replace("/", "", $bundlename), $entityform);
        $fs->dumpFile($routingFile, $code);

//Fixed: Adesso questa parte la fa da solo symfony (05/2015)
//Refixed dalla versione 2.8 non lo fa più (04/2016)

        $dest = $prjPath . "/src/" . $bundlename . "/Resources/config/routing.yml";

        $routingContext = "\n" . str_replace("/", "", $bundlename) . '_' . $entityform . ': ' . "\n" .
                '  resource: "@' . str_replace("/", "", $bundlename) . '/Resources/config/routing/' . strtolower($entityform) . '.yml"' . "\n" .
                '  prefix: /' . $entityform . "\n";

        //Si fa l'append nel file routing del bundle per aggiungerci le rotte della tabella che stiamo gestendo
        $fh = fopen($dest, 'a');
        fwrite($fh, $routingContext);
        fclose($fh);
        $retmsg = "Routing " . $dest . " generato automaticamente da pannelloammonistrazionebundle\n* * * * FARE CLEAR CACHE !!!!! * * * *";
        return $retmsg;
    }

    private function generateFormWiew($bundlename, $entityform, $view) {
        $fs = new Filesystem();
        $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
        $source = $prjPath . "/vendor/fi/fifreecorebundle/src/FiTemplate/views/" . $view . ".html.twig";
        $dest = $prjPath . "/src/" . $bundlename . "/Resources/views/" . $entityform . "/" . $view . ".html.twig";
        $fs->copy($source, $dest, true);
    }

    /* ENTITIES */

    public function generateEntityAction(Request $request) {
        $fs = new Filesystem();
        $finder = new Finder();

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
            $wbFile = $prjPath . DIRECTORY_SEPARATOR . "doc" . DIRECTORY_SEPARATOR . $request->get("file");
            $bundlePath = $request->get("bundle");
            if (!$fs->exists($wbFile)) {
                return new Response("Nella cartella 'doc' non è presente il file " . $wbFile . "!");
            }

            // Questo codice per versioni che usano un symfony 2 o 3
            if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
                $scriptGenerator = $prjPath . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "mysql-workbench-schema-export";
            } else {
                $scriptGenerator = $prjPath . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "mysql-workbench-schema-export";
            }

            $destinationPath = $prjPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR;
            $viewsPath = $prjPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . "Resources" . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR;
            $entityPath = $prjPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . "Entity" . DIRECTORY_SEPARATOR;
            $formPath = $prjPath . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . "Form" . DIRECTORY_SEPARATOR;

            if (!$fs->exists($scriptGenerator)) {
                return new Response("Non è presente il file export.php del bundle SchemaExporterBundle!");
            }
            $fs->mkdir($destinationPath);
            $fs->mkdir($destinationPath . DIRECTORY_SEPARATOR . "doctrine");
            $fs->mkdir($entityPath);
            $fs->mkdir($formPath);
            $fs->mkdir($viewsPath);
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

            $str = file_get_contents($prjPath . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fi/fifreecorebundle/src/FiTemplate/config/export.json");
            $str = str_replace("[bundle]", str_replace("/", "", $bundlePathEscaped), $str);
            $str = str_replace("[dir]", $destinationPathEscaped, $str);
            file_put_contents($exportJson, $str);

            if (OsFunctions::isWindows()) {
                $phpPath = OsFunctions::getPHPExecutableFromPath();
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

            $pathdoctrineyml = $destinationPath;

            //Si converte il nome file tabella.orm.yml se ha undercore
            $finder->in($pathdoctrineyml)->files()->name('*_*');
            $table = new Table();

            foreach ($finder as $file) {
                $oldfilename = $file->getPathName();
                $newfilename = $pathdoctrineyml . DIRECTORY_SEPARATOR . $table->beautify($file->getFileName());
                $fs->rename($oldfilename, $newfilename, true);
            }

            //Si cercano file con nomi errati
            $finderwrong = new Finder();
            $finderwrong->in($pathdoctrineyml)->files()->name('*_*');
            $wrongfilename = array();
            if (count($finderwrong) > 0) {
                foreach ($finderwrong as $file) {
                    $wrongfilename[] = $file->getFileName();
                    $fs->remove($pathdoctrineyml . DIRECTORY_SEPARATOR . $file->getFileName());
                }
            }
            $finderwrongcapitalize = new Finder();
            $finderwrongcapitalize->in($pathdoctrineyml)->files()->name('*.yml');
            foreach ($finderwrongcapitalize as $file) {
                if (!ctype_upper(substr($file->getFileName(), 0, 1))) {
                    $wrongfilename[] = $file->getFileName();
                    $fs->remove($pathdoctrineyml . DIRECTORY_SEPARATOR . $file->getFileName());
                }
            }

            $this->LockFile(false);

            if (count($wrongfilename) > 0) {
                return new Response('<i style="color: red;">Ci sono tabelle nel file ' . $wbFile . ' con nomi non consentiti:<br/>' . implode("<br/>", $wrongfilename) . '</i><br/>I nomi tabella devono essere <ul><li>con la prima lettera maiuscola</li><li>underscore ammesso</li><li>doppio underscore non ammesso</li>');
            }

            if (!$process->isSuccessful()) {
                return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
            }
            return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
        }
    }

    public function generateEntityClassAction(Request $request) {
        set_time_limit(0);
        $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $bundleName = $request->get("bundlename");

            $this->LockFile(true);

            $application = new Application($this->get("kernel"));
            $application->setAutoExit(false);

            $result = $this->executeCommand($application, "doctrine:generate:entities", array("--no-backup" => true, "name" => str_replace("/", "", $bundleName)));

            $this->LockFile(false);

            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', array("errcode" => $result["errcode"], "command" => $result["command"], "message" => $result["message"]));
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
//return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', array("errcode" => $result["errcode"], "command" => $result["command"], "message" => $result["message"]));
        }
    }

    private function showBundleGenerationMessage($bundlePath, $message) {
        $fs = new Filesystem();
        if ($fs->exists($bundlePath)) {
            echo str_replace("\n", "<br/>", $message);
            echo "Per abilitare il nuovo bundle nel kernel controllare che sia presente in app/AppKernel.php e aggiornare la pagina";
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
            if (!OsFunctions::isWindows()) {
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
            $this->LockFile(true);
            if (!OsFunctions::isWindows()) {
                $phpPath = "/usr/bin/php";
            } else {
                $phpPath = OsFunctions::getPHPExecutableFromPath();
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
                    . $phpPath . " console cache:clear --env=prod --no-debug";

            $processprod = new Process($commandprod);
            $processprod->setTimeout(60 * 100);
            $processprod->run();
            $cmdoutputprod = ($processprod->isSuccessful()) ? $processprod->getOutput() : $processprod->getErrorOutput();
            $this->LockFile(false);
            echo $commanddev . "<br/>" . $cmdoutputdev . "<br/><br/>" . $commandprod . "<br/>" . $cmdoutputprod;
//Uso exit perchè new response avendo cancellato la cache schianta non avendo più a disposizione i file
            exit;
//return new Response($commanddev . "<br/>" . $cmdoutputdev . "<br/><br/>" . $commandprod . "<br/>" . $cmdoutputprod);
        }
    }

    /* CLEAR CACHE */

    public function symfonyCommandAction(Request $request) {
        set_time_limit(0);
        $comando = $request->get("symfonycommand");
        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {

            $this->LockFile(true);

            if (!OsFunctions::isWindows()) {
                $phpPath = "/usr/bin/php";
            } else {
                $phpPath = OsFunctions::getPHPExecutableFromPath();
            }
            $pathsrc = $this->get('kernel')->getRootDir();
            $sepchr = self::getSeparator();

            $command = "cd " . $pathsrc . $sepchr
                    . $phpPath . " console " . $comando;

            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            $this->LockFile(false);
            if (!$process->isSuccessful()) {
                return new Response('Errore nel comando: <i style="color: white;">' . str_replace(";", "<br/>", str_replace("&&", "<br/>", $command)) . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
            }
            return new Response('<pre>Eseguito comando:<br/><br/><i style="color: white;">' . str_replace(";", "<br/>", str_replace("&&", "<br/>", $command)) . '</i><br/><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
        }
    }

    public function unixCommandAction(Request $request) {
        set_time_limit(0);
        $command = $request->get("unixcommand");
        if (!OsFunctions::isWindows()) {
            $lockdelcmd = "rm -rf ";
        } else {
            $lockdelcmd = "del ";
        }
//Se viene lanciato il comando per cancellare il file di lock su bypassa tutto e si lancia
        $filelock = str_replace("\\", "\\\\", $this->getFileLock());
        if (str_replace("\\\\", "/", $command) == str_replace("\\\\", "\\", $lockdelcmd . $filelock)) {
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
                    return new Response('Errore nel comando: <i style="color: white;">' . str_replace(";", "<br/>", str_replace("&&", "<br/>", $command)) . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
                }
                return new Response('File di lock cancellato');
            }
        }

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            $this->LockFile(true);
//$phpPath = OsFunctions::getPHPExecutableFromPath();
            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            $this->LockFile(false);
// eseguito deopo la fine del comando
            if (!$process->isSuccessful()) {
                echo 'Errore nel comando: <i style="color: white;">' . str_replace(";", "<br/>", str_replace("&&", "<br/>", $command)) . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>';
//Uso exit perchè new response avendo cancellato la cache schianta non avendo più a disposizione i file
                exit;
//return new Response('Errore nel comando: <i style="color: white;">' . $command . '</i><br/><i style="color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>');
            }
            echo '<pre>Eseguito comando:<br/><i style="color: white;"><br/>' . str_replace(";", "<br/>", str_replace("&&", "<br/>", $command)) . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>";
//Uso exit perchè new response avendo cancellato la cache schianta non avendo più a disposizione i file
            exit;
//return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");
        }
    }

    public function phpunittestAction(Request $request) {
        set_time_limit(0);

        if ($this->isLockedFile()) {
            return $this->LockedFunctionMessage();
        } else {
            if (!OsFunctions::isWindows()) {
                $this->LockFile(true);
                //$phpPath = OsFunctions::getPHPExecutableFromPath();
                $sepchr = self::getSeparator();
                $phpPath = "/usr/bin/php";

                // Questo codice per versioni che usano un symfony 2 o 3
                if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
                    $command = "cd " . substr($this->get('kernel')->getRootDir(), 0, -4) . $sepchr . $phpPath . " " . "vendor" . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "phpunit";
                } else {
                    $command = "cd " . substr($this->get('kernel')->getRootDir(), 0, -4) . $sepchr . $phpPath . " " . "bin" . DIRECTORY_SEPARATOR . "phpunit -c app";
                }

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

    static function getSeparator() {
        if (OsFunctions::isWindows()) {
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