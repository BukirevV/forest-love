<?php

namespace tests\functional;

use app\models\Planting;
use app\models\PlantingPhoto;
use app\models\PlantingRevision;
use FunctionalTester;
use tests\_support\RbacSeeder;
use tests\fixtures\PlantingLinkFixture;
use tests\fixtures\PlantingPhotoFixture;

class PlantingCest
{
    public function _fixtures(): array
    {
        return ['photos' => PlantingPhotoFixture::class, 'links' => PlantingLinkFixture::class];
    }

    public function _before(FunctionalTester $I): void
    {
        RbacSeeder::seed();
        $I->amLoggedInAs(1);
    }

    public function createWithoutCoordsAndSpeciesFails(FunctionalTester $I): void
    {
        $I->amOnRoute('planting/create');
        $I->attachFile('PlantingForm[photo]', 'tree.jpg');
        $I->submitForm('#planting-form', [
            'PlantingForm[latitude]' => '',
            'PlantingForm[longitude]' => '',
            'PlantingForm[species_id]' => '',
            'PlantingForm[submit]' => 1,
        ]);
        $I->see('Отметьте место посадки на карте');
        $I->see('Выберите породу из справочника');
        $I->dontSeeRecord(Planting::class, ['latitude' => null]);
    }

    public function createAndSubmit(FunctionalTester $I): void
    {
        $I->amOnRoute('planting/create');
        $I->attachFile('PlantingForm[photo]', 'tree.jpg');
        $I->submitForm('#planting-form', [
            'PlantingForm[latitude]' => '55.1234567',
            'PlantingForm[longitude]' => '37.7654321',
            'PlantingForm[species_id]' => 1,
            'PlantingForm[planted_at]' => '2026-04-20',
            'PlantingForm[photo_taken_at]' => '2026-04-20',
            'PlantingForm[links][0][url]' => 'https://vk.com/me',
            'PlantingForm[links][0][label]' => 'Я',
            'PlantingForm[links][1][url]' => 'https://vk.com/friend',
            'PlantingForm[submit]' => 1,
        ]);
        $planting = $I->grabRecord(Planting::class, ['latitude' => '55.1234567']);
        $I->assertSame('pending', $planting->status);
        $I->assertCount(2, $planting->links);
        $I->assertSame('vk', $planting->links[1]->network);
        $I->seeRecord(PlantingPhoto::class, ['planting_id' => $planting->id, 'status' => 'pending']);
    }

    public function editPublishedCreatesRevision(FunctionalTester $I): void
    {
        $I->amOnRoute('planting/update', ['id' => 1]);
        $I->see('Изменения уйдут на повторную модерацию');
        $I->submitForm('#planting-form', [
            'PlantingForm[latitude]' => '55.7500000',
            'PlantingForm[longitude]' => '37.6200000',
            'PlantingForm[species_id]' => 2,
            'PlantingForm[submit]' => 1,
        ]);
        $I->seeRecord(PlantingRevision::class, ['planting_id' => 1, 'status' => 'pending']);
        // Публичная версия не изменилась
        $I->assertSame(1, (int)Planting::findOne(1)->species_id);

        // Повторная правка заменяет предыдущую ревизию
        $I->amOnRoute('planting/update', ['id' => 1]);
        $I->submitForm('#planting-form', [
            'PlantingForm[latitude]' => '55.7500000',
            'PlantingForm[longitude]' => '37.6200000',
            'PlantingForm[species_id]' => 2,
            'PlantingForm[title]' => 'Новое',
            'PlantingForm[submit]' => 1,
        ]);
        $I->assertSame(1, (int)PlantingRevision::find()->where(['planting_id' => 1, 'status' => 'pending'])->count());
        $I->seeRecord(PlantingRevision::class, ['planting_id' => 1, 'status' => 'superseded']);
    }

    public function foreignPlantingIsForbidden(FunctionalTester $I): void
    {
        $I->amOnRoute('planting/view', ['id' => 4]);
        $I->seeResponseCodeIs(403);
        $I->amOnRoute('planting/update', ['id' => 4]);
        $I->seeResponseCodeIs(403);
    }

    public function uploadAdditionalPhoto(FunctionalTester $I): void
    {
        $I->amOnRoute('planting/view', ['id' => 1]);
        $I->attachFile('PhotoUploadForm[file]', 'tree.jpg');
        $I->submitForm('#photo-upload-modal form', ['PhotoUploadForm[taken_at]' => '2026-06-01']);
        $I->seeRecord(PlantingPhoto::class, ['planting_id' => 1, 'taken_at' => '2026-06-01', 'status' => 'pending']);
    }

    public function softDelete(FunctionalTester $I): void
    {
        $I->sendAjaxPostRequest('/cabinet/plantings/2/delete');
        $I->assertSame('hidden', Planting::findOne(2)->status);
    }
}
