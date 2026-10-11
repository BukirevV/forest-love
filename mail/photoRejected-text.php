<?php
/** @var \app\models\PlantingPhoto|null $photo */
/** @var string|null $comment */
/** @var string $url */
?>
Здравствуйте!

Фотография<?= $photo ? ' от ' . Yii::$app->formatter->asDate($photo->taken_at, 'long') : '' ?> не прошла модерацию.
<?php if (!empty($comment)): ?>
Комментарий модератора: <?= $comment ?>

<?php endif ?>
Открыть карточку: <?= $url ?>
