(function () {
    const input = document.querySelector('[data-search-input]');
    const results = document.querySelector('[data-search-results]');
    const query = new URLSearchParams(window.location.search).get('q')?.trim() || '';

    if (input) {
        input.value = query;
    }

    if (!results) {
        return;
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
                        <a class="player-name" href="/players/${encodeURIComponent(player.account_id)}">${escapeHtml(player.personaname || 'Игрок')}</a>
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

    function steamId64ToAccountId(steamId64) {
        if (!/^\d{17,}$/.test(steamId64) || typeof BigInt === 'undefined') {
            return null;
        }

        const accountId = BigInt(steamId64) - 76561197960265728n;
        return accountId > 0n ? accountId.toString() : null;
    }

    function resolveAccountInput(value) {
        const text = String(value || '').trim();
        if (!text) {
            return null;
        }

        const steamProfile = text.match(/steamcommunity\.com\/profiles\/(\d{17,})/i);
        if (steamProfile) {
            return steamId64ToAccountId(steamProfile[1]);
        }

        const playerUrl = text.match(/\/players?\/(\d+)/i);
        if (playerUrl) {
            return playerUrl[1];
        }

        if (/^\d{17,}$/.test(text)) {
            return steamId64ToAccountId(text);
        }

        const isLikelyMatchId = /^\d{8,16}$/.test(text) && Number(text) >= 3000000000;
        return /^\d+$/.test(text) && !isLikelyMatchId ? text : null;
    }

    function uniquePlayers(players) {
        const seen = new Set();
        return players.filter((player) => {
            const accountId = String(player?.account_id || '');
            if (!accountId || seen.has(accountId)) {
                return false;
            }
            seen.add(accountId);
            return true;
        });
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
            renderEmpty('Введите ник игрока, SteamID/ссылку или Match ID в поиск сверху.');
            return;
        }

        const resolvedAccountId = resolveAccountInput(query);
        const isLikelyMatchId = /^\d{8,16}$/.test(query) && Number(query) >= 3000000000;
        const searchQuery = resolvedAccountId || query;

        // Для Match ID сразу показываем ссылку — даже если OpenDota лагает или матч ещё не в локальном кэше.
        const matchPromise = isLikelyMatchId
            ? Promise.resolve(renderMatch(query))
            : Promise.resolve('');

        const profilePromise = resolvedAccountId
            ? fetchJson(`/api/players/${encodeURIComponent(resolvedAccountId)}`)
                .then(profile => [{
                    account_id: resolvedAccountId,
                    personaname: profile?.profile?.personaname || profile?.personaname || `Игрок #${resolvedAccountId}`,
                    avatarfull: profile?.profile?.avatarfull || profile?.avatarfull || null,
                }])
                .catch(() => [{ account_id: resolvedAccountId, personaname: `Игрок #${resolvedAccountId}`, avatarfull: null }])
            : Promise.resolve([]);

        const shouldSearchPlayers = !resolvedAccountId && !isLikelyMatchId;
        const playersPromise = shouldSearchPlayers
            ? fetchJson(`/api/search?q=${encodeURIComponent(searchQuery)}`).catch(() => [])
            : Promise.resolve([]);

        const [matchHtml, directPlayers, searchedPlayers] = await Promise.all([matchPromise, profilePromise, playersPromise]);
        const playersHtml = renderPlayers(uniquePlayers([
            ...(Array.isArray(directPlayers) ? directPlayers : []),
            ...(Array.isArray(searchedPlayers) ? searchedPlayers : []),
        ]));
        const parts = [matchHtml, playersHtml].filter(Boolean);

        results.innerHTML = parts.length
            ? parts.join('')
            : '<div class="empty-state">Ничего не найдено.</div>';
    }

    runSearch();
}());
