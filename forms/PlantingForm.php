<?php

namespace app\forms;

use app\enums\CoordsSource;
use app\enums\PhotoSource;
use app\models\Planting;
use app\models\TreeSpecies;
use app\validators\CoordinateValidator;
use app\validators\UrlListValidator;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Форма создания/редактирования карточки посадки (ТЗ 7).
 * Обязательны порода и координаты; при создании — минимум одна фотография.
 */
class PlantingForm extends Model
{
    public const SCENARIO_CREATE = 'create';
    public const SCENARIO_UPDATE = 'update';

    public int|string|null $species_id = null;
    public float|string|null $latitude = null;
    public float|string|null $longitude = null;
    public ?string $coords_source = null;
    public ?string $planted_at = null;
    public ?string $title = null;
    public ?string $description = null;
    /** @var array<int, array{url: string, label: ?string}> */
    public array|string|null $links = [];

    /** Первая фотография (только при создании). */
    public UploadedFile|string|null $photo = null;
    public ?string $photo_taken_at = null;
    public ?string $photo_source = null;

    /** Отправить сразу на модерацию (иначе — черновик). */
    public bool|string $submit = false;

    public function scenarios(): array
    {
        $common = ['species_id', 'latitude', 'longitude', 'coords_source', 'planted_at', 'title', 'description', 'links', 'submit'];
        return [
            self::SCENARIO_CREATE => array_merge($common, ['photo', 'photo_taken_at', 'photo_source']),
            self::SCENARIO_UPDATE => $common,
            self::SCENARIO_DEFAULT => $common,
        ];
    }

    public function rules(): array
    {
        $params = Yii::$app->params;
        return [
            [['title', 'description', 'planted_at'], 'trim'],
            [['title', 'description', 'planted_at'], 'default', 'value' => null],
            ['species_id', 'required', 'message' => 'Выберите породу из справочника.'],
            [['latitude', 'longitude'], 'required', 'message' => 'Отметьте место посадки на карте.'],
            ['species_id', 'integer'],
            ['species_id', 'exist', 'targetClass' => TreeSpecies::class, 'targetAttribute' => 'id',
                'filter' => ['is_active' => true], 'message' => 'Выберите породу из справочника.'],
            ['latitude', CoordinateValidator::class, 'type' => CoordinateValidator::TYPE_LATITUDE, 'pairAttribute' => 'longitude'],
            ['longitude', CoordinateValidator::class, 'type' => CoordinateValidator::TYPE_LONGITUDE, 'pairAttribute' => 'latitude'],
            ['coords_source', 'default', 'value' => CoordsSource::Manual->value],
            ['coords_source', 'in', 'range' => CoordsSource::values()],
            ['planted_at', 'date', 'format' => 'php:Y-m-d'],
            ['planted_at', 'validateNotInFuture'],
            ['title', 'string', 'max' => 150],
            ['description', 'string', 'max' => 5000],
            ['links', UrlListValidator::class, 'skipOnEmpty' => false],
            ['submit', 'boolean'],

            ['photo', 'required', 'on' => self::SCENARIO_CREATE, 'message' => 'Добавьте хотя бы одну фотографию посадки.'],
            ['photo', 'file',
                'extensions' => $params['upload.allowedTypes'] ?? ['jpg', 'jpeg', 'png', 'webp', 'heic'],
                'mimeTypes' => $params['upload.allowedMimeTypes'] ?? ['image/*'],
                'checkExtensionByMimeType' => false,
                'maxSize' => $params['upload.maxFileSize'] ?? 15 * 1024 * 1024,
                'on' => self::SCENARIO_CREATE],
            ['photo_taken_at', 'date', 'format' => 'php:Y-m-d'],
            ['photo_taken_at', 'validateNotInFuture'],
            ['photo_source', 'default', 'value' => PhotoSource::Upload->value],
            ['photo_source', 'in', 'range' => PhotoSource::values()],
        ];
    }

    public function validateNotInFuture(string $attribute): void
    {
        if ($this->$attribute && strtotime((string)$this->$attribute) > strtotime('today 23:59:59')) {
            $this->addError($attribute, 'Дата не может быть в будущем.');
        }
    }

    public function attributeLabels(): array
    {
        return [
            'species_id' => 'Порода',
            'latitude' => 'Широта',
            'longitude' => 'Долгота',
            'coords_source' => 'Источник координат',
            'planted_at' => 'Дата посадки',
            'title' => 'Имя дерева / подпись',
            'description' => 'Описание',
            'links' => 'Кто посадил',
            'photo' => 'Фотография',
            'photo_taken_at' => 'Дата снимка',
            'photo_source' => 'Источник фото',
        ];
    }

    public function attributeHints(): array
    {
        return [
            'links' => 'Ссылки на страницы участников в соцсетях или сайты. Можно указать несколько, в том числе в одной сети.',
            'title' => 'Отображается для именных деревьев.',
            'photo_taken_at' => 'Если в фото нет даты съёмки, укажите её вручную.',
        ];
    }

    public function beforeValidate(): bool
    {
        if ($this->scenario === self::SCENARIO_CREATE && !$this->photo instanceof UploadedFile) {
            $this->photo = UploadedFile::getInstance($this, 'photo');
        }
        if (is_string($this->latitude)) {
            $this->latitude = str_replace(',', '.', trim($this->latitude));
        }
        if (is_string($this->longitude)) {
            $this->longitude = str_replace(',', '.', trim($this->longitude));
        }
        if ($this->latitude === '') {
            $this->latitude = null;
        }
        if ($this->longitude === '') {
            $this->longitude = null;
        }
        return parent::beforeValidate();
    }

    /**
     * Заполнение формы из карточки (для редактирования). Если есть ожидающая ревизия —
     * показываются её значения, чтобы пользователь продолжил правку.
     */
    public function loadFromPlanting(Planting $planting): void
    {
        $this->scenario = self::SCENARIO_UPDATE;
        $revision = $planting->pendingRevision;
        $data = $revision ? $revision->getData() : $planting->snapshot();

        foreach (Planting::EDITABLE_FIELDS as $field) {
            $this->$field = $data[$field] ?? $planting->$field;
        }
        $this->links = $data['links'] ?? [];
    }

    /**
     * Данные карточки для сохранения (без ссылок).
     */
    public function getPlantingAttributes(): array
    {
        $result = [];
        foreach (Planting::EDITABLE_FIELDS as $field) {
            $result[$field] = $this->$field;
        }
        $result['species_id'] = (int)$result['species_id'];
        $result['latitude'] = round((float)$result['latitude'], 7);
        $result['longitude'] = round((float)$result['longitude'], 7);
        return $result;
    }

    /** @return array<int, array{url: string, label: ?string}> */
    public function getLinks(): array
    {
        return is_array($this->links) ? array_values($this->links) : [];
    }

    public function isSubmit(): bool
    {
        return (bool)$this->submit;
    }
}
