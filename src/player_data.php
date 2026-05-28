<?php

declare(strict_types=1);

function load_player_context(array $config, string $account_id): array
{
    $api_base = rtrim((string) $config['public_api_base'], '/');
    $timeout = (float) $config['request_timeout'];

    $profile = fetch_required_json("{$api_base}/players/{$account_id}", $timeout, 'Профиль игрока');
    $matches = fetch_required_json("{$api_base}/players/{$account_id}/matches?limit=20", $timeout, 'Матчи игрока');
    $heroes = fetch_first_required_json([
        rtrim((string) $config['api_base'], '/') . '/constants/heroes.json',
        "{$api_base}/constants/heroes",
    ], $timeout, 'Герои');

    return [
        'account_id' => $account_id,
        'player_profile' => $profile,
        'player_matches' => is_array($matches) ? $matches : [],
        'heroes' => $heroes,
    ];
}

function load_search_context(array $config, string $query): array
{
    $api_base = rtrim((string) $config['public_api_base'], '/');
    $timeout = (float) $config['request_timeout'];
    $players = [];
    $match = null;

    if ($query !== '') {
        if (ctype_digit($query) && strlen($query) >= 8) {
            $match_result = fetch_json("{$api_base}/matches/{$query}", $timeout);
            if ($match_result['ok'] && is_array($match_result['data']) && !empty($match_result['data']['match_id'])) {
                $match = $match_result['data'];
            }
        }

        $search_result = fetch_json("{$api_base}/search?q=" . rawurlencode($query), $timeout);
        if ($search_result['ok'] && is_array($search_result['data'])) {
            $players = $search_result['data'];
        }
    }

    return [
        'query' => $query,
        'search_players' => $players,
        'search_match' => $match,
    ];
}
