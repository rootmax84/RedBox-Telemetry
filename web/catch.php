<?php
$c = $_GET['c'] ?? '';

if ($c !== '') {
    $http_code = match($c) {
        'loginfailed', 'csrffailed' => 401,
        'disabled' => 403,
        'dberror' => 503,
        'maintenance' => 423,
        'noshare' => 404,
        'block', 'toomanyattempts' => 429,
        'error' => 500,
        default => 204,
    };
    http_response_code($http_code);
} else {
    http_response_code(204);
}

include_once __DIR__ . '/src/head.php';
?>
<body style="display:flex; justify-content:center; align-items:center; height:100vh">
    <div class="login login-form" id="login-form" style="width:fit-content; text-align:center">
    <?php
        if ($c == "disabled") { ?>
            <script>setTimeout(()=>{location.href='.?logout=true'}, 5000);</script>
            <h4 l10n='catch.disabled'></h4>
        <?php
        }
        elseif ($c == "loginfailed") { ?>
            <script>setTimeout(()=>{location.href='.'}, 2000);</script>
            <h4 l10n='catch.loginfailed'></h4>
        <?php
        }
        elseif ($c == "csrffailed") { ?>
            <script>setTimeout(()=>{location.href='.'}, 2000);</script>
            <h4 l10n='catch.csrf'></h4>
        <?php
        }
        elseif ($c == "toomanyattempts") { ?>
            <script>setTimeout(()=>{location.href='.'}, 5000);</script>
            <h4 style="line-height:1.5" l10n='catch.banned'></h4>
        <?php
        }
        elseif ($c == "dberror") { ?>
            <script>setTimeout(()=>{location.href='.'}, 10000);</script>
            <h4 l10n='catch.dberror'></h4>
        <?php
        }
        elseif ($c == "maintenance") { ?>
            <script>setTimeout(()=>{location.href='.'}, 10000);</script>
            <h4 style="line-height:1.5" l10n='catch.maintenance'></h4>
        <?php
        }
        elseif ($c == "noshare") { ?>
            <script>setTimeout(()=>{location.href='.?logout=true'}, 5000);</script>
            <h4 l10n='catch.noshare'></h4>
        <?php
        }
        elseif ($c == "block") { ?>
            <script>setTimeout(()=>{location.href='.'}, 10000);</script>
            <h4 style="line-height:1.5" l10n='catch.block'></h4>
        <?php
        }
        elseif ($c == "error") { ?>
            <script>setTimeout(()=>{location.href='.'}, 2000);</script>
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