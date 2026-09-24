    <!-- Variable Select Block -->
    <div class="row center-block" style="padding-bottom:10px;">
      <select multiple id="plot_data">
        <?php $fav_selected_count = 0;
            foreach ($coldata as $xcol) {
        ?>
        <option value="<?php echo $xcol['colname']; ?>" <?php
            $is_matched = false;
            $i = 1;
            while (isset(${'var' . $i})) {
                if (${'var' . $i} == $xcol['colname']) {
                    $is_matched = true;
                    break;
                }
                $i++;
            }
            if ($is_matched) {
                echo ' selected';
            } elseif ($xcol['colfavorite'] == 1 && $fav_selected_count < 10) {
                echo ' selected';
                $fav_selected_count++;
            }
            ?>><?php echo $xcol['colcomment']; ?></option>
        <?php } ?>
      </select>
    </div>
