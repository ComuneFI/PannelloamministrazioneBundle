Installazione:

Aggiungere nel file composer.json (root del progetto) nella sezione:

{
"name": "symfony/framework-standard-edition",
    "license": "MIT",
    "type": "project",
    "description": "The \"Symfony Standard Edition\" distribution",
    
"autoload": {
		...,
        "psr-4" : {
            "Fi\\PannelloAmministrazioneBundle\\": "vendor/Fi/PannelloAmministrazioneBundle/"
        },
        ....
    },
}    

Modificare sempre in composer.json:

"repositories": [
        {   
            ...
           ,
            "type": "package",
            "package": {
                "name": "Fi/PannelloAmministrazioneBundle",
                "version": "dev-master",
                "source": {
                    "url": "http://pobogdso:Sviluppo2015@gitserver.comune.intranet/git/pannelloamministrazionebundle.git/",
                    "type": "git",
                    "reference": "master"
                }
            }
        }
    ]


Aggiungere nel file app/AppKernel.php nella funzione registerBundles;
...
new Fi\PannelloAmministrazioneBundle\FiPannelloAmministrazioneBundle(),
...    
	
Aggiungere nella routing dell'applicazione in app/config/routing.yml:
...
fi_pannello_amministrazione:
    resource: "@FiPannelloAmministrazioneBundle/Resources/config/routing.yml"
    prefix:   /
