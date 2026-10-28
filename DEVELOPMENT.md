# OpenDoter — запуск и разработка

Проект состоит из двух частей, которые работают одновременно:

| Часть | Технология | Порт по умолчанию |
|-------|-----------|--------------------|
| Главный сайт (фронтенд) | PHP | `http://127.0.0.1:8000` (или свободный порт, показанный при запуске) |
| Локальный API (бэкенд) | Python | `http://localhost:8080` |

Сайт ходит в API по адресу из `src/config.php` (`api_base`). Поэтому API нужно запускать на порту **8080**.

## Быстрый старт

### Windows

- **`run-all.bat`** — запускает API и сайт в двух отдельных окнах и открывает браузер (рекомендуется).
- **`run-api.bat`** — только API.
- **`run-site.bat`** — только сайт.

Просто дважды кликните нужный файл или запустите его из консоли.

### macOS / Linux

```bash
./run-all.sh      # API в фоне + сайт на переднем плане
./run-api.sh      # только API
./run-site.sh     # только сайт
```

## Требования

- **PHP 8.1+** — для сайта (`php -S`). Достаточно встроенного сервера, расширения не нужны.
- **Python 3.10+** — для API и скриптов запуска сайта. Для просмотра матчей, профилей и констант дополнительных пакетов не требуется.
- **Java + Node.js** — нужны ТОЛЬКО для полного парсинга реплеев (кнопка «Запросить обработку»). Без них сайт работает в режиме метаданных.

## Настройка портов

- API: переменная окружения `PORT` (по умолчанию 8080).
- Сайт: переменная `SITE_PORT`. Без неё скрипт ищет свободный порт от 8000 до 8199, пропуская занятые и запрещённые Windows порты. Явно заданный `SITE_PORT` не подменяется: если он недоступен, запуск завершается с ошибкой.

Если меняете порт API, обновите `api_base` в `src/config.php`.

## Производительность: кэш констант

Константы (герои, предметы, способности) меняются очень редко, но нужны на каждой странице матча. Чтобы не читать и не парсить их по кругу:

- **API (Python):** отдаёт файлы констант из памяти (сырые байты, без повторного parse/serialize) с проверкой mtime.
- **Сайт (PHP):** кэширует ответы в `системном temp` (`opendoter_constants`). TTL настраивается в `src/config.php` ключом `constants_cache_ttl` (секунды; `0` — отключить).

## Известные моменты

- Tailwind подключён через CDN (`cdn.tailwindcss.com`). Это удобно для разработки, но компилирует CSS в браузере на каждой загрузке. Для продакшна рекомендуется собрать статичный CSS (Tailwind CLI) и подключать его файлом.

## Ошибка `Cannot GET /` и недоступный порт 8000

Главный сайт написан на PHP, а API — на Python. Ответ `Cannot GET /` не является страницей OpenDoter: проверьте, какой сервер отвечает на открытом адресе. Не останавливайте неизвестные процессы ради освобождения порта.

Запускайте `run-all.bat`: сайт проверяет доступность порта, запускает PHP и открывает браузер только после ответа именно от OpenDoter. Если Windows запрещает порт 8000 или он занят, будет выбран другой свободный порт. Используйте **точный URL из окна OpenDoter Site**, а не старую вкладку `localhost:8000`.

`run-site.bat --open-browser` или `./run-site.sh --open-browser` запускают только сайт с открытием браузера. Без флага браузер не открывается. Запуск напрямую через `php -S localhost:8000 index.php` не включает выбор свободного порта.

API остаётся на порту 8080. `GET /health` и `GET /` возвращают JSON с `service: OpenDoter API` и `status: ok`; это проверка самого HTTP-сервера, не доступности OpenDota или Java-парсера.

Проверка без внешних запросов:

```bash
python -m unittest discover -s tests -v
cd parser-master
python -m unittest discover -s tests -v
```

## Зависимость для новых реплеев Valve

Перед обработкой реплеев установите зависимости API из корня проекта:

```bash
python -m pip install -r parser-master/requirements.txt
```

Файл по ссылке `.dem.bz2` может быть сжат Zstandard, а не BZip2. API определяет формат по заголовку файла и поддерживает оба варианта. После обновления перезапустите API. Ранее скачанные архивы можно использовать повторно.

## Вкладки «Урон» и «Командные бои»

- «Урон»: сводка разделяет нанесённый урон и нативные счётчики полученного урона до/после уменьшения. Процент рассчитывается из этих счётчиков; подробные матрицы используют отдельные события `damage_taken`. Значения отсутствующих полей не подменяются нулями.
- Подробные матрицы раскрываются отдельно; на мобильном экране широкие таблицы прокручиваются внутри своего блока.
- Новые parsed blobs содержат явное `participated`, входящий урон и лечение участвующих союзников. Самолечение, пассивный доход, выкуп или использование предмета не подтверждают участие сами по себе.
- Для старых blobs действует консервативный fallback: убийства, смерти, нанесённый или полученный урон. Для полного учёта поддержки нужно пересобрать blob из `parsed.jsonl`; повторное скачивание демки и Java-парсинг не требуются.
- Командные итоги складываются только из показанных участников. Золото и опыт относятся ко всему временному окну, не только к наградам за бой. Преимущество — подписанная эвристика сайта, не официальный результат Dota.

Проверки:

```bash
php tests/test_match_insights.php
node --test parser-master/tests/teamfightParticipation.test.mjs
```

Визуальная проверка: ширины 1440 и 390 px, сводка, раскрытые матрицы, составы и действия игроков. Обновлены версии кэша двух вкладок и контекста матча, чтобы старый HTML не скрывал изменения.

## Native damage reduction

The damage summary reads replay team counters for physical, magical and pure incoming hero damage before/after reduction, rather than estimating from final armor. The parser exports the latest complete cumulative snapshot. Per-type counters are converted to integers before summing, matching the client scoreboard; reduction is `100 * (1 - post / pre)`, and prevented damage is `pre - post`. Missing counters remain unavailable; zero incoming damage has no defined percentage. Older cached matches need replay reprocessing. The detailed combat-log matrices remain a different measure.

The supplied match `9064491290` was reprocessed and checked against all five Dire players in the reference screenshot; Dragon Knight displays 131612 before reduction and 55.6%. These values are regression fixtures, not constants in the calculation.
