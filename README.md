# Pots & Planters 2.0 — инфраструктура

Реализуется только этап 1: INF-01–INF-06 из [ТЗ](docs/audit/03-infrastructure-spec.md).
Страница frontend — временная заглушка. Бизнес-моделей, каталога, интеграций и SEO-функций нет.
Результаты приёмки и ограничения среды: [отчёт](docs/infrastructure-acceptance.md).

## Стек и совместимость

| Компонент | Выбранная версия | Проверенные требования |
|---|---|---|
| Laravel | 13.35.0, `composer.lock` | [Laravel 13 поддерживает PHP 8.3–8.5](https://laravel.com/docs/13.x/releases) |
| PHP-FPM | 8.4, Debian Bookworm | Runtime закреплён на 8.4; Composer разрешает зависимости с `platform.php=8.4.0` |
| Filament | 5.10.1 | [PHP ^8.2](https://filamentphp.com/docs/5.x/introduction/installation); [Illuminate ^13.0, intl, Livewire 4](https://github.com/filamentphp/filament/blob/5.x/packages/support/composer.json) |
| Horizon | 5.48.1 | [Illuminate ^13.0, pcntl, posix](https://github.com/laravel/horizon/blob/5.x/composer.json) |
| Next.js | 16.4.2 | [Node >=20.9 и React 19 совместимы](https://registry.npmjs.org/next/latest); App Router, standalone |
| Node.js | 24 LTS | [Поддерживаемая LTS-линия](https://nodejs.org/en/about/previous-releases); Docker `node:24-bookworm-slim` |
| React / React DOM | 19.3.0 | Версии зафиксированы в `package-lock.json` |
| Mantine core/hooks | 9.4.2 | [App Router SSR: стили, ColorSchemeScript, MantineProvider](https://mantine.dev/guides/next/) |
| TypeScript | 5.9.3 | Совместимость подтверждена production build Next.js |
| MariaDB | 11.8 LTS | [Поддерживаемая LTS-линия](https://mariadb.org/about/#maintenance-policy), Docker `mariadb:11.8` |
| Redis | 8.2 | Docker `redis:8.2-alpine`; PHP driver phpredis 6.3.0 |
| nginx | 1.28 | Docker `nginx:1.28-alpine`, динамический DNS upstream |
| Composer | 2 | Используется только на стадии сборки production backend |

Lockfiles фиксируют версии приложений и транзитивных пакетов. Теги базовых образов фиксируют
линии версий и получают исправления; digest и patch-версии образов не зафиксированы.
Фактические версии PHP/Node/MariaDB/Redis/nginx в собранных контейнерах нужно записать после запуска:

```sh
docker compose exec backend php -v
docker compose exec frontend node --version
docker compose exec db mariadb --version
docker compose exec redis redis-server --version
docker compose exec nginx nginx -v
```

## Архитектура и маршруты

```text
Локально: http://localhost:8080 ── nginx:80
Dokploy: https://<domain> ── Traefik (TLS) ── nginx:80
                                             ├── /, /_next/* → frontend:3000
                                             ├── /api/* → backend:9000 (PHP-FPM)
                                             ├── /admin/* → backend:9000
                                             ├── /livewire-<hash>/* → backend:9000
                                             ├── /filament/*, /horizon/* → backend:9000
                                             └── /css|js|fonts/filament/* → static assets
```

`/storage/*` читает только публичную часть Laravel storage; выполнение PHP там запрещено.
`/up` — встроенная Laravel liveness-проверка. `/api/health` — readiness: `SELECT 1` в MariaDB
и `PING` в Redis; JSON с `status=ok`, иначе 503 без внутренних подробностей.
Readiness не проверяет применённые миграции, worker или scheduler — для них отдельные проверки.

| Сервис | Назначение | Доступ |
|---|---|---|
| frontend | Next.js standalone / dev server в override | Только nginx |
| backend | PHP-FPM, Laravel, Filament | Только Docker network |
| nginx | Общая HTTP-точка входа | Локально `127.0.0.1:8080`; Dokploy через router |
| db | MariaDB | Внутренняя сеть `data`, порт не опубликован |
| redis | Cache, sessions, queues, Horizon metadata | Внутренняя сеть `data`, пароль, AOF, порт не опубликован |
| horizon | Worker очереди `redis/default` | Портов нет |
| scheduler | Единственный `schedule:work` | Портов нет; `replicas: 1` |

backend/horizon/scheduler используют один образ, один набор переменных и один `APP_KEY`.
Именованные volumes: `mariadb_data`, `redis_data`, `laravel_storage`.
У Laravel сохраняется `storage/app`; framework cache/views/log files не переносятся между релизами.
PHP работает от `www-data`, storage и bootstrap/cache подготовлены при сборке.
Production использует multi-stage builds, без bind mounts и runtime dev-зависимостей.
`nginx` ждёт healthy backend и frontend; PHP-сервисы ждут healthy MariaDB и Redis.

## Первый запуск: только Docker

Нужны Docker Engine и Compose с поддержкой `!reset` (Compose 2.24.4+).
Глобальные PHP, Composer, Node и npm не нужны. Все команды выполняются **из корня репозитория**.
Для первой сборки требуется интернет к Docker Hub, Debian, PECL, Packagist/GitHub и npm.

```sh
cp .env.example .env
docker run --rm -v "$PWD:/workspace" -w /workspace php:8.4-cli php scripts/setup-env.php
```

`setup-env.php` генерирует Laravel APP_KEY и три отдельных случайных пароля только в пустых
полях `.env`. Значения не выводятся, существующие сохраняются, права файла — 0600.
Это эквивалент генерации Laravel `base64:` ключа на 32 байта. Повторный запуск ключ не меняет.
Не создавайте `backend/.env`: Compose передаёт единое окружение всем PHP-сервисам.
В production базовый Compose принудительно устанавливает `APP_DEBUG=false`.
Для локального HTTP оставьте `SESSION_SECURE_COOKIE=false`; при HTTPS установите `true`.

Production-like запуск:

```sh
docker compose config --quiet
docker compose up -d --build
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --class=PageSeoSeeder --force
docker compose exec backend php artisan app:create-admin
```

`PageSeoSeeder` creates the seven initial static-page SEO records. It is safe to rerun: existing editor changes are preserved. Run it after migrations.

Создание администратора интерактивное: имя, уникальный email, пароль от 12 символов и подтверждение.
Пароль скрыт; опции CLI с паролем и автоматические аккаунты не предусмотрены.
`app:create-admin` выставляет служебный `is_admin`; обычный `make:filament-user` этого не делает.
Публичной регистрации нет. Не запускайте seeders для получения доступа.

Адреса: `http://localhost:8080/`, `/api/health`, `/admin`, `/horizon`.
Horizon использует ту же сессию администратора и закрыт для гостей и обычных пользователей
**во всех окружениях**, включая local.

Миграции запускаются явно один раз оператором релиза. Entrypoint не запускает миграций,
не генерирует ключи и не очищает общие Redis caches. На самом первом запуске до миграций
админка может вернуть ошибку; readiness проверяет только соединения.
Scheduler получает heartbeat на ближайшей минуте, поэтому healthcheck имеет start period 90 секунд.

## Разработка

**После `scripts/verify-acceptance.sh` запущен production-like режим.** В нём нет
Fast Refresh и dev-индикатора Next.js. Для повседневной разработки переключитесь
на `compose.dev.yaml` командами ниже. Если acceptance уже прошёл, dev-образы собраны:
можно запускать `up -d --wait --wait-timeout 180` без повторного `--build`.
В dev появится стандартный индикатор Next.js внизу слева; Turbopack используется
командой `next dev` по умолчанию в Next.js 16.

Для перехода с production-like на dev сначала остановите стек, сохранив volumes:

```sh
docker compose down
docker compose -f compose.yaml -f compose.dev.yaml config --quiet
docker compose -f compose.yaml -f compose.dev.yaml up -d --build
docker compose -f compose.yaml -f compose.dev.yaml exec backend php artisan migrate
docker compose -f compose.yaml -f compose.dev.yaml exec backend php artisan db:seed --class=PageSeoSeeder
```

Для повседневного запуска разработки используйте оба Compose-файла:

```sh
docker compose -f compose.yaml -f compose.dev.yaml up -d
```

Если dev-контейнеры уже работают, повторять команду при редактировании файлов не нужно:
frontend обновляется через Fast Refresh, PHP HTTP-код — на следующем запросе.
Добавляйте `--build` после изменения зависимостей, lockfiles или Dockerfile:

```sh
docker compose -f compose.yaml -f compose.dev.yaml up -d --build
```

Обычный `docker compose up -d` без dev override запускает production-like режим,
в котором hot reload и dev-индикатор Next.js отсутствуют.
В локальном окружении автоперезапуск отключён (`restart: "no"`), включая production-like
режим. После перезапуска Docker запускайте стек вручную соответствующей командой выше.
Политика `restart: unless-stopped` включается только через `compose.dokploy.yaml`.
Если контейнеры созданы до изменения политики, один раз повторите `up -d`
с нужными Compose-файлами, чтобы применить настройку.

Адрес тот же, `http://localhost:8080`. `src` frontend смонтирован, Next.js dev server
поддерживает Fast Refresh, nginx пропускает WebSocket. PHP-код смонтирован отдельно от vendor;
OPcache проверяет timestamps. Изменения PHP HTTP-кода видны на следующем запросе.
Для изменений кода долгоживущих workers/scheduler выполните:

```sh
docker compose -f compose.yaml -f compose.dev.yaml restart horizon scheduler
```

Зависимости устанавливаются при сборке, а не каждым процессом при старте. После изменения
lockfiles/Dockerfile пересоберите соответствующие сервисы. Меняя backend public assets,
также пересобирайте nginx. В dev не используется Vite: кастомной темы Filament пока нет.

Проверка файловых изменений (временно меняет два файла и восстанавливает их при выходе):

```sh
sh scripts/verify-dev-hmr.sh
```

Тесты HTTP и аутентификации, изолированная SQLite in-memory, без production данных:

```sh
docker compose -f compose.yaml -f compose.dev.yaml exec backend php artisan test --compact
```

В dev image есть Composer, PHPUnit и `pdo_sqlite`; в production runtime dev-зависимостей нет.

## Проверки и диагностика

После startup и миграций дождитесь healthy scheduler и выполните:

```sh
sh scripts/verify-runtime.sh
# Для dev:
sh scripts/verify-runtime.sh -f compose.yaml -f compose.dev.yaml
```

Скрипт проверяет nginx config, HTTP homepage/health, миграции, соединения, Horizon status,
фактическое выполнение диагностического задания через очередь и heartbeat scheduler.
Задание `InfrastructureProbe` пишет временную метку в cache и не создаёт бизнес-данных.
Для полного последовательного прогона production → restart/recreate → dev tests/hot reload → production:

```sh
sh scripts/verify-acceptance.sh
```

Скрипт сравнивает данные таблицы migrations до/после recreate, временно переключает режимы,
сохраняет volumes и возвращается в production после успешных проверок. При ошибке останавливается
на текущем шаге. Восстановление production: `docker compose -f compose.yaml -f compose.dev.yaml down`,
затем `docker compose up -d --wait --wait-timeout 180`. Запускайте этот прогон локально.
В тестах параметры SQLite in-memory принудительно перекрывают Compose DB environment.

Нужно отдельно проверить браузером стили Mantine, вход/выход Filament и Livewire без ошибок.
Для проверки порта в redirect: `curl -I http://localhost:8080/admin` — Location должен
содержать `/admin/login` на том же origin, включая `:8080` при абсолютном URL.

```sh
docker compose ps
docker compose logs --tail=100 backend nginx frontend horizon scheduler
docker compose exec backend sh
docker compose exec frontend sh
docker compose exec backend php artisan infrastructure:ready
docker compose exec backend php artisan infrastructure:queue-check
docker compose exec horizon php artisan horizon:status
docker compose exec scheduler php artisan infrastructure:scheduler-status
docker compose exec backend php artisan migrate:status
```

Laravel пишет через `LOG_CHANNEL=stderr`: логи доступны в `docker compose logs backend`
(для CLI также horizon/scheduler). nginx и Next.js выводят логи в stdout/stderr.
При проблемах с правами существующего storage volume:

```sh
docker compose exec -u root backend chown -R www-data:www-data storage/app
```

Restart/recreate с проверкой сохранности начальных таблиц и миграций:

```sh
docker compose exec backend php artisan migrate:status
docker compose restart
docker compose up -d --wait --wait-timeout 180
docker compose up -d --force-recreate --wait --wait-timeout 180
docker compose exec backend php artisan migrate:status
sh scripts/verify-runtime.sh
```

Команды остановки и повторного старта сохраняют named volumes:

```sh
docker compose stop
docker compose start
docker compose down
docker compose up -d
```

Не используйте `down -v` для штатного обслуживания: он удаляет данные.
Не меняйте Compose project name между redeployments.

Пересборка только frontend:

```sh
docker compose up -d --build --no-deps frontend
```

Backend code и assets должны обновляться совместно с workers и nginx:

```sh
docker compose build backend nginx horizon scheduler
docker compose up -d --no-deps backend nginx horizon scheduler
```

## Dokploy

Реальный сервер в рамках этого этапа не разворачивается. Репозиторий готовится для
Compose application. [Официальные настройки доменов](https://docs.dokploy.com/docs/core/domains)
и [изоляция Compose deployments](https://docs.dokploy.com/docs/core/docker-compose/utilities).

1. Создайте Compose application, подключите Git repository, build context — корень.
2. Передайте переменные из `.env.example` через Environment, с отдельными секретами deployment.
   `APP_URL=https://<domain>`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`,
   `SESSION_DOMAIN=null`, `TRUSTED_PROXIES=*`, `API_INTERNAL_URL=http://nginx/api`.
3. Используйте `compose.yaml` + `compose.dokploy.yaml`. При необходимости Custom Command:
   `docker compose -f compose.yaml -f compose.dokploy.yaml up -d --build`.
   Override включает `restart: unless-stopped` для всех сервисов и снимает локальный host port. На Dokploy убедитесь, что platform-generated
   domain labels применены к итоговому Compose; не обходите их custom command.
   Если версия UI принимает только один файл, заранее сформируйте merged файл
   **без секретов** с placeholder environment через `docker compose ... config --no-interpolate`
   и проверьте generated Compose в Preview. Не храните раскрытые секреты в Git.
4. В Domains выберите service `nginx`, container port `80`, path `/`, HTTPS и сертификат.
   Strip Path выключен, Internal Path пустой. Отдельные домены/API-router не нужны.
5. Dokploy должен подключить **nginx** к своей сети Traefik, сохранив его связь с сетью `app`.
   Включите isolated deployments, чтобы несколько проектов с именем nginx не конфликтовали.
   Через Compose Preview проверьте networks и domain labels перед deploy.
6. TLS завершается на Traefik. Он передаёт Host/X-Forwarded-Proto/Port; nginx передаёт
   их Laravel. `TRUSTED_PROXIES=*` допустим в этой схеме, поскольку PHP-FPM не опубликован
   и HTTP-вход идёт через nginx. Внешние forwarded headers должен нормализовать Traefik.
   При изменении границы сети задайте явные адреса/CIDRs доверенных прокси вместо `*`.
7. Сохраните стабильные project name и названия volumes. Настройте backups MariaDB и
   `laravel_storage`; APP_KEY храните вместе с секретами восстановления. Все три PHP-сервиса
   должны получать одинаковый ключ при каждом релизе.
8. Оставьте только один scheduler replica. Не добавляйте дублирующее расписание в Dokploy
   Scheduled Jobs или cron. Текущие задачи также имеют Redis `onOneServer` и `withoutOverlapping`.
9. Staging закройте авторизацией на уровне router/access gateway. Глобальный `noindex`
   приложением не устанавливается; SEO-реализация относится к этапу 4.

После изменения Domains Compose приложение необходимо redeploy — Dokploy применяет Docker labels.
Проверьте HTTPS redirects, Secure/HttpOnly/SameSite cookies и отсутствие mixed content
на `/admin/login`, ресурсах Filament и Livewire update-запросах.

## Выпуск и откат

Применяйте миграции одним release-процессом после готовности DB, до открытия нового релиза трафику.
Для первого инфраструктурного запуска достаточно `up`, затем `migrate --force` как выше.
Для последующих обновлений с изменением схемы планируйте окно обслуживания: `artisan down`,
остановка horizon/scheduler, backup, build, `docker compose run --rm --no-deps backend php artisan migrate --force`,
пересоздание backend/nginx/frontend/horizon/scheduler, затем `artisan up` и проверки.
Не запускайте несколько migration jobs одновременно.

В текущем этапе readiness показывает доступность DB/Redis, поэтому release pipeline обязан
отдельно дождаться успешной миграции. Не считайте HTTP health заменой release-проверке схемы.
Используйте `IMAGE_TAG` с идентификатором релиза; все образы, включая nginx и workers,
должны соответствовать одному commit. Для rollback возвращайте согласованные образы.
Возврат кода не откатывает схему и данные; rollback миграций может быть разрушительным.
Автоматических destructive команд нет. Не вращайте APP_KEY и пароли при обычном redeploy.
