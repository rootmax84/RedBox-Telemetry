<?php include("head.php");?>
    <body style="display:flex; justify-content:center; align-items:center; height:100vh">
            <div class="login login-form" id="login-form" style="width:fit-content; text-align:center">
	<?php
		if ($_GET['c'] == "disabled") { ?>
		    <script>setTimeout(()=>{location.href='.?logout=true'}, 5000);</script>
		<h4>Account is disabled.</h4>
	    <?php
	    }
	    else if ($_GET['c'] == "loginfailed") { http_response_code(401); ?>
		    <script>setTimeout(()=>{location.href='.'}, 2000);</script>
		<h4>Wrong login or password!</h4>
	    <?php
	    }
	    else if ($_GET['c'] == "csrffailed") { http_response_code(401); ?>
		    <script>setTimeout(()=>{location.href='.'}, 2000);</script>
		<h4>CSRF check failed!</h4>
	    <?php
	    }
	    else if ($_GET['c'] == "toomanyattempts") { http_response_code(401); ?>
		    <script>setTimeout(()=>{location.href='.'}, 5000);</script>
		<h4 style="line-height:1.5">Too many failed login attempts.<br> Please try again in 5 minutes.</h4>
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
