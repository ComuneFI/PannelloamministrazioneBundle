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

class checkgitversionCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
                ->setName('pannelloamministrazione:checkgitversion')
                ->setDescription('Controllo versioni bundles')
                ->setHelp('Controlla le versioni git dei bundles')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $rootdir = $this->getContainer()->get('kernel')->getRootDir() . "/..";
        $appdir = $this->getContainer()->get('kernel')->getRootDir();
        $cachedir = $appdir . DIRECTORY_SEPARATOR . "cache";
        $logdir = $appdir . DIRECTORY_SEPARATOR . "logs";
        $tmpdir = $appdir . DIRECTORY_SEPARATOR . "/tmp";
        $srcdir = $rootdir . DIRECTORY_SEPARATOR . "/src";
        $webdir = $rootdir . DIRECTORY_SEPARATOR . "/web";
        $uploaddir = $webdir . DIRECTORY_SEPARATOR . "/uploads";
        $projectDir = substr($this->getContainer()->get('kernel')->getRootDir(), 0, -4);


        if (self::isWindows()) {
            echo "Non previsto in ambiente windows";
            exit;
        }


        $composerbundles = array();
        $composerbundlespath = $projectDir . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "fi";
        $findercomposerbundle = new Finder();
        $findercomposerbundle->in($composerbundlespath)->sortByName()->directories()->depth('== 0');

        foreach ($findercomposerbundle as $file) {
            $fullcomposerbundlepath = $composerbundlespath . DIRECTORY_SEPARATOR . $file->getBasename();
            $output->writeln('<info>' . $file->getBasename() . '</info> ' . $this->getGitVersion($fullcomposerbundlepath, false) . ' ---> ' . $this->getGitVersion($fullcomposerbundlepath, true));

            $composerbundles[] = array("name" => $file->getBasename(), "path" => $fullcomposerbundlepath, "version" => $this->getGitVersion($fullcomposerbundlepath));
        }
    }

    private function getGitVersion($path, $remote = false) {
        if (!self::isWindows()) {
            $shellOutput = [];
            if ($remote) {
                $cmd = "cd " . $path;
                $remotetag = $cmd . ";git ls-remote -t | awk '{print $2}' | cut -d '/' -f 3 | cut -d '^' -f 1 | sort --version-sort | tail -1";
                $process = new Process($remotetag);
                $process->setTimeout(60 * 100);
                $process->run();
                if ($process->isSuccessful()) {
                    return $process->getOutput();
                }
                return "";
            } else {
                $cmd = "cd " . $path;
                $process = new Process($cmd . ';git branch | ' . "grep ' * '");
                $process->setTimeout(60 * 100);
                $process->run();
                if ($process->isSuccessful()) {
                    $out = explode(chr(10), $process->getOutput());
                    foreach ($out as $line) {

                        if (strpos($line, '* ') !== false) {
                            return trim(strtolower(str_replace('* ', '', $line)));
                        }
                    }

                    /* if (strpos($line, '* ') !== false) {
                      return trim(strtolower(str_replace('* ', '', $line)));
                      }
                      return $process->getOutput(); */
                } else {
                    echo $process->getErrorOutput();
                }
                return "";
            }
        } else {
            //Per windows non esiste il grep
            return "";
        }
    }

    static function isWindows() {
        if (PHP_OS == "WINNT") {
            return true;
        } else {
            return false;
        }
    }

}

?>