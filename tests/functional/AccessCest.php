<?php

namespace tests\functional;

use app\models\User;
use FunctionalTester;
use tests\_support\RbacSeeder;
use tests\fixtures\UserFixture;
use Yii;

/**
 * Раздел администратора «Пользователи» и «Права доступа».
 */
class AccessCest
{
    public function _fixtures(): array
    {
        return ['users' => UserFixture::class];
    }

    public function _before(FunctionalTester $I): void
    {
        RbacSeeder::seed();
    }

    public function moderatorHasNoAccess(FunctionalTester $I): void
    {
        $I->amLoggedInAs(3);
        $I->amOnRoute('admin/user/index');
        $I->seeResponseCodeIs(403);
        $I->amOnRoute('admin/access/index');
        $I->seeResponseCodeIs(403);
    }

    public function createUserWithRole(FunctionalTester $I): void
    {
        $I->amLoggedInAs(4);
        $I->amOnRoute('admin/user/create');
        $I->submitForm('#user-form', [
            'UserAdminForm[display_name]' => 'Новый модератор',
            'UserAdminForm[email]' => 'newmod@example.com',
            'UserAdminForm[status]' => 'active',
            'UserAdminForm[password]' => 'secret-12345',
            'UserAdminForm[email_verified]' => 1,
            'UserAdminForm[roles]' => ['moderator'],
        ]);
        $user = $I->grabRecord(User::class, ['email' => 'newmod@example.com']);
        $I->assertTrue(Yii::$app->authManager->checkAccess($user->id, 'moderate'));
        $I->assertFalse(Yii::$app->authManager->checkAccess($user->id, 'manageUsers'));
    }

    public function cannotRemoveOwnAdminRole(FunctionalTester $I): void
    {
        $I->amLoggedInAs(4);
        $I->amOnRoute('admin/user/view', ['id' => 4]);
        $I->submitForm('#access-form', ['roles' => ['user']]);
        $I->assertTrue(Yii::$app->authManager->checkAccess(4, 'manageUsers'));
    }

    public function createRoleAndAssign(FunctionalTester $I): void
    {
        $I->amLoggedInAs(4);
        $I->amOnRoute('admin/access/create');
        $I->submitForm('#role-form', [
            'RoleForm[description]' => 'Менеджер рекламы',
            'RoleForm[name]' => 'ad_manager',
            'RoleForm[permissions]' => ['manageAds'],
            'RoleForm[inherits]' => ['user'],
        ]);
        $I->assertNotNull(Yii::$app->authManager->getRole('ad_manager'));

        $I->amOnRoute('admin/user/view', ['id' => 2]);
        $I->submitForm('#access-form', ['roles' => ['ad_manager']]);
        $I->assertTrue(Yii::$app->authManager->checkAccess(2, 'manageAds'));
        $I->assertTrue(Yii::$app->authManager->checkAccess(2, 'plantingCreate'));
    }

    public function systemRoleCannotBeDeleted(FunctionalTester $I): void
    {
        $I->amLoggedInAs(4);
        $I->sendAjaxPostRequest('/admin/access/delete?name=moderator');
        $I->assertNotNull(Yii::$app->authManager->getRole('moderator'));
    }
}
