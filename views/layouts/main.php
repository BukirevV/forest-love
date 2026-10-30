<?php

use app\assets\AppAsset;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $content */

AppAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, shrink-to-fit=no']);
$this->registerMetaTag(['name' => 'theme-color', 'content' => '#2f6b3a']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/img/favicon.svg')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title ? $this->title . ' — ' . Yii::$app->name : Yii::$app->name) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<header><?= $this->render('//partials/_header') ?></header>

<main class="flex-shrink-0 py-4" role="main">
    <div class="container">
        <?php if (!empty($this->params['breadcrumbs'])): ?>
            <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs'], 'homeLink' => ['label' => 'Карта', 'url' => Yii::$app->homeUrl]]) ?>
        <?php endif ?>
        <?= $this->render('//partials/_flash') ?>
        <?= $content ?>
    </div>
</main>

<footer class="footer mt-auto py-4 bg-light border-top">
    <div class="container d-flex flex-wrap justify-content-between gap-2 small text-muted">
        <div>&copy; <?= Html::encode(Yii::$app->name) ?>, <?= date('Y') ?></div>
        <div class="d-flex gap-3">
            <?= Html::a('О проекте', ['/site/about']) ?>
            <?= Html::a('Политика конфиденциальности', ['/site/privacy']) ?>
            <?= Html::a('Пользовательское соглашение', ['/site/terms']) ?>
        </div>
    </div>
</footer>

<?= $this->render('//partials/_made_by') ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
