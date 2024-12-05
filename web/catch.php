<?php include("head.php");?>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh">
        <div class="login login-form" id="login-form" style="width:fit-content; text-align:center">
	<?php
	    if ($_GET['c'] == "disabled") { ?>
		<script>setTimeout(()=>{location.href='.?logout=true'}, 5000);</script>
		<h4 l10n='catch.disabled'></h4>
	    <?php
	    }
	    else if ($_GET['c'] == "loginfailed") { http_response_code(401); ?>
		<script>setTimeout(()=>{location.href='.'}, 2000);</script>
		<h4 l10n='catch.loginfailed'></h4>
	    <?php
	    }
	    else if ($_GET['c'] == "csrffailed") { http_response_code(401); ?>
		<script>setTimeout(()=>{location.href='.'}, 2000);</script>
		<h4 l10n='catch.csrf'></h4>
	    <?php
	    }
	    else if ($_GET['c'] == "toomanyattempts") { http_response_code(401); ?>
		<script>setTimeout(()=>{location.href='.'}, 5000);</script>
		<h4 style="line-height:1.5" l10n='catch.banned'></h4>
	    <?php
	    }
	    else if ($_GET['c'] == "dberror") { http_response_code(503); ?>
		<script>setTimeout(()=>{location.href='.'}, 10000);</script>
		<h4 l10n='catch.dberror'></h4>
	    <?php
	    }
	    else if ($_GET['c'] == "maintenance") { http_response_code(423); ?>
		<script>setTimeout(()=>{location.href='.'}, 10000);</script>
		<h4 style="line-height:1.5" l10n='catch.maintenance'></h4>
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
