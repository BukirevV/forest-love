<?php

use yii\helpers\Html;

/** @var \app\models\Planting|null $planting */
/** @var string $url */
?>
<p>Здравствуйте!</p>
<p>Ваше дерево<?= $planting ? ' «' . Html::encode($planting->getDisplayTitle()) . '»' : '' ?> прошло модерацию и опубликовано на карте «Леса Любви».</p>
<p><?= Html::a('Посмотреть дерево', $url, ['style' => 'display:inline-block;background:#2f6b3a;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;']) ?></p>
<p>Делитесь ссылкой с друзьями и догружайте новые фотографии — пусть все видят, как растёт ваше дерево.</p>
