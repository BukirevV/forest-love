<?php
/** @var \app\models\Planting|null $planting */
/** @var string|null $comment */
/** @var bool|null $isRevision */
/** @var string $url */
?>
Здравствуйте!

<?= !empty($isRevision) ? 'Изменения карточки' : 'Карточка посадки' ?><?= $planting ? ' «' . $planting->getDisplayTitle() . '»' : '' ?> не прошли модерацию.
<?php if (!empty($comment)): ?>

Комментарий модератора: <?= $comment ?>

<?php endif ?>

Открыть карточку: <?= $url ?>
