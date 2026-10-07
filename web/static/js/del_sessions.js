$(document).ready(() => {
    $("#del-btn").on("click", (e) => {
        e.preventDefault();
        let isChecked = $("input[type=checkbox]").is(":checked");
        if (!isChecked) {
            noSel();
        } else {
            delSessions();
        }
    });
    sortMergeDel();
});

function delSessions() {
    let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message: localization.key['dialog.del.sessions'],
        onResolve: function () {
            const form = document.getElementById("formdel");
            const fd   = new FormData();

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

            // Inline-путь: показать спиннер, пока PHP молотит DELETE чанками.
            // Heavy-путь: спрячем его чуть ниже — у pollHeavyTask свой индикатор.
            $("#wait_layout").show();

            form.querySelectorAll('input').forEach(inp => {
                if (!inp.name) return;
                if (inp.type === 'checkbox') {
                    if (inp.checked) fd.append(inp.name, '1');
                } else if (inp.type === 'radio') {
                    if (inp.checked) fd.append(inp.name, inp.value);
                } else if (inp.value !== '') {
                    fd.append(inp.name, inp.value);
                }
            });
            if (!fd.has('delsession')) fd.append('delsession', '1');

            fetch('del_sessions.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => {
                const ct = r.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return r.json().then(j => ({ json: j }));
                }
                return r.text().then(t => ({ text: t }));
            })
            .then(result => {
                if (result.json) {
                    const data = result.json;
                    if (data.reload) { location.href = '.?logout=true'; return; }

                    if (data.status === 'accepted' && data.task_id) {
                        // Heavy-путь: отдаём эстафету pollHeavyTask
                        $("#wait_layout").hide();
                        // pollHeavyTask сам поднимет свой индикатор
                        pollHeavyTask(data.task_id, {
                            taskType: 'delete_sessions',
                            onDone: () => location.reload(),
                        });
                        return;
                    }
                    if (data.status === 'done') {
                        location.reload();
                        return;
                    }
                    serverError(data.error || 'Unknown response');
                    return;
                }
                // Fallback: сервер отдал HTML → перезагружаемся
                location.reload();
            })
            .catch(err => {
                serverError(err.message);
            });
        }
    };
    redDialog.make(dialogOpt);
}

function noSel() {
    let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: "OK",
        btnClassFail: "hidden",
        message: localization.key['dialog.no.select']
    };
    redDialog.make(dialogOpt);
}

function toggle(source) {
    let checkboxes = document.querySelectorAll('.table-del-merge-pid input[type="checkbox"]');
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i] != source) {
            checkboxes[i].checked = source.checked;
        }
    }
}
