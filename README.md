# OpenDoter

Лёгкий веб-интерфейс на PHP для просмотра матчей и игроков Dota 2 (данные OpenDota).

## Структура

Проект построен вокруг единой точки входа — `index.php` (front controller).

```
index.php          # единая точка входа и роутинг
.htaccess          # rewrite всех запросов на index.php (Apache)
src/               # логика: роутинг, API, хелперы, компоненты, шаблоны (views)
css/               # стили
assets/            # статика
parser-master/     # API-сервер и парсер, подключён как приватный Git submodule
```

API-сервер вынесен в отдельный приватный репозиторий: https://github.com/NikitaRTN/OpenDoter-API

Чтобы клонировать проект вместе с API-сервером, используй:

```bash
git clone --recurse-submodules https://github.com/NikitaRTN/OpenDoter.git
```

Если проект уже склонирован без submodule:

```bash
git submodule update --init --recursive
```

Все маршруты обрабатываются в `src/route.php` и рендерятся из `index.php`:

- `/` — главная
- `/search?q=...` — поиск
- `/matches/{match_id}/{tab}` — страница матча (overview, vision, laning и др.)
- `/players/{account_id}` — профиль игрока

## Запуск локально

```bash
php -S localhost:8000 index.php
```

`index.php` сам отдаёт существующие статические файлы и маршрутизирует остальное, поэтому отдельный `router.php` больше не нужен.

## Деплой на хостинг

Залейте содержимое репозитория в корневую папку сайта. Apache с `mod_rewrite` и `.htaccess` направит все запросы на `index.php`. Настройте `src/config.php` под свой API.
