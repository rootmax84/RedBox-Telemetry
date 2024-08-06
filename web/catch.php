<!DOCTYPE html>
<html lang="en">
    <head>
    <?php include("head.php");?>
    </head>
    <body>
            <div class="login login-form" id="login-form" style="width:400px; text-align:center; margin:5% auto; transition:.5s; opacity:0;">
	<?php
		if ($_GET['c'] == "disabled") { ?>
		    <script>setTimeout(()=>{location.href='.?logout=true'}, 5000);</script>
		<h4>Account is disabled.</h4>
	    <?php
	    }
	    else if ($_GET['c'] == "loginfailed") { http_response_code(401); ?>
		    <script>setTimeout(()=>{location.href='.'}, 2000);</script>
		<h4>Wrong login, password or code!</h4>
	    <?php
	    }
	    ?>
        </div>
    <div class="login-background"></div>
   <script>
    $(document).ready(function(){
     $("#login-form").css({"opacity":"1", "margin":"10% auto"});
    });
   </script>
 </body>
</html>
