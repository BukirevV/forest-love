<?php

use app\enums\ModerationAction;
use app\enums\ModerationEntity;
use app\enums\PlantingStatus;
use app\models\ModerationLog;
use app\models\User;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var User $user */
/** @var array $byStatus */
/** @var int $namedCount */
/** @var ModerationLog[] $notifications */

$this->title = 'Личный кабинет';
$f = Yii::$app->formatter;
?>
<h1 class="h3 mb-4">Здравствуйте, <?= Html::encode($user->display_name ?: 'участник') ?>!</h1>

<div class="row g-3 mb-4">
    <?php foreach ([PlantingStatus::Approved, PlantingStatus::Pending, PlantingStatus::Draft, PlantingStatus::Rejected] as $status): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= \yii\helpers\Url::to(['/planting/index', 'PlantingSearch[status]' => $status->value]) ?>" class="card text-decoration-none h-100">
                <div class="card-body">
                    <div class="fs-2 fw-bold text-<?= $status->cssClass() ?>"><?= (int)($byStatus[$status->value] ?? 0) ?></div>
                    <div class="small text-muted"><?= Html::encode($status->label()) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<?php if ($namedCount): ?>
    <p><i class="bi bi-star-fill text-warning"></i> Именных деревьев: <?= $namedCount ?></p>
<?php endif ?>

<div class="d-flex gap-2 mb-4">
    <?= Html::a('<i class="bi bi-plus-circle"></i> Добавить дерево', ['/planting/create'], ['class' => 'btn btn-success']) ?>
    <?= Html::a('Мои посадки', ['/planting/index'], ['class' => 'btn btn-outline-secondary']) ?>
</div>

<h2 class="h5">Последние решения модерации</h2>
<?php if (!$notifications): ?>
    <p class="text-muted">Пока нет уведомлений.</p>
<?php else: ?>
    <ul class="list-group">
        <?php foreach ($notifications as $n): ?>
            <?php $approve = $n->action === ModerationAction::Approve->value; ?>
            <li class="list-group-item d-flex gap-3">
                <i class="bi <?= $approve ? 'bi-check-circle text-success' : 'bi-x-circle text-danger' ?> fs-5"></i>
                <div>
                    <div>
                        <?= Html::encode(ModerationEntity::labelOf($n->entity_type)) ?>
                        <?= $n->entity_type === 'planting' ? Html::a('#' . $n->entity_id, ['/planting/view', 'id' => $n->entity_id]) : '' ?>:
                        <strong><?= Html::encode(ModerationAction::labelOf($n->action)) ?></strong>
                    </div>
                    <?php if ($n->comment): ?><div class="small"><?= Html::encode($n->comment) ?></div><?php endif ?>
                    <div class="small text-muted"><?= $f->asDatetime($n->created_at) ?></div>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
