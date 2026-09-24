    <div class="row center-block" style="padding-bottom:18px;">

<!--Live DATA -->
<p class="divided" onclick="dataToggle()">
 <span class="tlue" l10n="stream"></span>
 <span class="divider"></span>
 <span class="toggle" id="data_toggle" l10n="expand"></span>
 <?php if ($stream_lock) { echo '<span class="stream-lock-container"><span class="stream-lock"></span></span>'; } ?>
</p>
<div id="data" style="display:none">
        <table class="table live-data" style="width:410px;font-size:0.875em;margin:0 auto;">
          <thead>
	<tr>
	  <th l10n="stream.name"></th>
	  <th l10n="stream.val"></th>
	  <th l10n="stream.unit"></th>
	</tr>
          </thead>
           <tbody id="stream"><tr><td colspan="3" style="text-align:center"><span class="label label-success" l10n="stream.fetch.label"></span></td></tr>
          </tbody>
        </table>
</div>
    </div>
    <div class="row center-block" style="padding-bottom:18px;text-align:center;">
