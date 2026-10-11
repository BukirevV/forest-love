<?php

namespace tests\acceptance;

use AcceptanceTester;

/**
 * Сценарий: загрузить фото с GPS → координаты подставлены → карточка отправлена → одобрена → видна на карте.
 * Требует запущенный сервер (php yii serve --docroot=web), Chromium/WebDriver и фото tests/_data/exif/with-gps.jpg.
 */
class PlantingFlowCest
{
    public function photoWithGpsToMap(AcceptanceTester $I): void
    {
        if (!is_file(codecept_data_dir('exif/with-gps.jpg'))) {
            $I->markTestSkipped('Нет фото с GPS');
        }
        $I->amOnPage('/login');
        $I->fillField('LoginForm[login]', 'owner@example.com');
        $I->fillField('LoginForm[password]', 'password123');
        $I->click('Войти');

        $I->amOnPage('/cabinet/plantings/new');
        $I->attachFile('input[data-photo-input="upload"]', 'exif/with-gps.jpg');
        $I->waitForElementVisible('[data-exif-note], [data-exif-offer].d-flex', 10);
        $I->dontSeeInField('PlantingForm[latitude]', '');
        $I->selectOption('PlantingForm[species_id]', 'Дуб черешчатый');
        $I->click('Отправить на модерацию');
        $I->see('Карточка отправлена на модерацию');
    }
}
