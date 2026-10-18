# FE-01 — Отчёт о реализации

Дата: 2026-10-17. Объём: только Design System & Shared Layout.

## Источники и порядок выполнения

Работа выполнена последовательно FE-01.1 → FE-01.2 → FE-01.3 → FE-01.4 → FE-01.5. До изменения frontend создан `docs/frontend/FE-01-design-inventory.md`. В нём приведены пути источников, размеры и наблюдения по всем восьми screenshot, отсутствующие assets и предлагаемые изменения.

Основные источники:

- `references/opencart/catalog/view/theme/potsandplanters/assets/css/variables.scss`, `common.scss`, `style.scss`, `style.css`.
- `references/opencart/catalog/view/theme/potsandplanters/template/common/{header,footer,home,cart}.tpl`.
- `references/opencart/catalog/view/theme/potsandplanters/assets/js/main.js` — только изучение поведения.
- `references/screenshots/{main,category,product,cart}_{desktop,mobile}.png` — просмотрены все восемь файлов; уменьшенные копии созданы только в `/tmp`.

Извлечены `#989B86` (olive), `#333333` (текст), `#FFFFFF` (поверхности), `#000000`/`#C3C1C1` (нижний footer), границы `#CCCCCC`/`#E5E5E5`, radius 4/5/10/15px, header width 1420px, переключение desktop/mobile около 1220px. Подробности — в inventory. Скомпилированный CSS отличается от SCSS стандартного header: белый header соответствует screenshot, поэтому выбран он.

Существующий Lato подключался через Google Fonts; локальные файлы/лицензия отсутствуют. Используется явно задокументированный системный fallback Arial/Helvetica/sans-serif, без внешних запросов. Производные оттенки olive, новая spacing scale, тень и focus — решения реализации, а не дополнительные найденные brand tokens. Интерактивные заливки используют более тёмный olive для читаемости белого текста. Оригинальный olive сохранён в палитре на позиции 4.

## Результат по пакетам

| Пакет | Результат |
| --- | --- |
| FE-01.1 | Инвентаризация создана до кода; наблюдения отделены от модернизации |
| FE-01.2 | Один Mantine theme/provider, typography, palette, spacing/radius/shadow/focus, responsive Container; принудительная светлая схема. HMR не удалось проверить |
| FE-01.3 | Header, desktop navigation, mobile Drawer, search и mock Cart (0). Поиск: trim, encodeURIComponent, `/search?q=...`; Enter/submit, перевод фокуса в открытый поиск и Escape с возвратом на trigger |
| FE-01.4 | Footer с явно временными company/contact/showroom данными; постоянные Catalogues/WhatsApp actions, disabled WhatsApp, safe-area/reserved space |
| FE-01.5 | Shell подключён через root layout; минимальные previews `/`, `/catalogues`, `/search`; typecheck/build пройдены, HTML проверен, ограничения браузерной проверки зафиксированы |

Drawer использует Mantine focus trap, returnFocus, scroll lock, overlay, Escape и кнопку закрытия с accessible name. Закрывается при выборе любой ссылки, успешном переходе из поиска и расширении viewport до desktop. Floating actions скрываются при открытом Drawer; z-index 100 против 300. Снизу оболочки оставлено 120px + safe-area. Внешний WhatsApp при будущей настройке получает rel=noopener noreferrer и пояснение об открытии новой вкладки.

Главная заменена только текстовой демонстрацией оболочки, без hero или секций магазина. `/catalogues` — объясняющая временная заглушка, `/search` безопасно показывает переданный текст и явно сообщает об отсутствии результатов. Типизированный config объединяет навигацию, company/contacts/showrooms, WhatsApp destination; cart mock имеет отдельный интерфейс и единственное значение по умолчанию.

## Файлы

Изменены:

- `frontend/src/app/layout.tsx` — существующий provider, theme, shell и footer; минимальные preview metadata.
- `frontend/src/app/page.tsx` — минимальная демонстрация.
- `frontend/src/app/globals.css` — body, skip-link, focus; без сброса внутренних стилей Mantine.

Созданы:

- `frontend/src/theme/index.ts`.
- `frontend/src/types/site.ts`, `frontend/src/config/site.ts`.
- `frontend/src/components/layout/Brand.tsx` — текстовый fallback с пометкой отсутствующего logo.
- `frontend/src/components/layout/SiteHeader.tsx`, `MobileNavigation.tsx`, `SearchEntry.tsx`, `CartEntry.tsx`.
- `frontend/src/components/layout/SiteFooter.tsx`, `FloatingActions.tsx`, `SiteShell.tsx`.
- `frontend/src/components/layout/PreviewPage.tsx`, `Layout.module.css`.
- `frontend/src/app/catalogues/page.tsx`, `frontend/src/app/search/page.tsx`.
- `docs/frontend/FE-01-design-inventory.md`, этот отчёт.

Typecheck/build также обновляют локальные генерируемые `.next/`, `tsconfig.tsbuildinfo` и `next-env.d.ts`; вручную они не редактировались. Dependencies, package scripts, Next config, compose/Docker/nginx, backend, API helpers и references не изменялись. Git metadata недоступны (каталог `.git` в данной среде пуст); `git status --short` не работает, поэтому git diff нельзя подтвердить инструментом.

## Выполненные проверки

| Команда / проверка | Фактический результат |
| --- | --- |
| `node --version`, `npm --version` | v24.14.0 / 11.9.0 |
| `npm --prefix frontend run typecheck` | PASS, exit 0; повторена после финальных изменений |
| `npm run build` из `frontend/`, в PTY | PASS, exit 0; Next 16.4.2 Turbopack, compilation, TypeScript, static generation 5/5. Routes: static `/`, `/catalogues`, dynamic `/search`, служебный `/_not-found`. Финальная сборка после последних изменений также PASS |
| `npm --prefix frontend run lint` | Недоступен: `Missing script: "lint"`. ESLint/config отсутствуют, зависимости и script не добавлялись. Typecheck/build не выдаются за lint |
| `npm run dev -- --port 3100` из `frontend/` (обычный запуск и PTY) | FAIL среды: `listen EPERM: operation not permitted 0.0.0.0:3100`. HMR и HTTP preview не проверены |
| `docker compose ps` | Недоступен: permission denied к `/var/run/docker.sock`; ничего не перезапускалось |
| HTML audit через Python HTMLParser для `.next/server/app/index.html`, `catalogues.html` | PASS: по одному header/main/footer/h1; по два nav; повторяющихся ID нет; target `main-content` существует; активных wa.me URL нет |
| Чтение установленных Mantine Drawer/ModalBase defaults и hook | Подтверждены focus return/trap, scroll lock, Escape, overlay. Это проверка интеграции по коду, не browser interaction test |
| `git status --short` | Недоступен: `not a git repository`; `.git` пуст |

Во время работы первая сборка выявила передачу Next Link как function prop серверной страницы в Mantine Anchor; исправлено на обычный Link. Две последующие сборки без PTY завершались `Could not parse output from TypeScript's --showConfig`. Диагностика показала пустой захваченный stdout дочернего Node-процесса в этом режиме, при этом прямой `tsc --showConfig` выдавал валидный JSON (2360 байт). Запуск того же штатного build в PTY устранил проблему без правок infrastructure/config/dependencies; он завершился успешно дважды. Несколько ранних вызовов npm из корня дали ENOENT (в корне нет package.json); далее использованы frontend cwd / `--prefix frontend`.

Новые тестовые файлы и тестовые зависимости не добавлялись. Полноценный browser runtime тест, live navigation и кодирование запроса проверены только по реализации, не выполненным E2E.

## Acceptance checklist

`PASS` ниже означает подтверждение кодом/доступными проверками, а не визуальную или браузерную верификацию.

| № | Статус | Основание / ограничение |
| --- | --- | --- |
| 1 | NOT VERIFIED | Compose/Next dev/Turbopack HMR конфигурации сохранены; Docker/socket и dev port недоступны в среде |
| 2 | PASS | Laravel/Filament/Redis/Horizon не редактировались |
| 3 | PASS | Inventory с реальными путями, всеми screenshots, фактами и предложениями |
| 4 | NOT VERIFIED / missing input | Оригинальных logo файлов нет. Текстовое название с видимой пометкой fallback; компания/контакты не выдуманы |
| 5 | PARTIAL | Shell общий для всех трёх routes, responsive styles реализованы; визуальная согласованность не проверена |
| 6 | PARTIAL | Mantine Drawer + close handlers реализованы; фактические клавиатура/scroll/focus в браузере не проверены |
| 7 | PASS (code) | Контролируемая строка, encodeURIComponent, push `/search?q=`; result backend отсутствует; браузерный переход не проверен |
| 8 | PASS | Единый cartPreview, 0, disabled visual control без cart logic |
| 9 | PASS (code) | Catalogues → `/catalogues`; WhatsApp null/disabled, видимое пояснение |
| 10 | NOT VERIFIED visually | Reserved bottom space/safe-area/menu hiding реализованы; 375/768/1280px требуют просмотра |
| 11 | NOT VERIFIED | Responsive widths, min-width:0 и wrapping предусмотрены; overflow браузером не измерен |
| 12 | PASS (code + static HTML) | Semantic landmarks, один h1, skip link/target, focus-visible, labels; browser accessibility audit не выполнен |
| 13 | PARTIAL | Typecheck и production build PASS; lint отсутствует |
| 14 | PASS | Нет legacy runtime, backend features или infrastructure changes |
| 15 | PASS | Этот отчёт: решения, файлы, команды, ограничения и preview |

## Визуальные проверки и preview

Исходные screenshots просмотрены. Новая оболочка **не проверена визуально** на 375×812, 768×1024, 1280×800: браузерный preview недоступен из-за запрета bind dev-сервера. Наличие Firefox в системе не заменяет проверку работающего приложения. Не подтверждены отсутствие overflow/перекрытий, focus return, scroll unlock, Escape, overlays и реальный поиск после hydration.

В обычном окружении: `cd frontend && npm run dev`, открыть `/`, `/catalogues`, `/search?q=olive%20%26%20stone`. Для локального отдельного порта — `npm run dev -- --port 3100`. В штатном Docker dev окружении использовать существующие команды README; инфраструктуру для FE-01 менять не нужно.

При ручной приёмке проверить 375/768/1280px: menu open/close, Tab/Shift+Tab/Escape/overlay, возврат фокуса, блокировку и восстановление прокрутки, поиск с `&`, Unicode и пробелами, skip-link, контакты по якорю, отсутствие horizontal overflow и доступность footer над нижними actions. Отдельно проверить resize открытого menu на desktop.

## Нерешённые вопросы

1. Нужны оригинальные `header_logo_w.svg`, `footer_logo_black.svg` и другие брендовые SVG из inventory. До получения доступен только текстовый fallback; монограмма не перерисовывалась.
2. Нужны локальные Lato с лицензией либо подтверждение стратегии подключения. Сейчас нет новых font assets/запросов.
3. В legacy найден `wa.me/971585802562`, отличающийся от телефонов footer. Требуется подтвердить WhatsApp для нового сайта; единственная точка включения — `siteConfig.whatsappUrl`.
4. В stage 3 нужны подтверждённые company/contact/showroom данные; текущие placeholders явно подписаны. Исторические адреса, телефоны и email не активированы.
5. До разработки реальных направлений Outdoor/Indoor/Artificial ведут на `/catalogues`; это описано прямо на preview странице. News/Landscape/legal pages и ссылки к отсутствующим страницам не добавлялись.
6. Остаётся ручная responsive/accessibility/HMR приёмка в окружении с доступным dev-сервером. Lint нужно согласовать отдельной задачей, если он необходим как обязательный gate.

Главная магазина, товарный каталог, страницы товара/корзины, search backend, API-вызовы, Laravel/Filament и SEO-функции не реализовывались. Существующая инфраструктура не изменялась.

## Дополнение — Tabler и иконка корзины

По последующему запросу пользователя проверена установленная им зависимость: `npm ls @tabler/icons-react @tabler/icons --depth=1` подтверждает обе версии 3.49.0 без ошибок. Она присутствует в frontend package.json/package-lock.json; прежнее замечание об отсутствии изменений dependencies относится к первоначальной реализации FE-01.

В `CartEntry.tsx` текстовая кнопка заменена Tabler `IconShoppingCart` (28px, stroke 1.5) с маленьким круглым счётчиком справа снизу. `Layout.module.css` задаёт область 44×44px и контрастный badge 20×20px; устаревший mobile padding текстовой кнопки удалён. Значение берётся из прежнего `cartPreview.itemCount`, сейчас 0; свыше 99 показывается 99+, полное число сохраняется в accessible name. Реальная cart logic не добавлена, entry остаётся disabled preview.

После изменения `npm run typecheck` и `npm run build` (frontend cwd, build в PTY) завершились с exit 0. Браузерная визуальная проверка в этой сессии не выполнялась.

### Уточнение оформления header

По запросу пользователя корзина и поиск приведены к одному оформлению: обе иконки Tabler размером 22px со stroke 1.25, одинаковые круглые области 44×44px с тонкой границей. Badge корзины уменьшен с 20 до 16px и вынесен на нижний правый край окружности; mock count и accessible name сохранены. Заменён ручной SVG поиска на IconSearch. После изменения локальные typecheck и production build в PTY — PASS; визуальная проверка в браузере не выполнялась.

### Уточнение floating actions — кнопки справа

По новому указанию пользователя прежняя полоса на всю ширину заменена двумя отдельными круглыми кнопками справа снизу, расположенными вертикально: Tabler IconBrandWhatsapp и IconBook2. Цвета из olive palette, тонкие линии и лёгкая тень; видимые подписи WhatsApp/Catalogues. Размеры 56px desktop / 48px mobile, отступы 24px / 16px плюс safe-area. Фон, граница полноширинной панели и её резерв под shell удалены; footer сохранил обычные отступы. Это изменение заменяет первоначальное решение о нижней полосе и reserved bottom space в inventory/отчёте.

WhatsApp по-прежнему disabled с видимой пометкой `Preview: number pending`; Catalogues ведёт на `/catalogues`. Accessible names и скрытие при открытии меню сохранены. Изменены FloatingActions.tsx, Layout.module.css, SiteShell.tsx и theme/index.ts (удалён устаревший floatingReserve). Локальные typecheck и production build (PTY) — PASS. Ранний вызов npm из корня дал ENOENT; проверка повторена с frontend prefix/cwd успешно. Визуальная проверка перекрытий на 375/768/1280px остаётся невыполненной; при добавлении будущих форм нужно повторно проверить размещение floating controls рядом с ними.

### Цвет floating actions

По уточнению пользователя светлый вариант (`olive.1` фон, `olive.7` иконки) стал основным для обеих кнопок. Hover активных кнопок — `olive.2`. Подпись `Preview: number pending` и связанный aria-describedby удалены по явному указанию пользователя; WhatsApp остаётся disabled с accessible name `WhatsApp unavailable`. Это заменяет предыдущее требование видимой пометки для этого элемента. Typecheck после изменения — PASS; браузерная проверка не выполнялась.

### Предоставленные SVG-логотипы

По запросу пользователя исходные `header-logo-new.svg` и `footer_logo_black.svg` из `/home/andrey/Загрузки/PotsAndPlanters/images/` скопированы без изменений в `frontend/public/images/`. Brand.tsx использует Next Image с собственными размерами SVG и вариантами header/footer; SiteFooter выбирает footer-вариант. Текстовая заглушка Logo pending удалена. Header отображает SVG высотой 64px (48px на mobile), footer — шириной 173px, пропорции сохранены. Typecheck — PASS. Браузерная визуальная проверка не выполнялась. Ранее отмеченное отсутствие этих логотипов устранено; другие пробелы инвентаризации сохраняются.

### Локальный Lato как основной шрифт

Lato Regular/SemiBold/Bold (400/600/700) скачаны с `raw.githubusercontent.com/google/fonts/main/ofl/lato/` вместе с OFL.txt. FontTools преобразовал оригинальные TTF в WOFF2 без subsetting: 211944 / 213324 / 210852 bytes, по 3023 glyphs; веса проверены через OS/2 table. Добавлены `frontend/src/app/fonts/lato/{Lato-Regular,Lato-SemiBold,Lato-Bold}.woff2`, README с provenance и `frontend/public/licenses/Lato-OFL.txt` (SIL OFL 1.1).

В root layout используется next/font/local (`display: swap`, CSS variable `--font-lato` на html). Mantine body и headings используют эту переменную, fallback Arial/Helvetica/sans-serif. Файлы размещены под src, поэтому доступны существующему Docker dev bind mount; runtime dependencies и инфраструктура не менялись.

Проверки: typecheck PASS; production Turbopack build (PTY) PASS. В сгенерированном CSS проверены три src font-face normal 400/600/700 с same-origin `/_next/static/media/*.woff2`, font-display swap; HTML содержит локальные font preloads. Google Fonts/CDN URL в HTML и Lato font-face отсутствуют. Визуальная проверка в браузере не выполнялась. Прежние замечания о недоступности локальных Lato и лицензии устранены.
