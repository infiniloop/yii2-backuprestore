<div class="backup-default-index">

    <?php
    $this->params ['breadcrumbs'] [] = [
        'label' => 'Manage',
        'url' => array(
            'index'
        )
    ];
    ?>

    <div class="row">
        <div class="col-md-12">
            <?php
            if (!isset($dataProvider) || is_null($dataProvider))
                $dataProvider = new \yii\data\ArrayDataProvider(['allModels' => []]);
            echo $this->render('_list', array(
                'dataProvider' => $dataProvider
            ));
            ?>
        </div>
    </div>

</div>
