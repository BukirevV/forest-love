<?php

use app\forms\PlantingForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var PlantingForm $model */
/** @var array $speciesList */

$this->title = 'Новое дерево';
$this->params['breadcrumbs'][] = ['label' => 'Мои посадки', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model, 'speciesList' => $speciesList, 'isCreate' => true]) ?>
