<div <?php if($imapdata) { ?> class="pure-g split-container" <?php } ?>>
  <div <?php if($imapdata) { ?> class="pure-u-md-1-2 pane left" <?php } ?>>
    <!-- Chart Block -->
    <div id="Chart-Container" class="row center-block" style="z-index:1;position:relative;">
    <?php   if ( $var1 <> "" ) { ?>
    <div class="demo-container">
    <div id="placeholder" class="demo-placeholder"></div>
    </div>
    <?php   } else { ?>
    <div class="chart-label">
    <span class="label label-warning">. . .</span>
    </div>
    <?php   } ?>
    </div>
  </div>
  <div class="resizer"></div>
<?php if ($imapdata) { ?>
 <div class="pure-u-md-1-2 pane right">
    <!-- MAP -->
    <div id="map-div"><div class="row center-block map-container" id="map"></div></div>
  </div>
<?php } else { ?>
    <div id="nogps"></div>
<?php   } ?>
</div>
