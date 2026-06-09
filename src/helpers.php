<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_stat(mixed $value): string
{
    if ($value === null || $value === 0 || $value === '0' || $value === '-') {
        return '-';
    }

    $number = (float) $value;
    if ($number >= 1000) {
        return number_format($number / 1000, 1, '.', '') . 'k';
    }

    return (string) $value;
}

function get_rank_title(mixed $tier): string
{
    $tier = (int) $tier;
    if ($tier <= 0) {
        return 'Не откалиброван';
    }

    $ranks = [
        1 => 'Рекрут',
        2 => 'Страж',
        3 => 'Рыцарь',
        4 => 'Герой',
        5 => 'Легенда',
        6 => 'Властелин',
        7 => 'Божество',
        8 => 'Титан',
    ];

    $major = intdiv($tier, 10);
    $minor = $tier % 10;

    if ($major >= 8) {
        return 'Титан';
    }

    return ($ranks[$major] ?? 'Ранг') . " [$minor]";
}

function get_hero_img(int $hero_id, array $heroes): ?string
{
    if (!isset($heroes[$hero_id]) || empty($heroes[$hero_id]['name'])) {
        return null;
    }

    $system_name = str_replace('npc_dota_hero_', '', (string) $heroes[$hero_id]['name']);
    return "https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/{$system_name}.png";
}

function get_hero_name(int $hero_id, array $heroes): string
{
    if (!isset($heroes[$hero_id])) {
        return 'Неизвестный герой';
    }

    return (string) ($heroes[$hero_id]['localized_name'] ?? $heroes[$hero_id]['name'] ?? 'Неизвестный герой');
}

function get_item_img(int $item_id, array $items_by_id): ?string
{
    if ($item_id <= 0 || !isset($items_by_id[$item_id])) {
        return null;
    }

    $item = $items_by_id[$item_id];
    if (!empty($item['img'])) {
        $img = (string) $item['img'];
        if (str_starts_with($img, 'http')) {
            return $img;
        }

        return 'https://cdn.cloudflare.steamstatic.com' . explode('?', $img)[0];
    }

    $system_name = str_replace('item_', '', (string) ($item['name'] ?? ''));
    if ($system_name === '') {
        return null;
    }

    return "https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/items/{$system_name}.png";
}

function get_item_title(int $item_id, array $items_by_id): string
{
    if ($item_id <= 0) {
        return 'Пустой слот';
    }

    if (!isset($items_by_id[$item_id])) {
        return "Неизвестный предмет #{$item_id}";
    }

    if (!empty($items_by_id[$item_id]['dname'])) {
        return (string) $items_by_id[$item_id]['dname'];
    }

    $system_name = str_replace('item_', '', (string) ($items_by_id[$item_id]['name'] ?? 'item'));
    return ucwords(str_replace('_', ' ', $system_name));
}

/**
 * Resolve a numeric ability id (as found in ability_upgrades_arr) to its
 * internal ability name using the OpenDota ability_ids constant.
 */
function ability_name_from_id(int $ability_id, array $ability_ids): ?string
{
    if ($ability_id <= 0 || $ability_ids === []) {
        return null;
    }

    // json_decode turns numeric string keys into int keys, so both work.
    return $ability_ids[$ability_id] ?? ($ability_ids[(string) $ability_id] ?? null);
}

function get_ability_img(int $ability_id, array $abilities, array $ability_ids): ?string
{
    $name = ability_name_from_id($ability_id, $ability_ids);
    if ($name === null || !isset($abilities[$name]) || !is_array($abilities[$name])) {
        return null;
    }

    $img = (string) ($abilities[$name]['img'] ?? '');
    if ($img === '') {
        return null;
    }

    if (str_starts_with($img, 'http')) {
        return explode('?', $img)[0];
    }

    return 'https://cdn.cloudflare.steamstatic.com' . explode('?', $img)[0];
}

function get_ability_name(int $ability_id, array $abilities, array $ability_ids): string
{
    $name = ability_name_from_id($ability_id, $ability_ids);
    if ($name === null) {
        return 'Способность #' . $ability_id;
    }

    if (isset($abilities[$name]['dname']) && $abilities[$name]['dname'] !== '') {
        return (string) $abilities[$name]['dname'];
    }

    return ucwords(str_replace('_', ' ', $name));
}

function is_talent_ability(int $ability_id, array $abilities, array $ability_ids): bool
{
    $name = ability_name_from_id($ability_id, $ability_ids);
    return $name !== null && str_starts_with($name, 'special_bonus');
}

function app_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '/' ? '/' : $path;
}

function match_url(string $match_id, string $tab = 'overview'): string
{
    return app_url("matches/{$match_id}/{$tab}");
}

function player_url(mixed $account_id): string
{
    return app_url('players/' . (int) $account_id);
}

function asset_url(string $path): string
{
    return app_url($path);
}


/**
 * Account id текущего «залогиненного» игрока (из cookie) или null.
 * Полноценный Steam OpenID требует секретов и внешней сети, поэтому
 * вход облегчён: пользователь указывает свой account id / Steam, мы храним его в cookie.
 */
function current_account_id(): ?string
{
    $raw = (string) ($_COOKIE['opendoter_uid'] ?? '');
    return ctype_digit($raw) ? $raw : null;
}

function steamid64_to_account_id(string $steamid64): ?string
{
    if (!ctype_digit($steamid64) || strlen($steamid64) < 17) {
        return null;
    }
    if (function_exists('bcsub')) {
        $account = bcsub($steamid64, '76561197960265728');
    } else {
        $account = (string) (((int) $steamid64) - 76561197960265728);
    }
    return ctype_digit($account) && (int) $account > 0 ? $account : null;
}

/**
 * Привести произвольный ввод (account id, Steam ID64 или ссылку на профиль)
 * к Dota account_id. Возвращает null, если распознать не удалось.
 */
function resolve_account_input(string $input): ?string
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (preg_match('#steamcommunity\.com/profiles/(\d{17,})#', $input, $m)) {
        return steamid64_to_account_id($m[1]);
    }
    if (preg_match('#/players?/(\d+)#', $input, $m)) {
        return $m[1];
    }
    if (ctype_digit($input) && strlen($input) >= 17) {
        return steamid64_to_account_id($input);
    }
    if (ctype_digit($input)) {
        return $input;
    }
    return null;
}

function rank_medal_parts(mixed $tier): array
{
    $tier = (int) $tier;
    if ($tier <= 0) {
        return ['major' => 0, 'stars' => 0];
    }
    return ['major' => intdiv($tier, 10), 'stars' => $tier % 10];
}

function rank_medal_img(int $major): string
{
    $major = max(0, min(8, $major));
    return 'https://www.opendota.com/assets/images/dota2/rank_icons/rank_icon_' . $major . '.png';
}

function rank_star_img(int $stars): ?string
{
    if ($stars < 1 || $stars > 7) {
        return null;
    }
    return 'https://www.opendota.com/assets/images/dota2/rank_icons/rank_star_' . $stars . '.png';
}

/**
 * Примерный MMR-диапазон по медали Dota 2.
 * Это не точный текущий MMR игрока, а публично выводимый диапазон ранга.
 */
function rank_tier_mmr_range(int $tier): ?array
{
    $major = intdiv($tier, 10);
    $stars = $tier % 10;
    if ($major <= 0) {
        return null;
    }

    // Приблизительные шаги рангов: 5 звёзд по ~154 MMR.
    $rank_bases = [
        1 => 0,     // Рекрут / Herald
        2 => 770,   // Страж / Guardian
        3 => 1540,  // Рыцарь / Crusader
        4 => 2310,  // Герой / Archon
        5 => 3080,  // Легенда / Legend
        6 => 3850,  // Властелин / Ancient
        7 => 4620,  // Божество / Divine
        8 => 5420,  // Титан / Immortal+
    ];

    if ($major >= 8) {
        return ['min' => 5420, 'max' => null];
    }
    if (!isset($rank_bases[$major])) {
        return null;
    }

    $star = max(1, min(5, $stars ?: 1));
    $min = $rank_bases[$major] + (($star - 1) * 154);
    $max = $rank_bases[$major] + ($star * 154) - 1;
    return ['min' => $min, 'max' => $max];
}

function format_mmr_range(array $range): string
{
    if (($range['max'] ?? null) === null) {
        return (string) $range['min'] . '+';
    }
    return $range['min'] . '–' . $range['max'];
}

function game_mode_name(int $mode): string
{
    $modes = [
        0 => 'Неизвестно', 1 => 'All Pick', 2 => 'Captains Mode', 3 => 'Random Draft',
        4 => 'Single Draft', 5 => 'All Random', 12 => 'Least Played', 16 => 'Captains Draft',
        18 => 'Ability Draft', 22 => 'Ранговый All Pick', 23 => 'Турбо',
    ];
    return $modes[$mode] ?? ('Режим #' . $mode);
}

function lobby_type_name(int $lobby): string
{
    $lobbies = [
        0 => 'Обычная', 1 => 'Практика', 5 => 'Командный', 6 => 'Турнир',
        7 => 'Рейтинговая', 8 => '1в1 мид', 9 => 'Боты',
    ];
    return $lobbies[$lobby] ?? '';
}

function kda_ratio(int $kills, int $deaths, int $assists): float
{
    return round(($kills + $assists) / max(1, $deaths), 2);
}
