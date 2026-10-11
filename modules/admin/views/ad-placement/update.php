<?php

/** @var yii\web\View $this */
/** @var app\forms\AdPlacementForm $model */
/** @var app\models\AdPlacement $placement */
/** @var array $campaigns */

$this->title = 'Размещение #' . $placement->id;
$this->params['breadcrumbs'][] = ['label' => 'Размещения', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model, 'placement' => $placement, 'campaigns' => $campaigns]) ?>
