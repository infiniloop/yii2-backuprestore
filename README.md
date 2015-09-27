Yii2 Backup and Restore Database
===================
Database Backup and Restore functionality

A fork of open-ecommerce/yii2-backuprestore which in turn is based on:
https://github.com/spanjeta/yii2-backup and other yii1 similar backup-restore extensions 
Converted to yii2 and made more intuitive using the Kartik extensions.


Demo
-----
Simple demo to see the screens and a proof of concept
http://yii2.oe-lab.tk/



Installation
------------

Requirements
I am usin some Kartik extensions that you will need to install and configure in order to work:
php composer.phar require kartik-v/yii2-grid "*"
php composer.phar require kartik-v/yii2-widget-fileinput "*"


The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist infiniloop/yii2-backuprestore "dev-master"
```

or add

```
"infiniloop/yii2-backuprestore": "dev-master"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply add it in your config by  :

Basic ```config/web.php```

Advanced ```[backend|frontend|common]/config/main.php```

>
        'backuprestore' => [
            'class' => '\oe\modules\backuprestore\Module',
            //'layout' => '@admin-views/layouts/main', or what ever layout you use
            ...
            ...
        ],

make sure you create a writable directory named _backup on app root directory.

Pretty Url's ```/backuprestore```

No pretty Url's ```index.php?r=backuprestore```

