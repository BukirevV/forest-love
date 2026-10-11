<?php

use app\models\Planting;
use app\models\PlantingRevision;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\data\ActiveDataProvider;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var string $tab */
/** @var array $counts */
/** @var ActiveDataProvider $dataProvider */

$this->title = 'Модерация';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= $this->render('_tabs', ['tab' => $tab, 'counts' => $counts]) ?>

<?php if ($tab === 'plantings'): ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'pager' => ['class' => LinkPager::class],
        'tableOptions' => ['class' => 'table table-hover align-middle'],
        'emptyText' => 'Очередь пуста.',
        'columns' => [
            'id',
            [
                'label' => 'Фото',
                'format' => 'raw',
                'value' => static fn(Planting $p) => $p->mainPhoto ? Html::img($p->mainPhoto->getThumbUrl(), ['width' => 80, 'class' => 'rounded']) : '—',
            ],
            ['label' => 'Порода', 'value' => 'species.name'],
            'planted_at:date',
            ['label' => 'Владелец', 'value' => static fn(Planting $p) => $p->user->getPrivateName()],
            ['attribute' => 'created_at', 'format' => 'datetime', 'label' => 'Создана'],
            ['attribute' => 'updated_at', 'format' => 'relativeTime', 'label' => 'В очереди'],
            [
                'format' => 'raw',
                'value' => static fn(Planting $p) => Html::a('Рассмотреть', ['planting', 'id' => $p->id], ['class' => 'btn btn-sm btn-primary']),
            ],
        ],
    ]) ?>
<?php else: ?>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'pager' => ['class' => LinkPager::class],
        'tableOptions' => ['class' => 'table table-hover align-middle'],
        'emptyText' => 'Очередь пуста.',
        'columns' => [
            'id',
            [
                'label' => 'Карточка',
                'format' => 'raw',
                'value' => static fn(PlantingRevision $r) => Html::a('#' . $r->planting_id . ' ' . Html::encode($r->planting->getDisplayTitle()),
                    ['planting', 'id' => $r->planting_id]),
            ],
            ['label' => 'Автор', 'value' => static fn(PlantingRevision $r) => $r->user->getPrivateName()],
            ['label' => 'Изменено полей', 'value' => static fn(PlantingRevision $r) => count($r->getDiff())],
            'created_at:datetime',
            [
                'format' => 'raw',
                'value' => static fn(PlantingRevision $r) => Html::a('Сравнить', ['revision', 'id' => $r->id], ['class' => 'btn btn-sm btn-primary']),
            ],
        ],
    ]) ?>
<?php endif ?>
