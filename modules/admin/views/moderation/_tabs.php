<?php

use yii\bootstrap5\Html;

/** @var string $tab */
/** @var array $counts */

$tabs = [
    'plantings' => 'Новые карточки',
    'revisions' => 'Изменения',
    'photos' => 'Фотографии',
];
?>
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <?= Html::a(
                Html::encode($label) . ' <span class="badge ' . ($counts[$key] ? 'text-bg-danger' : 'text-bg-secondary') . '">' . (int)$counts[$key] . '</span>',
                ['index', 'tab' => $key],
                ['class' => 'nav-link' . ($tab === $key ? ' active' : '')]
            ) ?>
        </li>
    <?php endforeach ?>
    <li class="nav-item ms-auto"><?= Html::a('<i class="bi bi-journal-text"></i> Журнал', ['log'], ['class' => 'nav-link']) ?></li>
</ul>
