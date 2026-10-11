<?php

use yii\bootstrap5\Html;
use yii\widgets\DetailView;

/** @var yii\web\View $this */
/** @var app\models\Advertiser $model */

$this->title = $model->name;
$this->params['breadcrumbs'][] = ['label' => 'Рекламодатели', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<p>
    <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Новая кампания', ['/admin/ad-campaign/create', 'advertiser_id' => $model->id], ['class' => 'btn btn-outline-success']) ?>
    <?= Html::a('Удалить', ['delete', 'id' => $model->id], ['class' => 'btn btn-outline-danger', 'data-method' => 'post', 'data-confirm' => 'Удалить рекламодателя?']) ?>
</p>
<?= DetailView::widget(['model' => $model, 'attributes' => [
    ['attribute' => 'logo_path', 'format' => 'raw', 'value' => $model->getLogoUrl() ? Html::img($model->getLogoUrl(), ['style' => 'max-height:80px']) : null],
    'name', 'legal_name', 'legal_info:ntext', 'website_url:url', 'contact_email:email', 'contact_phone',
    'created_at:datetime',
]]) ?>
<h2 class="h5 mt-4">Кампании</h2>
<ul>
    <?php foreach ($model->campaigns as $c): ?>
        <li><?= Html::a(Html::encode($c->title), ['/admin/ad-campaign/view', 'id' => $c->id]) ?> — <?= Html::encode(\app\enums\CampaignStatus::labelOf($c->status)) ?></li>
    <?php endforeach ?>
</ul>
