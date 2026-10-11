<?php

namespace app\forms;

use app\enums\PhotoSource;
use app\services\ExifService;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Догрузка фотографии в карточку (история роста).
 * taken_at подставляется из EXIF, иначе обязателен для ввода.
 */
class PhotoUploadForm extends Model
{
    public UploadedFile|string|null $file = null;
    public ?string $taken_at = null;
    public ?string $source = null;

    /** Данные EXIF, извлечённые при валидации. */
    public array $exif = [];

    public function rules(): array
    {
        $params = Yii::$app->params;
        return [
            ['file', 'required', 'message' => 'Выберите фотографию.'],
            ['file', 'file',
                'extensions' => $params['upload.allowedTypes'] ?? ['jpg', 'jpeg', 'png', 'webp', 'heic'],
                'mimeTypes' => $params['upload.allowedMimeTypes'] ?? ['image/*'],
                'checkExtensionByMimeType' => false,
                'maxSize' => $params['upload.maxFileSize'] ?? 15 * 1024 * 1024,
            ],
            ['taken_at', 'trim'],
            ['taken_at', 'default', 'value' => null],
            ['taken_at', 'fillFromExif', 'skipOnEmpty' => false],
            ['taken_at', 'required', 'message' => 'В фото нет даты съёмки — укажите её вручную.'],
            ['taken_at', 'date', 'format' => 'php:Y-m-d'],
            ['taken_at', 'validateNotInFuture'],
            ['source', 'default', 'value' => PhotoSource::Upload->value],
            ['source', 'in', 'range' => PhotoSource::values()],
        ];
    }

    public function beforeValidate(): bool
    {
        if (!$this->file instanceof UploadedFile) {
            $this->file = UploadedFile::getInstance($this, 'file') ?? UploadedFile::getInstanceByName('file');
        }
        return parent::beforeValidate();
    }

    public function fillFromExif(): void
    {
        if (!$this->file instanceof UploadedFile || $this->hasErrors('file')) {
            return;
        }
        $this->exif = Yii::$container->get(ExifService::class)->extract($this->file->tempName);
        if (empty($this->taken_at) && !empty($this->exif['date'])) {
            $this->taken_at = $this->exif['date'];
        }
    }

    public function validateNotInFuture(string $attribute): void
    {
        if ($this->$attribute && strtotime((string)$this->$attribute) > strtotime('today 23:59:59')) {
            $this->addError($attribute, 'Дата снимка не может быть в будущем.');
        }
    }

    public function attributeLabels(): array
    {
        return ['file' => 'Фотография', 'taken_at' => 'Дата снимка', 'source' => 'Источник'];
    }
}
