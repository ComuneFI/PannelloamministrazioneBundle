<?php

namespace Fi\PannelloAmministrazioneBundle\Tests\DependencyInjection;

use Fi\CoreBundle\DependencyInjection\FifreeTest;
use Fi\PannelloAmministrazioneBundle\DependencyInjection\ProjectPath;

class ProjectPathTest extends FifreeTest
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
        $paths = new ProjectPath($this->container);

        $this->assertGreaterThan(0, strlen($paths->getAppPath()));
        $this->assertGreaterThan(0, strlen($paths->getSrcPath()));
        $this->assertGreaterThan(0, strlen($paths->getBinPath()));
        $this->assertGreaterThan(0, strlen($paths->getCachePath()));
        $this->assertGreaterThan(0, strlen($paths->getConsole()));
        $this->assertGreaterThan(0, strlen($paths->getDocPath()));
        $this->assertGreaterThan(0, strlen($paths->getProjectPath()));
        $this->assertGreaterThan(0, strlen($paths->getRootPath()));
        $this->assertGreaterThan(0, strlen($paths->getVarPath()));
        $this->assertGreaterThan(0, strlen($paths->getVendorBinPath()));
    }
}
