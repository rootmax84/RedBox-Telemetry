<?php

// Function to count uppercase strings
function substri_count($haystack, $needle) {
    return substr_count(strtoupper($haystack), strtoupper($needle));
}

// Calculate average
function average($arr)
{
    if (!count($arr)) return 0;

    $sum = 0.00;
    for ($i = 0; $i < count($arr); $i++)
    {
        $sum += $arr[$i];
    }

    return $sum / count($arr);
}

//Pressure Conversion
function pressure_conv($val, $unit, $id) {
    switch($unit) {
     case "Psi to Bar":
	$tmp = $id != "RedManage" ? $val/14.504 : $val;
     break;
     case "Bar to Psi":
	$tmp = $val*14.504;
     break;
     default:
	$tmp = $val;
     break;
 }
 return round($tmp,2);
}

//Speed Conversion
function speed_conv($val, $unit, $id) {
    switch($unit) {
     case "km to miles":
	$tmp = $val*0.621371;
     break;
     case "miles to km":
	$tmp = $id != "RedManage" ? $val*1.609344 : $val;
     break;
     default:
	$tmp = $val;
     break;
 }
 return round($tmp,1);
}

//Temperature Conversion
function temp_conv($val, $unit, $id) {
    switch($unit) {
     case "Celsius to Fahrenheit":
	$tmp = $val*9.0/5.0+32.0;
     break;
     case "Fahrenheit to Celsius":
	$tmp = $id != "RedManage" ? ($val-32.0)*5.0/9.0 : $val;
     break;
     default:
	$tmp = $val;
     break;
 }
 return round($tmp,1);
}
?>
