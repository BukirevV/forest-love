<?php

namespace app\components\sms\smsaero;

use app\components\sms\SmsSenderInterface;
use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\httpclient\Client;

/**
 * Отправка SMS через SMS Aero (API v2, https://smsaero.ru/integration/documentation/api/).
 *
 * Авторизация — HTTP Basic: логин = email аккаунта, пароль = API-ключ из личного кабинета.
 * Имя отправителя (sign) должно быть подтверждено в кабинете; для тестов доступно «SMS Aero».
 *
 * Конфигурация (config/common.php, SMS_DRIVER=smsaero):
 *   'smsSender' => [
 *       'class' => SmsAeroSender::class,
 *       'email' => '...', 'apiKey' => '...', 'sign' => 'SMS Aero',
 *       'callbackUrl' => 'https://site/sms/callback/<token>', // необязательно
 *   ]
 */
class SmsAeroSender extends Component implements SmsSenderInterface
{
    public const LOG_CATEGORY = 'sms';

    public string $apiUrl = 'https://gate.smsaero.ru/v2';
    public ?string $email = null;
    public ?string $apiKey = null;
    /** Имя отправителя, подтверждённое в кабинете SMS Aero. */
    public string $sign = 'SMS Aero';
    /** URL для статусов доставки (POST от SMS Aero). Пусто — не передавать. */
    public ?string $callbackUrl = null;
    public int $timeout = 15;
    /** Транспорт yii2-httpclient: StreamTransport (по умолчанию) или CurlTransport. */
    public string $transport = 'yii\httpclient\StreamTransport';

    /** Результат последней успешной отправки (для логов, тестов, консоли). */
    private ?SmsAeroMessage $lastMessage = null;
    private ?SmsAeroException $lastError = null;

    public function init(): void
    {
        parent::init();
        if (!$this->email || !$this->apiKey) {
            throw new InvalidConfigException('SMS Aero: не заданы SMSAERO_EMAIL и SMSAERO_API_KEY.');
        }
        $this->apiUrl = rtrim($this->apiUrl, '/');
    }

    // ------------------------------------------------------------------ SmsSenderInterface

    /**
     * Отправка SMS. Возвращает false при ошибке (подробности — getLastError() и лог категории sms).
     * SendSmsJob повторяет отправку только для временных ошибок (сеть, 5xx) — см. SmsAeroException::isRetryable().
     */
    public function send(string $phone, string $text): bool
    {
        $this->lastMessage = null;
        $this->lastError = null;
        try {
            $this->lastMessage = $this->sendMessage($phone, $text);
            $log = sprintf(
                'SMS Aero: #%d → %s, статус «%s», канал %s, стоимость %s',
                $this->lastMessage->id,
                self::maskPhone($phone),
                $this->lastMessage->statusLabel(),
                $this->lastMessage->channel ?? '—',
                $this->lastMessage->cost ?? '—'
            );
            $this->lastMessage->isFailed() ? Yii::warning($log, self::LOG_CATEGORY) : Yii::info($log, self::LOG_CATEGORY);
            return !$this->lastMessage->isFailed();
        } catch (SmsAeroException $e) {
            $this->lastError = $e;
            Yii::error(sprintf('SMS Aero: ошибка отправки на %s (HTTP %d): %s', self::maskPhone($phone), $e->httpCode, $e->getMessage()), self::LOG_CATEGORY);
            return false;
        }
    }

    public function getLastMessage(): ?SmsAeroMessage
    {
        return $this->lastMessage;
    }

    public function getLastError(): ?SmsAeroException
    {
        return $this->lastError;
    }

    // ------------------------------------------------------------------ Методы API

    /**
     * sms/send — отправка одного SMS.
     *
     * @param int|null $dateSend отложенная отправка (unixtime)
     * @throws SmsAeroException
     */
    public function sendMessage(string $phone, string $text, ?int $dateSend = null): SmsAeroMessage
    {
        $params = [
            'number' => self::normalizePhone($phone),
            'sign' => $this->sign,
            'text' => $text,
        ];
        if ($dateSend !== null) {
            $params['dateSend'] = $dateSend;
        }
        if ($this->callbackUrl) {
            $params['callbackUrl'] = $this->callbackUrl;
            $params['callbackFormat'] = 'JSON';
        }
        $data = $this->call('sms/send', $params);

        // Ответ — объект сообщения или массив из одного объекта
        $item = isset($data['id']) ? $data : ($data[0] ?? null);
        if (!is_array($item)) {
            throw new SmsAeroException('Неожиданный формат ответа sms/send.', 200, (array)$data);
        }
        return SmsAeroMessage::fromArray($item);
    }

    /**
     * sms/status — статус ранее отправленного SMS.
     *
     * @throws SmsAeroException
     */
    public function getStatus(int $id): SmsAeroMessage
    {
        $data = $this->call('sms/status', ['id' => $id]);
        $item = isset($data['id']) ? $data : ($data[0] ?? []);
        return SmsAeroMessage::fromArray((array)$item);
    }

    /**
     * auth — проверка email и API-ключа.
     *
     * @throws SmsAeroException
     */
    public function checkAuth(): bool
    {
        $this->call('auth');
        return true;
    }

    /**
     * balance — баланс аккаунта в рублях.
     *
     * @throws SmsAeroException
     */
    public function getBalance(): float
    {
        $data = $this->call('balance');
        return (float)($data['balance'] ?? 0);
    }

    /**
     * sign/list — подтверждённые имена отправителя.
     *
     * @return string[]
     * @throws SmsAeroException
     */
    public function getSigns(): array
    {
        $data = $this->call('sign/list');
        $rows = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data; // ответ может быть постраничным
        $names = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['name'])) {
                $names[] = (string)$row['name'];
            }
        }
        return $names;
    }

    // ------------------------------------------------------------------ HTTP

    /**
     * Вызов метода API. Возвращает поле data ответа.
     *
     * @throws SmsAeroException
     */
    public function call(string $method, array $params = []): array
    {
        [$code, $body] = $this->httpRequest($this->apiUrl . '/' . ltrim($method, '/'), $params);

        $json = [];
        if ($body !== '') {
            try {
                $json = (array)Json::decode($body);
            } catch (\Throwable) {
                throw new SmsAeroException("Некорректный ответ SMS Aero (HTTP {$code}).", $code);
            }
        }

        if ($code !== 200 || empty($json['success'])) {
            $message = (string)($json['message'] ?? '') ?: self::describeHttpCode($code);
            throw new SmsAeroException($message, $code === 200 ? 400 : $code, $json);
        }
        return is_array($json['data'] ?? null) ? $json['data'] : [];
    }

    /**
     * Выполняет POST-запрос. Вынесено в отдельный метод для подмены в тестах.
     *
     * @return array{0: int, 1: string} [HTTP-код (0 — сеть недоступна), тело ответа]
     */
    protected function httpRequest(string $url, array $params): array
    {
        $client = new Client(['transport' => $this->transport]);
        try {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->setFormat(Client::FORMAT_URLENCODED)
                ->setData($params)
                ->addHeaders([
                    'Authorization' => 'Basic ' . base64_encode($this->email . ':' . $this->apiKey),
                    'Accept' => 'application/json',
                ])
                ->setOptions(['timeout' => $this->timeout])
                ->send();
            return [(int)$response->statusCode, (string)$response->content];
        } catch (\Throwable $e) {
            throw new SmsAeroException('SMS Aero недоступен: ' . $e->getMessage(), 0, [], $e);
        }
    }

    // ------------------------------------------------------------------ helpers

    /**
     * SMS Aero принимает номер цифрами без «+»: 79990000000.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7' . substr($digits, 1);
        }
        return $digits;
    }

    public static function maskPhone(string $phone): string
    {
        $d = self::normalizePhone($phone);
        return strlen($d) > 4 ? substr($d, 0, 2) . str_repeat('*', strlen($d) - 4) . substr($d, -2) : $d;
    }

    private static function describeHttpCode(int $code): string
    {
        return match ($code) {
            400 => 'Ошибка валидации запроса',
            401, 403 => 'Неверный email или API-ключ SMS Aero',
            402 => 'Недостаточно средств на балансе SMS Aero',
            404 => 'Не найдено (или запрос с неразрешённого IP-адреса)',
            429 => 'Слишком много запросов',
            default => "Ошибка SMS Aero (HTTP {$code})",
        };
    }
}
