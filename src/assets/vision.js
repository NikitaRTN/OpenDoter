(function () {
    const gate = document.querySelector('[data-stats-gate]');
    const content = document.querySelector('[data-stats-content]');
    const requestButton = document.querySelector('[data-stats-gate-request]');
    const statusNode = document.querySelector('[data-stats-gate-status]');
    const toast = document.querySelector('[data-stats-toast]');
    const config = window.detailedStatsGate || {};
    let statusPollTimer = 0;

    function notify(message, state) {
        if (!toast) {
            return;
        }

        toast.textContent = message;
        toast.dataset.state = state || '';
        toast.hidden = false;
        window.clearTimeout(notify.timeout);
        notify.timeout = window.setTimeout(() => {
            toast.hidden = true;
        }, 4200);
    }

    function setGateStatus(message, state) {
        if (!statusNode) {
            return;
        }

        statusNode.textContent = message;
        statusNode.dataset.state = state || '';
    }

    function setGatePending(pending) {
        if (!requestButton) {
            return;
        }

        requestButton.disabled = pending;
        requestButton.textContent = pending ? 'Обработка...' : 'Запросить обработку';
    }

    function stopStatusPolling() {
        if (!statusPollTimer) {
            return;
        }

        window.clearTimeout(statusPollTimer);
        statusPollTimer = 0;
    }

    function showContent() {
        if (gate) {
            gate.hidden = true;
        }
        if (content) {
            content.hidden = false;
        }
    }

    function showGate() {
        if (gate) {
            gate.hidden = false;
        }
        if (content) {
            content.hidden = true;
        }
    }

    function isDoneStatus(data) {
        return data && data.state === 'done';
    }

    async function loadStatus() {
        if (!config.statusUrl) {
            throw new Error('Не задан адрес проверки статуса.');
        }

        const response = await fetch(config.statusUrl, {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    async function refreshGateStatus() {
        try {
            const data = await loadStatus();
            if (isDoneStatus(data)) {
                stopStatusPolling();
                setGatePending(false);
                showContent();
                notify('Подробная статистика доступна.', 'done');
                return true;
            }

            showGate();
            setGateStatus(data.message || 'Подробная статистика пока недоступна.', data.state || 'pending');
            return false;
        } catch (error) {
            showGate();
            setGateStatus('Не удалось проверить статус обработки.', 'error');
            notify(`Ошибка проверки статуса: ${error.message}`, 'error');
            return false;
        }
    }

    async function pollGateStatus() {
        const done = await refreshGateStatus();
        if (done) {
            window.setTimeout(() => window.location.reload(), 900);
            return;
        }

        setGatePending(true);
        statusPollTimer = window.setTimeout(pollGateStatus, 3000);
    }

    function startStatusPolling() {
        stopStatusPolling();
        setGatePending(true);
        pollGateStatus();
    }

    async function requestProcessing() {
        if (!config.matchId || !config.parseUrl) {
            setGateStatus('Не удалось определить матч для обработки.', 'error');
            notify('Не удалось определить матч для обработки.', 'error');
            return;
        }

        setGatePending(true);
        setGateStatus('Отправляем матч на обработку...', 'pending');

        try {
            const response = await fetch(config.parseUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    match_id: String(config.matchId),
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            notify('Матч отправлен на обработку.', 'done');
            setGateStatus('Запрос отправлен. Проверяем готовность...', 'pending');
            startStatusPolling();
        } catch (error) {
            setGateStatus('Ошибка запроса обработки.', 'error');
            notify(`Ошибка запроса обработки: ${error.message}`, 'error');
            setGatePending(false);
        }
    }

    if (requestButton) {
        requestButton.addEventListener('click', requestProcessing);
    }

    if (gate && content) {
        if (config.isAvailable) {
            showContent();
            return;
        }

        showGate();
        refreshGateStatus();
    }
}());

(function () {
    const root = document.querySelector('[data-vision-root]');
    if (!root || !Array.isArray(window.visionWards)) {
        return;
    }

    const wards = window.visionWards;
    const slider = root.querySelector('[data-time-slider]');
    const timeTitle = root.querySelector('[data-time-title]');
    const activeCount = root.querySelector('[data-active-count]');
    const summary = root.querySelector('[data-vision-summary]');
    const killedStat = root.querySelector('[data-stat-killed]');
    const expiredStat = root.querySelector('[data-stat-expired]');
    const totalStat = root.querySelector('[data-stat-total]');
    const teamInputs = Array.from(root.querySelectorAll('[data-filter-team]'));
    const kindInputs = Array.from(root.querySelectorAll('[data-filter-kind]'));
    const playerInputs = Array.from(root.querySelectorAll('[data-filter-player]'));
    const markers = new Map(Array.from(document.querySelectorAll('[data-ward-id]')).map((node) => [node.dataset.wardId, node]));
    const rows = new Map(Array.from(document.querySelectorAll('[data-ward-row]')).map((node) => [node.dataset.wardRow, node]));

    function formatTime(seconds) {
        const sign = seconds < 0 ? '-' : '';
        const value = Math.abs(Number(seconds) || 0);
        const minutes = Math.floor(value / 60);
        return `${sign}${minutes}:${String(value % 60).padStart(2, '0')}`;
    }

    function selectedValues(inputs, key) {
        return new Set(inputs.filter((input) => input.checked).map((input) => input.dataset[key]));
    }

    function isVisibleAtTime(ward, selectedTime) {
        if (selectedTime === 0) {
            return true;
        }

        return ward.placed <= selectedTime && (ward.removed === null || ward.removed > selectedTime);
    }

    function render() {
        const selectedTime = Number(slider.value);
        const allTime = selectedTime === 0;
        const teams = selectedValues(teamInputs, 'filterTeam');
        const kinds = selectedValues(kindInputs, 'filterKind');
        const players = selectedValues(playerInputs, 'filterPlayer');
        let visibleCount = 0;
        let filteredTotal = 0;
        let killedCount = 0;
        let expiredCount = 0;

        wards.forEach((ward) => {
            const passesFilter = teams.has(ward.team) && kinds.has(ward.kind) && players.has(ward.owner_key);
            const visible = passesFilter && isVisibleAtTime(ward, selectedTime);
            const marker = markers.get(ward.id);
            const row = rows.get(ward.id);

            if (passesFilter) {
                filteredTotal += 1;
            }
            if (passesFilter && ward.status === 'killed') {
                killedCount += 1;
            }
            if (passesFilter && ward.status === 'expired') {
                expiredCount += 1;
            }
            if (visible) {
                visibleCount += 1;
            }

            if (marker) {
                marker.hidden = !visible;
                marker.classList.toggle('is-past', allTime && ward.removed !== null);
                marker.classList.toggle('is-killed', ward.status === 'killed');
            }
            if (row) {
                row.hidden = !passesFilter || (!allTime && !visible);
            }
        });

        timeTitle.textContent = allTime ? 'За все время' : `На ${formatTime(selectedTime)}`;
        activeCount.textContent = visibleCount;
        summary.textContent = allTime ? `${visibleCount} установок за матч` : `${visibleCount} активных вардов`;
        totalStat.textContent = filteredTotal;
        killedStat.textContent = killedCount;
        expiredStat.textContent = expiredCount;
    }

    slider.addEventListener('input', render);
    teamInputs.concat(kindInputs, playerInputs).forEach((input) => input.addEventListener('change', render));
    render();
}());
