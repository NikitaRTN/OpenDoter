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
        'compare',
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
        'compare' => 'Сравнение',
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

/**
 * Сопоставление событий чата/таймлайна с реальными игроками.
 * `player_slot` — абсолютный слот (0..4 / 128..132), `slot` — позиция 0..9.
 */
function match_build_slot_lookup(array $radiant_players, array $dire_players): array
{
    $by_slot = [];
    $by_pos = [];
    $pos = 0;
    foreach (array_merge($radiant_players, $dire_players) as $player) {
        if (is_array($player)) {
            if (isset($player['player_slot'])) {
                $by_slot[(int) $player['player_slot']] = $player;
            }
            $by_pos[$pos] = $player;
        }
        $pos++;
    }
    return ['by_slot' => $by_slot, 'by_pos' => $by_pos];
}

function match_resolve_event_player(array $event, array $lookup, array $heroes): ?array
{
    $player = null;
    if (isset($event['player_slot'])) {
        $player = $lookup['by_slot'][(int) $event['player_slot']] ?? null;
    }
    if ($player === null && isset($event['slot'])) {
        $player = $lookup['by_pos'][(int) $event['slot']] ?? null;
    }
    if (!is_array($player)) {
        return null;
    }
    $hero_id = (int) ($player['hero_id'] ?? 0);
    $is_radiant = isset($player['isRadiant'])
        ? (bool) $player['isRadiant']
        : ((int) ($player['player_slot'] ?? 0) < 128);
    return [
        'hero_id' => $hero_id,
        'hero_name' => get_hero_name($hero_id, $heroes),
        'hero_img' => get_hero_img($hero_id, $heroes),
        'persona' => (string) ($player['personaname'] ?? $player['name'] ?? get_hero_name($hero_id, $heroes)),
        'account_id' => (int) ($player['account_id'] ?? 0),
        'team' => $is_radiant ? 'radiant' : 'dire',
    ];
}

function match_team_label_from_num(mixed $team): string
{
    $team = (int) $team;
    if ($team === 2) {
        return 'Свет';
    }
    if ($team === 3) {
        return 'Тьма';
    }
    return '';
}

/**
 * Читаемый чат: время · игрок (с иконкой героя) · текст сообщения.
 */
function render_chat_messages_tab(array $chat, array $lookup, array $heroes): void
{
    if ($chat === []) {
        render_match_tab_empty('Чат матча недоступен.');
        return;
    }
    usort($chat, static fn (array $a, array $b): int => ((int) ($a['time'] ?? 0)) <=> ((int) ($b['time'] ?? 0)));
    ?>
    <div class="flex flex-col gap-1.5">
        <?php foreach (array_slice($chat, 0, 250) as $event): ?>
            <?php
            $who = match_resolve_event_player($event, $lookup, $heroes);
            $type = (string) ($event['type'] ?? '');
            $is_wheel = $type === 'chatwheel';
            $text = $is_wheel
                ? 'Фраза колеса чата #' . (string) ($event['key'] ?? '?')
                : (string) ($event['key'] ?? '');
            $team_class = $who === null ? 'text-muted' : ($who['team'] === 'radiant' ? 'text-radiant' : 'text-dire');
            ?>
            <div class="flex items-start gap-2 rounded border border-line bg-black/10 px-2 py-1.5">
                <span class="w-10 shrink-0 text-xs tabular-nums text-muted"><?php echo e(format_match_tab_time((int) ($event['time'] ?? 0))); ?></span>
                <?php if ($who !== null && $who['hero_img']): ?>
                    <img class="chat-hero-img" src="<?php echo e($who['hero_img']); ?>" alt="" title="<?php echo e($who['hero_name']); ?>">
                <?php endif; ?>
                <div class="min-w-0">
                    <?php if ($who !== null): ?>
                        <span class="font-semibold <?php echo e($team_class); ?>"><?php echo e($who['persona']); ?></span>
                        <span class="text-xs text-muted"> · <?php echo e($who['hero_name']); ?></span>
                    <?php endif; ?>
                    <div class="break-words <?php echo $is_wheel ? 'italic text-muted' : 'text-main'; ?>"><?php echo e($text); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Превращает сырое событие в читаемую строку таймлайна.
 * Возвращает: ['time' => int, 'icon' => string, 'text' => string, 'team' => 'radiant'|'dire'|''].
 */
function humanize_match_event(array $event, array $lookup, array $heroes): array
{
    // Тимфайты приходят без поля type, но с массивом players и start.
    if (!isset($event['type']) && isset($event['players']) && is_array($event['players'])) {
        $deaths = (int) ($event['deaths'] ?? 0);
        return [
            'time' => (int) ($event['start'] ?? 0),
            'icon' => '⚔️',
            'text' => 'Командный бой' . ($deaths > 0 ? ' · погибло героев: ' . $deaths : ''),
            'team' => '',
        ];
    }

    $type = (string) ($event['type'] ?? $event['key'] ?? 'event');
    $who = match_resolve_event_player($event, $lookup, $heroes);
    $actor = $who !== null ? $who['hero_name'] : '';
    $team_side = $who !== null ? $who['team'] : '';
    $team_num_label = match_team_label_from_num($event['team'] ?? null);
    $time = (int) ($event['time'] ?? $event['start'] ?? 0);

    $with_actor = static fn (string $base): string => $actor !== '' ? $base . ' — ' . $actor : $base;
    $with_team = static fn (string $base): string => $team_num_label !== '' ? $base . ' (' . $team_num_label . ')' : $base;

    switch ($type) {
        case 'CHAT_MESSAGE_FIRSTBLOOD':
            return ['time' => $time, 'icon' => '🩸', 'text' => $with_actor('Первая кровь'), 'team' => $team_side];
        case 'CHAT_MESSAGE_COURIER_LOST':
            return ['time' => $time, 'icon' => '📦', 'text' => $with_team('Уничтожен курьер'), 'team' => ''];
        case 'CHAT_MESSAGE_COURIER_RESPAWNED':
            return ['time' => $time, 'icon' => '📦', 'text' => $with_team('Курьер возродился'), 'team' => ''];
        case 'CHAT_MESSAGE_ROSHAN_KILL':
            return ['time' => $time, 'icon' => '🐉', 'text' => $with_team('Повержен Рошан'), 'team' => ''];
        case 'CHAT_MESSAGE_AEGIS':
            return ['time' => $time, 'icon' => '🟡', 'text' => $with_actor('Подобран Аегис'), 'team' => $team_side];
        case 'CHAT_MESSAGE_AEGIS_STOLEN':
            return ['time' => $time, 'icon' => '🟡', 'text' => $with_actor('Аегис украден'), 'team' => $team_side];
        case 'CHAT_MESSAGE_DENIED_AEGIS':
            return ['time' => $time, 'icon' => '🟡', 'text' => $with_actor('Аегис уничтожен'), 'team' => $team_side];
        case 'CHAT_MESSAGE_RUNE_PICKUP':
        case 'CHAT_MESSAGE_RUNE_BOTTLE':
            return ['time' => $time, 'icon' => '🔮', 'text' => $with_actor('Подобрана руна'), 'team' => $team_side];
        case 'CHAT_MESSAGE_TOWER_KILL':
            return ['time' => $time, 'icon' => '🏰', 'text' => $with_team('Разрушена башня'), 'team' => ''];
        case 'CHAT_MESSAGE_TOWER_DENY':
            return ['time' => $time, 'icon' => '🏰', 'text' => $with_team('Башня уничтожена своими'), 'team' => ''];
        case 'CHAT_MESSAGE_BARRACKS_KILL':
            return ['time' => $time, 'icon' => '🏯', 'text' => 'Разрушены казармы', 'team' => ''];
        case 'building_kill':
            return ['time' => $time, 'icon' => '🏛️', 'text' => match_humanize_building_name((string) ($event['key'] ?? '')), 'team' => ''];
        case 'CHAT_MESSAGE_SUPER_CREEPS':
            return ['time' => $time, 'icon' => '👾', 'text' => $with_team('Мега-крипы'), 'team' => ''];
        case 'chat':
            return ['time' => $time, 'icon' => '💬', 'text' => ($actor !== '' ? $actor . ': ' : '') . (string) ($event['key'] ?? ''), 'team' => $team_side];
        case 'chatwheel':
            return ['time' => $time, 'icon' => '🗣️', 'text' => ($actor !== '' ? $actor . ': ' : '') . 'фраза колеса чата', 'team' => $team_side];
        default:
            $clean = ucfirst(strtolower(str_replace(['CHAT_MESSAGE_', '_'], ['', ' '], $type)));
            return ['time' => $time, 'icon' => '•', 'text' => $with_actor($clean), 'team' => $team_side];
    }
}

function match_humanize_building_name(string $key): string
{
    if ($key === '') {
        return 'Уничтожено строение';
    }
    $side = str_contains($key, 'goodguys') ? 'Света' : (str_contains($key, 'badguys') ? 'Тьмы' : '');
    if (str_contains($key, 'fort')) {
        $what = 'Трон';
    } elseif (str_contains($key, 'rax') || str_contains($key, 'melee') || str_contains($key, 'range')) {
        $what = 'Казармы';
    } elseif (str_contains($key, 'tower')) {
        $what = 'Башня';
    } else {
        $what = 'Строение';
    }
    return 'Разрушено: ' . $what . ($side !== '' ? ' ' . $side : '');
}

/**
 * Читаемая хронология (История / Журнал).
 */
function render_timeline_events_tab(array $events, array $lookup, array $heroes, string $empty_message): void
{
    if ($events === []) {
        render_match_tab_empty($empty_message);
        return;
    }
    $rows = [];
    foreach ($events as $event) {
        if (is_array($event)) {
            $rows[] = humanize_match_event($event, $lookup, $heroes);
        }
    }
    usort($rows, static fn (array $a, array $b): int => $a['time'] <=> $b['time']);
    ?>
    <div class="flex flex-col">
        <?php foreach ($rows as $row): ?>
            <?php
            $accent = $row['team'] === 'radiant' ? 'border-l-radiant' : ($row['team'] === 'dire' ? 'border-l-dire' : 'border-l-line');
            ?>
            <div class="flex items-center gap-2 border-l-2 <?php echo e($accent); ?> py-1 pl-3">
                <span class="w-10 shrink-0 text-xs tabular-nums text-muted"><?php echo e(format_match_tab_time((int) $row['time'])); ?></span>
                <span class="w-5 shrink-0 text-center"><?php echo $row['icon']; ?></span>
                <span class="text-main"><?php echo e($row['text']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}
