    <div class="row center-block" style="padding-bottom:22px;">
      <!-- Filter the session list by year and month -->
      <form method="post" class="form-horizontal" action="url.php?id=<?php echo $session_id; ?>">
        <table style="width:100%">
          <tr>
            <!-- Profile Filter -->
            <td style="width:22%">
              <select id="selprofile" name="selprofile" class="form-control">
                <option value="" disabled selected l10n="sel.profile"></option>
                <option style="text-align:center" value="ALL"<?php if ($filterprofile == "ALL") echo ' selected'; ?> l10n="profile.any"></option>
                <?php $i = 0; ?>
                <?php while(isset($profilearray[$i])) { ?>
                  <option value="<?php echo $profilearray[$i]; ?>"<?php if ($filterprofile == $profilearray[$i]) echo ' selected'; ?>><?php echo $profilearray[$i]; ?></option>
                  <?php $i = $i + 1; ?>
                <?php } ?>
              </select>
            </td>
            <td style="width:1%"></td>
            <!-- Year Filter -->
            <td style="width:22%">
              <select id="selyear" name="selyear" class="form-control">
                <option value="" disabled selected l10n="sel.year"></option>
                <option style="text-align:center" value="ALL"<?php if ($filteryear == "ALL") echo ' selected'; ?> l10n="year.any"></option>
                <?php $i = 0; ?>
                <?php while(isset($yeararray[$i])) { ?>
                  <option value="<?php echo $yeararray[$i]; ?>"<?php if ($filteryear == $yeararray[$i]) echo ' selected'; ?>><?php echo $yeararray[$i]; ?></option>
                  <?php $i = $i + 1; ?>
                <?php } ?>
              </select>
            </td>
            <td style="width:1%"></td>
            <!-- Month Filter -->
            <td style="width:22%">
              <select id="selmonth" name="selmonth" class="form-control">
                <option value="" disabled selected l10n="sel.month"></option>
                <option style="text-align:center" value="ALL"<?php if ($filtermonth == "ALL") echo ' selected'; ?> l10n="month.any"></option>
                <option value="January"<?php if ($filtermonth == "January") echo ' selected'; ?> l10n="month.jan"></option>
                <option value="February"<?php if ($filtermonth == "February") echo ' selected'; ?> l10n="month.feb"></option>
                <option value="March"<?php if ($filtermonth == "March") echo ' selected'; ?> l10n="month.mar"></option>
                <option value="April"<?php if ($filtermonth == "April") echo ' selected'; ?> l10n="month.apr"></option>
                <option value="May"<?php if ($filtermonth == "May") echo ' selected'; ?> l10n="month.may"></option>
                <option value="June"<?php if ($filtermonth == "June") echo ' selected'; ?> l10n="month.jun"></option>
                <option value="July"<?php if ($filtermonth == "July") echo ' selected'; ?> l10n="month.jul"></option>
                <option value="August"<?php if ($filtermonth == "August") echo ' selected'; ?> l10n="month.aug"></option>
                <option value="September"<?php if ($filtermonth == "September") echo ' selected'; ?> l10n="month.sep"></option>
                <option value="October"<?php if ($filtermonth == "October") echo ' selected'; ?> l10n="month.oct"></option>
                <option value="November"<?php if ($filtermonth == "November") echo ' selected'; ?> l10n="month.nov"></option>
                <option value="December"<?php if ($filtermonth == "December") echo ' selected'; ?> l10n="month.dec"></option>
              </select>
            </td>
          </tr>
        </table>
      </form><br>
      <!-- Session Select Drop-Down List -->
    <form method="post" class="form-horizontal" action="url.php" id="sessionForm">
      <select id="seshidtag" name="seshidtag" class="form-control">
        <?php foreach ($seshdates as $dateid => $datestr) { ?>
          <option value="<?php echo $dateid; ?>"<?php if ($dateid == $session_id) echo ' selected'; ?>>
            <?php
              echo $datestr;
              echo $seshprofile[$dateid];
              if ($show_session_length) { echo $seshsizes[$dateid]; }
              echo $seship[$dateid];
              echo $sesactive[$dateid];
              if ($dateid == $session_id) echo $translations[$lang]['get.sess.curr'];
            ?>
          </option>
          <?php if ($dateid == $session_id && $sesfavorite[$dateid] == 1) { ?>
            <script>$('.favorite').addClass('favorite-en')</script>
          <?php } ?>
        <?php } ?>
      </select>

      <input type="hidden" name="selprofile" id="hiddenProfile" value="<?php echo htmlspecialchars($filterprofile); ?>">
      <input type="hidden" name="selyear" id="hiddenYear" value="<?php echo htmlspecialchars($filteryear); ?>">
      <input type="hidden" name="selmonth" id="hiddenMonth" value="<?php echo htmlspecialchars($filtermonth); ?>">
    </form>
    </div>
