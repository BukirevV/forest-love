<?php

use app\assets\AdminAsset;
use app\rbac\Rbac;
use app\services\ModerationService;
use yii\bootstrap5\Breadcrumbs;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $content */

AdminAsset::register($this);
$this->registerCsrfMetaTags();
$this->registerMetaTag(['charset' => Yii::$app->charset], 'charset');
$this->registerMetaTag(['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']);
$this->registerMetaTag(['name' => 'robots', 'content' => 'noindex, nofollow']);

$queue = Yii::$container->get(ModerationService::class)->getQueueCounts();
$queueTotal = array_sum($queue);
$user = Yii::$app->user;
$c = Yii::$app->controller->id;

$menu = [
    ['<i class="bi bi-speedometer2"></i> Обзор', ['/admin/default/index'], 'default', Rbac::MODERATE],
    ['<i class="bi bi-shield-check"></i> Модерация' . ($queueTotal ? ' <span class="badge text-bg-danger">' . $queueTotal . '</span>' : ''), ['/admin/moderation/index'], 'moderation', Rbac::MODERATE],
    ['<i class="bi bi-tree"></i> Посадки', ['/admin/planting/index'], 'planting', Rbac::ROLE_ADMIN],
    ['<i class="bi bi-flower1"></i> Породы', ['/admin/species/index'], 'species', Rbac::MANAGE_SPECIES],
    ['<i class="bi bi-people"></i> Пользователи', ['/admin/user/index'], 'user', Rbac::MANAGE_USERS],
    ['<i class="bi bi-shield-lock"></i> Права доступа', ['/admin/access/index'], 'access', Rbac::MANAGE_USERS],
    ['<i class="bi bi-credit-card"></i> Платежи', ['/admin/payment/index'], 'payment', Rbac::MANAGE_PAYMENTS],
    ['<i class="bi bi-building"></i> Рекламодатели', ['/admin/advertiser/index'], 'advertiser', Rbac::MANAGE_ADS],
    ['<i class="bi bi-megaphone"></i> Кампании', ['/admin/ad-campaign/index'], 'ad-campaign', Rbac::MANAGE_ADS],
    ['<i class="bi bi-pin-map"></i> Размещения', ['/admin/ad-placement/index'], 'ad-placement', Rbac::MANAGE_ADS],
    ['<i class="bi bi-gear"></i> Настройки', ['/admin/settings/index'], 'settings', Rbac::MANAGE_SETTINGS],
];
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title><?= Html::encode(($this->title ? $this->title . ' — ' : '') . 'Админка') ?></title>
    <?php $this->head() ?>
</head>
<body class="admin-layout">
<?php $this->beginBody() ?>
<nav class="navbar navbar-dark bg-dark px-3">
    <a class="navbar-brand" href="<?= \yii\helpers\Url::to(['/admin/default/index']) ?>"><i class="bi bi-tree-fill text-success"></i> Лес Любви · админка</a>
    <div class="d-flex gap-3 align-items-center small text-white-50">
        <span>Очередь: карточки <?= $queue['plantings'] ?> · изменения <?= $queue['revisions'] ?> · фото <?= $queue['photos'] ?></span>
        <?= Html::a('На сайт', ['/site/index'], ['class' => 'link-light']) ?>
        <?= Html::beginForm(['/auth/logout'], 'post') . Html::submitButton('Выйти', ['class' => 'btn btn-sm btn-outline-light']) . Html::endForm() ?>
    </div>
</nav>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-2 bg-light border-end min-vh-100 py-3 admin-sidebar">
            <nav class="nav flex-column">
                <?php foreach ($menu as [$label, $url, $id, $perm]): ?>
                    <?php if ($user->can($perm)): ?>
                        <?= Html::a($label, $url, ['class' => 'nav-link' . ($c === $id ? ' active fw-semibold' : '')]) ?>
                    <?php endif ?>
                <?php endforeach ?>
            </nav>
        </aside>
        <main class="col-md-10 py-3">
            <?php if (!empty($this->params['breadcrumbs'])): ?>
                <?= Breadcrumbs::widget(['links' => $this->params['breadcrumbs'], 'homeLink' => ['label' => 'Админка', 'url' => ['/admin/default/index']]]) ?>
            <?php endif ?>
            <?= $this->render('@app/views/partials/_flash') ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
