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
];
