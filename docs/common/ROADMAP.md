# Pots & Planters 2.0 — Roadmap

**Срез на 22 октября 2026 года.** BE-05 и BE-06 завершены и приняты владельцем; следующий модуль — BE-07.

## Легенда

- ✅ Завершено по репозиторию и проверкам, указанным в примечаниях
- 🔎 Требует проверки в репозитории
- ▶️ Следующий модуль
- ⬜ Запланировано
- 🟡 Реализовано; приёмка не завершена

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
| BE-07 | Заявки и Telegram | ▶️ | Следующий модуль: надёжное сохранение, Horizon, четыре типа заявок, gated PDF download |
| BE-08 | Товары | ⬜ | Исследовать OpenCart SQL до проектирования вариантов и SKU |
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
- [x] Завершить BE-06 после полной приёмки владельцем; BE-07 — следующий модуль.

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
3. **BE-07:** Telegram не должен быть единственной точкой сохранения заявки; MariaDB первична, отправка через Horizon с retry и idempotency.
4. **BE-08:** структура товаров, SKU и вариантов зависит от исследования реального OpenCart SQL export; неизвестные цены показывать как `on_enquiry`.
5. **BE-09:** сохранить старые SEO URL, включая `?product_id=...`, с проверенным соответствием и 301 redirects.
6. **Privacy:** перед реальными заявками обновить Privacy Policy; путь `/private-policy` сохраняется.
7. **Solutions:** подключить после появления реальных товаров, не забыть в финальной интеграции.

## Правила актуализации

После приёмки каждого этапа обновлять дату, статус, подтверждение тестирования, обнаруженные ограничения и следующую задачу. `PROJECT_CONTEXT.md` изменять при принятии или пересмотре архитектурных решений. **Репозиторий остаётся источником истины о фактически реализованном коде.**
