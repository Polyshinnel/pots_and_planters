# Этап 1 — отчёт INF-01–INF-06

Дата: 2026-10-17. Статус: автоматическая контейнерная приёмка пройдена;
осталась ручная проверка frontend и admin через браузер. Полная приёмка **пока не заявляется**.

Пользователь выполнил `sh scripts/verify-acceptance.sh` в своём терминале и подтвердил
финальную строку `Automated acceptance checks completed; finish browser/admin verification in README.`
Скрипт использует `set -eu`, поэтому эта строка означает успешное завершение всех его шагов.
Это подтверждение пользователя; Docker socket внутри сессии агента по-прежнему недоступен.

## Контрольные точки

| Шаг | Создано/изменено | Проверки | Результат / следующий шаг |
|---|---|---|---|
| INF-01 | backend/, frontend/, docker/, .env.example, .gitignore, .dockerignore, README | Осмотр repo и ТЗ, проверка структуры, официальных требований | Исходного кода не было; документы сохранены; переход к Laravel |
| INF-02 | Laravel 13, composer.lock, PHP-FPM Dockerfile, MariaDB/Redis Compose, health endpoint | composer resolve с PHP 8.4 platform, composer validate, PHP lint, compose config | Совместимость разрешена без force/ignore-platform-reqs; runtime соединения и начальные миграции PASS в прогоне пользователя |
| INF-03 | AdminPanelProvider, User.is_admin, app:create-admin, Horizon provider/config, scheduler/heartbeat, InfrastructureProbe | Laravel boot, route:list, filament:assets, schedule:list с array cache; HTTP tests | Панель зарегистрирована; 5 тестов / 20 assertions прошли; dev-тесты входа и работа очереди PASS в прогоне пользователя; браузер ожидает проверки |
| INF-04 | Next.js App Router, TypeScript, Mantine SSR, placeholder, API clients, npm lockfile, frontend Dockerfile | npm run build, npm run typecheck, проверка standalone и HTML | PASS: build, HTTP и dev hot reload; контейнерный прогон выполнен пользователем |
| INF-05 | 7 сервисов, nginx routing, volumes/networks/healthchecks, dev/Dokploy overrides | Compose config для трёх вариантов, проверки resolved JSON | PASS: Compose config, сборки и runtime-прогон пользователя |
| INF-06 | README, setup-env.php, runtime/acceptance/hot-reload scripts, auth/HTTP tests | Shell syntax, PHP lint, security/config assertions | Документация и автоматический runtime-прогон PASS; осталась ручная браузерная проверка |

## Версии

Из lockfiles/установленных зависимостей: Laravel 13.35.0, Filament 5.10.1,
Horizon 5.48.1, Next.js 16.4.2, React/React DOM 19.3.0, Mantine core/hooks 9.4.2,
TypeScript 5.9.3.

Образы: PHP 8.4 FPM Bookworm, Node 24 LTS Bookworm Slim, MariaDB 11.8 LTS,
Redis 8.2 Alpine, nginx 1.28 Alpine, Composer 2 (build stage), phpredis 6.3.0.
Контейнерные сборки успешно выполнены пользователем. Скрипт вывел версии PHP и Node,
но численные patch-версии не переданы агенту; для записи в отчёт нужны результаты команд из README.
Проверки на хосте выполнены PHP 8.5.10 и Node 24.14.0;
контейнерная проверка PHP 8.4 выполнена в последующем прогоне пользователя.

Официальные источники совместимости перечислены в README. Composer разрешил PHP 8.4,
Laravel 13 и актуальные доступные stable Filament/Horizon без изменения согласованного стека.

## Команды и результаты

| Команда/проверка | Статус |
|---|---|
| composer create-project laravel/laravel backend '^13.0' --no-install --no-scripts | PASS, шаблон 13.11.0 из cache |
| composer update / composer require, network disabled | Resolution PASS; начальная установка BLOCKED отсутствующими архивами |
| composer install --prefer-dist --no-interaction | Выполнена пользователем; наличие vendor и Laravel boot проверены |
| composer validate --strict --no-check-publish | PASS |
| composer check-platform-reqs --no-dev | PASS на PHP хоста 8.5.10 |
| PHP lint, исходные app/bootstrap/config/routes/migrations/scripts | PASS |
| setup-env.php: ключ, отдельные пароли, права 0600, повторный запуск | PASS в изолированном временном каталоге |
| php artisan --version | PASS, Laravel 13.35.0 |
| php artisan route:list --path=admin | PASS, dashboard/login/logout |
| php artisan filament:assets | PASS |
| CACHE_STORE=array SESSION_DRIVER=array php artisan schedule:list | PASS, snapshot/heartbeat; это не запуск scheduler |
| php artisan test --compact --filter=InfrastructureTest | PASS, 5 tests / 20 assertions |
| Тесты сохранённого пользователя (AuthenticationTest) | PASS в dev-контейнере в полном прогоне пользователя; на хосте pdo_sqlite отсутствует |
| npm install --no-audit --no-fund | Выполнена пользователем; lockfile и зависимости проверены |
| npm run build | PASS, Next.js 16.4.2, TypeScript, prerendered homepage |
| npm run typecheck | PASS |
| .next/standalone/server.js, required-server-files.json, rendered HTML | PASS: standalone + Mantine Paper и placeholder в HTML |
| node .next/standalone/server.js | BLOCKED: listen EPERM на 127.0.0.1:3100 |
| docker compose config --quiet | PASS с валидационными значениями обязательных переменных |
| docker compose -f compose.yaml -f compose.dev.yaml config --quiet | PASS |
| docker compose -f compose.yaml -f compose.dokploy.yaml config --quiet | PASS |
| Resolved Compose assertions | PASS: 7 сервисов; публикация только nginx; общий APP_KEY; named volumes; dev/prod targets; APP_DEBUG=false в prod |
| sh -n scripts/verify-runtime.sh scripts/verify-dev-hmr.sh scripts/verify-acceptance.sh | PASS, каждый скрипт проверен отдельно |
| docker info | BLOCKED: permission denied на /var/run/docker.sock |
| docker compose up -d --build | PASS после двух исправлений Dockerfile, подтверждено пользователем |
| sh scripts/verify-acceptance.sh | PASS в терминале пользователя, подтверждена финальная строка |
| Production/dev runtime checks | PASS: nginx -t, homepage/health, migrations, DB/Redis, Horizon status, queue acknowledgement, scheduler heartbeat |
| Restart + force-recreate | PASS: healthy startup и совпадение fingerprint данных migrations до/после |
| Dev php artisan test --compact | PASS в dev image; точное число assertions из вывода не передано |
| sh scripts/verify-dev-hmr.sh | PASS: изменения frontend/PHP видны без пересоздания контейнеров; исходные файлы восстановлены |
| Возврат в production-like после dev | PASS: финальный up --wait прошёл |

## Исправление после первого Docker build

Первый запуск в терминале пользователя остановился на Composer/Artisan build step.
В Dockerfile каталоги storage создавались после `composer dump-autoload`, хотя его
`post-autoload-dump` запускает `package:discover` и загружает Filament view services.
При исключённом через .dockerignore `storage/framework` это приводит к
`InvalidArgumentException: Please provide a valid cache path.`

Ошибка воспроизведена в изолированной копии backend без runtime-каталогов:
`php artisan package:discover` — exit 1 до создания каталогов, exit 0 после.
Dockerfile исправлен: mkdir выполняется до Composer scripts. Последующая
контейнерная сборка и полный acceptance script успешно выполнены пользователем.

Повторная сборка прошла bootstrap и остановилась на `COPY --chmod`: в окружении
пользователя отключён BuildKit. В development и runtime стадиях этот флаг заменён
обычным `COPY` и `RUN chmod 755` перед переключением на www-data. Остальных
BuildKit-only инструкций в Dockerfiles нет. Последующий Docker build прошёл.

## Критерии приёмки ТЗ

| № | Критерий | Статус |
|---|---|---|
| 1 | Base + dev Compose config | PASS |
| 2 | Все production-like сервисы без crash loops | PASS: up --wait до/после restart, recreate и возврата из dev |
| 3 | Homepage HTTP 200 и видимые стили Mantine | HTTP и SSR/CSS PASS; видимые стили ожидают проверки браузером |
| 4 | /api/health HTTP 200 JSON | PASS: HTTP application tests и runtime с MariaDB/Redis |
| 5 | Filament redirect/login и вход администратора | Dev authentication/access tests PASS; работа Filament подтверждена пользователем; browser guest/logout check Horizon ожидает подтверждения |
| 6 | MariaDB connectivity + migrations | PASS в прогоне пользователя |
| 7 | Redis, Horizon, фактическое выполнение queue job | PASS: infrastructure:ready, horizon:status, infrastructure:queue-check |
| 8 | Единственный scheduler работает | PASS: replicas=1, Redis mutex, свежий runtime heartbeat |
| 9 | Next.js production build + standalone | PASS на хосте и в Docker build/runtime |
| 10 | Dev file edit отражается без rebuild | PASS: verify-dev-hmr.sh в прогоне пользователя |
| 11 | DB/Redis без публичных production ports | PASS в resolved Compose, применённом при запуске; отдельный inspect вывода портов агенту не передан |
| 12 | Restart/recreate и сохранность данных MariaDB | PASS: совпал fingerprint записей migrations до/после; volumes сохранены |
| 13 | README: dev/prod/Dokploy/диагностика | PASS |
| 14 | Нет функций этапов 2–4 | PASS по исходному коду |

## Ручная проверка и режим разработки

Пользователь подтвердил, что Filament работает и Horizon открывается после входа
в админку. Horizon использует ту же сессию: повторного запроса пароля в этой сессии
не должно быть. Проверка `/horizon` гостем в приватном окне ещё ожидает подтверждения.

После успешного acceptance script остаётся production-like режим: отсутствие
hot reload и dev-индикатора в нём ожидаемо. Для работы с Fast Refresh необходимо
переключиться на `compose.dev.yaml`; инструкции уточнены в README.
Автоматическая проверка изменений frontend/PHP в dev ранее прошла.

## Осталось выполнить

2. Проверить браузером `http://localhost:8080/`: видны стили Mantine и placeholder.
3. Открыть `/admin` гостем: переход на `/admin/login` на том же origin; войти созданным
   администратором, открыть dashboard и `/horizon`, выполнить logout. Проверить отсутствие
   ошибок загрузки Filament assets/Livewire, после logout доступ снова закрыт.
4. Передать фактические версии PHP/Node/MariaDB/Redis/nginx из команд README для записи patch-версий.
5. После подтверждения оставшихся браузерных проверок обновить критерии 3 и 5 и итоговый статус.

Файлы `.git` в среде недоступны как рабочий Git repository; commit не создавался.
Секреты исключены через .gitignore/.dockerignore, значения .env в отчёт не копируются.
Реальный Dokploy deployment не выполнялся и по ТЗ не требуется для локальной приёмки.
