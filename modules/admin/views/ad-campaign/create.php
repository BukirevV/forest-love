<?php

/** @var yii\web\View $this */
/** @var app\forms\AdCampaignForm $model */
/** @var app\models\AdCampaign $campaign */
/** @var array $advertisers */
/** @var array $plantingOptions */

if ($campaign->isNewRecord && ($aid = Yii::$app->request->get('advertiser_id'))) {
    $model->advertiser_id ??= (int)$aid;
}
$this->title = 'Новая кампания';
$this->params['breadcrumbs'][] = ['label' => 'Кампании', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<h1 class="h3 mb-3"><?= yii\helpers\Html::encode($this->title) ?></h1>
<?= $this->render('_form', ['model' => $model, 'advertisers' => $advertisers, 'plantingOptions' => $plantingOptions]) ?>
