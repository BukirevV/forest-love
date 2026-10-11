<?php

namespace app\forms;

use app\components\storage\StorageInterface;
use app\enums\BannerPosition;
use app\enums\PlacementType;
use app\models\AdCampaign;
use app\models\AdPlacement;
use app\validators\CoordinateValidator;
use Yii;
use yii\base\Model;
use yii\web\UploadedFile;

/**
 * Форма размещения для админки: точка (координаты, иконка) или баннер (изображения, позиция).
 */
class AdPlacementForm extends Model
{
    public int|string|null $campaign_id = null;
    public ?string $type = null;
    public float|string|null $latitude = null;
    public float|string|null $longitude = null;
    public ?string $position = null;
    public ?string $target_url = null;
    public int|string|null $priority = 0;
    public bool|string $is_active = true;

    public UploadedFile|string|null $imageFile = null;
    public UploadedFile|string|null $imageMobileFile = null;
    public UploadedFile|string|null $markerFile = null;

    public function __construct(private readonly AdPlacement $placement, array $config = [])
    {
        if (!$placement->isNewRecord) {
            foreach (['campaign_id', 'type', 'latitude', 'longitude', 'position', 'target_url', 'priority', 'is_active'] as $a) {
                $this->$a = $placement->$a;
            }
        } else {
            $this->type = PlacementType::Point->value;
        }
        parent::__construct($config);
    }

    public function rules(): array
    {
        $isPoint = fn() => $this->type === PlacementType::Point->value;
        $isBanner = fn() => $this->type === PlacementType::Banner->value;
        $image = ['extensions' => ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'], 'maxSize' => 3 * 1024 * 1024,
            'checkExtensionByMimeType' => false];

        return [
            [['campaign_id', 'type'], 'required'],
            ['campaign_id', 'exist', 'targetClass' => AdCampaign::class, 'targetAttribute' => 'id'],
            ['type', 'in', 'range' => PlacementType::values()],
            [['latitude', 'longitude'], 'required', 'when' => $isPoint,
                'whenClient' => "function(){ return $('#adplacementform-type').val() === 'point'; }"],
            ['latitude', CoordinateValidator::class, 'type' => 'latitude', 'pairAttribute' => 'longitude', 'when' => $isPoint],
            ['longitude', CoordinateValidator::class, 'type' => 'longitude', 'pairAttribute' => 'latitude', 'when' => $isPoint],
            ['position', 'required', 'when' => $isBanner,
                'whenClient' => "function(){ return $('#adplacementform-type').val() === 'banner'; }"],
            ['position', 'in', 'range' => BannerPosition::values(), 'skipOnEmpty' => true],
            ['imageFile', 'required', 'when' => fn() => $isBanner() && !$this->placement->image_path,
                'message' => 'Загрузите изображение баннера.'],
            [['imageFile', 'imageMobileFile', 'markerFile'], 'file', ...$image],
            ['target_url', 'url', 'defaultScheme' => 'https', 'validSchemes' => ['http', 'https']],
            ['target_url', 'default', 'value' => null],
            ['priority', 'integer'],
            ['is_active', 'boolean'],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge($this->placement->attributeLabels(), [
            'imageFile' => 'Изображение баннера',
            'imageMobileFile' => 'Изображение для мобильных',
            'markerFile' => 'Иконка маркера',
        ]);
    }

    public function getPlacement(): AdPlacement
    {
        return $this->placement;
    }

    public function beforeValidate(): bool
    {
        foreach (['imageFile', 'imageMobileFile', 'markerFile'] as $attr) {
            if (!$this->$attr instanceof UploadedFile) {
                $this->$attr = UploadedFile::getInstance($this, $attr);
            }
        }
        return parent::beforeValidate();
    }

    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        /** @var StorageInterface $storage */
        $storage = Yii::$app->get('storage');
        $p = $this->placement;
        $p->campaign_id = (int)$this->campaign_id;
        $p->type = $this->type;
        $p->target_url = $this->target_url;
        $p->priority = (int)$this->priority;
        $p->is_active = (bool)$this->is_active;

        if ($p->isPoint()) {
            $p->latitude = $this->latitude;
            $p->longitude = $this->longitude;
            $p->position = null;
        } else {
            $p->latitude = null;
            $p->longitude = null;
            $p->position = $this->position;
        }

        $map = ['imageFile' => 'image_path', 'imageMobileFile' => 'image_mobile_path', 'markerFile' => 'marker_icon'];
        foreach ($map as $fileAttr => $pathAttr) {
            $file = $this->$fileAttr;
            if ($file instanceof UploadedFile) {
                $old = $p->$pathAttr;
                $p->$pathAttr = $storage->savePublicFile($file->tempName, 'ads', $file->extension);
                if ($old) {
                    $storage->deletePublic($old);
                }
            }
        }

        if (!$p->save()) {
            $this->addErrors($p->getErrors());
            return false;
        }
        return true;
    }
}
