<?php

declare(strict_types=1);

/**
 * «Главная» страница необработанного матча.
 *
 * Показывает то, что знает OpenDota-метаданные (герои, длительность, победитель)
 * + кнопку «Запросить обработку». Кнопка шлёт POST на локальный /api/parse,
 * JS опрашивает /api/status/{id} и перезагружает страницу по готовности —
 * сервер уже будет отдавать полные распарсенные данные.
 */
?>
<section class="team-header">
    <div>
        <span class="team-title">Матч #<?php echo e($match_id); ?></span>
        <span class="team-subtitle"> - <?php echo e($match_duration); ?> · <?php echo e($match_end_time); ?></span>
    </div>
    <?php if (!empty($match['radiant_win'])): ?>
        <span class="winner-label radiant-title">Свет победил</span>
    <?php else: ?>
        <span class="winner-label dire-title">Тьма победила</span>
    <?php endif; ?>
</section>

<section class="stats-gate" data-stats-gate>
    <div class="stats-gate-card" role="status" aria-live="polite">
        <div class="stats-gate-title">Матч ещё не обработан</div>
        <div class="stats-gate-text">
            Демка не парсилась. Нажмите кнопку — локальный API скачает
            <code>.dem.bz2</code> и распарсит его. После этого откроется полная
            статистика (обзор, лейнинг, вижен, таймфайты и т.&nbsp;д.).
        </div>
        <button class="stats-gate-button" type="button" data-parse-trigger>Запросить обработку</button>
        <div class="stats-gate-status" data-parse-status></div>
    </div>
</section>

<?php
render_team_overview(
    'Силы Света',
    'radiant-title',
    $radiant_players,
    !empty($match['radiant_win']),
    $heroes,
    $items_by_id
);
render_team_overview(
    'Силы Тьмы',
    'dire-title',
    $dire_players,
    empty($match['radiant_win']),
    $heroes,
    $items_by_id
);
?>

<div class="stats-toast" data-stats-toast hidden></div>

<script>
    window.matchParseGate = {
        matchId: <?php echo json_encode((string) $match_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        parseUrl: <?php echo json_encode(app_url('api/parse'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        statusUrl: <?php echo json_encode(app_url('api/status/' . $match_id), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    };
</script>
<script src="<?php echo e(asset_url('src/assets/match-parse-gate.js')); ?>"></script>
