<?php

use app\models\TreeSpecies;
use yii\bootstrap5\Html;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string|null $q */

$this->title = 'Породы';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a('<i class="bi bi-plus-lg"></i> Добавить', ['create'], ['class' => 'btn btn-success']) ?>
</div>
<form class="mb-3" method="get"><input type="search" name="q" value="<?= Html::encode($q) ?>" class="form-control" placeholder="Поиск по названию"></form>
<p class="small text-muted">Порядок можно менять перетаскиванием строк. Импорт из CSV: <code>yii species/import file.csv</code>.</p>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle', 'data-sortable' => \yii\helpers\Url::to(['sort'])],
    'rowOptions' => static fn(TreeSpecies $m) => ['data-id' => $m->id, 'class' => $m->is_active ? '' : 'text-muted'],
    'columns' => [
        ['format' => 'raw', 'value' => static fn() => '<i class="bi bi-grip-vertical drag-handle"></i>', 'contentOptions' => ['style' => 'width:30px; cursor:move']],
        'name',
        'latin_name',
        'sort',
        'is_active:boolean',
        ['label' => 'Посадок', 'value' => static fn(TreeSpecies $m) => $m->getPlantings()->count()],
        [
            'format' => 'raw',
            'value' => static fn(TreeSpecies $m) => Html::a('<i class="bi bi-pencil"></i>', ['update', 'id' => $m->id], ['class' => 'btn btn-sm btn-outline-secondary'])
                . ' ' . Html::a($m->is_active ? '<i class="bi bi-toggle-on"></i>' : '<i class="bi bi-toggle-off"></i>', ['toggle', 'id' => $m->id], [
                    'class' => 'btn btn-sm btn-outline-secondary', 'data-method' => 'post', 'title' => 'Активность',
                ]),
        ],
    ],
]) ?>
