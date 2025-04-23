<?php
if (isset($_GET['c'])) {
    $http_code = match($_GET['c']) {
        'loginfailed', 'csrffailed' => 401,
        'dberror' => 503,
        'maintenance' => 423,
        'noshare' => 404,
        'block', 'toomanyattempts' => 429,
        'error' => 500,
        default => 200,
    };
    http_response_code($http_code);
}

include("head.php");
?>
<body style="display:flex; justify-content:center; align-items:center; height:100vh">
    <div class="login login-form" id="login-form" style="width:fit-content; text-align:center">
    <?php
        if ($_GET['c'] == "disabled") { ?>
            <script>setTimeout(()=>{location.href='.?logout=true'}, 5000);</script>
            <h4 l10n='catch.disabled'></h4>
        <?php
        }
        else if ($_GET['c'] == "loginfailed") { ?>
            <script>setTimeout(()=>{location.href='.'}, 2000);</script>
            <h4 l10n='catch.loginfailed'></h4>
        <?php
        }
        else if ($_GET['c'] == "csrffailed") { ?>
            <script>setTimeout(()=>{location.href='.'}, 2000);</script>
            <h4 l10n='catch.csrf'></h4>
        <?php
        }
        else if ($_GET['c'] == "toomanyattempts") { ?>
            <script>setTimeout(()=>{location.href='.'}, 5000);</script>
            <h4 style="line-height:1.5" l10n='catch.banned'></h4>
        <?php
        }
        else if ($_GET['c'] == "dberror") { ?>
            <script>setTimeout(()=>{location.href='.'}, 10000);</script>
            <h4 l10n='catch.dberror'></h4>
        <?php
        }
        else if ($_GET['c'] == "maintenance") { ?>
            <script>setTimeout(()=>{location.href='.'}, 10000);</script>
            <h4 style="line-height:1.5" l10n='catch.maintenance'></h4>
        <?php
        }
        else if ($_GET['c'] == "noshare") { ?>
            <script>setTimeout(()=>{location.href='.'}, 5000);</script>
            <h4 l10n='catch.noshare'></h4>
        <?php
        }
        else if ($_GET['c'] == "block") { ?>
            <script>setTimeout(()=>{location.href='.'}, 10000);</script>
            <h4 style="line-height:1.5" l10n='catch.block'></h4>
        <?php
        }
        else if ($_GET['c'] == "error") { ?>
            <script>setTimeout(()=>{location.href='.?logout=true'}, 2000);</script>
            <h4 style="line-height:1.5" l10n='catch.error'></h4>
        <?php
        }
    ?>
    </div>
    <div class="login-background"></div>
   <script>
    $(document).ready(function(){
     $("#login-form").css({"opacity":"1"});
    });
   </script>
 </body>
</html>
