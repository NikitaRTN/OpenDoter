<?php

declare(strict_types=1);

function render_player_profile(array $profile, array $matches, array $heroes, array $stats = []): void
{
    $data = is_array($profile['profile'] ?? null) ? $profile['profile'] : [];
    $account_id = (string) ($data['account_id'] ?? '');
    $name = (string) ($data['personaname'] ?? 'Игрок');
    $avatar = (string) ($data['avatarfull'] ?? $data['avatarmedium'] ?? '');
    $tier = (int) ($profile['rank_tier'] ?? 0);
    $medal = rank_medal_parts($tier);
    $current_mmr = $profile['competitive_rank'] ?? $profile['solo_competitive_rank'] ?? null;
    $rank_mmr_range = rank_tier_mmr_range($tier);
    $estimated_mmr = $profile['computed_mmr'] ?? $profile['mmr_estimate']['estimate'] ?? null;
    $is_plus = !empty($data['plus']);
    $aliases = is_array($profile['aliases'] ?? null) ? $profile['aliases'] : [];
    $is_me = current_account_id() !== null && current_account_id() === $account_id;

    $st = $stats;
    $total_wins = (int) ($st['total_wins'] ?? 0);
    $total_losses = (int) ($st['total_losses'] ?? 0);
    $winrate = (float) ($st['winrate'] ?? 0);
    $matches_page = max(1, (int) ($st['page'] ?? 1));
    $has_next_page = !empty($st['has_next_page']);
    $include_turbo = !empty($st['include_turbo']);
    $profile_base_url = player_url($account_id);
    $profile_query = $include_turbo ? 'turbo=1' : '';
    $turbo_toggle_url = $include_turbo ? $profile_base_url : $profile_base_url . '?turbo=1';
    $prev_query = http_build_query(array_filter([
        'page' => $matches_page - 1 > 1 ? $matches_page - 1 : null,
        'turbo' => $include_turbo ? 1 : null,
    ], static fn ($value): bool => $value !== null));
    $next_query = http_build_query(array_filter([
        'page' => $matches_page + 1,
        'turbo' => $include_turbo ? 1 : null,
    ], static fn ($value): bool => $value !== null));
    $prev_page_url = $profile_base_url . ($prev_query !== '' ? '?' . $prev_query : '');
    $next_page_url = $profile_base_url . ($next_query !== '' ? '?' . $next_query : '');
    ?>
    <script type="application/json" id="od-track-player">
    <?php echo json_encode([
        'type' => 'player',
        'account_id' => $account_id,
        'name' => $name,
        'avatar' => $avatar,
        'rank' => get_rank_title($tier),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>
    </script>

    <section class="profile-banner relative mb-5 overflow-hidden rounded-xl border border-line">
        <div class="profile-banner-glow"></div>
        <div class="relative flex flex-col gap-5 p-5 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
                <?php if ($avatar !== ''): ?>
                    <img class="h-[104px] w-[104px] rounded-xl border border-line object-cover shadow-card" src="<?php echo e($avatar); ?>" alt="">
                <?php else: ?>
                    <div class="flex h-[104px] w-[104px] items-center justify-center rounded-xl border border-line bg-black/30 text-3xl font-bold text-muted"><?php echo e(mb_substr($name, 0, 1)); ?></div>
                <?php endif; ?>
                <?php if ($is_plus): ?><span class="absolute -bottom-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-gold px-2 py-0.5 text-[10px] font-bold uppercase text-black shadow-soft">Plus</span><?php endif; ?>
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="m-0 truncate text-[26px] font-bold leading-tight text-main"><?php echo e($name); ?></h1>
                    <?php if ($is_me): ?><span class="rounded-full border border-radiant/50 bg-radiant/10 px-2.5 py-0.5 text-[11px] font-bold uppercase text-radiant">Это вы</span><?php endif; ?>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-muted">
                    <span>ID: <span class="text-main"><?php echo e($account_id); ?></span></span>
                    <span><?php echo e(get_rank_title($tier)); ?></span>
                    <?php if (!empty($profile['leaderboard_rank'])): ?><span class="text-gold">Лидерборд #<?php echo e($profile['leaderboard_rank']); ?></span><?php endif; ?>
                    <?php if (!empty($data['loccountrycode'])): ?><span><?php echo e($data['loccountrycode']); ?></span><?php endif; ?>
                </div>
                <?php if (count($aliases) > 1): ?>
                    <div class="mt-2 text-[12px] text-muted">Бывшие ники: <?php
                        $names = [];
                        foreach (array_slice($aliases, 1, 4) as $al) {
                            if (!empty($al['personaname'])) { $names[] = e($al['personaname']); }
                        }
                        echo implode(', ', $names);
                    ?></div>
                <?php endif; ?>
                <div class="mt-3.5 flex flex-wrap gap-2">
                    <?php if (!empty($data['profileurl'])): ?>
                        <a class="inline-flex items-center gap-1.5 rounded-md border border-link bg-link/10 px-3.5 py-2 text-xs font-bold uppercase text-link transition-colors hover:bg-link/20 hover:text-link-hover" href="<?php echo e($data['profileurl']); ?>" target="_blank" rel="noreferrer">Steam</a>
                    <?php endif; ?>
                    <a class="inline-flex items-center gap-1.5 rounded-md border border-link bg-link/10 px-3.5 py-2 text-xs font-bold uppercase text-link transition-colors hover:bg-link/20 hover:text-link-hover" href="https://www.opendota.com/players/<?php echo e($account_id); ?>" target="_blank" rel="noreferrer">OpenDota</a>
                    <a class="turbo-toggle <?php echo $include_turbo ? 'is-on' : ''; ?>" href="<?php echo e($turbo_toggle_url); ?>" aria-pressed="<?php echo $include_turbo ? 'true' : 'false'; ?>">
                        <span class="turbo-toggle-track"><span class="turbo-toggle-thumb"></span></span>
                        <span class="turbo-toggle-text">Turbo матчи</span>
                        <span class="turbo-toggle-state"><?php echo $include_turbo ? 'Вкл' : 'Выкл'; ?></span>
                    </a>
                    <?php if (!$is_me && $account_id !== ''): ?>
                        <a class="inline-flex items-center gap-1.5 rounded-md border border-radiant/50 bg-radiant/10 px-3.5 py-2 text-xs font-bold uppercase text-radiant transition-colors hover:bg-radiant/20" href="<?php echo e(app_url('login?account=' . urlencode($account_id))); ?>">Войти как этот игрок</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-center gap-2 rounded-xl border border-line bg-black/25 px-6 py-4">
                <div class="rank-medal">
                    <img class="rank-medal-img" src="<?php echo e(rank_medal_img((int) $medal['major'])); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                    <?php $star = rank_star_img((int) $medal['stars']); ?>
                    <?php if ($star): ?><img class="rank-medal-stars" src="<?php echo e($star); ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php endif; ?>
                </div>
                <span class="text-center text-[12px] font-bold uppercase tracking-wide text-main"><?php echo e(get_rank_title($tier)); ?></span>
                <div class="mmr-stack">
                    <?php if ($current_mmr): ?>
                        <span class="mmr-line mmr-current"><span>Текущий MMR</span><strong><?php echo e((int) $current_mmr); ?></strong></span>
                    <?php elseif ($rank_mmr_range !== null): ?>
                        <span class="mmr-line mmr-current"><span>MMR по рангу</span><strong><?php echo e(format_mmr_range($rank_mmr_range)); ?></strong></span>
                    <?php else: ?>
                        <span class="mmr-line"><span>MMR по рангу</span><strong>нет ранга</strong></span>
                    <?php endif; ?>
                    <?php if ($estimated_mmr): ?>
                        <span class="mmr-line mmr-estimate"><span>Оценка игры</span><strong>~<?php echo e((int) $estimated_mmr); ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php
    $sample_note = ($st['has_full_wl'] ?? false) ? 'за всё время' : 'по последним ' . (int) ($st['sample'] ?? 0) . ' матчам';
    ?>
    <section class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat-card">
            <span class="stat-card-label">Победы</span>
            <span class="stat-card-value text-radiant"><?php echo e($total_wins); ?></span>
            <span class="stat-card-sub"><?php echo e($sample_note); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Поражения</span>
            <span class="stat-card-value text-dire"><?php echo e($total_losses); ?></span>
            <span class="stat-card-sub"><?php echo e($sample_note); ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Винрейт</span>
            <span class="stat-card-value <?php echo $winrate >= 50 ? 'text-radiant' : 'text-dire'; ?>"><?php echo e($winrate); ?>%</span>
            <div class="winrate-bar mt-2"><span style="width: <?php echo e(min(100, max(0, $winrate))); ?>%"></span></div>
        </div>
        <div class="stat-card">
            <span class="stat-card-label">Средн. KDA</span>
            <span class="stat-card-value text-gold"><?php echo e($st['avg_kda'] ?? 0); ?></span>
            <span class="stat-card-sub"><?php echo e($st['avg_kills'] ?? 0); ?> / <?php echo e($st['avg_deaths'] ?? 0); ?> / <?php echo e($st['avg_assists'] ?? 0); ?></span>
        </div>
    </section>

    <?php $top = $st['top_heroes'] ?? []; ?>
    <?php if ($top !== []): ?>
    <section class="mb-5 rounded-xl border border-line bg-panel p-4">
        <div class="mb-3 border-b border-line pb-1.5">
            <span class="text-base font-bold uppercase tracking-wide text-main">Чаще всего играет</span>
            <span class="text-muted"> - топ герои</span>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <?php foreach ($top as $hero): ?>
                <?php
                $hid = (int) $hero['hero_id'];
                $himg = get_hero_img($hid, $heroes);
                $wr = (int) $hero['winrate'];
                ?>
                <div class="hero-card">
                    <?php if ($himg): ?><img class="hero-card-img" src="<?php echo e($himg); ?>" alt="" loading="lazy"><?php endif; ?>
                    <div class="hero-card-body">
                        <span class="hero-card-name"><?php echo e(get_hero_name($hid, $heroes)); ?></span>
                        <span class="hero-card-meta"><?php echo e($hero['games']); ?> игр · <span class="<?php echo $wr >= 50 ? 'text-radiant' : 'text-dire'; ?>"><?php echo e($wr); ?>%</span></span>
                        <div class="winrate-bar"><span class="<?php echo $wr >= 50 ? 'wr-good' : 'wr-bad'; ?>" style="width: <?php echo e($wr); ?>%"></span></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="mb-5 rounded-xl border border-line bg-panel p-4">
        <div class="mb-3 flex flex-col gap-1 border-b border-line pb-3 md:flex-row md:items-center md:justify-between">
            <div>
                <span class="text-base font-bold uppercase tracking-wide text-main">История матчей</span>
                <span class="text-muted"> - страница <?php echo e($matches_page); ?>, нажмите матч чтобы открыть</span>
            </div>
            <span class="text-[12px] text-muted"><?php echo $include_turbo ? 'Turbo включён: матчи и статистика учитывают Turbo' : 'Turbo выключен: Turbo-матчи скрыты'; ?></span>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full border-collapse text-[13px]">
            <thead>
                <tr>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-left text-[11px] font-normal uppercase text-muted">Герой</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">Результат</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-left text-[11px] font-normal uppercase text-muted">Режим</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">К / С / П</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">KDA</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">Длит.</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($matches as $match): ?>
                    <?php
                    $match_id = (string) ($match['match_id'] ?? '');
                    $hero_id = (int) ($match['hero_id'] ?? 0);
                    $is_radiant = (int) ($match['player_slot'] ?? 0) < 128;
                    $won = ((bool) ($match['radiant_win'] ?? false)) === $is_radiant;
                    $mk = (int) ($match['kills'] ?? 0);
                    $md = (int) ($match['deaths'] ?? 0);
                    $ma = (int) ($match['assists'] ?? 0);
                    $hero_img = get_hero_img($hero_id, $heroes);
                    ?>
                    <tr class="group cursor-pointer hover:bg-row-hover" onclick="window.location='<?php echo e(match_url($match_id, 'overview')); ?>'">
                        <td class="border-b border-line px-2.5 py-1.5">
                            <div class="flex items-center gap-2.5">
                                <span class="result-strip <?php echo $won ? 'bg-radiant' : 'bg-dire'; ?>"></span>
                                <?php if ($hero_img): ?><img class="h-[28px] w-[50px] rounded-sm object-cover" src="<?php echo e($hero_img); ?>" alt="" loading="lazy"><?php endif; ?>
                                <span class="font-medium text-main"><?php echo e(get_hero_name($hero_id, $heroes)); ?></span>
                            </div>
                        </td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center font-bold <?php echo $won ? 'text-radiant' : 'text-dire'; ?>"><?php echo $won ? 'Победа' : 'Поражение'; ?></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-muted">
                            <?php $mode_id = (int) ($match['game_mode'] ?? 0); ?>
                            <?php echo e(game_mode_name($mode_id)); ?>
                            <?php if ($mode_id === 23): ?><span class="turbo-badge">Turbo</span><?php endif; ?>
                        </td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center font-mono"><span class="text-radiant"><?php echo e($mk); ?></span> / <span class="text-dire"><?php echo e($md); ?></span> / <span class="text-gold"><?php echo e($ma); ?></span></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center text-muted"><?php echo e(kda_ratio($mk, $md, $ma)); ?></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center"><?php echo e(format_vision_time((int) ($match['duration'] ?? 0))); ?></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center text-muted"><?php echo !empty($match['start_time']) ? e(date('d.m.Y', (int) $match['start_time'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($matches === []): ?>
            <div class="mt-3 rounded-lg border border-line bg-black/15 p-3 text-sm text-muted">На этой странице матчей нет.</div>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-[12px] text-muted">
                Показано до <?php echo e((int) ($st['per_page'] ?? 25)); ?> матчей н�� странице<?php echo $include_turbo ? ' · Turbo включён' : ' · Turbo скрыт'; ?>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($matches_page > 1): ?>
                    <a class="profile-page-btn" href="<?php echo e($prev_page_url); ?>">← Назад</a>
                <?php else: ?>
                    <span class="profile-page-btn is-disabled">← Назад</span>
                <?php endif; ?>
                <span class="profile-page-current">Стр. <?php echo e($matches_page); ?></span>
                <?php if ($has_next_page): ?>
                    <a class="profile-page-btn" href="<?php echo e($next_page_url); ?>">Вперёд →</a>
                <?php else: ?>
                    <span class="profile-page-btn is-disabled">Вперёд →</span>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}
