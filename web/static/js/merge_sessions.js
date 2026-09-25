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
                if (!oversize) {
                    $("#wait_layout").show();
                    document.getElementById("formmerge").submit();
                }
            }
        };
        redDialog.make(dialogOpt);
    }
});
