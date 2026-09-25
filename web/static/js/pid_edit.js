function submitForm(form) {

    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn.disabled) {
        return false;
    }

    submitBtn.disabled = true;

    let formData = new FormData(form);

    document.querySelectorAll("td[contenteditable=true], input[type='checkbox'], select").forEach(function(el) {
        let fieldPid = el.getAttribute("id");
        if (!fieldPid) return;

        let value;
        if (el.type === "checkbox") {
            value = el.checked;
        } else {
            value = el.value || el.textContent;
        }
        formData.set(fieldPid, value);
    });

    fetch(form.getAttribute("action"), {
        method: form.method,
        body: formData
    })
    .then(response => response.text())
    .then(responseText => {
        xhrResponse(responseText);
        if (submitBtn) {
            setTimeout(() => {
                submitBtn.disabled = false;
            }, 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (submitBtn) {
            setTimeout(() => {
                submitBtn.disabled = false;
            }, 1000);
        }
    });

    return false;
}

$(document).on('keydown paste', '[contenteditable="true"]', function(e) {
    let max = $(this).is('[id^="description"]') ? 64 : 16;
    let currentText = $(this).text();

    if (e.type === 'paste') {
        setTimeout(() => {
            if ($(this).text().length > max) {
                $(this).text(currentText.substring(0, max));
            }
        }, 0);
        return true;
    }

    if (currentText.length >= max && 
        ![8, 46, 37, 38, 39, 40].includes(e.keyCode)) {
        return false;
    }
});

function deletePID(pid) {
    let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message: `${localization.key['dialog.pid.delete']} ${pid}?`,
        onResolve: function() {
            $("#wait_layout").show();
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const formData = new FormData();
            formData.append('delete', pid);
            formData.append('csrf_token', csrfToken);

            fetch('pid_commit.php', { method: 'POST', body: formData })
                .then(response => response.text())
                .then(text => {
                    $("#wait_layout").hide();
                    xhrResponse(text);
                    document.querySelector(`tr[data-pid="${pid}"]`)?.remove();
                })
                .catch(error => serverError(error.message));
        }
    };
    redDialog.make(dialogOpt);
}

function sortPID() {
    if ($("head style.table-sort-indicators").length === 0) {
        $("<style>")
            .prop("type", "text/css")
            .html(`
                th.sortable { cursor: pointer; }
                th.sorted-asc::after { content: " ▲"; }
                th.sorted-desc::after { content: " ▼"; }
            `)
            .appendTo("head");
    }

    $(".table-del-merge-pid thead th").not(":eq(2)").addClass("sortable");

    $(".table-del-merge-pid thead th.sortable").click(function() {
        const table = $(this).closest("table");
        const columnIndex = $(this).index();
        const rows = table.find("tbody tr").get();

        const isAscending = !$(this).hasClass("sorted-asc");
        table.find("th").removeClass("sorted-asc sorted-desc");
        $(this).addClass(isAscending ? "sorted-asc" : "sorted-desc");

        rows.sort((a, b) => {
            const valueA = getCellValue(a, columnIndex);
            const valueB = getCellValue(b, columnIndex);

            if (columnIndex === 0) {
                const hexA = valueA.substring(1);
                const hexB = valueB.substring(1);
                return parseInt(hexA, 16) - parseInt(hexB, 16);
            }
            else if (columnIndex === 1) {
                return valueA.localeCompare(valueB);
            }
            else if (columnIndex >= 3 && columnIndex <= 5) {
                return (valueA ? 1 : 0) - (valueB ? 1 : 0);
            }
        return 0;
    });

        if (!isAscending) rows.reverse();
        table.find("tbody").empty().append(rows);
    });

function getCellValue(row, index) {
    const cell = $(row).children("td").eq(index);
    if (index === 0) {
        return cell.contents()
            .filter(function() { return this.nodeType === 3; })
            .text().trim();
    }
    else if (index >= 3 && index <= 5) {
        return cell.find("input[type='checkbox']").is(":checked");
    }
    return cell.text().trim();
}

} $(function() { sortPID(); });
