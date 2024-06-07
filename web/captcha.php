<?php
#required php-gd module

# die on direct call
if (!isset($_GET["r"])) {
 die;
}

if (!isset($_SESSION)) { session_start(); }

$captcha_num = rand(1000, 9999);
$_SESSION['code'] = $captcha_num;

$font_size = 20;
$img_width = 70;
$img_height = 45;

header('Content-type: image/jpeg');

$image = imagecreate($img_width, $img_height); // create background image with dimensions
imagecolorallocate($image, 255, 255, 255); // set background color

$text_color = imagecolorallocate($image, 128, 0, 0); // set captcha text color

imagettftext($image, $font_size, 10, 10, 35, $text_color, './static/captcha.ttf', $captcha_num);

for ($x=1; $x<=5; $x++)  {
 imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
}

imagejpeg($image, NULL, 100);
imagedestroy($image);
?>