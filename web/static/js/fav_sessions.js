function removeFavorite(id, str) {
    let dialogOpt = {
        title: localization.key['dialog.confirm'],
        btnClassSuccessText: localization.key['btn.yes'],
        btnClassFailText: localization.key['btn.no'],
        btnClassFail: "btn btn-info btn-sm",
        message: `${localization.key['func.del']} ${str}?`,
        onResolve: function() {
            $(".fetch-data").css("display", "block");
            fetch('favorite.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                document.querySelector(`tr[data-sid="${id}"]`)?.remove();
                if ($('#fav-table tbody tr').length === 0) {
                    $('<h3 style="text-align:center"></h3>').text(localization.key['fav.empty']).insertAfter('#fav-table');
                    document.getElementById('update_desc').disabled = true;
                }
            })
            .catch(err => {
                serverError(err);
            })
            .finally(() => {
                $(".fetch-data").css("display", "none");
            });
        }
    };
    redDialog.make(dialogOpt);
}

function updateDescriptions() {
    $(".fetch-data").css("display", "block");
    const updates = [];

    document.querySelectorAll('td[contenteditable="true"][data-sid]').forEach(td => {
        const sessionId = td.getAttribute('data-sid');
        const newDescription = td.textContent.trim();
        updates.push({
            id: sessionId,
            description: newDescription
        });
    });

    if (updates.length === 0) {
        $(".fetch-data").css("display", "none");
        return;
    }

    fetch('favorite.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ updates: updates })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        xhrResponse(localization.key['fav.desc.update']);
    })
    .catch(err => {
        serverError(err);
    })
    .finally(() => {
        $(".fetch-data").css("display", "none");
    });
}

// Initialize editable fields
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('update_desc').addEventListener('click', updateDescriptions);

    // Make description cells editable
    document.querySelectorAll('#fav-table tbody td:nth-child(4)').forEach(td => {
        td.setAttribute('contenteditable', 'true');
        td.setAttribute('data-sid', td.closest('tr').getAttribute('data-sid'));
    });
});

$(document).on('keydown paste', '[contenteditable="true"]', function(e) {
    let max = 64; // Maximum length for description
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

function sortFavorites() {
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

    $("#fav-table thead th").not(":first, :last").addClass("sortable");

    $("#fav-table thead th.sortable").click(function() {
        const table = $(this).closest("table");
        const columnIndex = $(this).index();
        const rows = table.find("tbody tr").get();

        const isAscending = !$(this).hasClass("sorted-asc");
        table.find("th").removeClass("sorted-asc sorted-desc");
        $(this).addClass(isAscending ? "sorted-asc" : "sorted-desc");

        rows.sort((a, b) => {
            const valueA = getFavCellValue(a, columnIndex);
            const valueB = getFavCellValue(b, columnIndex);

            if (columnIndex === 1) {
                const timeA = valueA.split(':').reduce((acc, time) => (60 * acc) + +time, 0);
                const timeB = valueB.split(':').reduce((acc, time) => (60 * acc) + +time, 0);
                return timeA - timeB;
            }
            else if (columnIndex === 2 || columnIndex === 3) {
                return valueA.localeCompare(valueB);
            }

            return 0;
        });

        if (!isAscending) rows.reverse();
        table.find("tbody").empty().append(rows);
    });

    function getFavCellValue(row, index) {
        const cell = $(row).children("td").eq(index);
        return cell.text().trim();
    }
}

$(function() {
    sortFavorites();
});
