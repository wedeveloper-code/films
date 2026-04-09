# KinoBase — инструкции для Claude

## Подключение к серверу

- **SSH:** `ssh root@80.89.237.189` (VPS, не прямо к сайту)

## Пути на сервере

- **Корень сайта:** `/var/www/fastsite_top_usr/data/www/fastsite.top`
- **Тема WordPress:** `/var/www/fastsite_top_usr/data/www/fastsite.top/wp-content/themes/kinobase`
- **Репозиторий на VPS:** `/var/www/films`

## Команды деплоя

Подключиться к VPS:
```
ssh root@80.89.237.189
```

Обновить и задеплоить тему:
```
cd /var/www/films && git pull origin claude/wordpress-movie-theme-MEuhW && cp -r /var/www/films/kinobase /var/www/fastsite_top_usr/data/www/fastsite.top/wp-content/themes/
```

## Ветка разработки

`claude/wordpress-movie-theme-MEuhW`

## Стек

- WordPress на PHP 8.3 + Apache
- Тема: `kinobase` (custom, без плагинов)
- Сайт: `fastsite.top`
