<?php

namespace Fi\PannelloAmministrazioneBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Fi\OsBundle\DependencyInjection\OsFunctions;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\Commands;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\LockSystem;

class PannelloAmministrazioneController extends Controller
{
    public function indexAction()
    {
        $finder = new Finder();
        $fs = new Filesystem();

        $projectDir = substr($this->get('kernel')->getRootDir(), 0, -4);
        $bundlelists = $this->container->getParameter('kernel.bundles');
        $bundles = array();
        foreach ($bundlelists as $bundle) {
            if (substr($bundle, 0, 2) === 'Fi') {
                $bundle = str_replace('\\', '/', $bundle);
                $bundlepath = $projectDir.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.substr($bundle, 0, strripos($bundle, '/'));
                if ($fs->exists($bundlepath)) {
                    $bundles[] = substr($bundle, 0, strripos($bundle, '/'));
                }
            }
        }
        $docDir = $projectDir.'/doc/';

        $mwbs = array();

        if ($fs->exists($docDir)) {
            $finder->in($docDir)->files()->name('*.mwb');
            foreach ($finder as $file) {
                $mwbs[] = $file->getBasename();
            }
        }

        if ($fs->exists($projectDir.'/.svn')) {
            $svn = true;
        } else {
            $svn = false;
        }

        if ($fs->exists($projectDir.'/.git')) {
            $git = true;
        } else {
            $git = false;
        }

        if (!OsFunctions::isWindows()) {
            $delcmd = 'rm -rf';
            $delfoldercmd = 'rm -rf';
            $windows = false;
        } else {
            $delcmd = 'del';
            $delfoldercmd = 'rmdir /s';
            $windows = true;
        }

        $dellockfile = $delcmd.' '.$projectDir.DIRECTORY_SEPARATOR.
                'app'.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.'running.run';
        $delcomposerfile = $delcmd.' '.$projectDir.DIRECTORY_SEPARATOR.'composer.lock';
        $dellogsfiles = $delcmd.' '.$projectDir.DIRECTORY_SEPARATOR.
                'app'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'*';
        $delcacheprodfiles = $delcmd.' '.$projectDir.DIRECTORY_SEPARATOR.
                'app'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'prod'.DIRECTORY_SEPARATOR.'*';
        $delcachedevfiles = $delcmd.' '.$projectDir.DIRECTORY_SEPARATOR.
                'app'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'dev'.DIRECTORY_SEPARATOR.'*';

        $comandishell = array(
            'lockfile' => $this->fixSlash($dellockfile),
            'composerlock' => $this->fixSlash($delcomposerfile),
            'logsfiles' => $this->fixSlash($dellogsfiles),
            'cacheprodfiles' => $this->fixSlash($delcacheprodfiles),
            'cachedevfiles' => $this->fixSlash($delcachedevfiles),
        );

        $twigparms = array('svn' => $svn, 'git' => $git, 'bundles' => $bundles, 'mwbs' => $mwbs,
            'rootdir' => $this->fixSlash($projectDir),
            'comandishell' => $comandishell, 'iswindows' => $windows, );

        return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:index.html.twig', $twigparms);
    }

    private function fixSlash($path)
    {
        return str_replace('\\', '\\\\', $path);
    }

    public function aggiornaSchemaDatabaseAction()
    {
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);
            $commands = new Commands($this->container);
            $result = $commands->aggiornaSchemaDatabase();

            (new LockSystem($this->container))->lockFile(false);
            $twigparms = array('errcode' => $result['errcode'], 'command' => $result['command'], 'message' => $result['message']);

            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', $twigparms);
        }
    }

    /* FORMS */

    public function generateFormCrudAction(Request $request)
    {
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            $bundlename = $request->get('bundlename');
            $entityform = $request->get('entityform');

            (new LockSystem($this->container))->lockFile(true);

            $command = new Commands($this->container);
            $ret = $command->generateFormCrud($bundlename, $entityform);

            (new LockSystem($this->container))->lockFile(false);
            $retcc = '';
            if ($ret['errcode'] < 0) {
                return new Response($ret['message']);
            } else {
                $retcc = $command->clearCache();
            }
            $twigparms = array('errcode' => $ret['errcode'], 'command' => $ret['command'], 'message' => $ret['message'].$retcc);

            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', $twigparms);
        }
    }

    /* ENTITIES */

    public function generateEntityAction(Request $request)
    {
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);
            $wbFile = $request->get('file');
            $bundlePath = $request->get('bundle');
            $commands = new Commands($this->container);
            $ret = $commands->generateEntity($wbFile, $bundlePath);
            (new LockSystem($this->container))->lockFile(false);

            return new Response($ret['message']);
        }
    }

    /* BUNDLE */

    public function generateBundleAction(Request $request)
    {
        $fs = new Filesystem();
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);
            $prjPath = substr($this->get('kernel')->getRootDir(), 0, -4);
            $bundleName = $request->get('bundlename');

            $bundlePath = $prjPath.'/src/'.$bundleName;
            $addmessage = '';
            if ($fs->exists($bundlePath)) {
                $result = array('errcode' => -1, 'command' => 'generate:bundle', 'message' => "Il bundle esiste gia' in $bundlePath");
            } else {
                $commands = new Commands($this->container);
                $commandparms = array('--namespace' => $bundleName, '--dir' => $prjPath.'/src/', '--format' => 'yml', '--no-interaction' => true);
                $result = $commands->executeCommand('generate:bundle', $commandparms);
                $bundlePath = $prjPath.'/src/'.$bundleName;
                if ($fs->exists($bundlePath)) {
                    $addmessage = 'Per abilitare il nuovo bundle nel kernel controllare che sia presente in app/AppKernel.php '
                            .'e aggiornare la pagina';
                    echo '<script type="text/javascript">alert("Per abilitare il nuovo bundle nel kernel aggiornare la pagina");</script>';
                } else {
                    $addmessage = "Non e' stato creato il bundle in $bundlePath";
                }
            }
            (new LockSystem($this->container))->lockFile(false);
            //Uso exit perchè la render avendo creato un nuovo bundle schianta perchè non è caricato nel kernel il nuovo bundle ancora
            //exit;
            $twigparms = array('errcode' => $result['errcode'], 'command' => $result['command'], 'message' => $result['message'].$addmessage);

            return $this->render('FiPannelloAmministrazioneBundle:PannelloAmministrazione:outputcommand.html.twig', $twigparms);
        }
    }

    /* VCS (GIT,SVN) */

    public function getVcsAction()
    {
        set_time_limit(0);
        $fs = new Filesystem();
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);
            $sepchr = OsFunctions::getSeparator();
            $projectDir = substr($this->get('kernel')->getRootDir(), 0, -4);
            if ($fs->exists($projectDir.'/.svn')) {
                $vcscommand = 'svn update';
            }
            if ($fs->exists($projectDir.'/.git')) {
                $vcscommand = 'git pull';
            }

            $command = 'cd '.substr($this->get('kernel')->getRootDir(), 0, -4).$sepchr.$vcscommand;
            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            (new LockSystem($this->container))->lockFile(false);
            if (!$process->isSuccessful()) {
                $responseout = 'Errore nel comando: <i style = "color: white;">'.$command.'</i>'
                        .'<br/><i style = "color: red;">'.str_replace("\n", '<br/>', $process->getErrorOutput()).'</i>';

                return new Response($responseout);
            }
            $responseout = '<pre>Eseguito comando: <i style = "color: white;">'.$command.'</i><br/>'.
                    str_replace("\n", '<br/>', $process->getOutput()).'</pre>';

            return new Response($responseout);
        }
    }

    /* CLEAR CACHE */

    public function clearCacheAction(Request $request)
    {
        set_time_limit(0);
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);
            $commands = new Commands($this->container);

            $result = $commands->clearcache();

            (new LockSystem($this->container))->lockFile(false);

            /* Uso exit perchè new response avendo cancellato la cache schianta non avendo più a disposizione i file */
            //return $commanddev . '<br/>' . $cmdoutputdev . '<br/><br/>' . $commandprod . '<br/>' . $cmdoutputprod;
            return new Response(nl2br($result));
        }
    }

    /* CLEAR CACHE */

    public function symfonyCommandAction(Request $request)
    {
        set_time_limit(0);
        $comando = $request->get('symfonycommand');
        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);

            if (!OsFunctions::isWindows()) {
                $phpPath = '/usr/bin/php';
            } else {
                $phpPath = OsFunctions::getPHPExecutableFromPath();
            }
            $pathsrc = $this->get('kernel')->getRootDir();
            $sepchr = OsFunctions::getSeparator();

            $command = 'cd '.$pathsrc.$sepchr
                    .$phpPath.' console '.$comando;

            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            (new LockSystem($this->container))->lockFile(false);
            if (!$process->isSuccessful()) {
                $responseout = 'Errore nel comando: <i style = "color: white;">'.
                        str_replace(';', '<br/>', str_replace('&&', '<br/>', $command)).
                        '</i><br/><i style = "color: red;">'.str_replace("\n", '<br/>', $process->getErrorOutput()).'</i>';

                return new Response($responseout);
            }
            $responseout = '<pre>Eseguito comando:<br/><br/><i style = "color: white;">'.
                    str_replace(';', '<br/>', str_replace('&&', '<br/>', $command)).'</i><br/><br/>'.
                    str_replace("\n", '<br/>', $process->getOutput()).'</pre>';

            return new Response($responseout);
        }
    }

    public function unixCommandAction(Request $request)
    {
        set_time_limit(0);
        $command = $request->get('unixcommand');
        if (!OsFunctions::isWindows()) {
            $lockdelcmd = 'rm -rf ';
        } else {
            $lockdelcmd = 'del ';
        }
        //Se viene lanciato il comando per cancellare il file di lock su bypassa tutto e si lancia
        $filelock = str_replace('\\', '\\\\', (new LockSystem($this->container))->getFileLock());
        if (str_replace('\\\\', '/', $command) == str_replace('\\\\', '\\', $lockdelcmd.$filelock)) {
            $fs = new Filesystem();
            if ((!($fs->exists($filelock)))) {
                return new Response('Non esiste il file di lock: <i style = "color: white;">'.$filelock.'</i><br/>');
            } else {
                //Sblocca pannello di controllo da lock
                $process = new Process($command);
                $process->setTimeout(60 * 100);
                $process->run();

                // eseguito deopo la fine del comando
                if (!$process->isSuccessful()) {
                    $responseout = 'Errore nel comando: <i style = "color: white;">'.
                            str_replace(';', '<br/>', str_replace('&&', '<br/>', $command)).
                            '</i><br/><i style = "color: red;">'.str_replace("\n", '<br/>', $process->getErrorOutput()).'</i>';

                    return new Response($responseout);
                }

                return new Response('File di lock cancellato');
            }
        }

        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            (new LockSystem($this->container))->lockFile(true);
            //$phpPath = OsFunctions::getPHPExecutableFromPath();
            $process = new Process($command);
            $process->setTimeout(60 * 100);
            $process->run();

            (new LockSystem($this->container))->lockFile(false);
            // eseguito deopo la fine del comando
            if (!$process->isSuccessful()) {
                $errmsg = 'Errore nel comando: <i style = "color: white;">'.
                        str_replace(';', '<br/>', str_replace('&&', '<br/>', $command)).
                        '</i><br/><i style = "color: red;">'.str_replace("\n", '<br/>', $process->getErrorOutput()).'</i>';

                return new Response($errmsg);
                //Uso exit perchè new response avendo cancellato la cache schianta non avendo più a disposizione i file
                //return;
                /* return new Response('Errore nel comando: <i style = "color: white;">' .
                 * $command . '</i><br/><i style = "color: red;">' . str_replace("\n", '<br/>', $process->getErrorOutput()) . '</i>'); */
            }
            $msgok = '<pre>Eseguito comando:<br/><i style = "color: white;"><br/>'.
                    str_replace(';', '<br/>', str_replace('&&', '<br/>', $command)).'</i><br/>'.
                    str_replace("\n", '<br/>', $process->getOutput()).'</pre>';
            //Uso exit perchè new response avendo cancellato la cache schianta non avendo più a disposizione i file
            return new Response($msgok);
            //return;
            /* return new Response('<pre>Eseguito comando: <i style = "color: white;">' . $command .
             * '</i><br/>' . str_replace("\n", "<br/>", $process->getOutput()) . "</pre>"); */
        }
    }

    public function phpunittestAction(Request $request)
    {
        set_time_limit(0);

        if ((new LockSystem($this->container))->isLockedFile()) {
            return (new LockSystem($this->container))->lockedFunctionMessage();
        } else {
            if (!OsFunctions::isWindows()) {
                (new LockSystem($this->container))->lockFile(true);
                //$phpPath = OsFunctions::getPHPExecutableFromPath();
                $sepchr = OsFunctions::getSeparator();
                $phpPath = '/usr/bin/php';

                // Questo codice per versioni che usano un symfony 2 o 3
                if (version_compare(\Symfony\Component\HttpKernel\Kernel::VERSION, '3.0') >= 0) {
                    $command = 'cd '.substr($this->get('kernel')->getRootDir(), 0, -4).$sepchr.
                            $phpPath.' '.'vendor'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'phpunit';
                } else {
                    $command = 'cd '.substr($this->get('kernel')->getRootDir(), 0, -4).$sepchr.
                            $phpPath.' '.'bin'.DIRECTORY_SEPARATOR.'phpunit -c app';
                }

                $process = new Process($command);
                $process->run();

                (new LockSystem($this->container))->lockFile(false);
                // eseguito deopo la fine del comando
                /* if (!$process->isSuccessful()) {
                  return new Response('Errore nel comando: <i style = "color: white;">' .
                 * $command . '</i><br/><i style = "color: red;">' . str_replace("\n",
                 * '<br/>', $process->getErrorOutput()) . '</i>');
                  } */
                $responseout = '<pre>Eseguito comando: <i style = "color: white;">'.$command.'</i><br/>'.
                        str_replace("\n", '<br/>', $process->getOutput()).'</pre>';

                return new Response($responseout);
            } else {
                return new Response('Non previsto in ambiente windows!');
            }
        }
    }
}
