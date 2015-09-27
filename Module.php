<?php
namespace infiniloop\modules\backuprestore;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'infiniloop\modules\backuprestore\controllers';

    // list of databases as defined in the components objects of the current app
    public $databases = [];

    public function init()
    {
        parent::init();
    }
}
