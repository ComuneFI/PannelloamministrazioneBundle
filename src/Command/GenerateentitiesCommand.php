<?php

namespace Fi\PannelloAmministrazioneBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\ProjectPath;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\GeneratorHelper;

class GenerateentitiesCommand extends ContainerAwareCommand
{

    protected $apppaths;
    protected $genhelper;

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
        $this->genhelper = new GeneratorHelper($this->getContainer());
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
        $checkprerequisiti = $this->genhelper->checkprerequisiti($bundlename, $mwbfile, $output);

        if ($checkprerequisiti < 0) {
            return -1;
        }

        $destinationPath = $this->genhelper->getDestinationEntityYmlPath($bundlename);

        $command = $this->getExportJsonCommand($bundlename, $wbFile);

        $schemaupdateresult = $this->exportschema($command, $output);
        if ($schemaupdateresult < 0) {
            return 1;
        }

        $this->removeExportJsonFile();

        $tablecheck = $this->genhelper->checktables($destinationPath, $wbFile, $output);

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

    private function getExportJsonCommand($bundlePath, $wbFile)
    {
        $exportJson = $this->getExportJsonFile();
        $scriptGenerator = $this->genhelper->getScriptGenerator();
        $destinationPathEscaped = str_replace('/', "\/", str_replace('\\', '/', $this->genhelper->getDestinationEntityYmlPath($bundlePath)));
        $bundlePathEscaped = str_replace('\\', '\\\\', str_replace('/', '\\', $bundlePath));

        $exportjsonfile = GeneratorHelper::getJsonMwbGenerator();

        $bundlejson = str_replace('[bundle]', str_replace('/', '', $bundlePathEscaped), $exportjsonfile);
        $exportjsonreplaced = str_replace('[dir]', $destinationPathEscaped, $bundlejson);
        file_put_contents($exportJson, $exportjsonreplaced);
        $sepchr = OsFunctions::getSeparator();
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
}
