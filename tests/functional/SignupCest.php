<?php

namespace tests\functional;

use app\models\User;
use app\models\VerificationCode;
use FunctionalTester;
use tests\_support\RbacSeeder;

/**
 * Регистрация по email и по телефону (с фейковым SmsSender).
 */
class SignupCest
{
    public function _before(FunctionalTester $I): void
    {
        RbacSeeder::seed([]);
    }

    public function signupByEmail(FunctionalTester $I): void
    {
        $I->amOnRoute('auth/signup');
        $I->submitForm('#signup-form', [
            'SignupForm[login]' => 'Tree.Lover@Example.com',
            'SignupForm[password]' => 'secret-pass-1',
            'SignupForm[password_repeat]' => 'secret-pass-1',
            'SignupForm[agree]' => 1,
        ]);
        $I->see('Подтвердите email');
        $I->seeRecord(User::class, ['email' => 'tree.lover@example.com', 'status' => 'unverified']);
        $I->seeEmailIsSent();
    }

    public function signupWithoutConsentFails(FunctionalTester $I): void
    {
        $I->amOnRoute('auth/signup');
        $I->submitForm('#signup-form', [
            'SignupForm[login]' => 'x@example.com',
            'SignupForm[password]' => 'secret-pass-1',
            'SignupForm[password_repeat]' => 'secret-pass-1',
            'SignupForm[agree]' => 0,
        ]);
        $I->see('Необходимо согласие');
        $I->dontSeeRecord(User::class, ['email' => 'x@example.com']);
    }

    public function signupByPhoneAndVerify(FunctionalTester $I): void
    {
        $I->amOnRoute('auth/signup');
        $I->submitForm('#signup-form', [
            'SignupForm[login]' => '8 (999) 123-45-67',
            'SignupForm[password]' => 'secret-pass-1',
            'SignupForm[password_repeat]' => 'secret-pass-1',
            'SignupForm[agree]' => 1,
        ]);
        $I->seeRecord(User::class, ['phone' => '+79991234567', 'status' => 'unverified']);
        $I->see('Подтверждение телефона');

        /** @var \app\components\sms\FileSmsSender $sms */
        $sms = \Yii::$app->get('smsSender');
        $text = $sms->getLastMessage('+79991234567');
        $I->assertNotNull($text);
        preg_match('/(\d{6})/', $text, $m);

        // Неверный код
        $I->submitForm('#verify-form', ['VerifyCodeForm[code]' => $m[1] === '000000' ? '111111' : '000000']);
        $I->see('Неверный код');

        $I->submitForm('#verify-form', ['VerifyCodeForm[code]' => $m[1]]);
        $user = $I->grabRecord(User::class, ['phone' => '+79991234567']);
        $I->assertSame('active', $user->status);
        $I->assertNotNull($user->phone_verified_at);
        $I->assertTrue(\Yii::$app->authManager->checkAccess($user->id, 'plantingCreate'));
    }

    public function confirmEmailByToken(FunctionalTester $I): void
    {
        $user = new User(['email' => 'confirm@example.com', 'status' => 'unverified']);
        $user->setPassword('secret-pass-1');
        $user->save(false);
        [, $token] = VerificationCode::create($user, 'email', 'signup', 'confirm@example.com');

        $I->amOnRoute('auth/confirm-email', ['token' => $token]);
        $I->see('Email подтверждён');
        $user->refresh();
        $I->assertSame('active', $user->status);
    }
}
