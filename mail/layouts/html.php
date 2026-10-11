<?php

use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var \yii\mail\MessageInterface $message */
/** @var string $content */
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?= Yii::$app->charset ?>" />
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body style="margin:0;padding:0;background:#f3f6f2;font-family:Arial,Helvetica,sans-serif;color:#1f2d1f;">
<?php $this->beginBody() ?>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f2;padding:24px 0;">
    <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
            <tr><td style="background:#2f6b3a;color:#fff;padding:16px 24px;font-size:20px;font-weight:bold;">🌳 Лес Любви</td></tr>
            <tr><td style="padding:24px;font-size:15px;line-height:1.5;"><?= $content ?></td></tr>
            <tr><td style="padding:16px 24px;font-size:12px;color:#7a8a7a;border-top:1px solid #e5ebe4;">
                Это автоматическое письмо, отвечать на него не нужно.
            </td></tr>
        </table>
    </td></tr>
</table>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
