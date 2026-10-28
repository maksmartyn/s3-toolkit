# AGENTS.md

Памятка для агентов и разработчиков, которые продолжают работу над S3 Toolkit. Что делает сервис, описано в README.md.

## Общение и язык

- Интерфейс и ответы пользователю — на русском. Все строки интерфейса проходят через i18n: ключ — английская фраза, перевод в `lang/ru.json`. В PHP это `__()` и `trans_choice()`, во Vue — `$t()` и `trans()`.
- Комментарии в коде — на русском. Пишите их только там, где поведение неочевидно.
- После каждого законченного этапа делается один коммит с осмысленным сообщением на русском. Каталог `.serena/` в коммит не попадает: `git add -A -- . ':!.serena'`.

## Архитектура

Бэкенд разделён на слои, и важно их не смешивать:

- **Контроллер** (`app/Http/Controllers/<Группа>`) тонкий: принимает FormRequest, вызывает сервис и возвращает Inertia-ответ, JSON или redirect.
- **FormRequest** (`app/Http/Requests/<Группа>`) отвечает за права (`authorize()`), валидацию и превращение входных данных в DTO (`toData()`).
  - Запросы к бакетам и объектам наследуют `Bucket\BucketRequest`. В нём задаются право (`permission()`), дополнительные права (`additionalPermissions()`) и нужная функция хранилища (`capability()`).
  - Запросы к ключам наследуют `AccessKey\AccessKeyRequest`, к самому хранилищу — `Storage\StorageActionRequest` со способностью из `StoragePolicy`.
- **Сервис** (`app/Services/<Группа>`) содержит бизнес-логику. Принимает и возвращает DTO/VO. К моделям обращается только через репозитории, к S3 — только через `StorageGateway`.
- **Репозиторий** (`app/Repositories/<Группа>`) — лёгкий: базовый CRUD плюс `with()`, без Criteria. Интерфейсы привязаны в `RepositoryServiceProvider`.
- **Модель** тонкая: касты, связи, скрытые поля.
- **DTO** лежат в `App\Domain\DTO\<Группа>\` и наследуют `App\Domain\DTO\Abstracts\DTO` (публичные свойства, `toArray`/`jsonSerialize`, `with()`). Перечисления — в `App\Domain\Enum\<Группа>\`, VO — в `App\Domain\ValueObject\<Группа>\`.

Фронтенд: Inertia 3 + Vue 3 + TypeScript. Страницы лежат в `resources/js/pages`, компоненты — в `resources/js/components/<область>`, типы DTO — в `resources/js/types/*.ts` (с комментарием, какому PHP-классу соответствуют). URL берутся только из Wayfinder (`@/actions/...`, `@/routes/...`), строками их не пишем. UI-кит — shadcn-vue (`components/ui`, руками не правим).

### Ключевые узлы

| Что | Где |
|---|---|
| Все вызовы S3 | `app/Services/Storage/StorageGateway.php`. Создаётся через `StorageGatewayFactory::forStorage()`, клиенты — через `S3ClientFactory` (`makePresigner()` использует публичный endpoint) |
| Функции провайдеров | `StorageCapability::supportedBy()` и переопределения в `storages.capabilities` (хранятся только отличия), сервис `StorageCapabilities` |
| Перевод ошибок S3 | `StorageErrorTranslator`. Сервисы ловят `Throwable` и бросают `App\Exceptions\DomainException` с понятным текстом. Для JSON-запросов он превращается в ответ 422 `{message}`, для Inertia — в toast (`bootstrap/app.php`) |
| Права | Биты в `SystemPermission` и `StoragePermission`. **Номера битов не менять и не переиспользовать.** Маска — VO `PermissionMask`. Проверка на хранилище: `$user->can('use', [$storage, StoragePermission::X])` (`StoragePolicy`) |
| Журнал | `AuditService::record(AuditRecordData)`, действия в `AuditAction` (тип объекта и подпись). Подробности собираются через `AuditDetails::*`, отображаются через `AuditDetailsFormatter`. Действия с бакетом и объектами записывает `Bucket\BucketAuditor`. Журнал пишется **после** успешного изменения в S3: общей транзакции с S3 нет. Для фоновых задач передаётся `actorId` |
| Фоновые операции | Таблица `background_operations`, `OperationService` (start/progress/complete/fail и рассылка `OperationUpdated` в канал `storages.{id}`). Задачи в `app/Jobs/Bucket` наследуют `BucketOperationJob`, логика — в раннерах (`OperationRunner`), которые работают шагами по `s3toolkit.operations.step_seconds` и перезапускают себя. На бакет — одна операция уровня бакета; над папками разрешено несколько, но не над одной и той же. Параметры хранятся в `state['params']` |
| Объекты | `Services/Object/ObjectBrowserService` (чтение, ссылки), `ObjectManageService` (изменения), `ObjectUploadService` (загрузка, multipart по 16 МБ), `FolderOperationRunner`. Контроллеры — `Http/Controllers/Bucket/Object*Controller.php`. Ключ объекта передаётся параметром запроса, а не в пути |
| Ключи доступа | `Services/AccessKey`: контракт `Contracts\AccessKeyManager`, реализации в `Managers/*` (AWS IAM, MinIO через `mc`, Yandex, Keystone для Selectel и VK Cloud, Cloudflare R2), фабрика `AccessKeyManagerFactory`, сервис `AccessKeyService`. Учётные данные управления — `storages.admin_credentials` (`encrypted:array`) |
| Безопасность | `Http/Middleware/SecurityHeaders` (CSP с nonce через `Vite::useCspNonce()`), `EndpointGuard` (SSRF), `TrustProxies` настраивается в `AppServiceProvider` |
| Настройки сервиса | `config/s3toolkit.php` |

## Команды

Все проверки запускаются **только в контейнере** `tools` (лимит памяти 1,5 ГБ). Машина с 8 ГБ памяти, на ней работают и другие проекты: их не останавливать.

```bash
docker compose run --rm tools sh -c 'vendor/bin/pest'
docker compose run --rm tools sh -c 'vendor/bin/phpstan analyse --memory-limit=1G --no-progress'
docker compose run --rm tools sh -c 'vendor/bin/pint --parallel'
docker compose run --rm tools sh -c 'npm run check:fix && npm run check'
docker compose run --rm tools sh -c 'npm run types:check'
docker compose run --rm tools sh -c 'npm run build'
docker compose run --rm tools sh -c 'php artisan wayfinder:generate --with-form -n'   # после изменения маршрутов
```

- После изменения PHP-кода или сборки, если `--watch` не сработал: `docker compose exec -T app php artisan octane:reload`. После изменения задач: `docker compose exec horizon php artisan horizon:terminate`.
- Интеграционные тесты (`tests/Integration`) идут на настоящем MinIO и сами пропускаются, если он не запущен:
  - `docker compose --profile s3 up -d minio` — изнутри сети `http://minio:9000`, с хоста `localhost:9010`, логин `minioadmin`/`minioadmin`;
  - после тестов MinIO лучше остановить, чтобы освободить память.
- Перед коммитом этапа должны проходить: Pest, PHPStan (уровень 8, без baseline), `npm run check`, `npm run types:check`, `npm run build`.

Порты на хосте: app 8090, Reverb 8091, MariaDB 3307, MinIO 9010/9011. Порты 80, 3306, 6379 и 8000 заняты другими проектами. Тесты используют БД `s3_toolkit_testing`.

## Тесты

- Pest. Помощники лежат в `tests/Pest.php` и `tests/Support/helpers.php`:
  - пользователи — `superadmin()`, `userWith(SystemPermission...)`, `storageUser($storage, StoragePermission...)`;
  - S3 — `fakeS3(...$responses)` (MockHandler, ответы по порядку) и `awsError($code, $status)`.
- Последний выполненный S3-запрос можно посмотреть так: `$s3->handler->getLastCommand()`.
- `fakeS3()` вызывайте **до первого запроса** в тесте. `StorageGatewayFactory` создаётся вместе с контроллером, и подмена после этого уже не сработает.
- Ключи доступа подменяются так: AWS — через `fakeIam()` в `tests/Feature/AccessKey/AccessKeyTest.php`, HTTP-провайдеры — через `Http::fake()`, MinIO (`mc`) — через `Process::fake()`.
- Отложенные Inertia-свойства (`Inertia::defer`) проверяются через `loadDeferredProps`. У каждого раздела настроек бакета своя группа.
- AWS SDK сам проверяет параметры, например AccessKeyId IAM должен быть не короче 16 символов. В тестовых данных используйте правдоподобные значения.

## Подводные камни

- **Octane + RoadRunner буферизуют ответ целиком в памяти.** Поэтому файлы скачиваются только перенаправлением на подписанную ссылку, а загружаются частями по 16 МБ. Ограничения: `max_request_size: 40` в `.rr.yaml`, `post_max_size=40M` в `docker/php/php.ini`.
- Синглтоны под Octane живут между запросами. Не храните в них состояние запроса. Фабрики, которые подменяются в тестах (`McRunner`, `AccessKeyManagerFactory`), привязаны через `bind`, а не `singleton`.
- Задачи ставятся в очередь с `afterCommit()`, `$timeout = 60`, а `retry_after` равен 90. Шаг раннера — 20 с.
- AWS SDK настроен с `request_checksum_calculation` / `response_checksum_validation` = `when_required`, иначе новые версии SDK ломаются на MinIO и других S3-совместимых хранилищах.
- Если провайдер не отдаёт настройку, это ошибка вида `NoSuch*` / 404. Её разбирает `StorageGateway::isMissing()`.
- Пустой фильтр правила жизненного цикла SDK отдаёт как `[]`, а не как объект. Шлюз превращает его в `{"Prefix": ""}`.
- `vue-tsc`: статический атрибут со строкой, где есть `//` (например, `placeholder="https://..."`), ломает разбор. Пишите через привязку: `:placeholder="'https://...'"`.
- Переключатели «форма / JSON» (политика, жизненный цикл) не должны преобразовывать данные во время загрузки. Для этого есть флаг `loading` и `nextTick`, как в `PolicySection.vue`.
- Компонент `Input` обновляет модель асинхронно. В браузерной автоматизации задавайте значение и нажимайте кнопку отдельными шагами. `Intl` форматирует числа с узкими неразрывными пробелами.
- Пространство имён `App\Http\Controllers\Object` использовать нельзя: Wayfinder создаёт TS-модуль `Object`, и он конфликтует с глобальным `Object`. Поэтому контроллеры объектов лежат в `Bucket`.
- MinIO Admin API шифрует ответы через Argon2id с parallelism=4, а в PHP такого нет. Поэтому управление ключами MinIO идёт через CLI `mc`: он скопирован в образ из `bitnamilegacy/minio`, потому что `dl.min.io` недоступен. Секрет генерируем сами и передаём `mc` флагами.
- Подпись PS256 для Yandex собрана вручную (`AccessKey/Support/RsaPss.php`), потому что в `openssl_sign` нет PSS. Тест сверяет её с `openssl dgst`.
- Образ MinIO для разработки — `bitnamilegacy/minio:latest`: официальные образы больше не публикуются, а quay.io отвечает 401. Ему нужны `mem_limit: 384m` и `GOMEMLIMIT`, иначе процесс падает по памяти.
- В версионированном бакете MinIO отдаёт префикс, в котором остались только маркеры удаления, как папку (`CommonPrefixes`). Это не ошибка сервиса.
- `TrustProxies` настраивается в `AppServiceProvider`, а не в `bootstrap/app.php`: в замыкании `withMiddleware` у консольных команд ещё нет конфига.

## Решения, принятые с пользователем

- Один экземпляр на организацию, без мультиарендности. Пользователи удаляются мягко. Временный пароль нужно сменить при первом входе. Первый суперадмин создаётся командой `app:create-superadmin`.
- Права выдаются **любые, но не себе**. Автор хранилища прав не получает. Шаблоны хранятся в БД, при выдаче маска копируется.
  - Ревью показало: два пользователя с правом выдачи могут выдать друг другу любые права. Ограничение «выдавать только то, что есть у себя» пока не введено, потому что это меняет правило, заданное пользователем. Нужно решение пользователя.
- В журнал пишутся изменения прав, пользователей, хранилищ, настроек бакетов и ключей, а также удаления, перемещения и выдача ссылок. Входы, загрузки и копирование не пишутся.
- Удаление бакета подтверждается вводом его имени.
- Двухфакторная аутентификация отложена.
- Endpoint и набор функций Selectel и VK Cloud, а также API управления ключами всех провайдеров, кроме MinIO, не проверены на реальных аккаунтах.

## Как добавить настройку бакета или действие над объектами

1. Метод в `StorageGateway`: только вызов S3 и приведение ответа к массиву.
2. Метод сервиса: чтение через `section()` (ошибку возвращает текстом), запись через `write()`/`call()` и журнал после успеха.
3. FormRequest от `BucketRequest`: право, функция хранилища, валидация, `toData()`.
4. Метод контроллера и маршрут в `routes/storages.php`, затем `wayfinder:generate`.
5. Тип в `resources/js/types`, компонент, переводы в `lang/ru.json` (`i18n_missing.py` из временного каталога сессии может уже не существовать — проверяйте поиском по ключам).
6. Тесты: фейковый S3 в `tests/Feature` и при возможности интеграционный на MinIO.
