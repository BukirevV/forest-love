<?php

use app\models\Setting;
use yii\bootstrap5\Html;

/** @var yii\web\View $this */
/** @var Setting[] $settings */

$this->title = 'Настройки';
?>
<h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>
<?= Html::beginForm() ?>
<table class="table align-middle" style="max-width: 900px">
    <thead><tr><th>Параметр</th><th style="width: 260px">Значение</th><th>Ключ</th></tr></thead>
    <tbody>
    <?php foreach ($settings as $id => $s): ?>
        <tr>
            <td><?= Html::encode($s->label) ?></td>
            <td>
                <?php if ($s->type === 'bool'): ?>
                    <?= Html::activeDropDownList($s, "[$id]value", ['1' => 'Да', '0' => 'Нет'], ['class' => 'form-select']) ?>
                <?php else: ?>
                    <?= Html::activeTextInput($s, "[$id]value", ['class' => 'form-control' . ($s->hasErrors() ? ' is-invalid' : '')]) ?>
                    <?php if ($s->hasErrors()): ?><div class="invalid-feedback"><?= Html::encode($s->getFirstError('value')) ?></div><?php endif ?>
                <?php endif ?>
            </td>
            <td><code class="small"><?= Html::encode($s->key) ?></code></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?= Html::submitButton('Сохранить', ['class' => 'btn btn-success']) ?>
<?= Html::endForm() ?>
