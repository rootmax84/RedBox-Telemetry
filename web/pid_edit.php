<?php

require_once('db.php');
require_once('auth_user.php');
require_once('creds.php');
require_once('db_limits.php');

$excludedIds = ['kff1005', 'kff1006', 'kff1007'];
$excludedIdsString = implode(',', array_map(fn($id) => "'$id'", $excludedIds));

$query = "SELECT id, description, units, populated, stream, favorite 
          FROM $db_pids_table 
          WHERE id NOT IN ($excludedIdsString) 
          ORDER BY description";

$keydata = $db->query($query)->fetch_all(MYSQLI_ASSOC);

$db->close();
include("head.php");
?>
    <body>
    <script>
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
    </script>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
            <div class="storage-usage-img" onclick></div>
            <label id="storage-usage" l10n='stor.usage'><span><?php echo $db_used;?></span></label>
        <?php } ?>
        <div class="container">
            <div id="theme-switch"></div>
            <div class="navbar-header">
                <a class="navbar-brand" href="."><div id="redhead">RedB<img src="static/img/logo.svg" alt style="height:11px;">x</div> Telemetry</a>
                <span title="logout" class="navbar-brand logout" onClick="logout()"></span>
            </div>
        </div>
    </div>
    <form style="padding:50px 0 0;" method="POST" action="pid_commit.php" onsubmit="return submitForm(this);">
        <div style="padding:10px; display:flex; justify-content:center;">
            <button class="btn btn-info btn-sm" type="submit" type="submit" l10n="btn.apply"></button>
        </div>
        <table class="table table-del-merge-pid">
            <thead>
                <tr>
                    <th l10n="p.table.id"></th>
                    <th l10n="p.table.desc"></th>
                    <th l10n="p.table.units"></th>
                    <th l10n="p.table.chart"></th>
                    <th l10n="p.table.stream"></th>
                    <th l10n="p.table.fav"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keydata as $i => $keycol) { ?>
                    <tr<?php echo ($i & 1) ? ' class="odd"' : ''; ?> data-pid=<?php echo $keycol['id']; ?>>
                        <td id="id:<?php echo $keycol['id']; ?>"><span class='delete-icon' onclick="event.stopPropagation(); deletePID('<?php echo $keycol['id']; ?>')">&times;</span><?php echo $keycol['id']; ?></td>
                        <td id="description:<?php echo $keycol['id']; ?>" contenteditable="true"><?php echo $keycol['description']; ?></td>
                        <td id="units:<?php echo $keycol['id']; ?>" contenteditable="true"><?php echo $keycol['units']; ?></td>
                        <td><input type="checkbox" id="populated:<?php echo $keycol['id']; ?>"<?php if ($keycol['populated']) echo " checked"; ?>></td>
                        <td><input type="checkbox" id="stream:<?php echo $keycol['id']; ?>"<?php if ($keycol['stream']) echo " checked"; ?>></td>
                        <td><input type="checkbox" id="favorite:<?php echo $keycol['id']; ?>"<?php if ($keycol['favorite']) echo " checked"; ?>></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </form>
    <br>
</body>
</html>