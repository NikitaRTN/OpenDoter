<?php

declare(strict_types=1);

function fetch_json(string $url, float $timeout): array
{
    $warning = null;
    $status_code = 0;
    $context = stream_context_create([
        'http' => [
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
        return [
            'ok' => false,
            'data' => null,
            'error' => $warning ?: 'Не удалось выполнить HTTP-запрос.',
        ];
    }

    if ($status_code >= 400) {
        return [
            'ok' => false,
            'data' => null,
            'error' => "HTTP {$status_code}: {$url}",
        ];
    }

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

function proxy_json_request(string $url, string $method, ?string $body, float $timeout): void
{
    $warning = null;
    $status_code = 502;
    $headers = [
        'Accept: application/json',
    ];

    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

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
