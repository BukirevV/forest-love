<?php

namespace tests\functional;

use FunctionalTester;
use tests\_support\RbacSeeder;
use tests\fixtures\UserFixture;

class LoginCest
{
    public function _fixtures(): array
    {
        return ['users' => UserFixture::class];
    }

    public function _before(FunctionalTester $I): void
    {
        RbacSeeder::seed();
        $I->amOnRoute('auth/login');
    }

    public function loginByEmail(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', ['LoginForm[login]' => 'owner@example.com', 'LoginForm[password]' => 'password123']);
        $I->dontSeeElement('#login-form');
        $I->assertFalse(\Yii::$app->user->isGuest);
    }

    public function loginByPhoneInAnyFormat(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', ['LoginForm[login]' => '8 999 000-00-02', 'LoginForm[password]' => 'password123']);
        $I->assertSame(2, (int)\Yii::$app->user->id);
    }

    public function wrongPassword(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', ['LoginForm[login]' => 'owner@example.com', 'LoginForm[password]' => 'wrong']);
        $I->see('Неверный логин или пароль');
    }

    public function unverifiedCannotLogin(FunctionalTester $I): void
    {
        $I->submitForm('#login-form', ['LoginForm[login]' => 'new@example.com', 'LoginForm[password]' => 'password123']);
        $I->see('Контакт не подтверждён');
    }
}
