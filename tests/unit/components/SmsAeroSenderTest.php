<?php

namespace tests\unit\components;

use app\components\sms\smsaero\SmsAeroException;
use app\components\sms\smsaero\SmsAeroSender;
use Codeception\Test\Unit;

/**
 * SMS Aero без сети: HTTP-ответы подменяются.
 */
class SmsAeroSenderTest extends Unit
{
    private function sender(array $responses, array &$requests = []): SmsAeroSender
    {
        $s = new class(['email' => 'user@example.com', 'apiKey' => 'key', 'sign' => 'LesLubvi', 'callbackUrl' => 'https://site/sms/callback/t']) extends SmsAeroSender {
            public array $responses = [];
            public array $requests = [];
            protected function httpRequest(string $url, array $params): array
            {
                $this->requests[] = [$url, $params];
                $r = array_shift($this->responses);
                if ($r instanceof \Throwable) {
                    throw new SmsAeroException('net', 0);
                }
                return $r;
            }
        };
        $s->responses = $responses;
        return $s;
    }

    public function testSendSuccess(): void
    {
        $s = $this->sender([[200, json_encode(['success' => true, 'data' => [
            'id' => 101, 'from' => 'LesLubvi', 'number' => '79991234567', 'text' => 'x',
            'status' => 8, 'extendStatus' => 'moderation', 'channel' => 'FREE SIGN', 'cost' => 4.79,
        ], 'message' => null])]]);

        $this->assertTrue($s->send('+7 (999) 123-45-67', 'Код: 123456'));
        $this->assertSame(101, $s->getLastMessage()->id);
        $this->assertSame('на модерации', $s->getLastMessage()->statusLabel());

        [$url, $params] = $s->requests[0];
        $this->assertSame('https://gate.smsaero.ru/v2/sms/send', $url);
        $this->assertSame('79991234567', $params['number']);
        $this->assertSame('LesLubvi', $params['sign']);
        $this->assertSame('https://site/sms/callback/t', $params['callbackUrl']);
    }

    public function testNotEnoughMoneyIsNotRetryable(): void
    {
        $s = $this->sender([[402, json_encode(['success' => false, 'data' => null, 'message' => 'Not enough money'])]]);
        $this->assertFalse($s->send('+79991234567', 'x'));
        $this->assertSame(402, $s->getLastError()->httpCode);
        $this->assertFalse($s->getLastError()->isRetryable());
    }

    public function testNetworkErrorIsRetryable(): void
    {
        $s = $this->sender([new \RuntimeException()]);
        $this->assertFalse($s->send('+79991234567', 'x'));
        $this->assertTrue($s->getLastError()->isRetryable());
    }

    public function testBalanceAndStatus(): void
    {
        $s = $this->sender([
            [200, json_encode(['success' => true, 'data' => ['balance' => 150.5]])],
            [200, json_encode(['success' => true, 'data' => ['id' => 5, 'number' => '79991234567', 'status' => 1, 'extendStatus' => 'delivery']])],
        ]);
        $this->assertSame(150.5, $s->getBalance());
        $this->assertTrue($s->getStatus(5)->isDelivered());
    }

    public function testPhoneNormalization(): void
    {
        $this->assertSame('79991234567', SmsAeroSender::normalizePhone('+7 999 123-45-67'));
        $this->assertSame('79991234567', SmsAeroSender::normalizePhone('89991234567'));
        $this->assertSame('375291234567', SmsAeroSender::normalizePhone('+375 29 123-45-67'));
    }
}
