<?php

namespace app\widgets;

use app\assets\ExifrAsset;
use app\enums\PhotoSource;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\InputWidget;

/**
 * Кнопки «Сфотографировать» / «Загрузить», предпросмотр, чтение EXIF на клиенте.
 * При наличии координат в EXIF генерирует событие `photo:exif` на корневом элементе.
 * Поведение — в web/js/planting-form.js (data-photo-capture).
 */
class PhotoCaptureWidget extends InputWidget
{
    /** Поле даты снимка (заполняется из EXIF). */
    public ?string $takenAtInputId = null;
    /** Поле источника (camera/upload). */
    public ?string $sourceInputId = null;

    public function run(): string
    {
        ExifrAsset::register($this->view);
        $id = $this->options['id'] ?? Html::getInputId($this->model, $this->attribute);
        $name = Html::getInputName($this->model, $this->attribute);
        $accept = 'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic';

        // Один input для отправки (и для «Загрузить»), второй — с capture для камеры
        $uploadInput = Html::fileInput($name, null, [
            'id' => $id, 'accept' => $accept, 'class' => 'd-none', 'data-photo-input' => 'upload',
        ]);
        $cameraInput = Html::fileInput('', null, [
            'accept' => 'image/*', 'capture' => 'environment', 'class' => 'd-none', 'data-photo-input' => 'camera',
        ]);

        $buttons = Html::tag('div',
            Html::button('<i class="bi bi-camera"></i> Сфотографировать', ['class' => 'btn btn-success btn-lg', 'data-photo-camera' => true])
            . Html::button('<i class="bi bi-upload"></i> Загрузить', ['class' => 'btn btn-outline-success btn-lg', 'data-photo-upload' => true]),
            ['class' => 'd-flex flex-wrap gap-2']
        );

        $preview = Html::tag('div',
            Html::img('', ['class' => 'img-fluid rounded d-none', 'data-photo-preview' => true, 'alt' => 'Предпросмотр'])
            . Html::tag('div', '', ['class' => 'small text-muted mt-2', 'data-photo-info' => true]),
            ['class' => 'mt-3']
        );

        return Html::tag('div', $uploadInput . $cameraInput . $buttons . $preview, [
            'class' => 'photo-capture',
            'data-photo-capture' => true,
            'data-taken-at' => $this->takenAtInputId,
            'data-source' => $this->sourceInputId,
            'data-source-camera' => PhotoSource::Camera->value,
            'data-source-upload' => PhotoSource::Upload->value,
            'data-exif-url' => Url::to(['/planting/exif']),
            'data-max-size' => (int)Yii::$app->params['upload.maxFileSize'],
        ]);
    }
}
