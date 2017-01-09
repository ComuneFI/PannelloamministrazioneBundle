<?php

namespace Fi\PannelloAmministrazioneBundle\DependencyInjection;

use Symfony\Component\Filesystem\Filesystem;

class GenerateCode
{

    private $container;
    private $apppath;

    public function __construct($container)
    {
        $this->container = $container;
        $this->apppath = new ProjectPath($container);
    }

    public function generateFormsTemplates($bundlename, $entityform)
    {
        $fs = new Filesystem();
        //Controller
        $controlleFile = $this->apppath->getSrcPath() . DIRECTORY_SEPARATOR .
                $bundlename . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR .
                $entityform . 'Controller.php';
        $code = $this->getControllerCode(str_replace('/', '\\', $bundlename), $entityform);
        $fs->dumpFile($controlleFile, $code);

        //Routing
        $retmsg = $this->generateFormRouting($bundlename, $entityform);
        //Twig template (Crea i template per new edit show)
        $this->generateFormWiew($bundlename, $entityform, 'edit');
        $this->generateFormWiew($bundlename, $entityform, 'index');
        $this->generateFormWiew($bundlename, $entityform, 'new');

        return $retmsg;
    }

    public function generateFormRouting($bundlename, $entityform)
    {
        //Routing del form
        $fs = new Filesystem();
        $routingFile = $this->apppath->getSrcPath() . DIRECTORY_SEPARATOR . $bundlename .
                DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'config' .
                DIRECTORY_SEPARATOR . 'routing' . DIRECTORY_SEPARATOR . strtolower($entityform) . '.yml';

        $code = $this->getRoutingCode(str_replace('/', '', $bundlename), $entityform);
        $fs->dumpFile($routingFile, $code);

        //Fixed: Adesso questa parte la fa da solo symfony (05/2015)
        //Refixed dalla versione 2.8 non lo fa più (04/2016)

        $dest = $this->apppath->getSrcPath() . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR .
                'Resources' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routing.yml';

        $routingContext = "\n" . str_replace('/', '', $bundlename) . '_' . $entityform . ': ' . "\n" .
                '  resource: "@' . str_replace('/', '', $bundlename) . '/Resources/config/routing/' . strtolower($entityform) . '.yml"' . "\n" .
                '  prefix: /' . $entityform . "\n";

        //Si fa l'append nel file routing del bundle per aggiungerci le rotte della tabella che stiamo gestendo
        $fh = fopen($dest, 'a');
        fwrite($fh, $routingContext);
        fclose($fh);
        $retmsg = 'Routing ' . $dest . " generato automaticamente da pannelloammonistrazionebundle\n\n* * * * CLEAR CACHE * * * *\n";

        return $retmsg;
    }

    public function generateFormWiew($bundlename, $entityform, $view)
    {
        $fs = new Filesystem();
        $folderview = $this->apppath->getSrcPath() . DIRECTORY_SEPARATOR . $bundlename . DIRECTORY_SEPARATOR .
                'Resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR .
                $entityform . DIRECTORY_SEPARATOR;
        $dest = $folderview . $view . '.html.twig';
        $fs->mkdir($folderview);
        file_put_contents($dest, "{% include 'FiCoreBundle:Standard:" . $view . ".html.twig' %}");
    }

    public function generateFormsDefaultTableValues($entityform)
    {
        //Si inserisce il record di default nella tabella permessi
        $em = $this->container->get('doctrine')->getManager();
        $ruoloAmm = $em->getRepository('FiCoreBundle:ruoli')->findOneBy(array('is_superadmin' => true)); //SuperAdmin

        $newPermesso = new \Fi\CoreBundle\Entity\Permessi();
        $newPermesso->setCrud('crud');
        $newPermesso->setModulo($entityform);
        $newPermesso->setRuoli($ruoloAmm);
        $em->persist($newPermesso);
        $em->flush();

        $tabelle = new \Fi\CoreBundle\Entity\Tabelle();
        $tabelle->setNometabella($entityform);
        $em->persist($tabelle);
        $em->flush();
    }

    public function getControllerCode($bundlename, $tabella)
    {
        $codeTemplate = <<<EOF
<?php
namespace [bundle]\Controller;

use Fi\CoreBundle\Controller\FiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Fi\CoreBundle\Controller\Griglia;
use Fi\CoreBundle\Controller\gestionepermessiController;
use [bundle]\Entity\[tabella];
use [bundle]\Form\[tabella]Type;


/**
* [tabella] controller.
*
*/

class [tabella]Controller extends FiController {

}
EOF;
        $codebundle = str_replace('[bundle]', $bundlename, $codeTemplate);
        $code = str_replace('[tabella]', $tabella, $codebundle);

        return $code;
    }

    public function getRoutingCode($bundlename, $tabella)
    {
        $codeTemplate = <<<'EOF'
[tabella]_container:
    path:  /
    defaults: { _controller: "[bundle]:[tabella]:index" }

[tabella]_new:
    path:  /new
    defaults: { _controller: "[bundle]:[tabella]:new" }

[tabella]_create:
    path:  /create
    defaults: { _controller: "[bundle]:[tabella]:create" }
    requirements: { methods: post }

[tabella]_edit:
    path:  /{id}/edit
    defaults: { _controller: "[bundle]:[tabella]:edit" }

[tabella]_update:
    path:  /{id}/update
    defaults: { _controller: "[bundle]:[tabella]:update" }
    requirements: { methods: post|put }

[tabella]_aggiorna:
    path:  /aggiorna
    defaults: { _controller: "[bundle]:[tabella]:aggiorna" }
    requirements: { methods: post|put }

[tabella]_delete:
    path:  /{id}/delete
    defaults: { _controller: "[bundle]:[tabella]:delete" }
    requirements: { methods: post|delete }

[tabella]_deletemultiple:
    path:  /delete
    defaults: { _controller: "[bundle]:[tabella]:delete" }
    requirements: { methods: post|delete }

[tabella]_griglia:
    path:  /griglia
    defaults: { _controller: "[bundle]:[tabella]:Griglia" }
    requirements: { methods: get|post }
EOF;
        $codebundle = str_replace('[bundle]', $bundlename, $codeTemplate);
        $code = str_replace('[tabella]', $tabella, $codebundle);

        return $code;
    }
}
