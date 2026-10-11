<?php

use app\widgets\BannerWidget;
use app\widgets\MapWidget;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var array $speciesList */
/** @var int|null $focusTreeId */

$this->title = Yii::$app->name . ' — карта посаженных деревьев';
?>
<div class="map-page">
    <?= MapWidget::widget([
        'mode' => MapWidget::MODE_PUBLIC,
        'options' => ['id' => 'public-map', 'class' => 'map-page__map'],
        'clientOptions' => [
            'filtersForm' => '#map-filters',
            'sidePanel' => '#tree-panel',
            'focusTreeId' => $focusTreeId,
        ],
    ]) ?>

    <!-- Панель фильтров -->
    <form id="map-filters" class="map-filters card shadow-sm" onsubmit="return false">
        <div class="card-body p-2 d-flex flex-wrap gap-2 align-items-center">
            <?= Html::dropDownList('species_id', null, $speciesList, [
                'class' => 'form-select form-select-sm',
                'prompt' => 'Все породы',
                'aria-label' => 'Порода',
            ]) ?>
            <div class="form-check form-switch mb-0">
                <?= Html::checkbox('named', false, ['value' => 1, 'class' => 'form-check-input', 'id' => 'filter-named']) ?>
                <label class="form-check-label small" for="filter-named">Только именные</label>
            </div>
        </div>
    </form>

    <!-- Легенда -->
    <div class="map-legend card shadow-sm small">
        <div class="card-body p-2">
            <div><img src="<?= Yii::getAlias('@web/img/marker-tree.svg') ?>" alt="" width="16"> Дерево</div>
            <div><img src="<?= Yii::getAlias('@web/img/marker-named.svg') ?>" alt="" width="16"> Именное дерево</div>
            <div><img src="<?= Yii::getAlias('@web/img/marker-ad.svg') ?>" alt="" width="16"> Партнёр проекта</div>
        </div>
    </div>

    <!-- Боковая панель карточки дерева -->
    <aside id="tree-panel" class="tree-panel" aria-live="polite" hidden>
        <button type="button" class="btn-close tree-panel__close" aria-label="Закрыть" data-panel-close></button>
        <div class="tree-panel__content" data-panel-content></div>
    </aside>

    <!-- Баннеры поверх карты -->
    <?= BannerWidget::widget() ?>

    <div class="map-status small" data-map-status hidden></div>
</div>
