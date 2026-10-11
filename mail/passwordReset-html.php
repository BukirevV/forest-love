<?php

use yii\helpers\Html;

/** @var string $url */
?>
<p>Здравствуйте!</p>
<p>Вы запросили восстановление пароля. Чтобы задать новый пароль, нажмите на кнопку:</p>
<p><?= Html::a('Задать новый пароль', $url, ['style' => 'display:inline-block;background:#2f6b3a;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;']) ?></p>
<p style="font-size:13px;color:#7a8a7a;">Ссылка действует <?= (int)(Yii::$app->params['auth.passwordResetTokenTtl'] / 60) ?> минут.<br><?= Html::encode($url) ?></p>
<p style="font-size:13px;color:#7a8a7a;">Если вы не запрашивали восстановление, проигнорируйте это письмо — пароль останется прежним.</p>
