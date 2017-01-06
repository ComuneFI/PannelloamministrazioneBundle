<?php

namespace Fi\PannelloAmministrazioneBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\ProjectPath;
use MwbExporter\Model\Table;

class GenerateentitiesCommand extends ContainerAwareCommand
{

    protected $apppaths;

    protected function configure()
    {
        $this
                ->setName('pannelloamministrazione:generateentities')
                ->setDescription('Genera le entities partendo da un modello workbeanch mwb')
                ->setHelp('Genera le entities partendo da un modello workbeanch mwb, <br/>fifree.mwb Fi/CoreBundle default [--schemaupdate]<br/>')
                ->addArgument('mwbfile', InputArgument::REQUIRED, 'Nome file mwb, fifree.mwb')
                ->addArgument('bundlename', InputArgument::REQUIRED, 'Nome del bundle, Fi/CoreBundle')
                ->addArgument('em', InputArgument::OPTIONAL, 'Entity manager, default = default')
                ->addOption('schemaupdate', null, InputOption::VALUE_NONE, 'Se settato fa anche lo schema update sul db');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        $this->apppaths = new ProjectPath($this->getContainer());
        $bundlename = $input->getArgument('bundlename');
        $mwbfile = $input->getArgument('mwbfile');
        $schemaupdate = false;

        if (!$input->getArgument('em')) {
            $emdest = 'default';
        } else {
            $emdest = $input->getArgument('em');
        }

        if ($input->getOption('schemaupdate')) {
            $schemaupdate = true;
        }

        $wbFile = $this->apppaths->getDocPath() . DIRECTORY_SEPARATOR . $mwbfile;
        $checkprerequisiti = $this->checkprerequisiti($bundlename, $mwbfile, $output);

        if ($checkprerequisiti < 0) {
            return -1;
        }

        $destinationPath = $this->getDestinationPath($bundlename);

        $command = $this->getExportJsonCommand($bundlename, $wbFile);

        $schemaupdateresult = $this->exportschema($command, $output);
        if ($schemaupdateresult < 0) {
            return 1;
        }

        $this->removeExportJsonFile();

        $tablecheck = $this->checktables($destinationPath, $wbFile, $output);

        if ($tablecheck < 0) {
            return 1;
        }

        $output->writeln('<info>Entities yml create</info>');
        $this->clearCache($output);

        $generatecheck = $this->generateentities($bundlename, $emdest, $schemaupdate, $output);
        if ($generatecheck < 0) {
            return 1;
        }

        return 0;
    }

    private function getDestinationPath($bundlePath)
    {
        return $this->apppaths->getSrcPath() . DIRECTORY_SEPARATOR .
                $bundlePath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR .
                'config' . DIRECTORY_SEPARATOR . 'doctrine' . DIRECTORY_SEPARATOR;
    }

    private function checkprerequisiti($bundlename, $mwbfile, $output)
    {
        $fs = new Filesystem();

        $wbFile = $this->apppaths->getDocPath() . DIRECTORY_SEPARATOR . $mwbfile;
        $bundlePath = $this->apppaths->getSrcPath() . DIRECTORY_SEPARATOR . $bundlename;

        $viewsPath = $bundlePath .
                DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        $entityPath = $bundlePath .
                DIRECTORY_SEPARATOR . 'Entity' . DIRECTORY_SEPARATOR;
        $formPath = $bundlePath .
                DIRECTORY_SEPARATOR . 'Form' . DIRECTORY_SEPARATOR;

        $scriptGenerator = $this->getScriptGenerator();

        $destinationPath = $this->getDestinationPath($bundlename);
        $output->writeln('Creazione entities yml in ' . $destinationPath . ' da file ' . $mwbfile);
        $destinationPath = $destinationPath . 'doctrine' . DIRECTORY_SEPARATOR;

        if (!$fs->exists($bundlePath)) {
            $output->writeln('<error>Non esiste la cartella del bundle ' . $bundlePath . '</error>');

            return -1;
        }

        /* Creazione cartelle se non esistono nel bundle per l'esportazione */
        $fs->mkdir($destinationPath);
        $fs->mkdir($entityPath);
        $fs->mkdir($formPath);
        $fs->mkdir($viewsPath);

        if (!$fs->exists($wbFile)) {
            $output->writeln("<error>Nella cartella 'doc' non è presente il file " . $mwbfile . '!');

            return -1;
        }

        if (!$fs->exists($scriptGenerator)) {
            $output->writeln('<error>Non è presente il comando ' . $scriptGenerator . ' per esportare il modello!</error>');

            return -1;
        }
        if (!$fs->exists($destinationPath)) {
            $output->writeln("<error>Non esiste la cartella per l'esportazione " . $destinationPath . ', controllare il nome del Bundle!</error>');

            return -1;
        }

        return 0;
    }

    private function getExportJsonCommand($bundlePath, $wbFile)
    {
        $exportJson = $this->getExportJsonFile();
        $scriptGenerator = $this->getScriptGenerator();
        $destinationPathEscaped = str_replace('/', "\/", str_replace('\\', '/', $this->getDestinationPath($bundlePath)));
        $bundlePathEscaped = str_replace('\\', '\\\\', str_replace('/', '\\', $bundlePath));
        $jsonfile = $this->apppaths->getProjectPath() . DIRECTORY_SEPARATOR .
                'vendor' . DIRECTORY_SEPARATOR . 'fi' . DIRECTORY_SEPARATOR . 'pannelloamministrazionebundle' . DIRECTORY_SEPARATOR .
                'src' . DIRECTORY_SEPARATOR . 'FiTemplate' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'export.json';
        if (!file_exists($jsonfile)) {
            $jsonfile = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR .
                    'FiTemplate' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'export.json';
        }
        $exportjsonfile = file_get_contents($jsonfile);

        $bundlejson = str_replace('[bundle]', str_replace('/', '', $bundlePathEscaped), $exportjsonfile);
        $exportjsonreplaced = str_replace('[dir]', $destinationPathEscaped, $bundlejson);
        file_put_contents($exportJson, $exportjsonreplaced);
        $sepchr = self::getSeparator();
        if (OsFunctions::isWindows()) {
            $command = 'cd ' . $this->apppaths->getRootPath() . $sepchr
                    . $scriptGenerator . '.bat --export=doctrine2-yaml --config=' .
                    $exportJson . ' ' . $wbFile . ' ' . $destinationPathEscaped;
        } else {
            $phpPath = '/usr/bin/php';
            $command = 'cd ' . $this->apppaths->getRootPath() . $sepchr
                    . $phpPath . ' ' . $scriptGenerator . ' --export=doctrine2-yaml --config=' .
                    $exportJson . ' ' . $wbFile . ' ' . $destinationPathEscaped;
        }

        return $command;
    }

    private function getExportJsonFile()
    {
        $fs = new Filesystem();
        $cachedir = $this->apppaths->getCachePath();
        $exportJson = $cachedir . DIRECTORY_SEPARATOR . 'export.json';
        if ($fs->exists($exportJson)) {
            $fs->remove($exportJson);
        }

        return $exportJson;
    }

    private function removeExportJsonFile()
    {
        $this->getExportJsonFile();

        return true;
    }

    private function exportschema($command, $output)
    {
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ');

            return -1;
        }

        return 0;
    }

    private function clearcache($output)
    {
        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = '/usr/bin/php';
        }
        $command = $phpPath . ' ' . $this->apppaths->getConsole() . ' cache:clear '
                . '--env=' . $this->getContainer()->get('kernel')->getEnvironment();
        
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ');

            return -1;
        }

        return 0;
    }

    private function getScriptGenerator()
    {
        $scriptGenerator = "";
        if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
            $scriptGenerator = $this->apppaths->getVendorBinPath() . DIRECTORY_SEPARATOR . 'mysql-workbench-schema-export';
        } else {
            try {
                $scriptGenerator = $this->apppaths->getBinPath() . DIRECTORY_SEPARATOR . 'mysql-workbench-schema-export';
            } catch (\Exception $exc) {
                //echo $exc->getTraceAsString();
                if (!file_exists($scriptGenerator)) {
                    $scriptGenerator = $this->apppaths->getVendorBinPath() . DIRECTORY_SEPARATOR . 'mysql-workbench-schema-export';
                }
            }
        }
        if (!file_exists($scriptGenerator)) {
            $scriptGenerator = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
                    'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql-workbench-schema-export';
        }
        if (!$scriptGenerator) {
            throw new \Exception("mysql-workbench-schema-export non trovato", -100);
        }
        return $scriptGenerator;
    }

    private function generateentities($bundlename, $emdest, $schemaupdate, $output)
    {
        /* GENERATE ENTITIES */
        $output->writeln('Creazione entities class per il bundle ' . str_replace('/', '', $bundlename));
        //$application = new Application($this->getContainer()->get('kernel'));
        //$application->setAutoExit(false);

        $console = $this->apppaths->getConsole();
        $scriptGenerator = $console . ' doctrine:generate:entities';
        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = '/usr/bin/php';
        }

        $command = $phpPath . ' ' . $scriptGenerator . ' --no-backup ' . str_replace('/', '', $bundlename)
                . ' --env=' . $this->getContainer()->get('kernel')->getEnvironment();
        /* @var $process \Symfony\Component\Process\Process */
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ');

            return -1;
        } else {
            $output->writeln($process->getOutput());
        }

        /* $command = $this->getApplication()->find('doctrine:generate:entities');
          $inputdge = new ArrayInput(array('--no-backup' => true, 'name' => str_replace('/', '', $bundlename)));
          $command->run($inputdge, $output); */

        $output->writeln('<info>Entities class create</info>');

        if ($schemaupdate) {
            $output->writeln('Aggiornamento database...');

            /* $command = $this->getApplication()->find('doctrine:schema:update');
              $inputdsu = new ArrayInput(array('--force' => true, '--em' => $emdest));
              $result = $command->run($inputdsu, $output); */

            $scriptGenerator = $console . ' doctrine:schema:update';

            if (OsFunctions::isWindows()) {
                $phpPath = OsFunctions::getPHPExecutableFromPath();
            } else {
                $phpPath = '/usr/bin/php';
            }
            $command = $phpPath . ' ' . $scriptGenerator . ' --force --em=' . $emdest
                    . ' --env=' . $this->getContainer()->get('kernel')->getEnvironment();
            /* @var $process \Symfony\Component\Process\Process */
            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            if (!$process->isSuccessful()) {
                $output->writeln('Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ');
            } else {
                $output->writeln($process->getOutput());
                $output->writeln('<info>Aggiornamento database completato</info>');
            }
        }

        return 0;
    }

//    private function clearCache($output)
//    {
//        $output->writeln('<info>Pulizia cache...</info>');
//        $pathsrc = $this->apppaths->getRootPath();
//        $sepchr = self::getSeparator();
//        $console = $this->apppaths->getConsole();
//        $ccGenerator = $console . ' cache:clear';
//
//        if (file_exists($ccGenerator)) {
//            if (OsFunctions::isWindows()) {
//                $phpPath = OsFunctions::getPHPExecutableFromPath();
//            } else {
//                $phpPath = '/usr/bin/php';
//            }
//
//            $command = 'cd ' . $pathsrc . $sepchr
//                    . $phpPath . ' ' . $ccGenerator;
//            /* @var $process \Symfony\Component\Process\Process */
//            $process = new Process($command);
//            $process->setTimeout(60 * 100);
//            $process->run();
//
//            if (!$process->isSuccessful()) {
//                $output->writeln('Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ');
//            } else {
//                $output->writeln($process->getOutput());
//            }
//        }
//    }

    private function checktables($destinationPath, $wbFile, $output)
    {
        $finder = new Finder();
        $fs = new Filesystem();

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

        if (count($wrongfilename) > 0) {
            $errout = '<error>Ci sono tabelle nel file ' . $wbFile . ' con nomi non consentiti:' .
                    implode(',', $wrongfilename) .
                    '. I nomi tabella devono essere : con la prima lettera maiuscola,underscore ammesso,doppio underscore non ammesso</error>';

            $output->writeln($errout);

            return -1;
        } else {
            return 0;
        }
    }

    public static function getSeparator()
    {
        if (OsFunctions::isWindows()) {
            return '&';
        } else {
            return ';';
        }
    }
}
