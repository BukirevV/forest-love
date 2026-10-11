<?php

use app\enums\ModerationAction;
use app\enums\ModerationEntity;
use app\models\ModerationLog;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Журнал модерации';
$this->params['breadcrumbs'][] = ['label' => 'Модерация', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'pager' => ['class' => LinkPager::class],
    'tableOptions' => ['class' => 'table table-sm table-hover'],
    'columns' => [
        'created_at:datetime',
        ['attribute' => 'entity_type', 'value' => static fn(ModerationLog $l) => ModerationEntity::labelOf($l->entity_type)],
        'entity_id',
        ['attribute' => 'action', 'value' => static fn(ModerationLog $l) => ModerationAction::labelOf($l->action)],
        ['attribute' => 'moderator_id', 'value' => static fn(ModerationLog $l) => $l->moderator->getPrivateName()],
        'comment:ntext',
    ],
]) ?>
