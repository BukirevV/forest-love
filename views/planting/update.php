<?php

use app\forms\PlantingForm;
use app\models\Planting;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var PlantingForm $model */
/** @var Planting $planting */
/** @var array $speciesList */

$this->title = 'Редактирование: ' . $planting->getDisplayTitle();
$this->params['breadcrumbs'][] = ['label' => 'Мои посадки', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $planting->getDisplayTitle(), 'url' => ['view', 'id' => $planting->id]];
$this->params['breadcrumbs'][] = 'Редактирование';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

<?php if ($planting->isPublished()): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i>
        Карточка опубликована. Изменения уйдут на повторную модерацию; до их одобрения на карте будет отображаться текущая версия.
        <?php if ($planting->hasPendingChanges()): ?>
            <br><strong>У карточки уже есть изменения на модерации — новая правка заменит их.</strong>
        <?php endif ?>
    </div>
<?php elseif ($planting->status !== 'draft'): ?>
    <div class="alert alert-info">После сохранения карточка будет отправлена на модерацию.</div>
<?php endif ?>

<?= $this->render('_form', [
    'model' => $model,
    'speciesList' => $speciesList,
    'isCreate' => false,
    'isPublished' => $planting->isPublished(),
]) ?>
