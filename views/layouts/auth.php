<?php

use app\assets\AppAsset;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $content */

AppAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']);
$this->registerMetaTag(['name' => 'robots', 'content' => 'noindex']);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode(($this->title ? $this->title . ' — ' : '') . Yii::$app->name) ?></title>
    <?php $this->head() ?>
</head>
<body class="auth-layout d-flex align-items-center min-vh-100 py-4">
<?php $this->beginBody() ?>
<main class="container" style="max-width: 460px">
    <div class="text-center mb-4">
        <a href="<?= Yii::$app->homeUrl ?>" class="text-decoration-none text-success fs-3 fw-bold">
            <i class="bi bi-tree-fill"></i> <?= Html::encode(Yii::$app->name) ?>
        </a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <?= $this->render('//partials/_flash') ?>
            <?= $content ?>
        </div>
    </div>
    <p class="text-center small text-muted mt-3">
        <?= Html::a('Политика конфиденциальности', ['/site/privacy']) ?> · <?= Html::a('Соглашение', ['/site/terms']) ?>
    </p>
</main>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
