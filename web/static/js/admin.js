'use strict';

function maintenance() {
    let mode;

    fetch("/maintenance?mode", { method: "POST" })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(data => {
            $("#wait_layout").hide();
            mode = data;

            if (!mode.length) return;

            let dialogOpt = {
                title: localization.key['dialog.maintenance.title'],
                message: `${localization.key['dialog.maintenance.status']} ${mode}`,
                btnClassSuccessText: localization.key['dialog.maintenance.en'],
                btnClassFailText: localization.key['dialog.maintenance.dis'],
                btnClassFail: "btn btn-info btn-sm",

                onResolve: function () {
                    $("#wait_layout").show();

                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const fd = new FormData();
                    if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

                    fetch("/maintenance?enable", { method: "POST", body: fd })
                        .then(r => {
                            $("#wait_layout").hide();
                            if (!r.ok) throw new Error(`HTTP ${r.status}`);
                            return r.text();
                        })
                        .then(text => xhrResponse(text || 'OK'))
                        .catch(err => serverError(err.message));
                },

                onReject: function () {
                    $("#wait_layout").show();

                    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                    const fd = new FormData();
                    if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

                    fetch("/maintenance?disable", { method: "POST", body: fd })
                        .then(r => {
                            $("#wait_layout").hide();
                            if (!r.ok) throw new Error(`HTTP ${r.status}`);
                            return r.text();
                        })
                        .then(text => xhrResponse(text || 'OK'))
                        .catch(err => serverError(err.message));
                }
            };

            redDialog.make(dialogOpt);
        })
        .catch(error => {
            serverError(error);
            $("#wait_layout").hide();
        });
}

function initTableSorting(tableSelector) {
  var originalRows = $(tableSelector + " tbody tr").toArray();

  $(tableSelector + " thead th").not(":first, :last").each(function(index) {
    if (index === 0) {
      $(this).addClass("reset-sort").css("cursor", "pointer");
    } else {
      $(this).addClass("sortable").css("cursor", "pointer");
    }
  });

  $(tableSelector + " thead th.sortable").click(function() {
    var table = $(this).parents("table").eq(0);
    var index = $(this).index();
    var rows = table.find("tbody tr").toArray().sort(comparer(index));

    this.asc = !this.asc;
    if (!this.asc) {
      rows = rows.reverse();
    }

    table.find("th").removeClass("sorted-asc sorted-desc");
    $(this).addClass(this.asc ? "sorted-asc" : "sorted-desc");

    table.find("tbody").empty();
    for (var i = 0; i < rows.length; i++) {
      table.find("tbody").append(rows[i]);
    }

    updateRowNumbers(table);
  });

  $(tableSelector + " thead th.reset-sort").click(function() {
    var table = $(this).parents("table").eq(0);

    table.find("th").removeClass("sorted-asc sorted-desc");

    table.find("tbody").empty();
    for (var i = 0; i < originalRows.length; i++) {
      table.find("tbody").append(originalRows[i]);
    }

    updateRowNumbers(table);
  });

  function comparer(index) {
    return function(a, b) {
      var valA = getCellValue(a, index);
      var valB = getCellValue(b, index);

      if (index === 2 || index === 3) {
        return parseFloat(valA === "-" ? 0 : valA) - parseFloat(valB === "-" ? 0 : valB);
      } else if (index === 4 || index === 5) {
        return parseDateOrDefault(valA) - parseDateOrDefault(valB);
      } else {
        return valA.toString().localeCompare(valB.toString());
      }
    };
  }

  function getCellValue(row, index) {
    var cell = $(row).children("td").eq(index);
    return cell.text().trim();
  }

  function parseDateOrDefault(dateStr) {
    if (dateStr === "-") {
      return 0;
    }

    var parts = dateStr.split(" ");
    var dateParts = parts[0].split("-");
    var timeParts = parts[1].split(":");

    return new Date(
      parseInt(dateParts[0]),
      parseInt(dateParts[1]) - 1,
      parseInt(dateParts[2]),
      parseInt(timeParts[0]),
      parseInt(timeParts[1]),
      parseInt(timeParts[2])
    ).getTime();
  }

  function updateRowNumbers(table) {
    table.find("tbody tr").each(function(index) {
      $(this).children("td").first().text(index + 1);
    });
  }

  if ($("head style.table-sort-indicators").length === 0) {
    $("<style>")
      .prop("type", "text/css")
      .addClass("table-sort-indicators")
      .html(`
        th.sorted-asc::after { content: " ▲"; }
        th.sorted-desc::after { content: " ▼"; }
      `)
      .appendTo("head");
  }
}

function adminUserDelete(username) {
    let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message: `${localization.key['admin.del.title']} ${username}?`,
        onResolve: function () {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const formData = new FormData();
            formData.append('del_login', username);
            formData.append('csrf_token', csrfToken);

            // .fetch-data больше НЕ трогаем — индикатор создаст pollHeavyTask
            fetch('/admin/users/handler', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(response => {
                const ct = response.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return response.json().then(j => ({ json: j }));
                }
                return response.text().then(t => ({ text: t }));
            })
            .then(result => {
                if (result.json) {
                    const data = result.json;
                    if (data.reload) { location.href = '/logout'; return; }

                    if (data.status === 'accepted' && data.task_id) {
                        pollHeavyTask(data.task_id, {
                            taskType: 'delete_user',
                            onDone: () => {
                                xhrResponse(data.message || 'OK');
                                const row = document.querySelector(`tr[data-username="${username}"]`);
                                if (row) {
                                    row.style.pointerEvents = 'none';
                                    row.style.opacity = '0.5';
                                }
                            },
                        });
                        return;
                    }
                    xhrResponse(data.message || data.error || 'OK');
                    return;
                }

                // Fallback: HTML/plain-текст
                xhrResponse(result.text);
                const row = document.querySelector(`tr[data-username="${username}"]`);
                if (row) {
                    row.style.pointerEvents = 'none';
                    row.style.opacity = '0.5';
                }
            })
            .catch(error => {
                serverError(error.message);
            });
        }
    };
    redDialog.make(dialogOpt);
}
