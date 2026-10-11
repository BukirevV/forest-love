<?php

namespace tests\functional;

use app\models\Payment;
use app\models\Planting;
use FunctionalTester;
use tests\_support\RbacSeeder;
use tests\fixtures\PaymentFixture;

class PaymentWebhookCest
{
    public function _fixtures(): array
    {
        return ['payments' => PaymentFixture::class];
    }

    public function _before(FunctionalTester $I): void
    {
        RbacSeeder::seed();
    }

    private function send(FunctionalTester $I, array $data, ?string $secret = 'test-secret'): void
    {
        $body = json_encode($data);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Signature', hash_hmac('sha256', $body, (string)$secret));
        $I->sendAjaxRequest('POST', '/payment/webhook/fake', $body);
    }

    public function successfulWebhook(FunctionalTester $I): void
    {
        $this->send($I, ['payment_id' => 'fake_pending_1', 'status' => 'succeeded']);
        $I->seeResponseCodeIs(200);
        $I->assertSame('succeeded', Payment::findOne(1)->status);
        $I->assertSame('active', Planting::findOne(1)->named_status);

        // Повтор — без ошибок и повторных эффектов
        $this->send($I, ['payment_id' => 'fake_pending_1', 'status' => 'succeeded']);
        $I->seeResponseCodeIs(200);
    }

    public function invalidSignatureRejected(FunctionalTester $I): void
    {
        $this->send($I, ['payment_id' => 'fake_pending_1', 'status' => 'succeeded'], 'wrong');
        $I->seeResponseCodeIs(403);
        $I->assertSame('pending', Payment::findOne(1)->status);
    }
}
