# FE-01.1 — Инвентаризация дизайна

Дата: 2026-10-17. Документ составлен до изменения frontend.

## Проверенные источники

- `references/opencart/catalog/view/theme/potsandplanters/assets/css/variables.scss`: `$fontColor: #333`, `$primaryColor: #989B86`.
- В том же каталоге: `style.scss`, `style.css`, `common.scss`, `common.css`, `variables.css`. Просмотрены общие стили, header/footer, адаптивные правила и floating widget.
- `references/opencart/catalog/view/theme/potsandplanters/template/common/header.tpl`, `footer.tpl`, `cart.tpl`, `home.tpl`; шаблоны product/category/search и checkout/cart присутствуют, их страницы не переносятся.
- `references/opencart/catalog/view/theme/potsandplanters/assets/js/main.js`: jQuery-переключение меню/корзины, закрытие по внешнему клику. Скрипты не переносятся.
- Рекурсивно перечислены 496 файлов `references/opencart/`: PHP templates, CSS/SCSS, JS, vendor icon fonts, несколько обложек и незавершённый `.pdf.filepart`.
- Frontend: `frontend/src/app/{layout.tsx,page.tsx,globals.css}`, `frontend/src/lib/api*.ts`, `frontend/package.json`, `tsconfig.json`, `next.config.ts`. App Router, один MantineProvider, Next 16.4.2, Mantine 9.4.2, React 19.3.0. Есть dev/build/start/typecheck; lint и тестовый runner отсутствуют. API helpers не используются для FE-01.

## Скриншоты — наблюдения

Просмотрены все изображения целиком (уменьшенные копии для навигации по длинным страницам). Размеры ниже — пиксели файла, не доказанные CSS viewport/DPR:

| Файл | Размер | Общая оболочка |
| --- | --- | --- |
| `main_desktop.png` | 2880×14554 | Светлая навигация поверх фотографии; белый footer, чёрная нижняя полоса |
| `main_mobile.png` | 760×16384 | Корзина/search/menu поверх фотографии; одноколоночный footer |
| `category_desktop.png` | 2880×7922 | Белый header, тёмная монограмма слева, горизонтальное меню |
| `category_mobile.png` | 760×12252 | Монограмма, корзина, search, burger; footer logo над контактами |
| `product_desktop.png` | 2880×9772 | Тот же светлый header/footer, большие свободные поля |
| `product_mobile.png` | 1622×11664 | Контент занимает только левую часть изображения; на странице виден PHP notice. Не использовать ширину файла как mobile breakpoint |
| `cart_desktop.png` | 2880×4710 | Белый header с нижней границей, оливковые кнопки, свободное пространство перед footer |
| `cart_mobile.png` | 760×7130 | Белый компактный header, вертикальный footer; плавающий чат рядом с формой |

Скриншоты показывают аккуратные тонкие иконки, uppercase navigation, белые поверхности, мягко округлённые оливковые кнопки. На desktop footer: контакты слева, логотип справа; ниже ещё два showroom-блока и legal links. На mobile: logo сверху, блоки последовательно, legal links по центру. На всех вариантах есть плавающий чат; каталог как отдельная постоянная кнопка — требование нового ТЗ, а не установленный факт скриншотов.

## Цвета и геометрия из CSS

| Токен | Подтверждённое значение / источник |
| --- | --- |
| Основной accent | `#989B86` — variables.scss |
| Текст | `#333333` — variables.scss |
| Поверхности | `#FFFFFF`; нижняя footer полоса `#000000` — style.scss |
| Границы | `#CCCCCC`, `#E5E5E5` — style.scss |
| Вторичный текст | `#666666`, `#808080`; нижний footer `#C3C1C1` — style.scss |
| Radius | 4px select, 5px transparent button, 10px outlined green, 15px green — common.scss |
| Buttons | green 300×50px, transparent 320×60px, outlined 200×40px — common.scss; фиксированные размеры не подходят как универсальный responsive API |
| Header | width 1420px, margin-top 40px, nav 20px, промежутки 29px — style.scss |
| Footer | width 1600px, margin-top 180px, headings 20px, contact gaps 10/30px, middle margin 100px, bottom margin 90px/height 115px — style.scss |
| Breakpoints | В style.scss: 1700, 1640, 1500, 1400, 1300, 1220, 870, 800px; при 1220px скрываются desktop nav/logo и появляется burger |

Скомпилированный `style.css:674–707` отличается от SCSS: стандартный header белый, без фонового изображения, с `#262626` текстом/границей и olive hover; это соответствует screenshot. При расхождении приоритет отдан screenshot и фактическому CSS.

Тени и focus нельзя считать согласованной legacy системой. В старом reset есть `outline: none !important`; это не переносится.

## Шрифты, логотипы и доступность assets

`style.scss` импортирует Google Fonts Lato 300/400/700/900; семейство body — Lato, sans-serif. Локальных Lato, лицензии на шрифт и license-файлов темы в переданных материалах не найдено. Наличие Google import не является локальным файлом или лицензией. Vendor FontAwesome/Glyphicons не нужны оболочке и не копируются.

Шаблоны ссылаются на отсутствующие `assets/img/header_logo_w.svg`, `footer_logo_black.svg`, `logo-main-w.svg`, `banners/pp-logo.svg`, `favicon.svg`, `shipping_bag.svg`, `menu.svg`, `menu_cross.svg`, `footer-icons/1.svg`–`4.svg`. Монограмма и полное название видны на скриншотах, но оригинальные файлы недоступны. В `frontend/public/` готового brand logo также нет. Не перерисовывать монограмму и не выдавать новый рисунок за оригинальный логотип.

## Навигация и данные

Legacy menu: Main, Outdoor Pots, Indoor Pots, Artificial Plants (& Trees в mobile), Catalogs (`/pdf-catalogs`), News, Landscape, Contacts. Search — кнопка, раскрываемая строка и Enter/submit/close. Mobile menu — белая панель с logo и вертикальными ссылками; screenshot открытого меню отсутствует. Legacy JS не обеспечивает современное управление фокусом.

Footer содержит DEVA HARMONY TRADING, Real Touch Flowers, Design House Projects, адреса, телефоны, email, соцсети, legal links. Это исторические данные, не подтверждённые контакты нового сайта. `footer.tpl:215` содержит `https://wa.me/971585802562`, причём номер отличается от контактных телефонов footer. Требуется подтверждение для нового сайта; номер не активировать автоматически.

## Предлагаемая модернизация FE-01 (не legacy факты)

- Один светлый header для общей оболочки; hero-вариант остаётся вне FE-01. Текстовое название Pots & Planters вместо отсутствующего оригинального logo. Системный Arial/sans-serif до получения локального Lato с лицензией; без внешних font requests.
- Mantine theme: исходный olive как базовый accent, тёмный текст на olive для контраста; дополнительные оттенки палитры — производные, не исторические brand colors. Focus outline, spacing scale, лёгкая тень и согласованный radius — новые технические решения.
- Fluid container max 1420px, padding 16/32px; desktop menu от 1220px. Меньшие responsive пороги — Mantine defaults; это не измерения скриншотов.
- Mantine Drawer: focus trap/return, Escape, overlay, scroll lock. Меню закрывается при выборе ссылки и смене desktop viewport.
- Сохранить названия legacy направлений, но пока вести product-направления к явно временной `/catalogues`, Contacts — в footer, Main — `/`. News/Landscape/legal страницы не создавать и не рекламировать несуществующими ссылками.
- Контакты, company и showroom — явные текстовые placeholders из typed mock config. WhatsApp config `null`, видимое disabled состояние и пояснение; Catalogues ведёт на `/catalogues`.
- Floating actions в зарезервированной нижней полосе с safe-area, ниже z-index Drawer; резерв места в оболочке, при открытом меню скрыты. Не повторять перекрытие форм старым чатом.
- Минимальные `/`, `/catalogues`, `/search` демонстрируют оболочку, не реализуют главную, каталог или результаты поиска. Корзина — disabled visual entry с mock count 0.

## Нерешённые вопросы

Нужны исходные SVG logo, локальный Lato и лицензия; актуальные company/contact/showroom данные и подтверждение WhatsApp destination; окончательные маршруты product/nav/legal для следующих этапов. Эти пробелы не блокируют оболочку с явно обозначенными fallback/placeholder состояниями. Docker daemon в текущей среде недоступен (permission denied); инфраструктура не меняется.

## Дополнение — предоставленные логотипы

После первоначальной инвентаризации пользователь передал `/home/andrey/Загрузки/PotsAndPlanters/images/header-logo-new.svg` (72×86, viewBox 0 0 72 86) и `footer_logo_black.svg` (173×86, viewBox 0 0 173 86). Оба SVG состоят из векторных элементов, без внешних ссылок. Они скопированы без изменений в `frontend/public/images/`; отсутствие оригинальных логотипов больше не является открытым вопросом для header/footer. Высота header 64px / mobile 48px и ширина footer 173px — выбранные размеры отображения, пропорции исходников сохранены.

## Дополнение — локальный Lato

По запросу пользователя Lato скачан из `https://github.com/google/fonts/tree/main/ofl/lato` вместе с SIL OFL 1.1. В проект добавлены WOFF2 normal 400/600/700 (текущие используемые веса), без subsetting, по 3023 glyphs. Источники и расположение лицензии — `frontend/src/app/fonts/lato/README.md`. Прежний вопрос доступности/лицензии Lato закрыт; шрифт установлен основным через next/font/local и Mantine, системные семейства оставлены fallback.
