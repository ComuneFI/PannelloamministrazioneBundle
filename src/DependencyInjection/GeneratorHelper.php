<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\Filesystem\Filesystem;

class GeneratorHelper
{

    private $container;
    private $apppaths;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppaths = new ProjectPath($container);
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
