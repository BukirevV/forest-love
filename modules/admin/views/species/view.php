<?php

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\TreeSpecies $model */
/** @var int $plantingsCount */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Породы', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p>
    <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a($model->is_active ? 'Деактивировать' : 'Активировать', ['toggle', 'id' => $model->id], ['class' => 'btn btn-outline-secondary', 'data-method' => 'post']) ?>
    <?php if (!$plantingsCount): ?>
        <?= Html::a('Удалить', ['delete', 'id' => $model->id], ['class' => 'btn btn-outline-danger', 'data-method' => 'post', 'data-confirm' => 'Удалить породу?']) ?>
    <?php endif ?>
</p>
<?= DetailView::widget(['model' => $model, 'attributes' => [
    'id', 'name', 'latin_name', 'description:ntext', 'icon', 'sort', 'is_active:boolean',
    ['label' => 'Посадок', 'value' => $plantingsCount],
    'created_at:datetime', 'updated_at:datetime',
]]) ?>
