# FE-02 — Отчёт реализации главной

Дата: 2026-10-17. Область: только `/` и её frontend-компоненты/локальные ассеты.

## Выполнение FE-02.1–FE-02.5

1. **FE-02.1:** изучены main desktop/mobile screenshots, legacy home/featured templates, доступные изображения, текущие Layout/Mantine и изменения после FE-01. До реализации создан `FE-02-home-inventory.md`.
2. **FE-02.2:** добавлены типы, единый `home.mock.ts`, `ProductPreview`, карточка товара, секции, обработка отсутствующих/не загрузившихся изображений и общий helper контакта поверх `siteConfig`.
3. **FE-02.3:** собрана главная из восьми секций. Fancybox подключён к трём обложкам Inspiration. Swiper не используется: небольшие статические сетки доступны без JS и не требуют слайдера. Установленные зависимости не менялись.
4. **FE-02.4:** добавлены responsive grids, fluid typography, aspect-ratio, точные `sizes` с учётом container/gaps/полосы floating actions, focus из FE-01, reduced-motion настройки Fancybox. Изображения не обрезаются (`contain`), встроенные надписи обложек сохранены. Для placeholders подпись — HTML 14px, чтобы она не уменьшалась вместе с SVG.
5. **FE-02.5:** typecheck, production build и проверки сгенерированного HTML/ассетов прошли. Браузерная responsive/accessibility/HMR приёмка ограничена средой, подробности ниже.

## Готовые секции

| Секция | Поведение |
| --- | --- |
| Hero | Один H1, residential/commercial UAE, реальная обложка Royal Planters, Explore Collection → `/shop`, Discuss Your Project → `/#contact-cta` |
| Categories | Три подтверждённых направления из единого mock-слоя; явные placeholders |
| Project Solutions | Hotels & restaurants, offices & commercial spaces, homes & villas; без заявлений о выполненных проектах |
| Featured Products | Четыре модели первого ряда legacy screenshot, имя/артикул, Price on enquiry, будущие product URLs |
| PDF Catalogues | Шесть исходных обложек, переходы на существующую `/catalogues`; скачивание PDF и форма не реализованы |
| Inspiration | Три обложки с Fancybox; прямо обозначены как catalogue artwork, не портфолио проектов |
| About | Нейтральный текст, контакты и будущая `/about` |
| Final CTA | Контрастная olive-секция; общий helper WhatsApp/контакта |

Partners/Clients скрыт: логотипы отсутствуют. Отзывы не перенесены: актуальность и разрешение публикации не подтверждены. Новые клиенты, цены, проекты, годы основания и показатели не придуманы.

## Ассеты и placeholders

В `frontend/public/images/home/` находятся шесть JPEG 700×394, скопированных **без изменения содержимого** из `references/opencart/catalog/view/theme/potsandplanters/assets/img/catalog-cover/`:

| Новый файл | Исходник |
| --- | --- |
| `royal-planters.jpg` | `fleur-ami.JPG` |
| `lifelike-plants.jpg` | `artificial/artificial.JPG` |
| `lifelike-trees.jpg` | `artificial/artificials-tree.JPG` |
| `lifelike-cacti.jpg` | `artificial/cacti.JPG` |
| `project-catalogue.jpg` | `artificial/project-catalogs.JPG` |
| `trees-and-cacti.jpg` | `artificial/trees_and_cacti.JPG` |

Hero использует Royal Planters как подписанную обложку. Это временная композиция из доступных материалов: оригинальной интерьерной фотографии для полноэкранного hero нет. Обложки не выдаются за отдельные оригинальные фотографии или реализованные проекты.

`image-pending.svg` — явно технический локальный placeholder из существующей olive palette. Он используется для трёх категорий и четырёх товаров; `HomeImage` также переключается на него при ошибке загрузки, сохраняя размер контейнера. Новые реалистичные изображения и логотипы не генерировались, screenshot-фрагменты в продукт не включались. Внешних runtime image/font запросов нет.

## Сохранение пользовательских изменений FE-01

Header/Footer/SiteShell/FloatingActions/Brand, Mantine theme, site config и global CSS **не редактировались**. Сохранились SVG-логотипы, локальный Lato, оформление корзины/поиска, мобильное меню и плавающие кнопки справа.

`siteConfig.whatsappUrl` остаётся `null`. Исторический номер не активирован. Hero ведёт к final CTA, а Project Solutions/final CTA через `getProjectContact` — к существующему `/#contacts`. Это пока placeholder-контакты FE-01, полноценный канал связи требует подтверждённых данных. После заполнения общего config helper создаст WhatsApp URL с контекстом проекта и безопасным открытием в новой вкладке. Плавающая WhatsApp-кнопка сохраняет своё текущее disabled-состояние, Catalogues — `/catalogues`.

Главная оставляет свободную правую полосу 96px mobile / 120px desktop внутри своих контейнеров, чтобы её ссылки и CTA не проходили под floating actions. Это локальная мера главной, поведение оболочки не меняется. На мобильном она уменьшает доступную ширину контента; композицию нужно принять визуально.

## Архитектура и изменённые файлы

- `frontend/src/app/page.tsx` — заменён FE-01 preview на главную, добавлены title/description.
- `frontend/src/types/home.ts` — контракты изображений, категорий, продуктов, каталогов, решений.
- `frontend/src/data/home.mock.ts` — тексты, категории, четыре товара, решения, каталоги, выборка Inspiration. Данные можно заменить API adapter на следующем этапе.
- `frontend/src/lib/project-contact.ts` — общий destination/fallback и контекст WhatsApp.
- `frontend/src/components/home/HomeSections.tsx` — серверные секции.
- `frontend/src/components/home/ProductCard.tsx` — серверная карточка по `ProductPreview`; форматирование AED предусмотрено, в мок-данных стоят демонстрационные суммы 100–2 000 AED.
- `frontend/src/components/home/ProjectContactLink.tsx` — серверная общая ссылка для B2B CTA.
- `frontend/src/components/home/HomeImage.tsx` — небольшой client island для `next/image` и `onError` fallback.
- `frontend/src/components/home/InspirationGallery.tsx` — клиентская интеграция Fancybox, bind/unbind в пределах галереи, возврат фокуса и настройки reduced motion. Без JS обычные ссылки открывают локальные JPEG.
- `frontend/src/components/home/image-sizes.ts` — значения responsive sizes, соответствующие сеткам.
- `frontend/src/components/home/Home.module.css` — локальные стили главной.
- `frontend/public/images/home/` — шесть JPEG и один SVG placeholder.
- `docs/frontend/FE-02-home-inventory.md`, `docs/frontend/FE-02-implementation-report.md` — инвентаризация и отчёт.

Package manifests/lockfile, маршруты других страниц, backend, Docker и инфраструктура не редактировались. JSON-LD, sitemap, robots и canonical-логика не добавлены.

## Проверки: команды и результаты

| Проверка | Результат |
| --- | --- |
| `npm run typecheck` в `frontend/` (финально также `npm run typecheck --prefix frontend`) | PASS, exit 0 |
| `npm run build` в `frontend/`, PTY | PASS, exit 0; `/` статически сгенерирована; список маршрутов остался `/`, `/_not-found`, `/catalogues`, `/search` |
| `python /tmp/fe02-checks.py` из корня (временный проверочный скрипт с HTMLParser/hashlib) | PASS: один H1/main, description, уникальные IDs, alt, фиксированные размеры/fill, sizes у raster srcset, ровно один image preload для hero, отсутствие JSON-LD |
| Тот же скрипт: источники изображений и навигация | PASS: семь placeholders в SSR, все источники локально существуют; все якоря существуют; контактный fallback активен, неподтверждённый WhatsApp не включён |
| Тот же скрипт: SHA-256 шести JPEG | PASS: копии побайтово равны исходникам |
| Python-проверка изменённых/новых frontend source files | PASS: нет trailing whitespace |
| Lint | Не запускался: script `lint` и конфигурация отсутствуют; новый инструментарий не устанавливался |
| `git status --short` | Недоступен: смонтированная `.git` не является доступным git repository. Сверку git diff выполнить нельзя |
| `docker compose ps` | Недоступен: permission denied к Docker socket. Инфраструктура не изменялась |
| Локальный Next server: `node node_modules/next/dist/bin/next start --hostname 127.0.0.1 --port 3102` | Не запустился: `listen EPERM`, exit 1 |
| Headless Firefox с временным profile и remote debugging | Не запустился: exit 139; повторная попытка также не дала браузерную сессию |

Первоначальная проверка HTML требовала `sizes` у всех fill-изображений и остановилась: Next автоматически убирает `sizes` у unoptimized SVG. Проверка уточнена до raster `srcset`; все такие изображения имеют `sizes`. Это не ошибка сборки/изображений.

## Responsive и ручная приёмка

CSS подготовлен для контрольных ширин, но **фактические browser measurements и скриншоты не получены**. Отсутствие горизонтального скролла, CLS и перекрытий не объявляется измеренным PASS.

| Ширина | Заданные CSS-компоновки |
| --- | --- |
| 375 / 390 | Hero и все grids в одну колонку; содержимое с резервом под actions; CTA переносятся |
| 768 | Hero в одну колонку; категории/решения/Inspiration по 3, товары/каталоги по 2; About по 2 |
| 1024 / 1440 | Hero по 2; категории/решения/каталоги/Inspiration по 3; товары по 4 |

Для длинных имён предусмотрены `minmax(0, 1fr)`, `min-width: 0`, `overflow-wrap: anywhere`, естественная высота заголовка и нижнее выравнивание цены/ссылки. Реальные mock-названия уже длинные; экстремальные названия в браузере не проверены. Null-image состояние подтверждено в SSR; переключение при сетевой ошибке требует browser-проверки.

Ручная приёмка в рабочем dev-окружении:

- На 375/390/768/1024/1440: горизонтальный скролл, чтение текстов/подписей, качество 700px обложек, баланс hero, ширина контента с резервом справа.
- На каждой ширине: CTA и карточки при прокрутке рядом с плавающими кнопками; переходы к final CTA и contacts.
- Tab/Shift+Tab, видимый focus, skip-link, mobile drawer/search и возврат фокуса; меню должно скрывать floating actions как прежде.
- Fancybox: Enter на обложке, Tab, стрелки, Escape, возврат фокуса; reduced motion, закрытие и навигация назад.
- Отключённый JS/заблокированные изображения, broken-image fallback и очень длинное название товара.
- HMR после изменения текста mock-слоя; серверные/браузерные console errors. Bind mounts и HMR config не менялись, но live HMR не проверен.

## Будущие маршруты и открытые вопросы

Эти страницы **не создавались**, ссылки допускаются ТЗ и до следующих этапов ведут на 404:

- `/shop` — Explore Collection.
- `/shop/outdoor-pots`, `/shop/indoor-pots`, `/shop/artificial-plants` — страницы трёх направлений каталога; прежние URL перенаправляют на новые.
- `/product/coral-planter-25526`.
- `/product/magnifico-floor-vase-24182`.
- `/product/pandora-floor-vase-17310`.
- `/product/coral-floor-vase-25530`.
- `/about`.

Product slugs — предложенный будущий контракт `/product/[slug]`; старые product URLs неизвестны. `/catalogues` уже существует как preview FE-01; готовые PDF/скачивание/лид-форма остаются FE-07. `/#contacts` и `/#contact-cta` существуют.

Нужны оригинальные hero/category/product/interior изображения, актуальный ассортимент/характеристики/цены и URL, подтверждённые контакты/WhatsApp, подтверждённые partner logos и разрешение их публикации. До этого визуальная приёмка проводится с явно обозначенными placeholders. Переход к FE-03, каталогу и backend не выполнялся.
