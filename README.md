PannelloAmministrazioneBundle
=============
[![Build Status](https://travis-ci.org/ComuneFI/PannelloamministrazioneBundle.svg?branch=master)]
(https://travis-ci.org/ComuneFI/PannelloamministrazioneBundle) [![Coverage Status](https://img.shields.io/coveralls/ComuneFI/PannelloamministrazioneBundle.svg)] 
(https://coveralls.io/r/ComuneFI/PannelloamministrazioneBundle)

Installazione:
-------------

- Aggiungere nel file composer.json (root del progetto) nella sezione:
```
composer require "fi/pannelloamministrazionebundle"
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

- Test
Scarico dipendenze
```
    composer install
```
Preparare il db
```
    rm src/Tests/app/dbtest.sqlite
    php src/Tests/app/console fifree:install admin admin admin@admin.it --env=test
    rm -rf src/Tests/app/cache/test
    rm -rf src/Tests/app/cache/dev
```
Assets install
```
php src/Tests/app/console assets:install src/Tests/app --env=test
```
Start server
```
php src/Tests/app/console server:run -d src/Tests/app --env=test 2>&1 &
sh vendor/bin/selenium-server-standalone > /dev/null 2>&1 &
rm -rf src/Tests/app/cache/test
rm -rf src/Tests/app/cache/dev
```
Lanciare i test
```
    vendor/bin/phpunit
```



