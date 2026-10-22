# Pots & Planters 2.0 — Project Handoff Context

**Актуально на 22 октября 2026**

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
- BE-05 — News: завершён. Laravel suite — 50 тестов / 371 assertions, News-focused — 11 / 188; Pint, frontend typecheck и regression tests пройдены. Next.js production build прошёл с `NODE_ENV=production`. Dry-run подтвердил 7 записей, 3 категории и совпадающие SHA-256 изображений. Read-only Compose smoke подтвердил News Page SEO API и canonical архива, API/SSR для семи исторических slug, canonical/NewsArticle JSON-LD и 404. Временная запись в `potsandplanters_test` отобразилась в уже собранном Next.js без rebuild; статья и категория удалены. Владелец проекта вручную подтвердил успешный импорт исторических новостей, создание новой статьи и работу интерфейса Filament. Production migration/import/deploy не выполнялись. BE-06 не начинать без отдельного подтверждения владельца.

BE-02/03 считаются завершёнными согласно предыдущей проверке репозитория.

## Согласованный порядок

1. BE-04 — PDF-каталоги.
2. BE-05 — News.
3. BE-06 — контакты и шоурумы.
4. BE-07 — заявки и Telegram.
5. BE-08 — товары.
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

Три шоурума: DEVA HARMONY TRADING (Al Quoz, Dubai), Real Touch Flowers (Jebel Ali, Dubai), Design House Projects (Riyadh). Контакты в Header/Footer и на странице Contacts должны использовать согласованный единый источник.

## Заявки

Четыре типа: `contact`, `product_enquiry`, `catalogue_download`, `cart_enquiry`.

Один endpoint `POST /api/v1/enquiries`. Сначала надёжное сохранение в MariaDB, затем Telegram через Horizon. Использовать idempotency, серверные snapshots, retry и безопасную обработку PII. Ошибка Telegram не должна терять заявку.

Корзина остаётся в localStorage. Не очищать её при ошибке и не удалять недоступные позиции молча.

## Товары

Самый сложный модуль, поэтому отложен. Реальный OpenCart SQL export нужно исследовать до окончательного проектирования SKU/variants. Нельзя публиковать фиктивные цены и характеристики. Непроверенная цена — `on_enquiry`.

## Миграция OpenCart

Нужны SQL snapshot, изображения, PDF, SEO aliases, SEO Pro/Manager logic и реестр старых URL. Критически важны старые адреса вида `?product_id=...`; нельзя перенаправлять их просто на страницу категории. Требуется проверенный mapping и корректные 301 redirects.

## Privacy / Cookies

Путь политики — `/private-policy`, не менять его произвольно. Cookie notice с одной кнопкой `I agree`, сохранение подтверждения на шесть календарных месяцев. Если подключаются необязательные аналитические или маркетинговые cookies, необходим отдельный корректный механизм согласия. Перед запуском реальных заявок обновить Privacy Policy.

## SEO

Canonical должны соответствовать маршрутам. Google Search Preview — обязательный общий инструмент Filament. Не забыть sitemap, robots, metadata, OG, индексацию товаров, News и редиректы. Сохранять SEO-значимые исторические адреса.

## Главный принцип

**Один законченный модуль = управляемые данные в Filament + работающий API + реальное отображение на Next.js + тесты + удалённые mock-зависимости этого модуля.**
