<?php

use yii\helpers\Html;

/** @var \app\models\Payment|null $payment */
/** @var string $url */
?>
<p>Здравствуйте!</p>
<p>Оплата<?= $payment ? ' на сумму ' . Html::encode(Yii::$app->formatter->asCurrency($payment->amount, $payment->currency)) : '' ?> получена. Ваше дерево стало именным и выделено на карте особым маркером.</p>
<p><?= Html::a('Посмотреть дерево', $url, ['style' => 'display:inline-block;background:#2f6b3a;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;']) ?></p>
<p style="font-size:13px;color:#7a8a7a;">Номер платежа: <?= $payment ? (int)$payment->id : '' ?></p>
