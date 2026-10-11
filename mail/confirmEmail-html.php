<?php

use yii\helpers\Html;

/** @var string $url */
?>
<p>Здравствуйте!</p>
<p>Чтобы подтвердить email, нажмите на кнопку:</p>
<p><?= Html::a('Подтвердить email', $url, ['style' => 'display:inline-block;background:#2f6b3a;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;']) ?></p>
<p style="font-size:13px;color:#7a8a7a;">Если кнопка не работает, скопируйте ссылку в браузер:<br><?= Html::encode($url) ?></p>
<p style="font-size:13px;color:#7a8a7a;">Если вы не регистрировались, просто проигнорируйте это письмо.</p>
