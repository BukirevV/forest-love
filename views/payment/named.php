<?php

use app\forms\NamedOrderForm;
use app\models\Planting;
use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var Planting $planting */
/** @var NamedOrderForm $model */
/** @var array $tariffs */

$this->title = 'Именное дерево';
$this->params['breadcrumbs'][] = ['label' => 'Мои посадки', 'url' => ['/planting/index']];
$this->params['breadcrumbs'][] = ['label' => $planting->getDisplayTitle(), 'url' => ['/planting/view', 'id' => $planting->id]];
$this->params['breadcrumbs'][] = $this->title;
$f = Yii::$app->formatter;
?>
<h1 class="h3 mb-3"><i class="bi bi-star-fill text-warning"></i> <?= Html::encode($this->title) ?></h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h2 class="h5">Что даёт именное размещение</h2>
                <ul>
                    <li>Дерево выделяется на карте особым маркером.</li>
                    <li>В карточке отображается имя дерева или посвящение.</li>
                    <li>Бейдж «Именное дерево» на публичной странице.</li>
                </ul>
                <?php if (!$planting->isPublished()): ?>
                    <div class="alert alert-info small mb-3">Оплатить можно уже сейчас — именной статус будет виден на карте после публикации карточки.</div>
                <?php endif ?>

                <?php $form = ActiveForm::begin(['id' => 'named-form']) ?>
                    <?php if (count($tariffs) > 1): ?>
                        <?= $form->field($model, 'tariff')->radioList(array_map(
                            static fn($t) => $t['label'] . ' — ' . $f->asCurrency($t['price'], $t['currency']),
                            $tariffs
                        )) ?>
                    <?php else: ?>
                        <?= Html::activeHiddenInput($model, 'tariff') ?>
                    <?php endif ?>
                    <?= $form->field($model, 'title')->textInput(['maxlength' => 150, 'placeholder' => 'Например: «Дуб нашей семьи» или «В честь рождения Ани»']) ?>
                    <?= $form->field($model, 'agree')->checkbox([
                        'label' => 'Я принимаю ' . Html::a('условия именного размещения', ['/site/terms'], ['target' => '_blank']),
                    ]) ?>
                    <?php $t = $model->getTariff(); ?>
                    <div class="d-flex align-items-center gap-3">
                        <div class="fs-4 fw-bold"><?= $t['price'] > 0 ? $f->asCurrency($t['price'], $t['currency']) : 'Бесплатно' ?></div>
                        <?= Html::submitButton($t['price'] > 0 ? 'Перейти к оплате' : 'Оформить', ['class' => 'btn btn-warning btn-lg']) ?>
                    </div>
                    <div class="small text-muted mt-2"><?= Html::encode($t['label']) ?></div>
                <?php ActiveForm::end() ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <?php if ($planting->mainPhoto): ?>
                <img src="<?= Html::encode($planting->mainPhoto->getThumbUrl()) ?>" class="card-img-top" alt="">
            <?php endif ?>
            <div class="card-body">
                <div class="fw-semibold"><?= Html::encode($planting->species?->name) ?></div>
                <div class="small text-muted"><?= $planting->planted_at ? $f->asDate($planting->planted_at, 'long') : '' ?></div>
            </div>
        </div>
    </div>
</div>
