<?php

declare(strict_types=1);

function render_player_profile(array $profile, array $matches, array $heroes): void
{
    $data = is_array($profile['profile'] ?? null) ? $profile['profile'] : [];
    $name = (string) ($data['personaname'] ?? 'Игрок');
    $avatar = (string) ($data['avatarfull'] ?? $data['avatarmedium'] ?? '');
    ?>
    <section class="mb-5 rounded-lg border border-line bg-panel p-4">
        <div class="flex items-center gap-3.5">
            <?php if ($avatar !== ''): ?><img class="h-[84px] w-[84px] rounded border border-line object-cover" src="<?php echo e($avatar); ?>" alt=""><?php endif; ?>
            <div>
                <h1 class="m-0 mb-1.5 text-[22px] font-bold"><?php echo e($name); ?></h1>
                <div class="flex flex-wrap gap-2.5 text-muted">
                    <span>Account ID: <?php echo e($data['account_id'] ?? '-'); ?></span>
                    <span><?php echo e(get_rank_title($profile['rank_tier'] ?? 0)); ?></span>
                    <?php if (!empty($profile['leaderboard_rank'])): ?><span>Leaderboard: <?php echo e($profile['leaderboard_rank']); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (!empty($data['profileurl'])): ?>
            <a class="mt-3.5 inline-block rounded border border-link bg-link/10 px-4 py-2 text-xs font-bold uppercase text-link transition-colors hover:bg-link/20 hover:text-link-hover" href="<?php echo e($data['profileurl']); ?>" target="_blank" rel="noreferrer">Steam</a>
        <?php endif; ?>
    </section>

    <section class="mb-5 rounded-lg border border-line bg-panel p-4">
        <div class="mb-3 flex items-center justify-between border-b border-line pb-1.5">
            <div>
                <span class="text-base font-bold uppercase tracking-wide">Матчи игрока</span>
                <span class="text-muted"> - выберите матч для просмотра</span>
            </div>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-left text-[11px] font-normal uppercase text-muted">Матч</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-left text-[11px] font-normal uppercase text-muted">Герой</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">Результат</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">КДА</th>
                    <th class="border-b-2 border-line bg-surface px-2.5 py-2 text-center text-[11px] font-normal uppercase text-muted">Длительность</th>
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
                    ?>
                    <tr class="hover:bg-row-hover">
                        <td class="border-b border-line px-2.5 py-1.5"><a class="font-bold text-link no-underline hover:underline" href="<?php echo e(match_url($match_id, 'overview')); ?>"><?php echo e($match_id); ?></a></td>
                        <td class="border-b border-line px-2.5 py-1.5">
                            <div class="flex items-center gap-2.5">
                                <?php $hero_img = get_hero_img($hero_id, $heroes); ?>
                                <?php if ($hero_img): ?><img class="h-[27px] w-12 rounded-sm" src="<?php echo e($hero_img); ?>" alt=""><?php endif; ?>
                                <span><?php echo e(get_hero_name($hero_id, $heroes)); ?></span>
                            </div>
                        </td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center font-bold <?php echo $won ? 'text-radiant' : 'text-dire'; ?>"><?php echo $won ? 'Победа' : 'Поражение'; ?></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center"><?php echo e((int) ($match['kills'] ?? 0)); ?>/<?php echo e((int) ($match['deaths'] ?? 0)); ?>/<?php echo e((int) ($match['assists'] ?? 0)); ?></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center"><?php echo e(format_vision_time((int) ($match['duration'] ?? 0))); ?></td>
                        <td class="border-b border-line px-2.5 py-1.5 text-center"><?php echo !empty($match['start_time']) ? e(date('d.m.Y', (int) $match['start_time'])) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>
    <?php
}
