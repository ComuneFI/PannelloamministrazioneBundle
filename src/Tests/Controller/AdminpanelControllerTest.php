<?php

namespace Fi\PannelloAmministrazioneBundle\Tests\Controller;

use Fi\CoreBundle\DependencyInjection\FifreeTest;
use Behat\Mink\Mink;
use Behat\Mink\Session;

class AdminpanelControllerTest extends FifreeTest
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->setClassName(get_class());
    }

    public function test1starttests()
    {
        startTestsPA();
    }

    public function test10AdminpanelHomepage()
    {
        $client = $this->getClientAutorizzato();
        $url = parent::getContainer()->get('router')->generate('fi_pannello_amministrazione_homepage'/* , array('parms' => 'value') */);

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
        $browser = 'firefox';
        //$url = $client->getContainer()->get('router')->generate('Ffprincipale');
        $urlRouting = parent::getContainer()->get('router')->generate('fi_pannello_amministrazione_homepage'/* , array('parms' => 'value') */);
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

        $page->fillField('bundlename', 'Fi/ProvaBundle');

        $page->pressButton('adminpanelgeneratebundle');
        $scriptrun = "function(){ $('button:contains(\"Si\")').click();}()";
        $session->executeScript($scriptrun);
        $session->getDriver()->getWebDriverSession()->accept_alert();
        $this->ajaxWait($session);
        //$scriptclose = "function(){ if ($(\"#risultato\").is(\":visible\")) {$(\"#risultato\").dialog(\"close\");}}()";
        //$scriptclose = 'function(){ $("#risultato").dialog("close");}()';
        //$session->executeScript($scriptclose);

        $session->stop();
    }

    /*
     * @test
     */

    public function test100PannelloAmministrazioneMain()
    {
        $container = parent::getContainer();
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

    /*
     * @test
     */

    public function testZ999999999999CloeseTests()
    {
        startTestsPA();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        parent::tearDown();
    }
}
