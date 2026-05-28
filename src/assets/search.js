(function () {
    const input = document.querySelector('[data-search-input]');
    const results = document.querySelector('[data-search-results]');
    const query = new URLSearchParams(window.location.search).get('q')?.trim() || '';

    if (input) {
        input.value = query;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[char]);
    }

    function renderEmpty(message) {
        results.innerHTML = `<div class="empty-state">${escapeHtml(message)}</div>`;
    }

    function renderMatch(matchId) {
        return `
            <div class="search-match-result">
                <span>Найден матч #${escapeHtml(matchId)}</span>
                <a class="profile-link" href="/matches/${encodeURIComponent(matchId)}/overview">Открыть обзор</a>
            </div>
        `;
    }

    function renderPlayers(players) {
        if (!players.length) {
            return '';
        }

        const rows = players.slice(0, 20).map((player) => `
            <tr>
                <td>
                    <div class="player-cell">
                        ${player.avatarfull ? `<img class="profile-mini-avatar" src="${escapeHtml(player.avatarfull)}" alt="">` : ''}
                        <a class="player-name" href="/players/?id=${encodeURIComponent(player.account_id)}">${escapeHtml(player.personaname || 'Игрок')}</a>
                    </div>
                </td>
                <td class="col-center">${escapeHtml(player.account_id || '-')}</td>
            </tr>
        `).join('');

        return `
            <table class="overview-table search-table">
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th class="col-center">Account ID</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        `;
    }

    async function fetchJson(url) {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    async function runSearch() {
        if (!query) {
            renderEmpty('Введите ник игрока или Match ID в поиск сверху.');
            return;
        }

        const isMatchId = /^\d{8,}$/.test(query);
        const promises = [];

        // Запускаем оба запроса параллельно
        const matchPromise = isMatchId
            ? fetchJson(`https://api.opendota.com/api/matches/${encodeURIComponent(query)}`)
                .then(match => match && match.match_id ? renderMatch(match.match_id) : '')
                .catch(() => '')
            : Promise.resolve('');

        const playersPromise = fetchJson(`https://api.opendota.com/api/search?q=${encodeURIComponent(query)}`)
            .then(players => renderPlayers(Array.isArray(players) ? players : []))
            .catch(() => '');

        const [matchHtml, playersHtml] = await Promise.all([matchPromise, playersPromise]);
        const parts = [matchHtml, playersHtml].filter(Boolean);

        results.innerHTML = parts.length
            ? parts.join('')
            : '<div class="empty-state">Ничего не найдено.</div>';
    }

    runSearch();
}());
