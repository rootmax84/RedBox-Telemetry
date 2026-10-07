/* global $, jQuery, localization, redDialog, serverError, sortMergeDel, MERGE_CONFIG */

$(document).ready(() => {

    if (MERGE_CONFIG.noSessions) {
        const btn = document.getElementById('merge-btn');
        if (btn) btn.disabled = true;
    }

    let total = 0;

    updateTotalDatapoints();

    $(".session-checkbox").on("change", function () {
        updateTotalDatapoints();
    });

    function updateTotalDatapoints() {
        let sum = 0;
        $(".session-checkbox:checked").each(function () {
            sum += parseInt($(this).data("sessionsize"));
            total = sum;
        });
    }

    $("#merge-btn").on("click", (e) => {
        e.preventDefault();
        const checkedCount = $('input[type="checkbox"]:checked').length;
        if (checkedCount > 1) {
            mergeSession();
        } else {
            noSel();
        }
    });

    sortMergeDel();

    function noSel() {
        let dialogOpt = {
            title: localization.key['dialog.confirm'],
            btnClassSuccessText: "OK",
            btnClassFail: "hidden",
            message: localization.key['dialog.no.select']
        };
        redDialog.make(dialogOpt);
    }

    function mergeSession() {
        const mergedSession = document.querySelector('input[type="checkbox"].session-checkbox[disabled]');
        let msDate = "";
        if (mergedSession) {
            const checkboxCell = mergedSession.closest('td');
            const nextCell = checkboxCell.nextElementSibling;
            if (nextCell) {
                msDate = nextCell.textContent.trim();
            } else {
                msDate = MERGE_CONFIG.mergesession;
            }
        }

        if (!msDate.length) {
            serverError();
            return;
        }

        let maximum = MERGE_CONFIG.mergeMax;
        let oversize = total > maximum;
        let dialogOpt = {
            title: oversize ? localization.key['dialog.merge.big.title'] : localization.key['dialog.confirm'],
            message: oversize
                ? `${localization.key['dialog.merge.big.msg']} ${maximum / 1000}k ${localization.key['dialog.merge.big.datapoints']}<br>${localization.key['dialog.merge.big.sel']} ${total / 1000}k`
                : `${localization.key['dialog.merge.sessions']} (${msDate})?`,
            btnClassSuccessText: oversize ? "OK" : localization.key['btn.yes'],
            btnClassFailText: localization.key['btn.no'],
            btnClassFail: oversize ? "hidden" : "btn btn-info btn-sm",
            onResolve: function () {
                if (oversize) return;

                const form = document.getElementById("formmerge");
                const fd   = new FormData();

                // Inline-путь: показать спиннер, пока идёт чанковый UPDATE logs.
                // Heavy-путь: спрячем его чуть ниже — у pollHeavyTask свой индикатор.
                $("#wait_layout").show();

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
                if (!fd.has('mergesession')) fd.append('mergesession', '1');

                fetch('merge_sessions.php', {
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
                    // Не JSON — например, редирект на catch.php (сессия истекла)
                    return r.text().then(t => ({ text: t }));
                })
                .then(result => {
                    if (result.json) {
                        const data = result.json;
                        if (data.reload) { location.href = '.?logout=true'; return; }

                        // Heavy path: задача ушла в воркер
                        if (data.status === 'accepted' && data.task_id) {
                            $("#wait_layout").hide();
                            pollHeavyTask(data.task_id, {
                                taskType: 'merge_sessions',
                                onDone: (res) => {
                                    const newId = (res && res.new_session) ? res.new_session : null;
                                    if (newId) {
                                        location.href = '.?id=' + encodeURIComponent(newId);
                                    } else {
                                        location.reload();
                                    }
                                },
                            });
                            return;
                        }

                        // Inline path: сервер сам всё сделал (Redis был недоступен)
                        if (data.status === 'done' && data.new_session) {
                            location.href = '.?id=' + encodeURIComponent(data.new_session);
                            return;
                        }

                        serverError(data.error || 'Unknown response');
                        return;
                    }

                    // Fallback на совсем крайний случай (не должно случиться)
                    location.reload();
                })
                .catch(err => {
                    serverError(err.message);
                });
            }
        };
        redDialog.make(dialogOpt);
    }
});
