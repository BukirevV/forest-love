<?php

namespace tests\unit\services;

use app\components\payment\PaymentResult;
use app\enums\NamedStatus;
use app\enums\PaymentStatus;
use app\models\Payment;
use app\models\Planting;
use app\services\PaymentService;
use Codeception\Test\Unit;
use tests\fixtures\PaymentFixture;
use Yii;

class PaymentServiceTest extends Unit
{
    public function _fixtures(): array
    {
        return ['payments' => PaymentFixture::class];
    }

    public function testSucceededIsIdempotent(): void
    {
        $service = Yii::$container->get(PaymentService::class);
        $payment = Payment::findOne(1);
        $result = new PaymentResult('fake_pending_1', PaymentStatus::Succeeded, ['n' => 1]);

        $service->applyResult($payment, $result);
        $payment->refresh();
        $paidAt = $payment->paid_at;
        $this->assertSame(PaymentStatus::Succeeded->value, $payment->status);

        $planting = Planting::findOne(1);
        $this->assertSame(NamedStatus::Active->value, $planting->named_status);
        $this->assertSame('Дуб нашей семьи', $planting->title);

        // Сбрасываем имя, чтобы проверить отсутствие повторного эффекта
        $planting->title = 'Изменено вручную';
        $planting->save(false, ['title']);

        // Повторный вебхук
        $service->applyResult($payment, new PaymentResult('fake_pending_1', PaymentStatus::Succeeded, ['n' => 2]));
        $payment->refresh();
        $this->assertSame($paidAt, $payment->paid_at);
        $this->assertSame('Изменено вручную', Planting::findOne(1)->title);
    }

    public function testFailedResetsAwaitingStatus(): void
    {
        $planting = Planting::findOne(1);
        $planting->named_status = NamedStatus::AwaitingPayment->value;
        $planting->save(false, ['named_status']);

        $service = Yii::$container->get(PaymentService::class);
        $service->applyResult(Payment::findOne(1), new PaymentResult('fake_pending_1', PaymentStatus::Canceled));

        $this->assertSame(PaymentStatus::Canceled->value, Payment::findOne(1)->status);
        $this->assertSame(NamedStatus::None->value, Planting::findOne(1)->named_status);
    }

    public function testAmountMismatchRejected(): void
    {
        $this->expectException(\yii\web\BadRequestHttpException::class);
        Yii::$container->get(PaymentService::class)->applyResult(
            Payment::findOne(1),
            new PaymentResult('fake_pending_1', PaymentStatus::Succeeded, [], null, '1.00', 'RUB')
        );
    }
}
