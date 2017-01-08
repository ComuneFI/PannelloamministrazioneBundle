<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\Filesystem\Filesystem;

class GeneratorHelper
{

    private $container;
    private $apppath;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppath = new ProjectPath($container);
    }

    public function getDestinationEntityYmlPath($bundlePath)
    {
        return $this->apppaths->getSrcPath() . DIRECTORY_SEPARATOR .
                $bundlePath . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR .
                'config' . DIRECTORY_SEPARATOR . 'doctrine' . DIRECTORY_SEPARATOR;
    }

    public static function getJsonMwbGenerator()
    {
        $jsonTemplate = <<<EOF
{"export": "doctrine2-yaml", 
  "zip": false, 
  "dir": "[dir]", 
  "params": 
    {"indentation": 4, 
    "useTabs": false, 
    "filename": "%table%.orm.%extension%", 
    "skipPluralNameChecking": true, 
    "backupExistingFile": false, 
    "addGeneratorInfoAsComment":false,
    "useLoggedStorage": false, 
    "enhanceManyToManyDetection": true, 
    "logToConsole": false, 
    "logFile": "", 
    "bundleNamespace": "[bundle]", 
    "entityNamespace": "Entity", 
    "repositoryNamespace": "%entity%", 
    "useAutomaticRepository": false, 
    "extendTableNameWithSchemaName": false}}
EOF;
        return $jsonTemplate;
    }
}
