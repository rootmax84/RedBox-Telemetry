<?php
// Backward compatible link: MCP is now part of the unified settings screen.
// No redirect for unauthenticated users; normal /settings authentication applies.
header('Location: /settings?tab=mcp', true, 302);
exit;
