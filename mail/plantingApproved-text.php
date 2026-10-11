<?php
/** @var \app\models\Planting|null $planting */
/** @var string $url */
?>
Здравствуйте!

Ваше дерево<?= $planting ? ' «' . $planting->getDisplayTitle() . '»' : '' ?> прошло модерацию и опубликовано на карте.
<?= $url ?>
