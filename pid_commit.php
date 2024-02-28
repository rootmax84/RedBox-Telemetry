<?php

require_once('db.php');
require_once('auth_user.php');

if(!empty($_POST)) {
  //database settings
  foreach($_POST as $field_name => $val) {
    //clean post values
    $field_id = strip_tags(trim($field_name));
    $val = strip_tags(trim($val));

    //from the fieldname:id we need to get id
    $split_data = explode(':', $field_id);
    $id = $split_data[1];
    $field_name = $split_data[0];
    if(!empty($id) && !empty($field_name) && isset($val)) {
      if($field_name == 'populated' || $field_name == 'stream' || $field_name == 'favorite') {
        if($val == 'true'){
          $val=1;
          $query = "ALTER TABLE $db_table ADD IF NOT EXISTS ".quote_name($id)." float NOT NULL DEFAULT '0'"; //add enabled column
          $db->query($query);
        } else {
          $val=0;
       }
      }
      //update/delete the values in keys table
    if(strpos($val,"delete") !== false){
      $query = "DELETE FROM $db_pids_table WHERE id = ?";
      $db->execute_query($query, [$id]);
      $query = "ALTER TABLE $db_table DROP IF EXISTS ".quote_name($id); //delete disabled column
      $db->query($query);
    }
    else{
      $query = "UPDATE $db_pids_table SET ".quote_name($field_name)." = ".quote_value($val)." WHERE id = ?";
      $db->execute_query($query, [$id]);
    }
    echo "Updated";
    } else {
      echo "Invalid Requests";
    }
  }
} else {
  echo "Invalid Requests";
}

$db->close();
?>
