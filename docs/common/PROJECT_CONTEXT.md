# Pots & Planters 2.0 — Project Handoff Context

**Актуально на 2 ноября 2026**

## Проект

Pots & Planters 2.0 — премиальный B2B-сайт кашпо, декоративных горшков и искусственной зелени для рынка UAE. Основная цель — запросы коммерческих предложений, а не онлайн-оплата.

Публичный сайт на английском. Обсуждение задач и ТЗ для Codex — на русском.

## Стек

- Frontend: Next.js 16 App Router, React 19, TypeScript, Mantine 9.
- Backend: Laravel 13, PHP 8.4, Filament 5.
- MariaDB, Redis, Horizon, Docker Compose, nginx, Dokploy.
- Staging и production должны быть изолированы.
- Визуальный стиль: premium/minimal, olive `#989B86`, Lato, много воздуха.

## Текущий статус

- Infrastructure — завершена.
- Frontend FE-01…FE-12 — считается завершённым.
- BE-01 — read-only архитектурный аудит завершён.
- BE-02 — SEO статических страниц, по истории проекта завершён.
- BE-03 — категории, по истории проекта завершён.
- BE-04 — PDF Catalogues Management — завершён: Filament/API по фактическим изменениям репозитория, Next.js SSR-интеграция и проверки frontend выполнены.
- BE-05 — News: завершён. Laravel suite — 50 тестов / 371 assertions, News-focused — 11 / 188; Pint, frontend typecheck и regression tests пройдены. Next.js production build прошёл с `NODE_ENV=production`. Dry-run подтвердил 7 записей, 3 категории и совпадающие SHA-256 изображений. Read-only Compose smoke подтвердил News Page SEO API и canonical архива, API/SSR для семи исторических slug, canonical/NewsArticle JSON-LD и 404. Временная запись в `potsandplanters_test` отобразилась в уже собранном Next.js без rebuild; статья и категория удалены. Владелец проекта вручную подтвердил успешный импорт исторических новостей, создание новой статьи и работу интерфейса Filament. Production migration/import/deploy не выполнялись.
- BE-06 — Contacts & Showrooms: завершён и принят владельцем. Управляемые в Filament шоурумы и singleton-настройки выдаются через `GET /api/v1/contacts`; Next.js использует единый API без runtime mock fallback. Проверены пустой ответ, 503, опубликованность/primary и появление временного изменения тестовой БД в SSR без rebuild. Laravel suite — 57 тестов / 427 assertions; frontend typecheck и suites contacts 3/3, catalog 12/12, product 9/9, enquiry 8/8, forms 4/4; production build и Pint затронутых PHP-файлов прошли. Общий Pint выявил 11 ранее существующих style issues вне BE-06. Browser-level Filament smoke не выполнялся; владелец подтвердил полную приёмку. Production migration/import/deploy не выполнялись.

BE-02/03 считаются завершёнными согласно предыдущей проверке репозитория.

- BE-07 — Заявки и Telegram: реализован и принят в локальной dev-среде для Contact, Catalogue Download, Filament Enquiries и Telegram; ручная приёмка BE-07.6d — **31/31 PASS**. Автоматические проверки из BE-07.5 handoff: Laravel — 80 тестов / 572 assertions; enquiry/admin/delivery/download и Catalogue API profile — 28 / 183; frontend typecheck, suites catalog/product/enquiry/forms/contacts — 41 всего; production-mode build, SSR/API smoke и `git diff --check` прошли. `NEXT_PUBLIC_ENQUIRIES_ENABLED=true` задаётся только в `compose.dev.yaml`; production activation, миграции и deploy не выполнялись. Product Enquiry и Cart Enquiry остаются отключены до BE-08. Открытые задачи: Squid на staging, S3 backup, retention cleanup и юридическая экспертиза Privacy Policy.
- BE-08 — D1–D6 утверждены владельцем 2026-11-02. Этапы BE-08.3.1–08.3.5 реализованы и приняты владельцем после ручной проверки Filament: Brand, Supplier, Product Series, Product Types, Attributes/Options и связь Product Type ↔ Attributes. FIX-01 восстановил `public_id` в Telegram payload. BE-08.4.1–08.4.4 реализованы и автоматически проверены; ручная проверка Filament владельцем ожидается. BE-08.4.5 Product SEO & URLs реализован и автоматически проверен: SEO overrides, глобально уникальный slug, история внутренних canonical paths и resolver; миграция прошла только в `potsandplanters_test`. Ручная приёмка BE-08.4.5 ожидается. Product API и публичная Laravel-backed страница ещё не реализованы; текущая Next.js страница товара использует mock repository, поэтому HTTP 301 и API/Next.js интеграция отложены до публичного этапа. BE-09, импорт OpenCart и production activation не выполнялись.

## Согласованный порядок

Дополнение BE-08.4.4.1: реализована асинхронная оптимизация Product media через Intervention Image 3.11.10 и GD с WebP/EXIF в общем PHP Docker-образе. Новые оригиналы приватны; Horizon использует существующую `redis/default`, создавая три прозрачных квадратных WebP без увеличения содержимого. Добавлены статусы, безопасные ошибки, защита от повторных jobs и admin Retry. Профильные проверки 30/219, полный backend suite 157/1152; реальная Redis-очередь проверена. Миграция применена только к `potsandplanters_test`, обычная dev-схема не менялась. Legacy backfill и ручная приёмка не выполнялись. Подробности: `docs/backend/BE-08.4.4.1_IMPLEMENTATION_REPORT.md`. BE-08.4.5 реализован и автоматически проверен; ручная Filament приёмка ожидается. Отчёт: `docs/backend/BE-08.4.5_IMPLEMENTATION_REPORT.md`.

1. BE-04 — PDF-каталоги.
2. BE-05 — News.
3. BE-06 — контакты и шоурумы (завершён и принят).
4. BE-07 — заявки и Telegram (принят в локальной dev-среде).
5. BE-08 — товары: D1–D6 утверждены; справочники BE-08.3.1–08.3.5 приняты; реализации BE-08.4.1–08.4.4 ожидают ручной проверки Filament.
6. BE-09 — финальная интеграция и миграция OpenCart.

Solutions подключить после появления реальных товаров, как отдельную подзадачу финальной интеграции. Не забыть этот модуль.

## Правила разработки

- Каждый модуль реализуется вертикально: MariaDB → Laravel → Filament → API → Next.js.
- После проверки заменяем соответствующие mock-данные настоящими.
- Не допускается скрытый fallback на mock при ошибке API.
- Не менять публичный дизайн без отдельного запроса.
- Не добавлять лишние зависимости и не переписывать работающие компоненты без причины.
- Сохранять существующие маршруты, SEO и пользовательское поведение.
- Codex обязан учитывать актуальные ручные изменения в репозитории.
- Production-миграции — только с отдельного разрешения.
- Все публичные API — `/api/v1`, с типизированными DTO.
- Для Filament создавать удобные переиспользуемые компоненты, но не строить абстрактный UI-фреймворк заранее.

## Filament UI

Google Search Preview, созданный в BE-02, должен повторно использоваться во всех SEO-редакторах: категории, товары, новости и другие сущности. Нельзя создавать отдельные несовместимые реализации.

По мере работы развивать повторно используемые компоненты: PDF uploader, media preview, news preview, showroom contact fields, enquiry status/history, product gallery.

## PDF-каталоги

- Один каталог может принадлежать нескольким категориям.
- Связь many-to-many через `catalogue_category`.
- PDF хранить приватно, обложки публично.
- BE-04 завершён: `GET /api/v1/catalogues` возвращает только опубликованные каталоги в стабильном порядке; DTO содержит `id`, `slug`, `title`, nullable `description`, `coverImage`, `coverAlt`, `categoryIds` и опубликованные `categories[{id,name,slug}]`. PDF path и URL скачивания не публикуются.
- Next.js `/catalogues`, категория через `?category=<slug>`, каталоговый CTA и домашний слайдер используют серверный API через `serverApiFetch`/`API_INTERNAL_URL`, без mock fallback. Пустая выдача отображается явно, ошибка API не маскируется.
- Скачивание PDF после сохранения заявки подключить в BE-07.
- Не имитировать успешную отправку или скачивание.
- Сохранить существующий фильтр `/catalogues?category=...`.
- Проверки BE-04.4: frontend typecheck; catalog 12/12, product 9/9, enquiry 8/8, forms 4/4; SSR smoke для главной, списка и трёх реальных category slug, unknown slug, canonical и отсутствия внутренних/private URL; `git diff --check`; production Next.js build успешен при `NODE_ENV=production`.
- Ограничения: frontend lint script отсутствует; browser-проверка интерактивного модального окна не выполнялась. Отправка заявки и gated PDF download остаются в BE-07.

## News

Есть семь исторических публикаций из snapshot frontend mock с исходными изображениями. News использует Filament RichEditor с безопасным HTML и отдельный live preview; Google Search Preview остаётся общим SEO-инструментом. Импортёр `news:import-historical` проверяет семь записей и SHA-256 изображений, по умолчанию выполняет dry-run, а запись требует `--apply`; совпадающие уже существующие файлы переиспользуются, несовпадающие блокируют импорт. Владелец проекта вручную подтвердил успешный импорт, создание новой статьи и работу Filament. Не выполнять production-импорт без отдельного разрешения. Laravel API публикует только полные published-статьи активных категорий; Next.js получает News через серверный API без runtime mock fallback и рендерит slug динамически. Slug можно исправить вручную, автоматические redirects не создаются. SEO архива `/news` берётся из Page SEO API, SEO статьи — из её полей. Автоматизированные проверки, API/SSR для семи статей и появление новой тестовой публикации после production build также подтверждены. Production migration/import/deploy не выполнялись.

## Контакты

Три шоурума: DEVA HARMONY TRADING (Al Quoz, Dubai), Real Touch Flowers (Jebel Ali, Dubai), Design House Projects (Riyadh). Контакты в Header/Footer, Home, Product, Cart/Enquiry, Catalogues, About и на странице Contacts используют согласованный Laravel API `GET /api/v1/contacts` с envelope `{ data: { primary, whatsappUrl, showrooms } }`. DEVA — primary; наружу выдаются только опубликованные шоурумы в стабильном порядке. Пустая база возвращает `primary: null`, `whatsappUrl: null`, `showrooms: []`; ошибка API не подменяется фиктивными контактами. Next.js делает runtime-запросы с `no-store`, изменения отображаются без rebuild. Тесты Contacts и SSR, включая переключение primary, снятие публикации primary, пустой ответ и 503, пройдены. При BE-06.4 исправлена серверная валидация частичного Instagram-контакта. Владелец подтвердил полное закрытие BE-06. Production operations не выполнялись; browser-level Filament smoke не проводился.

## Заявки

Поддерживаемые сервером типы: `contact`, `catalogue_download`, `product_enquiry`, `cart_enquiry`. В локальной dev-среде и приёмке активны Contact и Catalogue Download; Product Enquiry и Cart Enquiry отключены до BE-08, поскольку авторитетного каталога товаров/SKU ещё нет.

Один endpoint `POST /api/v1/enquiries`. Заявка, item/catalogue snapshots, idempotency record и событие MariaDB outbox сохраняются в одной транзакции; успешный ответ отправляется после commit. Повтор с тем же ключом и payload возвращает исходную заявку, конфликтующий payload — `409`; ключ живёт 24 часа. MariaDB — источник истины: scheduler/dispatcher выбирает due deliveries из outbox, резервирует их и передаёт отдельные jobs в Redis/Horizon. Доставки имеют recipient snapshots, lease, retry/backoff и диагностические статусы; сбой enqueue/Telegram не удаляет заявку, terminal failures можно вернуть на retry. Telegram Settings в Filament управляет зашифрованным bot token, настройками Squid proxy и несколькими получателями; предусмотрены admin-only Test Connection и Send Test Message. Прямого fallback при сбое настроенного proxy нет.

Catalogue Download доступен только после сохранения заявки и проверки опубликованного каталога и приватного PDF. Случайный bearer grant хранится как SHA-256 hash, связан с enquiry/catalogue, действует 15 минут и передаётся браузером только в `Authorization`; download endpoint повторно проверяет grant и отдаёт PDF потоково. Grant не размещается в URL или browser storage.

`NEXT_PUBLIC_ENQUIRIES_ENABLED=true` присутствует только в dev Compose; базовая конфигурация и production значение по умолчанию — выключено. Production activation не выполнялась. Открытые задачи: проверить Squid на staging (без direct fallback), настроить S3 backup, реализовать и проверить retention cleanup, получить юридическую экспертизу Privacy Policy. Retention cleanup и S3 backup не считаются реализованными.

Корзина остаётся в localStorage. Не очищать её при ошибке и не удалять недоступные позиции молча.

## Товары

Модель согласована ADR-01–09 и D1–D6: каждая конфигурация — самостоятельный Product, ProductVariant не вводится; SKU уникален в пределах Brand и нормализуется удалением внешних пробелов и переводом ASCII `a-z` в `A-Z` без изменения внутренних пробелов/пунктуации; цена хранится как nullable `DECIMAL(15,4)` в AED и допускает только NULL либо значение строго больше нуля. У Product обязательны Brand, Supplier и Product Type; Series необязательна и принадлежит тому же Brand. Справочники BE-08.3.1–08.3.5 реализованы и приняты владельцем после ручной проверки Filament. BE-08.4.1 Product Core, BE-08.4.2 Product Categories и BE-08.4.3 Product Attribute Values реализованы и автоматически проверены в `potsandplanters_test`; ручная проверка Filament владельцем остаётся условием приёмки этих CRUD этапов. BE-08.4.3 хранит scalar значения типизированно, option selections отдельно; required характеристики проверяются при переходе в `published`. BE-08.4.4 добавил административную галерею Filament с переиспользуемыми MediaAsset, сортировкой, главным изображением и ALT; автоматические проверки выполнены, ручная проверка владельцем ожидается. BE-08.4.5 добавил SEO overrides, глобально уникальный Product slug, canonical URL alias history и тестируемый resolver; миграция применена только в `potsandplanters_test`. Автоматические проверки пройдены, ручная Filament проверка ожидается. Product API отсутствует, текущая Next.js Product page читает mock repository; HTTP 301/публичный canonical не реализованы. До Product API статус `published` не означает доступность товара на публичном сайте. BE-09 миграция и production activation не выполнялись. Не придумывать товары, SKU, цены или характеристики.

## Миграция OpenCart

Нужны SQL snapshot, изображения, PDF, SEO aliases, SEO Pro/Manager logic и реестр старых URL. Критически важны старые адреса вида `?product_id=...`; нельзя перенаправлять их просто на страницу категории. Требуется проверенный mapping и корректные 301 redirects.

## Privacy / Cookies

Путь политики — `/private-policy`, не менять его произвольно. Cookie notice с одной кнопкой `I agree`, сохранение подтверждения на шесть календарных месяцев. Если подключаются необязательные аналитические или маркетинговые cookies, необходим отдельный корректный механизм согласия. Текст Privacy Policy обновлён для фактического потока заявок, но требуется юридическая экспертиза; production activation не выполнялась. Retention cleanup и S3 backup остаются открытыми.

## SEO

Canonical должны соответствовать маршрутам. Google Search Preview — обязательный общий инструмент Filament. Не забыть sitemap, robots, metadata, OG, индексацию товаров, News и редиректы. Сохранять SEO-значимые исторические адреса.

## Главный принцип

**Один законченный модуль = управляемые данные в Filament + работающий API + реальное отображение на Next.js + тесты + удалённые mock-зависимости этого модуля.**
