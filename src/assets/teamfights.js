(() => {
  'use strict';
  const refresh = document.querySelector('[data-tf-refresh]');
  if (refresh) {
    const button = refresh.querySelector('button');
    const status = refresh.querySelector('[data-tf-refresh-status]');
    button.addEventListener('click', async () => {
      button.disabled = true; status.textContent = 'Обновляем…';
      try {
        const response = await fetch(refresh.dataset.url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({match_id:refresh.dataset.id, refresh_teamfights:true})});
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        for (;;) {
          await new Promise(resolve => setTimeout(resolve, 2000));
          const check = await fetch(refresh.dataset.statusUrl, {headers:{'Accept':'application/json'},cache:'no-store'});
          if (!check.ok) throw new Error(`HTTP ${check.status}`);
          const state = await check.json();
          if (state.state === 'error') throw new Error(state.message || 'Ошибка обработки');
          if (state.state === 'done') {
            status.textContent = 'Готово. Обновляем страницу…';
            const url = new URL(window.location.href); url.searchParams.set('refresh_teamfights','1'); window.location.assign(url.href); return;
          }
        }
      } catch (error) { status.textContent = `Не удалось обновить: ${error.message}`; button.disabled = false; }
    });
  }
  document.querySelectorAll('.tf-workspace .insight-fight').forEach(fight => {
    const player = fight.querySelector('[data-tf-filter="player"]');
    const kind = fight.querySelector('[data-tf-filter="kind"]');
    const events = [...fight.querySelectorAll('.tf-event')];
    const update = () => {
      let shown = 0;
      for (const row of events) {
        const matchesPlayer = player.value === 'all' || row.dataset.actor === player.value || row.dataset.target === player.value;
        row.hidden = !(matchesPlayer && (kind.value === 'all' || row.dataset.kind === kind.value));
        if (!row.hidden) shown++;
      }
      fight.querySelector('[data-tf-count]').textContent = events.length ? `${shown} / ${events.length}` : '';
      const empty = fight.querySelector('[data-tf-empty]');
      if (empty) empty.hidden = shown > 0;
    };
    player.addEventListener('change', update); kind.addEventListener('change', update);
    fight.querySelectorAll('[data-tf-player]').forEach(button => button.addEventListener('click', () => {
      player.value = button.dataset.tfPlayer; kind.value = 'all'; update();
      fight.querySelector('.tf-timeline').scrollIntoView({block:'nearest', behavior:'smooth'});
      player.focus({preventScroll:true});
    }));
    fight.querySelectorAll('.tf-use img').forEach(img => {
      const fail = () => { img.hidden = true; img.parentElement.classList.add('tf-icon-missing'); };
      img.addEventListener('error', fail); if (img.complete && !img.naturalWidth) fail();
    });
    update();
  });
})();
