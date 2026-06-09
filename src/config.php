<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Moscow');

$config = [
    'api_base' => 'http://127.0.0.1:8080',
    // Локальный Python-API сам проксирует OpenDota для профилей/матчей/поиска,
    // поэтому фронтенд PHP больше не должен ходить в api.opendota.com напрямую.
    'public_api_base' => 'http://127.0.0.1:8080',
    'match_id' => '8822427707',
    'request_timeout' => 20.0,
    // Сколько секунд хранить локальный кэш констант (heroes/items/...). 0 = выкл.
    'constants_cache_ttl' => 21600,
    // Готовый PHP-контекст матча: убирает повторные HTTP-запросы к API и декодирование JSON.
    'match_context_cache_ttl' => 86400,
    // Короткий кэш профиля игрока: сглаживает наплывы без долгого устаревания данных.
    'player_context_cache_ttl' => 300,
    // HTML-кэш публичных страниц матча для анонимных пользователей.
    'match_page_cache_ttl' => 86400,
];
