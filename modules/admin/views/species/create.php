<?php

/** @var yii\web\View $this */
/** @var app\models\TreeSpecies $model */

$this->title = 'Новая порода';
$this->params['breadcrumbs'][] = ['label' => 'Породы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model]) ?>
