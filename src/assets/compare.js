(function () {
  'use strict';

  var root = document.getElementById('cmp-root');
  var dataNode = document.getElementById('cmp-data');
  if (!root || !dataNode) return;

  var payload;
  try {
    payload = JSON.parse(dataNode.textContent || '{}');
  } catch (e) {
    root.innerHTML = '<div class="empty-state"><span>Не удалось прочитать данные сравнения.</span></div>';
    return;
  }

  var players = Array.isArray(payload.players) ? payload.players : [];
  if (!players.length) {
    root.innerHTML = '<div class="empty-state"><span>Нет игроков для сравнения.</span></div>';
    return;
  }

  var timeMetrics = [
    { key: 'networth', label: 'Net worth', unit: 'k', type: 'line' },
    { key: 'xp', label: 'Опыт', unit: 'k', type: 'line' },
    { key: 'lasthits', label: 'Ластхиты', unit: '', type: 'line' },
    { key: 'denies', label: 'Денаи', unit: '', type: 'line' }
  ].filter(function (m) {
    return players.some(function (p) {
      return p.series && Array.isArray(p.series[m.key]) && p.series[m.key].length > 1;
    });
  });

  var groups = [
    {
      title: 'Бой',
      metrics: [
        { key: 'hero_damage', label: 'Урон по героям', unit: 'k', type: 'bar' },
        { key: 'damage_taken', label: 'Получено урона', unit: 'k', type: 'bar' },
        { key: 'hero_healing', label: 'Лечение', unit: 'k', type: 'bar' },
        { key: 'kills', label: 'Убийства', unit: '', type: 'bar' },
        { key: 'deaths', label: 'Смерти', unit: '', type: 'bar' },
        { key: 'assists', label: 'Помощь', unit: '', type: 'bar' }
      ]
    },
    {
      title: 'Экономика',
      metrics: [
        { key: 'net_worth', label: 'Net worth (итог)', unit: 'k', type: 'bar' },
        { key: 'gpm', label: 'Золото/мин', unit: '', type: 'bar' },
        { key: 'xpm', label: 'Опыт/мин', unit: '', type: 'bar' },
        { key: 'last_hits', label: 'Ластхиты (итог)', unit: '', type: 'bar' },
        { key: 'denies', label: 'Денаи (итог)', unit: '', type: 'bar' }
      ]
    },
    {
      title: 'Объекты',
      metrics: [
        { key: 'tower_damage', label: 'Урон по строениям', unit: 'k', type: 'bar' }
      ]
    }
  ];

  var selectedPlayers = {};
  var selectedMetrics = {};
  players.forEach(function (p) { selectedPlayers[p.id] = true; });
  if (timeMetrics[0]) selectedMetrics[timeMetrics[0].key] = true;
  selectedMetrics.hero_damage = true;
  var hoverPoint = null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
    });
  }

  function fmt(value, unit) {
    var n = Math.round(Number(value) || 0);
    if (unit === 'k' && Math.abs(n) >= 1000) return (n / 1000).toFixed(1) + 'k';
    return String(n);
  }

  function fullNum(value) {
    return Math.round(Number(value) || 0).toLocaleString('ru-RU');
  }

  function teamLabel(player) {
    return player.team === 'radiant' ? 'Свет' : 'Тьма';
  }

  function make(tag, cls, html) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (html !== undefined) node.innerHTML = html;
    return node;
  }

  function activePlayers() {
    return players.filter(function (p) { return selectedPlayers[p.id]; });
  }

  function makeHeroButton(player) {
    var btn = make('button');
    btn.type = 'button';
    btn.className = 'cmp-hero-btn';
    btn.innerHTML = (player.img ? '<img src="' + esc(player.img) + '" alt="">' : '') +
      '<span class="cmp-hero-name">' + esc(player.hero) + '</span>' +
      '<span class="cmp-hero-player">' + esc(player.persona) + '</span>';
    function repaint() {
      btn.classList.toggle('active', !!selectedPlayers[player.id]);
      btn.classList.toggle('radiant', player.team === 'radiant');
      btn.classList.toggle('dire', player.team === 'dire');
      btn.style.setProperty('--hero-color', player.color);
    }
    btn.addEventListener('click', function () {
      selectedPlayers[player.id] = !selectedPlayers[player.id];
      repaint();
      renderCharts();
    });
    repaint();
    return btn;
  }

  function makeMetricButton(metric) {
    var btn = make('button');
    btn.type = 'button';
    btn.className = 'cmp-pill';
    btn.textContent = metric.label;
    function repaint() { btn.classList.toggle('active', !!selectedMetrics[metric.key]); }
    btn.addEventListener('click', function () {
      selectedMetrics[metric.key] = !selectedMetrics[metric.key];
      repaint();
      renderCharts();
    });
    repaint();
    return btn;
  }

  function seriesValue(series, minute) {
    if (!Array.isArray(series) || !series.length) return 0;
    var index = Math.max(0, Math.min(series.length - 1, minute));
    return Number(series[index]) || 0;
  }

  function minuteStat(player, key, minute, fallback) {
    var stats = player.minute_stats || {};
    if (Array.isArray(stats[key]) && minute < stats[key].length) return Number(stats[key][minute]) || 0;
    return fallback || 0;
  }

  function playerTooltip(player, metric, value, minute) {
    var isMinute = minute != null;
    var kills = isMinute ? minuteStat(player, 'kills', minute, 0) : (player.totals.kills || 0);
    var deaths = isMinute ? minuteStat(player, 'deaths', minute, 0) : (player.totals.deaths || 0);
    var assists = isMinute
      ? ((player.minute_stats && player.minute_stats.has_assists) ? minuteStat(player, 'assists', minute, 0) : '—')
      : (player.totals.assists || 0);
    var gpm = isMinute ? (minute > 0 ? Math.round(seriesValue(player.series && player.series.networth, minute) / minute) : 0) : (player.totals.gpm || 0);
    var xpm = isMinute ? (minute > 0 ? Math.round(seriesValue(player.series && player.series.xp, minute) / minute) : 0) : (player.totals.xpm || 0);

    return '<div class="cmp-tip-head">' +
      (player.img ? '<img src="' + esc(player.img) + '" alt="">' : '') +
      '<div><strong>' + esc(player.hero) + '</strong><span>' + esc(player.persona) + ' · ' + teamLabel(player) + '</span></div>' +
      '</div>' +
      '<div class="cmp-tip-grid">' +
      '<span>Показатель</span><b>' + esc(metric.label) + '</b>' +
      (isMinute ? '<span>Минута</span><b>' + minute + ':00</b>' : '') +
      '<span>Значение</span><b>' + esc(fullNum(value)) + '</b>' +
      '<span>K/D/A' + (isMinute ? ' к минуте' : '') + '</span><b>' + esc(kills + '/' + deaths + '/' + assists) + '</b>' +
      '<span>GPM / XPM' + (isMinute ? ' к минуте' : '') + '</span><b>' + esc(gpm + ' / ' + xpm) + '</b>' +
      '</div>';
  }

  function lineChart(metric) {
    var list = activePlayers().filter(function (p) {
      return p.series && Array.isArray(p.series[metric.key]) && p.series[metric.key].length > 1;
    });
    if (!list.length) return make('div', 'empty-state', '<span>Нет данных по времени для выбранных героев.</span>');

    var wrap = make('div', 'cmp-line-wrap');
    var tooltip = make('div', 'cmp-tooltip');
    wrap.appendChild(tooltip);

    var width = 1000, height = 330, left = 62, right = 18, top = 18, bottom = 38;
    var plotW = width - left - right, plotH = height - top - bottom;
    var maxLen = 1, maxVal = 1;
    list.forEach(function (p) {
      var s = p.series[metric.key];
      maxLen = Math.max(maxLen, s.length);
      s.forEach(function (v) { maxVal = Math.max(maxVal, Number(v) || 0); });
    });
    function x(i) { return left + (maxLen <= 1 ? 0 : (i / (maxLen - 1)) * plotW); }
    function y(v) { return top + plotH - ((Number(v) || 0) / maxVal) * plotH; }

    var svg = '<svg class="cmp-svg" viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none">';
    for (var g = 0; g <= 4; g++) {
      var val = (maxVal * g) / 4;
      var yy = y(val);
      svg += '<line x1="' + left + '" y1="' + yy + '" x2="' + (width - right) + '" y2="' + yy + '" class="cmp-grid"/>';
      svg += '<text x="' + (left - 8) + '" y="' + (yy + 4) + '" text-anchor="end" class="cmp-axis">' + esc(fmt(val, metric.unit)) + '</text>';
    }
    for (var minute = 0; minute < maxLen; minute += 5) {
      svg += '<text x="' + x(minute) + '" y="' + (height - 10) + '" text-anchor="middle" class="cmp-axis">' + minute + '</text>';
    }
    list.forEach(function (p) {
      var d = '';
      p.series[metric.key].forEach(function (v, i) { d += (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1) + ' '; });
      svg += '<path d="' + d + '" fill="none" stroke="' + esc(p.color) + '" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" opacity="0" data-hit="1"/>';
      svg += '<path d="' + d + '" fill="none" stroke="' + esc(p.color) + '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><title>' + esc(p.hero + ' · ' + p.persona + ' · ' + teamLabel(p)) + '</title></path>';
    });
    svg += '</svg>';
    wrap.appendChild(make('div', '', svg));

    function showTooltip(evt) {
      var rect = wrap.getBoundingClientRect();
      var px = evt.clientX - rect.left;
      var minute = Math.max(0, Math.min(maxLen - 1, Math.round(((px / rect.width) * width - left) / plotW * (maxLen - 1))));
      var nearest = null;
      list.forEach(function (p) {
        var s = p.series[metric.key];
        if (minute >= s.length) return;
        var scaledY = (y(s[minute]) / height) * rect.height;
        var dist = Math.abs((evt.clientY - rect.top) - scaledY);
        if (!nearest || dist < nearest.dist) nearest = { p: p, value: s[minute], dist: dist };
      });
      if (!nearest) return;
      tooltip.innerHTML = playerTooltip(nearest.p, metric, nearest.value, minute);
      tooltip.style.display = 'block';
      tooltip.style.left = Math.min(rect.width - 260, Math.max(8, evt.clientX - rect.left + 12)) + 'px';
      tooltip.style.top = Math.min(rect.height - 150, Math.max(8, evt.clientY - rect.top + 12)) + 'px';
      hoverPoint = nearest;
    }
    wrap.addEventListener('mousemove', showTooltip);
    wrap.addEventListener('mouseleave', function () { tooltip.style.display = 'none'; hoverPoint = null; });

    var legend = make('div', 'cmp-legend');
    list.forEach(function (p) {
      legend.appendChild(make('span', 'cmp-legend-item ' + (p.team === 'radiant' ? 'radiant' : 'dire'), '<i style="background:' + esc(p.color) + '"></i>' + esc(p.hero) + '<small>' + esc(teamLabel(p)) + '</small>'));
    });
    wrap.appendChild(legend);
    return wrap;
  }

  function barChart(metric) {
    var list = activePlayers().slice().sort(function (a, b) {
      return ((b.totals && b.totals[metric.key]) || 0) - ((a.totals && a.totals[metric.key]) || 0);
    });
    if (!list.length) return make('div', 'empty-state', '<span>Выберите героев для сравнения.</span>');
    var max = 1;
    list.forEach(function (p) { max = Math.max(max, (p.totals && Number(p.totals[metric.key])) || 0); });
    var wrap = make('div', 'cmp-bars');
    list.forEach(function (p) {
      var value = (p.totals && Number(p.totals[metric.key])) || 0;
      var pct = Math.max(2, (value / max) * 100);
      var row = make('div', 'cmp-bar-row');
      row.title = p.hero + ' · ' + p.persona + ' · ' + teamLabel(p) + ': ' + fullNum(value);
      row.innerHTML = '<div class="cmp-bar-name ' + (p.team === 'radiant' ? 'radiant' : 'dire') + '">' +
        (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '') +
        '<span>' + esc(p.hero) + '</span><small>' + esc(p.persona) + '</small></div>' +
        '<div class="cmp-bar-track"><div class="cmp-bar-fill" style="width:' + pct.toFixed(1) + '%;background:' + esc(p.color) + '"></div></div>' +
        '<div class="cmp-bar-value">' + esc(fmt(value, metric.unit)) + '</div>';
      wrap.appendChild(row);
    });
    return wrap;
  }

  function renderCharts() {
    var charts = document.getElementById('cmp-charts');
    if (!charts) return;
    charts.innerHTML = '';
    var allMetrics = timeMetrics.slice();
    groups.forEach(function (group) { group.metrics.forEach(function (m) { allMetrics.push(m); }); });
    var selected = allMetrics.filter(function (m) { return selectedMetrics[m.key]; });
    if (!selected.length) {
      charts.appendChild(make('div', 'empty-state', '<span>Выберите хотя бы один показатель.</span>'));
      return;
    }
    selected.forEach(function (metric) {
      var card = make('div', 'cmp-chart-card');
      card.appendChild(make('div', 'cmp-chart-head', '<strong>' + esc(metric.label) + '</strong>' + (metric.type === 'line' ? '<span>по минутам · наведите на линию для точной сводки</span>' : '<span>итог за матч</span>')));
      card.appendChild(metric.type === 'line' ? lineChart(metric) : barChart(metric));
      charts.appendChild(card);
    });
  }

  function render() {
    root.innerHTML = '';
    root.className = 'cmp-root';

    var intro = make('div', 'cmp-intro', '<strong>Сравнение героев</strong><span>Выберите героев и метрики. На графиках наведите курсор на линию — появится герой, игрок, команда, минута и точное значение.</span>');
    root.appendChild(intro);

    var heroes = make('div', 'cmp-panel');
    var heroHeader = make('div', 'cmp-panel-head', '<strong>Герои</strong>');
    var actions = make('div', 'cmp-actions');
    var all = make('button', '', 'Выбрать всех');
    var none = make('button', '', 'Снять всех');
    all.type = none.type = 'button';
    all.addEventListener('click', function () { players.forEach(function (p) { selectedPlayers[p.id] = true; }); render(); });
    none.addEventListener('click', function () { players.forEach(function (p) { selectedPlayers[p.id] = false; }); render(); });
    actions.appendChild(all); actions.appendChild(none); heroHeader.appendChild(actions); heroes.appendChild(heroHeader);
    ['radiant', 'dire'].forEach(function (team) {
      var block = make('div', 'cmp-team-block ' + team);
      block.appendChild(make('div', 'cmp-team-title', team === 'radiant' ? 'Свет' : 'Тьма'));
      var wrap = make('div', 'cmp-hero-grid');
      players.filter(function (p) { return p.team === team; }).forEach(function (p) { wrap.appendChild(makeHeroButton(p)); });
      block.appendChild(wrap);
      heroes.appendChild(block);
    });
    root.appendChild(heroes);

    var metrics = make('div', 'cmp-panel');
    metrics.appendChild(make('div', 'cmp-panel-head', '<strong>Метрики</strong>'));
    if (timeMetrics.length) {
      metrics.appendChild(make('div', 'cmp-metric-title', 'Динамика по минутам'));
      var dyn = make('div', 'cmp-pill-row');
      timeMetrics.forEach(function (m) { dyn.appendChild(makeMetricButton(m)); });
      metrics.appendChild(dyn);
    }
    groups.forEach(function (group) {
      metrics.appendChild(make('div', 'cmp-metric-title', esc(group.title) + ' за матч'));
      var wrap = make('div', 'cmp-pill-row');
      group.metrics.forEach(function (m) { wrap.appendChild(makeMetricButton(m)); });
      metrics.appendChild(wrap);
    });
    root.appendChild(metrics);

    var charts = make('div');
    charts.id = 'cmp-charts';
    root.appendChild(charts);
    renderCharts();
  }

  render();
})();
