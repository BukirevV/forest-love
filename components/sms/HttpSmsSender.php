<?php

namespace app\components\sms;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\httpclient\Client;

/**
 * Боевая отправка SMS через HTTP API шлюза.
 * Формат запроса типовой (JSON: to, text, from); адаптируется под выбранного провайдера (ТЗ 22, вопрос 2).
 */
class HttpSmsSender extends Component implements SmsSenderInterface
{
    public ?string $apiUrl = null;
    public ?string $apiKey = null;
    public string $senderName = 'LesLubvi';
    public int $timeout = 10;

    public function init(): void
    {
        parent::init();
        if (!$this->apiUrl || !$this->apiKey) {
            throw new InvalidConfigException('Не заданы SMS_API_URL / SMS_API_KEY.');
        }
    }

    public function send(string $phone, string $text): bool
    {
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        try {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($this->apiUrl)
                ->setFormat(Client::FORMAT_JSON)
                ->addHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->setData(['to' => ltrim($phone, '+'), 'text' => $text, 'from' => $this->senderName])
                ->setOptions(['timeout' => $this->timeout])
                ->send();

            if (!$response->isOk) {
                Yii::error("SMS gateway error {$response->statusCode}: {$response->content}", 'sms');
                return false;
            }
            Yii::info("SMS sent to {$phone}", 'sms');
            return true;
        } catch (\Throwable $e) {
            Yii::error('SMS gateway exception: ' . $e->getMessage(), 'sms');
            return false;
        }
    }
}
