(function () {
    const root = document.querySelector('[data-player-profile]');
    if (!root) {
        return;
    }

    const pathMatch = window.location.pathname.match(/\/players\/(\d+)/);
    const queryId = new URLSearchParams(window.location.search).get('id');
    const accountId = pathMatch ? pathMatch[1] : queryId;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        })[char]);
    }

    function formatTime(seconds) {
        const value = Math.max(0, Number(seconds) || 0);
        const minutes = Math.floor(value / 60);
        return `${minutes}:${String(value % 60).padStart(2, '0')}`;
    }

    function formatDate(timestamp) {
        if (!timestamp) return '-';
        const d = new Date(timestamp * 1000);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}.${month}.${year}`;
    }

    function rankTitle(tier) {
        const value = Number(tier) || 0;
        if (value <= 0) {
            return 'Не откалиброван';
        }
        const ranks = {
            1: 'Рекрут',
            2: 'Страж',
            3: 'Рыцарь',
            4: 'Герой',
            5: 'Легенда',
            6: 'Властелин',
            7: 'Божество',
            8: 'Титан',
        };
        const major = Math.floor(value / 10);
        const minor = value % 10;
        return major >= 8 ? 'Титан' : `${ranks[major] || 'Ранг'} [${minor}]`;
    }

    function heroName(heroId, heroes) {
        const hero = heroes[String(heroId)];
        return hero?.localized_name || hero?.name || 'Неизвестный герой';
    }

    function heroImg(heroId, heroes) {
        const hero = heroes[String(heroId)];
        if (!hero?.name) {
            return '';
        }
        return `https://cdn.cloudflare.steamstatic.com/apps/dota2/images/dota_react/heroes/${hero.name.replace('npc_dota_hero_', '')}.png`;
    }

    async function fetchJson(url) {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
    }

    function renderError(message) {
        root.innerHTML = `<section class="profile-panel"><div class="empty-state">${escapeHtml(message)}</div></section>`;
    }

    function renderProfile(profile, matches, heroes) {
        const data = profile.profile || {};
        const name = data.personaname || 'Игрок';
        const avatar = data.avatarfull || data.avatarmedium || '';
            const matchRows = matches.slice(0, 20).map((match) => {
            const isRadiant = Number(match.player_slot || 0) < 128;
            const won = Boolean(match.radiant_win) === isRadiant;
            const hImg = heroImg(match.hero_id, heroes);
            return `
                <tr>
                    <td><a class="player-name" href="/matches/${encodeURIComponent(match.match_id)}/overview">${escapeHtml(match.match_id)}</a></td>
                    <td>
                        <div class="player-cell compact">
                            ${hImg ? `<img class="hero-img" src="${escapeHtml(hImg)}" alt="">` : ''}
                            <span>${escapeHtml(heroName(match.hero_id, heroes))}</span>
                        </div>
                    </td>
                    <td class="col-center ${won ? 'radiant-title' : 'dire-title'}">${won ? 'Победа' : 'Поражение'}</td>
                    <td class="col-center">${Number(match.kills || 0)}/${Number(match.deaths || 0)}/${Number(match.assists || 0)}</td>
                    <td class="col-center">${formatTime(match.duration)}</td>
                    <td class="col-center">${formatDate(match.start_time)}</td>
                </tr>
            `;
        }).join('');

        document.title = `${name} - профиль`;
        root.innerHTML = `
            <section class="profile-panel">
                <div class="profile-main">
                    ${avatar ? `<img class="profile-avatar" src="${escapeHtml(avatar)}" alt="">` : ''}
                    <div>
                        <h1>${escapeHtml(name)}</h1>
                        <div class="profile-meta">
                            <span>Account ID: ${escapeHtml(data.account_id || accountId)}</span>
                            <span>${escapeHtml(rankTitle(profile.rank_tier))}</span>
                            ${profile.leaderboard_rank ? `<span>Leaderboard: ${escapeHtml(profile.leaderboard_rank)}</span>` : ''}
                        </div>
                    </div>
                </div>
                ${data.profileurl ? `<a class="profile-link" href="${escapeHtml(data.profileurl)}" target="_blank" rel="noreferrer">Steam</a>` : ''}
            </section>

            <section class="profile-panel">
                <div class="team-header">
                    <div>
                        <span class="team-title">Матчи игрока</span>
                        <span class="team-subtitle"> - выберите матч для просмотра</span>
                    </div>
                </div>
                <table class="overview-table profile-matches-table">
                    <thead>
                        <tr>
                            <th>Матч</th>
                            <th>Герой</th>
                            <th class="col-center">Результат</th>
                            <th class="col-center">КДА</th>
                            <th class="col-center">Длительность</th>
                            <th class="col-center">Дата</th>
                        </tr>
                    </thead>
                    <tbody>${matchRows}</tbody>
                </table>
            </section>
        `;
    }

    async function loadProfile() {
        if (!accountId) {
            renderError('Не указан Account ID игрока.');
            return;
        }

        try {
            const [profile, matches, heroes] = await Promise.all([
                fetchJson(`https://api.opendota.com/api/players/${encodeURIComponent(accountId)}`),
                fetchJson(`https://api.opendota.com/api/players/${encodeURIComponent(accountId)}/matches?limit=20`),
                fetchJson('https://api.opendota.com/api/constants/heroes'),
            ]);
            renderProfile(profile, Array.isArray(matches) ? matches : [], heroes || {});
        } catch (error) {
            renderError('Не удалось загрузить профиль. Попробуйте обновить страницу.');
        }
    }

    loadProfile();
}());
