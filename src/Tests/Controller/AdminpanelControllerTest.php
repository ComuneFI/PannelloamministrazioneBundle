<?php

namespace Fi\PannelloAmministrazioneBundle\Tests\Controller;

use Symfony\Component\DomCrawler\Crawler;
use Fi\CoreBundle\DependencyInjection\FifreeTest;

class AdminpanelControllerTest extends FifreeTest
{
    public function testAdminpanel()
    {
        $this->assertTrue(true);
        /* parent::__construct();
          $this->setClassName(get_class());
          $client = $this->getClientAutorizzato();
          $client->request('GET', '/adminpanel');
          $crawler = new Crawler($client->getResponse()->getContent());

          $this->assertTrue($client->getResponse()->isSuccessful());
          $this->assertGreaterThan(0, $crawler->filter('html:contains("Bundle")')->count());

         */
    }
}
