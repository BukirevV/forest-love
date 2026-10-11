<?php

use yii\bootstrap5\Alert;

/** @var yii\web\View $this */

$map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
foreach (Yii::$app->session->getAllFlashes() as $type => $messages) {
    foreach ((array)$messages as $message) {
        echo Alert::widget([
            'options' => ['class' => 'alert-' . ($map[$type] ?? 'info')],
            // Сообщения формируются приложением; пользовательский ввод экранируется при формировании
            'body' => $message,
        ]);
    }
}
