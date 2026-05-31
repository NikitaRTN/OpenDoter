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

/**
 * Shared panel wrapper used by every match-tab view. Each tab lives in its own
 * file under src/views/ (the view) and src/components/tabs/ (the renderer) and
 * renders its body through this helper so the chrome stays consistent.
 */
function render_match_tab_section(string $title, string $subtitle, callable $body): void
{
    ?>
    <section class="mb-5 rounded-lg border border-line bg-panel p-4">
        <div class="mb-3 flex items-center justify-between border-b border-line pb-1.5">
            <div>
                <span class="text-base font-bold uppercase tracking-wide text-main"><?php echo e($title); ?></span>
                <?php if ($subtitle !== ''): ?><span class="text-muted"> - <?php echo e($subtitle); ?></span><?php endif; ?>
            </div>
        </div>
        <?php $body(); ?>
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

function fantasy_match_score(array $player): int
{
    return (int) round(($player['kills'] ?? 0) * 3 + ($player['assists'] ?? 0) * 2 - ($player['deaths'] ?? 0) + ($player['last_hits'] ?? 0) / 10 + ($player['gold_per_min'] ?? 0) / 20);
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
