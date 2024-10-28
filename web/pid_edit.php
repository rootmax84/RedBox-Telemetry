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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include("head.php");?>
    <script>
        function submitForm(form) {
            var formData = new FormData(form);

            document.querySelectorAll("td[contenteditable=true], input[type='checkbox'], select").forEach(function(el) {
                var fieldPid = el.getAttribute("id");
                var value;
                if (el.type === "checkbox") {
                    value = el.checked;
                } else {
                    value = el.value || el.textContent;
                }
                formData.append(fieldPid, value);
            });

            var xhr = new XMLHttpRequest();
            xhr.onload = function() {
                xhrResponse(xhr.responseText);
            };
            xhr.open(form.method, form.getAttribute("action"));
            xhr.send(formData);
            return false;
        }

        function xhrResponse(text) {
            var dialogOpt = {
                title: "Result",
                message: text,
                btnClassSuccessText: "OK",
                btnClassFail: "hidden",
            };
            redDialog.make(dialogOpt);

        }
    </script>
</head>
<body>
    <div class="navbar navbar-default navbar-fixed-top navbar-inverse">
        <?php if (!isset($_SESSION['admin']) && $limit > 0) {?>
            <label id="storage-usage">Storage usage: <?php echo $db_used;?></label>
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
            <input class="btn btn-info btn-sm" type="submit" value="Apply" type="submit">
        </div>
        <table class="table table-del-merge-pid">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Description</th>
                    <th>Units</th>
                    <th>Chart</th>
                    <th>Stream</th>
                    <th>Favorite</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($keydata as $i => $keycol) { ?>
                    <tr<?php echo ($i & 1) ? ' class="odd"' : ''; ?>>
                        <td id="id:<?php echo $keycol['id']; ?>"><?php echo $keycol['id']; ?></td>
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