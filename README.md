PannelloAmministrazioneBundle
=============
[![Build Status](https://travis-ci.org/ComuneFI/PannelloamministrazioneBundle.svg?branch=master)]
(https://travis-ci.org/ComuneFI/PannelloamministrazioneBundle) [![Coverage Status](https://img.shields.io/coveralls/ComuneFI/PannelloamministrazioneBundle.svg)] 
(https://coveralls.io/r/ComuneFI/PannelloamministrazioneBundle)

Installazione:
-------------

- Aggiungere nel file composer.json (root del progetto) nella sezione:
```
    {
    "name": "ComuneFI/NomeProgetto",
        "license": "MIT",
        "type": "project",
        "description": "The \"Symfony Standard Edition\" distribution",

    "autoload": {
            "psr-4" : {
                "Fi\\PannelloAmministrazioneBundle\\": "vendor/fi/pannelloamministrazionebundle/",
                "Fi\\PannelloamministrazioneBundle\\": "vendor/fi/osbundle/"

            }
        },
    }    
```
- Aggiungere sempre in composer.json:
```
    "fi/pannelloamministrazionebundle": "2.0.*"
```
- Aggiungere nel file app/AppKernel.php nella funzione registerBundles;
```
    new Fi\PannelloAmministrazioneBundle\FiPannelloAmministrazioneBundle(),
```
- Aggiungere nella routing dell'applicazione in app/config/routing.yml:
```
    fi_pannello_amministrazione:
        resource: "@FiPannelloAmministrazioneBundle/Resources/config/routing.yml"
        prefix:   /
```
- Infine lanciare 
```
    assets:install
```
