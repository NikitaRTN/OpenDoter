<?php

declare(strict_types=1);

function load_player_context(array $config, string $account_id, int $page = 1, bool $include_turbo = false): array
{
    $api_base = rtrim((string) $config['api_base'], '/');
    $timeout = (float) $config['request_timeout'];
    $page = max(1, $page);
    $per_page = 25;
    $offset = ($page - 1) * $per_page;
    $turbo_param = $include_turbo ? '&turbo=1' : '';
    $cache_ttl = (int) ($config['player_context_cache_ttl'] ?? 0);
    $cache_key = 'player_' . preg_replace('/\D+/', '', $account_id) . '_p' . $page . '_' . ($include_turbo ? 'turbo' : 'regular') . '_v1';

    $cached = app_cache_get('player_context', $cache_key, $cache_ttl);
    if ($cached !== null) {
        return $cached;
    }

    $profile = fetch_required_json("{$api_base}/api/players/{$account_id}", $timeout, 'Профиль игрока');

    if ($page === 1) {
        // Первый экран и агрегаты используют один и тот же батч на 100 матчей.
        // Раньше профиль делал два похожих запроса: 26 матчей для таблицы и 100
        // для статистики. На холодном кэше это добавляло лишний поход в API/OpenDota.
        $stats_matches = fetch_required_json("{$api_base}/api/players/{$account_id}/matches?limit=100&offset=0{$turbo_param}", $timeout, 'Матчи игрока');
        $stats_matches = is_array($stats_matches) ? $stats_matches : [];
        $matches = array_slice($stats_matches, 0, $per_page);
        $has_next_page = count($stats_matches) > $per_page;
    } else {
        // История матчей постраничная. Запрашиваем на 1 матч больше, чтобы понять, есть ли следующая страница.
        $matches_page = fetch_required_json("{$api_base}/api/players/{$account_id}/matches?limit=" . ($per_page + 1) . "&offset={$offset}{$turbo_param}", $timeout, 'Матчи игрока');
        $matches_page = is_array($matches_page) ? $matches_page : [];
        $has_next_page = count($matches_page) > $per_page;
        $matches = array_slice($matches_page, 0, $per_page);

        // Отдельная выборка для агрегированной статистики сверху, чтобы пагинация не меняла средние значения.
        $stats_matches = fetch_first_optional_json(["{$api_base}/api/players/{$account_id}/matches?limit=100&offset=0{$turbo_param}"], $timeout);
        $stats_matches = is_array($stats_matches) ? $stats_matches : $matches;
    }

    $heroes = fetch_constants_cached('heroes', [
        "{$api_base}/constants/heroes.json",
        "{$api_base}/constants/heroes",
    ], $timeout, (int) ($config['constants_cache_ttl'] ?? 21600), true, 'Герои');

    // При include_turbo=1 локальный API прокидывает significant=0, поэтому Turbo попадает в win/loss и heroes.
    $wl_raw = fetch_first_optional_json(["{$api_base}/api/players/{$account_id}/wl" . ($include_turbo ? '?turbo=1' : '')], $timeout);
    $heroes_raw = fetch_first_optional_json(["{$api_base}/api/players/{$account_id}/heroes" . ($include_turbo ? '?turbo=1' : '')], $timeout);

    $stats = compute_player_stats($stats_matches, $heroes, $wl_raw, $heroes_raw);
    $stats['page'] = $page;
    $stats['per_page'] = $per_page;
    $stats['has_next_page'] = $has_next_page;
    $stats['include_turbo'] = $include_turbo;

    $context = [
        'account_id' => $account_id,
        'player_profile' => $profile,
        'player_matches' => $matches,
        'heroes' => $heroes,
        'player_stats' => $stats,
    ];

    app_cache_set('player_context', $cache_key, $context);

    return $context;
}

/**
 * Собрать агрегированную статистику игрока для подробного профиля.
 *
 * Основной источник — список матчей (работает всегда, даже офлайн).
 * Если доступны полные wl/heroes из OpenDota — используем их для «всего времени».
 */
function compute_player_stats(array $matches, array $heroes, array $wl_raw, array $heroes_raw): array
{
    $wins = 0;
    $losses = 0;
    $k = 0; $d = 0; $a = 0;
    $total_duration = 0;
    $count = 0;
    $hero_agg = [];
    $longest = 0;
    $best_kda = 0.0;

    foreach ($matches as $match) {
        if (!is_array($match)) {
            continue;
        }
        $count++;
        $is_radiant = (int) ($match['player_slot'] ?? 0) < 128;
        $won = ((bool) ($match['radiant_win'] ?? false)) === $is_radiant;
        if ($won) { $wins++; } else { $losses++; }

        $mk = (int) ($match['kills'] ?? 0);
        $md = (int) ($match['deaths'] ?? 0);
        $ma = (int) ($match['assists'] ?? 0);
        $k += $mk; $d += $md; $a += $ma;
        $dur = (int) ($match['duration'] ?? 0);
        $total_duration += $dur;
        if ($dur > $longest) { $longest = $dur; }
        $ratio = kda_ratio($mk, $md, $ma);
        if ($ratio > $best_kda) { $best_kda = $ratio; }

        $hid = (int) ($match['hero_id'] ?? 0);
        if ($hid > 0) {
            if (!isset($hero_agg[$hid])) {
                $hero_agg[$hid] = ['hero_id' => $hid, 'games' => 0, 'wins' => 0, 'k' => 0, 'd' => 0, 'a' => 0];
            }
            $hero_agg[$hid]['games']++;
            if ($won) { $hero_agg[$hid]['wins']++; }
            $hero_agg[$hid]['k'] += $mk;
            $hero_agg[$hid]['d'] += $md;
            $hero_agg[$hid]['a'] += $ma;
        }
    }

    // Герои из OpenDota /heroes имеют полную историю — предпочитаем их.
    $top_heroes = [];
    if ($heroes_raw !== []) {
        usort($heroes_raw, static fn($x, $y) => ((int) ($y['games'] ?? 0)) <=> ((int) ($x['games'] ?? 0)));
        foreach (array_slice($heroes_raw, 0, 8) as $row) {
            $games = (int) ($row['games'] ?? 0);
            if ($games <= 0) { continue; }
            $top_heroes[] = [
                'hero_id' => (int) ($row['hero_id'] ?? 0),
                'games' => $games,
                'wins' => (int) ($row['win'] ?? 0),
                'winrate' => $games > 0 ? round(((int) ($row['win'] ?? 0)) / $games * 100) : 0,
            ];
        }
    } else {
        usort($hero_agg, static fn($x, $y) => $y['games'] <=> $x['games']);
        foreach (array_slice(array_values($hero_agg), 0, 8) as $row) {
            $top_heroes[] = [
                'hero_id' => $row['hero_id'],
                'games' => $row['games'],
                'wins' => $row['wins'],
                'winrate' => $row['games'] > 0 ? round($row['wins'] / $row['games'] * 100) : 0,
            ];
        }
    }

    // Общие победы/поражения: предпочитаем wl (вся история), иначе — по матчам.
    $total_wins = (int) ($wl_raw['win'] ?? $wins);
    $total_losses = (int) ($wl_raw['lose'] ?? $losses);
    $total_games = $total_wins + $total_losses;

    return [
        'sample' => $count,
        'recent_wins' => $wins,
        'recent_losses' => $losses,
        'total_wins' => $total_wins,
        'total_losses' => $total_losses,
        'total_games' => $total_games,
        'winrate' => $total_games > 0 ? round($total_wins / $total_games * 100, 1) : 0.0,
        'has_full_wl' => $wl_raw !== [],
        'avg_kills' => $count > 0 ? round($k / $count, 1) : 0,
        'avg_deaths' => $count > 0 ? round($d / $count, 1) : 0,
        'avg_assists' => $count > 0 ? round($a / $count, 1) : 0,
        'avg_kda' => kda_ratio($k, $d, $a),
        'avg_duration' => $count > 0 ? (int) round($total_duration / $count) : 0,
        'longest' => $longest,
        'best_kda' => $best_kda,
        'top_heroes' => $top_heroes,
    ];
}

function load_search_context(array $config, string $query): array
{
    $api_base = rtrim((string) $config['api_base'], '/');
    $timeout = (float) $config['request_timeout'];
    $players = [];
    $match = null;
    $trimmed_query = trim($query);
    $is_numeric_query = ctype_digit($trimmed_query);
    $is_steamid64_query = $is_numeric_query && strlen($trimmed_query) >= 17;
    $is_steam_profile_query = (bool) preg_match('#steamcommunity\.com/profiles/\d{17,}#i', $trimmed_query);
    $is_player_url_query = (bool) preg_match('#/players?/\d+#i', $trimmed_query);
    $is_likely_match_id = $is_numeric_query && strlen($trimmed_query) >= 8 && strlen($trimmed_query) < 17
        && (int) $trimmed_query >= 3000000000;
    $is_direct_player_query = $is_steamid64_query || $is_steam_profile_query || $is_player_url_query
        || ($is_numeric_query && !$is_likely_match_id);
    $resolved_account_id = $is_direct_player_query ? resolve_account_input($trimmed_query) : null;

    if ($query !== '') {
        // Modern Dota match IDs are large 10+ digit numbers. Do not depend on
        // OpenDota search here: if OpenDota is slow, still show an "open match" link.
        if ($is_likely_match_id) {
            $match = lookup_match_by_id($api_base, $api_base, $trimmed_query, $timeout)
                ?? ['match_id' => $trimmed_query];
        }

        // Steam profile links and 17-digit SteamID64 values are player IDs, not match IDs.
        // Convert them to Dota account_id first so queries like
        // https://steamcommunity.com/profiles/76561198288639678 and 76561198288639678 work.
        if ($resolved_account_id !== null) {
            $profile_result = fetch_json("{$api_base}/api/players/{$resolved_account_id}", $timeout);
            if ($profile_result['ok'] && is_array($profile_result['data'])) {
                $profile = $profile_result['data'];
                $players[] = [
                    'account_id' => $resolved_account_id,
                    'personaname' => $profile['profile']['personaname'] ?? $profile['personaname'] ?? ('Игрок #' . $resolved_account_id),
                    'avatarfull' => $profile['profile']['avatarfull'] ?? $profile['avatarfull'] ?? null,
                ];
            } else {
                // Even if the profile endpoint is temporarily unavailable/private, show a direct link.
                $players[] = [
                    'account_id' => $resolved_account_id,
                    'personaname' => 'Игрок #' . $resolved_account_id,
                    'avatarfull' => null,
                ];
            }
        }

        // OpenDota /search часто таймаутится. Для прямых ID и Match ID он не нужен:
        // прямой игрок уже добавлен выше, а матч показывается отдельной карточкой.
        if (!$is_direct_player_query && !$is_likely_match_id) {
            $search_result = fetch_json("{$api_base}/api/search?q=" . rawurlencode($trimmed_query), $timeout);
            if ($search_result['ok'] && is_array($search_result['data'])) {
                foreach ($search_result['data'] as $row) {
                    if (!is_array($row) || empty($row['account_id'])) {
                        continue;
                    }
                    $account_id = (string) $row['account_id'];
                    if (!isset($players[$account_id])) {
                        $players[$account_id] = $row;
                    }
                }
            }
        }

        $players = array_values(array_reduce($players, static function (array $carry, array $player): array {
            $account_id = (string) ($player['account_id'] ?? '');
            if ($account_id !== '' && !isset($carry[$account_id])) {
                $carry[$account_id] = $player;
            }
            return $carry;
        }, []));
    }

    return [
        'query' => $query,
        'search_players' => $players,
        'search_match' => $match,
    ];
}

/**
 * Resolve a match id to its data using the local parser API.
 * Returns the normalized match array, or null when the id is unknown.
 */
function lookup_match_by_id(string $local_api_base, string $public_api_base_unused, string $match_id, float $timeout): ?array
{
    $candidates = [
        "{$local_api_base}/api/match/{$match_id}?full=1",
        "{$local_api_base}/api/match/{$match_id}",
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
