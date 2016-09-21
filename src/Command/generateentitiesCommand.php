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

class generateentitiesCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
                ->setName('pannelloamministrazione:generateentities')
                ->setDescription('Genera le entities partendo da un modello workbeanch mwb')
                ->setHelp('Genera le entities partendo da un modello workbeanch mwb, <br/>fifree.mwb Fi/CoreBundle default [--schemaupdate]<br/>')
                ->addArgument('mwbfile', InputArgument::REQUIRED, 'Nome file mwb, fifree.mwb')
                ->addArgument('bundlename', InputArgument::REQUIRED, 'Nome del bundle, Fi/CoreBundle')
                ->addArgument('em', InputArgument::OPTIONAL, 'Entity manager, default = default')
                ->addOption('schemaupdate', null, InputOption::VALUE_NONE, 'Se settato fa anche lo schema update sul db'
                )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        set_time_limit(0);
        $apppaths = new ProjectPath($this->getContainer());

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

        $wbFile = $apppaths->getProjectPath() . DIRECTORY_SEPARATOR . 'doc' . DIRECTORY_SEPARATOR . $mwbfile;
        $checkprerequisiti = $this->checkprerequisiti($bundlename, $mwbfile, $output);

        if ($checkprerequisiti < 0) {
            return -1;
        }
        $bundlePath = $bundlename;
        $destinationPath = $apppaths->getProjectPath() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'doctrine' . DIRECTORY_SEPARATOR;
        $tablecheck = $this->checktables($destinationPath, $wbFile, $output);

        if ($tablecheck < 0) {
            return -1;
        }

        $output->writeln('<info>Entities yml create</info>');

        $tablecheck = $this->generateentities($bundlename, $emdest, $schemaupdate, $output);
        if ($tablecheck < 0) {
            return -1;
        }
        return 0;
    }

    private function checkprerequisiti($bundlename, $mwbfile, $output) {
        $fs = new Filesystem();
        $apppaths = new ProjectPath($this->getContainer());

        $bundlePath = $bundlename;
        $wbFile = $apppaths->getRootPath() . DIRECTORY_SEPARATOR . 'doc' . DIRECTORY_SEPARATOR . $mwbfile;
        $viewsPath = $apppaths->getRootPath() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR;
        $entityPath = $apppaths->getRootPath() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . 'Entity' . DIRECTORY_SEPARATOR;
        $formPath = $apppaths->getRootPath() . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . 'Form' . DIRECTORY_SEPARATOR;

        $scriptGenerator = $this->getScriptGenerator();

        $destinationPath = $apppaths->getSrcPath() . DIRECTORY_SEPARATOR . $bundlePath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $output->writeln('Creazione entities yml in ' . $destinationPath . ' da file ' . $mwbfile);
        $destinationPath = $destinationPath . 'doctrine' . DIRECTORY_SEPARATOR;

        $exportJson = $this->getExportJson();

        $destinationPathEscaped = str_replace('/', "\/", str_replace('\\', '/', $destinationPath));
        $bundlePathEscaped = str_replace('\\', '\\\\', str_replace('/', '\\', $bundlePath));

        $exportjsonfile = file_get_contents($apppaths->getProjectPath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'fi/fifreecorebundle/src/FiTemplate/config/export.json');
        $bundlejson = str_replace('[bundle]', str_replace('/', '', $bundlePathEscaped), $exportjsonfile);
        $exportjsonreplaced = str_replace('[dir]', $destinationPathEscaped, $bundlejson);
        file_put_contents($exportJson, $exportjsonreplaced);

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

        $sepchr = self::getSeparator();
        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = "/usr/bin/php";
        }

        $command = 'cd ' . substr($apppaths->getRootPath(), 0, -4) . $sepchr
                . $phpPath . ' ' . $scriptGenerator . ' --export=doctrine2-yaml --config=' . 
                $exportJson . ' ' . $wbFile . ' ' . $destinationPathEscaped;

        $schemaupdateresult = $this->exportschema($command);
        if ($schemaupdateresult < 0) {
            return -1;
        }

        if ($fs->exists($exportJson)) {
            $fs->remove($exportJson);
        }

        $fs->mkdir($destinationPath);
        $fs->mkdir($entityPath);
        $fs->mkdir($formPath);
        $fs->mkdir($viewsPath);

        return 0;
    }

    private function getExportJson() {
        $fs = new Filesystem();
        $apppaths = new ProjectPath($this->getContainer());
        $exportJson = $apppaths->getAppPath() . DIRECTORY_SEPARATOR . 'tmp/export.json';
        if ($fs->exists($exportJson)) {
            $fs->remove($exportJson);
        }
        return $exportJson;
    }

    private function exportschema($command) {
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ');
            return -1;
        }
        return 0;
    }

    private function getScriptGenerator() {
        $apppaths = new ProjectPath($this->getContainer());
        if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
            $scriptGenerator = $apppaths->getRootPath() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'mysql-workbench-schema-export';
        } else {
            $scriptGenerator = $apppaths->getBinPath() . DIRECTORY_SEPARATOR . 'mysql-workbench-schema-export';
        }
        return $scriptGenerator;
    }

    private function generateentities($bundlename, $emdest, $schemaupdate, $output) {
        /* GENERATE ENTITIES */
        $output->writeln('Creazione entities class per il bundle ' . str_replace('/', '', $bundlename));
        //$application = new Application($this->getContainer()->get('kernel'));
        //$application->setAutoExit(false);
        $command = $this->getApplication()->find('doctrine:generate:entities');
        $inputdge = new ArrayInput(array('--no-backup' => true, 'name' => str_replace('/', '', $bundlename)));
        $command->run($inputdge, $output);

        $output->writeln('<info>Entities class create</info>');

        if ($schemaupdate) {
            $output->writeln('Aggiornamento database...');

            /* $command = $this->getApplication()->find('doctrine:schema:update');
              $inputdsu = new ArrayInput(array('--force' => true, '--em' => $emdest));
              $result = $command->run($inputdsu, $output); */

            $apppaths = new ProjectPath($this->getContainer());
            $pathsrc = $apppaths->getRootPath();
            $sepchr = self::getSeparator();
            // Questo codice per versioni che usano un symfony 2 o 3
            if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
                $scriptGenerator = $pathsrc . DIRECTORY_SEPARATOR . "bin" . DIRECTORY_SEPARATOR . "console doctrine:schema:update";
            } else {
                $scriptGenerator = $pathsrc . DIRECTORY_SEPARATOR . "app" . DIRECTORY_SEPARATOR . "console doctrine:schema:update";
            }

            if (OsFunctions::isWindows()) {
                $phpPath = OsFunctions::getPHPExecutableFromPath();
            } else {
                $phpPath = "/usr/bin/php";
            }
            $command = 'cd ' . $pathsrc . $sepchr
                    . $phpPath . ' ' . $scriptGenerator . ' --force --em=' . $emdest;
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

    private function checktables($destinationPath, $wbFile, $output) {
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
            $output->writeln('<error>Ci sono tabelle nel file ' . $wbFile . ' con nomi non consentiti:' . implode(",", $wrongfilename) . '. I nomi tabella devono essere : con la prima lettera maiuscola,underscore ammesso,doppio underscore non ammesso</error>');
            return -1;
        } else {
            return 0;
        }
    }

    public static function getSeparator() {
        if (OsFunctions::isWindows()) {
            return '&';
        } else {
            return ';';
        }
    }

}
