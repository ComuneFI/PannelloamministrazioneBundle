<?php

namespace Fi\PannelloAmministrazioneBundle\Tests\Controller;

use Fi\CoreBundle\DependencyInjection\FifreeTest;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Fi\OsBundle\DependencyInjection\OsFunctions;

class AdminpanelControllerTest extends FifreeTest
{
    public function test10AdminpanelHomepage()
    {
        parent::setUp();
        $this->CleanFilesystem();
        $client = $this->getClientAutorizzato();
//$url = $client->getContainer()->get('router')->generate('Ffprincipale');
        $url = $client->getContainer()->get('router')->generate('fi_pannello_amministrazione_homepage'/* , array('parms' => 'value') */);

        $client->request('GET', $url);
        $this->assertTrue(
            $client->getResponse()->headers->contains('Content-Type', 'text/html; charset=UTF-8')
        );
    }

    /*
     * @test
     */

    public function test20AdminpanelGenerateBundle()
    {
        parent::__construct();
        $this->setClassName(get_class());
        $browser = 'firefox';
        $client = $this->getClientAutorizzato();
//$url = $client->getContainer()->get('router')->generate('Ffprincipale');
        $urlRouting = $client->getContainer()->get('router')->generate('fi_pannello_amministrazione_homepage'/* , array('parms' => 'value') */);
        $url = 'http://127.0.0.1:8000/web.php'.$urlRouting;

// Choose a Mink driver. More about it in later chapters.
        $driver = new \Behat\Mink\Driver\Selenium2Driver($browser);
        $session = new Session($driver);
// start the session
        $session->start();
        $session->visit($url);
        $page = $session->getPage();
        sleep(1);
        /* Login */
        $page->fillField('username', 'admin');
        $page->fillField('password', 'admin');
        $page->pressButton('_submit');

        sleep(1);

        $page->fillField('bundlename', 'Fi/ProvaBundle');

        $page->pressButton('adminpanelgeneratebundle');
        $scriptrun = "function(){ $('button:contains(\"Si\")').click();}()";
        $session->executeScript($scriptrun);
        $session->getDriver()->getWebDriverSession()->accept_alert();
        $this->ajaxWait($session);
//$scriptclose = "function(){ if ($(\"#risultato\").is(\":visible\")) {$(\"#risultato\").dialog(\"close\");}}()";
        $scriptclose = 'function(){ $("#risultato").dialog("close");}()';
        $session->executeScript($scriptclose);

        $this->clearcache();

        $session->stop();
    }

    /* public function test30AdminpanelGenerateEntity() {
      parent::__construct();
      $this->setClassName(get_class());
      $browser = 'firefox';
      $client = $this->getClientAutorizzato();
      //$url = $client->getContainer()->get('router')->generate('Ffprincipale');
      $urlRouting = $client->getContainer()->get('router')->generate('fi_pannello_amministrazione_homepage');
      $url = 'http://127.0.0.1:8000/web.php' . $urlRouting;

      // Choose a Mink driver. More about it in later chapters.
      $driver = new \Behat\Mink\Driver\Selenium2Driver($browser);
      $session = new Session($driver);
      // start the session
      $session->start();
      $session->visit($url);
      $page = $session->getPage();
      sleep(1);
      // Login
      $page->fillField('username', 'admin');
      $page->fillField('password', 'admin');
      $page->pressButton('_submit');

      sleep(1);
      $page->fillField('bundlename', 'Fi/ProvaBundle');

      $page->selectFieldOption('entitybundle', 'Fi/ProvaBundle');

      $page->pressButton('adminpanelgenerateentity');
      $scriptrun = "function(){ $('button:contains(\"Si\")').click();}()";
      $session->executeScript($scriptrun);
      $this->ajaxWait($session);
      //$scriptclose = "function(){ if ($(\"#risultato\").is(\":visible\")) {$(\"#risultato\").dialog(\"close\");}}()";
      $scriptclose = "function(){ $(\"#risultato\").dialog(\"close\");}()";
      $session->executeScript($scriptclose);

      //$this->generateentities();
      $this->clearcache();
      $session->stop();
      } */

    public function ajaxWait($session)
    {
        $time = 5000; // time should be in milliseconds
        $session->wait($time, '(0 === jQuery.active)');
// asserts below
    }

    /*
     * @test
     */

    public function test100PannelloAmministrazioneMain()
    {
        parent::setUp();
        $container = $this->getContainer();
        /* @var $userManager \FOS\UserBundle\Doctrine\UserManager */
        $userManager = $container->get('fos_user.user_manager');
        /* @var $loginManager \FOS\UserBundle\Security\LoginManager */
        $loginManager = $container->get('fos_user.security.login_manager');
        $firewallName = $container->getParameter('fos_user.firewall_name');
        $username4test = $container->getParameter('user4test');
        $user = $userManager->findUserBy(array('username' => $username4test));
        $loginManager->loginUser($firewallName, $user);

        /* save the login token into the session and put it in a cookie */
        $container->get('session')->set('_security_'.$firewallName, serialize($container->get('security.token_storage')->getToken()));
        $container->get('session')->save();
    }

    private function CleanFilesystem()
    {
        $DELETE = "new Fi\ProvaBundle\FiProvaBundle(),";
        $vendorDir = dirname(dirname(__FILE__));
        $kernelfile = $vendorDir.'/app/AppKernel.php';
        $this->deleteLineFromFile($kernelfile, $DELETE);
        $routingfile = $vendorDir.'/app/config/routing.yml';
        $line = fgets(fopen($routingfile, 'r'));
        if (substr($line, 0, -1) == 'fi_prova:') {
            for ($index = 0; $index < 4; ++$index) {
                $this->deleteFirstLineFile($routingfile);
            }
        }
        $bundledir = $vendorDir.'/src';

        $fs = new Filesystem();
        $fs->remove($bundledir);

        /* $cachedir = dirname(dirname(__FILE__)) . '/app/cache/test';
          $fs->remove($cachedir); */
        //$this->CleanFilesystem();
        $this->clearcache();
    }

    private function deleteFirstLineFile($file)
    {
        $handle = fopen($file, 'r');
        $first = fgets($handle, 2048); //get first line.
        $outfile = 'temp';
        $o = fopen($outfile, 'w');
        while (!feof($handle)) {
            $buffer = fgets($handle, 2048);
            fwrite($o, $buffer);
        }
        fclose($handle);
        fclose($o);
        rename($outfile, $file);
    }

    private function deleteLineFromFile($file, $DELETE)
    {
        $data = file($file);

        $out = array();

        foreach ($data as $line) {
            if (trim($line) != $DELETE) {
                $out[] = $line;
            }
        }

        $fp = fopen($file, 'w+');
        flock($fp, LOCK_EX);
        foreach ($out as $line) {
            fwrite($fp, $line);
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function clearcache()
    {
        $command = 'rm -rf '.dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'test';
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();

        if (OsFunctions::isWindows()) {
            $phpPath = OsFunctions::getPHPExecutableFromPath();
        } else {
            $phpPath = '/usr/bin/php';
        }

        $command = $phpPath.' '.dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'console cache:clear --env=test';
        $process = new Process($command);
        $process->setTimeout(60 * 100);
        $process->run();
    }

    /* private function generateentities() {

      if (OsFunctions::isWindows()) {
      $phpPath = OsFunctions::getPHPExecutableFromPath();
      } else {
      $phpPath = '/usr/bin/php';
      }

      $command = $phpPath . ' ' . dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'console pannelloamministrazione:generateentities wbadmintest.mwb Fi/ProvaBundle';
      $process = new Process($command);
      $process->setTimeout(60 * 100);
      $process->run();
      if (!$process->isSuccessful()) {
      echo 'Errore nel comando ' . $command . '<error>' . $process->getErrorOutput() . '</error> ';
      } else {
      echo $process->getOutput();
      }

      // $fs = new Filesystem();
      //  $cachedir = dirname(dirname(__FILE__)) . '/app/cache/test';
      //  $fs->remove($cachedir);
      }
     */

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->CleanFilesystem();
        parent::setUp();
    }
}
