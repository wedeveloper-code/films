# FastWP — Техническое задание

WordPress-тема для сайта онлайн-кинотеатра. Папка темы: `fastwp`. Text domain: `fastwp`.

---

## 1. Стек и требования

| Параметр | Значение |
|---|---|
| CMS | WordPress 6.4+ |
| PHP | 8.1+ (используется `declare(strict_types=1)`, именованные аргументы, match) |
| Сервер | Apache2 |
| Расширения PHP | GD (для конвертации WebP), mbstring |
| Внешние CDN | **Запрещены** — всё локально |
| JS-фреймворки | **Запрещены** — только vanilla JS |
| CSS-фреймворки | **Запрещены** — только нативный CSS |
| Шрифт | Inter Variable (woff2, self-hosted: `assets/fonts/inter/InterVariable.woff2`) |

---

## 2. Структура файлов

```
fastwp/
├── style.css                        # Регистрация темы (только заголовок)
├── functions.php                    # Константы FASTWP_DIR / FASTWP_URI + include всех inc/
├── index.php                        # Запасной шаблон (пустой, только get_header/footer)
├── front-page.php                   # Главная страница
├── archive.php                      # Рубрики/архивы с фильтром
├── single.php                       # Страница одного фильма/поста
├── single-actor.php                 # Страница актёра
├── search.php                       # Результаты поиска
├── 404.php                          # Страница ошибки
├── page.php                         # Стандартная страница
├── page-login.php                   # Вход (slug: login)
├── page-register.php                # Регистрация (slug: register)
├── page-favorites.php               # Избранное (slug: favorites)
├── page-contact.php                 # Контакты (slug: contact)
├── header.php                       # Шапка
├── footer.php                       # Подвал
├── template-parts/
│   ├── movie-card.php               # Карточка фильма (полная)
│   └── movie-mini-card.php          # Мини-карточка (для актёров)
├── assets/
│   ├── css/main.css                 # Весь CSS (~2800 строк)
│   ├── js/main.js                   # Весь JS (~940 строк)
│   └── fonts/inter/
│       └── InterVariable.woff2      # Variable font (Latin + Cyrillic)
└── inc/
    ├── setup.php                    # after_setup_theme, image sizes, nav menus
    ├── enqueue.php                  # wp_enqueue_scripts, preload шрифта
    ├── post-types.php               # CPT movie+actor, taxonomy actor_attr, filter rewrites
    ├── meta-boxes.php               # Meta boxes для фильмов
    ├── meta-boxes-actor.php         # Meta boxes для актёров
    ├── category-meta.php            # SEO рубрик, h1 шаблоны, kb_bottom_description
    ├── image-optimizer.php          # Авто-конвертация WebP + сжатие до 200KB
    ├── ajax-handlers.php            # AJAX: счётчик просмотров
    ├── auth.php                     # Кастомная аутентификация
    ├── rotation.php                 # Ротация (рандомизация) порядка фильмов
    ├── sitemap.php                  # XML-карта сайта (/sitemap.xml)
    └── field-builder.php            # Конструктор кастомных полей (admin UI)
```

### `functions.php`

```php
define('FASTWP_VERSION', '1.0.0');
define('FASTWP_DIR', get_template_directory());
define('FASTWP_URI', get_template_directory_uri());

// Include order matters:
require FASTWP_DIR . '/inc/setup.php';
require FASTWP_DIR . '/inc/enqueue.php';
require FASTWP_DIR . '/inc/post-types.php';
require FASTWP_DIR . '/inc/meta-boxes.php';
require FASTWP_DIR . '/inc/meta-boxes-actor.php';
require FASTWP_DIR . '/inc/category-meta.php';
require FASTWP_DIR . '/inc/image-optimizer.php';
require FASTWP_DIR . '/inc/ajax-handlers.php';
require FASTWP_DIR . '/inc/auth.php';
require FASTWP_DIR . '/inc/rotation.php';
require FASTWP_DIR . '/inc/sitemap.php';
require FASTWP_DIR . '/inc/field-builder.php';
```

---

## 3. Типы записей и таксономии

### CPT: `movie`
- Публичный, slug: `film` (`/film/{slug}/`)
- `has_archive: false`
- Поддерживает: title, editor, thumbnail, comments, excerpt
- Таксономии: `category`, `post_tag`
- Показывается в меню на позиции 5, иконка `dashicons-format-video`

### CPT: `actor`
- Публичный, slug: `actor` (`/actor/{slug}/`)
- `has_archive: 'actors'` → `/actors/`
- Поддерживает: title, editor, thumbnail, excerpt

### Taxonomy: `actor_attr`
- На CPT `actor`, иерархическая
- Slug: `actor-attr` (`/actor-attr/{slug}/`)
- Структура: Родитель = тип атрибута (Гражданство, Ярлыки…), Дочерний = значение (Норвегия, Оскар…)

### Стандартные `post`
- Работают наравне с `movie` везде: на главной, в архивах, в категориях
- `pre_get_posts` хук добавляет `movie` ко всем запросам на category/tag/home/front_page

---

## 4. Структура категорий WordPress

Используются стандартные `category`. Структура иерархическая:

```
Фильмы          (slug: films)
Сериалы         (slug: series)
Телепередачи    (slug: tv)
Новинки         (slug: new)

Год             (slug: год / god / years / gody)
  └── 2020, 2021, 2022, 2023, 2024, 2025…

Жанры           (slug: жанры / zhanry / genres)
  └── боевик, комедия, драма, фантастика…

Качество        (slug: качество / quality)
  └── HD, FullHD, 4K

Акции/Бонусы    (slug: акция / скидки / бонусы — любое из этих слов)
  └── конкретные акции
```

**Важно:**
- Карточка фильма определяет год/качество/теги-акции по родительской категории (`parent->name` в lowercase)
- Родители для года: `год`, `year`, `годы`
- Родители для качества: `качество`, `quality`
- Родители для акций: `акция`, `акции`, `скидки`, `скидка`, `бонусы`, `бонус`
- Когда фильм числится в категории-потомке (напр. 2023 ⊂ Год), год определяется автоматически

---

## 5. Кастомные мета-поля

Хранятся через `post_meta`. Применяются к `post` и `movie`.

| Ключ | Тип | Описание |
|---|---|---|
| `movie_gallery` | array (int[]) | ID изображений из медиатеки (без ограничений, первое = постер) |
| `movie_quality` | string | Качество: HD / FullHD / 4K |
| `movie_duration` | string | Длительность: «1ч 55м» |
| `movie_translation` | string | Тип перевода |
| `movie_rating` | float | Рейтинг 0.0–10.0 |
| `movie_actors` | string | Список актёров (textarea, через запятую/перенос) |
| `movie_directors` | string | Список режиссёров |
| `movie_box_office` | array | Сборы: [{label, value}] (динамические строки) |
| `movie_rent_1` | int | Цена аренды 1 просмотр (₽) |
| `movie_rent_3` | int | Цена аренды 3 просмотра (₽) |
| `movie_rent_5` | int | Цена аренды 5 просмотров (₽) |
| `movie_buy_week` | int | Покупка на неделю (₽) |
| `movie_buy_month` | int | Покупка на месяц (₽) |
| `movie_buy_forever` | int | Покупка навсегда (₽) |
| `movie_coupon` | string | Код купона на скидку |
| `movie_views` | int | Счётчик просмотров карточки |

### Мета-боксы в админке

| Бокс | Поля |
|---|---|
| Постеры и фото фильма | `movie_gallery` — неограниченное кол-во фото, drag-and-drop, wp.media |
| Информация о фильме | quality, duration, translation |
| Актёры, режиссёры, рейтинг, сборы | rating, actors, directors, box_office (динамические строки) |
| Цены (Аренда и Покупка) | rent_1, rent_3, rent_5, buy_week, buy_month, buy_forever |
| Купон на скидку | coupon (sidebar) |
| Метатеги (SEO) | SEO-title, SEO-description (переопределяет глобальные шаблоны) |

### Meta boxes для актёров (`inc/meta-boxes-actor.php`)
- Дата рождения, место рождения, рост, краткая биография
- Связанные фильмы (поиск по названию + добавление)

---

## 6. Навигационные меню

Регистрируются 3 локации:

| Slug | Название в админке | Использование |
|---|---|---|
| `primary` | Верхнее меню (рубрики) | `header.php` — горизонтальная навигация |
| `fastwp_filters` | Меню фильтров (Год, Жанр, Качество…) | `front-page.php` и `archive.php` — фильтр-бар |
| `footer` | Меню подвала | `footer.php` |

### Структура меню фильтров (`fastwp_filters`)

```
Год           ← верхний уровень = кнопка-дропдаун
  ├── 2025
  ├── 2024
  └── 2020…
Жанр
  ├── Боевик
  └── Комедия…
Качество
  ├── HD
  └── 4K…
Бонусы
  ├── Акция
  └── Скидки…
```

- Каждый верхний уровень → кнопка с выпадающим списком
- Каждый дочерний пункт → ссылка на категорию WordPress
- При нескольких активных фильтрах — все сохраняются в URL: `/category/films/2023/боевик/`

---

## 7. URL-структура и Rewrite Rules

| URL | Что отображает |
|---|---|
| `/` | front-page.php — каталог (4 блока) |
| `/2023/` | front-page.php — главная, фильтр по году (query var `kb_home_year=2023`) |
| `/category/films/` | archive.php — рубрика «Фильмы» |
| `/category/films/2023/` | archive.php — фильмы 2023 года (query var `kb_filter_path=2023`) |
| `/category/films/2023/боевик/` | archive.php — фильмы 2023 + жанр Боевик |
| `/film/{slug}/` | single.php — страница фильма |
| `/actor/{slug}/` | single-actor.php — страница актёра |
| `/actors/` | archive.php — все актёры |
| `/search?s=…` | search.php |
| `/sitemap.xml` | XML-карта сайта |

### Rewrite rules (регистрируются в `inc/post-types.php`)

```php
// Фильтры внутри категории: /category/{cat}/{filter_path}/page/{n}/
'^category/([^/]+)/(.+)/page/([0-9]+)/?$'
  → 'index.php?category_name=$1&kb_filter_path=$2&paged=$3'

// Фильтры без пагинации:
'^category/([^/]+)/(.+?)/?$'
  → 'index.php?category_name=$1&kb_filter_path=$2'

// Год на главной: /{year}/page/{n}/
'^([0-9]{4})/page/([0-9]+)/?$'
  → 'index.php?kb_home_year=$1&paged=$2'

// Год на главной: /{year}/
'^([0-9]{4})/?$'
  → 'index.php?kb_home_year=$1'
```

Query vars: `kb_filter_path`, `kb_home_year`

**Важно:** После изменения правил вызывать `flush_rewrite_rules(false)`. В коде это делается через transient `kb_filter_rewrites_v2` (раз в месяц).

---

## 8. Шаблоны страниц

### `header.php`

- `<html class="dark">` — тёмная тема по умолчанию
- Inline-скрипт сразу после `<head>`: применяет тему из `localStorage('fastwp_theme')` ДО рендера (предотвращает FOUC)
- Поддержка авто-темы по времени (из WP options: `kb_default_theme`, `kb_theme_auto_time`, `kb_theme_dark_from`, `kb_theme_light_from`)
- Поисковый оверлей (скрытый `div`, открывается кликом на иконку поиска)
- Шапка: логотип `FAST`/`WP` + навигация (`wp_nav_menu` для `primary`) + иконка поиска + шестерёнка с дропдауном (Войти / Регистрация / переключатель темы)

### `front-page.php`

- H1 «Каталог видео — всего в базе N видео» (количество из transient `fastwp_movie_count`)
- Фильтр-бар (если настроено меню `fastwp_filters`)
- 4 секции: Фильмы / Сериалы / Телепередачи / Новинки
- Каждая секция: WP_Query по `category_name` + `no_found_rows: true`, 10 карточек
- Поддержка `kb_home_year` — фильтрует все секции по году
- Ротация: к WP_Query добавляется `orderby: RAND(seed)` (seed из `inc/rotation.php`)
- Кнопка «Следующая страница» добавляется как последняя карточка-плитка

### `archive.php`

- H1 через `kb_cat_h1($queried, $kb_filter_terms)` — поддерживает шаблонные переменные
- Счётчик фильмов в рубрике (отдельный WP_Query)
- Фильтр-бар сверху (если `fastwp_filters` настроен)
- Подкатегорийные пилюли НЕ показываются, если фильтр-бар есть
- Сетка карточек + пагинация (стандартная + мобильный список страниц)
- Текст внизу страницы (`kb_bottom_description` из term_meta) — для SEO

### `single.php`

- Хлебные крошки (Главная → Рубрика → Фильм)
- Левая колонка (35%): галерея постеров с навигацией по точкам
- Правая колонка (65%): мета-таблица категорий, рейтинг, цены, купон
- Мета-таблица показывает ВСЕ категории фильма как строки: «Год: 2023», «Жанр: Боевик» и т.д. — с ссылками на рубрики
- Блок цен: табы Аренда / Покупка, 3 ячейки каждый
- Купон: кнопка «Показать купон» → код → кнопка «Копировать»
- Отзывы (стандартные WordPress комментарии) — на всю ширину
- Форма отзыва с math-captcha (transient на 15 минут)

### `single-actor.php`

- Фото актёра + биография
- Атрибуты (из taxonomy `actor_attr`)
- Связанные фильмы (мини-карточки `movie-mini-card.php`)

### `template-parts/movie-card.php`

- Галерея: несколько изображений (из `movie_gallery`), переключение ховером по зонам
- Если изображений нет — фоллбэк на `get_the_post_thumbnail`
- Бейджи: качество (из категорий > фоллбэк из meta), рейтинг `★ 9.4`
- Иконка избранного (сердечко), счётчик просмотров
- Год и качество как ссылки на категории
- Теги акций (если есть категории из группы «Акции/Бонусы»)
- Таблица цен: 2 таба (Аренда / Покупка), по 3 ячейки
- Купон: кнопка → показать код → копировать
- `loading="lazy"` на все изображения кроме первых 5 карточек первого блока (LCP)
- `fetchpriority="high"` на первое изображение первых 5 карточек

---

## 9. Системные функции (inc/)

### `inc/setup.php`

- `add_image_size('movie-poster', 300, 450, true)` — постер 2:3
- `add_image_size('movie-poster-sm', 150, 225, true)` — мобильный
- Transient `fastwp_movie_count` — кешированный count, сбрасывается при `save_post` / `delete_post`
- Функции `fastwp_get_year_parent()` / `fastwp_get_genre_parent()` — ищут родительскую категорию по нескольким вариантам slug/name (static cache)
- `fastwp_filter_url(main_slug, year, genre)` — строит URL фильтра
- SEO: `fastwp_replace_seo_vars(tpl, post_id)` — заменяет `%название%`, `%сайт%` и т.д. в шаблонах

### `inc/enqueue.php`

- CSS: `main.css` с версией через `filemtime`
- JS: `main.js` defer, в подвале, с версией через `filemtime`
- `wp_localize_script`: передаёт `FastWP.ajaxUrl`, `FastWP.nonce` (fastwp_views), `FastWP.contactNonce`
- Preload: `<link rel="preload" href="…/InterVariable.woff2" as="font" crossorigin>`
- Отключены: `wp-block-library`, `wp-block-library-theme`, `classic-theme-styles`, `global-styles`
- Удалены из `wp_head`: `wp_generator`, `wlwmanifest_link`, `rsd_link`, `wp_shortlink_wp_head`, `adjacent_posts_rel_link_wp_head`

### `inc/image-optimizer.php`

Хук `wp_handle_upload`. Для каждой загруженной картинки:
1. Проверить MIME (image/*)
2. Открыть через GD (`imagecreatefrompng` / `imagecreatefromjpeg` / `imagecreatefromwebp`)
3. Сохранить как WebP через `imagewebp()`, качество 85
4. Если файл > 200KB — снижать качество пошагово (85→75→65→55) пока ≤ 200KB
5. Если всё ещё > 200KB — масштабировать изображение (уменьшить разрешение)
6. Заменить оригинал, обновить `$file['type']` на `image/webp`
7. Требует расширение GD (проверка в начале файла, `return` если недоступно)

### `inc/ajax-handlers.php`

- Action: `wp_ajax_nopriv_fastwp_increment_views` + `wp_ajax_fastwp_increment_views`
- POST: `post_id`, `nonce` (fastwp_views)
- Увеличивает `movie_views` на 1 через `update_post_meta`
- Использует `$wpdb` напрямую для атомарного инкремента: `UPDATE … SET meta_value = meta_value + 1`

### `inc/auth.php`

- При активации темы автоматически создаёт страницы `/login/` и `/register/` (page_template: `page-login.php` / `page-register.php`)
- Редирект с `wp-login.php` → `/login/`
- Регистрация: форма → письмо-подтверждение → создание пользователя → отправка пароля
- Роль новых пользователей: `subscriber`
- Вход через `wp_signon()`
- Author-роль: ограничен своими медиафайлами, урезанное меню в админке

### `inc/rotation.php`

- Страница в админке: Параметры → Ротация фильмов
- Настройки: `kb_rotation_enabled` (bool), `kb_rotation_interval` (часы, default 24)
- Принцип: stable seed = `floor(time() / (interval * 3600))`, хранится в transient
- Хук `pre_get_posts`: добавляет `orderby = RAND({seed})` ко всем публичным запросам на главной и архивах (если `kb_rotation_enabled = true`)
- Все посетители видят одинаковый порядок в течение одного интервала
- Пагинация работает корректно (seed детерминированный)

### `inc/sitemap.php`

- Rewrite rule: `/sitemap.xml` → `?kb_sitemap=1`
- Обрабатывается хуком `template_redirect`
- XML-формат: `<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`
- Включает: главная (priority 1.0), категории (0.9), фильмы + актёры (0.5), страницы (0.5)
- Страница настроек: Параметры → Карта сайта (включить/выключить, исключить типы)

### `inc/category-meta.php`

Добавляет в форму редактирования рубрики:
- **Текст внизу страницы** (`kb_bottom_description`) — textarea, выводится в `archive.php` ниже сетки
- **SEO Title** (`_kb_cat_seo_title`) — переопределяет глобальный шаблон
- **SEO Description** (`_kb_cat_seo_description`) — переопределяет глобальный шаблон
- **H1** (`_kb_cat_h1`) — переопределяет глобальный шаблон

Глобальные шаблоны (Параметры → SEO рубрик):
- `kb_cat_h1_tpl`, `kb_cat_seo_title_tpl`, `kb_cat_seo_desc_tpl`
- Переменные: `%название_рубрики%`, `%родительская_рубрика%`, `%сайт%`, `%фильтр%`

Фильтровые страницы `/category/films/2023/`:
- `kb_get_active_filter_terms()` → парсит `kb_filter_path`, возвращает WP_Term[]
- `kb_cat_h1($term, $filter_terms)` → строит H1 с учётом активных фильтров
- Комбинированные шаблоны (Параметры → SEO рубрик → «Шаблоны для страниц с фильтром»): можно задать H1/title/desc для конкретной комбинации рубрика+фильтр

### `inc/field-builder.php`

- Страница в админке: Фильмы → Поля фильмов
- Создание кастомных текстовых/числовых полей, которые добавляются ко всем карточкам
- Поля хранятся в WP option `fastwp_custom_fields` как JSON
- Типы: `text`, `number`, `textarea`
- Поле `field_type` санируется через `sanitize_text_field()` + `in_array` перед использованием

---

## 10. JavaScript (`assets/js/main.js`, ~940 строк)

Весь JS — vanilla, без зависимостей. Выполняется defer.

| Функция | Реализация |
|---|---|
| Переключение темы | `localStorage('fastwp_theme')` = 'dark'/'light', toggle `.dark` на `<html>` |
| Открытие поиска | Клик на иконку → показать `#search-overlay`, focus на input |
| Дропдаун шестерёнки | Клик → toggle `.open` |
| Фильтр-бар (дропдауны) | `data-fb-toggle` → toggle соответствующего `filter-bar-drop`, click outside — закрыть |
| Галерея карточки | `mouseenter` по `.gallery-zone` → добавить `.active` на соответствующий `img` |
| Избранное | Cookie `fastwp_favorites` (7 дней), JSON-массив post_id, toggle при клике на сердечко |
| Купон | Клик → показать код, второй клик → копировать в буфер (`navigator.clipboard`), показать «Скопировано!» |
| Счётчик просмотров | `IntersectionObserver` (threshold 0.5) → AJAX POST на `fastwp_increment_views`, один раз за сессию (sessionStorage) |
| Мобильный фильтр-панель | Кнопка «Фильтры» → sliding panel с экранами (список групп → конкретная группа) |
| Переход по клику на галерею | `click` на `.movie-gallery` → `window.location = data-href` |

---

## 11. CSS (`assets/css/main.css`, ~2800 строк)

### Тёмная/светлая тема

```css
:root {
  --bg: #f3f4f6;
  --bg-card: #ffffff;
  --bg-secondary: #e5e7eb;
  --text: #111827;
  --text-muted: #6b7280;
  --border: #d1d5db;
  --brand: #ff4d4d;
  --brand-accessible: #b91c1c;  /* WCAG AA контраст на белом */
}
.dark {
  --bg: #0f1115;
  --bg-card: #15181e;
  --bg-secondary: #1c1f27;
  --text: #f9fafb;
  --text-muted: #9ca3af;
  --border: #2d3139;
  --brand: #ff4d4d;
  --brand-accessible: #b91c1c;
}
```

### Шрифт

```css
@font-face {
  font-family: 'Inter';
  src: url('../fonts/inter/InterVariable.woff2') format('woff2');
  font-weight: 100 900;
  font-style: normal;
  font-display: swap;
}
```

### Ключевые компоненты

| Класс | Описание |
|---|---|
| `.container` | `max-width: 1600px`, `margin: 0 auto`, `padding: 0 1rem` |
| `.site-header` | `position: sticky; top: 0`, фиксированная шапка |
| `.movie-grid` | `display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))` |
| `.movie-card` | `aspect-ratio` для галереи, hover-эффекты |
| `.movie-gallery` | `position: relative`, изображения `position: absolute; inset: 0` |
| `.single-layout` | `display: grid; grid-template-columns: 35% 1fr` (desktop: ≥768px) |
| `.filter-bar` | flex-строка с кнопками-дропдаунами |
| `.filter-bar-drop` | абсолютное позиционирование, `z-index: 100` |
| `.filter-pill` | pills для мобильного фильтра |

### Breakpoints
- Мобильный: до 768px — одна колонка в `.single-layout`
- Планшет: 768px — `grid-template-columns: 35% 1fr`
- Десктоп: 1024px — `grid-template-columns: 35% 1fr`

### Accessibility (WCAG AA)
- Интерактивные элементы ≥ 44×44px touch target (через `padding + background-clip: content-box`)
- Кнопки и ссылки имеют `:focus-visible` outline
- Цвет акцента на кнопках: `--brand-accessible: #b91c1c` (контраст ≥ 4.5:1 с белым)
- Индикаторы галереи: `padding: 1.125rem`, `gap: 0` (tap target без увеличения визуала)

---

## 12. Страницы администратора

| Страница | Путь в меню | Функция |
|---|---|---|
| SEO рубрик | Параметры → SEO рубрик | Глобальные шаблоны H1/title/desc для рубрик |
| Ротация фильмов | Параметры → Ротация фильмов | Включить ротацию, интервал в часах |
| Карта сайта | Параметры → Карта сайта | Включить/выключить sitemap, исключить типы |
| Поля фильмов | Фильмы → Поля фильмов | Конструктор кастомных полей |

---

## 13. Производительность

| Метрика | Цель |
|---|---|
| LCP | < 2.5s |
| CLS | < 0.1 |
| INP | < 100ms |
| Внешних HTTP-запросов | 0 |
| Размер JS | < 30KB gzip |
| Размер CSS | < 50KB gzip |

**Техники:**
- `filemtime()` для версионирования CSS/JS (cache-busting без CDN)
- `no_found_rows: true` в WP_Query на главной (не считать общее число)
- Transients: счётчик фильмов (`fastwp_movie_count`), seed ротации, flush rewrites
- `loading="lazy"` + `fetchpriority="high"` на карточках
- Preload `InterVariable.woff2`, `font-display: swap`
- Defer для `main.js`
- Отключены все стили Gutenberg (`wp-block-library` и т.д.)
- Удалены лишние `wp_head` теги (generator, wlwmanifest, rsd, shortlink)

---

## 14. Установка и первоначальная настройка

1. Скопировать папку `fastwp` в `/wp-content/themes/`
2. Активировать тему в Внешний вид → Темы
3. Создать категории согласно разделу 4 (Год, Жанры, Качество, основные рубрики)
4. Внешний вид → Меню:
   - Создать «Главное меню» → назначить на `primary` (добавить Фильмы/Сериалы/Телепередачи/Новинки)
   - Создать «Фильтры» → назначить на `fastwp_filters` (структура: см. раздел 6)
5. Параметры → Постоянные ссылки → Сохранить (сброс rewrite rules)
6. Загрузить несколько фильмов, заполнить мета-поля, проверить карточки

