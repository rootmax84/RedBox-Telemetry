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

            // Показать лоадер красным до POST
            $(".fetch-data").css({
                'display': 'block',
                'background-color': 'red',
            });

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
                        // pollHeavyTask сам переключит лоадер (display:block + red)
                        pollHeavyTask(data.task_id, {
                            onDone: () => location.reload(),
                        });
                        return;
                    }
                    if (data.status === 'done') {
                        $(".fetch-data").css({
                            'display': 'none',
                            'background-color': 'currentColor',
                        });
                        location.reload();
                        return;
                    }
                    $(".fetch-data").css({
                        'display': 'none',
                        'background-color': 'currentColor',
                    });
                    serverError(data.error || 'Unknown response');
                    return;
                }
                $(".fetch-data").css({
                    'display': 'none',
                    'background-color': 'currentColor',
                });
                location.reload();
            })
            .catch(err => {
                $(".fetch-data").css({
                    'display': 'none',
                    'background-color': 'currentColor',
                });
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
