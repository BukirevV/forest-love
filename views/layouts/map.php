<?php

use app\assets\MapAsset;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $content */

MapAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1, maximum-scale=1']);
$this->registerMetaTag(['name' => 'theme-color', 'content' => '#2f6b3a']);
$this->registerMetaTag(['name' => 'description', 'content' => 'Лес Любви — карта посаженных деревьев. Посадите дерево и отметьте его на карте.']);
$this->registerLinkTag(['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => Yii::getAlias('@web/img/favicon.svg')]);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode($this->title ?: Yii::$app->name) ?></title>
    <?php $this->head() ?>
</head>
<body class="map-layout">
<?php $this->beginBody() ?>

<header class="map-layout__header"><?= $this->render('//partials/_header', ['overlay' => true]) ?></header>

<div class="map-layout__flash"><?= $this->render('//partials/_flash') ?></div>

<?= $content ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
