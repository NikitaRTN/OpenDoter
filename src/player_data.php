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
    $public_api_base = rtrim((string) $config['public_api_base'], '/');
    $local_api_base = rtrim((string) $config['api_base'], '/');
    $timeout = (float) $config['request_timeout'];
    $players = [];
    $match = null;

    if ($query !== '') {
        if (ctype_digit($query) && strlen($query) >= 8) {
            $match = lookup_match_by_id($local_api_base, $public_api_base, $query, $timeout);
        }

        $search_result = fetch_json("{$public_api_base}/search?q=" . rawurlencode($query), $timeout);
        if ($search_result['ok'] && is_array($search_result['data'])) {
            $players = array_values(array_filter(
                $search_result['data'],
                static fn ($row): bool => is_array($row) && !empty($row['account_id'])
            ));
        }
    }

    return [
        'query' => $query,
        'search_players' => $players,
        'search_match' => $match,
    ];
}

/**
 * Resolve a match id to its data, trying the local API first (the same source
 * the match pages use) and falling back to the public OpenDota API. Returns the
 * normalized match array, or null when the id is unknown to both sources.
 */
function lookup_match_by_id(string $local_api_base, string $public_api_base, string $match_id, float $timeout): ?array
{
    $candidates = [
        "{$local_api_base}/api/match/{$match_id}?full=1",
        "{$public_api_base}/matches/{$match_id}",
    ];

    foreach ($candidates as $url) {
        $result = fetch_json($url, $timeout);
        if (!$result['ok'] || !is_array($result['data'])) {
            continue;
        }

        [$normalized] = normalize_match_response($result['data']);
        if ($normalized !== [] && !empty($normalized['match_id'])) {
            return $normalized;
        }
    }

    return null;
}
