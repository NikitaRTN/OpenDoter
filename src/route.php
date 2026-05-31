<?php

declare(strict_types=1);

function resolve_route(array $config): array
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $path = '/' . trim($path, '/');
    if ($path === '//') {
        $path = '/';
    }

    if ($path === '/' || $path === '/index.php') {
        return ['type' => 'home'];
    }

    if ($path === '/api/parse') {
        return ['type' => 'api_parse'];
    }

    if (preg_match('#^/api/status/(\\d+)$#', $path, $matches)) {
        return [
            'type' => 'api_status',
            'match_id' => $matches[1],
        ];
    }

    if (preg_match('#^/matches/(\\d+)(?:/([a-z_]+))?$#', $path, $matches)) {
        return [
            'type' => 'match',
            'match_id' => $matches[1],
            'tab' => $matches[2] ?? 'overview',
        ];
    }

    if (preg_match('#^/players/(\\d+)$#', $path, $matches)) {
        return [
            'type' => 'player',
            'account_id' => $matches[1],
        ];
    }

    if ($path === '/search') {
        return [
            'type' => 'search',
            'query' => trim((string) ($_GET['q'] ?? '')),
        ];
    }

    return ['type' => 'not_found'];
}
