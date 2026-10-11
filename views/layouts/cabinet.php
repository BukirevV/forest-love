<?php

use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var string $content */

$controller = Yii::$app->controller->id;
$action = Yii::$app->controller->action->id;
$items = [
    ['<i class="bi bi-speedometer2"></i> Сводка', ['/cabinet/index'], $controller === 'cabinet' && $action === 'index'],
    ['<i class="bi bi-tree"></i> Мои посадки', ['/planting/index'], in_array($controller, ['planting', 'photo'], true) && $action !== 'create'],
    ['<i class="bi bi-plus-circle"></i> Добавить дерево', ['/planting/create'], $controller === 'planting' && $action === 'create'],
    ['<i class="bi bi-credit-card"></i> Платежи', ['/payment/history'], $controller === 'payment'],
    ['<i class="bi bi-person"></i> Профиль', ['/cabinet/profile'], $controller === 'cabinet' && $action === 'profile'],
];

$this->beginContent('@app/views/layouts/main.php');
?>
<div class="row g-4">
    <aside class="col-md-3">
        <nav class="list-group cabinet-menu">
            <?php foreach ($items as [$label, $url, $active]): ?>
                <?= Html::a($label, $url, ['class' => 'list-group-item list-group-item-action' . ($active ? ' active' : '')]) ?>
            <?php endforeach ?>
        </nav>
    </aside>
    <section class="col-md-9">
        <?= $content ?>
    </section>
</div>
<?php $this->endContent() ?>
