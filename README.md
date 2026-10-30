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

## Импорт посадок из фотографий

```bash
php yii import -n                          # пробный прогон: что будет импортировано, без изменений
php yii import                             # импорт всех фото из import/in
php yii import --species="ольха"           # порода для фото, где её нет в метаданных (название, первое слово или ID)
php yii import --user=admin@site.ru        # от чьего имени (по умолчанию — первый администратор)
php yii import --lat=54.6703 --lon=38.2469 # координаты для фото без GPS (шаг между точками — --spread=5, м)
```

1. Положите фотографии в `import/in` (JPEG, PNG, WebP, HEIC, до 15 МБ).
2. Из метаданных берутся:
   - GPS-координаты — обязательны, без них фото не импортируется;
   - дата съёмки → дата посадки и дата снимка;
   - заголовок и описание (XMP `dc:title`/`dc:description`, IPTC, EXIF `ImageDescription`, поля Windows «Название»/«Комментарии»);
   - порода — по ключевым словам или по упоминанию названия в тексте («дуб», «Берёза повислая», «Pinus sylvestris»).
3. Карточка создаётся от имени администратора сразу опубликованной, фото обрабатывается (копия без EXIF и превью) в этой же операции и появляется на карте.
4. Файл переносится в `import/success`, при ошибке — в `import/fail`, причина записывается рядом в `<имя>.error.txt`. При совпадении имени добавляется суффикс `-1`, `-2`…
5. В конце выводится список файлов со статусом. Код выхода 0 — всё успешно, 1 — были ошибки.

Запускайте от пользователя веб-сервера, чтобы у загруженных файлов были правильные права: `sudo -u www-data php yii import`.

### Фото без координат

Если в фото нет GPS (а в `plantings.json` — `"coordinates": null`), можно задать координаты по умолчанию:

```bash
php yii import --lat=54.6703 --lon=38.2469              # шаг между точками 5 м
php yii import --lat=54.6703 --lon=38.2469 --spread=10  # шаг 10 м
```

Первое такое фото ставится в саму точку, остальные — равномерно вокруг неё по спирали, примерно в `--spread` метрах друг от друга,
чтобы на карте они не слились в одну. Места, где уже есть посадки (в том числе из прошлых запусков), пропускаются.
Источник координат у таких посадок — «указаны вручную», в журнале модерации — пометка «координаты по умолчанию».
Без `--lat/--lon` фото без координат, как и раньше, уходят в `import/fail`.

### Именные посадки из `import/plantings.json`

Если рядом с папками лежит `import/plantings.json`, сначала импортируются записи из него, затем остальные фото из `import/in`.
Посадки из файла создаются **именными** и сразу **опубликованными**:

```json
[
  {
    "file": "IMG_0135.HEIC",
    "name": "Мария Филимонова",
    "coordinates": {"lat": 54.670342, "lon": 38.247131},
    "species": "дуб",
    "planted_at": "2025-05-12",
    "named_until": "2030-12-31",
    "description": "…",
    "note": "служебная пометка"
  }
]
```

- `file` и `name` обязательны; `name` — имя дерева, показывается на карте и в карточке.
- `coordinates: null` — координаты берутся из EXIF фото, если их нет и там — из `--lat/--lon`, иначе ошибка.
- `species` — название, первое слово или ID породы; если не указано — по метаданным или `--species=…`.
- `named_until` — до какой даты дерево именное (по умолчанию бессрочно).
- `note` в карточку не попадает — пишется в журнал модерации.
- Запись пропускается, если её фото уже в `import/success`, — повторный запуск не создаёт дублей.
  Если ошибка в самой записи (нет имени, неверные координаты), фото остаётся в `import/in`: исправьте запись и запустите снова.
- Другой файл: `--json=путь`. Файл в git не попадает.
- HEIC-фото (iPhone) требуют на сервере расширение Imagick с поддержкой HEIC (`sudo apt install php8.4-imagick`). Проверка: `php -r 'print_r(Imagick::queryFormats("HEI*"));'` — в списке должен быть `HEIC`.

## Обновление на сервере из GitHub

```bash
cd /var/www/forest-love
sudo bash deploy/update.sh --check   # есть ли новые коммиты
sudo bash deploy/update.sh           # обновить
```

Скрипт `deploy/update.sh`:
1. останавливается, если на сервере вручную изменены файлы проекта (чтобы не потерять правки);
2. забирает ветку с GitHub (по умолчанию `master`, другая — `--branch=имя`) и показывает новые коммиты;
3. делает резервную копию базы в `/var/backups/forest-love` (последние 10, отключить — `--no-backup`);
4. обновляет код; `.env`, загруженные фото, `runtime`, `storage` не затрагиваются;
5. `composer install` — только если менялись `composer.json`/`composer.lock`;
6. миграции, `rbac/init`, сброс кэша, права на папки для `www-data`;
7. перезапускает обработчик очереди и php-fpm;
8. если composer, миграция или другой шаг упали — возвращает код к предыдущей версии.

Журнал — `/var/log/forest-love-update.log`. Настройки (ветка, путь к PHP и т.п.) — в `deploy/update.conf`, пример — в начале скрипта.

Доступ к GitHub: если репозиторий закрытый, серверу нужен ключ развёртывания (Deploy key, только чтение):
`ssh-keygen -t ed25519 -f ~/.ssh/forest_deploy -N ""`, публичный ключ — в GitHub → Settings → Deploy keys,
затем `git remote set-url origin git@github.com:BukirevV/forest-love.git` и в `~/.ssh/config` — `IdentityFile ~/.ssh/forest_deploy` для `github.com`.

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
