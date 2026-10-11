# Лес Любви — сервис регистрации посадок деревьев (Yii2, шаблон basic)

Реализация ТЗ «Лес Любви на Yii2». Этот каталог накладывается **поверх** установленного шаблона `yiisoft/yii2-app-basic`
(файлы самого фреймворка и шаблона здесь не дублируются, кроме тех, что ТЗ требует заменить: `config/*`, `models/User.php`,
`controllers/SiteController.php`, макеты и виды `site/*`).

## Установка

```bash
composer create-project yiisoft/yii2-app-basic forest
cp -r <этот каталог>/. forest/        # наложить файлы проекта
cd forest
# удалить файлы шаблона, которые больше не используются:
rm models/LoginForm.php models/ContactForm.php views/site/contact.php views/site/login.php
composer update                       # зависимости из composer.json проекта
cp .env.example .env                  # заполнить БД, ключи SMS и платёжного провайдера
php yii migrate                       # таблицы + RBAC + очередь
php yii rbac/init
php yii species/import data/species.csv
php yii user/create-admin admin@example.com 'пароль'
mkdir -p storage web/uploads && chmod 0775 storage web/uploads
```

Продакшен: `deploy/nginx.conf` (HTTPS обязателен, запрет исполнения в `uploads/`), `deploy/crontab`,
`deploy/queue-worker.service` (обработчик очереди `yii queue/listen`).

## Структура

| Каталог | Содержимое (раздел ТЗ) |
|---|---|
| `migrations/` | 13 миграций: user, verification_code, tree_species, advertiser, ad_campaign, ad_placement, planting, planting_link, planting_photo, planting_revision, moderation_log, payment, setting (5) |
| `enums/` | статусы и типы как PHP enum с подписями и CSS-классами (4) |
| `models/`, `models/query/` | ActiveRecord и scopes `*Query` (6) |
| `models/search/` | поисковые модели для GridView (8) |
| `forms/` | модели форм (7) |
| `components/` | SMS (`FileSmsSender` для dev, `HttpSmsSender`), платежи (`FakePaymentGateway`, `HttpPaymentGateway` в стиле ЮKassa), хранилище `LocalStorage`, фильтр ограничения частоты (9.1) |
| `services/` | AuthService, ExifService, ImageService, PlantingService, ModerationService, NamedTreeService, PaymentService, MapDataService, AdStatsService, SocialNetworkResolver, NotificationService, RateLimitService (9.2) |
| `jobs/` | SendSmsJob, SendEmailJob, ProcessPhotoJob, NotifyOwnerJob (9.3) |
| `validators/` | CoordinateValidator, UrlListValidator, PhoneValidator (9.4) |
| `controllers/` | Site, Auth, Map (JSON API), Tree, Ad, Cabinet, Planting, Photo, Payment (10) |
| `modules/admin/` | модуль администрирования и модерации (11), включая разделы «Пользователи» и «Права доступа» |
| `views/`, `mail/` | макеты main/map/cabinet/auth, виды, шаблоны писем (12) |
| `widgets/` | MapWidget, LinksWidget, LinksInputWidget, PhotoTimelineWidget, PhotoCaptureWidget, StatusBadgeWidget, BannerWidget, AdStatsWidget (13) |
| `assets/`, `web/js`, `web/css`, `web/img` | Leaflet + markercluster, exifr, карта и форма посадки (14) |
| `rbac/` | роли/разрешения, `OwnPlantingRule` (15) |
| `config/` | web, console, common, container (DI), routes, params, test (16) |
| `commands/` | rbac/init, user/create-admin, user/assign-role, species/import, cleanup/codes, cleanup/orphan-files, payment/sync, named/expire, ad/finish (17) |
| `tests/` | Codeception: unit, functional, acceptance, фикстуры (20) |

## Пользователи и права доступа (админка)

Доступно роли «Администратор» (разрешение `manageUsers`):

- **Пользователи** (`/admin/user`): поиск и фильтр по роли, создание пользователя с паролем, редактирование контактов
  и статуса, отметка подтверждения email/телефона, блокировка, сброс сохранённых входов, удаление учётных записей
  без данных, назначение ролей и отдельных разрешений, просмотр итоговых разрешений.
- **Права доступа** (`/admin/access`): матрица «роль × разрешение», создание собственных ролей
  (с наследованием прав других ролей), переименование и удаление пользовательских ролей, список пользователей роли.
- Защита от потери доступа: нельзя снять с себя права администратора, заблокировать или удалить себя,
  отнять `manageUsers` у роли «Администратор», удалить системные роли или оставить систему без администраторов.
- `yii rbac/init` больше не стирает настройки, сделанные в админке (только добавляет недостающее);
  полный сброс — `yii rbac/init --reset`.

## Принятые решения по открытым вопросам ТЗ (раздел 22)

Значения по умолчанию, которые легко поменять:

1. **Платёжный провайдер** — интерфейс `PaymentGatewayInterface`; для разработки `FakePaymentGateway`, для боевого режима `HttpPaymentGateway` (схема ЮKassa: платёж → redirect → вебхук, статус перепроверяется запросом к API). Цена, валюта и срок — в админке «Настройки» (`named.price = 0` — оформление бесплатно и сразу).
2. **SMS** — `HttpSmsSender` с типовым JSON API; вход по SMS-коду без пароля реализован (`/auth/sms-login`).
3. **Подложка карты** — OpenStreetMap, меняется в `params['map.tileUrl']`.
4. **Именное дерево** — имя/посвящение (`title`) и особый маркер; текст имени не модерируется отдельно.
5. **Повторная модерация** — на карте остаётся одобренная версия до одобрения ревизии.
6. **Фото** — каждое модерируется; лимит в настройках (`upload.maxPhotosPerPlanting = 100`).
7. **Привязка посадок к рекламе** — вручную администратором (списком или выбором на карте).
8. **Показатели рекламы** — количество деревьев, разбивка по породам, период посадок, охват (число ячеек 1°×1°), прогресс к плану.
9. **Оплата рекламы** — вне системы, кабинета рекламодателя нет.

## Проверка

Код прогнан на PHP 8.3 + PostgreSQL 16 с ядром Yii2 и расширениями: миграции, все консольные команды,
сквозной сценарий (регистрация по телефону и email → карточка с фото и EXIF → модерация → карта → ревизия →
догрузка фото → оплата и повторный вебхук → реклама → удаление) и отрисовка всех ~80 страниц публичной части,
кабинета и админки. Тесты Codeception: `vendor/bin/codecept run unit,functional`
(нужна база `forest_test` и `php yii migrate` с `DB_DSN=$DB_TEST_DSN`).
