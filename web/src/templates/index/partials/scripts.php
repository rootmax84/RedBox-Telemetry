<script>
window.APP_CONFIG = <?php echo json_encode([
    'sessionId'         => $session_id ?? null,
    'sessionDate'       => isset($session_id) ? ($seshdates[$session_id] ?? '') : '',
    'hasSession'        => isset($session_id) && $session_id !== '',
    'showSessionLength' => (bool)($show_session_length ?? true),
    'streamLock'        => (int)($stream_lock ?? 0),
    'uid'               => $_SESSION['uid'] ?? null,
    'isAdmin'           => isset($_SESSION['admin']),
    'lang'              => $lang ?? 'en',
    'itime'             => $itime ?? '',
    'imapdata'          => $imapdata ?? '',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo version_url('static/js/index.js'); ?>"></script>
