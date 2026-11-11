<aside id="stream-panel" class="stream-panel" hidden aria-label="<?php echo escapeHtml($translations[$lang]['stream']); ?>">
  <p class="divided stream-bottom-heading">
    <span class="tlue" l10n="stream"></span>
    <span class="divider" aria-hidden="true"></span>
  </p>
  <div id="data" tabindex="0" role="region" aria-label="<?php echo escapeHtml($translations[$lang]['stream']); ?>">
    <table class="table live-data">
      <thead><tr><th l10n="stream.name"></th><th l10n="stream.val"></th><th l10n="stream.unit"></th></tr></thead>
      <tbody id="stream"></tbody>
    </table>
  </div>
</aside>
