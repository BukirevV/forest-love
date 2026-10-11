<?php
/** @var \app\models\Payment|null $payment */
/** @var string $url */
?>
Здравствуйте!

Оплата<?= $payment ? ' на сумму ' . Yii::$app->formatter->asCurrency($payment->amount, $payment->currency) : '' ?> получена. Ваше дерево стало именным.
<?= $url ?>

Номер платежа: <?= $payment ? (int)$payment->id : '' ?>
