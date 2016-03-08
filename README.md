PannelloAmministrazioneBundle
=============

Installazione:
-------------

- Aggiungere nel file composer.json (root del progetto) nella sezione:
```
    {
    "name": "symfony/framework-standard-edition",
        "license": "MIT",
        "type": "project",
        "description": "The \"Symfony Standard Edition\" distribution",

    "autoload": {
            "psr-4" : {
                "Fi\\PannelloAmministrazioneBundle\\": "vendor/fi/pannelloamministrazionebundle/",
                "Fi\\OsBundle\\": "vendor/fi/osbundle/"

            }
        },
    }    
```
- Aggiungere sempre in composer.json:
```
    "repositories": [
            {   
                {"type": "vcs","url": "https://github.com/manzolo/pannelloamministrazionebundle.git"},
                {"type": "vcs", "url": "http://pobogdso:Sviluppo2015@gitserver.comune.intranet/git/osbundle.git/"}

            }
    ]
```
- E sempre nel composer.json, nella sezione require aggiungere:
```
    "fi/pannelloamministrazionebundle": "1.0.*",
    "fi/osbundle": "1.0.*",
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
