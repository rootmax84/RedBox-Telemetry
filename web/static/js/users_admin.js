"use strict";

function submitForm(el) {
    const submitBtn = el.querySelector('button[type="submit"]');

    if (submitBtn.disabled) {
        return false;
    }

    submitBtn.disabled = true;

    fetch(el.getAttribute("action"), {
        method: el.method,
        body: new FormData(el),
        redirect: 'manual',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(response => {
        if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
            location.href = '.?logout=true';
            return null;
        }
        if (response.status === 401 || response.status === 419 || response.status === 403) {
            location.href = '.?logout=true';
            return null;
        }

        const ct = response.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            return response.json().then(j => ({ json: j }));
        }
        return response.text().then(t => ({ text: t }));
    })
    .then(result => {
        if (result === null) return;

        // JSON-ответ
        if (result.json) {
            const data = result.json;

            if (data.reload) { location.href = '.?logout=true'; return; }

            // 202 + task_id — тяжёлая задача стала в очередь
            if (data.status === 'accepted' && data.task_id) {
                pollHeavyTask(data.task_id, {
                    taskType: data.type || null,
                    onDone: () => {
                        submitBtn.disabled = false;
                        xhrResponse(data.message || 'OK');
                        // Небольшая задержка, чтобы пользователь увидел диалог
                        // прежде чем таблица обновится (если решим делать reload).
                        // Для truncate/delete reload не делаем — админ видит форму.
                    },
                });
                return;
            }

            // Обычный JSON-ответ (ошибки и т.п.)
            submitBtn.disabled = false;
            xhrResponse(data.message || data.error || 'OK');
            return;
        }

        // Plain-text ответ (старый путь, например «Пароль изменён»)
        xhrResponse(result.text);
        setTimeout(() => {
            submitBtn.disabled = false;
        }, 1000);
    })
    .catch(error => {
        console.error('Error:', error);
        setTimeout(() => {
            submitBtn.disabled = false;
        }, 1000);
    });

    return false;
}
