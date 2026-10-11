<?php

use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $name */
/** @var string $message */
/** @var Exception $exception */

$this->title = $name;
$code = $exception instanceof \yii\web\HttpException ? $exception->statusCode : 500;
?>
<div class="site-error text-center py-5">
    <div class="display-1 text-success"><i class="bi bi-tree"></i></div>
    <h1><?= Html::encode($this->title) ?></h1>
    <div class="alert alert-light d-inline-block"><?= nl2br(Html::encode($message)) ?></div>
    <p class="text-muted">
        <?= $code === 404 ? 'Страница не найдена.' : 'Произошла ошибка при обработке запроса.' ?>
    </p>
    <p><?= Html::a('На карту', ['/site/index'], ['class' => 'btn btn-success']) ?></p>
</div>
