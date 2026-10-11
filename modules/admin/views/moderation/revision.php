<?php

use app\forms\ModerationForm;
use app\models\PlantingRevision;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var PlantingRevision $revision */
/** @var array $diff */
/** @var ModerationForm $model */

$this->title = 'Изменения карточки #' . $revision->planting_id;
$this->params['breadcrumbs'][] = ['label' => 'Модерация', 'url' => ['index', 'tab' => 'revisions']];
$this->params['breadcrumbs'][] = $this->title;

$render = static function ($value, string $field): string {
    if ($field === 'links') {
        if (!$value) {
            return '<span class="text-muted">—</span>';
        }
        $items = array_map(static fn($l) => '<li>' . Html::a(Html::encode($l['url']), $l['url'], ['target' => '_blank', 'rel' => 'nofollow noopener'])
            . ($l['label'] ? ' — ' . Html::encode($l['label']) : '') . '</li>', $value);
        return '<ol class="mb-0 ps-3">' . implode('', $items) . '</ol>';
    }
    return $value === null || $value === '' ? '<span class="text-muted">—</span>' : nl2br(Html::encode((string)$value));
};
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p>
    <?= Html::a('Открыть карточку целиком', ['planting', 'id' => $revision->planting_id]) ?> ·
    Автор: <?= Html::encode($revision->user->getPrivateName()) ?> ·
    <?= Yii::$app->formatter->asDatetime($revision->created_at) ?>
</p>

<?php if (!$diff): ?>
    <div class="alert alert-info">Отличий от опубликованной версии нет.</div>
<?php else: ?>
    <table class="table table-bordered align-top">
        <thead class="table-light"><tr><th style="width: 20%">Поле</th><th>Было</th><th>Стало</th></tr></thead>
        <tbody>
        <?php foreach ($diff as $field => $row): ?>
            <tr>
                <th><?= Html::encode($row['label']) ?></th>
                <td class="diff-old"><?= $render($row['old'], $field) ?></td>
                <td class="diff-new"><?= $render($row['new'], $field) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>

<?php if ($revision->isPending()): ?>
    <div class="col-lg-6"><?= $this->render('_decision', ['model' => $model]) ?></div>
<?php else: ?>
    <div class="alert alert-secondary">Ревизия уже рассмотрена: <?= Html::encode(\app\enums\RevisionStatus::labelOf($revision->status)) ?>.</div>
<?php endif ?>
