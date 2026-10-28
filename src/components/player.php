<?php

declare(strict_types=1);
require_once __DIR__ . '/profile-workspace.php';

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
                    <img class="profile-avatar h-[104px] w-[104px] rounded-xl border border-line object-cover shadow-card" src="<?php echo e($avatar); ?>" alt="">
                <?php else: ?>
                    <div class="profile-avatar flex h-[104px] w-[104px] items-center justify-center rounded-xl border border-line bg-black/30 text-3xl font-bold text-muted"><?php echo e(mb_substr($name, 0, 1)); ?></div>
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
                    <?php if (!$is_me && $account_id !== ''): ?>
                        <a class="inline-flex items-center gap-1.5 rounded-md border border-radiant/50 bg-radiant/10 px-3.5 py-2 text-xs font-bold uppercase text-radiant transition-colors hover:bg-radiant/20" href="<?php echo e(app_url('login?account=' . urlencode($account_id))); ?>">Войти как этот игрок</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-rank-panel" aria-label="Рейтинги игрока">
                <div class="profile-rating profile-rating-rank">
                    <span class="profile-rating-label">Ранг Dota 2</span>
                    <div class="profile-rank-identity">
                        <div class="rank-medal">
                            <img class="rank-medal-img" src="<?php echo e(rank_medal_img((int)$medal['major'])); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                            <?php $star=rank_star_img((int)$medal['stars']); if($star): ?><img class="rank-medal-stars" src="<?php echo e($star); ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php endif; ?>
                        </div>
                        <strong><?php echo e(get_rank_title($tier)); ?></strong>
                    </div>
                    <?php if (is_numeric($current_mmr) && (int)$current_mmr>0): ?>
                        <span class="profile-mmr-value"><?php echo e((int)$current_mmr); ?> MMR</span>
                        <small>Опубликованный MMR</small>
                    <?php elseif ($rank_mmr_range!==null): ?>
                        <span class="profile-mmr-value">≈ <?php echo e(format_mmr_range($rank_mmr_range)); ?> MMR</span>
                        <small title="Точный MMR недоступен; диапазон соответствует медали">Оценка по медали</small>
                    <?php else: ?>
                        <span class="profile-mmr-value">MMR неизвестен</span>
                        <small>Рейтинг не опубликован</small>
                    <?php endif; ?>
                </div>
                <a class="profile-rating profile-rating-skill" href="#impact-method" data-profile-skill aria-live="polite" title="Оценка статистического вклада. Нажми, чтобы открыть формулу и ограничения.">
                    <span class="profile-rating-label">Рейтинг OpenDoter</span>
                    <strong data-skill-value>Загрузка…</strong>
                    <span class="profile-rating-caption">Статистический вклад</span>
                    <small data-skill-sample>Последние 100 матчей</small>
                </a>
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
            <span class="stat-card-label">KDA последних 100 матчей</span>
            <span class="stat-card-value text-gold"><?php echo e($st['avg_kda'] ?? 0); ?></span>
            <span class="stat-card-sub"><?php echo e($st['avg_kills'] ?? 0); ?> / <?php echo e($st['avg_deaths'] ?? 0); ?> / <?php echo e($st['avg_assists'] ?? 0); ?></span>
        </div>
    </section>

    <?php
    render_profile_workspace($account_id, $heroes, $st);
}
