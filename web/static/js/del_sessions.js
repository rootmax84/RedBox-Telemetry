$(document).ready(()=> {
    $("#del-btn").on("click",(e)=> {
        e.preventDefault();
        let isChecked = $("input[type=checkbox]").is(":checked");
        if (!isChecked) {
            noSel();
        }
        else delSessions();
    });
    sortMergeDel();
 });

function delSessions() {
 let dialogOpt = {
    title: localization.key['dialog.confirm'],
    btnClassSuccessText: localization.key['btn.yes'],
    btnClassFailText: localization.key['btn.no'],
    btnClassFail: "btn btn-info btn-sm",
    message : localization.key['dialog.del.sessions'],
    onResolve: function(){
     $("#wait_layout").show();
     document.getElementById("formdel").submit();
    }
 };
 redDialog.make(dialogOpt);
}

function noSel() {
 let dialogOpt = {
    title: localization.key['dialog.confirm'],
    btnClassSuccessText: "OK",
    btnClassFail: "hidden",
    message : localization.key['dialog.no.select']
 };
 redDialog.make(dialogOpt);
}

function toggle(source) {
    let checkboxes = document.querySelectorAll('.table-del-merge-pid input[type="checkbox"]');
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i] != source)
            checkboxes[i].checked = source.checked;
    }
}
