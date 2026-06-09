<?php

declare(strict_types=1);

function render_home_page(?string $account_id, ?array $me): void
{
    $profile = is_array($me['player_profile']['profile'] ?? null) ? $me['player_profile']['profile'] : [];
    $stats = is_array($me['player_stats'] ?? null) ? $me['player_stats'] : [];
    $name = (string) ($profile['personaname'] ?? '');
    $avatar = (string) ($profile['avatarfull'] ?? $profile['avatarmedium'] ?? '');
    $tier = (int) ($me['player_profile']['rank_tier'] ?? 0);
    $medal = rank_medal_parts($tier);
    ?>
    <?php if ($account_id !== null && $me !== null): ?>
        <section class="home-hero mb-5 overflow-hidden rounded-xl border border-line">
            <div class="home-hero-glow"></div>
            <div class="relative flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <?php if ($avatar !== ''): ?>
                        <img class="h-16 w-16 rounded-xl border border-line object-cover shadow-card" src="<?php echo e($avatar); ?>" alt="">
                    <?php endif; ?>
                    <div>
                        <div class="text-[13px] uppercase tracking-wide text-muted">С возвращением</div>
                        <div class="text-2xl font-bold text-main"><?php echo e($name !== '' ? $name : ('Игрок ' . $account_id)); ?></div>
                        <div class="mt-1 text-[13px] text-muted"><?php echo e(get_rank_title($tier)); ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="rank-medal rank-medal-sm">
                        <img class="rank-medal-img" src="<?php echo e(rank_medal_img((int) $medal['major'])); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
                        <?php $star = rank_star_img((int) $medal['stars']); ?>
                        <?php if ($star): ?><img class="rank-medal-stars" src="<?php echo e($star); ?>" alt="" loading="lazy" onerror="this.style.display='none'"><?php endif; ?>
                    </div>
                    <a class="rounded-md border border-link bg-link/10 px-4 py-2.5 text-xs font-bold uppercase text-link transition-colors hover:bg-link/20 hover:text-link-hover" href="<?php echo e(player_url($account_id)); ?>">Мой профиль</a>
                </div>
            </div>
            <?php if ($stats !== []): ?>
            <div class="grid grid-cols-2 gap-px border-t border-line bg-line lg:grid-cols-4">
                <div class="home-mini-stat"><span class="home-mini-label">Победы</span><span class="home-mini-value text-radiant"><?php echo e((int) ($stats['total_wins'] ?? 0)); ?></span></div>
                <div class="home-mini-stat"><span class="home-mini-label">Поражения</span><span class="home-mini-value text-dire"><?php echo e((int) ($stats['total_losses'] ?? 0)); ?></span></div>
                <div class="home-mini-stat"><span class="home-mini-label">Винрейт</span><span class="home-mini-value <?php echo ((float) ($stats['winrate'] ?? 0)) >= 50 ? 'text-radiant' : 'text-dire'; ?>"><?php echo e($stats['winrate'] ?? 0); ?>%</span></div>
                <div class="home-mini-stat"><span class="home-mini-label">Средн. KDA</span><span class="home-mini-value text-gold"><?php echo e($stats['avg_kda'] ?? 0); ?></span></div>
            </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="home-hero mb-5 overflow-hidden rounded-xl border border-line">
            <div class="home-hero-glow"></div>
            <div class="relative flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="m-0 text-2xl font-bold text-main">OpenDoter</h1>
                    <p class="mt-2 max-w-xl text-[14px] leading-relaxed text-muted">Подробная статистика матчей и игроков Dota 2. Войдите под своим аккаунтом, чтобы быстро открывать свой профиль и видеть недавно просмотренные игры.</p>
                </div>
                <button type="button" data-od-login-open class="shrink-0 rounded-md border border-radiant/60 bg-radiant/10 px-5 py-3 text-sm font-bold uppercase text-radiant transition-colors hover:bg-radiant/20">Войти</button>
            </div>
        </section>
    <?php endif; ?>

    <section class="mb-5 rounded-xl border border-line bg-panel p-4">
        <div class="mb-3 flex items-center justify-between border-b border-line pb-1.5">
            <div>
                <span class="text-base font-bold uppercase tracking-wide text-main">Недавно просмотренные игры</span>
                <span class="text-muted"> - история на этом устройстве</span>
            </div>
            <button type="button" data-od-clear-recent class="hidden text-[11px] uppercase text-muted hover:text-dire">Очистить</button>
        </div>
        <div id="od-recent-matches" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
        <div id="od-recent-empty" class="rounded-lg border border-dashed border-line bg-black/15 p-6 text-center text-[13px] text-muted">Здесь появятся матчи, которые вы открывали. Найдите матч или игрока через поиск сверху.</div>
    </section>

    <section class="mb-5 rounded-xl border border-line bg-panel p-4">
        <div class="mb-3 border-b border-line pb-1.5">
            <span class="text-base font-bold uppercase tracking-wide text-main">Недавно просмотренные игроки</span>
        </div>
        <div id="od-recent-players" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3"></div>
        <div id="od-recent-players-empty" class="rounded-lg border border-dashed border-line bg-black/15 p-6 text-center text-[13px] text-muted">Здесь появятся профили игроков, которые вы смотрели.</div>
    </section>
    <?php
}
