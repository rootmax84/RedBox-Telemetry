<!DOCTYPE html>
<html lang="en">
    <head>
    <?php include("head.php");?>
    </head>
    <body>
            <div class="login" id="login-form" style="width:400px; text-align:center; margin:5% auto; transition:.5s; opacity:0;">
	    <?php
		if ($_GET['c'] == "disabled") {
		    header("Refresh:5; url=/?logout");
	    ?>
		<h4>Account is disabled.</h4>
	    <?php
	    }
	    else if ($_GET['c'] == "loginfailed") {
		    header("Refresh:2; url=/");
	    ?>
		<h4>Wrong login, password or code!</h4>
	    <?php
	    }
	    ?>
        </div>
   <script>
    $(document).ready(function(){
     $("#login-form").css({"opacity":"1", "margin":"10% auto"});
    });
   </script>
 </body>
</html>
