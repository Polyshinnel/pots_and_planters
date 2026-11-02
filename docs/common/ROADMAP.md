# Pots & Planters 2.0 — Roadmap

**Срез на 2 ноября 2026 года.** BE-05, BE-06 и BE-07 приняты; BE-07 принят в локальной dev-среде. D1–D6 для BE-08 утверждены; BE-08.3.1–08.3.5 реализованы и приняты владельцем после ручной проверки Filament. BE-08.4.1–BE-08.4.7.3 реализованы и автоматически проверены; ручная проверка Filament владельцем остаётся условием приёмки.

## Легенда

- ✅ Завершено по репозиторию и проверкам, указанным в примечаниях
- 🔎 Требует проверки в репозитории
- ▶️ Следующий модуль
- ⬜ Запланировано
- 🟡 Реализовано; приёмка не завершена
- 🧪 Принято в локальной dev-среде; production activation не выполнялась

## Этапы

| ID | Модуль | Статус | Примечания |
| --- | --- | --- | --- |
| INFRA | Infrastructure | ✅ | Staging и production изолированы по требованиям; проверить фактическую конфигурацию при деплое |
| FE-01…FE-12 | Frontend | ✅ | Считается завершённым по handoff; моковые данные заменять по мере подключения API |
| BE-01 | Read-only архитектурный аудит | ✅ | Завершён по handoff |
| BE-02 | SEO статических страниц | ✅ | Проверен по репозиторию; Filament → API → Next.js SSR подтверждены. SEO индексной страницы `/news` включено в BE-02 и проверено вручную по HTML. Laravel tests и frontend typecheck пройдены. |
| BE-03 | Категории | ✅ | Проверены Filament CRUD, API и Next.js; изменения названия, title, description, H1 и других текстовых полей отображаются на публичных страницах, включая Solutions. Категории для Solutions читаются из API; tile description редактируется в Filament. Laravel tests и frontend typecheck пройдены. |
| BE-04 | PDF Catalogues Management | ✅ | Filament/API и Next.js интеграция завершены. Каталог DTO не раскрывает приватные PDF данные; category slug фильтр поддерживает many-to-many. Frontend typecheck, catalog 12/12, product 9/9, enquiry 8/8, forms 4/4, SSR smoke и production build (`NODE_ENV=production`) пройдены. `git diff --check` чистый. Ограничения: lint script отсутствует; интерактивный modal не проходил browser smoke. Заявки и gated download — BE-07. |
| BE-05 | News | ✅ | Завершён. Вертикальная реализация: MariaDB, Laravel, Filament RichEditor/live preview, API и Next.js без mock fallback. Полный backend suite 50/50 (371 assertions), News-focused 11/11 (188), Pint, frontend typecheck/regression tests и production build пройдены. Importer dry-run: 7 записей, 3 категории; семь изображений совпадают с fixture по SHA-256. API/SSR smoke: Page SEO API и canonical архива, SSR 7/7 с canonical/NewsArticle JSON-LD, неизвестный slug 404; новая тестовая публикация отобразилась после build без пересборки. Владелец проекта вручную подтвердил импорт, создание статьи и работу Filament. Production migration/import/deploy не выполнялись. |
| BE-06 | Контакты и шоурумы | ✅ | Завершён и принят владельцем. Filament CRUD и singleton settings → `GET /api/v1/contacts` → Next.js SSR; единый источник для Footer, Home, Contacts, About, Catalogues, Product и Cart/Enquiry, без runtime mock fallback. Backend suite 57/57 (427 assertions), frontend typecheck, contacts 3/3, catalog 12/12, product 9/9, enquiry 8/8, forms 4/4, production build и Pint для затронутых PHP-файлов прошли. Проверены точные три шоурума и primary DEVA, пустой ответ, 503, переключение primary и снятие публикации. Контактное изменение в тестовой БД появилось в SSR после build без пересборки. Общий Pint выявил 11 style issues вне BE-06; browser-level Filament smoke не выполнялся. Владелец подтвердил полное закрытие. Тестовая БД очищена; production operations не выполнялись. |
| BE-07 | Заявки и Telegram | 🧪 | Принят в локальной dev-среде для Contact, Catalogue Download, Filament Enquiries и Telegram; ручная приёмка BE-07.6d — 31/31 PASS. Contact/Catalogue активны только в dev; Product Enquiry и Cart Enquiry отключены до BE-08. `NEXT_PUBLIC_ENQUIRIES_ENABLED=true` задан только в dev Compose. Production activation не выполнялась. Открыты Squid на staging, S3 backup, retention cleanup и юридическая экспертиза Privacy Policy. |
| BE-08 | Товары | 🟡 | D1–D6 утверждены 2026-11-02. BE-08.3.1–08.3.5 приняты владельцем; BE-08.4.1–08.4.7.3 автоматически проверены до последующего unit follow-up. У Product Attributes `cm` по умолчанию, единицу можно редактировать для любого типа; migration 000018 выполнена только в `potsandplanters_test`, dev schema Pending. Follow-up ещё не прошёл отдельные backend/frontend checks. Backend suite 184/1290 и профильный набор 23/177 относятся к базовому BE-08.4.7.3. Подробности: `docs/backend/BE-08.4.7.3_IMPLEMENTATION_REPORT.md`. Ручная Filament-приёмка 08.4.5–08.4.7.3 ожидается. Product API отсутствует; Product Enquiry/Cart Enquiry выключены; публичный рендер описания, BE-09/OpenCart и production activation не выполнялись. |
| BE-09 | Финальная интеграция и миграция OpenCart | ⬜ | Импорт, URL mapping, 301, SEO, проверка перед запуском; Solutions после появления товаров |

## Ближайшие действия

- [x] Проверить фактическое состояние BE-02 и BE-03 в текущем репозитории (read-only).
- [x] Реализовать BE-04 вертикально: MariaDB → Laravel → Filament → API → Next.js.
- [x] Подключить many-to-many `catalogue_category`, приватное хранение PDF и публичные обложки.
- [x] Сохранить `/catalogues?category=<slug>`; frontend запрашивает опубликованные каталоги через `serverApiFetch` с `no-store` и не подменяет ошибку mock-данными.
- [x] Удалить runtime mock-зависимости каталожного модуля; fixture оставлен только для тестов форм/каталога.
- [x] Проверить frontend: typecheck; catalog 12/12, product 9/9, enquiry 8/8, forms 4/4; SSR для home, списка, трёх category slug и unknown slug; canonical и отсутствие внутренних/private URL; production build с `NODE_ENV=production`; `git diff --check`.
- [x] Согласовано и выполнено ТЗ BE-05.1–BE-05.4; реализация проверена в рамках BE-05.5.
- [x] Выполнить BE-05.5: News backend/frontend checks, Pint, production build, importer dry-run и read-only API/SSR smoke для архива, семи slug и unknown slug.
- [x] Завершить BE-05 после приёмки владельцем.
- [x] Выполнить BE-06.1–BE-06.4: read-only audit, backend/Filament/API, Next.js интеграция, автоматические проверки и SSR success/empty/503 сценарии.
- [x] Подтвердить изменения данных и primary в SSR без rebuild; исправить частичную Instagram-валидацию.
- [x] Завершить BE-06 после полной приёмки владельцем.
- [x] Завершить BE-07 локальной dev-приёмкой для Contact, Catalogue Download, Filament Enquiries и Telegram (BE-07.6d: 31/31 PASS); production activation не выполнять.
- [ ] Проверить Telegram через Squid на staging, настроить S3 backup, реализовать retention cleanup и провести юридическую экспертизу Privacy Policy.
- [x] Утвердить D1–D6 и принять BE-08.3.1–08.3.5 после ручной проверки Filament; сохранить FIX-01.
- [x] Реализовать BE-08.4.1 Product Core, BE-08.4.2 Product Categories и BE-08.4.3 Product Attribute Values; запускать их backend migrations только в `potsandplanters_test`.
- [x] Реализовать BE-08.4.4 Product Gallery & Media; проверить профильные и полные backend tests, Pint, `git diff --check` и миграционный цикл только в `potsandplanters_test`.
- [x] Реализовать BE-08.4.4.1: Intervention Image 3.11.10 + GD/WebP/EXIF в общем Docker-образе, приватные оригиналы, асинхронные WebP-варианты и admin Retry; профильные тесты 30/219, backend suite 157/1152. Реальные Redis workers проверены с изолированным prefix. Миграция выполнена только в `potsandplanters_test`; ручная приёмка и применение к обычной dev-базе не выполнялись. См. `docs/backend/BE-08.4.4.1_IMPLEMENTATION_REPORT.md`.
- [x] После применения миграции владельцем в local dev исправить preview: каталог после private → public rename теперь получает 0755, WebP — 0644. Готовые варианты первого товара возвращают HTTP 200; regression suite 158/1166, Pint и `git diff --check` прошли. Ручная приёмка продолжается.
- [x] Выполнить BE-08.4.5 Product SEO & URLs: SEO defaults/overrides, глобально уникальный slug, history/resolver, shared Google Search Preview; миграция только в `potsandplanters_test`. Подробности и точные тестовые результаты: `docs/backend/BE-08.4.5_IMPLEMENTATION_REPORT.md`.
- [x] Выполнить BE-08.4.6 Category Filter Configuration: сохранять на категории ссылку на существующий Attribute, presentation и порядок без дублирования характеристик и товарных значений; migration только в `potsandplanters_test`. См. `docs/backend/BE-08.4.6_IMPLEMENTATION_REPORT.md`.
- [x] Выполнить BE-08.4.7.1 Product Description: nullable LONGTEXT, Filament RichEditor и серверная HTML-санитизация; migration/test cycle только в `potsandplanters_test`. См. `docs/backend/BE-08.4.7.1_IMPLEMENTATION_REPORT.md`.
- [x] Выполнить BE-08.4.7.2 Selectable Product Attributes: отдельно сохранять выбранные необязательные Attributes и typed values; миграция и тесты только в `potsandplanters_test`. См. `docs/backend/BE-08.4.7.2_IMPLEMENTATION_REPORT.md`.
- [x] Выполнить BE-08.4.7.3 Card Display Attributes + Live Preview: хранить порядок выбранных Attribute ID, Short label и форматировать/предпросматривать карточку в Filament; миграцию/тесты выполнять только в `potsandplanters_test`. См. `docs/backend/BE-08.4.7.3_IMPLEMENTATION_REPORT.md`.
- [ ] Получить ручную проверку Filament владельца по BE-08.4.1–08.4.7.3. Для BE-08.4.5 подтвердить draft, overrides, preview, публикацию, slug/category изменения, OG fallback и конфликты resolver; для BE-08.4.6 — флаг Filterable на Attribute, presentation и порядок, независимые настройки категорий, снятие связи без изменения атрибутов и значений; для BE-08.4.7.1 — создание/редактирование пустого и форматированного Description; для BE-08.4.7.2 — выбранный пустой Attribute, повторное открытие, очистка значения с последующим снятием выбора, required-поля и смена Product Type; для BE-08.4.7.3 — реактивные изменения полного preview, Create image placeholder, Edit ready main image, сохранение порядка и сравнение с публичной карточкой.
- [ ] В публичном этапе связать Product resolver с authoritative API/Next.js и реализовать/проверить точный HTTP 301. Текущая Next.js Product route читает mock data и использует 308; не считать её backend Product integration.
- [ ] До появления авторитетного Product API не включать Product Enquiry и Cart Enquiry; BE-09 migration/import и production activation не выполнять без отдельного разрешения.

## Критерии готовности каждого модуля

- [ ] Данные управляются через Filament.
- [ ] API `/api/v1` работает и использует типизированные DTO.
- [ ] Next.js отображает реальные данные без скрытого fallback на mock.
- [ ] Сохранены существующие маршруты, SEO и пользовательское поведение.
- [ ] Проверены успешные сценарии, ошибки и граничные случаи.
- [ ] Изменения подтверждены по актуальному репозиторию; ручные правки не затёрты.
- [ ] Для production-миграций получено отдельное разрешение.

## Критические зависимости и риски

1. **BE-04 → BE-07:** PDF-каталоги создаются раньше механизма заявок; контролируемое скачивание реализуется только после надёжного сохранения заявки.
2. **BE-05:** нельзя выдумывать новости, даты или изображения; новые slug должны работать без пересборки Next.js.
   Реализация: семизаписный fixture импортёра, sanitizer, Filament resources/live preview, API и Next.js dynamic rendering находятся в репозитории. Автоматизированные проверки, read-only SSR smoke 7/7, новая опубликованная тестовая запись после build и ручная проверка владельца (импорт, новая статья, Filament) подтверждены. Production операции не выполнялись.
3. **BE-07 (принят в dev):** заявки сохраняются в MariaDB вместе с outbox в одной транзакции; dispatcher передаёт отдельные recipient deliveries в Redis/Horizon, retry и idempotency защищают от потери и дубликатов. Catalogue Download использует 15-минутные PDF grants, привязанные к сохранённой заявке; Telegram Settings управляются через Filament. `NEXT_PUBLIC_ENQUIRIES_ENABLED=true` установлен только в dev Compose; production activation не выполнялась. Открытые задачи BE-07: Squid на staging, S3 backup, retention cleanup, юридическая экспертиза Privacy Policy. Product Enquiry и Cart Enquiry отключены до BE-08.
4. **BE-08:** утверждённые решения задают самостоятельный Product без ProductVariant, обязательные Brand/Supplier/Product Type, необязательную Series того же Brand, SKU unique per Brand и AED price `NULL` либо `> 0`. BE-08.4.1 ограничен Product Core и не создаёт публичный API; опубликованный статус не делает запись публично доступной до отдельной реализации публикационного контракта. BE-08.4.2+ и BE-09 ещё не реализованы.
5. **BE-09:** сохранить старые SEO URL, включая `?product_id=...`, с проверенным соответствием и 301 redirects.
6. **Privacy:** актуализированный текст Privacy Policy требует юридической экспертизы; путь `/private-policy` сохраняется. Production activation заявок не выполнялась.
7. **Solutions:** подключить после появления реальных товаров, не забыть в финальной интеграции.

## Правила актуализации

После приёмки каждого этапа обновлять дату, статус, подтверждение тестирования, обнаруженные ограничения и следующую задачу. `PROJECT_CONTEXT.md` изменять при принятии или пересмотре архитектурных решений. **Репозиторий остаётся источником истины о фактически реализованном коде.**
