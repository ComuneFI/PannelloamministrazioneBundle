<?php

namespace Fi\PannelloAmministrazioneBundle\Tests\Controller;

use Fi\CoreBundle\DependencyInjection\FifreeTest;
use Behat\Mink\Mink;
use Behat\Mink\Session;

class AdminpanelControllerTest extends FifreeTest
{
    public function testAdminpanelHomepage()
    {
        parent::setUp();
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

    public function testAdminpanelWeb()
    {
        parent::__construct();
        $this->setClassName(get_class());
        $browser = 'firefox';
        $client = $this->getClientAutorizzato();
        //$url = $client->getContainer()->get('router')->generate('Ffprincipale');
        $urlRouting = $client->getContainer()->get('router')->generate('fi_pannello_amministrazione_homepage'/* , array('parms' => 'value') */);
        $url = 'http://127.0.0.1:8000'.$urlRouting;

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

        //$session->stop();
    }
}
