// OpenDoter — клиентские скрипты: вход, шапка и история просмотров.
(function () {
  'use strict';

  var BASE = (window.OD_BASE || '/');
  var KEY_MATCHES = 'od_recent_matches';
  var KEY_PLAYERS = 'od_recent_players';
  var MAX = 12;

  function url(path) {
    return BASE + String(path).replace(/^\//, '');
  }

  function readList(key) {
    try {
      var raw = localStorage.getItem(key);
      var arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function writeList(key, list) {
    try {
      localStorage.setItem(key, JSON.stringify(list.slice(0, MAX)));
    } catch (e) {}
  }

  // Добавить запись в начало списка, убрав дубликаты по id.
  function pushUnique(key, item, idField) {
    if (!item || !item[idField]) return;
    var list = readList(key).filter(function (x) {
      return String(x[idField]) !== String(item[idField]);
    });
    item.ts = Date.now();
    list.unshift(item);
    writeList(key, list);
  }

  function parseJsonScript(id) {
    var el = document.getElementById(id);
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || el.innerText || 'null');
    } catch (e) {
      return null;
    }
  }

  function esc(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  // ——— Трекинг просмотров ———
  function trackVisits() {
    var match = parseJsonScript('od-track-match');
    if (match && match.match_id) {
      pushUnique(KEY_MATCHES, match, 'match_id');
    }
    var player = parseJsonScript('od-track-player');
    if (player && player.account_id) {
      pushUnique(KEY_PLAYERS, player, 'account_id');
    }
  }

  // ——— Шапка: аватар/имя залогиненного игрока из кэша ———
  function hydrateChip() {
    var chip = document.querySelector('.account-chip[data-account-id]');
    if (!chip) return;
    var id = chip.getAttribute('data-account-id');
    var player = readList(KEY_PLAYERS).filter(function (x) {
      return String(x.account_id) === String(id);
    })[0];
    if (!player) return;
    var avatarEl = chip.querySelector('[data-od-avatar]');
    var nameEl = chip.querySelector('[data-od-name]');
    if (avatarEl && player.avatar) {
      avatarEl.innerHTML = '<img src="' + esc(player.avatar) + '" alt="">';
      avatarEl.classList.add('has-img');
    }
    if (nameEl && player.name) {
      nameEl.textContent = player.name;
    }
  }

  // ——— Модалка входа ———
  function setupLoginModal() {
    var modal = document.getElementById('od-login-modal');
    if (!modal) return;
    function open() {
      modal.removeAttribute('hidden');
      var input = modal.querySelector('input[name="account"]');
      if (input) setTimeout(function () { input.focus(); }, 30);
    }
    function close() { modal.setAttribute('hidden', ''); }
    document.querySelectorAll('[data-od-login-open]').forEach(function (b) {
      b.addEventListener('click', open);
    });
    modal.querySelectorAll('[data-od-login-close]').forEach(function (b) {
      b.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  // ——— Рендер недавно просмотренных ———
  function matchCard(m) {
    var won = m.radiant_win;
    var score = (m.radiant_score != null && m.dire_score != null)
      ? (esc(m.radiant_score) + ' : ' + esc(m.dire_score)) : '';
    return (
      '<a class="recent-card" href="' + esc(url('matches/' + m.match_id)) + '">' +
        '<span class="recent-card-strip ' + (won ? 'is-radiant' : 'is-dire') + '"></span>' +
        '<div class="recent-card-body">' +
          '<span class="recent-card-title">Матч ' + esc(m.match_id) + '</span>' +
          '<span class="recent-card-meta">' + (score ? ('Счёт ' + score) : 'Открыть матч') +
            (m.duration ? (' · ' + esc(m.duration)) : '') + '</span>' +
        '</div>' +
        '<span class="recent-card-arrow">→</span>' +
      '</a>'
    );
  }

  function playerCard(p) {
    var avatar = p.avatar
      ? '<img class="recent-card-avatar" src="' + esc(p.avatar) + '" alt="">'
      : '<span class="recent-card-avatar recent-card-avatar-fallback">' + esc((p.name || '?').slice(0, 1)) + '</span>';
    return (
      '<a class="recent-card" href="' + esc(url('players/' + p.account_id)) + '">' +
        avatar +
        '<div class="recent-card-body">' +
          '<span class="recent-card-title">' + esc(p.name || ('Игрок ' + p.account_id)) + '</span>' +
          '<span class="recent-card-meta">' + esc(p.rank || ('ID ' + p.account_id)) + '</span>' +
        '</div>' +
        '<span class="recent-card-arrow">→</span>' +
      '</a>'
    );
  }

  function renderRecent() {
    var matchesWrap = document.getElementById('od-recent-matches');
    if (matchesWrap) {
      var matches = readList(KEY_MATCHES);
      var emptyM = document.getElementById('od-recent-empty');
      if (matches.length) {
        matchesWrap.innerHTML = matches.map(matchCard).join('');
        if (emptyM) emptyM.style.display = 'none';
      } else if (emptyM) {
        emptyM.style.display = '';
      }
    }
    var playersWrap = document.getElementById('od-recent-players');
    if (playersWrap) {
      var players = readList(KEY_PLAYERS);
      var emptyP = document.getElementById('od-recent-players-empty');
      if (players.length) {
        playersWrap.innerHTML = players.map(playerCard).join('');
        if (emptyP) emptyP.style.display = 'none';
      } else if (emptyP) {
        emptyP.style.display = '';
      }
    }
    var clearBtn = document.querySelector('[data-od-clear-recent]');
    if (clearBtn) {
      var hasAny = readList(KEY_MATCHES).length || readList(KEY_PLAYERS).length;
      clearBtn.classList.toggle('hidden', !hasAny);
      clearBtn.addEventListener('click', function () {
        localStorage.removeItem(KEY_MATCHES);
        localStorage.removeItem(KEY_PLAYERS);
        renderRecent();
      });
    }
  }

  function init() {
    trackVisits();
    hydrateChip();
    setupLoginModal();
    renderRecent();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
