<?php

use yii\helpers\Html;

/** @var \app\models\PlantingPhoto|null $photo */
/** @var string|null $comment */
/** @var string $url */
?>
<p>Здравствуйте!</p>
<p>Фотография<?= $photo ? ' от ' . Yii::$app->formatter->asDate($photo->taken_at, 'long') : '' ?> не прошла модерацию.</p>
<?php if (!empty($comment)): ?>
    <p style="background:#fff4f4;border-left:3px solid #c0392b;padding:8px 12px;"><strong>Комментарий модератора:</strong><br><?= nl2br(Html::encode($comment)) ?></p>
<?php endif ?>
<p><?= Html::a('Открыть карточку', $url, ['style' => 'display:inline-block;background:#2f6b3a;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;']) ?></p>
