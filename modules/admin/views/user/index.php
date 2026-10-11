<?php

use app\enums\UserStatus;
use app\models\User;
use app\widgets\StatusBadgeWidget;
use yii\bootstrap5\Html;
use yii\bootstrap5\LinkPager;
use yii\grid\GridView;

/** @var yii\web\View $this */
/** @var app\models\search\UserSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Пользователи';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'pager' => ['class' => LinkPager::class],
    'tableOptions' => ['class' => 'table table-sm table-hover align-middle'],
    'columns' => [
        'id',
        'email:email',
        'phone',
        'display_name',
        [
            'attribute' => 'status',
            'filter' => UserStatus::labels(),
            'format' => 'raw',
            'value' => static fn(User $u) => StatusBadgeWidget::widget(['status' => $u->status, 'enum' => UserStatus::class]),
        ],
        ['label' => 'Роли', 'value' => static fn(User $u) => implode(', ', $u->getRoles())],
        [
            'attribute' => 'created_at',
            'format' => 'date',
            'filter' => Html::activeInput('date', $searchModel, 'registered_from', ['class' => 'form-control form-control-sm'])
                . Html::activeInput('date', $searchModel, 'registered_to', ['class' => 'form-control form-control-sm mt-1']),
        ],
        'last_login_at:relativeTime',
        ['format' => 'raw', 'value' => static fn(User $u) => Html::a('<i class="bi bi-eye"></i>', ['view', 'id' => $u->id], ['class' => 'btn btn-sm btn-outline-secondary'])],
    ],
]) ?>
