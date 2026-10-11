<?php

namespace app\forms;

use app\enums\CampaignStatus;
use app\models\AdCampaign;
use app\models\Advertiser;
use app\models\Planting;
use app\services\AdStatsService;
use Yii;
use yii\base\Model;

/**
 * Форма кампании для админки + множественная привязка посадок.
 */
class AdCampaignForm extends Model
{
    public int|string|null $advertiser_id = null;
    public ?string $title = null;
    public ?string $slug = null;
    public ?string $marketing_text = null;
    public ?string $legal_text = null;
    public ?string $status = null;
    public ?string $starts_at = null; // Y-m-d
    public ?string $ends_at = null;   // Y-m-d
    public int|string|null $funded_trees_target = null;
    /** @var int[]|string */
    public array|string|null $planting_ids = [];

    public function __construct(private readonly AdCampaign $campaign, array $config = [])
    {
        if (!$campaign->isNewRecord) {
            foreach (['advertiser_id', 'title', 'slug', 'marketing_text', 'legal_text', 'status', 'funded_trees_target'] as $a) {
                $this->$a = $campaign->$a;
            }
            $this->starts_at = $campaign->starts_at ? date('Y-m-d', $campaign->starts_at) : null;
            $this->ends_at = $campaign->ends_at ? date('Y-m-d', $campaign->ends_at) : null;
            $this->planting_ids = $campaign->getPlantings()->select('id')->column();
        } else {
            $this->status = CampaignStatus::Draft->value;
        }
        parent::__construct($config);
    }

    public function rules(): array
    {
        return [
            [['title', 'slug', 'legal_text'], 'trim'],
            [['slug', 'legal_text', 'starts_at', 'ends_at', 'funded_trees_target'], 'default', 'value' => null],
            [['advertiser_id', 'title', 'marketing_text', 'status'], 'required'],
            ['advertiser_id', 'exist', 'targetClass' => Advertiser::class, 'targetAttribute' => 'id'],
            ['title', 'string', 'max' => 200],
            ['slug', 'match', 'pattern' => '/^[a-z0-9-]+$/'],
            ['slug', 'unique', 'targetClass' => AdCampaign::class,
                'filter' => $this->campaign->isNewRecord ? null : ['<>', 'id', $this->campaign->id]],
            [['marketing_text', 'legal_text'], 'string'],
            ['status', 'in', 'range' => CampaignStatus::values()],
            [['starts_at', 'ends_at'], 'date', 'format' => 'php:Y-m-d'],
            ['ends_at', 'compare', 'compareAttribute' => 'starts_at', 'operator' => '>=',
                'when' => fn() => $this->starts_at && $this->ends_at],
            ['funded_trees_target', 'integer', 'min' => 1],
            ['planting_ids', 'each', 'rule' => ['integer']],
            ['planting_ids', 'default', 'value' => []],
        ];
    }

    public function attributeLabels(): array
    {
        return array_merge((new AdCampaign())->attributeLabels(), [
            'planting_ids' => 'Профинансированные посадки',
        ]);
    }

    public function getCampaign(): AdCampaign
    {
        return $this->campaign;
    }

    /**
     * Сохранение кампании и привязки посадок (в транзакции).
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $c = $this->campaign;
        $c->advertiser_id = (int)$this->advertiser_id;
        $c->title = $this->title;
        if ($this->slug) {
            $c->slug = $this->slug;
        }
        $c->marketing_text = $this->marketing_text;
        $c->legal_text = $this->legal_text;
        $c->status = $this->status;
        $c->starts_at = $this->starts_at ? strtotime($this->starts_at . ' 00:00:00') : null;
        $c->ends_at = $this->ends_at ? strtotime($this->ends_at . ' 23:59:59') : null;
        $c->funded_trees_target = $this->funded_trees_target ? (int)$this->funded_trees_target : null;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$c->save()) {
                $this->addErrors($c->getErrors());
                $tx->rollBack();
                return false;
            }
            $ids = array_map('intval', (array)$this->planting_ids);
            Planting::updateAll(['ad_campaign_id' => null], ['and', ['ad_campaign_id' => $c->id], ['not in', 'id', $ids ?: [0]]]);
            if ($ids) {
                Planting::updateAll(['ad_campaign_id' => $c->id], ['id' => $ids]);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
        Yii::$container->get(AdStatsService::class)->invalidate((int)$c->id);
        return true;
    }
}
