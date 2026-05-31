<?php

declare(strict_types=1);

function match_tab_keys(): array
{
    return [
        'benchmarks',
        'stats',
        'damage',
        'gold',
        'items',
        'graphs',
        'abilities',
        'objectives',
        'actions',
        'teamfights',
        'fantasy',
        'chat',
        'story',
        'log',
        'cosmetics',
    ];
}

function match_tab_title(string $tab): string
{
    return [
        'benchmarks' => 'Бенчмарки',
        'stats' => 'Показатели',
        'damage' => 'Урон',
        'gold' => 'Заработок',
        'items' => 'Предметы',
        'graphs' => 'Графики',
        'abilities' => 'Способности',
        'objectives' => 'Цели',
        'actions' => 'Действия',
        'teamfights' => 'Командные бои',
        'fantasy' => 'Фэнтези',
        'chat' => 'Чат',
        'story' => 'История',
        'log' => 'Журнал',
        'cosmetics' => 'Снаряжение',
    ][$tab] ?? strtoupper($tab);
}

function render_generic_match_tab(
    string $tab,
    array $match,
    array $radiant_players,
    array $dire_players,
    array $heroes,
    array $items_by_id,
    array $abilities = [],
    array $ability_ids = []
): void {
    $players = array_merge($radiant_players, $dire_players);
    ?>
    <section class="profile-panel">
        <div class="team-header">
            <div>
                <span class="team-title"><?php echo e(match_tab_title($tab)); ?></span>
                <span class="team-subtitle"> - базовая страница без заглушки</span>
            </div>
        </div>
        <?php
        switch ($tab) {
            case 'benchmarks':
                render_benchmarks_match_tab($players, $heroes);
                break;
            case 'stats':
                render_stats_match_tab($players, $heroes);
                break;
            case 'damage':
                render_metric_match_tab($players, $heroes, [
                    'hero_damage' => 'Урон по героям',
                    'tower_damage' => 'Урон по строениям',
                    'hero_healing' => 'Лечение',
                ]);
                break;
            case 'gold':
                render_metric_match_tab($players, $heroes, [
                    'total_gold' => 'Net worth',
                    'gold_per_min' => 'GPM',
                    'last_hits' => 'Last hits',
                    'denies' => 'Denies',
                ]);
                break;
            case 'items':
                render_items_match_tab($players, $heroes, $items_by_id);
                break;
            case 'graphs':
                render_graphs_match_tab($players, $heroes);
                break;
            case 'abilities':
                render_abilities_match_tab($players, $heroes, $abilities, $ability_ids);
                break;
            case 'objectives':
                render_events_match_tab($match['objectives'] ?? [], 'Целей в данных матча нет.');
                break;
            case 'actions':
                render_actions_match_tab($players, $heroes);
                break;
            case 'teamfights':
                render_teamfights_match_tab($match['teamfights'] ?? []);
                break;
            case 'fantasy':
                render_fantasy_match_tab($players, $heroes);
                break;
            case 'chat':
                render_events_match_tab($match['chat'] ?? [], 'Чат матча недоступен.');
                break;
            case 'story':
                render_events_match_tab(array_merge($match['objectives'] ?? [], $match['teamfights'] ?? [], $match['chat'] ?? []), 'Недостаточно событий, чтобы собрать историю матча.');
                break;
            case 'log':
                render_events_match_tab(array_merge($match['objectives'] ?? [], $match['chat'] ?? []), 'Журнал событий недоступен.');
                break;
            case 'cosmetics':
                render_cosmetics_match_tab($players, $heroes);
                break;
            default:
                render_match_tab_empty('Этот раздел пока не настроен.');
        }
        ?>
    </section>
    <?php
}

function render_player_match_cells(array $player, array $heroes): void
{
    $hero_img = get_hero_img((int) ($player['hero_id'] ?? 0), $heroes);
    $name = (string) ($player['personaname'] ?? $player['name'] ?? 'Аноним');
    ?>
    <td>
        <div class="player-cell compact">
            <?php if ($hero_img): ?><img class="hero-img" src="<?php echo e($hero_img); ?>" alt=""><?php endif; ?>
            <div class="player-info">
                <?php if (!empty($player['account_id'])): ?>
                    <a class="player-name" href="<?php echo e(player_url($player['account_id'])); ?>"><?php echo e($name); ?></a>
                <?php else: ?>
                    <span class="player-name"><?php echo e($name); ?></span>
                <?php endif; ?>
                <span class="player-rank"><?php echo e(get_hero_name((int) ($player['hero_id'] ?? 0), $heroes)); ?></span>
            </div>
        </div>
    </td>
    <?php
}

function player_match_team_label(array $player): string
{
    $is_radiant = isset($player['isRadiant']) ? (bool) $player['isRadiant'] : ((int) ($player['player_slot'] ?? 0) < 128);
    return $is_radiant ? 'Свет' : 'Тьма';
}

function player_match_team_class(array $player): string
{
    return player_match_team_label($player) === 'Свет' ? 'radiant-title' : 'dire-title';
}

function player_match_kda(array $player): float
{
    $deaths = max(1, (int) ($player['deaths'] ?? 0));
    return ((int) ($player['kills'] ?? 0) + (int) ($player['assists'] ?? 0)) / $deaths;
}

function render_benchmarks_match_tab(array $players, array $heroes): void
{
    $metrics = [
        'gold_per_min' => 'Лучший GPM',
        'xp_per_min' => 'Лучший XPM',
        'hero_damage' => 'Больше всего урона',
        'tower_damage' => 'Больше всего по строениям',
        'last_hits' => 'Больше всего CS',
        'kda' => 'Лучший KDA',
    ];
    ?>
    <table class="overview-table">
        <thead><tr><th>Бенчмарк</th><th>Игрок</th><th class="col-center">Значение</th></tr></thead>
        <tbody>
        <?php foreach ($metrics as $key => $label): ?>
            <?php
            $ranked = $players;
            usort($ranked, static function (array $a, array $b) use ($key): int {
                $a_value = $key === 'kda' ? player_match_kda($a) : (float) ($a[$key] ?? 0);
                $b_value = $key === 'kda' ? player_match_kda($b) : (float) ($b[$key] ?? 0);
                return $b_value <=> $a_value;
            });
            $top = $ranked[0] ?? [];
            ?>
            <tr>
                <td><?php echo e($label); ?></td>
                <?php render_player_match_cells($top, $heroes); ?>
                <td class="col-center col-gold"><?php echo e($key === 'kda' ? number_format(player_match_kda($top), 2) : format_stat($top[$key] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_stats_match_tab(array $players, array $heroes): void
{
    ?>
    <table class="overview-table">
        <thead><tr><th>Игрок</th><th class="col-center">Команда</th><th class="col-center">K/D/A</th><th class="col-center">LH/DN</th><th class="col-center">GPM/XPM</th><th class="col-center">Уровень</th><th class="col-center">Net worth</th></tr></thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td class="col-center"><?php echo e(($player['kills'] ?? 0) . '/' . ($player['deaths'] ?? 0) . '/' . ($player['assists'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(($player['last_hits'] ?? 0) . '/' . ($player['denies'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e(($player['gold_per_min'] ?? 0) . '/' . ($player['xp_per_min'] ?? 0)); ?></td>
                <td class="col-center"><?php echo e($player['level'] ?? 0); ?></td>
                <td class="col-center col-gold"><?php echo e(format_stat($player['total_gold'] ?? 0)); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_metric_match_tab(array $players, array $heroes, array $columns): void
{
    $first_key = array_key_first($columns);
    usort($players, static fn (array $a, array $b): int => ((int) ($b[$first_key] ?? 0)) <=> ((int) ($a[$first_key] ?? 0)));
    ?>
    <table class="overview-table">
        <thead><tr><th>Игрок</th><th class="col-center">Команда</th><?php foreach ($columns as $label): ?><th class="col-center"><?php echo e($label); ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <?php foreach ($columns as $key => $label): ?><td class="col-center"><?php echo e(format_stat($player[$key] ?? 0)); ?></td><?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_items_match_tab(array $players, array $heroes, array $items_by_id): void
{
    ?>
    <table class="overview-table">
        <thead><tr><th>Игрок</th><th class="col-center">Команда</th><th>Предметы</th><th class="col-center">Aghanim</th></tr></thead>
        <tbody>
        <?php foreach ($players as $player): ?>
            <tr>
                <?php render_player_match_cells($player, $heroes); ?>
                <td class="col-center <?php echo e(player_match_team_class($player)); ?>"><?php echo e(player_match_team_label($player)); ?></td>
                <td><?php render_items_cell($player, $items_by_id); ?></td>
                <td class="col-center"><?php echo !empty($player['aghanims_scepter']) ? 'Scepter ' : ''; ?><?php echo !empty($player['aghanims_shard']) ? 'Shard' : ''; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function render_graphs_match_tab(array $players, array $heroes): void
{
    render_metric_match_tab($players, $heroes, [
        'total_gold' => 'Net worth',
        'hero_damage' => 'Урон',
        'xp_per_min' => 'XPM',
    ]);
}

function render_actions_match_tab(array $players, array $heroes): void
{
    $rows = [];
    foreach ($players as $player) {
        $actions = is_array($player['actions_per_min'] ?? null) ? array_sum($player['actions_per_min']) : 0;
        $rows[] = [$player, (int) $actions];
    }
    usort($rows, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
    ?>
    <table class="overview-table"><thead><tr><th>Игрок</th><th class="col-center">Actions score</th></tr></thead><tbody>
    <?php foreach ($rows as [$player, $actions]): ?><tr><?php render_player_match_cells($player, $heroes); ?><td class="col-center"><?php echo e($actions > 0 ? format_stat($actions) : '-'); ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php
}

function render_teamfights_match_tab(mixed $teamfights): void
{
    if (!is_array($teamfights) || $teamfights === []) {
        render_match_tab_empty('Teamfight-данные недоступны для этого матча.');
        return;
    }
    ?>
    <table class="overview-table"><thead><tr><th class="col-center">Время</th><th class="col-center">Длительность</th><th>Кратко</th></tr></thead><tbody>
    <?php foreach ($teamfights as $fight): ?>
        <tr><td class="col-center"><?php echo e(format_match_tab_time((int) ($fight['start'] ?? 0))); ?></td><td class="col-center"><?php echo e(format_match_tab_time((int) (($fight['end'] ?? 0) - ($fight['start'] ?? 0)))); ?></td><td><?php echo e(count($fight['players'] ?? []) . ' игроков в событии'); ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
}

function render_fantasy_match_tab(array $players, array $heroes): void
{
    usort($players, static fn (array $a, array $b): int => fantasy_match_score($b) <=> fantasy_match_score($a));
    ?>
    <table class="overview-table"><thead><tr><th>Игрок</th><th class="col-center">Fantasy score</th><th class="col-center">Основа</th></tr></thead><tbody>
    <?php foreach ($players as $player): ?><tr><?php render_player_match_cells($player, $heroes); ?><td class="col-center col-gold"><?php echo e((string) fantasy_match_score($player)); ?></td><td class="col-center"><?php echo e(($player['kills'] ?? 0) . '/' . ($player['deaths'] ?? 0) . '/' . ($player['assists'] ?? 0)); ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php
}

function fantasy_match_score(array $player): int
{
    return (int) round(($player['kills'] ?? 0) * 3 + ($player['assists'] ?? 0) * 2 - ($player['deaths'] ?? 0) + ($player['last_hits'] ?? 0) / 10 + ($player['gold_per_min'] ?? 0) / 20);
}

function render_cosmetics_match_tab(array $players, array $heroes): void
{
    foreach ($players as $player) {
        if (!empty($player['cosmetics'])) {
            render_stats_match_tab($players, $heroes);
            return;
        }
    }
    render_match_tab_empty('Данные о косметических предметах не пришли из API.');
}

function render_events_match_tab(mixed $events, string $empty_message): void
{
    if (!is_array($events) || $events === []) {
        render_match_tab_empty($empty_message);
        return;
    }
    usort($events, static fn (array $a, array $b): int => ((int) ($a['time'] ?? $a['start'] ?? 0)) <=> ((int) ($b['time'] ?? $b['start'] ?? 0)));
    ?>
    <table class="overview-table"><thead><tr><th class="col-center">Время</th><th>Тип</th><th>Данные</th></tr></thead><tbody>
    <?php foreach (array_slice($events, 0, 80) as $event): ?>
        <tr><td class="col-center"><?php echo e(format_match_tab_time((int) ($event['time'] ?? $event['start'] ?? 0))); ?></td><td><?php echo e((string) ($event['type'] ?? $event['key'] ?? 'event')); ?></td><td><code><?php echo e(json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></code></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php
}

function format_match_tab_time(int $seconds): string
{
    $sign = $seconds < 0 ? '-' : '';
    $seconds = abs($seconds);
    return $sign . floor($seconds / 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
}

function render_match_tab_empty(string $message): void
{
    ?><div class="empty-state"><span><?php echo e($message); ?></span></div><?php
}
