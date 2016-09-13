<?php

namespace Fi\PannelloAmministrazioneBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Fi\OsBundle\DependencyInjection\OsFunctions;

class generateentitiesCommand extends ContainerAwareCommand
{
    protected function configure()
    {
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        $rootdir = $this->getContainer()->get('kernel')->getRootDir().'/..';
        $appdir = $this->getContainer()->get('kernel')->getRootDir();
        $cachedir = $appdir.DIRECTORY_SEPARATOR.'cache';
        $logdir = $appdir.DIRECTORY_SEPARATOR.'logs';
        $tmpdir = $appdir.DIRECTORY_SEPARATOR.'/tmp';
        $srcdir = $rootdir.DIRECTORY_SEPARATOR.'/src';
        $webdir = $rootdir.DIRECTORY_SEPARATOR.'/web';
        $uploaddir = $webdir.DIRECTORY_SEPARATOR.'/uploads';
        $prjPath = $rootdir;

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

        $fs = new Filesystem();
        $wbFile = $prjPath.DIRECTORY_SEPARATOR.'doc'.DIRECTORY_SEPARATOR.$mwbfile;
        $bundlePath = $bundlename;
        if (!$fs->exists($wbFile)) {
            $output->writeln("<error>Nella cartella 'doc' non è presente il file ".$mwbfile.'!');
            exit;
        }

        $scriptGenerator = $prjPath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'fi'.DIRECTORY_SEPARATOR.'schemaexporterbundle'.DIRECTORY_SEPARATOR.'cli'.DIRECTORY_SEPARATOR.'export.php';

        $destinationPath = $prjPath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.$bundlePath.DIRECTORY_SEPARATOR.'Resources'.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR;

        if (!$fs->exists($scriptGenerator)) {
            $output->writeln('<error>Non è presente il file export.php del bundle SchemaExporterBundle!</error>');
            exit;
        }
        if (!$fs->exists($destinationPath)) {
            $output->writeln("<error>Non esiste la cartella per l'esportazione ".$destinationPath.', controllare il nome del Bundle!</error>');
            exit;
        }

        $output->writeln('Creazione entities yml in '.$destinationPath.' da file '.$mwbfile);

        $destinationPath = $destinationPath.'doctrine'.DIRECTORY_SEPARATOR;
        $exportJson = $prjPath.'/app/tmp/export.json';

        if ($fs->exists($exportJson)) {
            $fs->remove($exportJson);
        }
        $destinationPathEscaped = str_replace('\\', '/', $destinationPath);
        $destinationPathEscaped = str_replace('/', "\/", $destinationPathEscaped);
        $bundlePathEscaped = str_replace('/', '\\', $bundlePath);
        $bundlePathEscaped = str_replace('\\', '\\\\', $bundlePathEscaped);

        $str = file_get_contents($prjPath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'fi/fifreecorebundle/FiTemplate/config/export.json');
        $str = str_replace('[bundle]', str_replace('/', '', $bundlePathEscaped), $str);
        $str = str_replace('[dir]', $destinationPathEscaped, $str);
        file_put_contents($exportJson, $str);

        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = '/usr/bin/php';
        }
        $pathsrc = $rootdir;
        $sepchr = self::getSeparator();

        $command = 'cd '.substr($pathsrc, 0, -4).$sepchr
                .$phpPath.' '.$scriptGenerator.' --export=doctrine2-yaml --config='.$exportJson.' '.$wbFile.' '.$destinationPathEscaped;

        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if ($fs->exists($exportJson)) {
            $fs->remove($exportJson);
        }

        if (!$process->isSuccessful()) {
            $output->writeln('Errore nel comando '.$command.'<error>'.$process->getErrorOutput().'</error> ');
        }
        $output->writeln('<info>Entities yml create</info>');

        //return new Response('<pre>Eseguito comando: <i style="color: white;">' . $command . '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>");

        /* GENERATE ENTITIES */
        //$bundleName = $request->get("bundlename");
        $output->writeln('Creazione entities class per il bundle '.str_replace('/', '', $bundlename));
        $application = new Application($this->getContainer()->get('kernel'));
        $application->setAutoExit(false);
        $command = $this->getApplication()->find('doctrine:generate:entities');
        $inputdge = new ArrayInput(array('--no-backup' => true, 'name' => str_replace('/', '', $bundlename)));
        $result = $command->run($inputdge, $output);

        $output->writeln('<info>Entities class create</info>');

        if ($schemaupdate) {
            $output->writeln('Aggiornamento database...');

            $application = new Application($this->getContainer()->get('kernel'));
            $application->setAutoExit(false);
            $command = $this->getApplication()->find('doctrine:schema:update');
            $inputdsu = new ArrayInput(array('--force' => true, '--em' => $emdest));
            $result = $command->run($inputdsu, $output);

            $output->writeln('<info>Aggiornamento database completato</info>');
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
