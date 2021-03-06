<?php
namespace infiniloop\modules\backuprestore\controllers;

use Yii;
use yii\base\Exception;
use yii\web\Controller;
use infiniloop\modules\backuprestore\models\UploadForm;
use yii\data\ArrayDataProvider;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\UploadedFile;
use yii\helpers\Html;
use yii\base\ErrorException;
use ZipArchive;

class DefaultController extends Controller {

    public $menu = [];
    public $tables = [];
    public $fp;
    public $file_name;
    public $_path = null;

    //public $layout = '//layout2';

    protected function getPath() {
        if (isset($this->module->path))
            $this->_path = $this->module->path;
        else
            $this->_path = sprintf('%s/%s/', Yii::$app->basePath, $this->module->backupFolder);

        if (!file_exists($this->_path)) {
            $dirMode = 0777;
            mkdir($this->_path, $dirMode, true);
            chmod($this->_path, $dirMode);
        }
        return $this->_path;
    }

    /**
     * Perform backup in the configured folder. Save a backup for each database configured with a name in this format:
     *      {timestamp}__{dbKey}__{db_name}{#if customName then '_customName'}.zip
     * @param null $dbKey   database configuration key, otherwise uses the module's configured databases value
     * @return object
     */
    public function DoBackup($dbKey = null, $customName = null) {

        $response = (object)[ 'success' => false , 'message' => '', 'backupFiles' => [] ];

        if (!is_writeable($this->path)) {
            $response->message = sprintf('Path <b>%s</b> is not writeable!', $this->path);
            return $response;
        }

        $dbKeys = is_null($dbKey) ? $this->module->databases : [$dbKey];

        $invalidDbNames = [];
        $backupFiles = [];
        foreach($dbKeys as $dbConfigKey) {

            try {
                $db = Yii::$app->get($dbConfigKey);
                $username = $db->username;
                $password = $db->password;
                # sample dsn: 'mysql:host=localhost;dbname=decent_yii'
                $dbName = explode('dbname=', $db->dsn)[1];
            } catch (Exception $e) {
                $invalidDbNames[] = $dbConfigKey;
                break;
            }

            try {

                $backupFileName = sprintf('%s__%s__%s%s',
                    date('Y.m.d_H.i.s'), $dbConfigKey, $dbName,
                    is_null($customName) ? '' : "_{$customName}");
                $backupFileWithPath = $this->path . $backupFileName;

                $suffix = time();
                #Execute the command to create backup sql file
                $backupCommand = $this->module->mysqlBasePath
                    . "mysqldump --user={$username} --password={$password}"
                    . " --quick --add-drop-table --add-locks --extended-insert --lock-tables"
                    . " {$dbName} > {$backupFileWithPath}.sql";
                exec($backupCommand, $output, $return_var);

                if ($return_var > 0) {
                    $backupCommand = str_replace(
                        [ $username, $password ],
                        [ '[actual_user_removed]', '[actual_password_removed]' ],
                        $backupCommand
                    );
                    throw new Exception(
                        "mysqldump command failed<br/>"
                        . "{$backupCommand}"
                        . ( count($output) > 0 ?
                            ( "Command output:<br/>" . implode('<br/>', $output) )
                            : ""
                        )
                    );
                }

                #Now zip that file
                $zip = new ZipArchive();
                $filename = "{$backupFileWithPath}.zip";
                if ($zip->open($filename, ZIPARCHIVE::CREATE) !== true) {
                    throw new Exception("Cannot open <$filename>n");
                }
                $zip->addFile("{$backupFileWithPath}.sql", "{$backupFileName}.sql");
                $zip->close();
                #Now delete the .sql file without any warning
                @unlink("{$backupFileWithPath}.sql");

                $response->backupFiles[$dbConfigKey] = "{$backupFileName}.zip";
            } catch (Exception $e) {
                $response->message = 'Failed to create the backup!<br/>' . $e->getMessage();
                return $response;
            }
        }

        //render error
        if (count($invalidDbNames) > 0) {
            $response->message = 'Failed to create backups!'
                . '<br/>There is no database configured in the components section with the name:'
                . '<br/><b>' . implode(',', $invalidDbNames) . '</b>'
                . '<br/>' . 'or the configuration does NOT include both username and password properties';
            return $response;
        }

        $response->message = "Backup files were created:<br/>" . implode('<br/>', $response->backupFiles);
        $response->success = true;
        return $response;
    }

    private function _outputError($errorMessage) {
        $flashError = 'error';
        $flashMsg = $errorMessage;
        \Yii::$app->getSession()->setFlash($flashError, $flashMsg);
        return $this->render('index');
    }

    public function actionCreate() {
        $flashError = '';
        $flashMsg = '';

        $outcome = $this->DoBackup();
        if ($outcome->success) {
            \Yii::$app->getSession()->setFlash('success', $outcome->message);
            $this->redirect(array('index'));
        } else {
            return $this->_outputError($outcome->message);
        }
    }

    public function actionDelete($file = null) {
        $flashError = '';
        $flashMsg = '';

        if (is_null($file))
            $file = $_GET['filename'];

//        $this->updateMenuItems();
        if (isset($file)) {
            $sqlFile = $this->path . basename($file);
            if (file_exists($sqlFile)) {
                unlink($sqlFile);
                $flashError = 'success';
                $flashMsg = 'The file ' . $sqlFile . ' was successfully deleted.';
            } else {
                $flashError = 'error';
                $flashMsg = 'The file ' . $sqlFile . ' was not found.';
            }
        } else {
            $flashError = 'error';
            $flashMsg = 'The file ' . $sqlFile . ' was not found.';
        }
        \Yii::$app->getSession()->setFlash($flashError, $flashMsg);
        $this->redirect(array('index'));
    }

    public function actionDownload($file = null) {
//        $this->updateMenuItems();
        if (isset($file)) {
            $sqlFile = $this->path . basename($file);
            if (file_exists($sqlFile)) {
                $request = Yii::$app->getRequest();
                $request->sendFile(basename($sqlFile), file_get_contents($sqlFile));
            }
        }
        throw new CHttpException(404, Yii::t('app', 'File not found'));
    }

    public function actionIndex() {
        //$this->layout = 'column1';
//        $this->updateMenuItems();
        $path = $this->path;
        $dataArray = array();

        $list_files = glob($path . '*.zip');
        if ($list_files) {
            $list = array_map('basename', $list_files);
            sort($list);
            foreach ($list as $id => $filename) {
                $columns = array();
                $columns['id'] = $id;
                $columns['name'] = basename($filename);
                $columns['size'] = filesize($path . $filename);

                $columns['create_time'] = date('Y-m-d H:i:s', filectime($path . $filename));
                $columns['modified_time'] = date('Y-m-d H:i:s', filemtime($path . $filename));

                $dataArray[] = $columns;
            }
        }
        $dataProvider = new ArrayDataProvider(['allModels' => $dataArray]);
        return $this->render('index', array(
                    'dataProvider' => $dataProvider,
        ));
    }


    /**
     * @param $fileName     Filename to be restored. Will be loaded from the configured path.
     * @param null $dbKey   dbKey configuration used to retreive database configuration (ie: db name, username, password)
     *                      If null, it'll parse the backup file generated by the DoBackup method
     * @return object
     */
    public function DoRestore($fileName, $dbKey = null) {

        $response = (object)[ 'success' => false , 'message' => '' ];

        if (!is_writeable($this->path)) {
            $response->message = sprintf('Path <b>%s</b> is not writeable!', $this->path);
            return $response;
        }

        $sqlRestoreFile = $this->path . str_replace('zip', 'sql', $fileName);
        $f = fopen($sqlRestoreFile, 'w+');
        if (!$f) {
            $response->message = sprintf('Unable to write the sql file (%s) for restore process!', $sqlRestoreFile);
            return $response;
        }

        try {
            $dbConfigKey = $dbKey;
            # try to guess the key from the auto-generated filename
            if (is_null($dbKey)) {
                $pattern = '(?<timestamp_date>[0-9.]+)_(?<timestamp_time>[0-9.]+)__(?<dbkey>[a-zA-Z0-9_]+)__(?<dbname_customname>\w+)\.(?<extension>\w+)';
                preg_match("/^{$pattern}$/", $fileName, $matches);
                if (!array_key_exists('dbkey', $matches)) {
                    $response->message = sprintf('Unable to parse a dbkey from the backup filename (%s)!', $fileName);
                    return $response;
                }
                $dbConfigKey = $matches['dbkey'];
            }
            $db = Yii::$app->get($dbConfigKey);
            $username = $db->username;
            $password = $db->password;
            # sample dsn: 'mysql:host=localhost;dbname=decent_yii'
            $dbName = explode('dbname=', $db->dsn)[1];
        } catch (Exception $e) {
            $response->message = sprintf('There is no configuration for the given database key (%s)!', $dbConfigKey);
            return $response;
        }

        $zip = new ZipArchive();
        if ($zip->open($this->path . $fileName) === TRUE) {
            #Get the backup content, expecting one backup sql per zip
            $sql = $zip->getFromIndex(0);
            #Close the Zip File
            $zip->close();
            #Prepare the sql file
            fwrite($f , $sql);
            fclose($f);
            #Now restore from the .sql file
            $command = $this->module->mysqlBasePath
                . "mysql --user={$username} --password={$password} --database={$dbName} < {$sqlRestoreFile}";
            exec($command, $output, $return_var);

            if ($return_var > 0) {
                $command = str_replace(
                    [ $username, $password ],
                    [ '[actual_user_removed]', '[actual_password_removed]' ],
                    $command
                );
                $response->message = "mysqldump command failed<br/>"
                    . "{$command}"
                    . ( count($output) > 0 ?
                        ( "Command output:<br/>" . implode('<br/>', $output) )
                        : ""
                    );
                return $response;
            }

            #Delete temporary files without any warning
            @unlink("{$sqlRestoreFile}");

            $response->success = true;
            $response->message = "Restored backup successfully to database: " . $dbName;
            return $response;
        }
        else {
            $response->message = sprintf('Failed to load the zip file (%s)!', $this->path . $fileName);
            return $response;
        }
    }

    public function actionRestore($file = null) {
        $flashError = '';
        $flashMsg = '';

        if (is_null($file))
            $file = $_GET['filename'];

        $response = $this->DoRestore($file);

        $flashError = $response->success ? 'success' : 'error';
        $flashMsg = $response->message;

        \Yii::$app->getSession()->setFlash($flashError, $flashMsg);
        $this->redirect(array('index'));
    }

    public function actionUpload() {
        $model = new UploadForm();
        if (isset($_POST['UploadForm'])) {
            $model->attributes = $_POST['UploadForm'];
            //oe change cUploaded for this
            $model->upload_file = UploadedFile::getInstance($model, 'upload_file');
            if ($model->upload_file->saveAs($this->path . $model->upload_file)) {
                // redirect to success page
                return $this->redirect(array('index'));
            }
        }

        return $this->render('upload', array('model' => $model));
    }
}
