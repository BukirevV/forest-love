<?php

namespace app\models;

use app\components\storage\StorageInterface;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Рекламодатель (ТЗ 6.10).
 *
 * @property int $id
 * @property string $name
 * @property string $legal_name
 * @property string $legal_info
 * @property string|null $website_url
 * @property string|null $logo_path
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property int $created_at
 * @property int $updated_at
 *
 * @property-read AdCampaign[] $campaigns
 */
class Advertiser extends ActiveRecord
{
    /** Загружаемый логотип (из формы админки). */
    public UploadedFile|string|null $logoFile = null;

    public static function tableName(): string
    {
        return '{{%advertiser}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name', 'legal_name', 'legal_info', 'website_url', 'contact_email', 'contact_phone'], 'trim'],
            [['website_url', 'contact_email', 'contact_phone'], 'default', 'value' => null],
            [['name', 'legal_name', 'legal_info'], 'required'],
            ['name', 'string', 'max' => 200],
            ['legal_name', 'string', 'max' => 300],
            ['legal_info', 'string'],
            ['website_url', 'url', 'defaultScheme' => 'https', 'validSchemes' => ['http', 'https']],
            ['website_url', 'string', 'max' => 500],
            ['contact_email', 'email'],
            [['contact_email', 'contact_phone', 'logo_path'], 'string', 'max' => 255],
            ['logoFile', 'image', 'extensions' => ['png', 'jpg', 'jpeg', 'webp', 'svg'], 'maxSize' => 2 * 1024 * 1024,
                'checkExtensionByMimeType' => false, 'skipOnEmpty' => true],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Название',
            'legal_name' => 'Юридическое наименование',
            'legal_info' => 'Юридическая информация (ИНН/ОГРН, адрес)',
            'website_url' => 'Сайт',
            'logo_path' => 'Логотип',
            'logoFile' => 'Логотип',
            'contact_email' => 'Контактный email',
            'contact_phone' => 'Контактный телефон',
            'created_at' => 'Создан',
            'updated_at' => 'Изменён',
        ];
    }

    public function getCampaigns(): ActiveQuery
    {
        return $this->hasMany(AdCampaign::class, ['advertiser_id' => 'id']);
    }

    public function getLogoUrl(): ?string
    {
        return $this->logo_path ? Yii::$app->get('storage')->getPublicUrl($this->logo_path) : null;
    }

    /**
     * Сохраняет загруженный логотип через StorageService.
     */
    public function uploadLogo(): void
    {
        if (!$this->logoFile instanceof UploadedFile) {
            return;
        }
        /** @var StorageInterface $storage */
        $storage = Yii::$app->get('storage');
        $old = $this->logo_path;
        $this->logo_path = $storage->savePublicFile($this->logoFile->tempName, 'advertisers', $this->logoFile->extension);
        if ($old) {
            $storage->deletePublic($old);
        }
    }

    public function beforeDelete(): bool
    {
        if ($this->getCampaigns()->exists()) {
            $this->addError('id', 'У рекламодателя есть кампании — удаление невозможно.');
            return false;
        }
        return parent::beforeDelete();
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        if ($this->logo_path) {
            Yii::$app->get('storage')->deletePublic($this->logo_path);
        }
    }
}
