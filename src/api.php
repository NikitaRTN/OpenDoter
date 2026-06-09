<?php

declare(strict_types=1);

const API_USER_AGENT = 'OpenDoter/1.0 (+https://github.com/NikitaRTN/OpenDoter)';
const API_MAX_ATTEMPTS = 3;
const API_RETRY_BASE_DELAY = 0.4;


function app_cache_root(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cache';
}

function app_cache_file(string $namespace, string $key): string
{
    $safe_namespace = preg_replace('/[^a-z0-9_-]+/i', '_', $namespace) ?: 'default';
    $safe_key = preg_replace('/[^a-z0-9_.-]+/i', '_', $key) ?: sha1($key);
    return app_cache_root() . DIRECTORY_SEPARATOR . $safe_namespace . DIRECTORY_SEPARATOR . $safe_key . '.cache';
}


function app_cache_get_raw(string $namespace, string $key, int $ttl): ?string
{
    if ($ttl <= 0) {
        return null;
    }

    $file = app_cache_file($namespace, $key);
    if (!is_file($file) || (time() - (int) filemtime($file)) >= $ttl) {
        return null;
    }

    $data = @file_get_contents($file);
    return $data === false ? null : $data;
}

function app_cache_set_raw(string $namespace, string $key, string $data): void
{
    $file = app_cache_file($namespace, $key);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $data, LOCK_EX) !== false) {
        @rename($tmp, $file);
    } else {
        @unlink($tmp);
    }
}

function app_cache_get(string $namespace, string $key, int $ttl): ?array
{
    if ($ttl <= 0) {
        return null;
    }

    static $memo = [];
    $memo_key = $namespace . ':' . $key;
    if (array_key_exists($memo_key, $memo)) {
        return $memo[$memo_key];
    }

    $file = app_cache_file($namespace, $key);
    if (!is_file($file) || (time() - (int) filemtime($file)) >= $ttl) {
        return $memo[$memo_key] = null;
    }

    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $memo[$memo_key] = null;
    }

    $data = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($data)) {
        @unlink($file);
        return $memo[$memo_key] = null;
    }

    return $memo[$memo_key] = $data;
}

function app_cache_set(string $namespace, string $key, array $data): void
{
    $file = app_cache_file($namespace, $key);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, serialize($data), LOCK_EX) !== false) {
        @rename($tmp, $file);
    } else {
        @unlink($tmp);
    }
}

function api_default_headers(?string $body = null): array
{
    $headers = [
        'Accept: application/json',
        'User-Agent: ' . API_USER_AGENT,
    ];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    return $headers;
}

function api_is_retryable_status(int $status_code): bool
{
    return $status_code === 408 || $status_code === 429 || $status_code >= 500;
}

function fetch_json(string $url, float $timeout): array
{
    $last_error = 'Не удалось выполнить HTTP-запрос.';

    for ($attempt = 1; $attempt <= API_MAX_ATTEMPTS; $attempt++) {
        $warning = null;
        $status_code = 0;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", api_default_headers()),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning = $message;
            return true;
        });

        try {
            $response = file_get_contents($url, false, $context);
            $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
            if (is_array($headers)) {
                foreach ($headers as $header) {
                    if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                        $status_code = (int) $matches[1];
                        break;
                    }
                }
            }
        } finally {
            restore_error_handler();
        }

        if ($response === false) {
            $last_error = $warning ?: 'Не удалось выполнить HTTP-запрос.';
        } elseif ($status_code >= 400) {
            $last_error = "HTTP {$status_code}: {$url}";
            if (!api_is_retryable_status($status_code)) {
                return ['ok' => false, 'data' => null, 'error' => $last_error];
            }
        } else {
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'ok' => false,
                    'data' => null,
                    'error' => 'Некорректный JSON: ' . json_last_error_msg(),
                ];
            }

            return [
                'ok' => true,
                'data' => $data,
                'error' => null,
            ];
        }

        if ($attempt < API_MAX_ATTEMPTS) {
            usleep((int) (API_RETRY_BASE_DELAY * (2 ** ($attempt - 1)) * 1000000));
        }
    }

    return [
        'ok' => false,
        'data' => null,
        'error' => $last_error,
    ];
}

function proxy_json_request(string $url, string $method, ?string $body, float $timeout): void
{
    $warning = null;
    $status_code = 502;
    $headers = api_default_headers($body);

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
        $warning = $message;
        return true;
    });

    try {
        $response = file_get_contents($url, false, $context);
        $response_headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
        if (is_array($response_headers)) {
            foreach ($response_headers as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                    $status_code = (int) $matches[1];
                    break;
                }
            }
        }
    } finally {
        restore_error_handler();
    }

    header('Content-Type: application/json; charset=utf-8');

    if ($response === false) {
        http_response_code(502);
        echo json_encode([
            'state' => 'error',
            'message' => $warning ?: 'Не удалось выполнить запрос к API.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    http_response_code($status_code);
    echo $response;
}

function fetch_required_json(string $url, float $timeout, string $label): array
{
    $result = fetch_json($url, $timeout);
    if (!$result['ok']) {
        throw new RuntimeException($label . ': ' . $result['error']);
    }

    if (!is_array($result['data'])) {
        throw new RuntimeException($label . ': API вернул пустой или неожиданный ответ.');
    }

    return $result['data'];
}

function fetch_first_required_json(array $urls, float $timeout, string $label): array
{
    $errors = [];
    foreach ($urls as $url) {
        $result = fetch_json($url, $timeout);
        if ($result['ok'] && is_array($result['data'])) {
            return $result['data'];
        }

        $errors[] = $result['error'] ?? 'Неизвестная ошибка';
    }

    throw new RuntimeException($label . ': ' . implode(' | ', $errors));
}

/**
 * Like fetch_first_required_json, but never throws. Returns the first OK array
 * response or an empty array if every URL fails. Use for optional/enrichment
 * data (e.g. ability constants) that should not break the whole page.
 */
function fetch_first_optional_json(array $urls, float $timeout): array
{
    foreach ($urls as $url) {
        $result = fetch_json($url, $timeout);
        if ($result['ok'] && is_array($result['data'])) {
            return $result['data'];
        }
    }

    return [];
}

/**
 * Локальный кэш редко меняющихся констант (heroes/items/abilities/...).
 * Избавляет от HTTP-запроса + декодирования большого JSON на каждой загрузке страницы.
 */
function constants_cache_dir(): string
{
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opendoter_constants';
}

function fetch_constants_cached(string $cache_key, array $urls, float $timeout, int $ttl, bool $required, string $label = ''): array
{
    // In-process мемо: одинаковые константы не декодируются дважды за запрос.
    static $memo = [];
    if (isset($memo[$cache_key])) {
        return $memo[$cache_key];
    }

    $file = constants_cache_dir() . DIRECTORY_SEPARATOR
        . preg_replace('/[^a-z0-9_]+/i', '_', $cache_key) . '.json';

    if ($ttl > 0 && is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && $cached !== []) {
            return $memo[$cache_key] = $cached;
        }
    }

    $data = $required
        ? fetch_first_required_json($urls, $timeout, $label)
        : fetch_first_optional_json($urls, $timeout);

    if (is_array($data) && $data !== [] && $ttl > 0) {
        $dir = constants_cache_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents(
            $file,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    return $memo[$cache_key] = $data;
}
