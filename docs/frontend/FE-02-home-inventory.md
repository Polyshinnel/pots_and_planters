# FE-02.1 — Инвентаризация главной

Дата: 2026-10-17. Выполнена до реализации FE-02.2–FE-02.5.

## Изученные источники

- `references/screenshots/main_desktop.png` (2880×14554) и `main_mobile.png` (760×16384), включая увеличенный фрагмент Featured Products.
- Legacy `template/common/home.tpl`, `template/extension/module/featured.tpl`, стили темы и перечень файлов `assets/`.
- Текущие `src/app`, `src/components/layout`, `src/theme/index.ts`, `src/config/site.ts`, отчёты FE-01 и package.json.

## Существующие блоки и решение FE-02

| В старой главной | Решение |
| --- | --- |
| Фотографический hero-слайдер Pandora Gold | Один статический hero; текст B2B-first, фотография на доступной обложке каталога. Не переносить неподтверждённые характеристики Pandora Gold |
| Outdoor Pots / Indoor Pots / Artificial Plants | Сохранить ровно эти три направления и старые маршруты `/outdoor-pots`, `/indoor-pots`, `/art-plants`; не создавать страницы |
| Featured Products, восемь товаров | Четыре позиции из первого ряда screenshot; артикулы/названия из screenshot, цены не переносить без актуального подтверждения |
| Отзывы | Не переносить: актуальность и разрешение на публикацию персональных отзывов не подтверждены |
| Our Partners, 15 логотипов | Скрыть: файлы `partners/1.png`–`15.png` отсутствуют; упоминание в шаблоне не заменяет логотип и проверку отношений |
| Три чередующихся текстовых/фото блока | Компактные Project Solutions и About без годов основания, статуса представителя и других непроверенных обещаний |
| PDF-каталоги | Добавить визуальный переход на существующую preview `/catalogues`, без скачивания/формы |
| Inspiration | Галерея доступных обложек; явно назвать их каталогами, не выполненными проектами |
| Финальный CTA | Общий контактный helper: WhatsApp с контекстом при наличии URL, иначе ссылка на `/#contacts` |

## Фактически доступные изображения

В исходной теме **только шесть JPEG**, все 700×394, с уже нанесёнными заголовками каталогов:

- `assets/img/catalog-cover/fleur-ami.JPG` — Royal Planters, кашпо с цветами.
- `assets/img/catalog-cover/artificial/artificial.JPG` — Lifelike Plants, флористическая композиция.
- `assets/img/catalog-cover/artificial/artificials-tree.JPG` — Lifelike Trees, листва.
- `assets/img/catalog-cover/artificial/cacti.JPG` — Lifelike Cacti, кактусы в интерьере.
- `assets/img/catalog-cover/artificial/project-catalogs.JPG` — Project Catalogue of Lifelike Plants, фон из листьев.
- `assets/img/catalog-cover/artificial/trees_and_cacti.JPG` — Lifelike Trees & Cacti, интерьер с кашпо.

Использовать локальные копии без удаления встроенных надписей. Для hero — Fleur Ami как явно подписанный catalogue visual, без увеличения фотографии на весь desktop viewport. Для Inspiration — три обложки как редакционные материалы, без заявлений о проектах компании. Все шесть доступны для секции каталогов.

Фотографии `categories/*.webp`, `content-img/main/*.jpg`, баннеры hero, product images и `partners/*.png` **отсутствуют**. Скриншоты служат визуальным ориентиром, не источником вырезанных товарных фотографий/логотипов. Для недостающих category/product images — явные локальные SVG placeholders с подписью Image pending. Не генерировать правдоподобные фотографии. `.pdf.filepart` — незавершённый файл, не использовать.

## Сохранение FE-01

Сохраняются реальные SVG header/footer, локальный Lato 400/600/700, Mantine olive palette, размеры и оформление header, корзины/поиска, drawer, footer, правые floating actions. WhatsApp `siteConfig.whatsappUrl === null`: не активировать исторический номер. Не менять пользовательские подписи/состояния floating buttons. Существующей общей утилиты контекстных WhatsApp-ссылок нет — добавить небольшую утилиту поверх того же config.

Swiper установлен, но статические сетки здесь полезнее: три категории, четыре товара, шесть обложек; autoplay не требуется. Fancybox использовать только в небольшой клиентской галерее обложек, с обычными ссылками на JPEG без JS. Tabler — существующие иконки CTA/решений.

## Открытые вопросы

Нужны оригинальные hero/category/product/interior фотографии, актуальные product URLs/ассортимент/цены, номер WhatsApp и контакты. Предложенные slug товаров и `/shop`, `/about` — будущие маршруты. Конечная таксономия материалов (FiberConcrete, FiberStone и др.) не вводится: в home.tpl соответствующие пункты закомментированы. `/catalogues` уже существует как FE-01 preview; содержимое FE-07 не реализуется.
