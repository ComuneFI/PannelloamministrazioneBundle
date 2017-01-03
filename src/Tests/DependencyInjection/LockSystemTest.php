<?php

namespace Fi\PannelloAmministrazioneBundle\Tests\DependencyInjection;

use Fi\CoreBundle\DependencyInjection\FifreeTest;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\LockSystem;

class LockSystemTest extends FifreeTest
{

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->setClassName(get_class());
    }

    public function testPaths()
    {
        $locksystem = new LockSystem($this->container);
        $locksystem->lockFile(true);
        $this->assertTrue($locksystem->isLockedFile());
        $this->assertGreaterThan(0, strlen($locksystem->getFileLock()));
        $locksystem->lockFile(false);
        $this->assertFalse($locksystem->isLockedFile());
    }
}
