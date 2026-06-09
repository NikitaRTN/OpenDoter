(function () {
    const trigger = document.querySelector('[data-parse-trigger]');
    const statusNode = document.querySelector('[data-parse-status]');
    const toast = document.querySelector('[data-stats-toast]');
    const config = window.matchParseGate || {};
    let pollTimer = 0;

    function notify(message, state) {
        if (!toast) {
            return;
        }
        toast.textContent = message;
        toast.dataset.state = state || '';
        toast.hidden = false;
        window.clearTimeout(notify.timer);
        notify.timer = window.setTimeout(() => {
            toast.hidden = true;
        }, 4200);
    }

    function setStatus(message, state) {
        if (!statusNode) {
            return;
        }
        statusNode.textContent = message;
        statusNode.dataset.state = state || '';
    }

    function setPending(pending) {
        if (!trigger) {
            return;
        }
        trigger.disabled = pending;
        trigger.textContent = pending ? 'Обработка...' : 'Запросить обработку';
    }

    function stopPolling() {
        if (!pollTimer) {
            return;
        }
        window.clearTimeout(pollTimer);
        pollTimer = 0;
    }

    async function loadStatus() {
        if (!config.statusUrl) {
            throw new Error('Не задан адрес проверки статуса.');
        }
        const response = await fetch(config.statusUrl, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    }

    async function pollStatus() {
        try {
            const data = await loadStatus();
            if (data && (data.state === 'done' || data.state === 'error')) {
                stopPolling();
                setPending(false);
                if (data.state === 'done') {
                    setStatus('Готово! Перезагружаем страницу...', 'done');
                    notify('Обработка завершена.', 'done');
                    window.setTimeout(() => window.location.reload(), 700);
                    return;
                }
                setStatus(data.message || 'Обработка завершилась с ошибкой.', 'error');
                notify('Не удалось обработать матч.', 'error');
                return;
            }
            setStatus(data && data.message ? data.message : 'Обработка...', 'pending');
            pollTimer = window.setTimeout(pollStatus, 3000);
        } catch (error) {
            setStatus('Не удалось проверить статус: ' + error.message, 'error');
            pollTimer = window.setTimeout(pollStatus, 5000);
        }
    }

    async function requestParse() {
        if (!config.matchId || !config.parseUrl) {
            setStatus('Не удалось определить матч для обработки.', 'error');
            notify('Не удалось определить матч для обработки.', 'error');
            return;
        }

        setPending(true);
        setStatus('Отправляем матч на обработку...', 'pending');

        try {
            const response = await fetch(config.parseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ match_id: String(config.matchId) }),
            });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            setStatus('Запрос отправлен. Ждём готовности...', 'pending');
            notify('Матч поставлен в очередь на обработку.', 'done');
            pollStatus();
        } catch (error) {
            setStatus('Ошибка запроса обработки: ' + error.message, 'error');
            notify('Ошибка запроса обработки: ' + error.message, 'error');
            setPending(false);
        }
    }

    // При загрузке страницы сразу подтягиваем текущий статус —
    // если парсинг уже идёт, сразу подключаемся к опросу,
    // если уже выполнен — перезагружаем, чтобы получить полные данные.
    async function bootstrap() {
        try {
            const data = await loadStatus();
            if (data && data.state === 'done') {
                setStatus('Матч уже обработан. Перезагружаем...', 'done');
                window.setTimeout(() => window.location.reload(), 500);
                return;
            }
            if (data && data.state === 'running') {
                setPending(true);
                setStatus('Обработка уже идёт: ' + (data.message || '...'), 'pending');
                pollStatus();
                return;
            }
            if (data && data.state === 'error') {
                setStatus('Предыдущая попытка: ' + (data.message || 'ошибка'), 'error');
            }
        } catch (error) {
            // Тихий провал — пользователь сам нажмёт кнопку.
        }
    }

    if (trigger) {
        trigger.addEventListener('click', requestParse);
    }
    bootstrap();
}());
