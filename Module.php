<?php
namespace infiniloop\modules\backuprestore;

use Yii;

class Module extends \yii\base\Module
{
    public $controllerNamespace = 'infiniloop\modules\backuprestore\controllers';

    // list of databases as defined in the components objects of the current app
    public $databases = [];

    // folder where all backups will be located, relative to the root folder app
    public $backupFolder = '_backup';

    // path where the mysql binaries are located, if empty it expects they're available globally
    public $mySqlBasePath = '';

    public function init()
    {
        parent::init();

        if (empty($this->databases)) {
            $components = \Yii::$app->getComponents();
            foreach ($components as $key => $component) {
                if (array_key_exists('class', $component) && $component['class'] === 'yii\db\Connection') {
                    $this->databases[] = $key;
                }
            }
        }
    }
}
