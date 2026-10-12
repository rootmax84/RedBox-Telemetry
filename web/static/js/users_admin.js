"use strict";

function submitForm(el) {
    const submitBtn = el.querySelector('button[type="submit"]');

    if (submitBtn.disabled) {
        return false;
    }

    submitBtn.disabled = true;

    $("#wait_layout").show();

    fetch(el.getAttribute("action"), {
        method: el.method,
        body: new FormData(el),
        redirect: 'manual',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(response => {
        if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
            location.href = '/logout';
            return null;
        }
        if (response.status === 401 || response.status === 419 || response.status === 403) {
            location.href = '/logout';
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

            if (data.reload) { location.href = '/logout'; return; }

            // 202 + task_id — тяжёлая задача стала в очередь.
            if (data.status === 'accepted' && data.task_id) {
                $("#wait_layout").hide();

                const alreadyTracked = (typeof heavyTasksMeta !== 'undefined')
                    && heavyTasksMeta.has(data.task_id);

                if (alreadyTracked) {
                    submitBtn.disabled = false;
                    xhrResponse(data.message || 'OK');
                    return;
                }

                let pollStarted = false;
                try {
                    pollHeavyTask(data.task_id, {
                        taskType: data.type || null,
                        onDone: () => {
                            submitBtn.disabled = false;
                            xhrResponse(data.message || 'OK');
                        },
                    });
                    pollStarted = true;
                } catch (e) {
                    serverError(e.message);
                }

                if (!pollStarted) {
                    submitBtn.disabled = false;
                }
                return;
            }

            // Обычный JSON-ответ (ошибки и т.п.)
            $("#wait_layout").hide();
            submitBtn.disabled = false;
            xhrResponse(data.message || data.error || 'OK');
            return;
        }

        $("#wait_layout").hide();
        xhrResponse(result.text);
        setTimeout(() => {
            submitBtn.disabled = false;
        }, 1000);
    })
    .catch(err => {
        serverError(err.message);
        setTimeout(() => {
            submitBtn.disabled = false;
        }, 1000);
    });

    return false;
}
