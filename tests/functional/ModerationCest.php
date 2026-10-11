<?php

namespace tests\functional;

use app\models\ModerationLog;
use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use FunctionalTester;
use tests\_support\RbacSeeder;
use tests\fixtures\PlantingLinkFixture;
use tests\fixtures\PlantingPhotoFixture;

class ModerationCest
{
    public function _fixtures(): array
    {
        return ['photos' => PlantingPhotoFixture::class, 'links' => PlantingLinkFixture::class];
    }

    public function _before(FunctionalTester $I): void
    {
        RbacSeeder::seed();
    }

    public function userHasNoAccess(FunctionalTester $I): void
    {
        $I->amLoggedInAs(1);
        $I->amOnRoute('admin/moderation/index');
        $I->seeResponseCodeIs(403);
    }

    public function approvePendingPlanting(FunctionalTester $I): void
    {
        $I->amLoggedInAs(3);
        $I->amOnRoute('admin/moderation/planting', ['id' => 3]);
        $I->submitForm('#moderation-form', ['ModerationForm[action]' => 'approve', 'ModerationForm[comment]' => '']);

        $planting = Planting::findOne(3);
        $I->assertSame('approved', $planting->status);
        $I->assertSame('approved', PlantingPhoto::findOne(3)->status);
        $I->assertSame(3, (int)$planting->main_photo_id);
        $I->seeRecord(ModerationLog::class, ['entity_type' => 'planting', 'entity_id' => 3, 'action' => 'approve']);
    }

    public function rejectRequiresComment(FunctionalTester $I): void
    {
        $I->amLoggedInAs(3);
        $I->amOnRoute('admin/moderation/planting', ['id' => 3]);
        $I->submitForm('#moderation-form', ['ModerationForm[action]' => 'reject', 'ModerationForm[comment]' => '']);
        $I->see('Укажите причину отклонения');
        $I->assertSame('pending', Planting::findOne(3)->status);

        $I->submitForm('#moderation-form', ['ModerationForm[action]' => 'reject', 'ModerationForm[comment]' => 'Фото нечёткое']);
        $I->assertSame('rejected', Planting::findOne(3)->status);
    }

    public function approveRevisionAppliesChanges(FunctionalTester $I): void
    {
        $revision = new PlantingRevision(['planting_id' => 1, 'user_id' => 1, 'status' => 'pending']);
        $data = Planting::findOne(1)->snapshot();
        $data['species_id'] = 2;
        $revision->setData($data);
        $revision->save(false);

        $I->amLoggedInAs(3);
        $I->amOnRoute('admin/moderation/revision', ['id' => $revision->id]);
        $I->see('Было');
        $I->submitForm('#moderation-form', ['ModerationForm[action]' => 'approve']);

        $I->assertSame(2, (int)Planting::findOne(1)->species_id);
        $I->assertSame('approved', PlantingRevision::findOne($revision->id)->status);
    }
}
