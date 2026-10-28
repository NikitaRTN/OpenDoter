<?php
declare(strict_types=1);
function render_profile_workspace(string $account_id, array $heroes, array $stats): void {
    $all = $stats['top_heroes'] ?? [];
    ?>
    <section class="profile-workspace" data-profile-workspace data-account="<?php echo e($account_id); ?>">
      <div class="pw-heading"><div><span class="pw-eyebrow">ПОДРОБНЫЙ ПРОФИЛЬ</span><h2>Как игрок влияет на матч</h2><p>История, герои и оценка вклада. Все режимы, включая Turbo, показаны по умолчанию.</p></div></div>
      <nav class="pw-tabs" aria-label="Разделы профиля"><a href="#profile-history">Матчи и вклад</a><a href="#profile-heroes">Все герои (<?php echo count($all); ?>)</a><a href="#impact-method">Как считается оценка</a></nav>
      <section id="profile-history" class="pw-panel">
        <div class="pw-title"><h3>История матчей</h3><button type="button" data-refresh>Обновить историю</button></div>
        <form data-profile-filters class="pw-filters">
          <label>Герой<select name="hero_id"><option value="">Все герои</option><?php $sorted = $heroes; uasort($sorted, static fn($a,$b) => strcmp($a['localized_name'] ?? '', $b['localized_name'] ?? '')); foreach ($sorted as $id=>$hero): ?><option value="<?php echo e((int)$id); ?>"><?php echo e($hero['localized_name'] ?? $hero['name'] ?? $id); ?></option><?php endforeach; ?></select></label>
          <label>Команда<select name="is_radiant"><option value="">Обе команды</option><option value="1">Свет</option><option value="0">Тьма</option></select></label>
          <label>Результат<select name="win"><option value="">Любой</option><option value="1">Победа</option><option value="0">Поражение</option></select></label>
          <label>Линия<select name="lane_role"><option value="">Любая линия</option><option value="1">Лёгкая</option><option value="2">Центр</option><option value="3">Сложная</option><option value="4">Роуминг</option></select></label>
          <label>Режим<select name="game_mode"><option value="">Все + Turbo</option><option value="23">Только Turbo</option><option value="22">All Pick</option><option value="2">Captain’s Mode</option><option value="3">Random Draft</option><option value="4">Single Draft</option><option value="5">All Random</option><option value="13">Другой: режим 13</option></select></label>
          <label>Патч<select name="patch" data-patch><option value="">Все патчи</option></select></label>
          <label>Тип игры<select name="lobby_type"><option value="">Любой</option><option value="7">Рейтинговая</option><option value="0">Обычная</option><option value="1">Практика</option><option value="2">Турнирная</option></select></label>
          <label>Период<select name="date"><option value="">Вся история</option><option value="7">7 дней</option><option value="30">30 дней</option><option value="90">90 дней</option><option value="365">365 дней</option></select></label>
          <label>Регион<select name="region"><option value="">Все регионы</option><option value="1">США, запад</option><option value="2">США, восток</option><option value="3">Европа, запад</option><option value="8">Россия</option><option value="9">Европа, восток</option><option value="5">Юго-Восточная Азия</option></select></label>
          <div class="pw-filter-actions"><button type="submit">Применить</button><button type="button" data-reset>Сбросить</button></div>
        </form>
        <p class="pw-muted">Фильтры применяются ко всей доступной истории, а не только к открытой странице. Линия известна не для всех матчей.</p>
        <div data-profile-status role="status" aria-live="polite">Загружаем историю…</div>
        <div data-profile-summary class="pw-summary"></div>
      <details class="pw-panel" data-match-breakdown hidden><summary>Разбор оценки выбранного матча</summary><div data-breakdown-content></div></details>
        <p class="pw-mobile-hint pw-muted">Таблицу можно прокручивать по горизонтали →</p>
        <div class="pw-scroll" tabindex="0" aria-label="Таблица матчей, прокрутка по горизонтали"><table class="pw-table"><thead><tr><th>Герой / матч</th><th>Результат</th><th>Режим</th><th>К / С / П</th><th>Урон героям</th><th>Здания</th><th>OD Impact</th><th>Длительность / дата</th></tr></thead><tbody data-profile-rows></tbody></table></div>
        <div class="pw-pagination"><button type="button" data-page-prev>← Назад</button><span data-page-label>Страница 1</span><button type="button" data-page-next>Вперёд →</button></div>
      </section>
      <section id="profile-heroes" class="pw-panel"><div class="pw-title"><div><h3>Все сыгранные герои</h3><p class="pw-muted">Вся доступная история, включая Turbo. Нажмите героя для фильтрации матчей.</p></div><label class="pw-hero-search">Поиск героя<input type="search" data-hero-search placeholder="Например, Dragon Knight"></label></div>
        <p class="pw-mobile-hint pw-muted">Таблицу можно прокручивать по горизонтали →</p>
        <div class="pw-scroll"><table class="pw-table"><thead><tr><th>Герой</th><th><button type="button" data-hero-sort="games">Игры ↕</button></th><th><button type="button" data-hero-sort="wins">Победы ↕</button></th><th><button type="button" data-hero-sort="winrate">Винрейт ↕</button></th></tr></thead><tbody data-hero-list>
        <?php foreach ($all as $h): $id=(int)$h['hero_id']; $hn=get_hero_name($id,$heroes); ?><tr data-hero-name="<?php echo e($hn); ?>" data-games="<?php echo e($h['games']); ?>" data-wins="<?php echo e($h['wins']); ?>" data-winrate="<?php echo e($h['winrate']); ?>"><td><button class="pw-hero-button" type="button" data-select-hero="<?php echo $id; ?>"><?php $img=get_hero_img($id,$heroes); if($img): ?><img loading="lazy" src="<?php echo e($img); ?>" alt=""><?php endif; ?><?php echo e($hn); ?></button></td><td><?php echo e($h['games']); ?></td><td><?php echo e($h['wins']); ?></td><td><?php echo e($h['winrate']); ?>%</td></tr><?php endforeach; ?>
        </tbody></table><p data-hero-empty hidden>Герой не найден.</p></div>
      </section>
      <details id="impact-method" class="pw-panel"><summary>OD Impact v1: открытая формула оценки 0–1000</summary>
        <p>Это экспериментальная оценка статистического вклада, не MMR, не процентиль игроков и не доказательство намеренного руина. Она не учитывает качество решений, сейвы и позиции на карте. Значение около 500 означает близость к выбранным нами ориентирам, а не «среднего игрока Dota».</p>
        <p><strong>Формула:</strong> 1000 × Σ(вес × компонент) / Σ доступных весов. Каждый компонент от 0 до 1; недоступные данные исключаются, а не заменяются нулями. Матчи короче 5 минут и матчи без достаточных данных не оцениваются. Победа сама по себе баллов не добавляет.</p>
        <ul><li>Боевой вклад — 20%: KDA / (KDA + 3), где KDA = (убийства + ассисты) / max(1, смерти).</li><li>Выживаемость — 20%: 1 / (1 + смерти на 10 минут / 1,5).</li><li>Урон героям — 20%: урон в минуту / (урон в минуту + 600); в Turbo ориентир 900.</li><li>Пуш — 15%: урон зданиям в минуту / (урон зданиям в минуту + 75); в Turbo ориентир 150.</li><li>Экономика — 15%: среднее GPM/(GPM+450) и XPM/(XPM+600); в Turbo ориентиры удвоены.</li><li>Поддержка — 10%: среднее доступных нормированных лечения, контроля и поставленных обсерверов за минуту; ориентиры 100 HP, 1,5 секунды и 0,2 варда в минуту.</li></ul>
        <p>При частично доступной экономике её вес уменьшается пропорционально числу доступных показателей из двух; для поддержки — из трёх. Оставшиеся веса нормируются заново.</p>
        <p>Линия не равна роли: саппорт на лёгкой и керри на лёгкой получают одинаковые базовые веса, пока источник не сообщает настоящую позицию. Роуминг также не считается автоматически саппортом. Поэтому оценка приблизительная и может недооценивать поддержку. Танкование не включено: без полных реплеев оно недоступно, а большой полученный урон не обязательно полезен.</p>
        <p>Сводка рассчитана по последним максимум 100 матчам текущего фильтра и не меняется при перелистывании страниц. В режиме «все» смешаны разные режимы: для сравнения выберите один. Покрытие показывает наличие метрик, не точность оценки. Кэш истории — 5 минут; кэш базового профиля — 1 час. При ошибке источника старые данные помечаются явно.</p>
      </details>
      <script type="application/json" data-profile-heroes><?php echo json_encode($heroes, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?></script>
    </section>
    <script defer src="<?php echo e(asset_url('src/assets/profile-workspace.js')); ?>?v=<?php echo filemtime(__DIR__.'/../assets/profile-workspace.js'); ?>"></script>
    <?php
}
